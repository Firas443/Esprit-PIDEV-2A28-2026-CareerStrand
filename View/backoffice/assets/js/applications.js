const API_BASE = '../../Controller/ApplicationController.php';
let allApps = [];
let filterStatus = '';
let currentDetailId = null;
const APPLICATION_STAGES = ['saved', 'applied', 'shortlisted', 'interview', 'accepted', 'rejected'];

function normalizeStatus(value) {
  const status = String(value || '').toLowerCase();
  return status === 'pending' ? 'applied' : status;
}

function normalizeTimeline(raw) {
  if (Array.isArray(raw)) return raw;
  if (typeof raw !== 'string' || !raw.trim()) return [];
  try {
    const decoded = JSON.parse(raw);
    return Array.isArray(decoded) ? decoded : [];
  } catch {
    return [];
  }
}

function statusLabel(value) {
  const status = normalizeStatus(value);
  if (status === 'saved') return 'Saved';
  if (status === 'applied') return 'Applied';
  if (status === 'shortlisted') return 'Shortlisted';
  if (status === 'interview') return 'Interview';
  if (status === 'accepted') return 'Accepted';
  if (status === 'rejected') return 'Rejected';
  return status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Applied';
}

function stageIndex(value) {
  const index = APPLICATION_STAGES.indexOf(normalizeStatus(value));
  return index >= 0 ? index : 1;
}

function stageTrackHTML(status) {
  const currentIndex = stageIndex(status);
  return `
    <div class="stage-track-grid">
      ${APPLICATION_STAGES.map((stage, index) => {
        const state = index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'locked');
        const abbr = stage === 'saved' ? 'SV' : stage === 'applied' ? 'AP' : stage === 'shortlisted' ? 'SL' : stage === 'interview' ? 'IN' : stage === 'accepted' ? 'AC' : 'RJ';
        return `
          <div class="stage-pill ${state}">
            <span>${abbr}</span>
            <small>${statusLabel(stage)}</small>
          </div>`;
      }).join('')}
    </div>`;
}

function timelineHTML(timeline) {
  const items = normalizeTimeline(timeline);
  if (!items.length) {
    return `<div class="timeline-empty">No timeline yet. Status changes will appear here.</div>`;
  }

  return `
    <div class="timeline-list">
      ${items.map((entry, index) => {
        const label = entry.label || statusLabel(entry.stage);
        const note = entry.note || 'Status updated.';
        const actor = entry.actor || 'CareerStrand team';
        const at = entry.at
          ? new Date(entry.at).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
          : '';
        return `
          <div class="timeline-item ${index === items.length - 1 ? 'is-current' : ''}">
            <div class="timeline-dot"></div>
            <div class="timeline-copy">
              <div class="timeline-head">
                <strong>${escHtml(label)}</strong>
                <span>${escHtml(actor)}</span>
              </div>
              <p>${escHtml(note)}</p>
              <small>${escHtml(at)}</small>
            </div>
          </div>`;
      }).join('')}
    </div>`;
}

function latestTimelineNote(timeline) {
  const items = normalizeTimeline(timeline);
  if (!items.length) return '';
  const last = items[items.length - 1] || {};
  return String(last.note || '').trim();
}

document.body.append(
  document.getElementById('detailModal'),
  document.getElementById('toast')
);
closeModal('detailModal');

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `toast ${type}`;
  void t.offsetWidth;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

