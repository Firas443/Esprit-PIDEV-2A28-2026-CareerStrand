/* ─── DATA ─── */
// ── Events loaded from MySQL via PHP ──────────────────
let events = []; // chargé depuis la BDD via loadEventsFromDB()
let eventsLoaded = true;

let participations = [
  {id:1,user:"Amira Bensalem",email:"amira@example.com",eventId:1,registeredAt:"2025-08-01",status:"Confirmed",rating:5,feedback:"Amazing session!"},
  {id:2,user:"Yassine Triki",email:"yassine@example.com",eventId:2,registeredAt:"2025-08-03",status:"Pending",rating:null,feedback:""},
  {id:3,user:"Fatma Souissi",email:"fatma@example.com",eventId:1,registeredAt:"2025-08-02",status:"Confirmed",rating:4,feedback:"Very useful."},
  {id:4,user:"Omar Mejri",email:"omar@example.com",eventId:3,registeredAt:"2025-07-28",status:"Confirmed",rating:3,feedback:"Good but short."},
  {id:5,user:"Sarra Ben Ali",email:"sarra@example.com",eventId:4,registeredAt:"2025-08-10",status:"Cancelled",rating:null,feedback:""},
  {id:6,user:"Rim Khalfallah",email:"rim@example.com",eventId:2,registeredAt:"2025-08-05",status:"Confirmed",rating:5,feedback:"Best event!"},
  {id:7,user:"Hamza Agrebi",email:"hamza@example.com",eventId:5,registeredAt:"2025-08-12",status:"Pending",rating:null,feedback:""},
  {id:8,user:"Nour Chaabane",email:"nour@example.com",eventId:6,registeredAt:"2025-07-10",status:"Confirmed",rating:4,feedback:"Great energy."},
];

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

function pct(reg,cap){return cap?Math.min(100,Math.round(reg/cap*100)):0}

/* ─── TABS ─── */
function switchTab(name,btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  $('tab-events').style.display = name==='events'?'':'none';
  $('tab-participations').style.display = name==='participations'?'':'none';
  $('tab-sponsors').style.display = name==='sponsors'?'':'none';
  $('tab-forms').style.display = name==='forms'?'':'none';
  if(name==='sponsors') loadSponsorsFromDB();
  if(name==='forms') loadEventFormsFromDB();
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
    tb.innerHTML=`<tr><td colspan="8"><div class="empty"><div class="empty-icon">🗓</div><h4>No events found</h4><p>Try changing filters or create one.</p></div></td></tr>`;
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
    managerId:e.managerId,registrations:0,time:e.time||'',tags:e.tags||'',organiser:e.organiser||'',
    eventMode:e.eventMode
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
          <button class="btn btn-soft btn-sm" onclick="editParticipation(${p.id})">✏️</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('part',${p.id},'${p.user.replace(/'/g,"\\'")}')">🗑</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function filterParticipations(){pPage=1;renderParticipations()}

/* ─── STATS ─── */
function updateStats(){
  $('stat-total').textContent=events.length;
  $('stat-upcoming').textContent=events.filter(e=>e.status==='Upcoming').length;
  const totalReg=participations.length;
  $('stat-reg').textContent=totalReg;
  const conf=participations.filter(p=>p.status==='Confirmed').length;
  $('stat-att').textContent=totalReg?Math.round(conf/totalReg*100)+'%':'—';
  $('stat-sponsors').textContent=sponsors.length;
}

/* ─── MODALS ─── */
function openModal(name){$('modal-'+name).classList.add('open')}
function closeModal(name){$('modal-'+name).classList.remove('open')}

