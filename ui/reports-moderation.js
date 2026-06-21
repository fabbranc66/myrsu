const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const reportsTable = document.querySelector('#reportsTable');
const statusFilter = document.querySelector('#statusFilter');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const reportModal = document.querySelector('#reportModal');
const reportModalTitle = document.querySelector('#reportModalTitle');
const reportModalMeta = document.querySelector('#reportModalMeta');
const reportModalMessage = document.querySelector('#reportModalMessage');
const closeReportModal = document.querySelector('#closeReportModal');
const initialStatus = new URLSearchParams(window.location.search).get('status');
let reports = [];

if (!token) window.location.href = 'app/index.html';
if (initialStatus) statusFilter.value = initialStatus;

async function api(path, options = {}) {
  const headers = options.headers || {};
  headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadReports() {
  reports = await api(`/reports?status=${encodeURIComponent(statusFilter.value)}`);
  reportsTable.innerHTML = reports.map(row).join('');
}

function row(report) {
  return `<tr>
    <td>${report.tracking_code}</td>
    <td>${report.subject}</td>
    <td>${report.origin === 'member' ? (report.author_name || 'membro') : 'anonima'}</td>
    <td>${translateStatus(report.status)}</td>
    <td>${report.protocol_number || '-'}</td>
    <td class="actions-cell">
      <button class="icon-action" data-approve="${report.id}" title="Approva">${MyRsuIcons.get('active')}</button>
      <button class="icon-action danger" data-reject="${report.id}" title="Respingi">${MyRsuIcons.get('suspended')}</button>
      <button class="icon-action" data-view="${report.id}" title="Visualizza">${MyRsuIcons.get('eye')}</button>
      ${report.document_id ? `<a class="icon-action" href="document-view.html?id=${report.document_id}" title="Documento">${MyRsuIcons.get('download')}</a>` : ''}
    </td>
  </tr>`;
}

function translateStatus(status) {
  return { pending: 'da moderare', approved: 'approvata', rejected: 'respinta' }[status] || status;
}

reportsTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  if (button.dataset.view) {
    showReport(Number(button.dataset.view));
    return;
  }
  const id = button.dataset.approve || button.dataset.reject;
  const status = button.dataset.approve ? 'approved' : 'rejected';
  await api(`/reports/${id}/moderation`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  });
  message.textContent = status === 'approved' ? 'Segnalazione approvata e protocollata' : 'Segnalazione respinta';
  await loadReports();
});

function showReport(id) {
  const report = reports.find((item) => Number(item.id) === id);
  if (!report) return;
  reportModalTitle.textContent = report.subject;
  reportModalMeta.textContent = `${report.tracking_code} - ${translateStatus(report.status)}`;
  reportModalMessage.textContent = report.message;
  reportModal.showModal();
}

closeReportModal.addEventListener('click', () => {
  reportModal.close();
});

statusFilter.addEventListener('change', loadReports);
loadReports().catch((error) => { message.textContent = error.message; });
