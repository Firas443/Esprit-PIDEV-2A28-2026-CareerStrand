
const USER_SCORE = 87;

let state = {
  filter:"recommended", cat:"all", search:"", sort:"match",
  data: [],
};
async function fetchOpportunities() {
  try {
    const res = await fetch('/Careerstrand/FrontOffice/CRUD/get_opportunities.php');

    const text = await res.text();
    console.log("RAW:", text); // DEBUG

    const json = JSON.parse(text);

    if (!json.success) throw new Error(json.message);

    console.log("DATA FROM DB:", json.data);

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
        ? new Date(o.deadline).toLocaleDateString('en-GB',{day:'numeric',month:'short'}) 
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

    render();

  } catch (e) {
    console.error("FETCH ERROR:", e);

    document.getElementById('oppoGrid').innerHTML =
      `<div class="empty-state"><p>Could not load opportunities: ${e.message}</p></div>`;
  }
}

let submissions = [
  { id:"sub-demo", opportunityId:6, motivation:"I have been building brand identities as side projects for two years and Pixel & Co work on editorial design really resonates with my style. My Illustrator skills are solid and I would love to grow in a team that cares about typography.", portfolio:"https://myportfolio.design", status:"pending", appliedAt: new Date(Date.now() - 2*24*60*60*1000).toISOString() },
];

//filter 
function getFiltered() {
  let list = state.data;
  if (state.filter==="recommended") list=list.filter(o=>o.isRecommended);
  else if (state.filter==="new") list=list.filter(o=>o.isNew);
  else if (state.filter==="closing") list=list.filter(o=>o.tags.includes("closing"));
  else if (state.filter==="applied") list=list.filter(o=>o.applied);
  if (state.cat!=="all") list=list.filter(o=>o.category===state.cat);
  if (state.search) {
    const q=state.search.toLowerCase();
    list=list.filter(o=>o.role.toLowerCase().includes(q)||o.company.toLowerCase().includes(q)||o.skills.some(s=>s.toLowerCase().includes(q)));
  }
  if (state.sort==="match") list=[...list].sort((a,b)=>b.match-a.match);
  else if (state.sort==="newest") list=[...list].sort((a,b)=>(b.isNew?1:0)-(a.isNew?1:0));
  else if (state.sort==="deadline") list=[...list].sort((a,b)=>a.deadline.localeCompare(b.deadline));
  return list;
}

