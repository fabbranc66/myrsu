import { api, refreshRoomTimeline } from './app.js?v=20260802-room-1';

const input = document.querySelector('#attachmentInput');
const attachButton = document.querySelector('#attachFile');
const audioButton = document.querySelector('#recordAudio');
let recorder = null;
let stream = null;
let chunks = [];

attachButton.addEventListener('click', () => input.click());
input.addEventListener('change', async () => {
  const file = input.files?.[0];
  if (!file) return;
  await uploadAttachment(file);
  input.value = '';
});

audioButton.addEventListener('click', async () => {
  if (recorder?.state === 'recording') {
    recorder.stop();
    return;
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({audio: true});
    chunks = [];
    recorder = new MediaRecorder(stream);
    recorder.addEventListener('dataavailable', (event) => {
      if (event.data.size) chunks.push(event.data);
    });
    recorder.addEventListener('stop', async () => {
      audioButton.classList.remove('recording');
      stream?.getTracks().forEach((track) => track.stop());
      const type = recorder.mimeType || 'audio/webm';
      await uploadAttachment(new File(chunks, `audio-${Date.now()}.${audioExtension(type)}`, {type}));
    });
    recorder.start();
    audioButton.classList.add('recording');
  } catch (error) {
    alert(error.message || 'Microfono non disponibile.');
  }
});

export async function uploadAttachment(file) {
  if (file.type.startsWith('video/') && await videoDuration(file) > 30) {
    alert('Il video può durare massimo 30 secondi.');
    return;
  }
  const form = new FormData();
  form.append('attachment', file);
  const textarea = document.querySelector('#messageForm textarea');
  if (textarea.value.trim()) form.append('caption', textarea.value.trim());
  attachButton.disabled = true;
  audioButton.disabled = true;
  try {
    await api('/room-access/attachments', {method: 'POST', body: form});
    textarea.value = '';
    await refreshRoomTimeline();
  } catch (error) {
    alert(error.message);
  } finally {
    attachButton.disabled = false;
    audioButton.disabled = false;
  }
}

function videoDuration(file) {
  return new Promise((resolve, reject) => {
    const video = document.createElement('video');
    const url = URL.createObjectURL(file);
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
      URL.revokeObjectURL(url);
      resolve(video.duration);
    };
    video.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Video non leggibile.'));
    };
    video.src = url;
  });
}

function audioExtension(type) {
  if (type.includes('mp4')) return 'm4a';
  if (type.includes('ogg')) return 'ogg';
  return 'webm';
}
