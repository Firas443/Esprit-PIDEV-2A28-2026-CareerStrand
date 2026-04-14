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
  <style>
    /* ── TOKENS ─────────────────────────────────────────────── */
    :root {
      --bg: #040816; --bg-2: #071126; --bg-3: #0d1631;
      --panel: rgba(65,100,184,.12); --panel-2: rgba(65,100,184,.08);
      --panel-3: rgba(255,110,69,.08);
      --border: rgba(126,159,228,.18); --border-soft: rgba(126,159,228,.1);
      --text: #f5f3ee; --muted: rgba(245,243,238,.72); --muted-2: rgba(245,243,238,.46);
      --blue: #6f8fd8; --blue-2: #95abeb;
      --red: #ff6e45; --red-2: #ff8564;
      --green: #59d39b; --yellow: #f5bf65; --white: #f5f3ee;
      --shadow: 0 30px 80px rgba(0,0,0,.35);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{
      min-height:100vh;font-family:Inter,Arial,sans-serif;color:var(--text);
      background:
        radial-gradient(circle at top left,rgba(111,143,216,.14),transparent 24%),
        radial-gradient(circle at 88% 12%,rgba(255,110,69,.11),transparent 24%),
        radial-gradient(circle at 50% 100%,rgba(111,143,216,.08),transparent 30%),
        linear-gradient(135deg,#02050f 0%,#071126 42%,#0b1022 100%);
    }
    a{color:inherit;text-decoration:none}
    button,input,select,textarea{font:inherit}

    /* ── LAYOUT ─────────────────────────────────────────────── */
    .admin-shell{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:100vh}

    /* ── SIDEBAR ─────────────────────────────────────────────── */
    .admin-sidebar{
      position:sticky;top:0;height:100vh;padding:28px 22px;
      background:linear-gradient(180deg,rgba(11,19,43,.98),rgba(6,11,25,.96));
      border-right:1px solid rgba(126,159,228,.16);
      backdrop-filter:blur(22px);overflow-y:auto;
    }
    .brand{display:flex;align-items:center;gap:14px;padding:8px 8px 24px}
    .brand-badge{
      width:48px;height:48px;border-radius:18px;position:relative;flex-shrink:0;
      background:linear-gradient(135deg,rgba(111,143,216,.22),rgba(255,110,69,.18));
      border:1px solid rgba(126,159,228,.2);box-shadow:0 12px 32px rgba(0,0,0,.28);
    }
    .brand-badge::before,.brand-badge::after{
      content:"";position:absolute;top:8px;left:16px;width:14px;height:30px;
      border-radius:50%;border:2px solid var(--blue-2);
    }
    .brand-badge::before{transform:rotate(28deg)}
    .brand-badge::after{transform:rotate(-28deg);border-color:var(--red)}
    .brand h1{font-size:20px;letter-spacing:-.03em}
    .brand p{margin-top:4px;color:var(--muted-2);font-size:11px;text-transform:uppercase;letter-spacing:.28em}
    .side-label{padding:0 10px 10px;color:var(--muted-2);font-size:11px;text-transform:uppercase;letter-spacing:.24em}
    .nav-list{display:grid;gap:8px}
    .nav-item{
      display:flex;align-items:center;justify-content:space-between;gap:12px;
      padding:14px 16px;border-radius:18px;border:1px solid transparent;
      color:var(--muted);transition:.22s ease;cursor:pointer;
    }
    .nav-item:hover,.nav-item.active{
      color:var(--white);
      background:linear-gradient(135deg,rgba(111,143,216,.16),rgba(111,143,216,.08));
      border-color:rgba(126,159,228,.18);
    }
    .nav-item span.badge{
      min-width:30px;text-align:center;padding:4px 8px;border-radius:999px;
      background:rgba(255,110,69,.14);color:var(--red);font-size:12px;font-weight:700;
    }
    .nav-item span.badge.live{background:rgba(89,211,155,.14);color:var(--green)}

    /* ── MAIN ────────────────────────────────────────────────── */
    .admin-main{padding:28px;display:grid;gap:24px}

    /* ── PAGE HEADER ─────────────────────────────────────────── */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:center}
    .page-header h2{font-size:clamp(30px,4vw,42px);letter-spacing:-.05em}
    .page-header p{margin-top:8px;color:var(--muted);max-width:760px;line-height:1.75}
    .header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}

    /* ── TABS ────────────────────────────────────────────────── */
    .tabs{display:flex;gap:8px;background:rgba(111,143,216,.06);border:1px solid var(--border-soft);border-radius:20px;padding:6px}
    .tab{padding:10px 22px;border-radius:14px;border:none;background:transparent;color:var(--muted);font-size:14px;font-weight:600;cursor:pointer;transition:.2s ease}
    .tab.active{background:linear-gradient(135deg,rgba(111,143,216,.22),rgba(255,110,69,.12));color:var(--white);border:1px solid var(--border)}

    /* ── BUTTONS ─────────────────────────────────────────────── */
    .btn{border:none;border-radius:999px;padding:13px 20px;cursor:pointer;transition:.22s ease;font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:8px}
    .btn:hover{transform:translateY(-1px)}
    .btn-main{background:linear-gradient(90deg,var(--blue),var(--red));color:var(--white);font-weight:700;box-shadow:0 16px 36px rgba(255,110,69,.18)}
    .btn-soft{color:var(--white);background:rgba(111,143,216,.12);border:1px solid rgba(126,159,228,.16)}
    .btn-danger{background:rgba(255,110,69,.15);color:var(--red);border:1px solid rgba(255,110,69,.22)}
    .btn-success{background:rgba(89,211,155,.12);color:var(--green);border:1px solid rgba(89,211,155,.2)}
    .btn-sm{padding:8px 14px;font-size:12px}

    /* ── SEARCH ──────────────────────────────────────────────── */
    .searchbar{
      display:flex;align-items:center;gap:12px;min-width:min(360px,100%);
      padding:13px 18px;border-radius:22px;background:rgba(8,15,33,.75);
      border:1px solid rgba(126,159,228,.14);transition:border-color .2s,box-shadow .2s;
    }
    .searchbar.is-focused{border-color:rgba(255,110,69,.35);box-shadow:0 0 0 4px rgba(255,110,69,.08)}
    .searchbar input{width:100%;border:none;outline:none;background:transparent;color:var(--white)}
    .searchbar input::placeholder{color:var(--muted-2)}

    /* ── FILTERS ─────────────────────────────────────────────── */
    .filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .filter{
      display:inline-flex;align-items:center;justify-content:center;
      padding:8px 14px;border-radius:999px;font-size:12px;font-weight:600;
      background:rgba(111,143,216,.1);border:1px solid rgba(126,159,228,.12);
      color:var(--blue-2);cursor:pointer;transition:.2s ease;
    }
    .filter.is-selected{background:rgba(255,110,69,.12);border-color:rgba(255,110,69,.35);color:var(--red)}

    /* ── PANELS ──────────────────────────────────────────────── */
    .panel{
      border-radius:30px;padding:24px;
      background:linear-gradient(180deg,rgba(12,22,46,.92),rgba(7,13,29,.94));
      border:1px solid rgba(126,159,228,.16);box-shadow:0 22px 60px rgba(0,0,0,.24);
    }
    .panel-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap}
    .panel-title h3{font-size:20px;letter-spacing:-.03em}
    .panel-title p{margin-top:5px;color:var(--muted);font-size:13px}

    /* ── STATS ROW ───────────────────────────────────────────── */
    .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
    .stat-card{
      padding:22px;border-radius:24px;
      background:linear-gradient(180deg,rgba(12,22,46,.92),rgba(7,13,29,.94));
      border:1px solid rgba(126,159,228,.14);
    }
    .stat-label{color:var(--muted-2);font-size:11px;text-transform:uppercase;letter-spacing:.2em}
    .stat-value{margin-top:10px;font-size:34px;font-weight:800;letter-spacing:-.04em}
    .stat-sub{margin-top:6px;color:var(--muted);font-size:12px}

    /* ── TABLE ───────────────────────────────────────────────── */
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;padding:14px 10px;border-bottom:1px solid rgba(126,159,228,.1);vertical-align:middle}
    th{color:var(--muted-2);font-size:11px;text-transform:uppercase;letter-spacing:.2em;font-weight:600}
    td{color:var(--muted);font-size:14px}
    td strong{color:var(--white);font-size:15px}
    tr:hover td{background:rgba(111,143,216,.04)}
    .table-actions{display:flex;gap:8px;align-items:center}
    .table-foot{margin-top:16px;display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:13px;flex-wrap:wrap;gap:12px}

    /* ── STATUS CHIPS ────────────────────────────────────────── */
    .chip{display:inline-flex;align-items:center;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700}
    .chip-green{background:rgba(89,211,155,.12);color:var(--green)}
    .chip-yellow{background:rgba(245,191,101,.13);color:var(--yellow)}
    .chip-red{background:rgba(255,110,69,.12);color:var(--red)}
    .chip-blue{background:rgba(111,143,216,.14);color:var(--blue-2)}

    /* ── TYPE BADGE ──────────────────────────────────────────── */
    .type-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;background:rgba(111,143,216,.1);border:1px solid rgba(126,159,228,.15);color:var(--blue-2)}

    /* ── MODAL BACKDROP ──────────────────────────────────────── */
    .modal-backdrop{
      position:fixed;inset:0;background:rgba(2,5,15,.82);backdrop-filter:blur(8px);
      z-index:1000;display:flex;align-items:center;justify-content:center;padding:24px;
      opacity:0;pointer-events:none;transition:opacity .25s ease;
    }
    .modal-backdrop.open{opacity:1;pointer-events:all}
    .modal{
      width:100%;max-width:640px;max-height:92vh;overflow-y:auto;
      background:linear-gradient(180deg,rgba(10,18,40,.98),rgba(6,12,26,.99));
      border:1px solid rgba(126,159,228,.2);border-radius:32px;padding:32px;
      transform:translateY(20px) scale(.97);transition:transform .28s cubic-bezier(.34,1.56,.64,1);
      box-shadow:0 40px 120px rgba(0,0,0,.55);
    }
    .modal-backdrop.open .modal{transform:translateY(0) scale(1)}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
    .modal-header h3{font-size:22px;letter-spacing:-.03em}
    .modal-close{background:rgba(255,255,255,.07);border:1px solid rgba(126,159,228,.14);border-radius:50%;width:36px;height:36px;display:grid;place-items:center;cursor:pointer;color:var(--muted);font-size:18px;transition:.2s}
    .modal-close:hover{background:rgba(255,110,69,.14);color:var(--red);border-color:rgba(255,110,69,.25)}
    .modal-footer{margin-top:24px;display:flex;gap:12px;justify-content:flex-end}

    /* ── FORM ────────────────────────────────────────────────── */
    .field-grid{display:grid;gap:16px}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .field{display:grid;gap:8px}
    .field label{color:var(--muted-2);font-size:11px;text-transform:uppercase;letter-spacing:.18em}
    .field input,.field select,.field textarea{
      border:1px solid rgba(126,159,228,.14);border-radius:16px;
      padding:13px 16px;background:rgba(111,143,216,.08);color:var(--white);outline:none;
      transition:border-color .2s,box-shadow .2s;
    }
    .field input:focus,.field select,.field textarea:focus{
      border-color:rgba(111,143,216,.4);box-shadow:0 0 0 3px rgba(111,143,216,.1);
    }
    .field select option{background:#0d1631;color:var(--white)}
    .field textarea{min-height:100px;resize:vertical}

    /* ── CONFIRM MODAL ───────────────────────────────────────── */
    .confirm-modal{max-width:420px;text-align:center}
    .confirm-icon{width:64px;height:64px;border-radius:50%;background:rgba(255,110,69,.12);border:1px solid rgba(255,110,69,.22);display:grid;place-items:center;font-size:28px;margin:0 auto 18px}
    .confirm-modal h3{font-size:22px;letter-spacing:-.03em;margin-bottom:10px}
    .confirm-modal p{color:var(--muted);line-height:1.7;margin-bottom:4px}

    /* ── TOAST ───────────────────────────────────────────────── */
    .toast-wrap{position:fixed;bottom:28px;right:28px;z-index:2000;display:grid;gap:12px}
    .toast{
      padding:14px 22px;border-radius:20px;font-size:14px;font-weight:600;
      display:flex;align-items:center;gap:12px;min-width:280px;
      background:linear-gradient(135deg,rgba(10,20,44,.98),rgba(7,13,28,.99));
      border:1px solid rgba(126,159,228,.18);box-shadow:0 16px 48px rgba(0,0,0,.42);
      animation:slideIn .3s cubic-bezier(.34,1.56,.64,1) forwards;
    }
    @keyframes slideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
    .toast.success .toast-dot{background:var(--green)}
    .toast.error .toast-dot{background:var(--red)}
    .toast-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}

    /* ── EMPTY STATE ─────────────────────────────────────────── */
    .empty{padding:60px 20px;text-align:center;color:var(--muted)}
    .empty-icon{font-size:40px;margin-bottom:16px}
    .empty h4{font-size:18px;color:var(--white);margin-bottom:8px}

    /* ── PROGRESS ────────────────────────────────────────────── */
    .progress-bar{height:6px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden}
    .progress-fill{height:100%;border-radius:999px;background:linear-gradient(to right,var(--blue),var(--red))}

    /* ── DETAIL PANEL ────────────────────────────────────────── */
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
    .info-item{padding:14px 16px;border-radius:16px;background:rgba(111,143,216,.07);border:1px solid rgba(126,159,228,.1)}
    .info-item .lbl{color:var(--muted-2);font-size:11px;text-transform:uppercase;letter-spacing:.16em;margin-bottom:6px}
    .info-item .val{color:var(--white);font-size:14px;font-weight:600}

    /* ── RESPONSIVE ──────────────────────────────────────────── */
    @media(max-width:1200px){.stat-row{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:980px){
      .admin-shell{grid-template-columns:1fr}
      .admin-sidebar{position:static;height:auto}
      .admin-main{padding:18px}
    }
    @media(max-width:720px){
      .stat-row,.field-row{grid-template-columns:1fr}
      .page-header,.panel-header{flex-direction:column;align-items:flex-start}
      .searchbar{min-width:100%}
    }

    /* ── SECTION DIVIDER ─────────────────────────────────────── */
    .section-gap{margin-top:8px}
    .eyebrow{
      display:inline-flex;align-items:center;gap:10px;padding:10px 14px;border-radius:999px;
      background:rgba(111,143,216,.12);border:1px solid rgba(126,159,228,.14);
      color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.24em;
    }
    .eyebrow::before{content:"";width:10px;height:10px;border-radius:50%;background:linear-gradient(90deg,var(--blue),var(--red));box-shadow:0 0 18px rgba(255,110,69,.38)}
  </style>
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
      <a class="nav-item active" href="admin-feedback.html">Events <span class="badge">12</span></a>
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
        <button class="btn btn-soft" onclick="openModal('participation-create')" style="border:1px solid rgba(126,159,228,.35);color:var(--blue-2)">＋ Add Participation</button>
        <button class="btn btn-soft" onclick="openSponsorCreate()" style="border:1px solid rgba(245,191,101,.35);color:var(--yellow)">💼 Add Sponsor</button>
        <button class="btn btn-soft" onclick="openFormCreate()" style="border:1px solid rgba(89,211,155,.35);color:var(--green)">📋 Add Form</button>
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
              <button class="filter" onclick="setSponsorFilter('active',this)">Active</button>
              <button class="filter" onclick="setSponsorFilter('inactive',this)">Inactive</button>
            </div>
            <button class="btn btn-main btn-sm" onclick="openSponsorCreate()">＋ Add Sponsor</button>
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
              <th>Status</th>
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
            <button class="btn btn-main btn-sm" onclick="openFormCreate()">＋ Add Form</button>
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
      <div class="field-row">
        <div class="field">
          <label>Event ID <span style="color:var(--muted-2);font-size:11px">(manuel — laisser vide = auto)</span></label>
          <input id="ef-eventid" type="number" min="1" placeholder="e.g. 10" />
        </div>
        <div class="field">
          <label>Event Title *</label>
          <input id="ef-title" type="text" placeholder="e.g. Portfolio Lab Live" />
        </div>
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
          <label>Tags (comma-separated)</label>
          <input id="ef-tags" type="text" placeholder="design, web, no-code" />
        </div>
        <div class="field">
          <label>Organiser</label>
          <input id="ef-organiser" type="text" placeholder="Name or team" />
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Manager ID (optional)</label>
          <input id="ef-manager" type="number" placeholder="User ID of manager" />
        </div>
        <div class="field">
          <label>Created At</label>
          <input id="ef-createdat" type="datetime-local" />
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
        <label>Event *</label>
        <select id="pf-event"></select>
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
        <label>Event *</label>
        <select id="sf-event"></select>
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
          <label>Status</label>
          <select id="sf-status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
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
        <label>Event *</label>
        <select id="ff-event"></select>
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
<script>
/* ─── DATA ─── */
// ── Events loaded from MySQL via PHP ──────────────────
let events = <?php echo $eventsJson; ?>;
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
  if(name==='sponsors') renderSponsors();
  if(name==='forms') renderEventForms();
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
          managerId:e.managerId,registrations:0,time:'',tags:e.tags||'',organiser:e.organiser||''
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
  $('stat-sponsors').textContent=sponsors.filter(s=>s.status==='active').length;
}

