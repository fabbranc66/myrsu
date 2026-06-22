const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const reportForm = document.querySelector('#reportForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const antibotA = document.querySelector('#antibotA');
const antibotB = document.querySelector('#antibotB');
const antibotErrors = document.querySelector('#antibotErrors');
const antibotLabel = document.querySelector('#antibotLabel');

function initAntiBot() {
  const a = Math.floor(Math.random() * 8) + 2;
  const b = Math.floor(Math.random() * 8) + 2;
  antibotA.value = String(a);
  antibotB.value = String(b);
  antibotLabel.firstChild.textContent = `Controllo antibot: quanto fa ${a} + ${b}?`;
  reportForm.antibot_answer.value = '';
}

async function api(path, options = {}) {
  const headers = options.headers || {};
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

reportForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  try {
    const result = await api('/reports', {
      method: 'POST',
      body: new FormData(reportForm),
    });
    message.textContent = `Segnalazione inviata: ${result.report.tracking_code}`;
    reportForm.reset();
    antibotErrors.value = '0';
    initAntiBot();
  } catch (error) {
    message.textContent = error.message;
    if (error.message.includes('antibot')) {
      const errors = Number(antibotErrors.value || 0) + 1;
      antibotErrors.value = String(errors);
      if (errors >= 3) {
        message.textContent = 'Troppi errori antibot. Ricarica la pagina per riprovare.';
        reportForm.querySelectorAll('input, textarea, button').forEach((field) => {
          field.disabled = true;
        });
        return;
      }
    }
    initAntiBot();
  }
});

initAntiBot();
