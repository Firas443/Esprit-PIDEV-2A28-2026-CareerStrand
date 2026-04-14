<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin — Opportunities</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <style>
    
   .modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;    /* make sure this beats everything */
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .25s ease;
}
    .modal-overlay.open { opacity: 1; pointer-events: auto; }

    .modal {
      width: min(560px, 100%);
      border-radius: 20px;
      padding: 28px;
      background: var(--bg-2);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      transform: translateY(16px);
      transition: transform .28s ease;
    }
    .modal-overlay.open .modal { transform: translateY(0); }

    .modal-head {
      display: flex; align-items: center;
      justify-content: space-between;
      margin-bottom: 22px;
    }
    .modal-head h3 { font-size: 18px; color: var(--text); margin: 0; }
    .modal-close {
      width: 32px; height: 32px; border-radius: 10px;
      border: 1px solid var(--border); background: var(--panel);
      color: var(--muted); cursor: pointer; font-size: 16px;
      display: grid; place-items: center; transition: .18s ease;
    }
    .modal-close:hover { color: var(--text); background: var(--panel-2); }

    .field-grid { display: grid; gap: 14px; }
    .field { display: flex; flex-direction: column; gap: 6px; }
    .field label {
      font-size: 11px; text-transform: uppercase;
      letter-spacing: .22em; color: var(--muted-2, var(--muted));
    }
    .field input, .field select, .field textarea {
      padding: 10px 13px; border-radius: 10px;
      background: var(--bg-3, var(--bg-2));
      border: 1px solid var(--border); color: var(--text);
      font: inherit; font-size: 14px; outline: none;
      transition: border-color .18s ease;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
      border-color: var(--blue);
    }
    .field textarea { resize: vertical; min-height: 80px; }
    .field select option { background: var(--bg-2); }

    .modal-actions {
      display: flex; gap: 10px; margin-top: 20px;
    }
    .btn-delete-confirm {
      flex: 1; padding: 11px; border-radius: 10px;
      border: 1px solid rgba(255,110,69,.28);
      background: rgba(255,110,69,.08);
      color: var(--red); font: inherit; font-size: 14px;
      font-weight: 600; cursor: pointer; transition: .18s ease;
    }
    .btn-delete-confirm:hover { background: rgba(255,110,69,.16); }
    .btn-save {
      flex: 2; padding: 11px; border-radius: 10px;
      border: none; background: var(--blue);
      color: #fff; font: inherit; font-size: 14px;
      font-weight: 700; cursor: pointer; transition: .18s ease;
    }
    .btn-save:hover { filter: brightness(1.1); }
    .btn-cancel-modal {
      flex: 1; padding: 11px; border-radius: 10px;
      border: 1px solid var(--border); background: var(--panel);
      color: var(--muted); font: inherit; font-size: 14px;
      cursor: pointer; transition: .18s ease;
    }
    .btn-cancel-modal:hover { color: var(--text); }

    .toast {
      position: fixed; bottom: 28px; left: 50%;
      transform: translateX(-50%) translateY(20px);
      padding: 12px 22px; border-radius: 999px;
      font-size: 14px; font-weight: 600;
      background: var(--bg-3, var(--bg-2));
      border: 1px solid var(--border);
      color: var(--text);
      box-shadow: 0 8px 28px rgba(0,0,0,.4);
      z-index: 300; opacity: 0;
      transition: opacity .22s ease, transform .22s ease;
      white-space: nowrap;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast.success { border-color: rgba(89,211,155,.3); color: #59d39b; }
    .toast.error   { border-color: rgba(255,110,69,.3);  color: var(--red); }

    
    .filter-row {
      display: flex; gap: 10px; flex-wrap: wrap;
      margin-bottom: 18px; align-items: center;
    }
    .filter-row input, .filter-row select {
      padding: 9px 13px; border-radius: 10px;
      background: var(--panel); border: 1px solid var(--border);
      color: var(--text); font: inherit; font-size: 13px; outline: none;
    }
    .filter-row input { min-width: 200px; }

    
    .status-chip { font-size: 11px; padding: 4px 10px; border-radius: 999px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .status-published { background: rgba(89,211,155,.12); color: #59d39b;   border: 1px solid rgba(89,211,155,.24); }
    .status-draft     { background: rgba(245,191,101,.12); color: #f5bf65;  border: 1px solid rgba(245,191,101,.24); }
    .status-archived  { background: rgba(255,255,255,.06); color: var(--muted); border: 1px solid var(--border); }
    .category-chip { font-size: 11px; padding: 4px 10px; border-radius: 999px; background: var(--panel); border: 1px solid var(--border); color: var(--muted); }
    .link-btn { cursor: pointer; color: var(--blue); font-size: 13px; font-weight: 600; transition: .18s ease; }
    .link-btn:hover { color: var(--text); }
    .link-btn.danger { color: var(--red); }
    .link-btn.danger:hover { color: var(--red-2, var(--red)); }
    .table-actions { display: flex; gap: 12px; }
    .empty-row td { text-align: center; color: var(--muted); padding: 32px 0; font-size: 14px; }

    
    .delete-confirm-text {
      font-size: 14px; color: var(--muted);
      line-height: 1.7; margin-bottom: 8px;
    }
    .delete-confirm-text strong { color: var(--text); }
  </style>
</head>
<body>
<div class="admin-shell">

  
  <aside class="admin-sidebar">
    <div class="brand">
      <div class="brand-badge"></div>
      <div><h1>CareerStrand Admin</h1><p>Back office console</p></div>
    </div>
    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
      <a class="nav-item" href="admin-dashboard.html"><span>Dashboard</span><span>Home</span></a>
      <a class="nav-item" href="admin-users.html"><span>Users</span><span>—</span></a>
      <a class="nav-item" href="admin-profiles.html"><span>Profiles</span><span>—</span></a>
      <a class="nav-item" href="admin-questions.html"><span>Courses</span><span>—</span></a>
      <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>—</span></a>
      <a class="nav-item active" href="admin-opportunities.php"><span>Opportunities</span><span id="sideCount">—</span></a>
      <a class="nav-item" href="admin-applications.html"><span>Applications</span><span>—</span></a>
      <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
      <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>—</span></a>
      <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>—</span></a>
    </nav>
  </aside>

 
  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>Opportunities</h2>
        <p>Manage internships, junior roles, and freelance tasks. Only published opportunities are visible to users.</p>
      </div>
      <div class="header-actions">
        <div class="searchbar">
          <span>Search</span>
          <input type="text" id="searchInput" placeholder="Search by title..." />
        </div>
        <button class="btn btn-soft" id="filterPublished">Published only</button>
        <button class="btn btn-main" id="btnCreate">+ Create opportunity</button>
      </div>
    </header>

    <section class="panel" style="margin-top:0;">
      <div class="panel-header">
        <div class="panel-title">
          <h3>Opportunity library</h3>
          <p id="tableCaption">Loading...</p>
        </div>
        <div class="filter-row">
          <select id="filterCategory">
            <option value="">All categories</option>
            <option>Technical</option>
            <option>Creativity</option>
            <option>Business</option>
            <option>Communication</option>
            <option>Leadership</option>
          </select>
          <select id="filterLevel">
            <option value="">All levels</option>
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
          </select>
        </div>
      </div>

      <table id="oppoTable">
<thead>
  <tr>
    <th>Title</th>
    <th>Type</th>
    <th>Category</th>
    <th>Level</th>
    <th>Deadline</th>
    <th>Created</th>     
    <th>By</th>          
    <th>Apps</th>
    <th>Status</th>
    <th>Actions</th>
  </tr>
</thead>
        <tbody id="oppoBody">
          <tr><td colspan="10" class="empty-row" style="text-align:center;color:var(--muted);padding:32px 0;">Loading opportunities...</td></tr>
        </tbody>
      </table>
    </section>
  </main>
</div>


<div class="modal-overlay" id="formModal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modalTitle">Create opportunity</h3>
      <button class="modal-close" id="formModalClose">&#x2715;</button>
    </div>
    <input type="hidden" id="editId" value="" />
    <div class="field-grid">
      <div class="field">
        <label>Title</label>
        <input type="text" id="fTitle" placeholder="e.g. UI/UX Designer Intern" />
      </div>
      <div class="field">
        <label>Description</label>
        <textarea id="fDescription" placeholder="What will the candidate do?"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="field">
          <label>Type</label>
          <select id="fType">
            <option value="internship">Internship</option>
            <option value="job">Job</option>
            <option value="freelance">Freelance</option>
            <option value="volunteer">Volunteer</option>
          </select>
        </div>
        <div class="field">
          <label>Category</label>
          <select id="fCategory">
            <option>Technical</option>
            <option>Creativity</option>
            <option>Business</option>
            <option>Communication</option>
            <option>Leadership</option>
          </select>
        </div>
        <div class="field">
          <label>Required level</label>
          <select id="fLevel">
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
          </select>
        </div>
        <div class="field">
          <label>Deadline</label>
          <input type="date" id="fDeadline" />
        </div>
      </div>
      <div class="field">
        <label>Status</label>
        <select id="fStatus">
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel-modal" id="formModalCancel">Cancel</button>
      <button class="btn-save" id="formModalSave">Save opportunity</button>
    </div>
  </div>
</div>

<!-- ── DELETE CONFIRM MODAL ── -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-head">
      <h3>Delete opportunity</h3>
      <button class="modal-close" id="deleteModalClose">&#x2715;</button>
    </div>
    <p class="delete-confirm-text">
      You are about to permanently delete <strong id="deleteTarget">this opportunity</strong>
      and all its linked applications. This cannot be undone.
    </p>
    <div class="modal-actions">
      <button class="btn-cancel-modal" id="deleteModalCancel">Cancel</button>
      <button class="btn-delete-confirm" id="deleteModalConfirm">Yes, delete</button>
    </div>
  </div>
</div>


<div class="toast" id="toast"></div>

<script src="assets/js/admin.js"></script>
<script>const MANAGER_ID    = 1;
const API_BASE      = 'CRUD/';
let allOppos        = [];
let filterStatus    = '';
let pendingDeleteId = null;

// ── HELPERS ──
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = `toast ${type}`;
  void t.offsetWidth;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── INIT: move modals to body root, force closed ──
document.body.append(
  document.getElementById('formModal'),
  document.getElementById('deleteModal'),
  document.getElementById('toast')
);
closeModal('formModal');
closeModal('deleteModal');

// ── LOAD ──
async function loadOpportunities() {
  const category = document.getElementById('filterCategory').value;
  const level    = document.getElementById('filterLevel').value;
  const search   = document.getElementById('searchInput').value.trim();

  const params = new URLSearchParams();
  if (filterStatus) params.set('status', filterStatus);
  if (category)     params.set('category', category);
  if (level)        params.set('requiredLevel', level);
  if (search)       params.set('search', search);

  try {
    const res         = await fetch(`${API_BASE}get_opportunities.php?${params}`);
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server returned non-JSON: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    allOppos = json.data;
    renderTable(allOppos);
    document.getElementById('sideCount').textContent = allOppos.length;
  } catch (e) {
    showToast('Failed to load: ' + e.message, 'error');
    document.getElementById('oppoBody').innerHTML =
      `<tr><td colspan="10" class="empty-row">Failed to load opportunities.</td></tr>`;
  }
}

// ── RENDER ──
function renderTable(list) {
  const tbody = document.getElementById('oppoBody');
  document.getElementById('tableCaption').textContent =
    `${list.length} opportunit${list.length === 1 ? 'y' : 'ies'} found`;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="10" class="empty-row">No opportunities match your filters.</td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(o => {
    const deadline    = o.deadline ? new Date(o.deadline).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' }) : '—';
    const created     = o.createdAt ? new Date(o.createdAt).toLocaleDateString('en-GB', { day:'numeric', month:'short' }) : '—';
    const createdBy   = o.managerName || '—';
    const statusClass = { published:'status-published', draft:'status-draft', archived:'status-archived' }[o.status] || '';
    const toggleLabel = o.status === 'published' ? 'Unpublish' : o.status === 'draft' ? 'Publish' : 'Restore';

    return `<tr data-id="${o.opportunityId}">
      <td><strong>${escHtml(o.title)}</strong></td>
      <td>${escHtml(o.type)}</td>
      <td><span class="category-chip">${escHtml(o.category)}</span></td>
      <td>${escHtml(o.requiredLevel)}</td>
      <td>${deadline}</td>
      <td>${created}</td>
      <td>${escHtml(createdBy)}</td>
      <td>${o.applicationCount ?? 0}</td>
      <td><span class="status-chip ${statusClass}">${o.status}</span></td>
      <td class="table-actions">
        <span class="link-btn" onclick="openEditModal(${o.opportunityId})">Edit</span>
        <span class="link-btn" onclick="quickToggleStatus(${o.opportunityId}, '${o.status}')">${toggleLabel}</span>
        <span class="link-btn danger" onclick="openDeleteModal(${o.opportunityId}, '${escHtml(o.title)}')">Delete</span>
      </td>
    </tr>`;
  }).join('');
}

// ── STATUS TOGGLE ──
async function quickToggleStatus(id, currentStatus) {
  const next = currentStatus === 'published' ? 'draft' : currentStatus === 'draft' ? 'published' : 'published';
  const o    = allOppos.find(x => x.opportunityId == id);
  if (!o) return;
  try {
    const res  = await fetch(`${API_BASE}update_opportunity.php?id=${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: o.title, description: o.description, type: o.type, category: o.category, deadline: o.deadline, requiredLevel: o.requiredLevel, status: next }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || JSON.stringify(json.errors));
    showToast(`Status changed to ${next}.`);
    loadOpportunities();
  } catch (e) { showToast('Update failed: ' + e.message, 'error'); }
}

// ── CREATE MODAL ──
function openCreateModal() {
  document.getElementById('modalTitle').textContent  = 'Create opportunity';
  document.getElementById('editId').value            = '';
  document.getElementById('fTitle').value            = '';
  document.getElementById('fDescription').value      = '';
  document.getElementById('fType').value             = 'internship';
  document.getElementById('fCategory').value         = 'Technical';
  document.getElementById('fLevel').value            = 'Beginner';
  document.getElementById('fDeadline').value         = '';
  document.getElementById('fStatus').value           = 'draft';
  openModal('formModal');
}

// ── EDIT MODAL ──
function openEditModal(id) {
  const o = allOppos.find(x => x.opportunityId == id);
  if (!o) return;
  document.getElementById('modalTitle').textContent  = 'Edit opportunity';
  document.getElementById('editId').value            = id;
  document.getElementById('fTitle').value            = o.title;
  document.getElementById('fDescription').value      = o.description;
  document.getElementById('fType').value             = o.type;
  document.getElementById('fCategory').value         = o.category;
  document.getElementById('fLevel').value            = o.requiredLevel;
  document.getElementById('fDeadline').value         = o.deadline ? o.deadline.split('T')[0] : '';
  document.getElementById('fStatus').value           = o.status;
  openModal('formModal');
}

// ── SAVE ──
async function saveOpportunity() {
  const id   = document.getElementById('editId').value;
  const body = {
    managerId:     MANAGER_ID,
    title:         document.getElementById('fTitle').value.trim(),
    description:   document.getElementById('fDescription').value.trim(),
    type:          document.getElementById('fType').value,
    category:      document.getElementById('fCategory').value,
    requiredLevel: document.getElementById('fLevel').value,
    deadline:      document.getElementById('fDeadline').value,
    status:        document.getElementById('fStatus').value,
  };
  const isEdit = !!id;
  const url    = isEdit ? `${API_BASE}update_opportunity.php?id=${id}` : `${API_BASE}create_opportunity.php`;
  try {
    const res  = await fetch(url, { method: isEdit ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const json = await res.json();
    if (!json.success) throw new Error(json.errors ? json.errors.join('\n') : json.message);
    showToast(isEdit ? 'Opportunity updated.' : 'Opportunity created.');
    closeModal('formModal');
    loadOpportunities();
  } catch (e) { showToast(e.message, 'error'); }
}

// ── DELETE ──
function openDeleteModal(id, title) {
  pendingDeleteId = id;
  document.getElementById('deleteTarget').textContent = `"${title}"`;
  openModal('deleteModal');
}

async function confirmDelete() {
  if (!pendingDeleteId) return;
  try {
    const res  = await fetch(`${API_BASE}delete_opportunity.php?id=${pendingDeleteId}`, { method: 'DELETE' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    showToast('Opportunity deleted.');
    closeModal('deleteModal');
    pendingDeleteId = null;
    loadOpportunities();
  } catch (e) { showToast('Delete failed: ' + e.message, 'error'); }
}

// ── EVENT LISTENERS ──
document.getElementById('btnCreate').addEventListener('click', openCreateModal);
document.getElementById('formModalSave').addEventListener('click', saveOpportunity);
document.getElementById('formModalClose').addEventListener('click', () => closeModal('formModal'));
document.getElementById('formModalCancel').addEventListener('click', () => closeModal('formModal'));
document.getElementById('formModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('formModal'); });

document.getElementById('deleteModalClose').addEventListener('click', () => closeModal('deleteModal'));
document.getElementById('deleteModalCancel').addEventListener('click', () => closeModal('deleteModal'));
document.getElementById('deleteModalConfirm').addEventListener('click', confirmDelete);
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('deleteModal'); });

document.getElementById('filterPublished').addEventListener('click', function () {
  filterStatus      = filterStatus === 'published' ? '' : 'published';
  this.textContent  = filterStatus ? 'Show all' : 'Published only';
  loadOpportunities();
});

let searchTimer;
document.getElementById('searchInput').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadOpportunities, 350);
});

document.getElementById('filterCategory').addEventListener('change', loadOpportunities);
document.getElementById('filterLevel').addEventListener('change', loadOpportunities);

loadOpportunities();
</script>
</body>
</html>