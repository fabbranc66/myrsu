export function createProjectionPointer(viewer) {
  const marker = document.createElement('span');
  marker.className = 'projection-pointer';
  marker.setAttribute('aria-hidden', 'true');
  marker.hidden = true;
  const highlight = document.createElement('span');
  highlight.className = 'projection-highlight';
  highlight.setAttribute('aria-hidden', 'true');
  highlight.hidden = true;
  let enabled = false;
  let position = { pointer_page: 1, pointer_x: 0.5, pointer_y: 0.1, highlight_x: null, highlight_y: null, highlight_page: null, highlight_end_x: null, highlight_end_y: null };
  let lastClient = null;

  function render() {
    const canvas = viewer.querySelector(`.pdf-page[data-page-number="${position.pointer_page}"]`);
    if (!enabled || !canvas) {
      marker.hidden = true;
      highlight.hidden = true;
      return;
    }
    if (marker.parentNode !== viewer) viewer.appendChild(marker);
    if (highlight.parentNode !== viewer) viewer.appendChild(highlight);
    marker.style.left = `${canvas.offsetLeft + canvas.offsetWidth * position.pointer_x}px`;
    marker.style.top = `${canvas.offsetTop + canvas.offsetHeight * position.pointer_y}px`;
    marker.hidden = false;
    const highlightCanvas = viewer.querySelector(`.pdf-page[data-page-number="${position.highlight_page ?? position.pointer_page}"]`);
    highlight.hidden = position.highlight_x === null || position.highlight_y === null || !highlightCanvas;
    if (!highlight.hidden) {
      const endX = position.highlight_end_x ?? position.pointer_x;
      const endY = position.highlight_end_y ?? position.pointer_y;
      highlight.style.left = `${highlightCanvas.offsetLeft + highlightCanvas.offsetWidth * Math.min(position.highlight_x, endX)}px`;
      highlight.style.top = `${highlightCanvas.offsetTop + highlightCanvas.offsetHeight * Math.min(position.highlight_y, endY)}px`;
      highlight.style.width = `${highlightCanvas.offsetWidth * Math.abs(endX - position.highlight_x)}px`;
      highlight.style.height = `${highlightCanvas.offsetHeight * Math.abs(endY - position.highlight_y)}px`;
    }
  }

  function capture(clientX, clientY, nearest = false) {
    const bounds = viewer.getBoundingClientRect();
    if (clientX < bounds.left || clientX > bounds.right || clientY < bounds.top || clientY > bounds.bottom) return false;
    const pages = [...viewer.querySelectorAll('.pdf-page')].map((canvas) => ({ canvas, rect: canvas.getBoundingClientRect() }));
    let selected = pages.find(({ rect }) => clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom);
    if (!selected && nearest) {
      selected = pages.filter(({ rect }) => rect.bottom > bounds.top && rect.top < bounds.bottom)
        .sort((first, second) => Math.abs(clientY - (first.rect.top + first.rect.bottom) / 2) - Math.abs(clientY - (second.rect.top + second.rect.bottom) / 2))[0];
    }
    if (!selected || selected.rect.width <= 0 || selected.rect.height <= 0) return false;
    const next = {
      pointer_page: Number(selected.canvas.dataset.pageNumber),
      pointer_x: Math.round(Math.max(0, Math.min(1, (clientX - selected.rect.left) / selected.rect.width)) * 1000000) / 1000000,
      pointer_y: Math.round(Math.max(0, Math.min(1, (clientY - selected.rect.top) / selected.rect.height)) * 1000000) / 1000000,
    };
    const changed = Object.keys(next).some((field) => next[field] !== position[field]);
    position = { ...position, ...next };
    lastClient = { x: clientX, y: clientY };
    enabled = true;
    render();
    return changed;
  }

  function ensureVisible({ horizontal = true } = {}) {
    const canvas = viewer.querySelector(`.pdf-page[data-page-number="${position.pointer_page}"]`);
    if (!enabled || !canvas) return;
    const pointX = canvas.offsetLeft + canvas.offsetWidth * position.pointer_x;
    const pointY = canvas.offsetTop + canvas.offsetHeight * position.pointer_y;
    if (pointY < viewer.scrollTop + 24 || pointY > viewer.scrollTop + viewer.clientHeight - 24) {
      viewer.scrollTop = Math.max(0, pointY - viewer.clientHeight / 2);
    }
    if (horizontal && (pointX < viewer.scrollLeft + 24 || pointX > viewer.scrollLeft + viewer.clientWidth - 24)) {
      viewer.scrollLeft = Math.max(0, pointX - viewer.clientWidth / 2);
    }
  }

  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(render).observe(viewer);
  return {
    capture,
    render,
    ensureVisible,
    startHighlight() {
      position.highlight_page = null;
      position.highlight_end_x = null;
      position.highlight_end_y = null;
      position.highlight_x = position.pointer_x;
      position.highlight_y = position.pointer_y;
      render();
    },
    freezeHighlight() {
      if (position.highlight_x === null || position.highlight_y === null) return;
      position.highlight_page = position.pointer_page;
      position.highlight_end_x = position.pointer_x;
      position.highlight_end_y = position.pointer_y;
      render();
    },
    clearHighlight() {
      const changed = position.highlight_x !== null || position.highlight_y !== null;
      position.highlight_x = null;
      position.highlight_y = null;
      position.highlight_page = null;
      position.highlight_end_x = null;
      position.highlight_end_y = null;
      if (changed) render();
      return changed;
    },
    differs(state) {
      return position.pointer_page !== Number(state.pointer_page ?? 1)
        || position.pointer_x !== Number(state.pointer_x ?? 0.5)
        || position.pointer_y !== Number(state.pointer_y ?? 0.1)
        || position.highlight_x !== (state.highlight_x == null ? null : Number(state.highlight_x))
        || position.highlight_y !== (state.highlight_y == null ? null : Number(state.highlight_y))
        || position.highlight_page !== (state.highlight_page == null ? null : Number(state.highlight_page))
        || position.highlight_end_x !== (state.highlight_end_x == null ? null : Number(state.highlight_end_x))
        || position.highlight_end_y !== (state.highlight_end_y == null ? null : Number(state.highlight_end_y));
    },
    apply(state, follow = false) {
      enabled = Boolean(state.document_id);
      position = {
        pointer_page: Number(state.pointer_page ?? 1),
        pointer_x: Number(state.pointer_x ?? 0.5),
        pointer_y: Number(state.pointer_y ?? 0.1),
        highlight_x: state.highlight_x == null ? null : Number(state.highlight_x),
        highlight_y: state.highlight_y == null ? null : Number(state.highlight_y),
        highlight_page: state.highlight_page == null ? null : Number(state.highlight_page),
        highlight_end_x: state.highlight_end_x == null ? null : Number(state.highlight_end_x),
        highlight_end_y: state.highlight_end_y == null ? null : Number(state.highlight_end_y),
      };
      lastClient = null;
      render();
      if (follow) ensureVisible({ horizontal: false });
    },
    captureAfterScroll() {
      const bounds = viewer.getBoundingClientRect();
      const point = lastClient || { x: bounds.left + bounds.width / 2, y: bounds.top + Math.min(120, bounds.height / 2) };
      return capture(point.x, point.y, true);
    },
    snapshot() { return { ...position }; },
    hide() { enabled = false; marker.hidden = true; highlight.hidden = true; },
  };
}
