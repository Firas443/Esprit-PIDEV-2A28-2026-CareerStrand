
const OPP_BASE = '../../Controller/OpportunityController.php';

// ── ADN SCORING ──
const LEVEL_MAP = { Beginner: 20, Intermediate: 50, Advanced: 80 };

const USER_SCORE = 87; // display only (DNA ring widget)

//display error message in the bottom
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = `toast ${type}`;
  void t.offsetWidth;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

let state = {
  filter: "recommended", cat: "all", search: "", sort: "match", typeFilter: "",
  skillFilter: "",
  data: [],
};
async function fetchUserSkills(userId) {
  const res  = await fetch(`../../Controller/UserSkillController.php?userId=${userId}`);
  const json = await res.json();
  const map  = {};
  if (json.success) json.data.forEach(s => map[s.skillName] = s.skillLevel ?? s.level ?? 0);
  return map;
}
//fetch all public opportunities + closing soon + new 
async function fetchOpportunities() {
  try {
    const userSkillMap = await fetchUserSkills(USER_ID);
    const res  = await fetch(`${OPP_BASE}?source=front`);
    const text = await res.text();
    const json = JSON.parse(text);
    if (!json.success) throw new Error(json.message);
    state.data = json.data.map(o => {
      const opportunityId = Number(o.opportunityId ?? o.opportunityID ?? o.OpportunityId ?? o.opportunity_id ?? o.id ?? 0);
      return {
      id:            opportunityId,
      company:       o.managerName ?? 'CareerStrand',
      role:          o.title,
      avatar:        o.title.charAt(0).toUpperCase(),
      avatarClass:   ['av-blue','av-red','av-mix','av-green'][opportunityId % 4],
      category:      (o.category || '').toLowerCase(),
      categoryKey:   o.category || '',
      skills:        (o.skills || []).map(s => s.skillName),
      level:         o.requiredLevel,
      type:          o.type ? o.type.charAt(0).toUpperCase() + o.type.slice(1) : '',
      deadline:      o.deadline
        ? new Date(o.deadline).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
        : '—',
      isNew:         o.createdAt
        ? (Date.now() - new Date(o.createdAt)) < 4 * 24 * 60 * 60 * 1000
        : false,
      applied:       false,
      saved:         false,
      tags: (() => {
        const t = [];
        if (o.deadline) {
          const ms = new Date(o.deadline) - Date.now();
          if (ms > 0 && ms < 5 * 24 * 60 * 60 * 1000) t.push('closing');
        }
        return t;
      })(),
      // ── ADN compatibility ──
      ...(() => {
        const required = o.skills || []; // [{skillName, requiredLevel, isPrimary}]
  if (!required.length) return { match: 0, label: 'LOW MATCH', gaps: [], isRecommended: false, isPossible: false };

  const W_P = 2; // primary skill weight, tweak as needed

  let weightedSum  = 0;
  let totalWeights = 0;
  const gaps = [];

  required.forEach(skill => {
    const r_i = Number(skill.requiredLevel) || 0;
    const u_i = userSkillMap[skill.skillName] ?? 0;
    const w_i = skill.isPrimary ? W_P : 1;
    const s_i = r_i > 0 ? Math.min(u_i / r_i, 1) : 0;

    weightedSum  += w_i * s_i;
    totalWeights += w_i;

    const g_i = Math.max(r_i - u_i, 0);
    if (g_i > 0) gaps.push({ skillName: skill.skillName, gap: g_i });
  });

  const S     = weightedSum / totalWeights;
  const match = Math.round(S * 100);
  const label = S >= 0.75 ? 'RECOMMENDED' : S >= 0.5 ? 'POSSIBLE' : 'LOW MATCH';

  return {
    match,
    label,
    gaps,
    isRecommended: S >= 0.75,
    isPossible:    S >= 0.5 && S < 0.75,
  };
})(),
    };
    });
    await fetchMyApplications();
    buildSkillFilters();
    render();
  } catch (e) {
    console.error('FETCH ERROR:', e);
    document.getElementById('oppoGrid').innerHTML =
      `<div class="empty-state"><p>Could not load opportunities: ${e.message}</p></div>`;
  }
}

