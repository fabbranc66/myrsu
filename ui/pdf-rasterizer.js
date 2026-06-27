let pdfJsPromise = null;

function pdfJs() {
  if (!pdfJsPromise) {
    pdfJsPromise = import('./vendor/pdfjs/pdf.mjs').then((library) => {
      library.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';
      return library;
    });
  }
  return pdfJsPromise;
}

async function rasterizePdf(file, onProgress) {
  const library = await pdfJs();
  const assetBase = new URL('./vendor/pdfjs/', document.baseURI).href;
  const task = library.getDocument({
    data: await file.arrayBuffer(),
    cMapUrl: `${assetBase}cmaps/`,
    cMapPacked: true,
    standardFontDataUrl: `${assetBase}standard_fonts/`,
    wasmUrl: `${assetBase}wasm/`,
  });
  const source = await task.promise;
  const { jsPDF } = window.jspdf;
  let output = null;

  for (let pageNumber = 1; pageNumber <= source.numPages; pageNumber += 1) {
    const page = await source.getPage(pageNumber);
    const viewport = page.getViewport({ scale: 2 });
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);
    const context = canvas.getContext('2d', { alpha: false });
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    await page.render({ canvasContext: context, viewport }).promise;

    const orientation = canvas.width > canvas.height ? 'landscape' : 'portrait';
    if (!output) {
      output = new jsPDF({ orientation, unit: 'px', format: [canvas.width, canvas.height], hotfixes: ['px_scaling'] });
    } else {
      output.addPage([canvas.width, canvas.height], orientation);
    }
    output.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, canvas.width, canvas.height, undefined, 'FAST');
    page.cleanup();
    onProgress(Math.round((pageNumber / source.numPages) * 30));
  }

  await task.destroy();
  if (!output) throw new Error('PDF senza pagine.');
  return output.output('blob');
}

window.MyRsuPdfRasterizer = { rasterizePdf };
