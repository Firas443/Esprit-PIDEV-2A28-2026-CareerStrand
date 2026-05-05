const API_BASE = '/Careerstrand/controller/ApplicationController.php';
let allApps         = [];
let filterStatus    = '';
let currentDetailId = null;

document.body.append(
  document.getElementById('detailModal'),
  document.getElementById('toast')
);
closeModal('detailModal');
//show the message error at the bottom of the screen 
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = `toast ${type}`;
  void t.offsetWidth; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
// Converts < > & " into safe HTML entities so they display as text instead of executing
function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

//fetch all applications from the backend
async function loadApplications() {
  const search         = document.getElementById('searchInput').value.trim();
  const searchPosition = document.getElementById('searchPosition').value.trim();
  const params = new URLSearchParams({ source: 'back' });
  if (filterStatus)    params.set('status',         filterStatus);
  if (search)          params.set('search',          search);
  if (searchPosition)  params.set('searchPosition',  searchPosition);
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

//render list of applications  + accept reject details buttons
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

//count how much accept reject pending returned by the backend and animate bar
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

// Opens the detail modal for a specific application by its ID + show accept reject button
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
  document.getElementById('dDescription').textContent = a.opportunityDescription || '—';
  document.getElementById('aiSummaryBox').innerHTML = `<p class="ai-empty">Click summarize to review the portfolio, motivation, and job description.</p>`;
  document.getElementById('detailSummarize').disabled = false;
  document.getElementById('detailSummarize').textContent = 'Summarize with AI';
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

function renderAiSummary(data) {
  const list = items => (items || []).length
    ? `<ul>${items.map(item => `<li>${escHtml(item)}</li>`).join('')}</ul>`
    : `<p class="ai-muted">No items found.</p>`;

  document.getElementById('aiSummaryBox').innerHTML = `
    <div class="ai-fit-row">
      <span class="ai-fit-pill">${escHtml(data.fitDecision)}</span>
      <strong>${escHtml(data.fitScore)}%</strong>
    </div>
    <div class="ai-section">
      <h4>Summary</h4>
      <p>${escHtml(data.summary)}</p>
    </div>
    <div class="ai-section">
      <h4>Portfolio</h4>
      <p>${escHtml(data.portfolioSummary)}</p>
    </div>
    <div class="ai-two-col">
      <div class="ai-section">
        <h4>Strengths</h4>
        ${list(data.strengths)}
      </div>
      <div class="ai-section">
        <h4>Concerns</h4>
        ${list(data.concerns)}
      </div>
    </div>
    <div class="ai-section">
      <h4>Recommendation</h4>
      <p>${escHtml(data.recommendation)}</p>
    </div>`;
}

async function summarizeApplication() {
  if (!currentDetailId) return;
  const btn = document.getElementById('detailSummarize');
  const box = document.getElementById('aiSummaryBox');
  btn.disabled = true;
  btn.textContent = 'Summarizing...';
  box.innerHTML = `<p class="ai-empty">Reviewing application with AI...</p>`;

  try {
    const res = await fetch(`${API_BASE}?id=${currentDetailId}&action=summarize`, { method: 'POST' });
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    renderAiSummary(json.data);
  } catch (e) {
    box.innerHTML = `<p class="ai-error">Could not summarize: ${escHtml(e.message)}</p>`;
    showToast('AI summary failed: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Summarize with AI';
  }
}

//change status and refresh table
async function updateStatus(id, status) {
  try {
    const res= await fetch(`${API_BASE}?id=${id}&action=status`, {
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
document.getElementById('detailSummarize').addEventListener('click', summarizeApplication);

document.querySelectorAll('.filter-status-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.filter-status-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    filterStatus = this.dataset.status;
    loadApplications();
  });
});

let searchTimer;
function debounceSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadApplications, 350);
}
document.getElementById('searchInput').addEventListener('input', debounceSearch);
document.getElementById('searchPosition').addEventListener('input', debounceSearch);

loadApplications();