// filter
function getFiltered() {
  let list = state.data;
  if (state.filter === "recommended") list = list.filter(o => o.isRecommended);
  else if (state.filter === "new")     list = list.filter(o => o.isNew);
  else if (state.filter === "closing") list = list.filter(o => o.tags.includes("closing"));
  else if (state.filter === "applied") list = list.filter(o => o.applied);
  else if (state.filter === "saved")   list = list.filter(o => o.saved);
  if (state.cat !== "all") list = list.filter(o => o.category === state.cat);
  if (state.typeFilter) list = list.filter(o => o.type.toLowerCase() === state.typeFilter);
  if (state.skillFilter) list = list.filter(o => o.skills.includes(state.skillFilter));
  if (state.search) {
    const q = state.search.toLowerCase();
    list = list.filter(o =>
      o.role.toLowerCase().includes(q) ||
      o.company.toLowerCase().includes(q) ||
      o.skills.some(s => s.toLowerCase().includes(q))
    );
  }
  if (state.sort === "match")    list = [...list].sort((a, b) => b.match - a.match);
  else if (state.sort === "newest")   list = [...list].sort((a, b) => (b.isNew ? 1 : 0) - (a.isNew ? 1 : 0));
  else if (state.sort === "deadline") list = [...list].sort((a, b) => a.deadline.localeCompare(b.deadline));
  return list;
}

function buildSkillFilters() {
  const select = document.getElementById("skillFilter");
  if (!select) return;
  const current = select.value || state.skillFilter;
  const skills = [...new Set(state.data.flatMap(o => o.skills || []))].sort((a, b) => a.localeCompare(b));
  const wrapper = select.previousElementSibling;
  if (wrapper?.classList.contains('custom-select')) wrapper.remove();
  document.querySelectorAll('.custom-select-panel').forEach(panel => {
    if (panel._wrapper === wrapper) panel.remove();
  });
  select.innerHTML = `<option value="">All skills</option>` + skills.map(skill => `<option value="${escAttr(skill)}">${escHtml(skill)}</option>`).join("");
  select.value = skills.includes(current) ? current : "";
  state.skillFilter = select.value;
  select.style.display = '';
  buildCustomSelect(select);
}

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
  return escHtml(str).replace(/'/g, '&#39;');
}

