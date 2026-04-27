<?php
require_once __DIR__ . '/../../Controller/EventsController.php';

$eventC = new EventsController();

// ── Handle flash messages ──────────────────────────────
$flashMsg  = '';
$flashType = 'success';
if (isset($_GET['success'])) {
    $msgs = ['created'=>'Événement créé avec succès ✓','updated'=>'Événement mis à jour ✓','deleted'=>'Événement supprimé ✓'];
    $flashMsg = $msgs[$_GET['success']] ?? 'Opération réussie ✓';
}
if (isset($_GET['error'])) {
    $flashMsg  = "Erreur : ID manquant ou événement introuvable.";
    $flashType = 'error';
}

// ── Load all events from DB ────────────────────────────
$dbEvents = $eventC->listerEvents();

// ── Serialize to JS-safe JSON ──────────────────────────
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
        'registrations' => 0,
        'time'        => '',
        'tags'        => $e->getTags(),
        'organiser'   => $e->getOrganiser(),
        'eventMode'   => $e->getEventMode(),
    ];
}, $dbEvents), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Events — CareerStrand Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/admin-feedback.css" />
    <link rel="stylesheet" href="assets/css/admin.css" />
</head>
<body>

<div class="admin-shell">

  <!-- ══════════════ SIDEBAR ══════════════ -->
  <aside class="admin-sidebar">
    <div class="brand">
      <div class="brand-badge"></div>
      <div>
        <h1>CareerStrand</h1>
        <p>Back Office Console</p>
      </div>
    </div>

    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
      <a class="nav-item" href="admin-dashboard.html">Dashboard <span class="badge">Home</span></a>
      <a class="nav-item" href="admin-users.html">Users <span class="badge">1.2k</span></a>
      <a class="nav-item" href="admin-profiles.html">Profiles <span class="badge">842</span></a>
      <a class="nav-item" href="admin-questions.html">Courses <span class="badge">24</span></a>
      <a class="nav-item" href="admin-skills.html">Challenges <span class="badge">18</span></a>
      <a class="nav-item" href="admin-opportunities.html">Opportunities <span class="badge">36</span></a>
      <a class="nav-item" href="admin-applications.html">Applications <span class="badge">128</span></a>
      <a class="nav-item" href="admin-analytics.html">ADN Analytics <span class="badge live">Live</span></a>
      <a class="nav-item active" href="admin-feedback.php">Events <span class="badge">12</span></a>
      <a class="nav-item" href="admin-settings.html">Settings<span class="badge">New</span></a>
    </nav>
  </aside>

  <!-- ══════════════ MAIN ══════════════ -->
  <main class="admin-main">

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <span class="eyebrow">Events Module</span>
        <h2 style="margin-top:14px">Events</h2>
        <p>Manage workshops, hackathons, bootcamps, and career events that connect users to real engagement outside the learning path.</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-soft" onclick="openModal('participation-create')">＋ Add Participation</button>
        <button class="btn btn-soft" onclick="openSponsorCreate()" >＋ Add Sponsor</button>
        <button class="btn btn-soft" onclick="openFormCreate()">＋ Add Form</button>
        <button class="btn btn-main" onclick="openModal('event-create')">＋ Create event</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-label">Total Events</div>
        <div class="stat-value" id="stat-total">12</div>
        <div class="stat-sub">↑ 3 this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Upcoming</div>
        <div class="stat-value" style="color:var(--blue-2)" id="stat-upcoming">5</div>
        <div class="stat-sub">Next: Portfolio Lab</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Registrations</div>
        <div class="stat-value" style="color:var(--green)" id="stat-reg">347</div>
        <div class="stat-sub">↑ 22% vs last month</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Avg. Attendance</div>
        <div class="stat-value" style="color:var(--yellow)" id="stat-att">72%</div>
        <div class="stat-sub">+8% vs last event</div>
      </div>
      <div class="stat-card" style="display:none" id="stat-card-sponsors">
        <div class="stat-label">Active Sponsors</div>
        <div class="stat-value" style="color:var(--yellow)" id="stat-sponsors">0</div>
        <div class="stat-sub">Total funding</div>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
      <div class="tabs">
        <button class="tab active" onclick="switchTab('events',this)">🗓 Events</button>
        <button class="tab" onclick="switchTab('participations',this)">👥 Participations</button>
        <button class="tab" onclick="switchTab('sponsors',this)">💼 Sponsors</button>
        <button class="tab" onclick="switchTab('forms',this)">📋 Event Forms</button>
      </div>
    </div>

    <!-- ─── EVENTS TABLE ─── -->
    <div id="tab-events">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>All Events</h3>
            <p>Create, update, delete and track all events.</p>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="searchbar" id="search-events-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input id="search-events" type="text" placeholder="Search events…" oninput="filterEvents()" onfocus="this.parentElement.classList.add('is-focused')" onblur="this.parentElement.classList.remove('is-focused')" />
            </div>
            <div class="filters" id="type-filters">
              <button class="filter is-selected" onclick="setTypeFilter('all',this)">All</button>
              <button class="filter" onclick="setTypeFilter('Workshop',this)">Workshop</button>
              <button class="filter" onclick="setTypeFilter('Hackathon',this)">Hackathon</button>
              <button class="filter" onclick="setTypeFilter('Career Event',this)">Career</button>
              <button class="filter" onclick="setTypeFilter('Bootcamp',this)">Bootcamp</button>
            </div>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Event</th>
              <th>Type</th>
              <th>Date</th>
              <th>Capacity</th>
              <th>Registrations</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="events-tbody"></tbody>
        </table>
        <div class="table-foot">
          <span id="events-count">— events</span>
          <div style="display:flex;gap:8px">
            <button class="btn btn-soft btn-sm" onclick="prevPage('events')">← Prev</button>
            <span id="events-page" style="display:flex;align-items:center;font-size:13px;color:var(--muted)">Page 1</span>
            <button class="btn btn-soft btn-sm" onclick="nextPage('events')">Next →</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── PARTICIPATIONS TABLE ─── -->
    <div id="tab-participations" style="display:none">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Participations</h3>
            <p>View, register, update and cancel user event registrations.</p>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="searchbar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input id="search-participations" type="text" placeholder="Search by user or event…" oninput="filterParticipations()" onfocus="this.parentElement.classList.add('is-focused')" onblur="this.parentElement.classList.remove('is-focused')" />
            </div>
            <div class="filters" id="status-filters">
              <button class="filter is-selected" onclick="setPStatusFilter('all',this)">All</button>
              <button class="filter" onclick="setPStatusFilter('Confirmed',this)">Confirmed</button>
              <button class="filter" onclick="setPStatusFilter('Pending',this)">Pending</button>
              <button class="filter" onclick="setPStatusFilter('Cancelled',this)">Cancelled</button>
            </div>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Event</th>
              <th>Registered</th>
              <th>Status</th>
              <th>Rating</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="parts-tbody"></tbody>
        </table>
        <div class="table-foot">
          <span id="parts-count">— participations</span>
          <div style="display:flex;gap:8px">
            <button class="btn btn-soft btn-sm" onclick="prevPage('parts')">← Prev</button>
            <span id="parts-page" style="display:flex;align-items:center;font-size:13px;color:var(--muted)">Page 1</span>
            <button class="btn btn-soft btn-sm" onclick="nextPage('parts')">Next →</button>
          </div>
        </div>
      </div>
    </div>


    <!-- ─── SPONSORS TABLE ─── -->
     <div id="tab-sponsors" style="display:none">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Sponsors</h3>
            <p>Manage event sponsors, their contributions and funding amounts.</p>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="searchbar" id="search-sponsors-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input id="search-sponsors" type="text" placeholder="Search sponsors…" oninput="filterSponsors()" onfocus="this.parentElement.classList.add('is-focused')" onblur="this.parentElement.classList.remove('is-focused')" />
            </div>
            <div class="filters" id="sponsor-status-filters">
              <button class="filter is-selected" onclick="setSponsorFilter('all',this)">All</button>
              <button class="filter" onclick="setSponsorFilter('desc',this)">↓ Amount</button>
              <button class="filter" onclick="setSponsorFilter('asc',this)">↑ Amount</button>
            </div>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Sponsor</th>
              <th>Company</th>
              <th>Event</th>
              <th>Contribution</th>
              <th>Amount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="sponsors-tbody"></tbody>
        </table>
        <div class="table-foot">
          <span id="sponsors-count">— sponsors</span>
        </div>
      </div>
    </div>

    <!-- ─── EVENT FORMS TABLE ─── -->
    <div id="tab-forms" style="display:none">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Event Forms</h3>
            <p>Manage registration and application forms linked to events.</p>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="searchbar" id="search-forms-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--muted-2);flex-shrink:0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input id="search-forms" type="text" placeholder="Search forms…" oninput="filterForms()" onfocus="this.parentElement.classList.add('is-focused')" onblur="this.parentElement.classList.remove('is-focused')" />
            </div>
            <div class="filters" id="form-status-filters">
              <button class="filter is-selected" onclick="setFormFilter('all',this)">All</button>
              <button class="filter" onclick="setFormFilter('open',this)">Open</button>
              <button class="filter" onclick="setFormFilter('closed',this)">Closed</button>
              <button class="filter" onclick="setFormFilter('draft',this)">Draft</button>
            </div>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Event</th>
              <th>Description</th>
              <th>Form Link</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="forms-tbody"></tbody>
        </table>
        <div class="table-foot">
          <span id="forms-count">— forms</span>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- ══════════ MODALS ══════════ -->

