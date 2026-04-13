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
        <!-- SIDEBAR -->
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
 
        <!-- MAIN -->
        <main class="admin-main">
            <header class="page-header">
            <div>
                <h2>Calendrier</h2>
                <p>Visualisez les dates de début et de fin de chaque cours. Cliquez sur un jour pour voir le détail.</p>
            </div>
            <div class="header-actions">
                <a href="admin-courses.php" class="btn btn-soft" style="text-decoration:none">← Retour aux cours</a>
                <button class="btn btn-main" onclick="goToday()">Aujourd'hui</button>
            </div>
            </header>
        
            <!-- STATS ROW -->
            <div class="tile-grid" style="margin-bottom:24px" id="stats-row">
            <div class="metric-tile">
                <div class="metric-label">Cours planifiés</div>
                <div class="metric-value" id="s-planned" style="font-size:32px">0</div>
                <div class="metric-sub" id="s-planned-sub">sur 8 cours</div>
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
 
      <!-- CALENDAR -->
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
 
        <!-- LEGEND -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(126,159,228,0.12)">
          <div class="side-label" style="padding:0 0 10px;font-size:10px">Légende des tracks</div>
          <div class="legend-grid" id="legend-grid"></div>
        </div>
      </div>
 
      <!-- SIDE PANEL -->
      <div class="side-section">
        <div class="side-tabs">
          <div class="side-tab active" id="tab-day"    onclick="switchTab('day')">Jour sélectionné</div>
          <div class="side-tab"        id="tab-courses" onclick="switchTab('courses')">Tous les cours</div>
        </div>
 
        <!-- DAY DETAIL -->
        <div class="side-body" id="panel-day">
          <div class="detail-day-title" id="detail-title">Cliquez sur un jour du calendrier</div>
          <div id="detail-events">
            <div class="empty-state">Sélectionnez un jour<br>pour voir les cours actifs.</div>
          </div>
        </div>
 
        <!-- ALL COURSES LIST -->
        <div class="side-body" id="panel-courses" style="display:none">
          <div id="all-courses-list"></div>
        </div>
      </div>
 
    </div>
  </main>
</div>
 <script src="assets/js/calendar.js"></script>
</body>
    </head>
</html>