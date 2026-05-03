<?php
include 'C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php';
include 'C:/xampp/htdocs/careerstrand/Controller/Controcalendar.php';

$cl = new controlcalendar();

// ── UPDATE / ADD ──
if (isset($_POST['Status']) && !empty($_POST['Status'])) {
    $cal = new calendar(
        $_POST['Title'],
        new DateTime($_POST['startDate']),
        new DateTime($_POST['endDate']),
        (int)$_POST['Progress'],
        $_POST['Status']
    );
    if (!empty($_POST['calendarID']))
        $cl->updatecalendar($cal, $_POST['calendarID']);
    else
        $cl->addcalendar($cal);
    header("Location: admin-calendrier.php");
    exit;
}

// ── DELETE ──
if (isset($_GET['delete'])) {
    $cl->deletecalendar($_GET['delete']);
    header("Location: admin-calendrier.php");
    exit;
}

// ── Courses ──
$controlC     = new ControlCourses();
$coursesRaw   = $controlC->listeCourse();
$coursesArray = [];
if ($coursesRaw instanceof PDOStatement) {
    $coursesArray = $coursesRaw->fetchAll(PDO::FETCH_ASSOC);
} elseif (is_array($coursesRaw)) {
    $coursesArray = $coursesRaw;
}

// ── Calendrier ──
$calendarRaw   = $cl->listecalendar();
$calendarArray = [];
if ($calendarRaw instanceof PDOStatement) {
    $calendarArray = $calendarRaw->fetchAll(PDO::FETCH_ASSOC);
} elseif (is_array($calendarRaw)) {
    $calendarArray = $calendarRaw;
}

