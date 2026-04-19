let submissions = [];

// ── FETCH MY APPLICATIONS ──
async function fetchMyApplications() {
  try {
    const res  = await fetch(`${APP_BASE}get_applications.php?source=front&userId=${USER_ID}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    submissions = json.data.map(a => ({
      id:                 a.applicationId,
      applicationId:      a.applicationId,
      opportunityId:      a.opportunityId,
      motivation:         a.motivation || '',
      portfolio:          a.portfolio  || '',
      status:             a.status     || 'pending',
      appliedAt:          a.appliedAt,
      isEditing:          false,
      isEditingPortfolio: false,
    }));
    submissions.forEach(s => {
      const o = state.data.find(x => x.id == s.opportunityId);
      if (o) o.applied = true;
    });
  } catch (e) {
    console.error('Failed to load applications:', e);
  }
}

// ── SUBMIT APPLICATION ──
async function submitApply(id) {
  const o = state.data.find(x => x.id === id);
  if (!o) return;
  const motivation = document.getElementById('modalMotivation').value.trim();
  const portfolio  = document.getElementById('modalPortfolio').value.trim();
  if (!motivation) { showToast('Please write your motivation.', 'error'); return; }
  if (portfolio) {
    try { if (new URL(portfolio).protocol !== 'https:') throw new Error(); }
    catch { showToast('Please enter a valid portfolio URL (e.g. https://myportfolio.com)', 'error'); return; }
  }
  try {
    const res  = await fetch(`${APP_BASE}create_application.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        userId:             USER_ID,
        opportunityId:      id,
        motivation:         motivation,
        portfolio:          portfolio || null,
        compatibilityScore: o.match,
      }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.errors ? json.errors.join('\n') : json.message);
    o.applied = true;
    submissions.push({
      id:                 json.applicationId,
      applicationId:      json.applicationId,
      opportunityId:      id,
      motivation:         motivation,
      portfolio:          portfolio,
      status:             'pending',
      appliedAt:          new Date().toISOString(),
      isEditing:          false,
      isEditingPortfolio: false,
    });
    closeModal(); render(); renderDrawer();
  } catch (e) {
    showToast('Could not submit: ' + e.message, 'error');
  }
}

// ── DRAWER BADGE ──
function updateDrawerBadge() {
  const badge = document.getElementById("drawerBadge");
  const count = submissions.length;
  badge.textContent  = count;
  badge.style.display = count > 0 ? "flex" : "none";
}

