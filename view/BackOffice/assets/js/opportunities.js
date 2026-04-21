const MANAGER_ID    = 1;
const API_BASE = '/Careerstrand/Controller/OpportunityController.php';
let allOppos        = [];
let filterStatus    = '';
let pendingDeleteId = null;

document.body.append(
  document.getElementById('formModal'),
  document.getElementById('deleteModal'),
  document.getElementById('toast')
);
closeModal('formModal');
closeModal('deleteModal');

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

// AJAX CHECK
let titleCheckTimer = null;
let titleIsValid    = true;

function resetTitleValidation() {
  document.getElementById('fTitle').classList.remove('invalid', 'valid');
  document.getElementById('titleError').classList.remove('visible');
  titleIsValid = true;
}

async function checkTitleExists(title, excludeId = 0) {
  if (!title.trim()) { resetTitleValidation(); return; }

  clearTimeout(titleCheckTimer);
  titleCheckTimer = setTimeout(async () => {
    try {
      const params = new URLSearchParams({ action: 'checkTitle', title: title.trim() });
      if (excludeId) params.set('excludeId', excludeId);

      const res  = await fetch(`${API_BASE}?${params}`);
      const json = await res.json();

      const input = document.getElementById('fTitle');
      const error = document.getElementById('titleError');

      if (json.exists) {
        input.classList.add('invalid');
        input.classList.remove('valid');
        error.classList.add('visible');
        titleIsValid = false;
      } else {
        input.classList.add('valid');
        input.classList.remove('invalid');
        error.classList.remove('visible');
        titleIsValid = true;
      }
    } catch (e) {
      console.error('Title check failed:', e);
    }
  }, 400); // 400ms debounce
}
// ── LOAD ──
async function loadOpportunities() {
  const category = document.getElementById('filterCategory').value;
  const level    = document.getElementById('filterLevel').value;
  const search   = document.getElementById('searchInput').value.trim();
  const params   = new URLSearchParams();
  if (filterStatus) params.set('status', filterStatus);
  if (category)     params.set('category', category);
  if (level)        params.set('requiredLevel', level);
  if (search)       params.set('search', search);
  try {
    const res         = await fetch(`${API_BASE}?source=back&${params}`)
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

// ── RENDER TABLE ──
function renderTable(list) {
  const tbody = document.getElementById('oppoBody');
  document.getElementById('tableCaption').textContent =
    `${list.length} opportunit${list.length === 1 ? 'y' : 'ies'} found`;
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="10" class="empty-row">No opportunities match your filters.</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(o => {
    const deadline    = o.deadline  ? new Date(o.deadline).toLocaleDateString('en-GB',  { day:'numeric', month:'short', year:'numeric' }) : '—';
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
    const res         = await fetch(`${API_BASE}?id=${id}` ,{
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: o.title, description: o.description, type: o.type, category: o.category, deadline: o.deadline, requiredLevel: o.requiredLevel, status: next }),
    });
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.message || JSON.stringify(json.errors));
    showToast(`Status changed to ${next}.`);
    loadOpportunities();
  } catch (e) { showToast('Update failed: ' + e.message, 'error'); }
}

// ── CREATE MODAL ──
function openCreateModal() {
  resetTitleValidation();
  document.getElementById('modalTitle').textContent = 'Create opportunity';
  document.getElementById('editId').value           = '';
  document.getElementById('fTitle').value           = '';
  document.getElementById('fDescription').value     = '';
  document.getElementById('fType').value            = 'internship';
  document.getElementById('fCategory').value        = 'Technical';
  document.getElementById('fLevel').value           = 'Beginner';
  document.getElementById('fDeadline').value        = '';
  document.getElementById('fStatus').value          = 'draft';
  openModal('formModal');
}

// ── EDIT MODAL ──
function openEditModal(id) {
  resetTitleValidation();
  const o = allOppos.find(x => x.opportunityId == id);
  if (!o) return;
  document.getElementById('modalTitle').textContent = 'Edit opportunity';
  document.getElementById('editId').value           = id;
  document.getElementById('fTitle').value           = o.title;
  document.getElementById('fDescription').value     = o.description;
  document.getElementById('fType').value            = o.type;
  document.getElementById('fCategory').value        = o.category;
  document.getElementById('fLevel').value           = o.requiredLevel;
  document.getElementById('fDeadline').value        = o.deadline ? o.deadline.split('T')[0] : '';
  document.getElementById('fStatus').value          = o.status;
  openModal('formModal');
}

// ── SAVE ──
async function saveOpportunity() {
  const title       = document.getElementById('fTitle').value.trim();
  const description = document.getElementById('fDescription').value.trim();
  const deadline    = document.getElementById('fDeadline').value;

  // ── CLIENT-SIDE VALIDATION ──
  if (!title) {
    document.getElementById('fTitle').classList.add('invalid');
    showToast('Title is required.', 'error');
    return;
  }

  if (!titleIsValid) {
    showToast('Please choose a different title — this one already exists.', 'error');
    return;
  }

  if (!description) {
    document.getElementById('fDescription').classList.add('invalid');
    showToast('Description is required.', 'error');
    return;
  }

  if (!deadline) {
    document.getElementById('fDeadline').classList.add('invalid');
    showToast('Deadline is required.', 'error');
    return;
  }

  const today = new Date(); today.setHours(0,0,0,0);
  if (new Date(deadline) < today) {
    document.getElementById('fDeadline').classList.add('invalid');
    showToast('Deadline must be today or in the future.', 'error');
    return;
  }

  const id   = document.getElementById('editId').value;
  const body = {
    managerId:     MANAGER_ID,
    title,
    description,
    type:          document.getElementById('fType').value,
    category:      document.getElementById('fCategory').value,
    requiredLevel: document.getElementById('fLevel').value,
    deadline,
    status:        document.getElementById('fStatus').value,
  };

  const isEdit = !!id;
  const url    = isEdit ? `${API_BASE}?id=${id}` : API_BASE;
  try {
    const res         = await fetch(url, { method: isEdit ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
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
    const res  = await fetch(`${API_BASE}?id=${pendingDeleteId}`, { method: 'DELETE' });
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
document.getElementById('fTitle').addEventListener('input', function () {
  const excludeId = document.getElementById('editId').value || 0;
  checkTitleExists(this.value, excludeId);
});
document.getElementById('filterPublished').addEventListener('click', function () {
  filterStatus     = filterStatus === 'published' ? '' : 'published';
  this.textContent = filterStatus ? 'Show all' : 'Published only';
  loadOpportunities();
});

let searchTimer;
document.getElementById('searchInput').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadOpportunities, 350);
});
['fTitle', 'fDescription', 'fDeadline'].forEach(id => {
  document.getElementById(id).addEventListener('input', function () {
    this.classList.remove('invalid');
  });
});
document.getElementById('filterCategory').addEventListener('change', loadOpportunities);
document.getElementById('filterLevel').addEventListener('change', loadOpportunities);

loadOpportunities();
