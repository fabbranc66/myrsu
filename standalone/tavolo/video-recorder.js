import { uploadAttachment } from './attachments.js?v=20260802-room-1';

const modal = document.querySelector('#videoModal');
const preview = document.querySelector('#videoPreview');
const timer = document.querySelector('#videoTimer');
const startButton = document.querySelector('#startVideo');
const stopButton = document.querySelector('#stopVideo');
let stream = null;
let recorder = null;
let chunks = [];
let interval = null;
let startedAt = 0;
let cancelled = false;

document.querySelector('#recordVideo').addEventListener('click', openCamera);
document.querySelector('#closeVideo').addEventListener('click', closeCamera);
startButton.addEventListener('click', startRecording);
stopButton.addEventListener('click', stopRecording);

async function openCamera() {
  try {
    cancelled = false;
    stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}, audio: true});
    preview.srcObject = stream;
    timer.textContent = '00:00 / 00:30';
    startButton.disabled = false;
    stopButton.disabled = true;
    modal.showModal();
  } catch (error) {
    alert(error.message || 'Fotocamera non disponibile.');
  }
}

function startRecording() {
  const mimeType = supportedMimeType();
  chunks = [];
  recorder = mimeType ? new MediaRecorder(stream, {mimeType}) : new MediaRecorder(stream);
  recorder.addEventListener('dataavailable', (event) => {
    if (event.data.size) chunks.push(event.data);
  });
  recorder.addEventListener('stop', saveRecording);
  recorder.start(1000);
  startedAt = Date.now();
  startButton.disabled = true;
  stopButton.disabled = false;
  interval = setInterval(updateTimer, 250);
}

function stopRecording() {
  if (recorder?.state === 'recording') recorder.stop();
}

async function saveRecording() {
  clearInterval(interval);
  stopButton.disabled = true;
  if (cancelled) return;
  const type = recorder.mimeType || 'video/webm';
  await uploadAttachment(new File(chunks, `video-${Date.now()}.${videoExtension(type)}`, {type}));
  closeCamera();
}

function updateTimer() {
  const seconds = Math.min(30, Math.floor((Date.now() - startedAt) / 1000));
  timer.textContent = `00:${String(seconds).padStart(2, '0')} / 00:30`;
  if (seconds >= 30) stopRecording();
}

function closeCamera() {
  cancelled = true;
  clearInterval(interval);
  if (recorder?.state === 'recording') recorder.stop();
  stream?.getTracks().forEach((track) => track.stop());
  preview.srcObject = null;
  if (modal.open) modal.close();
}

function supportedMimeType() {
  return ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4']
    .find((type) => MediaRecorder.isTypeSupported(type)) || '';
}

function videoExtension(type) {
  return type.includes('mp4') ? 'mp4' : 'webm';
}
