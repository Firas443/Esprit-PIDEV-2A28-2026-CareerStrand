// MANAGER_ID is injected by admin-opportunities.php from $_SESSION['userId']
const API_BASE        = '../../Controller/OpportunityController.php';
const SKILL_API_BASE  = '../../Controller/OpportunitySkillController.php';
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
//display error notificaton at the bottom of the screen 
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

function closeCustomSelects() {
  document.querySelectorAll('.cs-select-panel.open').forEach(panel => {
    panel.classList.remove('open');
    panel._wrapper?.classList.remove('open');
  });
}

function syncCustomSelect(sel) {
  if (!sel?._csWrapper) return;
  const label = sel._csWrapper.querySelector('.cs-select-label');
  if (label) label.textContent = sel.options[sel.selectedIndex]?.text || '';
  sel._csPanel?.querySelectorAll('.cs-select-option').forEach(option => {
    option.classList.toggle('selected', option.dataset.value === sel.value);
  });
}

function enhanceSelect(sel) {
  if (!sel || sel._csWrapper) return;
  sel.hidden = true;
  sel.tabIndex = -1;
  sel.setAttribute('aria-hidden', 'true');
  sel.style.display = 'none';
  sel.style.pointerEvents = 'none';

  const wrapper = document.createElement('div');
  wrapper.className = 'cs-select';

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'cs-select-button';

  const label = document.createElement('span');
  label.className = 'cs-select-label';
  label.textContent = sel.options[sel.selectedIndex]?.text || '';

  const arrow = document.createElement('span');
  arrow.className = 'cs-select-arrow';
  arrow.textContent = 'v';

  button.append(label, arrow);
  wrapper.appendChild(button);

  const panel = document.createElement('div');
  panel.className = 'cs-select-panel';
  panel._wrapper = wrapper;

  function rebuildOptions() {
    panel.innerHTML = '';
    Array.from(sel.options).forEach(opt => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'cs-select-option';
      item.dataset.value = opt.value;
      item.textContent = opt.textContent;
      item.disabled = opt.disabled;
      item.classList.toggle('selected', opt.value === sel.value);
      item.addEventListener('click', () => {
        if (item.disabled) return;
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        syncCustomSelect(sel);
        closeCustomSelects();
      });
      panel.appendChild(item);
    });
  }

  function positionPanel() {
    const rect = button.getBoundingClientRect();
    panel.style.left = rect.left + 'px';
    panel.style.top = (rect.bottom + 6) + 'px';
    panel.style.minWidth = rect.width + 'px';
  }

  button.addEventListener('click', e => {
    e.stopPropagation();
    const opening = !panel.classList.contains('open');
    closeCustomSelects();
    if (opening) {
      rebuildOptions();
      positionPanel();
      panel.classList.add('open');
      wrapper.classList.add('open');
    }
  });

  sel.addEventListener('change', () => syncCustomSelect(sel));
  window.addEventListener('resize', closeCustomSelects);
  window.addEventListener('scroll', closeCustomSelects, true);

  sel.parentNode.insertBefore(wrapper, sel);
  document.body.appendChild(panel);
  sel.hidden = true;
  sel.classList.add('native-select-hidden');
  sel.style.display = 'none';
  sel._csWrapper = wrapper;
  sel._csPanel = panel;
}

function enhanceOpportunitySelects(scope = document) {
  scope.querySelectorAll('select.pill-select, select.skill-level-select')
    .forEach(enhanceSelect);
}

function getWorkModeValue() {
  return String(document.getElementById('fWorkMode')?.value || 'onsite').toLowerCase();
}

function syncLocationField() {
  const locationField = document.getElementById('fLocation');
  if (!locationField) return;
  const remote = getWorkModeValue() === 'remote';
  locationField.disabled = remote;
  locationField.placeholder = remote ? 'Remote roles do not need a location' : 'e.g. Tunis, Sousse, Lagos';
  if (remote) {
    locationField.value = '';
  }
}

document.addEventListener('click', closeCustomSelects);