//show applied or apply now button + build card
function cardHTML(o) {
  const lowScore    = o.gap > 0;
  const closingSoon = o.tags.includes("closing");
  const badges = [
    o.isRecommended ? `<span class="badge badge-recommended">&#9733; Recommended</span>` : "",
    o.isNew         ? `<span class="badge badge-new">New</span>` : "",
    closingSoon     ? `<span class="badge badge-closing">Closing soon</span>` : "",
    (o.type === "Remote" || o.type === "Flexible") ? `<span class="badge badge-remote">${o.type}</span>` : "",
  ].filter(Boolean).join("");
  const skills = o.skills.map(s => `<span class="skill-chip">${s}</span>`).join("");
  const applyBtn = o.applied
    ? `<button class="btn-apply applied" disabled>&#10003; Applied</button>`
    : `<button class="btn-apply" onclick="openModal(${o.id})">Apply now &#x2192;</button>`;
  const saveIcon = o.saved
    ? `<svg viewBox="0 0 16 16" fill="currentColor"><path d="M3 2a1 1 0 011-1h8a1 1 0 011 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>`
    : `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2a1 1 0 011-1h8a1 1 0 011 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>`;
const gapList = o.gaps.map(g => `${g.skillName} (+${g.gap})`).join(', ');
const warningBadge = o.gaps.length ? `
  <div class="gap-badge">
    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 1L1 11h10L6 1z"/><path d="M6 5v3M6 9.5v.5"/></svg>
    Gaps: ${gapList}
  </div>` : '';
  return `
    <article class="oppo-card ${o.isRecommended ? "recommended" : ""}" data-id="${o.id}">
      <div class="card-top"><div class="card-badges">${badges}</div><div class="match-pill">${o.match}% match</div></div>
      <div class="card-identity">
        <div class="company-avatar ${o.avatarClass}">${o.avatar}</div>
        <div class="identity-text"><div class="company-name">${o.company}</div><div class="role-title">${o.role}</div></div>
      </div>
      <div class="card-skills">${skills}</div>
      <div class="card-info">
        <div class="info-item"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="6" r="2.5"/><path d="M13 6c0 4.5-5 8-5 8S3 10.5 3 6a5 5 0 0110 0z"/></svg>${o.type}</div>
        <div class="info-item"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="12" height="11" rx="2"/><path d="M5 1v4M11 1v4M2 7h12"/></svg>Deadline ${o.deadline}</div>
        <div class="info-item"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 10l4-4 3 3 5-6"/></svg>${o.level}</div>
      </div>
      <div class="compat-bar">
        <div class="compat-label-row"><span>DNA compatibility</span><span>${o.match}%</span></div>
        <div class="compat-track"><div class="compat-fill" style="width:${o.match}%"></div></div>
      </div>
      <div class="card-footer">
        ${warningBadge}
        <div class="footer-right">
          <button class="btn-save ${o.saved ? "saved" : ""}" onclick="toggleSave(event,${o.id})" title="Save">${saveIcon}</button>
          ${applyBtn}
        </div>
      </div>
    </article>`;
}

// ── RENDER GRID ──
function render() {
  const list = getFiltered();
  const grid = document.getElementById("oppoGrid");
  document.getElementById("resultCount").textContent = list.length;
  const skillLabel = state.skillFilter ? " + " + state.skillFilter : "";
  document.getElementById("filterLabel").textContent = "Showing " + (state.cat === "all" ? "all categories" : state.cat) + skillLabel;
  grid.innerHTML = list.length === 0
    ? `<div class="empty-state"><p>No opportunities match your current filters.</p></div>`
    : list.map(cardHTML).join("");
  document.querySelectorAll(".filter-tab").forEach(tab => {
    const f = tab.dataset.filter;
    let c = state.data.length;
    if (f === "recommended") c = state.data.filter(o => o.isRecommended).length;
    else if (f === "new")     c = state.data.filter(o => o.isNew).length;
    else if (f === "closing") c = state.data.filter(o => o.tags.includes("closing")).length;
    else if (f === "applied") c = state.data.filter(o => o.applied).length;
    else if (f === "saved")   c = state.data.filter(o => o.saved).length;
    tab.querySelector(".tab-count").textContent = c;
  });
  updateDrawerBadge();
}
//save filter
function toggleSave(e, id) {
  e.stopPropagation();
  const o = state.data.find(x => x.id === id);
  if (o) { o.saved = !o.saved; render(); }
}

//open apply 'window' prefill data + submit button 
function openModal(id) {
  const opportunityId = Number(id);
  if (!Number.isInteger(opportunityId) || opportunityId <= 0) {
    showToast('This opportunity is missing its ID. Please refresh and try again.', 'error');
    return;
  }
  const o = state.data.find(x => x.id === opportunityId);
  if (!o) return;
  document.getElementById("modalRole").textContent    = o.role;
  document.getElementById("modalCompany").textContent = o.company;
  document.getElementById("modalScore").textContent   = o.match + "%";
  document.getElementById("modalBar").style.width     = o.match + "%";
  document.getElementById("modalMotivation").value    = "";
  document.getElementById("modalPortfolio").value     = "";
  document.getElementById("applyModal").classList.add("open");
  document.getElementById("modalSubmit").onclick = () => submitApply(opportunityId);
}