/* ─── EVENT CRUD ─── */
function openEventCreate(){
  editingEventId=null;
  $('event-form-title').textContent='Create Event';
  ['ef-title','ef-location','ef-capacity','ef-desc','ef-tags','ef-organiser','ef-date','ef-time','ef-manager','ef-sponsor','ef-duration'].forEach(id=>$(id)&&($(id).value=''));
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
  $('ef-status').value=e.status;
  $('ef-date').value=e.date;
  $('ef-time').value=e.time;
  $('ef-location').value=e.location;
  $('ef-capacity').value=e.capacity;
  $('ef-desc').value=e.description;
  $('ef-tags').value=e.tags;
  $('ef-organiser').value=e.organiser;
  $('ef-manager').value=e.managerId||'';
  $('ef-sponsor').value=e.sponsorId||'';
  $('ef-duration').value=e.duration||'';
  // Set eventMode radio based on the loaded event
if (e.eventMode === 'In-person') {
    document.querySelector('input[name="event-mode"][value="In-person"]').checked = true;
} else {
    document.querySelector('input[name="event-mode"][value="Online"]').checked = true;
}
  ['ef-title','ef-type','ef-date','ef-location','ef-capacity','ef-desc','ef-organiser'].forEach(id=>{const el=$(id);if(el)el.style.borderColor='';});
  openModal('event-form');
}


/* 
   CONTRÔLE DE SAISIE — verifEvents()
  */