<!-- ── CREATE / EDIT EVENT ── -->
<div class="modal-backdrop" id="modal-event-form">
  <div class="modal">
    <div class="modal-header">
      <h3 id="event-form-title">Create Event</h3>
      <button class="modal-close" onclick="closeModal('event-form')">✕</button>
    </div>
    <div class="field-grid">
      <div class="field">
        <label>Event Title *</label>
        <input id="ef-title" type="text" placeholder="e.g. Portfolio Lab Live" />
      </div>
      <div class="field-row">
        <div class="field">
          <label>Type *</label>
          <select id="ef-type">
            <option>Workshop</option>
            <option>Hackathon</option>
            <option>Career Event</option>
            <option>Bootcamp</option>
          </select>
        </div>
        <div class="field">
          <label>Status</label>
          <select id="ef-status">
            <option>Upcoming</option>
            <option>Live</option>
            <option>Past</option>
            <option>Cancelled</option>
          </select>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Date *</label>
          <input id="ef-date" type="date" />
        </div>
        <div class="field">
          <label>Time</label>
          <input id="ef-time" type="time" />
        </div>
      </div>
      <div class="field">
    <label>Event Mode *</label>
    <div style="display:flex;gap:16px;margin-top:6px">
        <label style="display:flex;align-items:center;gap:6px">
            <input type="radio" name="event-mode" value="Online" checked> Online
        </label>
        <label style="display:flex;align-items:center;gap:6px">
            <input type="radio" name="event-mode" value="In-person"> In‑person
        </label>
    </div>
