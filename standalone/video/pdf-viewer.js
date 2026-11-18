export function createProjectionPdfViewer(viewer, onError) {
  let currentPdf = null;
  let loading = null;
  let pages = [];
  let zoom = 1;
  let generation = 0;
  let frame = null;
  let work = null;
  let task = null;
  let dirty = false;

  function invalidate() {
    generation++;
    dirty = true;
    task?.cancel();
  }

  function schedule() {
    invalidate();
    if (frame !== null || work) return;
    frame = requestAnimationFrame(() => {
      frame = null;
      flush().catch(onError);
    });
  }

  function setZoom(nextZoom) {
    zoom = nextZoom;
    for (const item of pages) {
      const fit = Math.min(2, Math.max(.55, (viewer.clientWidth - 16) / item.base.width));
      item.viewport = item.page.getViewport({scale: fit * zoom});
      item.wrapper.dataset.baseWidth = String(item.viewport.width / zoom);
      item.wrapper.dataset.baseHeight = String(item.viewport.height / zoom);
      item.wrapper.style.width = `${item.viewport.width}px`;
      item.wrapper.style.height = `${item.viewport.height}px`;
      if (item.region) {
        const factor = item.viewport.width / item.region.pageWidth;
        item.canvas.style.left = `${item.region.left * factor}px`;
        item.canvas.style.top = `${item.region.top * factor}px`;
        item.canvas.style.width = `${item.region.width * factor}px`;
        item.canvas.style.height = `${item.region.height * factor}px`;
      }
      item.key = null;
    }
    schedule();
  }

  async function draw(version) {
    const bounds = viewer.getBoundingClientRect();
    for (const item of pages) {
      if (version !== generation) return;
      const rect = item.wrapper.getBoundingClientRect();
      const left = Math.max(0, Math.floor(bounds.left - rect.left - 128));
      const top = Math.max(0, Math.floor(bounds.top - rect.top - 128));
      const right = Math.min(Math.ceil(item.viewport.width), Math.ceil(bounds.right - rect.left + 128));
      const bottom = Math.min(Math.ceil(item.viewport.height), Math.ceil(bounds.bottom - rect.top + 128));
      if (right <= left || bottom <= top) {
        item.canvas.hidden = true;
        if (item.canvas.width > 1) { item.canvas.width = 1; item.canvas.height = 1; }
        item.key = null;
        continue;
      }
      const width = right - left;
      const height = bottom - top;
      const density = Math.min(Math.max(1, window.devicePixelRatio || 1), Math.sqrt(8000000 / (width * height)), 8192 / Math.max(width, height));
      const key = [left, top, width, height, density, item.viewport.scale].join(':');
      if (item.key === key) continue;
      item.canvas.width = Math.max(1, Math.floor(width * density));
      item.canvas.height = Math.max(1, Math.floor(height * density));
      item.canvas.style.left = `${left}px`;
      item.canvas.style.top = `${top}px`;
      item.canvas.style.width = `${width}px`;
      item.canvas.style.height = `${height}px`;
      item.canvas.hidden = false;
      item.canvas.dataset.renderReady = 'false';
      item.canvas.dataset.renderZoom = String(zoom);
      item.canvas.dataset.renderDensity = String(density);
      item.region = {left, top, width, height, pageWidth: item.viewport.width};
      task = item.page.render({
        canvasContext: item.canvas.getContext('2d', {alpha: false}),
        viewport: item.viewport,
        transform: [density, 0, 0, density, -left * density, -top * density],
      });
      try {
        await task.promise;
        if (version === generation) {
          item.key = key;
          item.canvas.dataset.renderReady = 'true';
        }
      } catch (error) {
        if (error.name !== 'RenderingCancelledException') throw error;
      } finally {
        task = null;
      }
    }
  }

  function flush() {
    if (work) return work;
    work = (async () => {
      while (dirty) {
        dirty = false;
        await draw(generation);
      }
    })().finally(() => {
      work = null;
      if (dirty) schedule();
    });
    return work;
  }

  return {
    schedule,
    setZoom,
    async refresh() { invalidate(); return flush(); },
    async load(pdf, nextZoom) {
      if (pdf !== currentPdf) {
        currentPdf = pdf;
        invalidate();
        loading = (async () => {
          if (work) await work;
          const loaded = await Promise.all(Array.from({length: pdf.numPages}, (_, index) => pdf.getPage(index + 1)));
          if (pdf !== currentPdf) return;
          const fragment = document.createDocumentFragment();
          pages = loaded.map((page, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'pdf-page';
            wrapper.dataset.pageNumber = String(index + 1);
            const canvas = document.createElement('canvas');
            canvas.className = 'pdf-page-surface';
            canvas.width = 1;
            canvas.height = 1;
            wrapper.appendChild(canvas);
            fragment.appendChild(wrapper);
            return {page, wrapper, canvas, base: page.getViewport({scale: 1}), viewport: null, key: null, region: null};
          });
          viewer.replaceChildren(fragment);
        })();
      }
      await loading;
      if (pdf === currentPdf) setZoom(nextZoom);
    },
  };
}
