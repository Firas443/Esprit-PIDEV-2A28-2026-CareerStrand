const API_BASE = '/Careerstrand/Controller/ApplicationController.php';
let allApps         = [];
let filterStatus    = '';
let currentDetailId = null;

document.body.append(
  document.getElementById('detailModal'),
  document.getElementById('toast')
);
closeModal('detailModal');

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = `toast ${type}`;
  void t.offsetWidth; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── LOAD ──
async function loadApplications() {
  const params = new URLSearchParams({ source: 'back' });
  if (filterStatus) params.set('status', filterStatus);
  try {
    const res         = await fetch(`${API_BASE}?${params}`);
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    allApps = json.data;
    renderTable(allApps);
    renderSummary(json.counts);
    document.getElementById('sideCount').textContent = allApps.length;
  } catch (e) {
    showToast('Failed to load: ' + e.message, 'error');
    document.getElementById('appBody').innerHTML =
      `<tr><td colspan="6" class="empty-row">Failed to load applications.</td></tr>`;
  }
}

// ── RENDER TABLE ──
function renderTable(list) {
  const tbody = document.getElementById('appBody');
  document.getElementById('tableCaption').textContent =
    `${list.length} application${list.length === 1 ? '' : 's'} found`;
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No applications match your filters.</td></tr>`;
    return;
  }
  const statusClass = { pending:'status-pending', accepted:'status-accepted', rejected:'status-rejected' };
  tbody.innerHTML = list.map(a => {
    const date    = a.appliedAt ? new Date(a.appliedAt).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' }) : '—';
    const sCls    = statusClass[a.status] || '';
    const actions = a.status === 'pending'
      ? `<span class="link-btn success" onclick="updateStatus(${a.applicationId},'accepted')">Accept</span>
         <span class="link-btn danger"  onclick="updateStatus(${a.applicationId},'rejected')">Reject</span>
         <span class="link-btn"         onclick="openDetail(${a.applicationId})">Details</span>`
      : `<span class="link-btn"         onclick="openDetail(${a.applicationId})">Details</span>`;
    return `<tr>
      <td><strong>${escHtml(a.applicantName)}</strong></td>
      <td>${escHtml(a.opportunityTitle)}</td>
      <td>${a.compatibilityScore ?? '—'}%</td>
      <td>${date}</td>
      <td><span class="status-chip ${sCls}">${a.status}</span></td>
      <td class="table-actions">${actions}</td>
    </tr>`;
  }).join('');
}

// ── RENDER SUMMARY BARS ──
function renderSummary(counts) {
  if (!counts) return;
  const total = (counts.pending || 0) + (counts.accepted || 0) + (counts.rejected || 0) || 1;
  document.getElementById('countPending').textContent  = counts.pending  || 0;
  document.getElementById('countAccepted').textContent = counts.accepted || 0;
  document.getElementById('countRejected').textContent = counts.rejected || 0;
  document.getElementById('barPending').style.width    = ((counts.pending  || 0) / total * 100) + '%';
  document.getElementById('barAccepted').style.width   = ((counts.accepted || 0) / total * 100) + '%';
  document.getElementById('barRejected').style.width   = ((counts.rejected || 0) / total * 100) + '%';
}

// ── OPEN DETAIL MODAL ──
function openDetail(id) {
  const a = allApps.find(x => x.applicationId == id);
  if (!a) return;
  currentDetailId = id;
  const date = a.appliedAt ? new Date(a.appliedAt).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' }) : '—';
  const sCls = { pending:'status-pending', accepted:'status-accepted', rejected:'status-rejected' }[a.status] || '';
  document.getElementById('dName').textContent       = a.applicantName    || '—';
  document.getElementById('dPosition').textContent   = a.opportunityTitle || '—';
  document.getElementById('dScore').textContent      = (a.compatibilityScore ?? '—') + '%';
  document.getElementById('dDate').textContent       = date;
  document.getElementById('dStatus').innerHTML       = `<span class="status-chip ${sCls}">${a.status}</span>`;
  document.getElementById('dMotivation').textContent = a.motivation || '—';
  const portEl = document.getElementById('dPortfolio');
  if (a.portfolio) {
    portEl.innerHTML = `<a href="${escHtml(a.portfolio)}" target="_blank" rel="noopener">${escHtml(a.portfolio)}</a>`;
  } else {
    portEl.textContent = '—';
  }
  document.getElementById('detailAccept').style.display = a.status === 'pending' ? '' : 'none';
  document.getElementById('detailReject').style.display = a.status === 'pending' ? '' : 'none';
  openModal('detailModal');
}

// ── UPDATE STATUS ──
async function updateStatus(id, status) {
  try {
    const res         = await fetch(`${API_BASE}?id=${id}&action=status`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status }),
    });
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    showToast(`Application ${status}.`);
    closeModal('detailModal');
    loadApplications();
  } catch (e) {
    showToast('Failed: ' + e.message, 'error');
  }
}

// ── EVENT LISTENERS ──
document.getElementById('detailModalClose').addEventListener('click',  () => closeModal('detailModal'));
document.getElementById('detailModalCancel').addEventListener('click', () => closeModal('detailModal'));
document.getElementById('detailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('detailModal'); });
document.getElementById('detailAccept').addEventListener('click', () => updateStatus(currentDetailId, 'accepted'));
document.getElementById('detailReject').addEventListener('click', () => updateStatus(currentDetailId, 'rejected'));

document.querySelectorAll('.filter-status-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.filter-status-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    filterStatus = this.dataset.status;
    loadApplications();
  });
});

document.getElementById('searchInput').addEventListener('input', () => {});

loadApplications();
