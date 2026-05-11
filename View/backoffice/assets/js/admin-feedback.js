/* ─── DATA ─── */
// ── Events loaded from MySQL via PHP ──────────────────
let events = []; // chargé depuis la BDD via loadEventsFromDB()
let eventsLoaded = true;


  let participations = []; // chargé depuis la DB

let nextEid = 8, nextPid = 9;
const PER_PAGE = 5;
let ePage = 1, pPage = 1;
let eFilter = "all", pFilter = "all";
let editingEventId = null, editingPartId = null;
let pendingDelete = null;

/* ─── HELPERS ─── */
const $ = id => document.getElementById(id);
function getEvent(id){return events.find(e=>e.id===id)}

function statusChip(s){
  const map={Upcoming:"chip-blue",Live:"chip-green",Past:"chip-yellow",Cancelled:"chip-red",Confirmed:"chip-green",Pending:"chip-yellow"};
  return `<span class="chip ${map[s]||'chip-blue'}">${s}</span>`;
}
function typeChip(t){return `<span class="type-badge">${t}</span>`}
function ratingStars(r){if(!r)return '<span style="color:var(--muted-2)">—</span>';return '★'.repeat(r)+'☆'.repeat(5-r)}
function ratingStarsForView(r){if(r===null||r===undefined||r==='')return '<span style="color:var(--muted-2)">No rating yet</span>';const n=Math.max(0,Math.min(5,parseInt(r)||0));return '★'.repeat(n)+'☆'.repeat(5-n)}

function pct(reg,cap){return cap?Math.min(100,Math.round(reg/cap*100)):0}

/* ─── TABS ─── */
function switchTab(name,btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  $('tab-events').style.display = name==='events'?'':'none';
  $('tab-participations').style.display = name==='participations'?'':'none';
  $('tab-sponsors').style.display = name==='sponsors'?'':'none';
  if($('tab-ai-analyzer')) $('tab-ai-analyzer').style.display = name==='ai-analyzer'?'':'none';
  if(name==='participations') loadParticipationsFromDB();
  if(name==='sponsors') loadSponsorsFromDB();
  if(name==='ai-analyzer') loadAiFeedbackAnalyzer();
}

/* ─── FILTERS ─── */
function setTypeFilter(val,btn){
  eFilter=val; ePage=1;
  document.querySelectorAll('#type-filters .filter').forEach(f=>f.classList.remove('is-selected'));
  btn.classList.add('is-selected');
  renderEvents();
}
function setPStatusFilter(val,btn){
  pFilter=val; pPage=1;
  document.querySelectorAll('#status-filters .filter').forEach(f=>f.classList.remove('is-selected'));
  btn.classList.add('is-selected');
  renderParticipations();
}

/* ─── EVENTS RENDER ─── */
function filteredEvents(){
  const q=($('search-events')||{value:''}).value.toLowerCase();
  return events.filter(e=>{
    if(eFilter!=='all'&&e.type!==eFilter)return false;
    if(q&&!e.title.toLowerCase().includes(q)&&!e.type.toLowerCase().includes(q))return false;
    return true;
  });
}

function renderEvents(){
  const fe=filteredEvents();
  const total=fe.length;
  const pages=Math.max(1,Math.ceil(total/PER_PAGE));
  if(ePage>pages)ePage=pages;
  const slice=fe.slice((ePage-1)*PER_PAGE,ePage*PER_PAGE);
  $('events-count').textContent=`${total} event${total!==1?'s':''}`;
  $('events-page').textContent=`Page ${ePage} / ${pages}`;

  const tb=$('events-tbody');
  if(!slice.length){
    tb.innerHTML=`<tr><td colspan="9"><div class="empty"><div class="empty-icon">🗓</div><h4>No events found</h4><p>Try changing filters or create one.</p></div></td></tr>`;
    return;
  }
  tb.innerHTML=slice.map(e=>{
    const p=pct(e.registrations,e.capacity);
    return `<tr>
      <td><span style="font-family:var(--font-mono,monospace);font-size:12px;color:var(--muted-2);background:rgba(111,143,216,.1);padding:3px 7px;border-radius:6px">#${e.id}</span></td>
      <td><strong>${e.title}</strong><br><span style="color:var(--muted-2);font-size:12px">${e.location}</span></td>
      <td>${typeChip(e.type)}</td>
      <td>${e.date} <span style="color:var(--muted-2)">${e.time}</span></td>
      <td>
        <div style="margin-bottom:6px;font-size:13px">${e.registrations} / ${e.capacity}</div>
        <div class="progress-bar" style="width:90px"><div class="progress-fill" style="width:${p}%"></div></div>
      </td>
      <td><span style="color:var(--green);font-weight:700">${p}%</span></td>
      <td>${statusChip(e.status)}</td>
      </td>
      <td>
        <div class="table-actions">
          <button class="btn btn-soft btn-sm" onclick="viewEvent(${e.id})">👁</button>
          <button class="btn btn-soft btn-sm" onclick="editEvent(${e.id})">✏️</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('event',${e.id},'${e.title.replace(/'/g,"\\'")}')">🗑</button>
        </div>
      </td>
    </tr>`;
  }).join('');
  updateStats();
}

function filterEvents(){
  ePage=1;
  const q=($('search-events')||{value:''}).value.trim();
  if(q.length>0){
    fetch('search_event.php?q='+encodeURIComponent(q)+'&type='+encodeURIComponent(eFilter))
      .then(r=>r.json())
      .then(data=>{
       events=data.map(e=>({
    id:e.eventId,title:e.name,description:e.description,
    type:e.type,location:e.location,capacity:e.capacity,
    date:e.date,status:e.status,createdAt:e.createdAt,
    managerId:e.managerId,registrations:parseInt(e.registrations)||0,time:e.time||'',tags:e.tags||'',organiser:e.organiser||'',
    eventMode:e.eventMode,
    formLink:e.formLink
}));
        renderEvents();refreshEventSelect();updateStats();
      });
  }else{
    loadEventsFromDB();
  }
}
function prevPage(t){if(t==='events'&&ePage>1){ePage--;renderEvents()}if(t==='parts'&&pPage>1){pPage--;renderParticipations()}}
function nextPage(t){
  if(t==='events'){const p=Math.ceil(filteredEvents().length/PER_PAGE);if(ePage<p){ePage++;renderEvents()}}
  if(t==='parts'){const p=Math.ceil(filteredParts().length/PER_PAGE);if(pPage<p){pPage++;renderParticipations()}}
}

