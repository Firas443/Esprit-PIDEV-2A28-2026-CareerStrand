const USER_ID = Number(window.CAREERSTRAND_USER?.userId || 1);
const APP_BASE = '../../Controller/ApplicationController.php';
const APPLICATION_STAGES = ['saved', 'applied', 'shortlisted', 'interview', 'accepted', 'rejected'];

let submissions = [];

function statusLabel(status) {
  const value = String(status || '').toLowerCase();
  if (value === 'saved') return 'Saved';
  if (value === 'applied' || value === 'pending') return 'Applied';
  if (value === 'shortlisted') return 'Shortlisted';
  if (value === 'interview') return 'Interview';
  if (value === 'accepted') return 'Accepted';
  if (value === 'rejected') return 'Rejected';
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : 'Applied';
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

function latestTimelineNote(timeline) {
  const items = normalizeTimeline(timeline);
  if (!items.length) return '';
  const last = items[items.length - 1] || {};
  return String(last.note || '').trim();
}

function stageIndex(status) {
  const value = String(status || '').toLowerCase();
  const idx = APPLICATION_STAGES.indexOf(value);
  return idx >= 0 ? idx : 1;
}

function stageTrackerHTML(status) {
  const currentIndex = stageIndex(status);
  return `
    <div class="drawer-stage-track" aria-label="Application progress stages">
      ${APPLICATION_STAGES.map((stage, index) => {
        const state = index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'locked');
        return `
          <div class="drawer-stage ${state}">
            <span>${stage === 'saved' ? 'SV' : stage === 'applied' ? 'AP' : stage === 'shortlisted' ? 'SL' : stage === 'interview' ? 'IN' : stage === 'accepted' ? 'AC' : 'RJ'}</span>
            <small>${statusLabel(stage)}</small>
          </div>`;
      }).join('')}
    </div>`;
}

function timelineHTML(timeline) {
  const items = Array.isArray(timeline) ? timeline : [];
  if (!items.length) {
    return `<div class="drawer-timeline-empty">No timeline yet. Updates will appear here.</div>`;
  }

  return `
    <div class="drawer-timeline">
      ${items.map((entry, index) => {
        const stage = String(entry.stage || '').toLowerCase();
        const label = entry.label || statusLabel(stage);
        const note = entry.note || 'Status updated.';
        const actor = entry.actor || 'CareerStrand team';
        const at = entry.at
          ? new Date(entry.at).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
          : '';
        return `
          <div class="drawer-timeline-item ${index === items.length - 1 ? 'is-current' : ''}">
            <div class="drawer-timeline-dot"></div>
            <div class="drawer-timeline-copy">
              <div class="drawer-timeline-head">
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

function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `toast ${type}`;
  void t.offsetWidth;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

function getApplicationId(app) {
  return Number(app?.applicationId ?? app?.applicationID ?? app?.ApplicationId ?? app?.application_id ?? app?.id ?? 0);
}

async function fetchMyApplications() {
  try {
    const res = await fetch(`${APP_BASE}?source=front&userId=${USER_ID}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.message);

    submissions = json.data.map(a => ({
      id: getApplicationId(a),
      applicationId: getApplicationId(a),
      opportunityId: Number(a.opportunityId || 0),
      motivation: a.motivation || '',
      portfolio: a.portfolio || '',
      status: String(a.status || 'applied').toLowerCase() === 'pending' ? 'applied' : String(a.status || 'applied').toLowerCase(),
      notes: a.applicationNotes || a.notes || '',
      timeline: normalizeTimeline(a.timeline),
      appliedAt: a.appliedAt,
      isEditing: false,
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

async function submitApply(id) {
  const opportunityId = Number(id);
  if (!Number.isInteger(opportunityId) || opportunityId <= 0) {
    showToast('Could not submit: Opportunity ID is missing. Please refresh and try again.', 'error');
    return;
  }

  const o = state.data.find(x => x.id === opportunityId);
  if (!o) return;

  const motivation = document.getElementById('modalMotivation').value.trim();
  const portfolio = document.getElementById('modalPortfolio').value.trim();

  if (!motivation) {
    showToast('Please write your motivation.', 'error');
    return;
  }

  if (portfolio) {
    try {
      if (new URL(portfolio).protocol !== 'https:') throw new Error();
    } catch {
      showToast('Please enter a valid portfolio URL (exp: https://myportfolio.com)', 'error');
      return;
    }
  }

  try {
    const res = await fetch(APP_BASE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        userId: USER_ID,
        opportunityId,
        motivation,
        portfolio: portfolio || null,
        compatibilityScore: o.match,
      }),
    });

    const json = await res.json();
    if (!json.success) throw new Error(json.errors ? json.errors.join('\n') : json.message);

    o.applied = true;
    submissions.push({
      id: json.applicationId,
      applicationId: json.applicationId,
      opportunityId,
      motivation,
      portfolio,
      status: 'applied',
      notes: '',
      timeline: [{
        stage: 'applied',
        label: 'Applied',
        note: 'Application submitted.',
        actor: 'System',
        at: new Date().toISOString(),
      }],
      appliedAt: new Date().toISOString(),
      isEditing: false,
      isEditingPortfolio: false,
    });

    closeApplyModal();
    render();
    renderDrawer();
    showToast('Application submitted.', 'success');
  } catch (e) {
    showToast('Could not submit: ' + e.message, 'error');
  }
}

function updateDrawerBadge() {
  const badge = document.getElementById('drawerBadge');
  const count = submissions.length;
  badge.textContent = count;
  badge.style.display = count > 0 ? 'flex' : 'none';
}

function renderDrawer() {
  const list = document.getElementById('drawerList');
  if (!submissions.length) {
    list.innerHTML = `<div class="drawer-empty"><p>No applications yet.<br>Hit Apply on any opportunity.</p></div>`;
    updateDrawerBadge();
    return;
  }

  const statusMeta = s => {
    const value = String(s || '').toLowerCase();
    if (value === 'saved') return { label: 'Saved', cls: 'status-saved', dot: '#95abeb' };
    if (value === 'applied' || value === 'pending') return { label: 'Applied', cls: 'status-applied', dot: '#f5bf65' };
    if (value === 'shortlisted') return { label: 'Shortlisted', cls: 'status-shortlisted', dot: '#95abeb' };
    if (value === 'interview') return { label: 'Interview', cls: 'status-interview', dot: '#d7a6ff' };
    if (value === 'accepted') return { label: 'Accepted', cls: 'status-accepted', dot: '#59d39b' };
    if (value === 'rejected') return { label: 'Rejected', cls: 'status-rejected', dot: '#ff6e45' };
    return { label: statusLabel(value), cls: 'status-applied', dot: '#f5bf65' };
  };

  list.innerHTML = submissions.map(sub => {
    const opp = state.data.find(o => o.id == sub.opportunityId);
    if (!opp) return '';

    const { label, cls, dot } = statusMeta(sub.status);
    const date = sub.appliedAt ? new Date(sub.appliedAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'â€”';
    const isEditable = ['saved', 'applied', 'pending'].includes(String(sub.status || '').toLowerCase());

    const motivationBlock = sub.isEditing
      ? `<div class="drawer-section-label">Edit your pitch</div>
         <textarea id="edit-${sub.id}" style="width:100%;min-height:80px;padding:10px;border-radius:10px;background:var(--bg-3,#0d1631);border:1px solid var(--border);color:var(--text);font:inherit;font-size:13px;resize:vertical;">${escHtml(sub.motivation)}</textarea>
         <div style="display:flex;gap:8px;margin-top:8px;">
           <button onclick="saveEdit('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:none;background:var(--blue);color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;">Save</button>
           <button onclick="cancelEdit('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--panel);color:var(--muted);font:inherit;font-size:13px;cursor:pointer;">Cancel</button>
         </div>`
      : `<div class="drawer-section-label">Your pitch</div>
         <div class="drawer-text">${sub.motivation || 'â€”'}</div>`;

    const noteText = sub.notes || latestTimelineNote(sub.timeline) || '';
    const notesBlock = `<div class="drawer-notes ${noteText ? '' : 'is-empty'}">${escHtml(noteText || 'No notes added yet.')}</div>`;

    const portfolioBlock = sub.isEditingPortfolio
      ? `<div class="drawer-section-label" style="margin-top:12px;">Edit your portfolio</div>
         <input id="portfolio-${sub.id}" type="text" value="${escHtml(sub.portfolio || '')}" placeholder="https://your-work.com"
           style="width:100%;padding:10px;border-radius:10px;background:var(--bg-3,#0d1631);border:1px solid var(--border);color:var(--text);font:inherit;font-size:13px;box-sizing:border-box;" />
         <div style="display:flex;gap:8px;margin-top:8px;">
           <button onclick="savePortfolio('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:none;background:var(--blue);color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;">Save</button>
           <button onclick="cancelPortfolioEdit('${sub.id}')" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--panel);color:var(--muted);font:inherit;font-size:13px;cursor:pointer;">Cancel</button>
         </div>`
      : `<div class="drawer-section-label" style="margin-top:12px;">Portfolio</div>
         <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
           ${sub.portfolio
             ? `<a href="${escHtml(sub.portfolio)}" target="_blank" rel="noopener" style="color:var(--blue);font-size:13px;word-break:break-all;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;">${escHtml(sub.portfolio)}</a>`
             : `<span class="drawer-text" style="color:var(--muted-2);">â€”</span>`}
           ${isEditable ? `<button onclick="togglePortfolioEdit('${sub.id}')" style="flex-shrink:0;padding:4px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font:inherit;font-size:12px;cursor:pointer;">Edit</button>` : ''}
         </div>`;

    return `
      <div class="drawer-card">
        <div class="drawer-card-top">
          <div class="drawer-identity">
            <div class="company-avatar ${opp.avatarClass}" style="width:36px;height:36px;border-radius:12px;font-size:14px;flex-shrink:0;">${opp.avatar}</div>
            <div><div class="drawer-role">${escHtml(opp.role)}</div><div class="drawer-company">${escHtml(opp.company)}</div></div>
          </div>
          <div class="drawer-status ${cls}"><span class="drawer-dot" style="background:${dot}"></span>${label}</div>
        </div>
        <div class="drawer-meta"><span>Applied ${date}</span><span>${opp.match}% match</span></div>
        <div class="drawer-section-label" style="margin-top:6px;">Progress stage</div>
        ${stageTrackerHTML(sub.status)}
        ${motivationBlock}
        <div class="drawer-section-label" style="margin-top:12px;">Application notes</div>
        ${notesBlock}
        ${portfolioBlock}
        <div class="drawer-section-label" style="margin-top:12px;">Timeline</div>
        ${timelineHTML(sub.timeline)}
        <div style="display:flex;gap:8px;margin-top:12px;">
          ${isEditable ? `<button class="drawer-cancel-btn" onclick="toggleEdit('${sub.id}')">Edit</button>` : ''}
          ${isEditable ? `<button class="drawer-cancel-btn" style="border-color:rgba(255,110,69,.28);color:var(--red);" onclick="cancelSubmission('${sub.id}')">Withdraw</button>` : ''}
        </div>
      </div>`;
  }).join('');

  updateDrawerBadge();
}

function toggleEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) {
    sub.isEditing = !sub.isEditing;
    renderDrawer();
  }
}

function cancelEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) {
    sub.isEditing = false;
    renderDrawer();
  }
}

async function saveEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (!sub) return;

  const textarea = document.getElementById(`edit-${subId}`);
  const newText = textarea ? textarea.value.trim() : '';
  if (!newText) {
    showToast('Motivation cannot be empty.', 'error');
    return;
  }

  try {
    const res = await fetch(`${APP_BASE}?id=${sub.applicationId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ motivation: newText, portfolio: sub.portfolio || null }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    sub.motivation = newText;
    sub.isEditing = false;
    renderDrawer();
  } catch (e) {
    showToast('Could not save: ' + e.message, 'error');
  }
}

function togglePortfolioEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) {
    sub.isEditingPortfolio = !sub.isEditingPortfolio;
    renderDrawer();
  }
}