function openModal(id) {
  document.getElementById(id).classList.add('open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

function escHtml(str) {
  return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function getApplicationId(app) {
  return Number(app?.applicationId ?? app?.applicationID ?? app?.ApplicationId ?? app?.application_id ?? app?.id ?? 0);
}

async function loadApplications() {
  const search = document.getElementById('searchInput').value.trim();
  const searchPosition = document.getElementById('searchPosition').value.trim();
  const params = new URLSearchParams({ source: 'back' });
  if (filterStatus) params.set('status', filterStatus);
  if (search) params.set('search', search);
  if (searchPosition) params.set('searchPosition', searchPosition);

  try {
    const res = await fetch(`${API_BASE}?${params}`);
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

function renderTable(list) {
  const tbody = document.getElementById('appBody');
  document.getElementById('tableCaption').textContent =
    `${list.length} application${list.length === 1 ? '' : 's'} found`;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No applications match your filters.</td></tr>`;
    return;
  }

  const statusClass = {
    saved: 'status-saved',
    applied: 'status-pending',
    pending: 'status-pending',
    shortlisted: 'status-shortlisted',
    interview: 'status-interview',
    accepted: 'status-accepted',
    rejected: 'status-rejected',
  };

  tbody.innerHTML = list.map(a => {
    const date = a.appliedAt ? new Date(a.appliedAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
    const normalized = normalizeStatus(a.status);
    const sCls = statusClass[normalized] || '';
    const appId = getApplicationId(a);
    const canMove = !['accepted', 'rejected'].includes(normalized);
    const actions = appId > 0
      ? (canMove
        ? `<span class="link-btn" onclick="updateStatus(${appId},'saved')">Save</span>
           <span class="link-btn" onclick="updateStatus(${appId},'shortlisted')">Shortlist</span>
           <span class="link-btn" onclick="updateStatus(${appId},'interview')">Interview</span>
           <span class="link-btn success" onclick="updateStatus(${appId},'accepted')">Accept</span>
           <span class="link-btn danger" onclick="updateStatus(${appId},'rejected')">Reject</span>
           <span class="link-btn" onclick="openDetail(${appId})">Details</span>
           <span class="link-btn danger" onclick="deleteApplication(${appId})">Delete</span>`
        : `<span class="link-btn" onclick="openDetail(${appId})">Details</span>
           <span class="link-btn danger" onclick="deleteApplication(${appId})">Delete</span>`)
      : `<span class="link-btn danger">Invalid ID</span>`;

    return `<tr>
      <td><strong>${escHtml(a.applicantName)}</strong></td>
      <td>${escHtml(a.opportunityTitle)}</td>
      <td>${a.compatibilityScore ?? '—'}%</td>
      <td>${date}</td>
      <td><span class="status-chip ${sCls}">${statusLabel(normalized)}</span></td>
      <td class="table-actions">${actions}</td>
    </tr>`;
  }).join('');
}

function renderSummary(counts) {
  if (!counts) return;
  const total = Object.values(counts).reduce((sum, value) => sum + (Number(value) || 0), 0) || 1;
  const set = (id, value) => { document.getElementById(id).textContent = Number(value) || 0; };
  const width = value => ((Number(value) || 0) / total * 100) + '%';
  set('countSaved', counts.saved);
  set('countApplied', counts.applied);
  set('countShortlisted', counts.shortlisted);
  set('countInterview', counts.interview);
  set('countAccepted', counts.accepted);
  set('countRejected', counts.rejected);
  document.getElementById('barSaved').style.width = width(counts.saved);
  document.getElementById('barApplied').style.width = width(counts.applied);
  document.getElementById('barShortlisted').style.width = width(counts.shortlisted);
  document.getElementById('barInterview').style.width = width(counts.interview);
  document.getElementById('barAccepted').style.width = width(counts.accepted);
  document.getElementById('barRejected').style.width = width(counts.rejected);
}

function openDetail(id) {
  const appId = Number(id);
  if (!Number.isInteger(appId) || appId <= 0) {
    showToast('Missing application ID. Refresh the page and try again.', 'error');
    return;
  }

  const a = allApps.find(x => getApplicationId(x) === appId);
  if (!a) return;

  currentDetailId = appId;
  const normalized = normalizeStatus(a.status);
  const timeline = normalizeTimeline(a.timeline);
  const notesText = String(a.applicationNotes || a.notes || latestTimelineNote(timeline) || 'No notes yet.').trim();
  const date = a.appliedAt ? new Date(a.appliedAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
  const sCls = {
    saved: 'status-saved',
    applied: 'status-pending',
    shortlisted: 'status-shortlisted',
    interview: 'status-interview',
    accepted: 'status-accepted',
    rejected: 'status-rejected',
  }[normalized] || '';

  document.getElementById('dName').textContent = a.applicantName || '—';
  document.getElementById('dPosition').textContent = a.opportunityTitle || '—';
  document.getElementById('dScore').textContent = (a.compatibilityScore ?? '—') + '%';
  document.getElementById('dDate').textContent = date;
  document.getElementById('dStatus').innerHTML = `<span class="status-chip ${sCls}">${statusLabel(normalized)}</span>`;
  document.getElementById('dMotivation').textContent = a.motivation || '—';
  document.getElementById('dDescription').textContent = a.opportunityDescription || '—';
  document.getElementById('dNotes').textContent = notesText;
  document.getElementById('dStageTrack').innerHTML = stageTrackHTML(normalized);
  document.getElementById('dTimeline').innerHTML = timelineHTML(timeline);
  document.getElementById('aiSummaryBox').innerHTML = `<p class="ai-empty">Click summarize to review the portfolio, motivation, and job description.</p>`;
  document.getElementById('detailSummarize').disabled = false;
  document.getElementById('detailSummarize').textContent = 'Summarize with AI';

  const portfolioLinkBtn    = document.getElementById('dPortfolioLinkBtn');
  const publicPortfolioLink = document.getElementById('dPublicPortfolio');
  const cvExportLink        = document.getElementById('dCvExport');
  const noLinksSpan         = document.getElementById('dNoLinks');
  const applicantUserId     = Number(a.userId ?? 0);

  if (portfolioLinkBtn) {
    if (a.portfolio) { portfolioLinkBtn.href = a.portfolio; portfolioLinkBtn.hidden = false; }
    else { portfolioLinkBtn.hidden = true; }
  }

  if (applicantUserId > 0) {
    const query = `?userId=${encodeURIComponent(String(applicantUserId))}`;
    if (publicPortfolioLink) { publicPortfolioLink.href = `../FrontOffice/public_portfolio.php${query}`; publicPortfolioLink.hidden = false; }
    if (cvExportLink) { cvExportLink.href = `../FrontOffice/export_cv.php${query}`; cvExportLink.hidden = false; }
  } else {
    if (publicPortfolioLink) publicPortfolioLink.hidden = true;
    if (cvExportLink) cvExportLink.hidden = true;
  }

  if (noLinksSpan) noLinksSpan.hidden = !!(a.portfolio || applicantUserId > 0);

  const terminal = ['accepted', 'rejected'].includes(normalized);
  const reviewable = ['saved', 'applied', 'shortlisted', 'interview'].includes(normalized);
  document.getElementById('detailShortlist').style.display = terminal ? 'none' : '';
  document.getElementById('detailInterview').style.display = terminal ? 'none' : '';
  document.getElementById('detailAccept').style.display = terminal ? 'none' : '';
  document.getElementById('detailReject').style.display = terminal ? 'none' : '';
  document.getElementById('detailShortlist').disabled = !reviewable;
  document.getElementById('detailInterview').disabled = !reviewable;

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
  if (!Number.isInteger(Number(currentDetailId)) || Number(currentDetailId) <= 0) {
    showToast('Missing application ID. Open the application details again.', 'error');
    return;
  }
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

async function updateStatus(id, status) {
  const appId = Number(id);
  if (!Number.isInteger(appId) || appId <= 0) {
    showToast('Failed: Missing application ID. Refresh the page and try again.', 'error');
    return;
  }

  try {
    const res = await fetch(`${API_BASE}?id=${appId}&action=status`, {
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

async function deleteApplication(id) {
  if (!confirm('Delete this application permanently?')) return;
  try {
    const res = await fetch(`${API_BASE}?id=${id}&source=back`, { method: 'DELETE' });
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    showToast('Application deleted.');
    closeModal('detailModal');
    loadApplications();
  } catch (e) {
    showToast('Delete failed: ' + e.message, 'error');
  }
}

document.getElementById('detailModalClose').addEventListener('click', () => closeModal('detailModal'));
document.getElementById('detailModalCancel').addEventListener('click', () => closeModal('detailModal'));
document.getElementById('detailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('detailModal'); });
document.getElementById('detailShortlist').addEventListener('click', () => updateStatus(currentDetailId, 'shortlisted'));
document.getElementById('detailInterview').addEventListener('click', () => updateStatus(currentDetailId, 'interview'));
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