/* ─── PARTICIPATIONS RENDER ─── */
function filteredParts(){
  const q=($('search-participations')||{value:''}).value.toLowerCase();
  return participations.filter(p=>{
    if(pFilter!=='all'&&p.status!==pFilter)return false;
    const ev=getEvent(p.eventId);
    if(q&&!p.user.toLowerCase().includes(q)&&!(ev&&ev.title.toLowerCase().includes(q)))return false;
    return true;
  });
}

function renderParticipations(){
  const fp=filteredParts();
  const total=fp.length;
  const pages=Math.max(1,Math.ceil(total/PER_PAGE));
  if(pPage>pages)pPage=pages;
  const slice=fp.slice((pPage-1)*PER_PAGE,pPage*PER_PAGE);
  $('parts-count').textContent=`${total} participation${total!==1?'s':''}`;
  $('parts-page').textContent=`Page ${pPage} / ${pages}`;

  const tb=$('parts-tbody');
  if(!slice.length){
    tb.innerHTML=`<tr><td colspan="6"><div class="empty"><div class="empty-icon">👥</div><h4>No participations found</h4><p>Try changing filters.</p></div></td></tr>`;
    return;
  }
  tb.innerHTML=slice.map(p=>{
    const ev=getEvent(p.eventId);
    return `<tr>
      <td><strong>${p.user}</strong><br><span style="color:var(--muted-2);font-size:12px">${p.email}</span></td>
      <td>${ev?`<strong>${ev.title}</strong><br><span style="color:var(--muted-2);font-size:12px">${typeChip(ev.type)}</span>`:'—'}</td>
      <td>${p.registeredAt}</td>
      <td>${statusChip(p.status)}</td>
      <td style="color:var(--yellow)">${ratingStars(p.rating)}</td>
      <td>
        <div class="table-actions">
          <button class="btn btn-soft btn-sm" onclick="viewParticipation(${p.id})">👁</button>
          ${p.status==='Pending'?`<button class="btn btn-success btn-sm" onclick="setParticipationDecision(${p.id},'Confirmed')">Accept</button><button class="btn btn-danger btn-sm" onclick="setParticipationDecision(${p.id},'Cancelled')">Refuse</button>`:''}
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('part',${p.id},'${p.user.replace(/'/g,"\\'")}')">Kick</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function filterParticipations(){pPage=1;renderParticipations()}

function viewParticipation(id){
  const p=participations.find(x=>x.id===id); if(!p)return;
  const ev=getEvent(p.eventId);
  const eventTitle=ev ? ev.title : 'Event #' + p.eventId;
  const feedback=(p.feedback || '').trim();
  $('part-view-title').textContent='Participation Details';
  $('part-view-body').innerHTML=`
    <div class="info-grid">
      <div class="info-item"><div class="lbl">User Name</div><div class="val">${p.user || '—'}</div></div>
      <div class="info-item"><div class="lbl">User Email</div><div class="val">${p.email || '—'}</div></div>
      <div class="info-item"><div class="lbl">Event Title</div><div class="val">${eventTitle}</div></div>
      <div class="info-item"><div class="lbl">Event Date</div><div class="val">${ev ? ev.date : '—'}</div></div>
      <div class="info-item"><div class="lbl">Event Type</div><div class="val">${ev ? ev.type : '—'}</div></div>
      <div class="info-item"><div class="lbl">Location</div><div class="val">${ev ? ev.location : '—'}</div></div>
      <div class="info-item"><div class="lbl">Participation Status</div><div class="val">${statusChip(p.status)}</div></div>
      <div class="info-item"><div class="lbl">Attendance Status</div><div class="val">${statusChip(p.attendanceStatus || p.status)}</div></div>
      <div class="info-item"><div class="lbl">Registered At</div><div class="val">${p.registeredAt || '—'}</div></div>
      <div class="info-item"><div class="lbl">Rating</div><div class="val" style="color:var(--yellow);font-size:18px">${ratingStarsForView(p.rating)}</div></div>
    </div>
    <div style="margin-top:14px" class="info-item">
      <div class="lbl">Feedback</div>
      <div class="val" style="line-height:1.7;white-space:pre-wrap">${feedback || 'No feedback yet.'}</div>
    </div>
  `;
  $('part-view-edit-btn').onclick=()=>{closeModal('participation-view');editParticipation(id)};
  openModal('participation-view');
}

function pendingParts(){
  return participations.filter(p=>p.status==='Pending');
}

function updateRequestDesk(){
  const pending = pendingParts();
  const countEl = $('pending-requests-count');
  const pluralEl = $('pending-requests-plural');
  const copyEl = $('pending-requests-copy');
  const btn = $('accept-all-btn');
  if(!countEl || !pluralEl || !copyEl || !btn) return;
  countEl.textContent = pending.length;
  pluralEl.textContent = pending.length === 1 ? '' : 's';
  copyEl.textContent = pending.length
    ? 'Approve every waiting learner in one move, then the front office will show them as registered.'
    : 'No one is waiting right now.';
  btn.disabled = pending.length === 0;
  btn.textContent = pending.length ? `Accept All (${pending.length})` : 'All Clear';
}

function setParticipationDecision(id, decision){
  const p=participations.find(x=>x.id===id); if(!p)return;
  const formData = new FormData();
  formData.append('attendanceStatus', decision);
  formData.append('status', decision);
  formData.append('rating', p.rating ?? '');
  formData.append('feedback', p.feedback || '');

  fetch('update_participation.php?id=' + id, { method:'POST', body:formData })
    .then(r => r.json())
    .then(data => {
      if(data.success){
        toast(decision === 'Confirmed' ? 'Participation accepted ✓' : 'Participation refused', 'success');
        loadParticipationsFromDB();
        loadEventsFromDB();
      } else {
        toast(data.error || 'Erreur','error');
      }
    })
    .catch(() => toast('Erreur réseau','error'));
}

/* ─── STATS ─── */
function acceptAllRequests(){
  const pending = pendingParts();
  if(!pending.length){
    toast('No pending requests to accept','info');
    updateRequestDesk();
    return;
  }
  const btn = $('accept-all-btn');
  if(btn){ btn.disabled = true; btn.textContent = 'Approving...'; }
  Promise.all(pending.map(p => {
    const formData = new FormData();
    formData.append('attendanceStatus', 'Confirmed');
    formData.append('status', 'Confirmed');
    formData.append('rating', p.rating ?? '');
    formData.append('feedback', p.feedback || '');
    return fetch('update_participation.php?id=' + p.id, { method:'POST', body:formData })
      .then(r => r.json())
      .then(data => ({ id:p.id, ok:!!data.success, error:data.error || '' }))
      .catch(() => ({ id:p.id, ok:false, error:'Network error' }));
  })).then(results => {
    const ok = results.filter(r=>r.ok).length;
    const failed = results.length - ok;
    toast(failed ? `${ok} accepted, ${failed} failed` : `${ok} request${ok!==1?'s':''} accepted`, failed ? 'error' : 'success');
    loadParticipationsFromDB();
    loadEventsFromDB();
  });
}

function updateStats(){
  $('stat-total').textContent=events.length;
  $('stat-upcoming').textContent=events.filter(e=>e.status==='Upcoming').length;
  const totalReg=participations.length;
  $('stat-reg').textContent=totalReg;
  const conf=participations.filter(p=>p.status==='Confirmed').length;
  $('stat-att').textContent=totalReg?Math.round(conf/totalReg*100)+'%':'—';
  $('stat-sponsors').textContent=sponsors.length;
  updateRequestDesk();
}

/* ─── MODALS ─── */
function openModal(name){$('modal-'+name).classList.add('open')}
function closeModal(name){$('modal-'+name).classList.remove('open')}

/* ─── EVENT CRUD ─── */
function openEventCreate(){
  editingEventId=null;
  $('event-form-title').textContent='Create Event';
  ['ef-title','ef-location','ef-capacity','ef-desc','ef-tags','ef-organiser','ef-date','ef-time','ef-manager','ef-sponsor','ef-duration','ef-formlink'].forEach(id=>$(id)&&($(id).value=''));
  $('ef-type').value='Workshop';
  $('ef-status').value='Upcoming';
  ['ef-title','ef-type','ef-date','ef-location','ef-capacity','ef-desc','ef-organiser'].forEach(id=>{const el=$(id);if(el)el.style.borderColor='';});
  openModal('event-form');
}

function editEvent(id){
  const e=getEvent(id); if(!e)return;
  editingEventId=id;
  $('event-form-title').textContent='Edit Event';
  $('ef-title').value=e.title;
  $('ef-type').value=e.type;
  $('ef-date').value=e.date;
  autoCheckDateStatus();
  // garde le status original si pas "Past" automatique
  if($('ef-status').value !== 'Past'){
    $('ef-status').value=e.status;
  }
  $('ef-time').value=e.time;
  $('ef-location').value=e.location;
  $('ef-capacity').value=e.capacity;
  $('ef-desc').value=e.description;
  $('ef-tags').value=e.tags;
  $('ef-organiser').value=e.organiser;
  $('ef-manager').value=e.managerId||'';
  $('ef-sponsor').value=e.sponsorId||'';
  $('ef-duration').value=e.duration||'';
  $('ef-formlink').value = e.formLink || '';
  // Set eventMode radio based on the loaded event
if (e.eventMode === 'In-person') {
    document.querySelector('input[name="event-mode"][value="In-person"]').checked = true;
} else {
    document.querySelector('input[name="event-mode"][value="Online"]').checked = true;
}
  ['ef-title','ef-type','ef-status','ef-date','ef-time','ef-location','ef-capacity','ef-desc','ef-tags','ef-organiser','ef-duration','ef-formlink'].forEach(id=>{const el=$(id);if(el)el.style.borderColor='';});
  openModal('event-form');
}


/* 
   CONTRÔLE DE SAISIE — verifEvents()
  */
function verifEvents() {
  // Réinitialiser toutes les bordures
  ['ef-title','ef-type','ef-status','ef-date','ef-time',
   'ef-location','ef-capacity','ef-desc','ef-tags',
   'ef-organiser','ef-manager','ef-sponsor','ef-duration', 'ef-formlink'
  ].forEach(function(id) {
    var el = $(id);
    if (el) { el.style.borderColor = ''; el.title = ''; }
  });

  var errors = [];

  // ── Titre : obligatoire, lettres uniquement (pas de chiffres seuls), 3–100 caractères ──
  var title = $('ef-title').value.trim();
  if (!title) {
    errors.push('Le titre est obligatoire.');
    $('ef-title').style.borderColor = 'var(--red)';
  } else if (title.length < 3 || title.length > 100) {
    errors.push('Le titre doit contenir entre 3 et 100 caractères.');
    $('ef-title').style.borderColor = 'var(--red)';
  } else if (/^\d+$/.test(title)) {
    errors.push("Le titre ne peut pas être uniquement des chiffres — c'est un texte descriptif.");
    $('ef-title').style.borderColor = 'var(--red)';
  }

  // ── Type : obligatoire, valeur parmi la liste ──
  var type = $('ef-type').value;
  var validTypes = ['Workshop','Hackathon','Career Event','Bootcamp'];
  if (!type || !validTypes.includes(type)) {
    errors.push('Le type est obligatoire (Workshop, Hackathon, Career Event, Bootcamp).');
    $('ef-type').style.borderColor = 'var(--red)';
  }

  // ── Status : obligatoire, valeur parmi la liste ──
  var status = $('ef-status').value;
  var validStatuses = ['Upcoming','Live','Past','Cancelled'];
  if (!status || !validStatuses.includes(status)) {
    errors.push('Le statut est obligatoire (Upcoming, Live, Past, Cancelled).');
    $('ef-status').style.borderColor = 'var(--red)';
  }

 // ── Date de l'événement : obligatoire, date valide ──
var date = $('ef-date').value;
var dateObj = null;
if (!date) {
    errors.push('La date est obligatoire.');
    $('ef-date').style.borderColor = 'var(--red)';
} else {
    dateObj = new Date(date);
    if (isNaN(dateObj.getTime())) {
        errors.push('La date est invalide (format attendu : JJ/MM/AAAA).');
        $('ef-date').style.borderColor = 'var(--red)';
        dateObj = null;
    }
}

// ── La date de l'événement doit être postérieure à aujourd'hui ──
// (createdAt est automatiquement la date du jour)
if (dateObj && dateObj <= new Date()) {
    errors.push("La date de l'événement doit être postérieure à aujourd'hui.");
    $('ef-date').style.borderColor = 'var(--red)';
}

  // ── Heure : obligatoire, format HH:MM ──
  var time = $('ef-time').value;
  if (!time) {
    errors.push("L'heure est obligatoire.");
    $('ef-time').style.borderColor = 'var(--red)';
  } else if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) {
    errors.push("L'heure est invalide (format HH:MM, ex: 09:00, 14:30).");
    $('ef-time').style.borderColor = 'var(--red)';
  }

  //// ── Event Mode & Location logic ──
var eventMode = document.querySelector('input[name="event-mode"]:checked')?.value;
var location = $('ef-location').value.trim();
if (eventMode === 'In-person') {
    if (!location) {
        errors.push('La location est obligatoire pour les événements en présentiel.');
        $('ef-location').style.borderColor = 'var(--red)';
    } else if (location.length < 2 || location.length > 255) {
        errors.push('La location doit contenir entre 2 et 255 caractères.');
        $('ef-location').style.borderColor = 'var(--red)';
    }
}
// If Online, location is optional – no validation


  // ── Capacité : obligatoire, entier positif ──
  var capacity = $('ef-capacity').value;
  if (!capacity) {
    errors.push('La capacité est obligatoire.');
    $('ef-capacity').style.borderColor = 'var(--red)';
  } else if (isNaN(capacity) || parseInt(capacity) <= 0 || !Number.isInteger(parseFloat(capacity))) {
    errors.push('La capacité doit être un entier positif (ex: 50, 100).');
    $('ef-capacity').style.borderColor = 'var(--red)';
  }

  // ── Description : obligatoire, 10–255 caractères ──
  var desc = $('ef-desc').value.trim();
  if (!desc) {
    errors.push('La description est obligatoire.');
    $('ef-desc').style.borderColor = 'var(--red)';
  } else if (desc.length < 10) {
    errors.push('La description doit contenir au moins 10 caractères.');
    $('ef-desc').style.borderColor = 'var(--red)';
  } else if (desc.length > 255) {
    errors.push('La description ne peut pas dépasser 255 caractères.');
    $('ef-desc').style.borderColor = 'var(--red)';
  }

  // ── Tags : obligatoire, format mot,mot,mot (lettres, chiffres, tirets) ──
  var tags = $('ef-tags').value.trim();
  if (!tags) {
    errors.push('Les tags sont obligatoires (ex: design,web,no-code).');
    $('ef-tags').style.borderColor = 'var(--red)';
  } else if (!/^[\wÀ-ž-]+(,[\wÀ-ž-]+)*$/.test(tags)) {
    errors.push('Les tags doivent être des mots séparés par des virgules sans espaces (ex: design,web,no-code).');
    $('ef-tags').style.borderColor = 'var(--red)';
  }

  // ── Organisateur : obligatoire, chaîne (pas que chiffres), 2–100 caractères ──
  var organiser = $('ef-organiser').value.trim();
  if (!organiser) {
    errors.push("L'organisateur est obligatoire.");
    $('ef-organiser').style.borderColor = 'var(--red)';
  } else if (organiser.length < 2 || organiser.length > 100) {
    errors.push("L'organisateur doit contenir entre 2 et 100 caractères.");
    $('ef-organiser').style.borderColor = 'var(--red)';
  } else if (/^\d+$/.test(organiser)) {
    errors.push("L'organisateur ne peut pas être uniquement des chiffres.");
    $('ef-organiser').style.borderColor = 'var(--red)';
  }

  // ── Manager ID : obligatoire, entier positif ──
  var manager = $('ef-manager').value.trim();
  if (!manager) {
    errors.push('Le Manager ID est obligatoire.');
    $('ef-manager').style.borderColor = 'var(--red)';
  } else if (isNaN(manager) || parseInt(manager) <= 0 || !Number.isInteger(parseFloat(manager))) {
    errors.push('Le Manager ID doit être un entier positif (ex: 1, 2, 3).');
    $('ef-manager').style.borderColor = 'var(--red)';
  }

  // ── Sponsor ID : obligatoire, entier positif ──
var sponsorId = $('ef-sponsor') ? $('ef-sponsor').value.trim() : '';
if (!sponsorId) {
    errors.push('Le Sponsor ID est obligatoire.');
    $('ef-sponsor').style.borderColor = 'var(--red)';
} else if (isNaN(sponsorId) || parseInt(sponsorId) <= 0 || !Number.isInteger(parseFloat(sponsorId))) {
    errors.push('Le Sponsor ID doit être un entier positif (ex: 1, 2, 3).');
    $('ef-sponsor').style.borderColor = 'var(--red)';
}


  // ── Duration : obligatoire, entier positif (en minutes) ──
  var duration = $('ef-duration').value;
  if (!duration) {
    errors.push('La durée est obligatoire.');
    $('ef-duration').style.borderColor = 'var(--red)';
  } else if (isNaN(duration) || parseInt(duration) <= 0 || !Number.isInteger(parseFloat(duration))) {
    errors.push('La durée doit être un entier positif en minutes (ex: 60, 120).');
    $('ef-duration').style.borderColor = 'var(--red)';
  }

  // ── Form Link : obligatoire si mode Online ──
  var eventModeRadio = document.querySelector('input[name="event-mode"]:checked');
  var isOnline = !eventModeRadio || eventModeRadio.value === 'Online';
  var formLink = $('ef-formlink').value.trim();

  if (isOnline) {
    if (!formLink) {
      errors.push('Le lien du formulaire est obligatoire pour les événements en ligne.');
      $('ef-formlink').style.borderColor = 'var(--red)';
      valid = false;
    } else if (!/^https?:\/\/.{3,}/.test(formLink)) {
      errors.push('Le lien doit être une URL valide (ex: https://forms.google.com/...).');
      $('ef-formlink').style.borderColor = 'var(--red)';
      valid = false;
    } else if (formLink.length > 255) {
      errors.push('Le lien ne peut pas dépasser 255 caractères.');
      $('ef-formlink').style.borderColor = 'var(--red)';
      valid = false;
    } else {
      $('ef-formlink').style.borderColor = '';
    }
  } else {
    $('ef-formlink').style.borderColor = '';
  }
  // Si In-person : formLink optionnel
  
  // Afficher toutes les erreurs une par une via toast
  if (errors.length > 0) {
    // Afficher le premier message d'erreur
    toast(errors[0], 'error');
    return false;
  }
  return true;
}

/* ─── AUTO STATUS: si date < aujourd'hui → status = "Past" ─── */
function autoCheckDateStatus(){
  var dateVal = $('ef-date').value;
  if(!dateVal) return;
  var eventDate = new Date(dateVal);
  var today = new Date();
  today.setHours(0,0,0,0);
  eventDate.setHours(0,0,0,0);
  if(eventDate < today){
    $('ef-status').value = 'Past';
  } else {
    // seulement si le status était "Past" avant, le remettre à "Upcoming"
    if($('ef-status').value === 'Past'){
      $('ef-status').value = 'Upcoming';
    }
  }
}

function saveEvent(){
  if (!verifEvents()) return;
  const title = $('ef-title').value.trim();
  const dateVal = $('ef-date').value;

  const fd=new FormData();
  fd.append('name',        title);
  fd.append('type',        $('ef-type').value);
  fd.append('status',      $('ef-status').value);
  fd.append('date',        dateVal);
  fd.append('location',    $('ef-location').value||'TBD');
  fd.append('capacity',    $('ef-capacity').value||0);
  fd.append('description', $('ef-desc').value||'');
  fd.append('tags',        $('ef-tags').value||'');
  fd.append('organiser',   $('ef-organiser').value||'');
  fd.append('time',        $('ef-time').value||'');
  fd.append('eventMode',   document.querySelector('input[name="event-mode"]:checked')?.value || 'Online');
  fd.append('managerId',   $('ef-manager').value||'');
  fd.append('sponsorId',   $('ef-sponsor').value||'');
  fd.append('duration',    $('ef-duration').value||'');
  fd.append('formLink', $('ef-formlink').value||'');

  const url = editingEventId
    ? 'update_event.php?id='+editingEventId
    : 'create_event.php';
  if(editingEventId) fd.append('eventId', editingEventId);
  fd.append('status',       'active');
  fd.append('status',       'active');

  fetch(url, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      if(data.success){
        toast(data.message || (editingEventId ? 'Événement mis à jour ✓' : 'Événement créé ✓'), 'success');
        closeModal('event-form');
        loadEventsFromDB();
      } else {
        toast('Erreur : '+(data.error||'Inconnue'), 'error');
      }
    })
    .catch(err => toast('Erreur réseau : '+err.message, 'error'));
}

function viewEvent(id){
  const e=getEvent(id); if(!e)return;
  $('ev-view-title').textContent=e.title;
  const p=pct(e.registrations,e.capacity);
  $('ev-view-body').innerHTML=`
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
    ${typeChip(e.type)} ${statusChip(e.status)}
  </div>
  <p style="color:var(--muted);line-height:1.75;margin-bottom:16px">${e.description||'Aucune description.'}</p>
  <div class="info-grid">
    <div class="info-item"><div class="lbl">Event ID</div><div class="val">#${e.id}</div></div>
    <div class="info-item"><div class="lbl">Type</div><div class="val">${e.type||'—'}</div></div>
    <div class="info-item"><div class="lbl">Status</div><div class="val">${e.status||'—'}</div></div>
    <div class="info-item"><div class="lbl">Date</div><div class="val">${e.date||'—'}</div></div>
    <div class="info-item"><div class="lbl">Time</div><div class="val">${e.time||'—'}</div></div>
    <div class="info-item"><div class="lbl">Location</div><div class="val">${e.location||'—'}</div></div>
    <div class="info-item"><div class="lbl">Capacity</div><div class="val">${e.registrations} / ${e.capacity} (${p}%)</div></div>
    <div class="info-item"><div class="lbl">Organiser</div><div class="val">${e.organiser||'—'}</div></div>
    <div class="info-item"><div class="lbl">Manager ID</div><div class="val">${e.managerId||'—'}</div></div>
    <div class="info-item"><div class="lbl">Event Mode</div><div class="val">${e.eventMode === 'Online' ? '🖥️ Online' : '📍 In-person'}</div></div>
    <div class="info-item"><div class="lbl">Sponsor ID</div><div class="val">${e.sponsorId || '—'}</div></div>
    <div class="info-item"><div class="lbl">Duration (minutes)</div><div class="val">${e.duration || '—'}</div></div>
    <div class="info-item"><div class="lbl">Created At</div><div class="val">${e.createdAt||'—'}</div></div>
    <div class="info-item"><div class="lbl">Form Link</div><div class="val">${e.formLink ? `<a href="${e.formLink}" target="_blank" rel="noopener noreferrer" style="color:var(--blue-2)">🔗 Open</a>` : '—'}</div></div>
  </div>
  <div style="margin-top:14px">
    <div class="progress-bar"><div class="progress-fill" style="width:${p}%"></div></div>
    <div style="margin-top:6px;font-size:12px;color:var(--muted-2)">${p}% filled</div>
  </div>
  ${e.tags?`<div style="margin-top:16px"><div style="font-size:11px;color:var(--muted-2);text-transform:uppercase;letter-spacing:.16em;margin-bottom:8px">Tags</div><div style="display:flex;gap:8px;flex-wrap:wrap">${e.tags.split(',').map(t=>`<span class="type-badge">${t.trim()}</span>`).join('')}</div></div>`:''}
  <div class="info-item" style="margin-top:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <div class="lbl">QR Attendance Check-in</div>
        <div class="val" style="font-size:13px;color:var(--muted);font-weight:500">Generate a secure QR code for participants to confirm attendance.</div>
      </div>
      <button class="btn btn-main btn-sm" onclick="generateEventQr(${e.id})">Generate QR Code</button>
    </div>
    <div id="event-qr-wrap" style="margin-top:14px"></div>
  </div>
`;
  $('ev-view-edit-btn').onclick=()=>{closeModal('event-view');editEvent(id)};
  openModal('event-view');
  loadEventQr(id);
}

/* ─── PARTICIPATION MANAGEMENT ─── */
function loadEventQr(eventId){
  fetch('generate_event_qr.php?eventId=' + eventId)
    .then(r => r.json())
    .then(data => {
      if(data.success && data.token) renderEventQr(data);
    })
    .catch(() => {});
}

function generateEventQr(eventId){
  fetch('generate_event_qr.php?eventId=' + eventId + '&generate=1')
    .then(r => r.json())
    .then(data => {
      if(data.success){
        renderEventQr(data);
        toast('QR code ready for check-in ✓','success');
      }else{
        toast(data.error || 'Unable to generate QR code','error');
      }
    })
    .catch(err => toast('QR error: ' + err.message,'error'));
}

function renderEventQr(data){
  const wrap=$('event-qr-wrap');
  if(!wrap) return;
  wrap.innerHTML=`
    <div style="display:grid;grid-template-columns:180px 1fr;gap:16px;align-items:center">
      <div style="padding:12px;border-radius:18px;background:#fff;display:grid;place-items:center">
        <img src="${data.qrImageUrl}" alt="Event QR Code" style="width:150px;height:150px;display:block">
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.16em;color:var(--muted-2);margin-bottom:8px">Secure Scan URL</div>
        <div style="font-size:12px;line-height:1.6;color:var(--muted);word-break:break-all">${data.scanUrl}</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
          <a class="btn btn-soft btn-sm" href="${data.qrImageUrl}" download="event-${data.eventId}-qr.png" target="_blank" rel="noopener noreferrer">Download QR</a>
          <a class="btn btn-soft btn-sm" href="${data.scanUrl}&back=backoffice" target="_blank" rel="noopener noreferrer">Test Scan</a>
        </div>
      </div>
    </div>
  `;
}

function refreshEventSelect(){
  // Participation creation belongs to the front office.
}

function openPartCreate() {
  toast('Participation creation is handled from the front office.', 'error');
}

function editParticipation(id){
  const p=participations.find(x=>x.id===id); if(!p)return;
  editingPartId=id;
  $('part-form-title').textContent='Edit Participation';
  $('pf-attendance').value=p.attendanceStatus||p.status||'Pending';
  $('pf-status').value=p.status;
  $('pf-rating').value=p.rating||'';
  $('pf-feedback').value=p.feedback||'';
  openModal('participation-form');
}
/* ════════════════════════════════════════════
   1. verifParticipation()
   Champs : attendanceStatus, status, rating, feedback
   ════════════════════════════════════════════ */
function verifParticipation() {

  // Réinitialiser toutes les bordures
  ['pf-attendance','pf-status','pf-rating','pf-feedback'].forEach(function(id) {
    var el = $(id);
    if (el) { el.style.borderColor = ''; el.title = ''; }
  });

  var errors = [];

  // ── Attendance Status : obligatoire, valeur parmi la liste ──
  var attendance = $('pf-attendance').value;
  var validAttendance = ['Confirmed', 'Pending', 'Cancelled'];
  if (!attendance || !validAttendance.includes(attendance)) {
    errors.push('L\'attendance status est obligatoire (Confirmed, Pending, Cancelled).');
    $('pf-attendance').style.borderColor = 'var(--red)';
  }

  // ── Status : obligatoire, valeur parmi la liste ──
  var status = $('pf-status').value;
  var validStatuses = ['Confirmed', 'Pending', 'Cancelled'];
  if (!status || !validStatuses.includes(status)) {
    errors.push('Le statut est obligatoire (Confirmed, Pending, Cancelled).');
    $('pf-status').style.borderColor = 'var(--red)';
  }

  // ── Rating : optionnel MAIS si rempli → entier entre 1 et 5 ──
  var rating = $('pf-rating').value.trim();
  if (rating !== '') {
    if (isNaN(rating) || !Number.isInteger(parseFloat(rating))) {
      errors.push('Le rating doit être un entier (ex: 1, 2, 3, 4, 5).');
      $('pf-rating').style.borderColor = 'var(--red)';
    } else if (parseInt(rating) < 1 || parseInt(rating) > 5) {
      errors.push('Le rating doit être compris entre 1 et 5.');
      $('pf-rating').style.borderColor = 'var(--red)';
    }
  }

  // ── Feedback : optionnel MAIS si rempli → max 500 caractères ──
  var feedback = $('pf-feedback').value.trim();
  if (feedback.length > 500) {
    errors.push('Le feedback ne peut pas dépasser 500 caractères.');
    $('pf-feedback').style.borderColor = 'var(--red)';
  }

  // Afficher le premier message d'erreur via toast
  if (errors.length > 0) {
    toast(errors[0], 'error');
    return false;
  }
  return true;
}


/* ════════════════════════════════════════════
   2. verifSponsor()
   Champs : name, company, email, eventId,
            contribution, amount, status
   ════════════════════════════════════════════ */
function verifSponsor() {
  ['sf-name','sf-company','sf-email','sf-contribution','sf-amount','sf-user'].forEach(function(id) {
    var el = $(id);
    if (el) { el.style.borderColor = ''; el.title = ''; }
  });

  var errors = [];
  var name = $('sf-name').value.trim();
  var company = $('sf-company').value.trim();
  var email = $('sf-email').value.trim();
  var contribution = $('sf-contribution').value.trim();
  var amount = $('sf-amount').value.trim();

  if (!name || name.length < 2 || name.length > 100 || /^\d+$/.test(name)) {
    errors.push('Sponsor name must be 2-100 characters and not only numbers.');
    $('sf-name').style.borderColor = 'var(--red)';
  }
  if (!company || company.length < 2 || company.length > 100 || /^\d+$/.test(company)) {
    errors.push('Company name must be 2-100 characters and not only numbers.');
    $('sf-company').style.borderColor = 'var(--red)';
  }
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.push('Enter a valid sponsor email.');
    $('sf-email').style.borderColor = 'var(--red)';
  }
  if (!contribution || contribution.length < 3 || contribution.length > 255) {
    errors.push('Contribution must be between 3 and 255 characters.');
    $('sf-contribution').style.borderColor = 'var(--red)';
  }
  if (!amount || isNaN(amount) || parseFloat(amount) < 0) {
    errors.push('Amount must be a positive number.');
    $('sf-amount').style.borderColor = 'var(--red)';
  }
  if (errors.length > 0) {
    toast(errors[0], 'error');
    return false;
  }
  return true;
}

function saveParticipation(){
  if(!editingPartId){
    toast('Participation creation is handled from the front office.', 'error');
    return;
  }
  if (!verifParticipation()) return;
  const status  = $('pf-status').value;
  const rating  = $('pf-rating').value ? parseInt($('pf-rating').value) : null;
  const feedback= $('pf-feedback').value;
  const attendance = $('pf-attendance').value;

  const formData = new FormData();
  formData.append('attendanceStatus', attendance);
  formData.append('status',           status);
  formData.append('rating',           rating ?? '');
  formData.append('feedback',         feedback);

  fetch('update_participation.php?id=' + editingPartId, { method:'POST', body:formData })
    .then(r => r.json())
    .then(data => {
      if(data.success){ toast('Participation updated ✓','success'); loadParticipationsFromDB(); }
      else toast(data.error || 'Erreur','error');
      closeModal('participation-form');
    })
    .catch(() => toast('Erreur réseau','error'));
  updateStats();
}


/* ─── SPONSORS FILTER STATE ─── */
let sponsorFilter = 'all';   // 'all' | 'asc' | 'desc'
let sponsorSearch = '';

/* ─── SPONSORS DATA ─── */
let sponsors = []; // chargé depuis la BDD via loadSponsorsFromDB()
let editingSponsorId = null;


/* ─── SPONSORS RENDER ─── */
function getFilteredSponsors(){
  let list=sponsors.filter(s=>{
    const q=sponsorSearch.toLowerCase();
    if(q&&!s.name.toLowerCase().includes(q)&&!s.company.toLowerCase().includes(q)&&!s.email.toLowerCase().includes(q)&&!s.contribution.toLowerCase().includes(q)) return false;
    return true;
  });
  if(sponsorFilter==='desc') list=[...list].sort((a,b)=>b.amount-a.amount);
  else if(sponsorFilter==='asc') list=[...list].sort((a,b)=>a.amount-b.amount);
  return list;
}
function setSponsorFilter(val,btn){
  sponsorFilter=val;
  document.querySelectorAll('#sponsor-status-filters .filter').forEach(f=>f.classList.remove('is-selected'));
  btn.classList.add('is-selected');
  renderSponsors();
}
function filterSponsors(){
  sponsorSearch=$('search-sponsors').value;
  renderSponsors();
}
function renderSponsors(){
  const list=getFilteredSponsors();
  $('sponsors-count').textContent=`${list.length} sponsor${list.length!==1?'s':''}`;
  if(!list.length){
    $('sponsors-tbody').innerHTML=`<tr><td colspan="6"><div class="empty"><div class="empty-icon">💼</div><h4>No sponsors found</h4></div></td></tr>`;
    updateStats();return;
  }
  $('sponsors-tbody').innerHTML=list.map(s=>{
    const linkedEvent = events.find(e=>e.sponsorId && parseInt(e.sponsorId)===s.sponsorId);
    return `<tr>
      <td><strong>${s.name}</strong><br><span style="font-size:11px;color:var(--muted-2)">${s.email}</span></td>
      <td>${s.company}</td>
      <td>${linkedEvent ? linkedEvent.title : '—'}</td>
      <td>${s.contribution}</td>
      <td><strong style="color:var(--yellow)">${Number(s.amount).toLocaleString()} TND</strong></td>
      <td>
        <div class="table-actions">
          <button class="btn btn-soft btn-sm" onclick="editSponsor(${s.sponsorId})">✏️</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('sponsor',${s.sponsorId},'${s.name.replace(/'/g,"\'")}')">🗑</button>
        </div>
      </td>
    </tr>`;
  }).join('');
  updateStats();
}

/* ─── SPONSORS CRUD ─── */
function openSponsorCreate(){
  editingSponsorId=null;
  $('sponsor-form-title').textContent='Add Sponsor';
  ['sf-name','sf-company','sf-email','sf-contribution','sf-amount'].forEach(id=>$(id).value='');
  if($('sf-user')) $('sf-user').value='';
  openModal('sponsor-form');
}
function editSponsor(id){
  const s=sponsors.find(x=>x.sponsorId===id);if(!s)return;
  editingSponsorId=id;
  $('sponsor-form-title').textContent='Edit Sponsor';
  $('sf-name').value=s.name;$('sf-company').value=s.company;$('sf-email').value=s.email;
  $('sf-contribution').value=s.contribution;$('sf-amount').value=s.amount;
  if($('sf-user')) $('sf-user').value=s.userId||'';
  openModal('sponsor-form');
}
function saveSponsor(){
  if (!verifSponsor()) return;
  const fd = new FormData();
  fd.append('name',         $('sf-name').value.trim());
  fd.append('company',      $('sf-company').value.trim());
  fd.append('email',        $('sf-email').value.trim());
  fd.append('contribution', $('sf-contribution').value.trim());
  fd.append('amount',       $('sf-amount').value || 0);
  fd.append('userId',       $('sf-user') ? $('sf-user').value : '');

  const url = editingSponsorId
    ? 'update_sponsor.php?id=' + editingSponsorId
    : 'create_sponsor.php';

  fetch(url, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        toast(data.message || 'Sponsor sauvegardé ✓', 'success');
        editingSponsorId = null;
        closeModal('sponsor-form');
        loadSponsorsFromDB();
      } else {
        toast('Erreur : ' + (data.error || 'Inconnue'), 'error');
      }
    })
    .catch(err => toast('Erreur réseau : ' + err.message, 'error'));
}

/* ─── DELETE ─── */
function confirmDelete(type,id,name){
  $('confirm-title').textContent=type==='part' ? `Kick "${name}"?` : `Delete "${name}"?`;
  const msgs={event:"This will also remove all linked participations, sponsors and forms.",
    part:"This participant will be removed from the event.",
    sponsor:"This sponsor will be permanently removed."};
  $('confirm-body').textContent=msgs[type]||"This action cannot be undone.";
  $('confirm-ok-btn').textContent=type==='part' ? 'Yes, Kick' : 'Yes, Delete';
  $('confirm-ok-btn').onclick=()=>doDelete(type,id);
  openModal('confirm');
}

function doDelete(type,id){
  if(type==='event'){
    fetch('delete_event.php?id='+id)
      .then(r => r.json())
      .then(data => {
        if(data.success){
          toast('Événement supprimé ✓','success');
          loadEventsFromDB();
        } else {
          toast('Erreur : '+(data.error||'Inconnue'),'error');
        }
      })
      .catch(err=>toast('Erreur réseau : '+err.message,'error'));
    closeModal('confirm');
    return;
  }else if(type==='part'){
    fetch('delete_participation.php?id='+id)
      .then(r => r.json())
      .then(data => {
        if(data.success){
          toast('Participant kicked ✓','success');
          loadParticipationsFromDB();
        } else {
          toast('Erreur : '+(data.error||'Inconnue'),'error');
        }
      })
      .catch(err => toast('Erreur réseau : '+err.message,'error'));
    closeModal('confirm');
    return;
  }else if(type==='sponsor'){
    fetch('delete_sponsor.php?id='+id)
      .then(r => r.json())
      .then(data => {
        if(data.success){
          toast('Sponsor supprimé ✓','success');
          loadSponsorsFromDB();
        } else {
          toast('Erreur : '+(data.error||'Inconnue'),'error');
        }
      })
      .catch(err=>toast('Erreur réseau : '+err.message,'error'));
    closeModal('confirm');
    return;
  }
  updateStats();
  closeModal('confirm');
}

/* ─── TOAST ─── */
function toast(msg,type='success'){
  const wrap=$('toast-wrap');
  const el=document.createElement('div');
  el.className=`toast ${type}`;
  el.innerHTML=`<div class="toast-dot"></div>${msg}`;
  wrap.appendChild(el);
  setTimeout(()=>el.remove(),3200);
}

/* ─── WIRE openModal shortcuts ─── */
function openModal(name){
  $('modal-'+name).classList.add('open');
  $('modal-'+name).onclick=e=>{if(e.target===$('modal-'+name))closeModal(name)};
}

/* ─── OVERRIDE the + buttons to use correct open fns ─── */
const eventCreateButton = document.querySelector('[onclick="openModal(\'event-create\')"]');
if (eventCreateButton) eventCreateButton.onclick = openEventCreate;

/* ─── LOAD EVENTS FROM DB ─── */
function aiEmpty(text){
  return `<div class="empty" style="padding:28px 12px"><h4>${text}</h4></div>`;
}

function aiPercent(value){
  return `${Math.round(Number(value || 0))}%`;
}

function loadAiFeedbackAnalyzer(){
  const totalEl = $('ai-total-feedbacks');
  if(totalEl) totalEl.textContent = '...';

  fetch('ai_feedback_analyzer.php')
    .then(r => r.json())
    .then(data => {
      if(data.error){
        toast(data.error, 'error');
        return;
      }
      renderAiFeedbackAnalyzer(data);
    })
    .catch(err => toast('AI analysis error: ' + err.message, 'error'));
}

function renderAiFeedbackAnalyzer(data){
  const stats = data.stats || {};
  $('ai-total-feedbacks').textContent = stats.totalFeedbacks || 0;
  $('ai-positive-percent').textContent = aiPercent(stats.positivePercent);
  $('ai-negative-percent').textContent = aiPercent(stats.negativePercent);
  $('ai-neutral-percent').textContent = aiPercent(stats.neutralPercent);
  $('ai-positive-count').textContent = `${stats.positive || 0} review${stats.positive === 1 ? '' : 's'}`;
  $('ai-negative-count').textContent = `${stats.negative || 0} review${stats.negative === 1 ? '' : 's'}`;
  $('ai-neutral-count').textContent = `${stats.neutral || 0} review${stats.neutral === 1 ? '' : 's'}`;
  if($('ai-positive-bar')) $('ai-positive-bar').style.width = `${Number(stats.positivePercent || 0)}%`;
  if($('ai-negative-bar')) $('ai-negative-bar').style.width = `${Number(stats.negativePercent || 0)}%`;
  if($('ai-neutral-bar')) $('ai-neutral-bar').style.width = `${Number(stats.neutralPercent || 0)}%`;

  const health = Math.max(0, Math.min(100, Math.round((stats.positivePercent || 0) - (stats.negativePercent || 0) + 50)));
  if($('ai-health-score')) $('ai-health-score').textContent = health;
  if($('ai-priority-title') && $('ai-priority-copy')){
    if((stats.negative || 0) > 0){
      $('ai-priority-title').textContent = 'Fix negative patterns first';
      $('ai-priority-copy').textContent = 'Start with the most repeated complaint, then compare the next event feedback to see if the issue decreased.';
    }else if((stats.positive || 0) > (stats.neutral || 0)){
      $('ai-priority-title').textContent = 'Strong feedback health';
      $('ai-priority-copy').textContent = 'Use the most liked events as examples for future event planning and repeat what worked well.';
    }else{
      $('ai-priority-title').textContent = 'Collect more detailed feedback';
      $('ai-priority-copy').textContent = 'Ask users for more specific comments after each event so the analyzer can detect clearer patterns.';
    }
  }

  const complaints = data.topComplaints || [];
  $('ai-complaints-list').innerHTML = complaints.length
    ? complaints.map(item => `
        <div class="ai-list-item">
          <div>
            <strong>${item.topic}</strong>
            <span>${item.examples && item.examples.length ? item.examples.join(' | ') : 'Detected in negative feedback'}</span>
          </div>
          <b>${item.count}</b>
        </div>
      `).join('')
    : aiEmpty('No negative complaint patterns found.');

  const liked = data.mostLikedEvents || [];
  $('ai-liked-events-list').innerHTML = liked.length
    ? liked.map(item => `
        <div class="ai-list-item">
          <div>
            <strong>${item.title}</strong>
            <span>Average rating: ${Number(item.averageRating || 0).toFixed(1)} / 5</span>
          </div>
          <b>${item.positiveReviews} positive</b>
        </div>
      `).join('')
    : aiEmpty('No liked events yet.');

  const suggestions = data.suggestions || [];
  $('ai-suggestions-list').innerHTML = suggestions.length
    ? suggestions.map(text => `<div class="ai-suggestion">${String(text ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}</div>`).join('')
    : aiEmpty('No suggestions yet. More feedback will make the analyzer smarter.');

  loadParticipationsFromDB();
}

function loadEventsFromDB(){
  fetch('search_event.php')
    .then(r=>r.json())
    .then(data=>{
      events=data.map(e=>({
        id:e.eventId,title:e.name,description:e.description,
        type:e.type,location:e.location,capacity:e.capacity,
        date:e.date,status:e.status,createdAt:e.createdAt,
        managerId:e.managerId, sponsorId:e.sponsorId, duration:e.duration,
        eventMode:e.eventMode,
        formLink:e.formLink,
       registrations:parseInt(e.registrations)||0, time:e.time||'', tags:e.tags||'', organiser:e.organiser||''
      }));
      renderEvents();refreshEventSelect();updateStats();
    })
    .catch(err=>console.error('loadEventsFromDB:',err));
}

/* ─── LOAD SPONSORS FROM DB ─── */
function loadSponsorsFromDB(){
  fetch('search_sponsor.php')
    .then(r => r.json())
    .then(data => {
      sponsors = data;
      renderSponsors();
      updateStats();
    })
    .catch(err => console.error('loadSponsorsFromDB:', err));
}

/* ─── LOAD PARTICIPATIONS FROM DB ─── */
function loadParticipationsFromDB(){
  fetch('search_participation.php')
    .then(r => r.json())
    .then(data => {
      participations = data.map(p => ({
        id:          p.participationId,
        user:        p.userName  || 'User #' + (p.userId||'?'),
        email:       p.userEmail || '',
        eventId:     p.eventId,
        registeredAt:p.registrationDate,
        attendanceStatus:p.attendanceStatus,
        status:      p.attendanceStatus || p.status,
        rating:      p.rating ? parseInt(p.rating) : null,
        feedback:    p.feedbackText || '',
      }));
      renderParticipations();
      updateStats();
    })
    .catch(err => { console.error('loadParticipationsFromDB:', err); renderParticipations(); });
}

/* ─── INIT ─── */
loadEventsFromDB();
loadSponsorsFromDB();
loadParticipationsFromDB();
updateStats();
