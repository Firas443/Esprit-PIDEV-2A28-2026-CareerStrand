const MANAGER_ID      = 1;
const API_BASE        = '/Careerstrand/controller/OpportunityController.php';
const SKILL_API_BASE  = '/Careerstrand/controller/OpportunitySkillController.php';
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
    document.getElementById('sideCount').textContent = allOppos.length;
  } catch (e) {
    showToast('Failed to load: ' + e.message, 'error');
    document.getElementById('oppoBody').innerHTML =
      `<tr><td colspan="10" class="empty-row">Failed to load opportunities.</td></tr>`;
  }
}

// take list of opportunities and build table 
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

//toggle status (published/draft/archive) and then send new data
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

const AVAILABLE_SKILLS = [
  'Technical', 'Creativity', 'Business', 'Communication', 'Leadership',
  'Figma', 'HTML/CSS', 'JavaScript', 'Python', 'React',
  'UI/UX Design', 'Data Analysis', 'Project Management', 'Marketing', 'Copywriting'
];

// ── SKILL ROWS ──
function clearSkillRows() {
  const list = document.getElementById('skillsList');
  const empty = document.getElementById('skillsEmptyMsg');

  if (list) list.innerHTML = '';
  if (empty) empty.style.display = '';
}
function getUsedSkills() {
  return Array.from(document.querySelectorAll('#skillsList .skill-name-select'))
    .map(s => s.value);
}

function addSkillRow(skill = null) {
  const emptyMsg = document.getElementById('skillsEmptyMsg');
if (emptyMsg) emptyMsg.style.display = 'none';
  const usedSkills = getUsedSkills();
  const row        = document.createElement('div');
  row.className    = 'skill-row';

  // Skill name — dropdown not free text
  const nameSel       = document.createElement('select');
  nameSel.className   = 'skill-name-select';

  const placeholder   = document.createElement('option');
  placeholder.value   = '';
  placeholder.textContent = 'Choose a skill…';
  placeholder.disabled    = true;
  placeholder.selected    = !skill;
  nameSel.appendChild(placeholder);

  AVAILABLE_SKILLS.forEach(s => {
    const opt         = document.createElement('option');
    opt.value         = s;
    opt.textContent   = s;
    // disable already used skills (except current row's own value)
    if (usedSkills.includes(s) && (!skill || skill.skillName !== s)) {
      opt.disabled = true;
    }
    if (skill && skill.skillName === s) opt.selected = true;
    nameSel.appendChild(opt);
  });

  // When skill is changed, refresh all rows to update disabled options
  nameSel.addEventListener('change', () => refreshSkillSelects());

  // Level dropdown
  const levelSel      = document.createElement('select');
  levelSel.className  = 'skill-level-select';
  [{ v: '20', t: 'Beginner' }, { v: '50', t: 'Intermediate' }, { v: '80', t: 'Advanced' }]
    .forEach(opt => {
      const o       = document.createElement('option');
      o.value       = opt.v;
      o.textContent = opt.t;
      if (skill && String(skill.requiredLevel) === opt.v) o.selected = true;
      levelSel.appendChild(o);
    });

  // Primary badge
  const primaryLabel      = document.createElement('label');
  primaryLabel.className  = 'skill-primary-label';
  const primaryCheck      = document.createElement('input');
  primaryCheck.type       = 'checkbox';
  primaryCheck.className  = 'skill-primary-check';
  if (skill && skill.isPrimary) primaryCheck.checked = true;
  // Only one primary allowed
  primaryCheck.addEventListener('change', () => {
    if (primaryCheck.checked) {
      document.querySelectorAll('.skill-primary-check').forEach(c => {
        if (c !== primaryCheck) c.checked = false;
      });
    }
  });
  primaryLabel.appendChild(primaryCheck);
  primaryLabel.appendChild(document.createTextNode('Primary'));

  // Remove button
  const removeBtn         = document.createElement('button');
  removeBtn.type          = 'button';
  removeBtn.className     = 'btn-remove-skill';
  removeBtn.textContent   = '×';
  removeBtn.addEventListener('click', () => {
    row.remove();
    refreshSkillSelects();
  });

  row.appendChild(nameSel);
  row.appendChild(levelSel);
  row.appendChild(primaryLabel);
  row.appendChild(removeBtn);
  document.getElementById('skillsList').appendChild(row);
}

// Refresh all skill dropdowns to keep disabled options in sync
function refreshSkillSelects() {
  const usedSkills = getUsedSkills();
  document.querySelectorAll('#skillsList .skill-name-select').forEach(sel => {
    const currentVal = sel.value;
    Array.from(sel.options).forEach(opt => {
      if (opt.value === '') return;
      opt.disabled = usedSkills.includes(opt.value) && opt.value !== currentVal;
    });
  });
}

function getSkillsFromUI() {
  const rows = Array.from(document.querySelectorAll('#skillsList .skill-row'));
  return rows.map(row => ({
    skillName:     row.querySelector('.skill-name-select').value,
    requiredLevel: parseInt(row.querySelector('.skill-level-select').value),
    isPrimary:     row.querySelector('.skill-primary-check').checked ? 1 : 0,
  })).filter(s => s.skillName); // remove rows with no skill selected
}

async function saveSkillsForOpportunity(oppId) {
  // Delete existing skills first
  await fetch(`${SKILL_API_BASE}?opportunityId=${oppId}`, { method: 'DELETE' });

  const skills = getSkillsFromUI();

  // Ensure at least one primary
  const hasPrimary = skills.some(s => s.isPrimary);
  if (!hasPrimary && skills.length > 0) skills[0].isPrimary = 1;

  for (const skill of skills) {
    await fetch(SKILL_API_BASE, {
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
  }
}

async function loadSkillsForOpportunity(oppId) {
  clearSkillRows();
  try {
    const res  = await fetch(`${SKILL_API_BASE}?opportunityId=${oppId}`);
    const json = await res.json();
    if (json.success && json.data.length) {
      json.data.forEach(s => addSkillRow(s));
    }
  } catch (e) { console.error('Skills load error:', e); }
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
document.getElementById('btnAddSkill').addEventListener('click', () => {
  addSkillRow();
  // hide empty message when at least one row exists
  document.getElementById('skillsEmptyMsg').style.display = 'none';
});

loadOpportunities();