// ── Action ──
$action    = $_GET['action'] ?? 'list';
$editEvent = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editEvent = $cl->getcalendarById((int)$_GET['id']);
    if (!$editEvent) { header("Location: admin-calendrier.php"); exit; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CareerStrand Admin Calendrier</title>
    <link rel="stylesheet" href="assets/css/admin.css" />
    <style>
        /* ══ VALIDATION MESSAGES ══ */
        .field-error {
            display: none;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            font-weight: 500;
            color: #ff8564;
            margin-top: 8px;
            padding: 8px 12px;
            background: rgba(255, 110, 69, 0.10);
            border: 1px solid rgba(255, 110, 69, 0.30);
            border-radius: 10px;
            animation: errSlide 0.18s ease;
        }
        .field-error.visible {
            display: flex;
        }
        .field-error::before {
            content: '⚠';
            font-size: 13px;
            flex-shrink: 0;
            color: #ff6e45;
        }
        @keyframes errSlide {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .field.has-error input,
        .field.has-error select {
            border-color: rgba(255, 110, 69, 0.55) !important;
            box-shadow: 0 0 0 3px rgba(255, 110, 69, 0.12) !important;
            outline: none;
        }
        .field.has-error label {
            color: #ff8564 !important;
        }

        .field.has-success input,
        .field.has-success select {
            border-color: rgba(89, 211, 155, 0.45) !important;
            box-shadow: 0 0 0 3px rgba(89, 211, 155, 0.08) !important;
        }
        .field.has-success label {
            color: rgba(89, 211, 155, 0.85);
        }

        /* ══ BOUTONS DELETE/EDIT PANNEAU DROIT ══ */
        .detail-ev-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }
        .detail-ev-actions { display: flex; gap: 6px; flex-shrink: 0; }

        .detail-ev-delete,
        .detail-ev-edit {
            display: grid; place-items: center;
            width: 28px; height: 28px;
            border-radius: 8px; font-size: 13px;
            text-decoration: none; transition: 0.2s; cursor: pointer;
        }
        .detail-ev-delete {
            background: rgba(255,80,60,0.08); border: 1px solid rgba(255,80,60,0.18);
            color: rgba(255,100,80,0.7);
        }
        .detail-ev-delete:hover { background: rgba(255,80,60,0.22); border-color: rgba(255,80,60,0.45); color: #ff6e45; }
        .detail-ev-edit {
            background: rgba(111,143,216,0.08); border: 1px solid rgba(111,143,216,0.2);
            color: rgba(149,171,235,0.7);
        }
        .detail-ev-edit:hover { background: rgba(111,143,216,0.22); border-color: rgba(111,143,216,0.45); color: #95abeb; }

        /* ══ POPUP ══ */
        .event-modal-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
            z-index: 9999; align-items: center; justify-content: center;
        }
        .event-modal-backdrop.open { display: flex; }
        .event-modal {
            background: #0b1120; border: 1px solid rgba(126,159,228,0.22);
            border-radius: 24px; padding: 32px;
            width: min(580px, calc(100vw - 40px)); max-height: 88vh;
            overflow-y: auto; box-shadow: 0 32px 80px rgba(0,0,0,0.6);
            position: relative; animation: popIn 0.22s ease;
        }
        @keyframes popIn {
            from { opacity:0; transform:translateY(20px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .em-close {
            position: absolute; top:16px; right:16px;
            width:32px; height:32px; border-radius:50%;
            border:1px solid rgba(255,255,255,0.12); background:rgba(255,255,255,0.05);
            color:rgba(255,255,255,0.6); font-size:16px; cursor:pointer;
            display:grid; place-items:center; transition:0.2s;
        }
        .em-close:hover { background:rgba(255,80,60,0.2); color:#fff; }
        .em-track-badge {
            display:inline-block; font-size:11px; font-weight:700;
            letter-spacing:0.14em; text-transform:uppercase;
            padding:4px 12px; border-radius:999px; margin-bottom:12px;
        }
        .badge-Planned   { background:rgba(111,143,216,0.15); color:#95abeb; border:1px solid rgba(111,143,216,0.3); }
        .badge-Ongoing   { background:rgba(89,211,155,0.15);  color:#59d39b; border:1px solid rgba(89,211,155,0.3); }
        .badge-Completed { background:rgba(245,191,101,0.15); color:#f5bf65; border:1px solid rgba(245,191,101,0.3); }
        .em-title { font-size:24px; font-weight:800; color:#f5f3ee; margin-bottom:18px; letter-spacing:-0.02em; }
        .em-progress-label { display:flex; justify-content:space-between; font-size:12px; color:rgba(255,255,255,0.45); margin-bottom:8px; }
        .em-progress-label strong { color:rgba(255,255,255,0.8); }
        .em-progress-track { height:8px; border-radius:999px; background:rgba(255,255,255,0.08); overflow:hidden; margin-bottom:20px; }
        .em-progress-fill { height:100%; border-radius:999px; background:linear-gradient(to right,#6f8fd8,#95abeb,#ff6e45); }
        .em-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; }
        .em-field { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:14px; padding:14px 16px; }
        .em-field .ef-label { font-size:10px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase; color:rgba(255,255,255,0.35); margin-bottom:6px; }
        .em-field .ef-value { font-size:14px; font-weight:600; color:#f5f3ee; }
        .em-separator { height:1px; background:rgba(255,255,255,0.07); margin:18px 0; }
        .em-section-label { font-size:10px; font-weight:700; letter-spacing:0.2em; text-transform:uppercase; color:rgba(255,255,255,0.35); margin-bottom:14px; }
        .em-desc { font-size:13px; color:rgba(245,243,238,0.62); line-height:1.8; padding:14px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:12px; margin-bottom:14px; }
        .em-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
        .em-chip { font-size:12px; padding:5px 12px; border-radius:999px; background:rgba(111,143,216,0.1); color:#95abeb; border:1px solid rgba(111,143,216,0.2); }
        .em-footer { display:flex; gap:10px; flex-wrap:wrap; margin-top:22px; padding-top:18px; border-top:1px solid rgba(255,255,255,0.07); }
        .em-not-found { text-align:center; padding:20px 0; color:rgba(255,255,255,0.35); font-size:13px; font-style:italic; }
    </style>
</head>
<body>
<div class="admin-shell">

<!-- ══════════ LIST ══════════ -->
<?php if ($action == 'list'): ?>
    <aside class="admin-sidebar">
        <div class="brand"><div class="brand-badge"></div><div><h1>CareerStrand</h1><p>Admin console</p></div></div>
        <div class="side-label" style="margin-top:24px">Main Menu</div>
        <nav class="nav-list">
            <a class="nav-item" href="admin-dashboard.html"><span>Dashboard</span><span>Home</span></a>
            <a class="nav-item" href="admin-users.html"><span>Users</span><span>1.2k</span></a>
            <a class="nav-item" href="admin-profiles.html"><span>Profiles</span><span>842</span></a>
            <a class="nav-item" href="admin-courses.php"><span>Courses</span><span>24</span></a>
            <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>18</span></a>
            <a class="nav-item" href="admin-opportunities.html"><span>Opportunities</span><span>36</span></a>
            <a class="nav-item" href="admin-applications.html"><span>Applications</span><span>128</span></a>
            <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
            <a class="nav-item active" href="admin-calendrier.php"><span>Calendrier</span><span>24</span></a>
            <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>New</span></a>
        </nav>
        <div class="sidebar-card" style="margin-top:32px">
            <h3>📋 Courses</h3>
            <p>Retournez à la gestion des cours pour ajouter ou modifier des dates.</p>
            <a href="admin-courses.php" class="btn btn-soft" style="display:inline-block;margin-top:14px;font-size:13px;text-decoration:none;padding:10px 18px">Gérer les cours</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="page-header">
            <div><h2>Calendrier</h2><p>Visualisez les dates de début et de fin de chaque cours.</p></div>
            <div class="header-actions">
                <a href="admin-calendrier.php?action=add" class="btn-primary">+ Add session</a>
                <a class="btn btn-main" href="../front_office/course.php">Front Office</a>
            </div>
        </header>

        <div class="tile-grid" style="margin-bottom:24px">
            <div class="metric-tile"><div class="metric-label">Cours planifiés</div><div class="metric-value" id="s-planned" style="font-size:32px">0</div><div class="metric-sub" id="s-planned-sub">sur 0 cours</div></div>
            <div class="metric-tile"><div class="metric-label">En cours ce mois</div><div class="metric-value" id="s-active" style="font-size:32px;color:var(--green)">0</div><div class="metric-sub">actifs ce mois-ci</div></div>
            <div class="metric-tile"><div class="metric-label">À venir</div><div class="metric-value" id="s-upcoming" style="font-size:32px;color:var(--blue-2)">0</div><div class="metric-sub">cours à venir</div></div>
            <div class="metric-tile"><div class="metric-label">Terminés</div><div class="metric-value" id="s-done" style="font-size:32px;color:var(--muted-2)">0</div><div class="metric-sub">cours passés</div></div>
        </div>

        <div class="cal-wrapper">
            <div class="panel" style="padding:24px">
                <div class="cal-nav">
                    <button class="cal-nav-btn" onclick="prevMonth()">&#8249;</button>
                    <div class="cal-month-title" id="month-title">—</div>
                    <button class="cal-nav-btn" onclick="nextMonth()">&#8250;</button>
                </div>
                <div class="cal-weekdays">
                    <div class="cal-wd">Lun</div><div class="cal-wd">Mar</div><div class="cal-wd">Mer</div>
                    <div class="cal-wd">Jeu</div><div class="cal-wd">Ven</div><div class="cal-wd">Sam</div><div class="cal-wd">Dim</div>
                </div>
                <div class="cal-grid" id="cal-grid"></div>
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(126,159,228,0.12)">
                    <div class="side-label" style="padding:0 0 10px;font-size:10px">Légende des tracks</div>
                    <div class="legend-grid" id="legend-grid"></div>
                </div>
            </div>
            <div class="side-section">
                <div class="side-tabs">
                    <div class="side-tab active" id="tab-day" onclick="switchTab('day')">Jour sélectionné</div>
                    <div class="side-tab" id="tab-courses" onclick="switchTab('courses')">Tous les cours</div>
                </div>
                <div class="side-body" id="panel-day">
                    <div class="detail-day-title" id="detail-title">Cliquez sur un jour du calendrier</div>
                    <div id="detail-events"><div class="empty-state">Sélectionnez un jour<br>pour voir les cours actifs.</div></div>
                </div>
                <div class="side-body" id="panel-courses" style="display:none">
                    <div id="all-courses-list"></div>
                </div>
            </div>
        </div>
    </main>
<?php endif; ?>

<!-- ══════════ ADD ══════════ -->
<?php if ($action == 'add'): ?>
    <aside class="admin-sidebar">
        <div class="brand"><div class="brand-badge"></div><div><h1>CareerStrand</h1><p>Admin console</p></div></div>
        <div class="side-label">Navigation</div>
        <nav class="nav-list"><a class="nav-item active" href="admin-calendrier.php"><span>Calendrier</span></a></nav>
    </aside>

    <main class="admin-main">
        <header class="page-header">
            <div><h2>Ajouter une session</h2><p>Planifiez les dates d'un cours avec progression et statut.</p></div>
            <div class="header-actions"><a href="admin-calendrier.php" class="btn btn-soft">← Retour</a></div>
        </header>

        <div class="panel" style="max-width:700px;">
            <!-- novalidate désactive HTML5, onsubmit appelle notre validation JS -->
            <form method="POST" id="form-add" novalidate autocomplete="off" onsubmit="return validerCalendrier('form-add')" class="field-grid">

                <div class="field" id="field-add-title">
                    <label>Cours associé</label>
                    <select name="Title" id="add-title">
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach ($coursesArray as $c): ?>
                            <option value="<?= htmlspecialchars($c['Title'], ENT_QUOTES) ?>"><?= htmlspecialchars($c['Title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-add-title"></span>
                </div>

                <div class="field" id="field-add-start">
                    <label>Date de début</label>
                    <input type="date" name="startDate" id="add-start">
                    <span class="field-error" id="err-add-start"></span>
                </div>

                <div class="field" id="field-add-end">
                    <label>Date de fin</label>
                    <input type="date" name="endDate" id="add-end">
                    <span class="field-error" id="err-add-end"></span>
                </div>

                <div class="field" id="field-add-progress">
                    <label>Progress in hours (%)</label>
                    <input type="number" name="Progress" id="add-progress" min="0" max="100" value="0">
                    <span class="field-error" id="err-add-progress"></span>
                </div>

                <div class="field" id="field-add-status">
                    <label>Status</label>
                    <select name="Status" id="add-status">
                        <option value="">-- Choisir --</option>
                        <option value="Planned">Planned</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                    <span class="field-error" id="err-add-status"></span>
                </div>

                <div style="margin-top:10px;">
                    <button type="submit" class="btn btn-main">Ajouter session</button>
                </div>
            </form>
        </div>
    </main>
<?php endif; ?>

<!-- ══════════ EDIT ══════════ -->
<?php if ($action == 'edit' && $editEvent): ?>
    <aside class="admin-sidebar">
        <div class="brand"><div class="brand-badge"></div><div><h1>CareerStrand</h1><p>Admin console</p></div></div>
        <div class="side-label">Navigation</div>
        <nav class="nav-list"><a class="nav-item active" href="admin-calendrier.php"><span>Calendrier</span></a></nav>
    </aside>

    <main class="admin-main">
        <header class="page-header">
            <div><h2>Modifier l'événement</h2><p>Modifiez les dates, la progression ou le statut de cet événement.</p></div>
            <div class="header-actions"><a href="admin-calendrier.php" class="btn btn-soft">← Retour</a></div>
        </header>

        <div class="panel" style="max-width:700px;">
            <form method="POST" id="form-edit" novalidate autocomplete="off" onsubmit="return validerCalendrier('form-edit')" class="field-grid">
                <input type="hidden" name="calendarID" value="<?= (int)$editEvent['calendarID'] ?>">

                <div class="field" id="field-edit-title">
                    <label>Cours associé</label>
                    <select name="Title" id="edit-title">
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach ($coursesArray as $c): ?>
                            <option value="<?= htmlspecialchars($c['Title'], ENT_QUOTES) ?>"
                                <?= $c['Title'] === $editEvent['Title'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['Title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-edit-title"></span>
                </div>

                <div class="field" id="field-edit-start">
                    <label>Date de début</label>
                    <input type="date" name="startDate" id="edit-start"
                        value="<?= htmlspecialchars(date('Y-m-d', strtotime($editEvent['startDate']))) ?>">
                    <span class="field-error" id="err-edit-start"></span>
                </div>

                <div class="field" id="field-edit-end">
                    <label>Date de fin</label>
                    <input type="date" name="endDate" id="edit-end"
                        value="<?= htmlspecialchars(date('Y-m-d', strtotime($editEvent['endDate']))) ?>">
                    <span class="field-error" id="err-edit-end"></span>
                </div>

                <div class="field" id="field-edit-progress">
                    <label>Progress (%)</label>
                    <input type="number" name="Progress" id="edit-progress" min="0" max="100"
                        value="<?= (int)$editEvent['Progress'] ?>">
                    <span class="field-error" id="err-edit-progress"></span>
                </div>

                <div class="field" id="field-edit-status">
                    <label>Status</label>
                    <select name="Status" id="edit-status">
                        <option value="">-- Choisir --</option>
                        <?php foreach (['Planned','Ongoing','Completed'] as $s): ?>
                            <option value="<?= $s ?>" <?= $editEvent['Status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-edit-status"></span>
                </div>

                <div style="margin-top:10px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-main">💾 Enregistrer</button>
                    <a href="admin-calendrier.php" class="btn btn-soft">Annuler</a>
                </div>
            </form>
        </div>
    </main>
<?php endif; ?>

</div>

<!-- ══ POPUP ══ -->
<div class="event-modal-backdrop" id="event-modal">
    <div class="event-modal">
        <button class="em-close" id="em-close-btn">✕</button>
        <div id="em-body"></div>
    </div>
</div>

<script>
/* ══ VALIDATION JS — remplace HTML5 natif ══ */
function validerCalendrier(formId) {
    var prefix  = formId === 'form-add' ? 'add' : 'edit';
    var isValid = true;

    function getEl(id) { return document.getElementById(id); }

    function showErr(fieldId, msg) {
        var field = getEl('field-' + prefix + '-' + fieldId);
        var err   = getEl('err-'   + prefix + '-' + fieldId);
        if (!field || !err) return;
        field.classList.add('has-error');
        field.classList.remove('has-success');
        err.textContent = msg;
        err.classList.add('visible');
        isValid = false;
    }

    function showOk(fieldId) {
        var field = getEl('field-' + prefix + '-' + fieldId);
        var err   = getEl('err-'   + prefix + '-' + fieldId);
        if (!field || !err) return;
        field.classList.remove('has-error');
        field.classList.add('has-success');
        err.textContent = '';
        err.classList.remove('visible');
    }

    function clearAll() {
        ['title','start','end','progress','status'].forEach(function(f) {
            var field = getEl('field-' + prefix + '-' + f);
            var err   = getEl('err-'   + prefix + '-' + f);
            if (field) { field.classList.remove('has-error','has-success'); }
            if (err)   { err.textContent = ''; err.classList.remove('visible'); }
        });
    }

    clearAll();

    // 1. Cours associé
    var title = getEl(prefix + '-title');
    if (!title || title.value.trim() === '') {
        showErr('title', 'Veuillez choisir un cours.');
    } else { showOk('title'); }

    // 2. Date de début
    var start = getEl(prefix + '-start');
    if (!start || start.value === '') {
        showErr('start', 'Veuillez entrer une date de début.');
    } else { showOk('start'); }

    // 3. Date de fin
    var end = getEl(prefix + '-end');
    if (!end || end.value === '') {
        showErr('end', 'Veuillez entrer une date de fin.');
    } else if (start && start.value !== '' && end.value < start.value) {
        showErr('end', 'La date de fin doit être après la date de début.');
    } else { showOk('end'); }

    // 4. Progression
    var prog = getEl(prefix + '-progress');
    if (prog) {
        var v = Number(prog.value);
        if (prog.value === '' || isNaN(v) || v < 0 || v > 100) {
            showErr('progress', 'La progression doit être entre 0 et 100.');
        } else { showOk('progress'); }
    }

    // 5. Statut
    var status = getEl(prefix + '-status');
    if (!status || status.value === '') {
        showErr('status', 'Veuillez choisir un statut.');
    } else { showOk('status'); }

    // Scroll vers le premier champ en erreur
    if (!isValid) {
        var firstErr = document.querySelector('#' + formId + ' .field.has-error');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    return isValid;
}
</script>
<script src="assets/js/calendrier.js"></script>
<script>
var COURSE_DETAILS = {};
<?php foreach ($coursesArray as $c): ?>
COURSE_DETAILS[<?= json_encode(strtolower(trim($c['Title']))) ?>] = {
    title:       <?= json_encode($c['Title']       ?? '') ?>,
    description: <?= json_encode($c['Description'] ?? '') ?>,
    categorie:   <?= json_encode($c['Categorie']   ?? '') ?>,
    skill:       <?= json_encode($c['Skill']        ?? '') ?>,
    difficulty:  <?= json_encode($c['Difficulty']   ?? '') ?>,
    duration:    <?= json_encode((string)($c['Duration'] ?? '')) ?>,
    statut:      <?= json_encode($c['Statut']       ?? '') ?>,
    published:   <?= json_encode(!empty($c['Published_AT']) ? date('d/m/Y', strtotime($c['Published_AT'])) : '—') ?>
};
<?php endforeach; ?>

<?php foreach ($calendarArray as $row):
    $row   = array_change_key_case($row, CASE_LOWER);
    $id    = (int)($row['calendarid'] ?? 0);
    if ($id === 0) continue;
    $title = !empty($row['title'])     ? $row['title']     : ($row['status'] ?? 'Sans titre');
    $track = !empty($row['status'])    ? $row['status']    : 'Planned';
    $prog  = (int)($row['progress']    ?? 0);
    $start = !empty($row['startdate']) ? date('Y-m-d', strtotime($row['startdate'])) : date('Y-m-d');
    $end   = !empty($row['enddate'])   ? date('Y-m-d', strtotime($row['enddate']))   : date('Y-m-d');
?>
COURSES.push({ id:<?= $id ?>, title:<?= json_encode($title) ?>, track:<?= json_encode($track) ?>, start:<?= json_encode($start) ?>, end:<?= json_encode($end) ?>, Progress:<?= $prog ?> });
state[<?= $id ?>] = { scheduled:true, start:<?= json_encode($start) ?>, end:<?= json_encode($end) ?>, Progress:<?= $prog ?> };
<?php endforeach; ?>

renderCalendar();

// ── Popup ──
function openEventPopup(courseId) {
    var cal = null;
    for (var i = 0; i < COURSES.length; i++) {
        if (COURSES[i].id === courseId) { cal = COURSES[i]; break; }
    }
    if (!cal) return;
    var key = cal.title.toLowerCase().trim();
    var details = COURSE_DETAILS[key] || null;
    var progress = cal.Progress || 0;
    var startFmt = new Date(cal.start+'T00:00:00').toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'});
    var endFmt   = new Date(cal.end  +'T00:00:00').toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'});
    var badgeCls = 'badge-'+(cal.track||'Planned');
    var html =
        '<span class="em-track-badge '+badgeCls+'">'+cal.track+'</span>'+
        '<div class="em-title">'+cal.title+'</div>'+
        '<div class="em-progress-label"><span>Progression</span><strong>'+progress+'%</strong></div>'+
        '<div class="em-progress-track"><div class="em-progress-fill" style="width:'+progress+'%"></div></div>'+
        '<div class="em-grid">'+
            '<div class="em-field"><div class="ef-label">Date de début</div><div class="ef-value">'+startFmt+'</div></div>'+
            '<div class="em-field"><div class="ef-label">Date de fin</div><div class="ef-value">'+endFmt+'</div></div>'+
        '</div>';
    if (details) {
        html += '<div class="em-separator"></div><div class="em-section-label">Détails du cours</div>'+
            '<div class="em-desc">'+(details.description||'<em>Aucune description.</em>')+'</div>'+
            '<div class="em-grid">'+
                '<div class="em-field"><div class="ef-label">Catégorie</div><div class="ef-value">'+(details.categorie||'—')+'</div></div>'+
                '<div class="em-field"><div class="ef-label">Difficulté</div><div class="ef-value">'+(details.difficulty||'—')+'</div></div>'+
                '<div class="em-field"><div class="ef-label">Durée</div><div class="ef-value">'+(details.duration||'—')+' semaine(s)</div></div>'+
                '<div class="em-field"><div class="ef-label">Publié le</div><div class="ef-value">'+(details.published||'—')+'</div></div>'+
            '</div>'+
            '<div class="em-chips">'+
                '<span class="em-chip">🎯 '+(details.skill||'—')+'</span>'+
                '<span class="em-chip">📁 '+(details.statut||'—')+'</span>'+
            '</div>';
    } else {
        html += '<div class="em-not-found">Aucun détail trouvé pour ce cours.</div>';
    }
    html += '<div class="em-footer">'+
        '<a href="admin-calendrier.php?action=edit&id='+courseId+'" class="btn btn-soft" style="font-size:13px;padding:9px 18px;text-decoration:none;background:rgba(111,143,216,0.1);color:#95abeb;border:1px solid rgba(111,143,216,0.2)">✏️ Modifier</a>'+
        '<a href="admin-calendrier.php?delete='+courseId+'" class="btn btn-soft" style="font-size:13px;padding:9px 18px;text-decoration:none;background:rgba(255,80,60,0.1);color:#ff8564;border:1px solid rgba(255,80,60,0.2)" onclick="return confirm(\'Supprimer cet événement ?\')">🗑 Supprimer</a>'+
        '<button onclick="closeEventPopup()" class="btn btn-soft" style="font-size:13px;padding:9px 18px;margin-left:auto">Fermer</button>'+
    '</div>';
    document.getElementById('em-body').innerHTML = html;
    document.getElementById('event-modal').classList.add('open');
}

function closeEventPopup() { document.getElementById('event-modal').classList.remove('open'); }
document.getElementById('event-modal').addEventListener('click', function(e){ if(e.target===this) closeEventPopup(); });
document.getElementById('em-close-btn').addEventListener('click', closeEventPopup);
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeEventPopup(); });
</script>
</body>
</html>