// AJAX CHECK
let titleCheckTimer = null;
let titleIsValid    = true;
//reset title border (remove the red when error and hide the error msg)
function resetTitleValidation() {
  document.getElementById('fTitle').classList.remove('invalid', 'valid');
  document.getElementById('titleError').classList.remove('visible');
  titleIsValid = true;
}
//check if title already existed in the database (controle de saisie) + show the red border when error 
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
//fetch opportunities and render them 
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
    const sideCount = document.getElementById('sideCount');
    if (sideCount) sideCount.textContent = allOppos.length;
  } catch (e) {
    showToast('Failed to load: ' + e.message, 'error');
    document.getElementById('oppoBody').innerHTML =
      `<tr><td colspan="11" class="empty-row">Failed to load opportunities.</td></tr>`;
  }
}

// take list of opportunities and build table 
function renderTable(list) {
  const tbody = document.getElementById('oppoBody');
  document.getElementById('tableCaption').textContent =
    `${list.length} opportunit${list.length === 1 ? 'y' : 'ies'} found`;
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="11" class="empty-row">No opportunities match your filters.</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(o => {
    const deadline    = o.deadline  ? new Date(o.deadline).toLocaleDateString('en-GB',  { day:'numeric', month:'short', year:'numeric' }) : '—';
    const created     = o.createdAt ? new Date(o.createdAt).toLocaleDateString('en-GB', { day:'numeric', month:'short' }) : '—';
    const createdBy   = o.managerName || '—';
    const statusClass = { published:'status-published', draft:'status-draft', archived:'status-archived' }[o.status] || '';
    const toggleLabel = o.status === 'published' ? 'Unpublish' : o.status === 'draft' ? 'Publish' : 'Restore';
    const modeLabel = String(o.workMode || 'onsite').toUpperCase();
    const locationLabel = o.workMode === 'remote' ? 'Remote' : (o.location ? escHtml(o.location) : '—');
    return `<tr data-id="${o.opportunityId}">
      <td><strong>${escHtml(o.title)}</strong></td>
      <td>${escHtml(o.type)}</td>
      <td><span class="category-chip">${escHtml(o.category)}</span></td>
      <td><div style="display:grid;gap:4px"><span>${locationLabel}</span><span style="font-size:11px;color:var(--muted)">${modeLabel}</span></div></td>
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

//toggle status (published/draft/archive) and then send new data
async function quickToggleStatus(id, currentStatus) {
  const next = currentStatus === 'published' ? 'draft' : currentStatus === 'draft' ? 'published' : 'published';
  const o    = allOppos.find(x => x.opportunityId == id);
  if (!o) return;
  try {
    const res         = await fetch(`${API_BASE}?id=${id}` ,{
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        title: o.title,
        description: o.description,
        type: o.type,
        category: o.category,
        location: o.location || '',
        workMode: o.workMode || 'onsite',
        deadline: o.deadline,
        requiredLevel: o.requiredLevel,
        status: next,
      }),
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

const SKILL_MULTI_TARGET_ID = 'oppSkills';
const AVAILABLE_SKILLS = Array.isArray(window.SKILL_LIBRARY_OPTIONS)
  ? window.SKILL_LIBRARY_OPTIONS
  : [];

function isSkillInLibrary(skillName) {
  const value = String(skillName || '').trim().toLowerCase();
  if (!value || !AVAILABLE_SKILLS.length) return true;
  return AVAILABLE_SKILLS.some(skill => String(skill || '').trim().toLowerCase() === value);
}

function getSkillMultiContainer() {
  return document.querySelector(`[data-skill-multi][data-skill-target="${SKILL_MULTI_TARGET_ID}"]`);
}

function normalizeSkillRow(row) {
  return {
    name: String(row?.skillName ?? row?.name ?? row?.value ?? '').trim(),
    level: String(row?.requiredLevel ?? row?.level ?? '50'),
    primary: Number(row?.isPrimary ?? row?.primary ?? 0) ? 1 : 0,
  };
}

function setSkillRows(rows) {
  const normalized = Array.isArray(rows) ? rows.map(normalizeSkillRow) : [];
  if (window.SkillMulti) {
    window.SkillMulti.set(SKILL_MULTI_TARGET_ID, normalized);
  } else {
    const hidden = document.getElementById(SKILL_MULTI_TARGET_ID);
    if (hidden) hidden.value = JSON.stringify(normalized);
  }
  refreshSkillSelects();
}

function clearSkillRows() {
  if (window.SkillMulti) {
    window.SkillMulti.clear(SKILL_MULTI_TARGET_ID);
  } else {
    const hidden = document.getElementById(SKILL_MULTI_TARGET_ID);
    if (hidden) hidden.value = '[]';
  }
  refreshSkillSelects();
}

function getSkillsFromUI() {
  if (window.SkillMulti) {
    return window.SkillMulti.values(SKILL_MULTI_TARGET_ID)
      .map(row => ({
        skillName: String(row?.name ?? '').trim(),
        requiredLevel: parseInt(row?.level ?? '50', 10),
        isPrimary: row?.primary ? 1 : 0,
      }))
      .filter(skill => skill.skillName);
  }

  const hidden = document.getElementById(SKILL_MULTI_TARGET_ID);
  if (!hidden?.value) return [];
  try {
    const parsed = JSON.parse(hidden.value);
    return (Array.isArray(parsed) ? parsed : []).map(normalizeSkillRow)
      .map(row => ({
        skillName: row.name,
        requiredLevel: parseInt(row.level, 10),
        isPrimary: row.primary,
      }))
      .filter(skill => skill.skillName);
  } catch (error) {
    return [];
  }
}

function getUsedSkills() {
  return getSkillsFromUI().map(skill => skill.skillName);
}

function addSkillRow(skill = null) {
  const rows = getSkillsFromUI().map(row => ({
    skillName: row.skillName,
    requiredLevel: row.requiredLevel,
    isPrimary: row.isPrimary,
  }));
  rows.push(skill ? normalizeSkillRow(skill) : { skillName: '', level: '50', primary: 0 });
  setSkillRows(rows);
}

function refreshSkillSelects() {
  const container = getSkillMultiContainer();
  if (!container) return;

  const usedSkills = getUsedSkills();
  container.querySelectorAll('.skill-multi-input').forEach(input => {
    const name = input.value.trim();
    const duplicate = usedSkills.filter(s => s.toLowerCase() === name.toLowerCase()).length > 1;
    const inLibrary = isSkillInLibrary(name);
    input.classList.toggle('invalid', !!name && (duplicate || !inLibrary));
  });
}

async function saveSkillsForOpportunity(oppId) {
  if (!Number.isInteger(Number(oppId)) || Number(oppId) <= 0) {
    throw new Error('Opportunity ID is missing, so skills could not be saved.');
  }

  let res = await fetch(`${SKILL_API_BASE}?opportunityId=${oppId}`, { method: 'DELETE' });
  let json = await res.json();
  if (!json.success) throw new Error(json.message || 'Could not clear old skills.');

  const skills = getSkillsFromUI();
  if (skills.some(skill => !isSkillInLibrary(skill.skillName))) {
    throw new Error('Please choose required skills from the shared skill library.');
  }
  const skillKeys = new Set();
  if (skills.some(skill => {
    const key = skill.skillName.trim().toLowerCase();
    if (!key) return false;
    if (skillKeys.has(key)) return true;
    skillKeys.add(key);
    return false;
  })) {
    throw new Error('Please use each skill only once.');
  }

  const hasPrimary = skills.some(s => s.isPrimary);
  if (!hasPrimary && skills.length > 0) {
    skills[0].isPrimary = 1;
    setSkillRows(skills);
  }

  for (const skill of skills) {
    res = await fetch(SKILL_API_BASE, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        opportunityId: oppId,
        skillName:     skill.skillName,
        requiredLevel: skill.requiredLevel,
        weight:        1.0,
        isPrimary:     skill.isPrimary,
      }),
    });
    json = await res.json();
    if (!json.success) {
      throw new Error(json.errors ? json.errors.join('\n') : (json.message || 'Could not save a required skill.'));
    }
  }
}

async function loadSkillsForOpportunity(oppId) {
  clearSkillRows();
  try {
    const res  = await fetch(`${SKILL_API_BASE}?opportunityId=${oppId}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Could not load saved skills.');
    if (json.success && json.data.length) {
      setSkillRows(json.data);
    } else {
      clearSkillRows();
    }
  } catch (e) {
    console.error('Skills load error:', e);
    showToast('Could not load saved skills: ' + e.message, 'error');
  }
}

//open the create 'window' clear all fields
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
  document.getElementById('fLocation').value        = '';
  document.getElementById('fWorkMode').value        = 'onsite';
  ['fType', 'fCategory', 'fLevel', 'fStatus', 'fWorkMode'].forEach(id => syncCustomSelect(document.getElementById(id)));
  syncLocationField();
  clearSkillRows();
  openModal('formModal');
}