//cards
function cardHTML(o) {
  const locked=o.minScore>USER_SCORE;
  const closingSoon=o.tags.includes("closing");
  const badges=[
    o.isRecommended?`<span class="badge badge-recommended">&#9733; Recommended</span>`:"",
    o.isNew?`<span class="badge badge-new">New</span>`:"",
    closingSoon?`<span class="badge badge-closing">Closing soon</span>`:"",
    (o.type==="Remote"||o.type==="Flexible")?`<span class="badge badge-remote">${o.type}</span>`:"",
  ].filter(Boolean).join("");
  const skills=o.skills.map(s=>`<span class="skill-chip">${s}</span>`).join("");
  const applyBtn=o.applied
    ?`<button class="btn-apply applied" disabled>&#10003; Applied</button>`
    :`<button class="btn-apply" onclick="openModal(${o.id})">Apply now &#x2192;</button>`;
  const saveIcon=o.saved
    ?`<svg viewBox="0 0 16 16" fill="currentColor"><path d="M3 2a1 1 0 011-1h8a1 1 0 011 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>`
    :`<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2a1 1 0 011-1h8a1 1 0 011 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>`;
  return `
    <article class="oppo-card ${o.isRecommended?"recommended":""} ${locked?"locked":""}" data-id="${o.id}">
      ${locked?`<div class="lock-overlay"><span style="font-size:22px">&#x1F512;</span><div class="lock-msg">Your ADN score needs to reach ${o.minScore} to unlock this</div><div class="lock-score">Your score: ${USER_SCORE} &middot; Need: ${o.minScore}</div></div>`:""}
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
          <button class="btn-save ${o.saved?"saved":""}" onclick="toggleSave(event,${o.id})" title="Save">${saveIcon}</button>
          ${applyBtn}
        </div>
      </div>
    </article>`;
}

//render 
function render() {
  const list=getFiltered();
  const grid=document.getElementById("oppoGrid");
  document.getElementById("resultCount").textContent=list.length;
  document.getElementById("filterLabel").textContent="Showing "+(state.cat==="all"?"all categories":state.cat);
  grid.innerHTML=list.length===0?`<div class="empty-state"><p>No opportunities match your current filters.</p></div>`:list.map(cardHTML).join("");
  document.querySelectorAll(".filter-tab").forEach(tab=>{
    const f=tab.dataset.filter;
    let c=state.data.length;
    if (f==="recommended") c=state.data.filter(o=>o.isRecommended).length;
    else if (f==="new") c=state.data.filter(o=>o.isNew).length;
    else if (f==="closing") c=state.data.filter(o=>o.tags.includes("closing")).length;
    else if (f==="applied") c=state.data.filter(o=>o.applied).length;
    tab.querySelector(".tab-count").textContent=c;
  });
  updateDrawerBadge();
}

function toggleSave(e,id){e.stopPropagation();const o=state.data.find(x=>x.id===id);if(o){o.saved=!o.saved;render();}}

//show formulaire
function openModal(id){
  const o=state.data.find(x=>x.id===id);if(!o)return;
  document.getElementById("modalRole").textContent=o.role;
  document.getElementById("modalCompany").textContent=o.company;
  document.getElementById("modalScore").textContent=o.match+"%";
  document.getElementById("modalBar").style.width=o.match+"%";
  document.getElementById("modalMotivation").value="";
  document.getElementById("modalPortfolio").value="";
  document.getElementById("applyModal").classList.add("open");
  document.getElementById("modalSubmit").onclick=()=>submitApply(id);
}
function closeModal(){document.getElementById("applyModal").classList.remove("open");}
function submitApply(id){
  const o=state.data.find(x=>x.id===id);if(!o)return;
  const motivation=document.getElementById("modalMotivation").value.trim();
  const portfolio=document.getElementById("modalPortfolio").value.trim();
  o.applied=true;
  submissions.push({id:"sub-"+Date.now(),opportunityId:id,motivation,portfolio,status:"pending",appliedAt:new Date().toISOString()});
  closeModal();render();renderDrawer();
}

function updateDrawerBadge(){
  const badge=document.getElementById("drawerBadge");
  const count=submissions.length;
  badge.textContent=count;
  badge.style.display=count>0?"flex":"none";
}

function statusMeta(s){
  if(s==="accepted") return {label:"Accepted",cls:"status-accepted",dot:"#59d39b"};
  if(s==="rejected") return {label:"Rejected",cls:"status-rejected",dot:"#ff6e45"};
  return {label:"Pending",cls:"status-pending",dot:"#f5bf65"};
}

function formatDate(iso){
  return new Date(iso).toLocaleDateString("en-GB",{day:"numeric",month:"short",year:"numeric"});
}

function renderDrawer(){
  const list=document.getElementById("drawerList");
  if(!submissions.length){
    list.innerHTML=`<div class="drawer-empty"><svg viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="6" y="4" width="28" height="32" rx="4"/><path d="M13 14h14M13 20h10M13 26h6"/></svg><p>No applications yet.<br>Hit Apply on any opportunity.</p></div>`;
    return;
  }
  list.innerHTML=submissions.map(sub=>{
    const opp=state.data.find(o=>o.id===sub.opportunityId);if(!opp)return"";
    const {label,cls,dot}=statusMeta(sub.status);
    return `
      <div class="drawer-card">
        <div class="drawer-card-top">
          <div class="drawer-identity">
            <div class="company-avatar ${opp.avatarClass}" style="width:36px;height:36px;border-radius:12px;font-size:14px;flex-shrink:0;">${opp.avatar}</div>
            <div><div class="drawer-role">${opp.role}</div><div class="drawer-company">${opp.company}</div></div>
          </div>
          <div class="drawer-status ${cls}"><span class="drawer-dot" style="background:${dot}"></span>${label}</div>
        </div>
        <div class="drawer-meta"><span>Applied ${formatDate(sub.appliedAt)}</span><span>${opp.match}% match</span></div>
        ${sub.motivation?`<div class="drawer-section-label">Your pitch</div><div class="drawer-text">${sub.motivation}</div>`:""}
        ${sub.portfolio?`<div class="drawer-section-label">Portfolio</div><a class="drawer-link" href="${sub.portfolio}" target="_blank" rel="noopener">${sub.portfolio}</a>`:""}
        <div class="drawer-skills">${opp.skills.map(s=>`<span class="skill-chip">${s}</span>`).join("")}</div>
        <button class="drawer-cancel-btn" onclick="cancelSubmission('${sub.id}')">Withdraw application</button>
      </div>`;
  }).join("");
}

function cancelSubmission(subId){
  const sub=submissions.find(s=>s.id===subId);if(!sub)return;
  const o=state.data.find(x=>x.id===sub.opportunityId);if(o)o.applied=false;
  submissions=submissions.filter(s=>s.id!==subId);
  render();renderDrawer();
}

function openDrawer(){renderDrawer();document.getElementById("submissionsDrawer").classList.add("open");document.getElementById("drawerBackdrop").classList.add("open");}
function closeDrawer(){document.getElementById("submissionsDrawer").classList.remove("open");document.getElementById("drawerBackdrop").classList.remove("open");}


document.addEventListener("DOMContentLoaded",function(){
  document.querySelectorAll(".filter-tab").forEach(tab=>{
    tab.addEventListener("click",()=>{
      document.querySelectorAll(".filter-tab").forEach(t=>t.classList.remove("active"));
      tab.classList.add("active");state.filter=tab.dataset.filter;render();
    });
  });
  document.querySelectorAll(".cat-chip").forEach(chip=>{
    chip.addEventListener("click",()=>{
      document.querySelectorAll(".cat-chip").forEach(c=>c.classList.remove("active"));
      chip.classList.add("active");state.cat=chip.dataset.cat;render();
    });
  });
  document.getElementById("searchInput").addEventListener("input",e=>{state.search=e.target.value;render();});
  document.getElementById("sortSelect").addEventListener("change",e=>{state.sort=e.target.value;render();});
  document.getElementById("modalClose").addEventListener("click",closeModal);
  document.getElementById("modalCancel").addEventListener("click",closeModal);
  document.getElementById("applyModal").addEventListener("click",e=>{if(e.target===e.currentTarget)closeModal();});
  document.getElementById("drawerBtn").addEventListener("click",openDrawer);
  document.getElementById("drawerClose").addEventListener("click",closeDrawer);
  document.getElementById("drawerBackdrop").addEventListener("click",closeDrawer);
  fetchOpportunities();
  const observer=new IntersectionObserver(entries=>{entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add("show");});},{threshold:0.08});
  document.querySelectorAll(".fade-up").forEach(el=>{
    if(el.getBoundingClientRect().top<window.innerHeight)el.classList.add("show");
    else observer.observe(el);
  });
});