/* ─── MODALS ─── */
function openModal(name){$('modal-'+name).classList.add('open')}
function closeModal(name){$('modal-'+name).classList.remove('open')}

/* ─── EVENT CRUD ─── */
function openEventCreate(){
  editingEventId=null;
  $('event-form-title').textContent='Create Event';
  ['ef-eventid','ef-title','ef-location','ef-capacity','ef-desc','ef-tags','ef-organiser','ef-date','ef-time','ef-manager','ef-createdat'].forEach(id=>$(id)&&($(id).value=''));
  $('ef-type').value='Workshop';
  $('ef-status').value='Upcoming';
  if($('ef-eventid')) $('ef-eventid').readOnly=false;
  openModal('event-form');
}

function editEvent(id){
  const e=getEvent(id); if(!e)return;
  editingEventId=id;
  $('event-form-title').textContent='Edit Event';
  $('ef-eventid').value=e.id;
  $('ef-eventid').readOnly=true;
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
  $('ef-createdat').value='';
  openModal('event-form');
}

function saveEvent(){
  const title=$('ef-title').value.trim();
  if(!title){toast('Titre obligatoire','error');return}
  const dateVal=$('ef-date').value;
  if(!dateVal){toast('Date obligatoire','error');return}

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
  fd.append('managerId',   $('ef-manager').value||'');
  fd.append('createdAt',   $('ef-createdat').value||'');
  const manualId=($('ef-eventid')||{value:''}).value.trim();
  if(manualId && !editingEventId) fd.append('eventId', manualId);

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
    <p style="color:var(--muted);line-height:1.75;margin-bottom:16px">${e.description||'No description.'}</p>
    <div class="info-grid">
      <div class="info-item"><div class="lbl">Date & Time</div><div class="val">${e.date} at ${e.time||'—'}</div></div>
      <div class="info-item"><div class="lbl">Location</div><div class="val">${e.location}</div></div>
      <div class="info-item"><div class="lbl">Capacity</div><div class="val">${e.registrations} / ${e.capacity} (${p}%)</div></div>
      <div class="info-item"><div class="lbl">Organiser</div><div class="val">${e.organiser||'—'}</div></div>
      <div class="info-item"><div class="lbl">Manager ID</div><div class="val">${e.managerId||'—'}</div></div>
      <div class="info-item"><div class="lbl">Created At</div><div class="val">${e.createdAt||'—'}</div></div>
    </div>
    <div style="margin-top:14px">
      <div class="progress-bar"><div class="progress-fill" style="width:${p}%"></div></div>
      <div style="margin-top:6px;font-size:12px;color:var(--muted-2)">${p}% filled</div>
    </div>
    ${e.tags?`<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">${e.tags.split(',').map(t=>`<span class="type-badge">${t.trim()}</span>`).join('')}</div>`:''}
  `;
  $('ev-view-edit-btn').onclick=()=>{closeModal('event-view');editEvent(id)};
  openModal('event-view');
}

/* ─── PARTICIPATION CRUD ─── */
function refreshEventSelect(){
  const opts=events.map(e=>`<option value="${e.id}">${e.title}</option>`).join('');
  ['pf-event','sf-event','ff-event'].forEach(id=>{const el=$(id);if(el)el.innerHTML=opts;});
}

function openPartCreate(){
  editingPartId=null;
  $('part-form-title').textContent='Register Participation';
  $('pf-user').value='';$('pf-email').value='';
  $('pf-regdate').value='';$('pf-attendance').value='Confirmed';
  $('pf-status').value='Confirmed';$('pf-rating').value='';$('pf-feedback').value='';
  refreshEventSelect();
  openModal('participation-form');
}

function editParticipation(id){
  const p=participations.find(x=>x.id===id); if(!p)return;
  editingPartId=id;
  $('part-form-title').textContent='Edit Participation';
  $('pf-user').value=p.user;$('pf-email').value=p.email;
  refreshEventSelect();
  $('pf-event').value=p.eventId;
  $('pf-regdate').value=p.registeredAt||'';
  $('pf-attendance').value=p.attendanceStatus||p.status||'Pending';
  $('pf-status').value=p.status;
  $('pf-rating').value=p.rating||'';
  $('pf-feedback').value=p.feedback||'';
  openModal('participation-form');
}

function saveParticipation(){
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
let sponsorFilter = 'all';
let sponsorSearch = '';
let formFilter = 'all';
let formSearch = '';

/* ─── SPONSORS DATA ─── */
let sponsors = [
  {id:1,eventId:1,name:"Khalil Mansour",company:"TechTunis",email:"khalil@techtunis.tn",contribution:"Venue + Equipment",amount:5000,status:"active"},
  {id:2,eventId:2,name:"Sara Belhaj",company:"StartupHub",email:"sara@startuphub.tn",contribution:"Meals + Prizes",amount:3000,status:"active"},
  {id:3,eventId:5,name:"Amine Ferjani",company:"AILabs",email:"amine@ailabs.io",contribution:"Software licenses",amount:1500,status:"inactive"},
];
let nextSid = 4;
let editingSponsorId = null;

/* ─── EVENT FORMS DATA ─── */
let eventForms = [
  {id:1,eventId:1,title:"Registration Form – Portfolio Lab",description:"Sign up to secure your spot.",formLink:"https://forms.google.com/portfolio-lab",status:"open"},
  {id:2,eventId:2,title:"Team Registration – Build Sprint",description:"Register your team of 2-4 people.",formLink:"https://forms.google.com/build-sprint",status:"open"},
  {id:3,eventId:3,title:"Feedback Form – Talent Connect",description:"Share your experience.",formLink:"https://forms.google.com/talent-feedback",status:"closed"},
];
let nextFid = 4;
let editingFormId = null;

/* ─── SPONSORS RENDER ─── */
function getFilteredSponsors(){
  return sponsors.filter(s=>{
    if(sponsorFilter!=='all'&&s.status!==sponsorFilter) return false;
    const q=sponsorSearch.toLowerCase();
    if(q&&!s.name.toLowerCase().includes(q)&&!s.company.toLowerCase().includes(q)&&!s.email.toLowerCase().includes(q)) return false;
    return true;
  });
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
    $('sponsors-tbody').innerHTML=`<tr><td colspan="7"><div class="empty"><div class="empty-icon">💼</div><h4>No sponsors found</h4></div></td></tr>`;
    updateStats();return;
  }
  $('sponsors-tbody').innerHTML=list.map(s=>{
    const ev=getEvent(s.eventId);
    const statusChipSp=s.status==='active'?'<span class="chip chip-green">Active</span>':'<span class="chip chip-red" style="background:rgba(255,110,69,.12);color:var(--red)">Inactive</span>';
    return `<tr>
      <td><strong>${s.name}</strong><br><span style="font-size:11px;color:var(--muted-2)">${s.email}</span></td>
      <td>${s.company}</td>
      <td>${ev?ev.title:'—'}</td>
      <td>${s.contribution}</td>
      <td><strong style="color:var(--yellow)">${Number(s.amount).toLocaleString()} TND</strong></td>
      <td>${statusChipSp}</td>
      <td>
        <div class="table-actions">
          <button class="btn btn-soft btn-sm" onclick="editSponsor(${s.id})">✏️</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('sponsor',${s.id},'${s.name.replace(/'/g,"\'")}')">🗑</button>
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
  $('sf-status').value='active';
  refreshEventSelect();
  openModal('sponsor-form');
}
function editSponsor(id){
  const s=sponsors.find(x=>x.id===id);if(!s)return;
  editingSponsorId=id;
  $('sponsor-form-title').textContent='Edit Sponsor';
  $('sf-name').value=s.name;$('sf-company').value=s.company;$('sf-email').value=s.email;
  $('sf-contribution').value=s.contribution;$('sf-amount').value=s.amount;$('sf-status').value=s.status;
  refreshEventSelect();$('sf-event').value=s.eventId;
  openModal('sponsor-form');
}
function saveSponsor(){
  const name=$('sf-name').value.trim(),company=$('sf-company').value.trim();
  if(!name||!company){toast('Name and company are required','error');return}
  const data={name,company,email:$('sf-email').value,eventId:parseInt($('sf-event').value),
    contribution:$('sf-contribution').value,amount:parseFloat($('sf-amount').value)||0,status:$('sf-status').value};
  if(editingSponsorId){
    Object.assign(sponsors.find(x=>x.id===editingSponsorId),data);
    toast('Sponsor updated ✓','success');
  }else{
    sponsors.push({id:nextSid++,...data});
    toast('Sponsor added ✓','success');
  }
  editingSponsorId=null;closeModal('sponsor-form');renderSponsors();
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
          <button class="btn btn-soft btn-sm" onclick="editEventForm(${f.id})">✏️</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete('form',${f.id},'${f.title.replace(/'/g,"\'")}')">🗑</button>
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
  refreshEventSelect();
  openModal('eventform-form');
}
function editEventForm(id){
  const f=eventForms.find(x=>x.id===id);if(!f)return;
  editingFormId=id;
  $('eventform-form-title').textContent='Edit Form';
  $('ff-title').value=f.title;$('ff-desc').value=f.description;
  $('ff-link').value=f.formLink;$('ff-status').value=f.status;
  refreshEventSelect();$('ff-event').value=f.eventId;
  openModal('eventform-form');
}
function saveEventForm(){
  const title=$('ff-title').value.trim(),link=$('ff-link').value.trim();
  if(!title||!link){toast('Title and link are required','error');return}
  const data={title,eventId:parseInt($('ff-event').value),description:$('ff-desc').value,formLink:link,status:$('ff-status').value};
  if(editingFormId){
    Object.assign(eventForms.find(x=>x.id===editingFormId),data);
    toast('Form updated ✓','success');
  }else{
    eventForms.push({id:nextFid++,...data});
    toast('Form added ✓','success');
  }
  editingFormId=null;closeModal('eventform-form');renderEventForms();
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
    sponsors=sponsors.filter(s=>s.id!==id);
    renderSponsors();
    toast('Sponsor deleted','success');
  }else if(type==='form'){
    eventForms=eventForms.filter(f=>f.id!==id);
    renderEventForms();
    toast('Form deleted','success');
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
        managerId:e.managerId,registrations:0,time:'',tags:e.tags||'',organiser:e.organiser||''
      }));
      renderEvents();refreshEventSelect();updateStats();
    })
    .catch(err=>console.error('loadEventsFromDB:',err));
}

/* ─── INIT ─── */
loadEventsFromDB();
renderParticipations();
updateStats();

/* ─── FLASH MESSAGE (from PHP redirect) ─── */
<?php if($flashMsg): ?>
  document.addEventListener('DOMContentLoaded',function(){
    toast(<?php echo json_encode($flashMsg); ?>, <?php echo json_encode($flashType); ?>);
  });
<?php endif; ?>
</script>
</body>
</html>