function verifEvents() {
  // Réinitialiser toutes les bordures
  ['ef-title','ef-type','ef-status','ef-date','ef-time',
   'ef-location','ef-capacity','ef-desc','ef-tags',
   'ef-organiser','ef-manager','ef-sponsor','ef-duration'
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

  
  // Afficher toutes les erreurs une par une via toast
  if (errors.length > 0) {
    // Afficher le premier message d'erreur
    toast(errors[0], 'error');
    return false;
  }
  return true;
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

  const url = editingEventId
    ? 'update_event.php?id='+editingEventId
    : 'create_event.php';
  if(editingEventId) fd.append('eventId', editingEventId);

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
  </div>
  <div style="margin-top:14px">
    <div class="progress-bar"><div class="progress-fill" style="width:${p}%"></div></div>
    <div style="margin-top:6px;font-size:12px;color:var(--muted-2)">${p}% filled</div>
  </div>
  ${e.tags?`<div style="margin-top:16px"><div style="font-size:11px;color:var(--muted-2);text-transform:uppercase;letter-spacing:.16em;margin-bottom:8px">Tags</div><div style="display:flex;gap:8px;flex-wrap:wrap">${e.tags.split(',').map(t=>`<span class="type-badge">${t.trim()}</span>`).join('')}</div></div>`:''}
`;
  $('ev-view-edit-btn').onclick=()=>{closeModal('event-view');editEvent(id)};
  openModal('event-view');
}

/* ─── PARTICIPATION CRUD ─── */
  function refreshEventSelect(){
  // pf-event, sf-event, ff-event sont des inputs number (eventId)
    // pas besoin de les remplir
  }

function openPartCreate(){
  editingPartId=null;
  $('part-form-title').textContent='Register Participation';
  $('pf-user').value='';$('pf-email').value='';
  $('pf-regdate').value='';$('pf-attendance').value='Confirmed';
  $('pf-status').value='Confirmed';$('pf-rating').value='';$('pf-feedback').value='';
  $('pf-event').value='';
  openModal('participation-form');
}

function editParticipation(id){
  const p=participations.find(x=>x.id===id); if(!p)return;
  editingPartId=id;
  $('part-form-title').textContent='Edit Participation';
  $('pf-user').value=p.user;$('pf-email').value=p.email;
  $('pf-event').value=p.eventId;
  $('pf-regdate').value=p.registeredAt||'';
  $('pf-attendance').value=p.attendanceStatus||p.status||'Pending';
  $('pf-status').value=p.status;
  $('pf-rating').value=p.rating||'';
  $('pf-feedback').value=p.feedback||'';
  openModal('participation-form');
}
/* ════════════════════════════════════════════
   1. verifParticipation()
   Champs : userId, eventId, registrationDate,
            attendanceStatus, status, rating, feedback
   ════════════════════════════════════════════ */
function verifParticipation() {

  // Réinitialiser toutes les bordures
  ['pf-user','pf-email','pf-event','pf-regdate',
   'pf-attendance','pf-status','pf-rating','pf-feedback'
  ].forEach(function(id) {
    var el = $(id);
    if (el) { el.style.borderColor = ''; el.title = ''; }
  });

  var errors = [];

  // ── Nom d'utilisateur : obligatoire, pas que chiffres, 2–100 caractères ──
  var user = $('pf-user').value.trim();
  if (!user) {
    errors.push('Le nom d\'utilisateur est obligatoire.');
    $('pf-user').style.borderColor = 'var(--red)';
  } else if (user.length < 2 || user.length > 100) {
    errors.push('Le nom doit contenir entre 2 et 100 caractères.');
    $('pf-user').style.borderColor = 'var(--red)';
  } else if (/^\d+$/.test(user)) {
    errors.push('Le nom d\'utilisateur ne peut pas être uniquement des chiffres.');
    $('pf-user').style.borderColor = 'var(--red)';
  }

  // ── Email : obligatoire, format valide ──
  var email = $('pf-email').value.trim();
  if (!email) {
    errors.push('L\'email est obligatoire.');
    $('pf-email').style.borderColor = 'var(--red)';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.push('L\'email est invalide (ex: user@example.com).');
    $('pf-email').style.borderColor = 'var(--red)';
  }

  // ── Event ID : obligatoire, entier positif ──
  var eventId = $('pf-event').value.trim();
  if (!eventId) {
    errors.push('L\'Event ID est obligatoire.');
    $('pf-event').style.borderColor = 'var(--red)';
  } else if (isNaN(eventId) || parseInt(eventId) <= 0 || !Number.isInteger(parseFloat(eventId))) {
    errors.push('L\'Event ID doit être un entier positif (ex: 1, 2, 3).');
    $('pf-event').style.borderColor = 'var(--red)';
  }

  // ── Date d'inscription : obligatoire, date valide ──
  var regDate = $('pf-regdate').value;
  var regDateObj = null;
  if (!regDate) {
    errors.push('La date d\'inscription est obligatoire.');
    $('pf-regdate').style.borderColor = 'var(--red)';
  } else {
    regDateObj = new Date(regDate);
    if (isNaN(regDateObj.getTime())) {
      errors.push('La date d\'inscription est invalide (format attendu : JJ/MM/AAAA).');
      $('pf-regdate').style.borderColor = 'var(--red)';
      regDateObj = null;
    }
  }

  // ── LOGIQUE : la date d'inscription ne peut pas être dans le futur ──
  if (regDateObj && regDateObj > new Date()) {
    errors.push('La date d\'inscription ne peut pas être dans le futur.');
    $('pf-regdate').style.borderColor = 'var(--red)';
  }

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

  // Réinitialiser toutes les bordures
  ['sf-name','sf-company','sf-email',
   'sf-contribution','sf-amount','sf-user'
  ].forEach(function(id) {
    var el = $(id);
    if (el) { el.style.borderColor = ''; el.title = ''; }
  });

  var errors = [];

  // ── Nom du sponsor : obligatoire, pas que chiffres, 2–100 caractères ──
  var name = $('sf-name').value.trim();
  if (!name) {
    errors.push('Le nom du sponsor est obligatoire.');
    $('sf-name').style.borderColor = 'var(--red)';
  } else if (name.length < 2 || name.length > 100) {
    errors.push('Le nom doit contenir entre 2 et 100 caractères.');
    $('sf-name').style.borderColor = 'var(--red)';
  } else if (/^\d+$/.test(name)) {
    errors.push('Le nom du sponsor ne peut pas être uniquement des chiffres.');
    $('sf-name').style.borderColor = 'var(--red)';
  }

  // ── Company : obligatoire, pas que chiffres, 2–100 caractères ──
  var company = $('sf-company').value.trim();
  if (!company) {
    errors.push('Le nom de la company est obligatoire.');
    $('sf-company').style.borderColor = 'var(--red)';
  } else if (company.length < 2 || company.length > 100) {
    errors.push('Le nom de la company doit contenir entre 2 et 100 caractères.');
    $('sf-company').style.borderColor = 'var(--red)';
  } else if (/^\d+$/.test(company)) {
    errors.push('Le nom de la company ne peut pas être uniquement des chiffres.');
    $('sf-company').style.borderColor = 'var(--red)';
  }

  // ── Email : obligatoire, format valide ──
  var email = $('sf-email').value.trim();
  if (!email) {
    errors.push('L\'email du sponsor est obligatoire.');
    $('sf-email').style.borderColor = 'var(--red)';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.push('L\'email est invalide (ex: sponsor@company.com).');
    $('sf-email').style.borderColor = 'var(--red)';
  }


  // ── Contribution : obligatoire, 3–255 caractères ──
  var contribution = $('sf-contribution').value.trim();
  if (!contribution) {
    errors.push('La contribution est obligatoire.');
    $('sf-contribution').style.borderColor = 'var(--red)';
  } else if (contribution.length < 3 || contribution.length > 255) {
    errors.push('La contribution doit contenir entre 3 et 255 caractères.');
    $('sf-contribution').style.borderColor = 'var(--red)';
  }

  // ── Amount : obligatoire, nombre positif (décimal autorisé) ──
  var amount = $('sf-amount').value.trim();
  if (!amount) {
    errors.push('Le montant est obligatoire.');
    $('sf-amount').style.borderColor = 'var(--red)';
  } else if (isNaN(amount) || parseFloat(amount) < 0) {
    errors.push('Le montant doit être un nombre positif (ex: 1000, 2500.50).');
    $('sf-amount').style.borderColor = 'var(--red)';
  }

  var userId = $('sf-user').value.trim();
if (!userId) {
    errors.push('Le User ID est obligatoire.');
    $('sf-user').style.borderColor = 'var(--red)';
} else if (isNaN(userId) || parseInt(userId) <= 0 || !Number.isInteger(parseFloat(userId))) {
    errors.push('Le User ID doit être un entier positif (ex: 1, 2, 3).');
    $('sf-user').style.borderColor = 'var(--red)';
}

  // Afficher le premier message d'erreur via toast
  if (errors.length > 0) {
    toast(errors[0], 'error');
    return false;
  }
  return true;
}


/* ════════════════════════════════════════════
   3. verifEventForm()
   Champs : title, eventId, description,
            formLink, status
   ════════════════════════════════════════════ */
function verifEventForm() {

  // Réinitialiser toutes les bordures
  ['ff-title','ff-event','ff-desc','ff-link','ff-status'
  ].forEach(function(id) {
    var el = $(id);
    if (el) { el.style.borderColor = ''; el.title = ''; }
  });

  var errors = [];

  // ── Titre du formulaire : obligatoire, pas que chiffres, 3–100 caractères ──
  var title = $('ff-title').value.trim();
  if (!title) {
    errors.push('Le titre du formulaire est obligatoire.');
    $('ff-title').style.borderColor = 'var(--red)';
  } else if (title.length < 3 || title.length > 100) {
    errors.push('Le titre doit contenir entre 3 et 100 caractères.');
    $('ff-title').style.borderColor = 'var(--red)';
  } else if (/^\d+$/.test(title)) {
    errors.push('Le titre ne peut pas être uniquement des chiffres.');
    $('ff-title').style.borderColor = 'var(--red)';
  }

  // ── Event ID : obligatoire, entier positif ──
  var eventId = $('ff-event').value.trim();
  if (!eventId) {
    errors.push('L\'Event ID est obligatoire.');
    $('ff-event').style.borderColor = 'var(--red)';
  } else if (isNaN(eventId) || parseInt(eventId) <= 0 || !Number.isInteger(parseFloat(eventId))) {
    errors.push('L\'Event ID doit être un entier positif (ex: 1, 2, 3).');
    $('ff-event').style.borderColor = 'var(--red)';
  }

  // ── Description : obligatoire, 5–255 caractères ──
  var desc = $('ff-desc').value.trim();
  if (!desc) {
    errors.push('La description est obligatoire.');
    $('ff-desc').style.borderColor = 'var(--red)';
  } else if (desc.length < 5) {
    errors.push('La description doit contenir au moins 5 caractères.');
    $('ff-desc').style.borderColor = 'var(--red)';
  } else if (desc.length > 255) {
    errors.push('La description ne peut pas dépasser 255 caractères.');
    $('ff-desc').style.borderColor = 'var(--red)';
  }

  // ── Form Link : obligatoire, URL valide (doit commencer par http:// ou https://) ──
 // ── Conditional validation based on event mode ──
var eventId = parseInt($('ff-event').value);
var eventObj = events.find(e => e.id === eventId);
var isOnline = eventObj && eventObj.eventMode === 'Online';

if (isOnline) {
    var link = $('ff-link').value.trim();
    if (!link) {
        errors.push('Le lien du formulaire est obligatoire pour les événements en ligne.');
        $('ff-link').style.borderColor = 'var(--red)';
    } else if (!/^https?:\/\/.{3,}/.test(link)) {
        errors.push('Le lien doit être une URL valide commençant par http:// ou https://.');
        $('ff-link').style.borderColor = 'var(--red)';
    } else if (link.length > 255) {
        errors.push('Le lien ne peut pas dépasser 255 caractères.');
        $('ff-link').style.borderColor = 'var(--red)';
    }
}
// If event is In-person, link is optional → no validation

  // ── Status : obligatoire, valeur parmi la liste ──
  var status = $('ff-status').value;
  var validStatuses = ['open', 'closed', 'draft'];
  if (!status || !validStatuses.includes(status)) {
    errors.push('Le statut est obligatoire (open, closed, draft).');
    $('ff-status').style.borderColor = 'var(--red)';
  }

  // Afficher le premier message d'erreur via toast
  if (errors.length > 0) {
    toast(errors[0], 'error');
    return false;
  }
  return true;
}


function saveParticipation(){
  if (!verifParticipation()) return;
  const user=$('pf-user').value.trim();
  if(!user){toast('User name is required','error');return}
  const eventId=parseInt($('pf-event').value);
  const today=new Date().toISOString().slice(0,10);
  if(editingPartId){
    const p=participations.find(x=>x.id===editingPartId);
    Object.assign(p,{
      user,email:$('pf-email').value,eventId,
      registeredAt:$('pf-regdate').value||p.registeredAt,
      attendanceStatus:$('pf-attendance').value,
      status:$('pf-status').value,
      rating:$('pf-rating').value?parseInt($('pf-rating').value):null,
      feedback:$('pf-feedback').value
    });
    toast('Participation updated ✓','success');
  }else{
    participations.push({
      id:nextPid++,user,email:$('pf-email').value,eventId,
      registeredAt:$('pf-regdate').value||today,
      attendanceStatus:$('pf-attendance').value,
      status:$('pf-status').value,
      rating:$('pf-rating').value?parseInt($('pf-rating').value):null,
      feedback:$('pf-feedback').value
    });
    toast('Participation registered ✓','success');
  }
  closeModal('participation-form');
  renderParticipations();
  updateStats();
}


/* ─── SPONSORS FILTER STATE ─── */
let sponsorFilter = 'all';   // 'all' | 'asc' | 'desc'
let sponsorSearch = '';
let formFilter = 'all';
let formSearch = '';

/* ─── SPONSORS DATA ─── */
let sponsors = []; // chargé depuis la BDD via loadSponsorsFromDB()
let editingSponsorId = null;

/* ─── EVENT FORMS DATA ─── */
let eventForms = [];
let nextFid = 4;
let editingFormId = null;

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
    const ev=getEvent(s.eventId);
    return `<tr>
      <td><strong>${s.name}</strong><br><span style="font-size:11px;color:var(--muted-2)">${s.email}</span></td>
      <td>${s.company}</td>
      <td>${ev?ev.title:'—'}</td>
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

/* ─── EVENT FORMS RENDER ─── */
function getFilteredForms(){
  return eventForms.filter(f=>{
    if(formFilter!=='all'&&f.status!==formFilter) return false;
    const q=formSearch.toLowerCase();
    if(q&&!f.title.toLowerCase().includes(q)&&!f.description.toLowerCase().includes(q)) return false;
    return true;
  });
}
function setFormFilter(val,btn){
  formFilter=val;
  document.querySelectorAll('#form-status-filters .filter').forEach(f=>f.classList.remove('is-selected'));
  btn.classList.add('is-selected');
  renderEventForms();
}
function filterForms(){
  formSearch=$('search-forms').value;
  renderEventForms();
}

function loadEventFormsFromDB(){
  fetch('search_eventform.php')
    .then(r => r.json())
    .then(data => {
      eventForms = data;
      renderEventForms();
    })
    .catch(err => console.error('loadEventFormsFromDB:', err));
}

function renderEventForms(){
  const list=getFilteredForms();
  $('forms-count').textContent=`${list.length} form${list.length!==1?'s':''}`;
  if(!list.length){
    $('forms-tbody').innerHTML=`<tr><td colspan="6"><div class="empty"><div class="empty-icon">📋</div><h4>No forms found</h4></div></td></tr>`;
    return;
  }
  $('forms-tbody').innerHTML=list.map(f=>{
    const ev=getEvent(f.eventId);
    const fStatusChip=f.status==='open'?'<span class="chip chip-green">Open</span>':f.status==='closed'?'<span class="chip chip-red" style="background:rgba(255,110,69,.12);color:var(--red)">Closed</span>':'<span class="chip chip-yellow" style="background:rgba(245,191,101,.13);color:var(--yellow)">Draft</span>';
    return `<tr>
      <td><strong>${f.title}</strong></td>
      <td>${ev?ev.title:'—'}</td>
      <td style="max-width:180px;color:var(--muted);font-size:12px">${f.description}</td>
      <td><a href="${f.formLink}" target="_blank" style="color:var(--blue-2);font-size:12px">🔗 Open</a></td>
      <td>${fStatusChip}</td>
      <td>
        <div class="table-actions">
          <button class="btn btn-soft btn-sm" onclick="editEventForm(${f.formId})">✏️</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('form',${f.formId},'${f.title.replace(/'/g,"\'")}')">🗑</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

/* ─── EVENT FORMS CRUD ─── */
function openFormCreate(){
  editingFormId=null;
  $('eventform-form-title').textContent='Add Event Form';
  ['ff-title','ff-desc','ff-link'].forEach(id=>$(id).value='');
  $('ff-status').value='open';
  openModal('eventform-form');
}

function editEventForm(id){
  const f=eventForms.find(x=>x.formId===id); if(!f)return;
  editingFormId=id;
  $('eventform-form-title').textContent='Edit Form';
  $('ff-title').value=f.title;
  $('ff-desc').value=f.description;
  $('ff-link').value=f.formLink;
  $('ff-status').value=f.status;
  $('ff-event').value=f.eventId;
  openModal('eventform-form');
}

function saveEventForm(){
  if (!verifEventForm()) return;
  const title = $('ff-title').value.trim();
  const link = $('ff-link').value.trim();
  if(!title){toast('Title is required','error');return}
  
  const fd = new FormData();
  fd.append('eventId', parseInt($('ff-event').value));
  fd.append('title', title);
  fd.append('description', $('ff-desc').value);
  fd.append('formLink', link);
  fd.append('status', $('ff-status').value);
  
  const url = editingFormId
    ? 'update_eventform.php?id=' + editingFormId
    : 'create_eventform.php';
  if(editingFormId) fd.append('formId', editingFormId);
  
  fetch(url, {method:'POST', body:fd})
    .then(r => r.json())
    .then(data => {
      if(data.success){
        toast(data.message, 'success');
        closeModal('eventform-form');
        loadEventFormsFromDB();
      } else {
        toast('Erreur : '+(data.error||'Inconnue'), 'error');
      }
    })
    .catch(err => toast('Erreur réseau : '+err.message, 'error'));
}

/* ─── DELETE ─── */
function confirmDelete(type,id,name){
  $('confirm-title').textContent=`Delete "${name}"?`;
  const msgs={event:"This will also remove all linked participations, sponsors and forms.",
    part:"This registration will be permanently removed.",
    sponsor:"This sponsor will be permanently removed.",
    form:"This form will be permanently removed."};
  $('confirm-body').textContent=msgs[type]||"This action cannot be undone.";
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
    participations=participations.filter(p=>p.id!==id);
    renderParticipations();
    toast('Participation removed','success');
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
  }else if (type === 'form') {
  fetch('delete_eventform.php?id=' + id)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        toast('Form deleted', 'success');
        loadEventFormsFromDB();   // refreshes the list from database
      } else {
        toast('Erreur : ' + (data.error || 'Inconnue'), 'error');
      }
    })
    .catch(err => toast('Erreur réseau : ' + err.message, 'error'));
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
document.querySelector('[onclick="openModal(\'event-create\')"]').onclick=openEventCreate;
document.querySelector('[onclick="openModal(\'participation-create\')"]').onclick=openPartCreate;

/* ─── LOAD EVENTS FROM DB ─── */
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
       registrations:0, time:e.time||'', tags:e.tags||'', organiser:e.organiser||''
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

/* ─── INIT ─── */
loadEventsFromDB();
loadSponsorsFromDB();
renderParticipations();
updateStats();