// ── RENDER DRAWER ──
function renderDrawer() {
  const list = document.getElementById('drawerList');
  if (!submissions.length) {
    list.innerHTML = `<div class="drawer-empty"><p>No applications yet.<br>Hit Apply on any opportunity.</p></div>`;
    updateDrawerBadge(); return;
  }

  const statusMeta = s => {
    if (s === 'accepted') return { label: 'Accepted', cls: 'status-accepted', dot: '#59d39b' };
    if (s === 'rejected') return { label: 'Rejected',  cls: 'status-rejected', dot: '#ff6e45' };
    return { label: 'Pending', cls: 'status-pending', dot: '#f5bf65' };
  };

  list.innerHTML = submissions.map(sub => {
    const opp = state.data.find(o => o.id == sub.opportunityId);
    if (!opp) return '';
    const { label, cls, dot } = statusMeta(sub.status);
    const date      = sub.appliedAt ? new Date(sub.appliedAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
    const isPending = sub.status === 'pending';

    const motivationBlock = sub.isEditing
      ? `<div class="drawer-section-label">Edit your pitch</div>
         <textarea id="edit-${sub.id}" style="width:100%;min-height:80px;padding:10px;border-radius:10px;background:var(--bg-3,#0d1631);border:1px solid var(--border);color:var(--text);font:inherit;font-size:13px;resize:vertical;">${sub.motivation}</textarea>
         <div style="display:flex;gap:8px;margin-top:8px;">
           <button onclick="saveEdit('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:none;background:var(--blue);color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;">Save</button>
           <button onclick="cancelEdit('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--panel);color:var(--muted);font:inherit;font-size:13px;cursor:pointer;">Cancel</button>
         </div>`
      : `<div class="drawer-section-label">Your pitch</div>
         <div class="drawer-text">${sub.motivation || '—'}</div>`;

    const portfolioBlock = sub.isEditingPortfolio
      ? `<div class="drawer-section-label" style="margin-top:12px;">Edit your portfolio</div>
         <input id="portfolio-${sub.id}" type="text" value="${sub.portfolio || ''}" placeholder="https://your-work.com"
           style="width:100%;padding:10px;border-radius:10px;background:var(--bg-3,#0d1631);border:1px solid var(--border);color:var(--text);font:inherit;font-size:13px;box-sizing:border-box;" />
         <div style="display:flex;gap:8px;margin-top:8px;">
           <button onclick="savePortfolio('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:none;background:var(--blue);color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;">Save</button>
           <button onclick="cancelPortfolioEdit('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--panel);color:var(--muted);font:inherit;font-size:13px;cursor:pointer;">Cancel</button>
         </div>`
      : `<div class="drawer-section-label" style="margin-top:12px;">Portfolio</div>
         <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
           ${sub.portfolio
             ? `<a href="${sub.portfolio}" target="_blank" rel="noopener" style="color:var(--blue);font-size:13px;word-break:break-all;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;">${sub.portfolio}</a>`
             : `<span class="drawer-text" style="color:var(--muted-2);">—</span>`}
           ${isPending ? `<button onclick="togglePortfolioEdit('${sub.id}')" style="flex-shrink:0;padding:4px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font:inherit;font-size:12px;cursor:pointer;">Edit</button>` : ''}
         </div>`;

    return `
      <div class="drawer-card">
        <div class="drawer-card-top">
          <div class="drawer-identity">
            <div class="company-avatar ${opp.avatarClass}" style="width:36px;height:36px;border-radius:12px;font-size:14px;flex-shrink:0;">${opp.avatar}</div>
            <div><div class="drawer-role">${opp.role}</div><div class="drawer-company">${opp.company}</div></div>
          </div>
          <div class="drawer-status ${cls}"><span class="drawer-dot" style="background:${dot}"></span>${label}</div>
        </div>
        <div class="drawer-meta"><span>Applied ${date}</span><span>${opp.match}% match</span></div>
        ${motivationBlock}
        ${portfolioBlock}
        <div style="display:flex;gap:8px;margin-top:12px;">
          ${isPending ? `<button class="drawer-cancel-btn" onclick="toggleEdit('${sub.id}')">Edit</button>` : ''}
          ${isPending ? `<button class="drawer-cancel-btn" style="border-color:rgba(255,110,69,.28);color:var(--red);" onclick="cancelSubmission('${sub.id}')">Withdraw</button>` : ''}
        </div>
      </div>`;
  }).join('');

  updateDrawerBadge();
}

// ── EDIT PITCH ──
function toggleEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) { sub.isEditing = !sub.isEditing; renderDrawer(); }
}
function cancelEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) { sub.isEditing = false; renderDrawer(); }
}
async function saveEdit(subId) {
  const sub      = submissions.find(s => s.id == subId);
  if (!sub) return;
  const textarea = document.getElementById(`edit-${subId}`);
  const newText  = textarea ? textarea.value.trim() : '';
  if (!newText) { showToast('Motivation cannot be empty.', 'error'); return; }
  try {
    const res  = await fetch(`${APP_BASE}update_application.php?id=${sub.applicationId}`, {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ motivation: newText, portfolio: sub.portfolio || null }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    sub.motivation = newText;
    sub.isEditing  = false;
    renderDrawer();
  } catch (e) {
    showToast('Could not save: ' + e.message, 'error');
  }
}

// ── EDIT PORTFOLIO ──
function togglePortfolioEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) { sub.isEditingPortfolio = !sub.isEditingPortfolio; renderDrawer(); }
}
function cancelPortfolioEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) { sub.isEditingPortfolio = false; renderDrawer(); }
}
async function savePortfolio(subId) {
  const sub   = submissions.find(s => s.id == subId);
  if (!sub) return;
  const input = document.getElementById(`portfolio-${subId}`);
  const val   = input ? input.value.trim() : '';
  if (val) {
    try { if (new URL(val).protocol !== 'https:') throw new Error(); }
    catch { showToast('Portfolio link must start with https://', 'error'); return; }
  }
  try {
    const res  = await fetch(`${APP_BASE}update_application.php?id=${sub.applicationId}`, {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ motivation: sub.motivation, portfolio: val || null }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    sub.portfolio          = val;
    sub.isEditingPortfolio = false;
    renderDrawer();
    showToast('Portfolio saved.', 'success');
  } catch (e) {
    showToast('Could not save: ' + e.message, 'error');
  }
}

// ── WITHDRAW ──
async function cancelSubmission(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (!sub) return;
  try {
    const res  = await fetch(`${APP_BASE}delete_application.php?id=${sub.applicationId}`, { method: 'DELETE' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    const o = state.data.find(x => x.id == sub.opportunityId);
    if (o) o.applied = false;
    submissions = submissions.filter(s => s.id != subId);
    render(); renderDrawer();
  } catch (e) {
    showToast('Could not withdraw: ' + e.message, 'error');
  }
}

// ── DRAWER OPEN / CLOSE ──
function openDrawer()  {
  renderDrawer();
  document.getElementById("submissionsDrawer").classList.add("open");
  document.getElementById("drawerBackdrop").classList.add("open");
}
function closeDrawer() {
  document.getElementById("submissionsDrawer").classList.remove("open");
  document.getElementById("drawerBackdrop").classList.remove("open");
}

// ── INIT ──
document.addEventListener("DOMContentLoaded", function () {
  document.getElementById("drawerBtn").addEventListener("click", openDrawer);
  document.getElementById("drawerClose").addEventListener("click", closeDrawer);
  document.getElementById("drawerBackdrop").addEventListener("click", closeDrawer);
});