function cancelPortfolioEdit(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (sub) {
    sub.isEditingPortfolio = false;
    renderDrawer();
  }
}

async function savePortfolio(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (!sub) return;
  const input = document.getElementById(`portfolio-${subId}`);
  const val = input ? input.value.trim() : '';
  if (val) {
    try {
      if (new URL(val).protocol !== 'https:') throw new Error();
    } catch {
      showToast('Portfolio link must start with https://', 'error');
      return;
    }
  }

  try {
    const res = await fetch(`${APP_BASE}?id=${sub.applicationId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ motivation: sub.motivation, portfolio: val || null }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    sub.portfolio = val;
    sub.isEditingPortfolio = false;
    renderDrawer();
    showToast('Portfolio saved.', 'success');
  } catch (e) {
    showToast('Could not save: ' + e.message, 'error');
  }
}

async function cancelSubmission(subId) {
  const sub = submissions.find(s => s.id == subId);
  if (!sub) return;
  try {
    const res = await fetch(`${APP_BASE}?id=${sub.applicationId}`, { method: 'DELETE' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    const o = state.data.find(x => x.id == sub.opportunityId);
    if (o) o.applied = false;
    submissions = submissions.filter(s => s.id != subId);
    render();
    renderDrawer();
  } catch (e) {
    showToast('Could not withdraw: ' + e.message, 'error');
  }
}

function openDrawer() {
  renderDrawer();
  document.getElementById('submissionsDrawer').classList.add('open');
  document.getElementById('drawerBackdrop').classList.add('open');
}

function closeDrawer() {
  document.getElementById('submissionsDrawer').classList.remove('open');
  document.getElementById('drawerBackdrop').classList.remove('open');
}

document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('drawerBtn').addEventListener('click', openDrawer);
  document.getElementById('drawerClose').addEventListener('click', closeDrawer);
  document.getElementById('drawerBackdrop').addEventListener('click', closeDrawer);
});