function closeModal() {
  document.getElementById("applyModal").classList.remove("open");
}

// ── CUSTOM SELECT ──
function buildCustomSelect(sel) {
  const wrapper = document.createElement('div');
  wrapper.className = 'custom-select';

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'custom-select-btn';

  const label = document.createElement('span');
  label.className = 'custom-select-label';
  label.textContent = sel.options[sel.selectedIndex]?.text || '';

  const arrow = document.createElement('span');
  arrow.className = 'custom-select-arrow';
  arrow.innerHTML = `<svg viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

  btn.appendChild(label);
  btn.appendChild(arrow);

  // Panel portaled to <body> so card stacking contexts never block it
  const panel = document.createElement('div');
  panel.className = 'custom-select-panel';
  panel._wrapper = wrapper;
  document.body.appendChild(panel);

  function syncLabel() {
    const idx = sel.selectedIndex;
    if (idx >= 0) label.textContent = sel.options[idx].text;
    panel.querySelectorAll('.custom-select-option').forEach(o => {
      o.classList.toggle('selected', o.dataset.value === sel.value);
    });
  }

  function positionPanel() {
    const r = btn.getBoundingClientRect();
    panel.style.top     = (r.bottom + 7) + 'px';
    panel.style.left    = r.left + 'px';
    panel.style.minWidth = Math.max(r.width, 140) + 'px';
  }

  Array.from(sel.options).forEach(opt => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'custom-select-option' + (opt.selected ? ' selected' : '');
    item.dataset.value = opt.value;
    item.textContent = opt.text;
    item.addEventListener('click', () => {
      sel.value = opt.value;
      syncLabel();
      panel.classList.remove('open');
      wrapper.classList.remove('open');
      sel.dispatchEvent(new Event('change'));
    });
    panel.appendChild(item);
  });

  btn.addEventListener('click', e => {
    e.stopPropagation();
    const opening = !panel.classList.contains('open');
    closeAllCustomSelects();
    if (opening) { positionPanel(); panel.classList.add('open'); wrapper.classList.add('open'); }
  });

  wrapper.appendChild(btn);
  sel.style.display = 'none';
  sel.parentNode.insertBefore(wrapper, sel);
}

function closeAllCustomSelects() {
  document.querySelectorAll('.custom-select-panel.open').forEach(p => {
    p.classList.remove('open');
    if (p._wrapper) p._wrapper.classList.remove('open');
  });
}

document.addEventListener('click', closeAllCustomSelects);

// ── INIT ──
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".filter-tab").forEach(tab => {
    tab.addEventListener("click", () => {
      document.querySelectorAll(".filter-tab").forEach(t => t.classList.remove("active"));
      tab.classList.add("active");
      state.filter = tab.dataset.filter;
      render();
    });
  });
  //event listener
  document.getElementById("searchInput").addEventListener("input", e => { state.search = e.target.value; render(); });
  document.getElementById("typeFilter").addEventListener("change", e => { state.typeFilter = e.target.value; render(); });
  document.getElementById("sortSelect").addEventListener("change", e => { state.sort = e.target.value; render(); });
  document.getElementById("categoryFilter").addEventListener("change", e => { state.cat = e.target.value; render(); });
  document.getElementById("skillFilter").addEventListener("change", e => { state.skillFilter = e.target.value; render(); });
  document.querySelectorAll('.pill-select').forEach(buildCustomSelect);
  document.getElementById("modalClose").addEventListener("click", closeModal);
  document.getElementById("modalCancel").addEventListener("click", closeModal);
  document.getElementById("applyModal").addEventListener("click", e => { if (e.target === e.currentTarget) closeModal(); });
  fetchOpportunities();
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add("show"); });
  }, { threshold: 0.08 });
  document.querySelectorAll(".fade-up").forEach(el => {
    if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add("show");
    else observer.observe(el);
  });
});
