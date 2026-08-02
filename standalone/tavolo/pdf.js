import * as pdfjsLib from '/myrsu/ui/vendor/pdfjs/pdf.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = '/myrsu/ui/vendor/pdfjs/pdf.worker.mjs';
let activeBlobUrl = null;

export async function openPdf(url, title, headers) {
  const modal=document.querySelector('#documentModal');const frame=document.querySelector('#documentFrame');const viewer=document.querySelector('#pdfViewer');document.querySelector('#documentTitle').textContent=title;modal.showModal();
  const response=await fetch(url,{headers});if(!response.ok)throw new Error('Documento non disponibile.');
  if(!matchMedia('(max-width:900px),(pointer:coarse)').matches){viewer.classList.add('hidden');frame.classList.remove('hidden');if(activeBlobUrl)URL.revokeObjectURL(activeBlobUrl);activeBlobUrl=URL.createObjectURL(await response.blob());frame.src=activeBlobUrl;return;}
  frame.src='';frame.classList.add('hidden');viewer.classList.remove('hidden');viewer.textContent='Caricamento...';
  const pdf=await pdfjsLib.getDocument({data:await response.arrayBuffer(),cMapUrl:'/myrsu/ui/vendor/pdfjs/cmaps/',cMapPacked:true,standardFontDataUrl:'/myrsu/ui/vendor/pdfjs/standard_fonts/',wasmUrl:'/myrsu/ui/vendor/pdfjs/wasm/'}).promise;viewer.innerHTML='';
  for(let number=1;number<=pdf.numPages;number+=1){const page=await pdf.getPage(number);const original=page.getViewport({scale:1});const viewport=page.getViewport({scale:Math.max(.5,(viewer.clientWidth-16)/original.width)});const canvas=document.createElement('canvas');const wrap=document.createElement('div');canvas.width=Math.ceil(viewport.width);canvas.height=Math.ceil(viewport.height);canvas.className='pdf-page';wrap.className='pdf-page-wrap';wrap.style.width=`${canvas.width}px`;wrap.style.height=`${canvas.height}px`;wrap.appendChild(canvas);viewer.appendChild(wrap);await page.render({canvasContext:canvas.getContext('2d'),viewport}).promise;const annotations=await page.getAnnotations({intent:'display'});annotations.filter((item)=>(item.url||item.unsafeUrl)&&item.rect).forEach((item)=>addLink(wrap,viewport,item.rect,item.url||item.unsafeUrl));}
}

export function closePdf(){if(activeBlobUrl)URL.revokeObjectURL(activeBlobUrl);activeBlobUrl=null;document.querySelector('#documentFrame').src='';document.querySelector('#pdfViewer').innerHTML='';document.querySelector('#documentModal').close();}
function addLink(wrap,viewport,rect,url){const [x1,y1,x2,y2]=viewport.convertToViewportRectangle(rect);const button=document.createElement('button');button.className='pdf-link';button.dataset.verifyUrl=url;button.style.left=`${Math.min(x1,x2)}px`;button.style.top=`${Math.min(y1,y2)}px`;button.style.width=`${Math.abs(x2-x1)}px`;button.style.height=`${Math.abs(y2-y1)}px`;wrap.appendChild(button);}
