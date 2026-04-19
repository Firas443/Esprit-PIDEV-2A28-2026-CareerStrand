<?php
include 'C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php';
include 'C:/xampp/htdocs/careerstrand/Controller/Controcalendar.php';

$cl = new controlcalendar();

if (isset($_POST['Status'])) {
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

if (isset($_GET['delete'])) {
    $cl->deletecalendar($_GET['delete']);
}

$controlC    = new ControlCourses();
$coursesRaw  = $controlC->listeCourse();
$coursesArray = [];
if ($coursesRaw instanceof PDOStatement) {
    $coursesArray = $coursesRaw->fetchAll(PDO::FETCH_ASSOC);
} elseif (is_array($coursesRaw)) {
    $coursesArray = $coursesRaw;
}

$calendarArray = $cl->listecalendar();
$action = $_GET['action'] ?? 'list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CareerStrand Admin Calendrier</title>
    <link rel="stylesheet" href="assets/css/admin.css" />
</head>
<body>
<div class="admin-shell">

<?php if ($action == 'list'): ?>
    <aside class="admin-sidebar">
        <div class="brand">
            <div class="brand-badge"></div>
            <div><h1>CareerStrand</h1><p>Admin console</p></div>
        </div>
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
            <div>
                <h2>Calendrier</h2>
                <p>Visualisez les dates de début et de fin de chaque cours. Cliquez sur un jour pour voir le détail.</p>
            </div>
            <div class="header-actions">
                <a href="admin-calendrier.php?action=add" class="btn-primary">+ Add event</a>
                <a class="btn btn-main" href="../front_office/courses.html">Front Office</a>
            </div>
        </header>

        <div class="tile-grid" style="margin-bottom:24px">
            <div class="metric-tile">
                <div class="metric-label">Cours planifiés</div>
                <div class="metric-value" id="s-planned" style="font-size:32px">0</div>
                <div class="metric-sub" id="s-planned-sub">sur 0 cours</div>
            </div>
            <div class="metric-tile">
                <div class="metric-label">En cours ce mois</div>
                <div class="metric-value" id="s-active" style="font-size:32px;color:var(--green)">0</div>
                <div class="metric-sub">actifs ce mois-ci</div>
            </div>
            <div class="metric-tile">
                <div class="metric-label">À venir</div>
                <div class="metric-value" id="s-upcoming" style="font-size:32px;color:var(--blue-2)">0</div>
                <div class="metric-sub">cours à venir</div>
            </div>
            <div class="metric-tile">
                <div class="metric-label">Terminés</div>
                <div class="metric-value" id="s-done" style="font-size:32px;color:var(--muted-2)">0</div>
                <div class="metric-sub">cours passés</div>
            </div>
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
                    <div id="detail-events">
                        <div class="empty-state">Sélectionnez un jour<br>pour voir les cours actifs.</div>
                    </div>
                </div>
                <div class="side-body" id="panel-courses" style="display:none">
                    <div id="all-courses-list"></div>
                </div>
            </div>
        </div>
    </main>

<?php endif; ?>

<?php if ($action == 'add'): ?>
    <aside class="admin-sidebar">
        <div class="brand">
            <div class="brand-badge"></div>
            <div><h1>CareerStrand</h1><p>Admin console</p></div>
        </div>
        <div class="side-label">Navigation</div>
        <nav class="nav-list">
            <a class="nav-item active" href="admin-calendrier.php"><span>Calendrier</span></a>
        </nav>
    </aside>

    <main class="admin-main">
        <header class="page-header">
            <div>
                <h2>Ajouter un événement</h2>
                <p>Planifiez les dates d'un cours avec progression et statut.</p>
            </div>
            <div class="header-actions">
                <a href="admin-calendrier.php" class="btn btn-soft">← Retour</a>
            </div>
        </header>

        <div class="panel" style="max-width:700px;">
            <form method="POST" class="field-grid">

                <!-- COURS — value is the Title, saved directly into calendar.Title -->
                <div class="field">
                    <label>Cours associé</label>
                    <select name="Title" required>
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach ($coursesArray as $c): ?>
                            <option value="<?= htmlspecialchars($c['Title'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($c['Title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DATE DEBUT -->
                <div class="field">
                    <label>Date de début</label>
                    <input type="date" name="startDate" required>
                </div>

                <!-- DATE FIN -->
                <div class="field">
                    <label>Date de fin</label>
                    <input type="date" name="endDate" required>
                </div>

                <!-- PROGRESS -->
                <div class="field">
                    <label>Progress (%)</label>
                    <input type="number" name="Progress" min="0" max="100" value="0">
                </div>

                <!-- STATUS -->
                <div class="field">
                    <label>Status</label>
                    <select name="Status" required>
                        <option value="">-- Choisir --</option>
                        <option value="Planned">Planned</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div style="margin-top:10px;">
                    <button type="submit" class="btn btn-main">Ajouter l'événement</button>
                </div>

            </form>
        </div>
    </main>
<?php endif; ?>

</div>

<script src="assets/js/calendrier.js"></script>
<script>
<?php foreach ($calendarArray as $row):
    $title = !empty($row['Title']) ? $row['Title'] : $row['Status'];
    $start = date('Y-m-d', strtotime($row['startDate']));
    $end   = date('Y-m-d', strtotime($row['endDate']));
?>
COURSES.push({
    id:       <?= (int)$row['calendarID'] ?>,
    title:    "<?= htmlspecialchars($title, ENT_QUOTES) ?>",
    track:    "<?= htmlspecialchars($row['Status'], ENT_QUOTES) ?>",
    start:    "<?= $start ?>",
    end:      "<?= $end ?>",
    Progress: <?= (int)$row['Progress'] ?>
});
state[<?= (int)$row['calendarID'] ?>] = {
    scheduled: true,
    start:    "<?= $start ?>",
    end:      "<?= $end ?>",
    Progress: <?= (int)$row['Progress'] ?>
};
<?php endforeach; ?>
renderCalendar();
</script>
</body>
</html>