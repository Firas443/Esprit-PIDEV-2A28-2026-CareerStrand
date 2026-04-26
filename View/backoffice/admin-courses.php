<?php 
    include "C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php";

    $controlC = new ControlCourses();

    $editCourse = null;
    $viewCourse = null;
    $mode       = 'add';

    if (isset($_GET['update'])) {
        $editCourse = $controlC->getCourseById($_GET['update']);
        $mode = 'edit';
    }

    if (isset($_GET['view'])) {
        $viewCourse = $controlC->getCourseById($_GET['view']);
        $mode = 'view';
    }

    if (isset($_POST['Title'])) {
        $c = new Courses(
            $_POST['Title'],
            $_POST['Description'],
            $_POST['Categorie'],
            $_POST['Skill'],
            $_POST['Difficulty'],
            (int)$_POST['Duration'],
            $_POST['Statut'],
            new DateTime($_POST['Published_AT']),
        );
        if (!empty($_POST['CourseID']))
            $controlC->updateCourse($c, $_GET['update']);
        else
            $controlC->addCourse($c);
        header("Location: admin-courses.php");
        exit;
    }

    if (isset($_GET['delete'])) {
        $controlC->deleteCourse($_GET['delete']);
        header("Location: admin-courses.php");
        exit;
    }

    // ── Récupération + filtrage + tri ──
    $searchQuery = trim($_GET['search'] ?? '');
    $sortBy      = $_GET['sort'] ?? 'default';
    $coursesRaw  = $controlC->listeCourse();
    $allCourses  = $coursesRaw->fetchAll(PDO::FETCH_ASSOC);

    if ($searchQuery !== '') {
        $allCourses = array_values(array_filter($allCourses, function($row) use ($searchQuery) {
            return stripos($row['Title'], $searchQuery) !== false;
        }));
    }

    usort($allCourses, function($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'alpha_asc':  return strcasecmp($a['Title'], $b['Title']);
            case 'alpha_desc': return strcasecmp($b['Title'], $a['Title']);
            case 'recent':     return strtotime($b['Published_AT'] ?? '0') - strtotime($a['Published_AT'] ?? '0');
            case 'oldest':     return strtotime($a['Published_AT'] ?? '0') - strtotime($b['Published_AT'] ?? '0');
            default:           return 0;
        }
    });

    $panelData  = $editCourse ?? $viewCourse ?? null;

    $sortLabels = [
        'default'    => '⇅  Tri par défaut',
        'alpha_asc'  => 'A → Z  (alphabétique)',
        'alpha_desc' => 'Z → A  (alphabétique inverse)',
        'recent'     => '🕐 Plus récent en premier',
        'oldest'     => '🕐 Plus ancien en premier',
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin Courses</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <style>
    /* ── View mode ── */
    .view-mode input,
    .view-mode textarea,
    .view-mode select {
      pointer-events: none;
      opacity: 0.75;
      border-color: rgba(126,159,228,0.15) !important;
      background: rgba(255,255,255,0.02) !important;
      cursor: default;
    }

    .view-badge {
      display: inline-block;
      font-size: 11px; font-weight: 700;
      letter-spacing: 0.14em; text-transform: uppercase;
      padding: 4px 12px; border-radius: 999px;
      background: rgba(111,143,216,0.14);
      color: #95abeb;
      border: 1px solid rgba(111,143,216,0.25);
      margin-bottom: 14px;
    }

    .link-btn.view {
      background: rgba(111,143,216,0.12);
      border-color: rgba(111,143,216,0.3);
      color: #95abeb;
    }
    .link-btn.view:hover { background: rgba(111,143,216,0.25); }

    /* ── Toolbar ── */
    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      margin-top: 4px;
    }

    /* ── Recherche ── */
    .search-form {
      display: flex;
      gap: 8px;
      align-items: center;
      flex: 1;
      min-width: 200px;
    }

    .search-form input[type="text"] { flex: 1; }

    .search-form button {
      padding: 8px 16px;
      border-radius: 999px;
      border: none;
      background: rgba(111,143,216,0.2);
      color: #95abeb;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: 0.2s;
      white-space: nowrap;
    }
    .search-form button:hover { background: rgba(111,143,216,0.35); }

    .clear-btn {
      background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.5);
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 999px;
      font-size: 13px;
      white-space: nowrap;
      transition: 0.2s;
    }
    .clear-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

    /* ── Select tri natif ── */
    .sort-form {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sort-select {
      appearance: none;
      -webkit-appearance: none;
      padding: 8px 36px 8px 14px;
      border-radius: 999px;
      border: 1px solid rgba(126,159,228,0.22);
      background: rgba(255,255,255,0.04) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='rgba(149,171,235,0.7)'/%3E%3C/svg%3E") no-repeat right 14px center;
      color: rgba(255,255,255,0.82);
      font-size: 13px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: 0.2s;
      min-width: 220px;
    }

    .sort-select:hover {
      background-color: rgba(255,255,255,0.08);
      border-color: rgba(126,159,228,0.4);
    }

    .sort-select:focus {
      outline: none;
      border-color: #95abeb;
    }

    .sort-select option {
      background: #0d1528;
      color: #f5f3ee;
      font-weight: 500;
    }

    .sort-submit {
      padding: 8px 16px;
      border-radius: 999px;
      border: none;
      background: rgba(111,143,216,0.2);
      color: #95abeb;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      font-family: inherit;
      transition: 0.2s;
      white-space: nowrap;
    }
    .sort-submit:hover { background: rgba(111,143,216,0.35); }

    /* ── Result info ── */
    .result-info {
      font-size: 12px;
      color: rgba(255,255,255,0.38);
      margin-top: 10px;
      padding: 0 2px;
    }
    .result-info strong { color: #95abeb; }
  </style>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="brand"><div class="brand-badge"></div><div><h1>CareerStrand Admin</h1><p>Back office console</p></div></div>
      <div class="side-label">Main Menu</div>
      <nav class="nav-list">
        <a class="nav-item" href="admin-dashboard.html"><span>Dashboard</span><span>Home</span></a>
        <a class="nav-item" href="admin-users.html"><span>Users</span><span>1.2k</span></a>
        <a class="nav-item" href="admin-profiles.html"><span>Profiles</span><span>842</span></a>
        <a class="nav-item active" href="admin-courses.php"><span>Courses</span><span>24</span></a>
        <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>18</span></a>
        <a class="nav-item" href="admin-opportunities.html"><span>Opportunities</span><span>36</span></a>
        <a class="nav-item" href="admin-applications.html"><span>Applications</span><span>128</span></a>
        <a class="nav-item" href="admin-calendrier.php"><span>Calendrier</span><span>128</span></a>
        <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
        <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>12</span></a>
        <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>New</span></a>
      </nav>
    </aside>

    <main class="admin-main">
      <header class="page-header">
        <div><h2>Courses Management</h2><p>Manage the education module by creating courses, tracking enrollments, and shaping the learning stage of the CareerStrand progression journey.</p></div>
        <div class="header-actions"><button class="btn btn-soft">Enrollment view</button></div>
        <div class="header-actions"><a class="btn btn-soft" href="C:/xampp/htdocs/careerstrand/View/front_office/index.html">Front</a></div>
      </header>

      <section class="detail-grid">
        <article class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <h3>Course catalog</h3>
              <p>Educational content that strengthens users before they move into practical stages.</p>
            </div>

            <div class="filters">
              <div class="toolbar">

                <!-- ── Recherche ── -->
                <form method="GET" action="admin-courses.php" class="search-form">
                  <?php if ($sortBy !== 'default'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                  <?php endif; ?>
                  <input
                    type="text"
                    name="search"
                    id="search-input"
                    placeholder="🔍 Recherche par titre..."
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    autocomplete="off"
                  />
                  <button type="submit">Chercher</button>
                  <?php if ($searchQuery !== '' || $sortBy !== 'default'): ?>
                    <a href="admin-courses.php" class="clear-btn">✕ Reset</a>
                  <?php endif; ?>
                </form>

                <!-- ── Tri via select natif ── -->
                <form method="GET" action="admin-courses.php" class="sort-form">
                  <?php if ($searchQuery !== ''): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                  <?php endif; ?>
                  <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <?php foreach ($sortLabels as $key => $label): ?>
                      <option value="<?= $key ?>" <?= $sortBy === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <noscript>
                    <button type="submit" class="sort-submit">Appliquer</button>
                  </noscript>
                </form>

              </div><!-- /toolbar -->

              <!-- Infos résultats -->
              <div class="result-info">
                <?php if ($searchQuery !== ''): ?>
                  <strong><?= count($allCourses) ?></strong> résultat(s) pour "<strong><?= htmlspecialchars($searchQuery) ?></strong>"
                  <?php if ($sortBy !== 'default'): ?> · trié : <strong><?= $sortLabels[$sortBy] ?></strong><?php endif; ?>
                <?php elseif ($sortBy !== 'default'): ?>
                  Trié : <strong><?= $sortLabels[$sortBy] ?></strong> · <strong><?= count($allCourses) ?></strong> cours
                <?php else: ?>
                  <strong><?= count($allCourses) ?></strong> cours au total
                <?php endif; ?>
              </div>
            </div>
          </div>

          <table id="courses-table">
            <thead>
              <tr>
                <th>Course</th>
                <th>Difficulty</th>
                <th>Duration</th>
                <th>Categorie</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($allCourses)): ?>
                <tr>
                  <td colspan="5" style="text-align:center;padding:30px;color:rgba(255,255,255,0.4);">
                    Aucun cours trouvé pour "<?= htmlspecialchars($searchQuery) ?>"
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($allCourses as $row): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['Title']) ?></strong></td>
                  <td><span class="category-chip"><?= htmlspecialchars($row['Difficulty']) ?></span></td>
                  <td><?= htmlspecialchars($row['Duration']) ?></td>
                  <td><?= htmlspecialchars($row['Categorie']) ?></td>
                  <td class="table-actions">
                    <a class="link-btn view" href="admin-courses.php?view=<?= (int)$row['CourseID'] ?>">View</a>
                    <a class="link-btn" href="admin-courses.php?update=<?= (int)$row['CourseID'] ?>" onclick="return confirm('Update this course?')">Edit</a>
                    <a class="link-btn" href="admin-courses.php?delete=<?= (int)$row['CourseID'] ?>" onclick="return confirm('Delete this course?')">Delete</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </article>

        <!-- ── Panneau droit ── -->
        <aside class="detail-card <?= $mode === 'view' ? 'view-mode' : '' ?>">

          <?php if ($mode === 'view' && $viewCourse): ?>
            <span class="view-badge">👁 View only</span>
            <h4>Course details</h4>
          <?php elseif ($mode === 'edit'): ?>
            <h4>Edit course</h4>
          <?php else: ?>
            <h4>Create new course</h4>
          <?php endif; ?>

          <form method="post" onsubmit="return <?= $mode === 'view' ? 'false' : 'validerCourse()' ?>" action="admin-courses.php?update=<?= $_GET['update'] ?? '' ?>">
            <input type="hidden" name="CourseID" value="<?= $panelData['CourseID'] ?? '' ?>">
            <div class="field-grid">

              <div class="field">
                <label>Course title</label>
                <input type="text" id="Title" name="Title"
                  value="<?= htmlspecialchars($panelData['Title'] ?? '') ?>"
                  placeholder="Enter course title" />
              </div>

              <div class="field">
                <label>Description</label>
                <textarea id="Description" name="Description"
                  placeholder="Enter description du course"><?= htmlspecialchars($panelData['Description'] ?? '') ?></textarea>
              </div>

              <div class="field">
                <label>Category</label>
                <select id="Categorie" name="Categorie">
                  <?php foreach (['Programming','Design','Marketing','Business','Mathematics','Languages'] as $opt): ?>
                    <option <?= ($panelData['Categorie'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>Skills</label>
                <select id="Skill" name="Skill">
                  <?php foreach (['Problem solving','Critical thinking','Analytical thinking','Logical reasoning'] as $opt): ?>
                    <option <?= ($panelData['Skill'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>Difficulty</label>
                <select id="Difficulty" name="Difficulty">
                  <?php foreach (['Beginner','Intermediate','Advanced'] as $opt): ?>
                    <option <?= ($panelData['Difficulty'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>Status</label>
                <select id="Statut" name="Statut">
                  <?php foreach (['Availeble','Not Availeble'] as $opt): ?>
                    <option <?= ($panelData['Statut'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>Duration (weeks)</label>
                <input type="number" id="Duration" name="Duration"
                  value="<?= htmlspecialchars($panelData['Duration'] ?? '') ?>"
                  placeholder="e.g. 4" />
              </div>

              <div class="field">
                <label>Published At</label>
                <input type="date" id="Published_AT" name="Published_AT"
                  value="<?= htmlspecialchars(!empty($panelData['Published_AT']) ? date('Y-m-d', strtotime($panelData['Published_AT'])) : '') ?>" />
              </div>

              <br>

              <?php if ($mode === 'view'): ?>
                <a href="admin-courses.php?update=<?= (int)$viewCourse['CourseID'] ?>" class="btn btn-main" style="text-decoration:none">Edit this course</a>
                <a href="admin-courses.php" class="btn btn-soft" style="margin-left:8px;text-decoration:none">← Back</a>
              <?php elseif ($mode === 'edit'): ?>
                <button type="submit" class="btn btn-main">Update Course</button>
                <a href="admin-courses.php" class="btn btn-soft" style="margin-left:8px;text-decoration:none">Cancel</a>
              <?php else: ?>
                <button type="submit" class="btn btn-main">Add Course</button>
              <?php endif; ?>

            </div>
          </form>
        </aside>
      </section>
    </main>
  </div>

  <script src="assets/js/admin.js"></script>
  <script src="./assets/js/courses.js"></script>
</body>
</html>