</div>
      <div class="field-row">
        <div class="field">
          <label>Location / Platform</label>
          <input id="ef-location" type="text" placeholder="Online or venue name" />
        </div>
        <div class="field">
          <label>Capacity</label>
          <input id="ef-capacity" type="number" min="1" placeholder="e.g. 100" />
        </div>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea id="ef-desc" placeholder="Short description of the event…"></textarea>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Tags </label>
          <input id="ef-tags" type="text" placeholder="design, web, no-code" />
        </div>
        <div class="field">
          <label>Organiser</label>
          <input id="ef-organiser" type="text" placeholder="Name or team" />
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Manager ID </label>
          <input id="ef-manager" type="number" placeholder="User ID of manager" />
        </div>
        <div class="field">
          <label>Sponsor ID *</label>
          <input id="ef-sponsor" type="number" min="1" placeholder="e.g. 1" />
        </div>
        <div class="field">
          <label>Duration (minutes) *</label>
          <input id="ef-duration" type="number" min="1" placeholder="e.g. 60" />
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-soft" onclick="closeModal('event-form')">Cancel</button>
      <button class="btn btn-main" onclick="saveEvent()">Save Event</button>
    </div>
  </div>
</div>

<!-- ── VIEW EVENT ── -->
<div class="modal-backdrop" id="modal-event-view">
  <div class="modal">
    <div class="modal-header">
      <h3 id="ev-view-title">Event Details</h3>
      <button class="modal-close" onclick="closeModal('event-view')">✕</button>
    </div>
    <div id="ev-view-body"></div>
    <div class="modal-footer">
      <button class="btn btn-soft" onclick="closeModal('event-view')">Close</button>
      <button class="btn btn-main" id="ev-view-edit-btn">Edit Event</button>
    </div>
  </div>
</div>

<!-- ── CREATE / EDIT PARTICIPATION ── -->
<div class="modal-backdrop" id="modal-participation-form">
  <div class="modal">
    <div class="modal-header">
      <h3 id="part-form-title">Register Participation</h3>
      <button class="modal-close" onclick="closeModal('participation-form')">✕</button>
    </div>
    <div class="field-grid">
      <div class="field-row">
        <div class="field">
          <label>User Name *</label>
          <input id="pf-user" type="text" placeholder="e.g. Amira Bensalem" />
        </div>
        <div class="field">
          <label>User Email</label>
          <input id="pf-email" type="email" placeholder="user@example.com" />
        </div>
      </div>
      <div class="field">
        <label>Event ID *</label>
