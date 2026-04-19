<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin — Applications</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <style>
    .modal-overlay {
      position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,0.65); backdrop-filter: blur(6px);
      display: flex; align-items: center; justify-content: center;
      padding: 20px; opacity: 0; pointer-events: none;
      transition: opacity .25s ease;
    }
    .modal-overlay.open { opacity: 1; pointer-events: auto; }
    .modal {
      width: min(580px, 100%); border-radius: 20px; padding: 28px;
      background: var(--bg-2); border: 1px solid var(--border);
      box-shadow: var(--shadow); transform: translateY(16px);
      transition: transform .28s ease;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-head {
      display: flex; align-items: center;
      justify-content: space-between; margin-bottom: 22px;
    }
    .modal-head h3 { font-size: 18px; color: var(--text); margin: 0; }
    .modal-close {
      width: 32px; height: 32px; border-radius: 10px;
      border: 1px solid var(--border); background: var(--panel);
      color: var(--muted); cursor: pointer; font-size: 16px;
      display: grid; place-items: center; transition: .18s ease;
    }
    .modal-close:hover { color: var(--text); }

    .detail-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
      margin-bottom: 18px;
    }
    .detail-field { display: flex; flex-direction: column; gap: 6px; }
    .detail-label {
      font-size: 11px; text-transform: uppercase;
      letter-spacing: .22em; color: var(--muted);
    }
    .detail-value {
      font-size: 14px; color: var(--text);
      background: var(--panel); border: 1px solid var(--border);
      border-radius: 10px; padding: 10px 13px; line-height: 1.6;
    }
    .detail-value.full { grid-column: 1 / -1; }
    .detail-value a { color: var(--blue); text-decoration: underline; }

    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .btn-accept {
      flex: 1; padding: 11px; border-radius: 10px;
      border: 1px solid rgba(89,211,155,.28);
      background: rgba(89,211,155,.08); color: var(--green);
      font: inherit; font-size: 14px; font-weight: 600;
      cursor: pointer; transition: .18s ease;
    }
    .btn-accept:hover { background: rgba(89,211,155,.16); }
    .btn-reject {
      flex: 1; padding: 11px; border-radius: 10px;
      border: 1px solid rgba(255,110,69,.28);
      background: rgba(255,110,69,.08); color: var(--red);
      font: inherit; font-size: 14px; font-weight: 600;
      cursor: pointer; transition: .18s ease;
    }
    .btn-reject:hover { background: rgba(255,110,69,.16); }
    .btn-close-detail {
      flex: 1; padding: 11px; border-radius: 10px;
      border: 1px solid var(--border); background: var(--panel);
      color: var(--muted); font: inherit; font-size: 14px;
      cursor: pointer; transition: .18s ease;
    }
    .btn-close-detail:hover { color: var(--text); }

    .toast {
      position: fixed; bottom: 28px; left: 50%;
      transform: translateX(-50%) translateY(20px);
      padding: 12px 22px; border-radius: 999px;
      font-size: 14px; font-weight: 600;
      background: var(--bg-3, var(--bg-2));
      border: 1px solid var(--border); color: var(--text);
      box-shadow: 0 8px 28px rgba(0,0,0,.4);
      z-index: 99999; opacity: 0;
      transition: opacity .22s ease, transform .22s ease;
      white-space: nowrap;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.success { border-color: rgba(89,211,155,.3); color: #59d39b; }
    .toast.error   { border-color: rgba(255,110,69,.3); color: var(--red); }

    .status-chip { font-size: 11px; padding: 4px 10px; border-radius: 999px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .status-pending  { background: rgba(245,191,101,.12); color: #f5bf65; border: 1px solid rgba(245,191,101,.24); }
    .status-accepted { background: rgba(89,211,155,.12);  color: #59d39b; border: 1px solid rgba(89,211,155,.24); }
    .status-rejected { background: rgba(255,110,69,.12);  color: var(--red); border: 1px solid rgba(255,110,69,.24); }

    .link-btn { cursor: pointer; color: var(--blue); font-size: 13px; font-weight: 600; transition: .18s ease; }
    .link-btn:hover { color: var(--text); }
    .link-btn.danger { color: var(--red); }
    .link-btn.success { color: var(--green); }
    .table-actions { display: flex; gap: 12px; }
    .empty-row { text-align: center; color: var(--muted); padding: 32px 0; font-size: 14px; }

    .searchbar {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 16px; border-radius: 16px;
      background: rgba(8,15,33,0.75);
      border: 1px solid rgba(126,159,228,0.14);
      min-width: 260px;
    }
    .searchbar input {
      border: none; outline: none;
      background: transparent; color: var(--white);
      font: inherit; font-size: 14px; width: 100%;
    }
    .searchbar svg { width: 16px; height: 16px; flex-shrink: 0; color: var(--muted); }

    .filter-status-row { display: flex; gap: 8px; flex-wrap: wrap; }
    .filter-status-btn {
      padding: 7px 14px; border-radius: 999px; font-size: 12px;
      font-weight: 700; cursor: pointer; transition: .18s ease;
      border: 1px solid var(--border); background: var(--panel);
      color: var(--muted);
    }
    .filter-status-btn.active { color: var(--white); border-color: var(--blue); background: rgba(111,143,216,.18); }

    .bar-row { display: grid; grid-template-columns: 100px 1fr 48px; align-items: center; gap: 14px; color: var(--muted); font-size: 14px; }
    .bar-track { height: 10px; border-radius: 999px; background: rgba(255,255,255,0.08); overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(to right, var(--blue), var(--red)); }
  </style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="brand"><div class="brand-badge"></div><div><h1>CareerStrand Admin</h1><p>Back office console</p></div></div>
    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
      <a class="nav-item" href="admin-dashboard.html"><span>Dashboard</span><span>Home</span></a>
      <a class="nav-item" href="admin-users.html"><span>Users</span><span>—</span></a>
      <a class="nav-item" href="admin-profiles.html"><span>Profiles</span><span>—</span></a>
      <a class="nav-item" href="admin-questions.html"><span>Courses</span><span>—</span></a>
      <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>—</span></a>
      <a class="nav-item" href="admin-opportunities.php"><span>Opportunities</span><span>—</span></a>
      <a class="nav-item active" href="admin-applications.php"><span>Applications</span><span id="sideCount">—</span></a>
      <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
      <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>—</span></a>
      <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>—</span></a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>Applications</h2>
        <p>Review student applications, check ADN compatibility scores, and manage accept/reject decisions.</p>
      </div>
      <div class="header-actions">
        <div class="searchbar">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <circle cx="7" cy="7" r="4.5"/><line x1="10.2" y1="10.2" x2="13.5" y2="13.5"/>
          </svg>
          <input type="text" id="searchInput" placeholder="Search by applicant name..." />
        </div>
      </div>
    </header>

    <section class="split-grid" style="margin-bottom: 24px;">
      <article class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Application queue</h3>
            <p id="tableCaption">Loading...</p>
          </div>
          <div class="filter-status-row">
            <button class="filter-status-btn active" data-status="">All</button>
            <button class="filter-status-btn" data-status="pending">Pending</button>
            <button class="filter-status-btn" data-status="accepted">Accepted</button>
            <button class="filter-status-btn" data-status="rejected">Rejected</button>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Applicant</th>
              <th>Position</th>
              <th>Score</th>
              <th>Applied</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="appBody">
            <tr><td colspan="6" class="empty-row">Loading applications...</td></tr>
          </tbody>
        </table>
      </article>

      <article class="panel">
        <div class="panel-header">
          <div class="panel-title"><h3>Review summary</h3><p>Current queue breakdown.</p></div>
        </div>
        <div class="bars" style="display:grid;gap:14px;">
          <div class="bar-row">
            <span>Pending</span>
            <div class="bar-track"><div class="bar-fill" id="barPending" style="width:0%"></div></div>
            <strong id="countPending">0</strong>
          </div>
          <div class="bar-row">
            <span>Accepted</span>
            <div class="bar-track"><div class="bar-fill" id="barAccepted" style="width:0%"></div></div>
            <strong id="countAccepted">0</strong>
          </div>
          <div class="bar-row">
            <span>Rejected</span>
            <div class="bar-track"><div class="bar-fill" id="barRejected" style="width:0%"></div></div>
            <strong id="countRejected">0</strong>
          </div>
        </div>
      </article>
    </section>
  </main>
</div>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="detailModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Application details</h3>
      <button class="modal-close" id="detailModalClose">&#x2715;</button>
    </div>
    <div class="detail-grid">
      <div class="detail-field">
        <span class="detail-label">Applicant</span>
        <div class="detail-value" id="dName">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Position</span>
        <div class="detail-value" id="dPosition">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Compatibility score</span>
        <div class="detail-value" id="dScore">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Applied on</span>
        <div class="detail-value" id="dDate">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Status</span>
        <div class="detail-value" id="dStatus">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Portfolio</span>
        <div class="detail-value" id="dPortfolio">—</div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Motivation</span>
        <div class="detail-value" id="dMotivation" style="min-height:80px;">—</div>
      </div>
    </div>
    <div class="modal-actions" id="detailActions">
      <button class="btn-close-detail" id="detailModalCancel">Close</button>
      <button class="btn-accept"  id="detailAccept">Accept</button>
      <button class="btn-reject"  id="detailReject">Reject</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="assets/js/admin.js"></script>
<script>
const API_BASE = '/Careerstrand/controller/controller-applications/';
let allApps    = [];
let filterStatus = '';
let currentDetailId = null;

// Move modal and toast to body root
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
    const res         = await fetch(`${API_BASE}get_applications.php?${params}`);
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

  const date    = a.appliedAt ? new Date(a.appliedAt).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' }) : '—';
  const sCls    = { pending:'status-pending', accepted:'status-accepted', rejected:'status-rejected' }[a.status] || '';
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

  // Hide accept/reject if already decided
  document.getElementById('detailAccept').style.display = a.status === 'pending' ? '' : 'none';
  document.getElementById('detailReject').style.display = a.status === 'pending' ? '' : 'none';

  openModal('detailModal');
}

// ── UPDATE STATUS ──
async function updateStatus(id, status) {
  try {
    const res         = await fetch(`${API_BASE}update_status.php?id=${id}`, {
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

// Searchbar — aesthetic only for now
document.getElementById('searchInput').addEventListener('input', () => {});

loadApplications();
</script>
</body>
</html>