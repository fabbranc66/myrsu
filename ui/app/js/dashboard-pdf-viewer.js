import * as pdfjsLib from '../../vendor/pdfjs/pdf.mjs';

const assetBase = new URL('../../vendor/pdfjs/', import.meta.url).href;
pdfjsLib.GlobalWorkerOptions.workerSrc = `${assetBase}pdf.worker.mjs`;

export async function renderDashboardPdfDocument(item, viewer, addLink, signal) {
  const response = await fetch(item.url || '', {cache: 'no-store', signal});
  if (!response.ok) throw new Error('Documento non disponibile.');
  if (signal.aborted) return;
  const loadingTask = pdfjsLib.getDocument({
    data: await response.arrayBuffer(),
    cMapUrl: `${assetBase}cmaps/`, cMapPacked: true,
    standardFontDataUrl: `${assetBase}standard_fonts/`, wasmUrl: `${assetBase}wasm/`,
  });
  const pages = [];
  let timer;
  let painting = false;
  let pending = false;
  signal.addEventListener('abort', () => {
    clearTimeout(timer);
    for (const state of pages) state.task?.cancel();
    Promise.resolve(loadingTask.destroy()).catch(() => {});
  }, {once: true});
  const pdf = await loadingTask.promise;
  if (signal.aborted) return;
  viewer.replaceChildren();

  function layoutPage(state) {
    const width = Math.max(1, viewer.clientWidth - 16);
    if (state.viewport && Math.abs(state.viewport.width - width) < 0.5) return;
    state.viewport = state.page.getViewport({scale: width / state.page.getViewport({scale: 1}).width});
    state.scale = 0;
    state.wrap.style.width = `${state.viewport.width}px`;
    state.wrap.style.height = `${state.viewport.height}px`;
    state.canvas.style.width = `${state.viewport.width}px`;
    state.canvas.style.height = `${state.viewport.height}px`;
    state.wrap.replaceChildren(state.canvas);
    for (const annotation of state.annotations) {
      if ((annotation.url || annotation.unsafeUrl) && annotation.rect) addLink(state.wrap, state.viewport, annotation.rect, annotation.url || annotation.unsafeUrl);
    }
    if (state.number === 1 && item.verifyUrl) addLink(state.wrap, state.viewport, [502, 728, 554, 780], item.verifyUrl);
  }

  async function paintPage(state, zoom) {
    const viewport = state.viewport;
    const scale = Math.min(Math.max(1, window.devicePixelRatio || 1) * zoom, 8,
      Math.sqrt(8_388_608 / (viewport.width * viewport.height)),
      8192 / Math.max(viewport.width, viewport.height));
    if (Math.abs(state.scale - scale) < 0.01 || signal.aborted) return;
    const canvas = document.createElement('canvas');
    canvas.className = 'dashboard-pdf-page';
    canvas.width = Math.ceil(viewport.width * scale);
    canvas.height = Math.ceil(viewport.height * scale);
    canvas.style.width = `${viewport.width}px`;
    canvas.style.height = `${viewport.height}px`;
    state.task = state.page.render({canvasContext: canvas.getContext('2d'), viewport, transform: [scale, 0, 0, scale, 0, 0]});
    await state.task.promise;
    state.task = null;
    if (signal.aborted) return;
    state.canvas.replaceWith(canvas);
    state.canvas = canvas;
    state.scale = scale;
  }

  async function redraw() {
    pending = true;
    if (painting || signal.aborted) return;
    painting = true;
    try {
      while (pending && !signal.aborted) {
        pending = false;
        const visual = window.visualViewport;
        const zoom = Math.max(1, visual?.scale || 1);
        const bounds = viewer.getBoundingClientRect();
        const top = Math.max(bounds.top, visual?.offsetTop || 0);
        const bottom = Math.min(bounds.bottom, (visual?.offsetTop || 0) + (visual?.height || window.innerHeight));
        for (const state of pages) {
          if (signal.aborted) return;
          layoutPage(state);
          const rect = state.wrap.getBoundingClientRect();
          if (rect.bottom < top - viewer.clientHeight || rect.top > bottom + viewer.clientHeight) {
            state.canvas.width = state.canvas.height = 1;
            state.scale = 0;
            continue;
          }
          await paintPage(state, rect.bottom > top && rect.top < bottom ? zoom : 1);
        }
      }
    } finally {
      painting = false;
    }
  }

  function scheduleRedraw() {
    clearTimeout(timer);
    timer = setTimeout(() => {
      redraw().catch((error) => {
        if (!signal.aborted) viewer.textContent = error.message || 'Anteprima non disponibile.';
      });
    }, 120);
  }

  for (let number = 1; number <= pdf.numPages; number += 1) {
    if (signal.aborted) return;
    const page = await pdf.getPage(number);
    if (signal.aborted) return;
    const wrap = document.createElement('div');
    wrap.className = 'dashboard-pdf-page-wrap';
    const canvas = document.createElement('canvas');
    canvas.className = 'dashboard-pdf-page';
    canvas.width = canvas.height = 1;
    const annotations = await page.getAnnotations({intent: 'display'});
    if (signal.aborted) return;
    const state = {number, page, wrap, canvas, annotations, viewport: null, task: null, scale: 0};
    pages.push(state);
    layoutPage(state);
    viewer.appendChild(wrap);
  }
  if (signal.aborted) return;
  viewer.addEventListener('scroll', scheduleRedraw, {passive: true, signal});
  window.addEventListener('resize', scheduleRedraw, {passive: true, signal});
  window.visualViewport?.addEventListener('resize', scheduleRedraw, {passive: true, signal});
  window.visualViewport?.addEventListener('scroll', scheduleRedraw, {passive: true, signal});
  await redraw();
}
