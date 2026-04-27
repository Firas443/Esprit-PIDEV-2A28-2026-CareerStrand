<?php
require_once __DIR__ . '/../../Controller/EventsController.php';

$eventC  = new EventsController();
$dbEvents = $eventC->listerEvents();

$eventsJson = json_encode(array_map(function($e) {
    return [
        'id'          => $e->getEventId(),
        'title'       => $e->getName(),
        'description' => $e->getDescription(),
        'type'        => $e->getType(),
        'location'    => $e->getLocation(),
        'capacity'    => $e->getCapacity(),
        'date'        => $e->getDate(),
        'status'      => $e->getStatus(),
        'createdAt'   => $e->getCreatedAt(),
        'managerId'   => $e->getManagerId(),
        'sponsorId'   => $e->getSponsorId(),
        'duration'    => $e->getDuration(),
        'registrations' => 0,
        'time'        => $e->getTime(),
        'tags'        => explode(',', $e->getTags()),
        'organiser'   => $e->getOrganiser(),
        'trending'    => false,
        'popular'     => false,
        'eventMode'   => $e->getEventMode(),
    ];
}, $dbEvents), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Events — CareerStrand</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/events.css" />
</head>
</head>
<body>

<!-- ══ HEADER ══ -->
<header class="site-header">
  <div class="container">
    <div class="header-inner">
      <div class="brand" onclick="showPage('listing')">
        <div class="brand-badge"></div>
        <div><div class="brand-title">CareerStrand</div><div class="brand-sub">Career Progression</div></div>
      </div>
      <nav class="nav">
        <button class="nav-btn" onclick="">Profile</button>
        <button class="nav-btn" onclick="">Education</button>
        <button class="nav-btn" onclick="">Skill Hub</button>
        <button class="nav-btn active" id="nav-listing" onclick="showPage('listing')">Events</button>
        <button class="nav-btn" onclick="">Opportunities</button>
      </nav>
      <div class="header-right">
        <button class="btn btn-ghost btn-sm" id="nav-sponsors" onclick="showPage('sponsors')">💼 Sponsors</button>
        <button class="btn btn-ghost btn-sm" id="nav-forms" onclick="showPage('forms')">📋 Forms</button>
        <button class="btn btn-ghost btn-sm" id="nav-my" onclick="showPage('my')">My Events</button>
        <div class="user-av" title="Logged in as: Alex">AY</div>
      </div>
    </div>
  </div>
</header>

<!-- ══════════ PAGE: LISTING ══════════ -->
<div class="page active" id="page-listing">
  <div class="container">

    <!-- Hero -->
    <div class="hero">
      <div class="hero-eyebrow"><span class="dot"></span> Live Platform</div>
      <h1>Discover & Join<br>Events</h1>
      <p class="hero-sub">Workshops, hackathons, bootcamps and career events — all in one place to accelerate your career journey.</p>
      <div class="hero-actions">
        <button class="btn btn-main" onclick="document.getElementById('search-input').focus()">Browse Events</button>
        <button class="btn btn-ghost" onclick="showPage('my')">My Participations</button>
      </div>
      <div class="hero-stats">
        <div class="hstat"><div class="hstat-label">Total Events</div><div class="hstat-value" id="hs-total">—</div><div class="hstat-sub">Active platform</div></div>
        <div class="hstat"><div class="hstat-label">Upcoming</div><div class="hstat-value" id="hs-upcoming" style="color:var(--blue-2)">—</div><div class="hstat-sub">Register now</div></div>
        <div class="hstat"><div class="hstat-label">Joined</div><div class="hstat-value" id="hs-joined" style="color:var(--green)">—</div><div class="hstat-sub">Your events</div></div>
      </div>
    </div>

    <!-- Search + Filters -->
    <div class="search-section">
      <div class="search-wrap">
        <div class="searchbar" id="sb-wrap">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input id="search-input" type="text" placeholder="Search events by title, type, location…" oninput="renderAll()" onfocus="document.getElementById('sb-wrap').classList.add('focused')" onblur="document.getElementById('sb-wrap').classList.remove('focused')" />
        </div>
      </div>
      <div class="filter-chips" id="filter-chips">
        <button class="fchip active" onclick="setFilter('all',this)">All</button>
        <button class="fchip" onclick="setFilter('Workshop',this)">Workshop</button>
        <button class="fchip" onclick="setFilter('Hackathon',this)">Hackathon</button>
        <button class="fchip" onclick="setFilter('Career Event',this)">Career</button>
        <button class="fchip" onclick="setFilter('Bootcamp',this)">Bootcamp</button>
      </div>
    </div>

    <!-- Recommended Section -->
    <div class="rec-section" id="rec-section" style="display:none">
      <div class="rec-label">⭐ Recommended for you</div>
      <div class="events-grid" id="rec-grid"></div>
    </div>

    <!-- Upcoming -->
    <div class="sec-hdr">
      <div class="sec-title">📅 Upcoming Events <span class="sec-count" id="upcoming-count">0</span></div>
    </div>
    <div class="events-grid" id="upcoming-grid"></div>

    <!-- Past -->
    <div class="sec-hdr" style="margin-top:8px">
      <div class="sec-title">🕐 Past Events <span class="sec-count" id="past-count">0</span></div>
    </div>
    <div class="events-grid" id="past-grid"></div>

  </div>
</div>

<!-- ══════════ PAGE: DETAIL ══════════ -->
<div class="page" id="page-detail">
  <div class="container">
    <div class="detail-layout">
      <!-- LEFT -->
      <div class="detail-main">
        <div class="detail-hero">
          <div class="detail-back" onclick="showPage('listing')">← Back to Events</div>
          <div class="detail-type-row" id="detail-types"></div>
          <div class="detail-title" id="detail-title">—</div>
          <div class="detail-desc" id="detail-desc">—</div>
          <div class="detail-meta-grid" id="detail-meta"></div>
        </div>

        <!-- Feedback Section -->
        <div class="feedback-section" id="feedback-wrap">
          <div class="feedback-title" id="feedback-section-title">⭐ Ratings & Feedback</div>

          <!-- Rating form (only for joined + past) -->
          <div id="feedback-form-wrap" style="display:none">
            <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Share your experience with this event:</p>
            <div class="feedback-form">
              <div>
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.16em;color:var(--muted-2);margin-bottom:8px">Your Rating</div>
                <div class="stars-input" id="rating-input">
                  <span class="star empty" onclick="setRating(1)">★</span>
                  <span class="star empty" onclick="setRating(2)">★</span>
                  <span class="star empty" onclick="setRating(3)">★</span>
                  <span class="star empty" onclick="setRating(4)">★</span>
                  <span class="star empty" onclick="setRating(5)">★</span>
                </div>
              </div>
              <textarea class="feedback-textarea" id="feedback-text" placeholder="Write your feedback…"></textarea>
              <div style="display:flex;justify-content:flex-end">
                <button class="btn btn-main btn-sm" onclick="submitFeedback()">Submit Feedback</button>
              </div>
            </div>
          </div>

          <!-- Avg rating display -->
          <div id="avg-rating-display" style="margin-bottom:16px"></div>

          <!-- Reviews list -->
          <div class="feedback-list" id="feedback-list"></div>
          <div id="feedback-empty" style="display:none;text-align:center;padding:24px;color:var(--muted-2);font-size:13px">No reviews yet. Be the first!</div>
        </div>
      </div>

      <!-- RIGHT -->
      <div class="detail-side">
        <div class="side-card">
          <div class="join-section">
            <div class="join-count" id="detail-join-count">—</div>
            <div class="join-label">Participants Registered</div>
            <div class="join-btn-wrap" id="join-btn-wrap"></div>
            <div class="join-progress">
              <div class="lbl-row">
                <span id="cap-label-left">Capacity</span>
                <span id="cap-label-right">—</span>
              </div>
              <div class="join-bar"><div class="join-bar-fill" id="join-bar-fill" style="width:0%"></div></div>
            </div>
          </div>
        </div>

        <!-- Quick info -->
        <div class="side-card">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.18em;color:var(--muted-2);margin-bottom:12px">Event Info</div>
          <div id="side-info"></div>
        </div>

        <!-- Organiser -->
        <div class="side-card" id="side-organiser-wrap">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.18em;color:var(--muted-2);margin-bottom:12px">Organiser</div>
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:40px;height:40px;border-radius:14px;display:grid;place-items:center;font-weight:800;font-size:14px;background:linear-gradient(135deg,var(--blue),var(--red));color:var(--white)" id="org-av">—</div>
            <div><div style="font-size:14px;font-weight:700;color:var(--white)" id="org-name">—</div><div style="font-size:12px;color:var(--muted-2)">Event Organiser</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════ PAGE: MY PARTICIPATIONS ══════════ -->
<div class="page" id="page-my">
  <div class="container" style="padding:40px 0">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:32px;flex-wrap:wrap">
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.22em;color:var(--muted-2);margin-bottom:10px">Your Events</div>
        <h2 style="font-size:clamp(28px,4vw,40px);font-weight:900;letter-spacing:-.04em">My Participations</h2>
        <p style="margin-top:8px;color:var(--muted);font-size:14px;line-height:1.75">Track all events you've registered for and manage your participation status.</p>
      </div>
    </div>

    <!-- Status filter -->
    <div class="filter-chips" style="margin-bottom:24px" id="my-filter-chips">
      <button class="fchip active" onclick="setMyFilter('all',this)">All</button>
      <button class="fchip" onclick="setMyFilter('Confirmed',this)">Confirmed</button>
      <button class="fchip" onclick="setMyFilter('Pending',this)">Pending</button>
      <button class="fchip" onclick="setMyFilter('Cancelled',this)">Cancelled</button>
    </div>

    <div class="part-list" id="my-list"></div>
    <div id="my-empty" style="display:none">
      <div class="empty-state">
        <div class="empty-icon">🎟</div>
        <h3>No participations yet</h3>
        <p>Browse upcoming events and join something exciting!</p>
        <button class="btn btn-main" style="margin-top:20px" onclick="showPage('listing')">Browse Events</button>
      </div>
    </div>
  </div>
</div>


<!-- ══════════ PAGE: SPONSORS ══════════ -->
<div class="page" id="page-sponsors">
  <div class="container" style="padding:40px 0">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.22em;color:var(--muted-2);margin-bottom:10px">Event Sponsors</div>
        <h2 style="font-size:clamp(28px,4vw,40px);font-weight:900;letter-spacing:-.04em">Our Sponsors</h2>
        <p style="margin-top:8px;color:var(--muted);font-size:14px;line-height:1.75">Companies and partners supporting our events community.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div class="searchbar" id="sb-sponsors-wrap" style="min-width:220px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input id="search-sponsors-front" type="text" placeholder="Search sponsors…" oninput="renderSponsors()" onfocus="document.getElementById('sb-sponsors-wrap').classList.add('focused')" onblur="document.getElementById('sb-sponsors-wrap').classList.remove('focused')" />
        </div>
        <div class="filters" id="sponsor-sort-filters">
          <button class="filter is-selected" onclick="setSponsorSort('all',this)">All</button>
          <button class="filter" onclick="setSponsorSort('desc',this)">↓ Amount</button>
          <button class="filter" onclick="setSponsorSort('asc',this)">↑ Amount</button>
        </div>
      </div>
    </div>
    <div id="sponsors-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px"></div>
  </div>
</div>

<!-- ══════════ PAGE: FORMS ══════════ -->
<div class="page" id="page-forms">
  <div class="container" style="padding:40px 0">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.22em;color:var(--muted-2);margin-bottom:10px">Registration Forms</div>
        <h2 style="font-size:clamp(28px,4vw,40px);font-weight:900;letter-spacing:-.04em">Event Forms</h2>
        <p style="margin-top:8px;color:var(--muted);font-size:14px;line-height:1.75">Open registration and application forms for upcoming events.</p>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div class="searchbar" id="sb-forms-wrap" style="min-width:220px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input id="search-forms-front" type="text" placeholder="Search forms…" oninput="renderForms()" onfocus="document.getElementById('sb-forms-wrap').classList.add('focused')" onblur="document.getElementById('sb-forms-wrap').classList.remove('focused')" />
        </div>
        <div class="filters" id="form-status-filters-front">
          <button class="filter is-selected" onclick="setFormStatusFilter('all',this)">All</button>
          <button class="filter" onclick="setFormStatusFilter('open',this)">Open</button>
          <button class="filter" onclick="setFormStatusFilter('closed',this)">Closed</button>
          <button class="filter" onclick="setFormStatusFilter('draft',this)">Draft</button>
        </div>
      </div>
    </div>
    <div id="forms-list" style="display:grid;gap:16px"></div>
  </div>
</div>

<!-- ══ MODALS ══ -->

<!-- Join Confirm -->
<div class="modal-backdrop" id="modal-join">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Join Event</h3>
      <button class="modal-close" onclick="closeModal('join')">✕</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;padding:8px 0 16px">
        <div style="font-size:48px;margin-bottom:12px">🎟</div>
        <div style="font-size:18px;font-weight:800;color:var(--white);margin-bottom:8px" id="join-modal-title">—</div>
        <div style="font-size:13px;color:var(--muted);line-height:1.7" id="join-modal-meta">—</div>
      </div>
      <div style="padding:16px;border-radius:16px;background:rgba(89,211,155,.07);border:1px solid rgba(89,211,155,.16)">
        <div style="font-size:13px;color:var(--green);font-weight:600;margin-bottom:4px">✓ Your spot will be confirmed immediately</div>
        <div style="font-size:12px;color:var(--muted)">You can cancel anytime from My Participations.</div>
      </div>
    </div>
    <div class="modal-ftr">
      <button class="btn btn-ghost" onclick="closeModal('join')">Cancel</button>
      <button class="btn btn-main" id="join-confirm-btn">Confirm Registration</button>
    </div>
  </div>
</div>

<!-- Cancel Confirm -->
<div class="modal-backdrop" id="modal-cancel">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Cancel Participation</h3>
      <button class="modal-close" onclick="closeModal('cancel')">✕</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;padding:8px 0 16px">
        <div style="font-size:48px;margin-bottom:12px">⚠️</div>
        <div style="font-size:16px;font-weight:700;color:var(--white);margin-bottom:8px" id="cancel-modal-title">—</div>
        <div style="font-size:13px;color:var(--muted)">Are you sure you want to cancel? Your spot will be freed.</div>
      </div>
    </div>
    <div class="modal-ftr">
      <button class="btn btn-ghost" onclick="closeModal('cancel')">Keep my spot</button>
      <button class="btn btn-danger" id="cancel-confirm-btn">Yes, Cancel</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- ══ SCRIPT ══ -->
<script>
/* ───── DATA ───── */
// ── Events loaded from MySQL via PHP ──────────────────
let events = <?php echo $eventsJson; ?>;

/* Participations: {eventId, status, joinedAt} */
let myParticipations = [
  {eventId:3,status:"Confirmed",joinedAt:"2025-07-20"},
  {eventId:6,status:"Confirmed",joinedAt:"2025-07-08"},
];

/* Feedbacks: {eventId, user, rating, text, date} */
let feedbacks = [
  {eventId:3,user:"Alex Y.",rating:4,text:"Really useful session. Got direct feedback on my portfolio.",date:"2025-07-31"},
  {eventId:3,user:"Rim K.",rating:5,text:"Best career event I've attended. Managers were very engaged.",date:"2025-07-31"},
  {eventId:6,user:"Omar M.",rating:4,text:"Great energy! Loved the open source community vibe.",date:"2025-07-13"},
];

let currentFilter = "all";
let currentMyFilter = "all";
let currentDetailId = null;
let currentRating = 0;
let pendingJoinId = null;
let pendingCancelId = null;

/* ───── HELPERS ───── */
const $ = id => document.getElementById(id);

function typeChipColor(t){
  const m={"Workshop":"chip-blue","Hackathon":"chip-red","Career Event":"chip-green","Bootcamp":"chip-yellow"};
  return m[t]||"chip-gray";
}

function statusChip(s){
  const m={"Upcoming":"chip-blue","Past":"chip-gray","Live":"chip-green"};
  return `<span class="chip ${m[s]||'chip-gray'}">${s}</span>`;
}

function pct(reg,cap){return cap?Math.min(100,Math.round(reg/cap*100)):0}

function starsHtml(r,total=5){
  let h="";
  for(let i=1;i<=total;i++) h+=`<span class="star ${i<=r?'filled':'empty'}">★</span>`;
  return h;
}

function avgRating(eid){
  const fb=feedbacks.filter(f=>f.eventId===eid);
  if(!fb.length) return null;
  return (fb.reduce((s,f)=>s+f.rating,0)/fb.length).toFixed(1);
}

function isJoined(eid){return myParticipations.some(p=>p.eventId===eid&&p.status!=="Cancelled")}
function getParticipation(eid){return myParticipations.find(p=>p.eventId===eid)}

function hasFeedback(eid){
  return feedbacks.some(f=>f.eventId===eid&&f.user==="Alex Y.");
}

/* ───── FILTER ───── */
function setFilter(val,btn){
  currentFilter=val;
  document.querySelectorAll('#filter-chips .fchip').forEach(f=>f.classList.remove('active'));
  btn.classList.add('active');
  renderAll();
}

function setMyFilter(val,btn){
  currentMyFilter=val;
  document.querySelectorAll('#my-filter-chips .fchip').forEach(f=>f.classList.remove('active'));
  btn.classList.add('active');
  renderMy();
}

function getFiltered(){
  const q=$('search-input').value.toLowerCase();
  return events.filter(e=>{
    if(currentFilter!=='all'&&e.type!==currentFilter) return false;
    if(q&&!e.title.toLowerCase().includes(q)&&!e.type.toLowerCase().includes(q)&&!e.location.toLowerCase().includes(q)) return false;
    return true;
  });
}

/* ───── RECOMMENDATIONS ───── */
function getRecommended(){
  const joinedTypes=myParticipations.filter(p=>p.status!=="Cancelled").map(p=>events.find(e=>e.id===p.eventId)?.type).filter(Boolean);
  return events.filter(e=>{
    if(e.status==="Past") return false;
    if(isJoined(e.id)) return false;
    if(joinedTypes.includes(e.type)) return true;
    if(e.popular&&pct(e.registrations,e.capacity)>70) return true;
    return false;
  }).slice(0,3);
}

/* ───── RENDER CARD ───── */
function renderCard(e, showRec=false){
  const joined=isJoined(e.id);
  const p=pct(e.registrations,e.capacity);
  const avg=avgRating(e.id);
  const isFull=e.registrations>=e.capacity;

  let badge="";
  if(e.trending) badge=`<div class="trending-badge">🔥 Trending</div>`;
  else if(e.popular) badge=`<div class="popular-badge">⭐ Popular</div>`;

  let recBanner="";
  if(showRec) recBanner=`<div class="recommended-banner"><span>⭐</span> Recommended based on your interests</div>`;

  let joinedBadge="";
  if(joined) joinedBadge=`<span class="chip chip-green" style="margin-left:auto">✓ Joined</span>`;

  let ratingHtml="";
  if(avg) ratingHtml=`<div class="avg-rating"><span class="avg-val">${avg}</span>${starsHtml(Math.round(avg))}<span class="avg-count">(${feedbacks.filter(f=>f.eventId===e.id).length})</span></div>`;

  return `
    <div class="event-card ${joined?'joined':''} ${e.status==='Past'?'past':''}" onclick="openDetail(${e.id})">
      ${badge}
      ${recBanner}
      <div class="card-top">
        <div class="card-badges">
        <span class="chip ${typeChipColor(e.type)}">${e.type}</span>
        ${statusChip(e.status)}
        <span class="chip ${e.eventMode === 'Online' ? 'chip-blue' : 'chip-green'}">${e.eventMode === 'Online' ? '💻 Online' : '🏢 In-person'}</span>
        ${joinedBadge}
        </div>
      </div>
      <div class="card-title">${e.title}</div>
      <div class="card-desc">${e.description}</div>
      <div class="card-meta">
    <div class="meta-item"><span class="meta-icon">📅</span>${e.date} at ${e.time}</div>
    <div class="meta-item"><span class="meta-icon">📍</span>${e.location}</div>
    <div class="meta-item"><span class="meta-icon">⏱️</span>${e.duration ? e.duration + ' min' : '—'}</div>
    <div class="meta-item"><span class="meta-icon">👥</span>${e.registrations}/${e.capacity}</div>
</div>
      ${ratingHtml}
      <div class="card-footer" style="margin-top:12px">
        <div class="cap-wrap">
          <div class="cap-info"><span>Capacity</span><span>${p}%</span></div>
          <div class="cap-bar"><div class="cap-fill" style="width:${p}%"></div></div>
        </div>
        <button class="btn ${joined?'btn-success':'btn-main'} btn-sm" onclick="event.stopPropagation();${joined?`openCancelModal(${e.id})`:`openJoinModal(${e.id})`}" ${e.status==='Past'||(!joined&&isFull)?'disabled style="opacity:.5;cursor:not-allowed"':''}>
          ${e.status==='Past'?'Past':joined?'✓ Joined':isFull?'Full':'Join →'}
        </button>
      </div>
    </div>
  `;
}

/* ───── RENDER ALL ───── */
function renderAll(){
  const filtered=getFiltered();
  const upcoming=filtered.filter(e=>e.status==="Upcoming"||e.status==="Live");
  const past=filtered.filter(e=>e.status==="Past");

  $('upcoming-count').textContent=upcoming.length;
  $('past-count').textContent=past.length;

  $('upcoming-grid').innerHTML=upcoming.length
    ? upcoming.map(e=>renderCard(e)).join('')
    : `<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">🗓</div><h3>No upcoming events</h3><p>Try changing your filters.</p></div>`;

  $('past-grid').innerHTML=past.length
    ? past.map(e=>renderCard(e)).join('')
    : `<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">🕐</div><h3>No past events</h3></div>`;

  // Recommendations
  const recs=getRecommended();
  if(recs.length&&currentFilter==='all'&&!$('search-input').value){
    $('rec-section').style.display='block';
    $('rec-grid').innerHTML=recs.map(e=>renderCard(e,true)).join('');
  }else{
    $('rec-section').style.display='none';
  }

  // Hero stats
  $('hs-total').textContent=events.length;
  $('hs-upcoming').textContent=events.filter(e=>e.status==="Upcoming").length;
  $('hs-joined').textContent=myParticipations.filter(p=>p.status!=="Cancelled").length;
}

/* ───── MY PARTICIPATIONS ───── */
function renderMy(){
  const filtered=myParticipations.filter(p=>currentMyFilter==='all'||p.status===currentMyFilter);
  const list=$('my-list');
  const empty=$('my-empty');
  if(!filtered.length){list.innerHTML='';empty.style.display='block';return}
  empty.style.display='none';
  list.innerHTML=filtered.map(p=>{
    const e=events.find(ev=>ev.id===p.eventId);
    if(!e) return '';
    const avg=avgRating(e.id);
    const hasFb=hasFeedback(e.id);
    return `
      <div class="part-card ${p.status.toLowerCase()}" onclick="openDetail(${e.id})">
        <div class="part-top">
          <div>
            <div class="part-title">${e.title}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
              <span class="chip ${typeChipColor(e.type)}">${e.type}</span>
              ${statusChip(e.status)}
              <span class="chip ${p.status==='Confirmed'?'chip-green':p.status==='Pending'?'chip-yellow':'chip-red'}">${p.status}</span>
            </div>
          </div>
          ${avg?`<div class="avg-rating"><span class="avg-val">${avg}</span><span style="color:var(--yellow)">★</span></div>`:''}
        </div>
        <div class="meta-item" style="margin-bottom:8px"><span class="meta-icon">📅</span>${e.date} at ${e.time} · ${e.location}</div>
        <div class="meta-item"><span class="meta-icon">📋</span>Registered: ${p.joinedAt}</div>
        <div class="part-actions" onclick="event.stopPropagation()">
          <button class="btn btn-ghost btn-xs" onclick="openDetail(${e.id})">View Event</button>
          ${p.status!=='Cancelled'?`<button class="btn btn-danger btn-xs" onclick="openCancelModal(${e.id})">Cancel</button>`:''}
          ${e.status==='Past'&&p.status==='Confirmed'&&!hasFb?`<button class="btn btn-xs" style="background:rgba(245,191,101,.12);color:var(--yellow);border:1px solid rgba(245,191,101,.22)" onclick="openDetail(${e.id},true)">★ Leave Feedback</button>`:''}
        </div>
      </div>`;
  }).join('');
}

/* ───── DETAIL PAGE ───── */
function openDetail(id, scrollToFeedback=false){
  currentDetailId=id;
  const e=events.find(ev=>ev.id===id);
  if(!e) return;
  const joined=isJoined(id);
  const part=getParticipation(id);
  const isFull=e.registrations>=e.capacity;
  const p=pct(e.registrations,e.capacity);

  // Types row
  $('detail-types').innerHTML=`
    <span class="chip ${typeChipColor(e.type)}">${e.type}</span>
    ${statusChip(e.status)}
    ${joined?'<span class="chip chip-green">✓ You are registered</span>':''}
    ${e.trending?'<span class="chip chip-red">🔥 Trending</span>':''}
    ${e.popular?'<span class="chip chip-blue">⭐ Popular</span>':''}
  `;

  $('detail-title').textContent=e.title;
  $('detail-desc').textContent=e.description;

  // Meta grid
$('detail-meta').innerHTML=`
    <div class="detail-meta-item"><div class="lbl">Date & Time</div><div class="val">📅 ${e.date} at ${e.time||'—'}</div></div>
    <div class="detail-meta-item"><div class="lbl">Location</div><div class="val">📍 ${e.location}</div></div>
    <div class="detail-meta-item"><div class="lbl">Capacity</div><div class="val">👥 ${e.registrations} / ${e.capacity} registered</div></div>
    <div class="detail-meta-item"><div class="lbl">Event Type</div><div class="val">${e.type}</div></div>
    <div class="detail-meta-item"><div class="lbl">Format</div><div class="val">${e.eventMode === 'Online' ? '🖥️ Online' : '📍 In-person'}</div></div>
    <div class="detail-meta-item"><div class="lbl">Sponsor ID</div><div class="val">${e.sponsorId || '—'}</div></div>
    <div class="detail-meta-item"><div class="lbl">Duration (min)</div><div class="val">${e.duration || '—'}</div></div>
    <div class="detail-meta-item"><div class="lbl">Tags</div><div class="val">${e.tags&&e.tags.length?e.tags.join(', '):'—'}</div></div>
    <div class="detail-meta-item"><div class="lbl">Created At</div><div class="val">📆 ${e.createdAt||'—'}</div></div>
  `;

  // Join count
  $('detail-join-count').textContent=e.registrations;
  $('cap-label-left').textContent=`${p}% filled`;
  $('cap-label-right').textContent=`${e.capacity-e.registrations} spots left`;
  $('join-bar-fill').style.width=p+"%";

  // Join button
  let joinBtnHtml="";
  if(e.status==="Past"){
    joinBtnHtml=`<button class="btn btn-ghost" disabled style="width:100%;justify-content:center;opacity:.5">Event Ended</button>`;
  }else if(joined){
    joinBtnHtml=`<button class="btn btn-success" style="width:100%;justify-content:center" onclick="openCancelModal(${e.id})">✓ Registered — Cancel?</button>`;
  }else if(isFull){
    joinBtnHtml=`<button class="btn btn-ghost" disabled style="width:100%;justify-content:center;opacity:.5">Event Full</button>`;
  }else{
    joinBtnHtml=`<button class="btn btn-main" style="width:100%;justify-content:center" onclick="openJoinModal(${e.id})">Join Event →</button>`;
  }
  $('join-btn-wrap').innerHTML=joinBtnHtml;

  // Side info
  $('side-info').innerHTML=`
    <div style="display:grid;gap:8px">
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.08)"><span style="color:var(--muted-2);font-size:12px">Type</span><span style="font-size:13px;font-weight:600">${e.type}</span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.08)"><span style="color:var(--muted-2);font-size:12px">Date</span><span style="font-size:13px;font-weight:600">${e.date}</span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.08)"><span style="color:var(--muted-2);font-size:12px">Time</span><span style="font-size:13px;font-weight:600">${e.time||'—'}</span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.08)"><span style="color:var(--muted-2);font-size:12px">Location</span><span style="font-size:13px;font-weight:600">${e.location}</span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.08)"><span style="color:var(--muted-2);font-size:12px">Manager ID</span><span style="font-size:13px;font-weight:600">${e.managerId||'—'}</span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.08)"><span style="color:var(--muted-2);font-size:12px">Tags</span><span style="font-size:13px;font-weight:600">${e.tags&&e.tags.length?e.tags.join(', '):'—'}</span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0"><span style="color:var(--muted-2);font-size:12px">Created At</span><span style="font-size:13px;font-weight:600">${e.createdAt||'—'}</span></div>
    </div>
  `;

  // Organiser
  $('org-av').textContent=(e.organiser||'CS').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
  $('org-name').textContent=e.organiser||'CareerStrand';

  // Feedback section
  const avg=avgRating(id);
  const eventFeedbacks=feedbacks.filter(f=>f.eventId===id);
  const myFeedback=feedbacks.find(f=>f.eventId===id&&f.user==="Alex Y.");
  const showForm=e.status==="Past"&&joined&&!myFeedback;

  $('feedback-section-title').textContent=e.status==="Past"?"⭐ Ratings & Feedback":"💬 Community (feedback available after event)";
  $('feedback-form-wrap').style.display=showForm?'block':'none';

  // Avg display
  if(avg){
    $('avg-rating-display').innerHTML=`
      <div style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:16px;background:rgba(245,191,101,.07);border:1px solid rgba(245,191,101,.16);margin-bottom:14px">
        <div style="text-align:center">
          <div style="font-size:40px;font-weight:900;letter-spacing:-.04em;color:var(--yellow)">${avg}</div>
          <div style="display:flex;gap:2px;margin-top:4px">${starsHtml(Math.round(parseFloat(avg)))}</div>
        </div>
        <div>
          <div style="font-size:15px;font-weight:700;color:var(--white)">Average Rating</div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px">Based on ${eventFeedbacks.length} review${eventFeedbacks.length!==1?'s':''}</div>
        </div>
      </div>`;
  }else{
    $('avg-rating-display').innerHTML='';
  }

  // Reviews
  if(eventFeedbacks.length){
    $('feedback-empty').style.display='none';
    $('feedback-list').innerHTML=eventFeedbacks.map(f=>`
      <div class="feedback-item">
        <div class="feedback-item-top">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:10px;display:grid;place-items:center;font-size:11px;font-weight:800;background:linear-gradient(135deg,var(--blue),var(--red));color:var(--white)">${f.user.split(' ').map(w=>w[0]).join('')}</div>
            <div><div class="feedback-author">${f.user}</div><div style="display:flex;gap:2px;margin-top:2px">${starsHtml(f.rating)}</div></div>
          </div>
          <div class="feedback-date">${f.date}</div>
        </div>
        <div class="feedback-text">${f.text}</div>
      </div>`).join('');
  }else{
    $('feedback-list').innerHTML='';
    $('feedback-empty').style.display=e.status==="Past"?'block':'none';
  }

  // Reset rating input
  currentRating=0;
  document.querySelectorAll('#rating-input .star').forEach((s,i)=>{
    s.className='star empty';s.onclick=()=>setRating(i+1);
  });
  $('feedback-text').value='';

  showPage('detail');
  if(scrollToFeedback) setTimeout(()=>$('feedback-wrap').scrollIntoView({behavior:'smooth'}),300);
}

/* ───── RATING INPUT ───── */
function setRating(n){
  currentRating=n;
  document.querySelectorAll('#rating-input .star').forEach((s,i)=>{
    s.className=`star ${i<n?'filled':'empty'}`;
  });
}

function submitFeedback(){
  if(!currentRating){toast('Please select a rating','error');return}
  const text=$('feedback-text').value.trim();
  feedbacks.push({eventId:currentDetailId,user:"Alex Y.",rating:currentRating,text:text||"Great event!",date:new Date().toISOString().slice(0,10)});
  toast('Feedback submitted ✓','success');
  openDetail(currentDetailId);
}

/* ───── JOIN / CANCEL ───── */
function openJoinModal(id){
  pendingJoinId=id;
  const e=events.find(ev=>ev.id===id);
  $('join-modal-title').textContent=e.title;
  $('join-modal-meta').textContent=`${e.date} at ${e.time} · ${e.location}`;
  $('join-confirm-btn').onclick=()=>confirmJoin();
  openModal('join');
}

function confirmJoin(){
  const e=events.find(ev=>ev.id===pendingJoinId);
  if(!e) return;
  const existing=myParticipations.find(p=>p.eventId===pendingJoinId);
  if(existing){existing.status="Confirmed";}
  else{myParticipations.push({eventId:pendingJoinId,status:"Confirmed",joinedAt:new Date().toISOString().slice(0,10)});}
  e.registrations=Math.min(e.registrations+1,e.capacity);
  closeModal('join');
  toast(`You've joined "${e.title}" 🎉`,'success');
  const countEl=$('detail-join-count');
  if(countEl){countEl.textContent=e.registrations;countEl.classList.add('join-anim');setTimeout(()=>countEl.classList.remove('join-anim'),400);}
  renderAll();
  if(currentDetailId===pendingJoinId) openDetail(pendingJoinId);
}

function openCancelModal(id){
  pendingCancelId=id;
  const e=events.find(ev=>ev.id===id);
  $('cancel-modal-title').textContent=e.title;
  $('cancel-confirm-btn').onclick=()=>confirmCancel();
  openModal('cancel');
}

function confirmCancel(){
  const e=events.find(ev=>ev.id===pendingCancelId);
  if(!e) return;
  const p=myParticipations.find(x=>x.eventId===pendingCancelId);
  if(p){p.status="Cancelled";}
  e.registrations=Math.max(0,e.registrations-1);
  closeModal('cancel');
  toast(`Participation cancelled`,'info');
  renderAll();
  renderMy();
  if(currentDetailId===pendingCancelId) openDetail(pendingCancelId);
}

/* ───── NAVIGATION ───── */
function showPage(name){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  $('page-'+name).classList.add('active');
  document.querySelectorAll('nav.nav .nav-btn').forEach(b=>b.classList.remove('active'));
  const nb=$('nav-'+name);if(nb) nb.classList.add('active');
  ['nav-my','nav-sponsors','nav-forms'].forEach(bid=>{
    const b=$(bid);
    if(b){const active=bid==='nav-'+name;b.style.background=active?'rgba(111,143,216,.18)':'rgba(255,255,255,.05)';b.style.borderColor=active?'rgba(126,159,228,.28)':'rgba(255,255,255,.1)';}
  });
  if(name==='listing') renderAll();
  if(name==='my') renderMy();
  if(name==='sponsors') loadSponsorsFromDB();
  if(name==='forms') loadEventFormsFromDB();
  window.scrollTo({top:0,behavior:'smooth'});
}

/* ───── MODAL ───── */
function openModal(n){$('modal-'+n).classList.add('open');$('modal-'+n).onclick=e=>{if(e.target===$('modal-'+n))closeModal(n)}}
function closeModal(n){$('modal-'+n).classList.remove('open')}

/* ───── TOAST ───── */
function toast(msg,type='success'){
  const w=$('toast-wrap'),el=document.createElement('div');
  el.className=`toast ${type}`;el.innerHTML=`<div class="toast-dot"></div>${msg}`;
  w.appendChild(el);setTimeout(()=>el.remove(),3200);
}

/* ───── SPONSORS & FORMS DATA ───── */
let sponsors = []; // chargé depuis la BDD via loadSponsorsFromDB()
let sponsorSort = 'all'; // 'all' | 'asc' | 'desc'
let formStatusFilter = 'all'; // 'all' | 'open' | 'closed' | 'draft'

function loadSponsorsFromDB(){
  fetch('search_sponsor_front.php')
    .then(r => r.json())
    .then(data => {
      sponsors = data;
      renderSponsors();
    })
    .catch(() => renderSponsors());
}
let eventForms = [];

/* ───── SPONSOR SORT ───── */
function setSponsorSort(val,btn){
  sponsorSort=val;
  document.querySelectorAll('#sponsor-sort-filters .filter').forEach(f=>f.classList.remove('is-selected'));
  btn.classList.add('is-selected');
  renderSponsors();
}

/* ───── FORMS STATUS FILTER ───── */
function setFormStatusFilter(val,btn){
  formStatusFilter=val;
  document.querySelectorAll('#form-status-filters-front .filter').forEach(f=>f.classList.remove('is-selected'));
  btn.classList.add('is-selected');
  renderForms();
}

/* ───── SPONSORS RENDER ───── */
function renderSponsors(){
  const grid=document.getElementById('sponsors-grid');
  const q=(document.getElementById('search-sponsors-front')?.value||'').toLowerCase();
  let list=sponsors.filter(s=>{
    if(!q) return true;
    return s.name.toLowerCase().includes(q)||s.company.toLowerCase().includes(q)||(s.contribution||'').toLowerCase().includes(q);
  });
  if(sponsorSort==='desc') list=[...list].sort((a,b)=>b.amount-a.amount);
  else if(sponsorSort==='asc') list=[...list].sort((a,b)=>a.amount-b.amount);
  if(!list.length){grid.innerHTML='<div class="empty-state"><div class="empty-icon">💼</div><h3>No sponsors found</h3></div>';return}
 grid.innerHTML=list.map(s=>{
    return `<div style="padding:22px;border-radius:24px;background:linear-gradient(180deg,rgba(12,22,46,.92),rgba(7,13,29,.94));border:1px solid rgba(126,159,228,.14);box-shadow:0 14px 40px rgba(0,0,0,.2)">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
        <div style="width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-weight:800;font-size:15px;background:linear-gradient(135deg,var(--blue),var(--red));color:var(--white);flex-shrink:0">${s.company.slice(0,2).toUpperCase()}</div>
        <div><div style="font-size:16px;font-weight:800;color:var(--white)">${s.company}</div><div style="font-size:12px;color:var(--muted-2)">${s.name}</div></div>
      </div>
      <div style="padding:10px 14px;border-radius:12px;background:rgba(111,143,216,.07);border:1px solid rgba(126,159,228,.1);margin-bottom:12px">
        <div style="font-size:11px;color:var(--muted-2);margin-bottom:4px">Contribution</div>
        <div style="font-size:13px;color:var(--white)">${s.contribution}</div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="font-size:22px;font-weight:800;letter-spacing:-.04em;color:var(--yellow)">${Number(s.amount).toLocaleString()} TND</div>
      </div>
    </div>`;
}).join('');
}

function loadEventFormsFromDB(){
  fetch('search_eventform_front.php')
    .then(r => r.json())
    .then(data => {
      eventForms = data;
      renderForms();   // refresh the UI
    })
    .catch(err => console.error('loadEventFormsFromDB:', err));
}

/* ───── FORMS RENDER ───── */
function renderForms(){
  const listEl=document.getElementById('forms-list');
  const q=(document.getElementById('search-forms-front')?.value||'').toLowerCase();
  let filtered=eventForms.filter(f=>{
    if(formStatusFilter!=='all'&&f.status!==formStatusFilter) return false;
    if(q&&!f.title.toLowerCase().includes(q)&&!f.description.toLowerCase().includes(q)) return false;
    return true;
  });
  if(!filtered.length){listEl.innerHTML='<div class="empty-state"><div class="empty-icon">📋</div><h3>No forms found</h3></div>';return}
  listEl.innerHTML=filtered.map(f=>{
    const ev=events.find(e=>e.id===f.eventId);
    const isOpen=f.status==='open';
    return `<div style="padding:22px;border-radius:24px;background:linear-gradient(180deg,rgba(12,22,46,.92),rgba(7,13,29,.94));border:1px solid ${isOpen?'rgba(89,211,155,.18)':'rgba(126,159,228,.14)'};box-shadow:0 14px 40px rgba(0,0,0,.2);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <span style="font-size:16px;font-weight:800;color:var(--white)">${f.title}</span>
          <span class="chip ${isOpen?'chip-green':'chip-red'}">${f.status}</span>
        </div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:6px">${f.description}</div>
        <div style="font-size:12px;color:var(--muted-2)">📅 Event: ${ev?ev.title:'—'}</div>
      </div>
      ${isOpen?`<a href="${f.formLink}" target="_blank" class="btn btn-main btn-sm" style="text-decoration:none">Fill Form →</a>`
              :`<span class="btn btn-ghost btn-sm" style="opacity:.5;cursor:not-allowed">Closed</span>`}
    </div>`;
  }).join('');
}

/* ───── LOAD FROM DB ───── */
function loadEventsFromDB(){
  fetch('search_event_front.php')
    .then(r=>r.json())
    .then(data=>{
      events=data.map(e=>({
        id:e.eventId, title:e.name, description:e.description,
        type:e.type, location:e.location, capacity:e.capacity,
        date:e.date, status:e.status, createdAt:e.createdAt,
        managerId:e.managerId, registrations:0,
        time:e.time||'', tags:(e.tags||'').split(',').map(t=>t.trim()).filter(Boolean), organiser:e.organiser||'', trending:false, popular:false , eventMode:e.eventMode
      }));
      renderAll();renderSponsors();renderForms();loadEventFormsFromDB();
    })
    .catch(()=>{ renderAll();renderSponsors();renderForms(); });
}

/* ───── INIT ───── */
renderAll();
loadSponsorsFromDB();
renderForms();
</script>
<script src="assets/js/frontoffice.js"></script>
</body>
</html>