//open the 'edit' window and prefill existing data
async function openEditModal(id) {
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
  document.getElementById('fLocation').value        = o.workMode === 'remote' ? '' : (o.location || '');
  document.getElementById('fWorkMode').value        = o.workMode || 'onsite';
  ['fType', 'fCategory', 'fLevel', 'fStatus', 'fWorkMode'].forEach(id => syncCustomSelect(document.getElementById(id)));
  syncLocationField();
  clearSkillRows();
  await loadSkillsForOpportunity(id);
  openModal('formModal');
}

//save all the data 
async function saveOpportunity() {
  const title       = document.getElementById('fTitle').value.trim();
  const description = document.getElementById('fDescription').value.trim();
  const deadline    = document.getElementById('fDeadline').value;

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
  const workMode = getWorkModeValue();
  const location = document.getElementById('fLocation').value.trim();
  if (workMode !== 'remote' && !location) {
    document.getElementById('fLocation').classList.add('invalid');
    showToast('Location is required for onsite and hybrid opportunities.', 'error');
    return;
  }
  const today = new Date(); today.setHours(0,0,0,0);
  if (new Date(deadline) < today) {
    document.getElementById('fDeadline').classList.add('invalid');
    showToast('Deadline must be today or in the future.', 'error');
    return;
  }

  // ── Validate skills ──
  const skills = getSkillsFromUI();
  if (skills.length === 0) {
    showToast('Please add at least one required skill.', 'error');
    return;
  }
  if (skills.some(skill => !isSkillInLibrary(skill.skillName))) {
    showToast('Choose skills from the shared skill library.', 'error');
    return;
  }
  const usedSkillNames = new Set();
  if (skills.some(skill => {
    const key = skill.skillName.trim().toLowerCase();
    if (!key) return false;
    if (usedSkillNames.has(key)) return true;
    usedSkillNames.add(key);
    return false;
  })) {
    showToast('Please use each skill only once.', 'error');
    return;
  }
  const hasPrimary = skills.some(s => s.isPrimary);
  if (!hasPrimary) {
    showToast('Please mark one skill as Primary.', 'error');
    return;
  }

  const id   = document.getElementById('editId').value;
  const body = {
    managerId:     MANAGER_ID,
    title,
    description,
    type:          document.getElementById('fType').value,
    category:      document.getElementById('fCategory').value,
    location:      workMode === 'remote' ? '' : location,
    workMode,
    requiredLevel: document.getElementById('fLevel').value,
    deadline,
    status:        document.getElementById('fStatus').value,
  };

  const isEdit = !!id;
  const url    = isEdit ? `${API_BASE}?id=${id}` : API_BASE;
  try {
    const res         = await fetch(url, {
      method:  isEdit ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body),
    });
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      const raw = await res.text();
      throw new Error('Server error: ' + raw.substring(0, 200));
    }
    const json = await res.json();
    if (!json.success) throw new Error(json.errors ? json.errors.join('\n') : json.message);

    const oppId = isEdit ? parseInt(id) : json.opportunityId;
    await saveSkillsForOpportunity(oppId);

    showToast(isEdit ? 'Opportunity updated.' : 'Opportunity created.');
    closeModal('formModal');
    loadOpportunities();
  } catch (e) { showToast(e.message, 'error'); }
}

//open delete 'window' and store the id 
function openDeleteModal(id, title) {
  pendingDeleteId = id;
  document.getElementById('deleteTarget').textContent = `"${title}"`;
  openModal('deleteModal');
}
//delete the opportunity that was in the stored id 
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

// EVENT LISTENERS 
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
document.getElementById('fWorkMode').addEventListener('change', syncLocationField);
document.getElementById('fLocation').addEventListener('input', function () {
  this.classList.remove('invalid');
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
document.addEventListener('input', (event) => {
  if (event.target && event.target.classList && event.target.classList.contains('skill-multi-input')) {
    refreshSkillSelects();
  }
});
document.addEventListener('change', (event) => {
  if (event.target && event.target.classList && event.target.classList.contains('skill-multi-input')) {
    refreshSkillSelects();
  }
});

enhanceOpportunitySelects();
loadOpportunities();