<input id="pf-event" type="number" min="1" placeholder="e.g. 3" />
      </div>
      <div class="field-row">
        <div class="field">
          <label>Registration Date</label>
          <input id="pf-regdate" type="date" />
        </div>
        <div class="field">
          <label>Attendance Status</label>
          <select id="pf-attendance">
            <option>Confirmed</option>
            <option>Pending</option>
            <option>Cancelled</option>
          </select>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Status</label>
          <select id="pf-status">
            <option>Confirmed</option>
            <option>Pending</option>
            <option>Cancelled</option>
          </select>
        </div>
        <div class="field">
          <label>Rating (1-5)</label>
          <input id="pf-rating" type="number" min="1" max="5" placeholder="e.g. 4" />
        </div>
      </div>
      <div class="field">
        <label>Feedback</label>
        <textarea id="pf-feedback" placeholder="User feedback or notes…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-soft" onclick="closeModal('participation-form')">Cancel</button>
      <button class="btn btn-main" onclick="saveParticipation()">Save</button>
    </div>
  </div>
</div>


<!-- ── CREATE / EDIT SPONSOR ── -->
<div class="modal-backdrop" id="modal-sponsor-form">
  <div class="modal">
    <div class="modal-header">
      <h3 id="sponsor-form-title">Add Sponsor</h3>
      <button class="modal-close" onclick="closeModal('sponsor-form')">✕</button>
    </div>
    <div class="field-grid">
      <div class="field-row">
        <div class="field">
          <label>Sponsor Name *</label>
          <input id="sf-name" type="text" placeholder="e.g. John Smith" />
        </div>
        <div class="field">
          <label>Company *</label>
          <input id="sf-company" type="text" placeholder="e.g. TechCorp" />
        </div>
      </div>
      <div class="field">
        <label>Email</label>
        <input id="sf-email" type="email" placeholder="sponsor@company.com" />
      </div>
      <div class="field">
        <label>Contribution (description)</label>
        <input id="sf-contribution" type="text" placeholder="e.g. Provides venue + meals" />
      </div>
      <div class="field-row">
        <div class="field">
          <label>Amount (TND)</label>
          <input id="sf-amount" type="number" min="0" step="0.01" placeholder="e.g. 5000" />
        </div>
        <div class="field">
          <label>User ID </label>
          <input id="sf-user" type="number" min="1" placeholder="e.g. 1" />
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-soft" onclick="closeModal('sponsor-form')">Cancel</button>
      <button class="btn btn-main" onclick="saveSponsor()">Save Sponsor</button>
    </div>
  </div>
</div>

<!-- ── CREATE / EDIT EVENT FORM ── -->
<div class="modal-backdrop" id="modal-eventform-form">
  <div class="modal">
    <div class="modal-header">
      <h3 id="eventform-form-title">Add Event Form</h3>
      <button class="modal-close" onclick="closeModal('eventform-form')">✕</button>
    </div>
    <div class="field-grid">
      <div class="field">
        <label>Form Title *</label>
        <input id="ff-title" type="text" placeholder="e.g. Registration Form – Portfolio Lab" />
      </div>
      <div class="field">
        <label>Event ID *</label>
       <input id="ff-event" type="number" min="1" placeholder="e.g. 3" />
      </div>
      <div class="field">
        <label>Description</label>
        <textarea id="ff-desc" placeholder="What is this form for?"></textarea>
      </div>
      <div class="field">
        <label>Form Link (URL) *</label>
        <input id="ff-link" type="url" placeholder="https://forms.google.com/…" />
      </div>
      <div class="field">
        <label>Status</label>
        <select id="ff-status">
          <option value="open">Open</option>
          <option value="closed">Closed</option>
          <option value="draft">Draft</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-soft" onclick="closeModal('eventform-form')">Cancel</button>
      <button class="btn btn-main" onclick="saveEventForm()">Save Form</button>
    </div>
  </div>
</div>

<!-- ── DELETE CONFIRM ── -->
<div class="modal-backdrop" id="modal-confirm">
  <div class="modal confirm-modal">
    <div class="confirm-icon">🗑</div>
    <h3 id="confirm-title">Delete this item?</h3>
    <p id="confirm-body">This action cannot be undone.</p>
    <div class="modal-footer" style="justify-content:center;margin-top:20px">
      <button class="btn btn-soft" onclick="closeModal('confirm')">Cancel</button>
      <button class="btn btn-danger" id="confirm-ok-btn">Yes, Delete</button>
    </div>
  </div>
</div>

<!-- ── TOAST ── -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- ══════════ SCRIPT ══════════ -->
<script src="assets/js/admin-feedback.js"></script>
<script src="assets/js/admin.js"></script>
</body>
</html>