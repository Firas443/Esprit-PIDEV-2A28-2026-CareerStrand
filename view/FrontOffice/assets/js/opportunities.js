
const OPP_BASE = '/Careerstrand/controller/OpportunityController.php';
const USER_SCORE = 87;

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
  filter: "recommended", cat: "all", search: "", sort: "match",
  data: [],
};

//fetch all public opportunities
async function fetchOpportunities() {
  try {
    const res  = await fetch(`${OPP_BASE}?source=front`);
    const text = await res.text();
    const json = JSON.parse(text);
    if (!json.success) throw new Error(json.message);
    state.data = json.data.map(o => ({
      id:            o.opportunityId,
      company:       o.managerName ?? 'CareerStrand',
      role:          o.title,
      avatar:        o.title.charAt(0).toUpperCase(),
      avatarClass:   ['av-blue','av-red','av-mix','av-green'][o.opportunityId % 4],
      category:      (o.category || '').toLowerCase(),
      skills:        [],
      level:         o.requiredLevel,
      type:          o.type ? o.type.charAt(0).toUpperCase() + o.type.slice(1) : '',
      match:         Math.floor(60 + Math.random() * 40),
      deadline:      o.deadline
        ? new Date(o.deadline).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
        : '—',
      isNew:         o.createdAt
        ? (Date.now() - new Date(o.createdAt)) < 7 * 24 * 60 * 60 * 1000
        : false,
      isRecommended: o.requiredLevel === 'Beginner',
      minScore:      o.requiredLevel === 'Beginner' ? 50 : o.requiredLevel === 'Intermediate' ? 70 : 85,
      applied:       false,
      saved:         false,
      tags:          [],
    }));
    await fetchMyApplications();
    render();
  } catch (e) {
    console.error('FETCH ERROR:', e);
    document.getElementById('oppoGrid').innerHTML =
      `<div class="empty-state"><p>Could not load opportunities: ${e.message}</p></div>`;
  }
}

// ── FILTER ──
function getFiltered() {
  let list = state.data;
  if (state.filter === "recommended") list = list.filter(o => o.isRecommended);
  else if (state.filter === "new")     list = list.filter(o => o.isNew);
  else if (state.filter === "closing") list = list.filter(o => o.tags.includes("closing"));
  else if (state.filter === "applied") list = list.filter(o => o.applied);
  if (state.cat !== "all") list = list.filter(o => o.category === state.cat);
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

//show applied or apply now button + build card 
function cardHTML(o) {
  const locked = o.minScore > USER_SCORE;
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
  return ` 
    <article class="oppo-card ${o.isRecommended ? "recommended" : ""} ${locked ? "locked" : ""}" data-id="${o.id}">
      ${locked ? `<div class="lock-overlay"><span style="font-size:22px">&#x1F512;</span><div class="lock-msg">Your ADN score needs to reach ${o.minScore} to unlock this</div><div class="lock-score">Your score: ${USER_SCORE} &middot; Need: ${o.minScore}</div></div>` : ""}
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
        <div class="deadline-text">Closes <strong>${o.deadline}</strong></div>
        <div style="display:flex;gap:8px;align-items:center;">
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
  document.getElementById("filterLabel").textContent = "Showing " + (state.cat === "all" ? "all categories" : state.cat);
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
    tab.querySelector(".tab-count").textContent = c;
  });
  updateDrawerBadge();
}

function toggleSave(e, id) {
  e.stopPropagation();
  const o = state.data.find(x => x.id === id);
  if (o) { o.saved = !o.saved; render(); }
}

//open apply 'window' prefill data + submit button 
function openModal(id) {
  const o = state.data.find(x => x.id === id);
  if (!o) return;
  document.getElementById("modalRole").textContent    = o.role;
  document.getElementById("modalCompany").textContent = o.company;
  document.getElementById("modalScore").textContent   = o.match + "%";
  document.getElementById("modalBar").style.width     = o.match + "%";
  document.getElementById("modalMotivation").value    = "";
  document.getElementById("modalPortfolio").value     = "";
  document.getElementById("applyModal").classList.add("open");
  document.getElementById("modalSubmit").onclick = () => submitApply(id);
}

function closeModal() {
  document.getElementById("applyModal").classList.remove("open");
}

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
  document.querySelectorAll(".cat-chip").forEach(chip => {
    chip.addEventListener("click", () => {
      document.querySelectorAll(".cat-chip").forEach(c => c.classList.remove("active"));
      chip.classList.add("active");
      state.cat = chip.dataset.cat;
      render();
    });
  });
  document.getElementById("searchInput").addEventListener("input", e => { state.search = e.target.value; render(); });
  document.getElementById("sortSelect").addEventListener("change", e => { state.sort = e.target.value; render(); });
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
