<?php 
    include "C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php";
    include "C:/xampp/htdocs/Careerstrand/Controller/ControlCourseVideos.php";

    $controlC = new ControlCourses();
    $controlV = new ControlCourseVideos();

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

    // ── Ajout / Mise à jour du cours ──
    if (isset($_POST['Title'])) {
        $c = new Courses(
            $_POST['Title'],
            $_POST['Description'],
            $_POST['Categorie'],
            $_POST['Skill'],
            $_POST['Difficulty'],
            (int)$_POST['Duration'],
            $_POST['Statut'],
            new DateTime($_POST['Published_AT'])
        );

        if (!empty($_POST['CourseID'])) {
            $controlC->updateCourse($c, $_GET['update']);
            $savedCourseId = (int)$_POST['CourseID'];
        } else {
            $controlC->addCourse($c);
            // Récupère l'ID du cours qu'on vient de créer
            $savedCourseId = (int)$controlC->getLastInsertedId();
        }

        // ── Multi-upload vidéos ──
        // Les fichiers arrivent sous le nom video_files[] avec leurs titres video_titles[]
        if (!empty($_FILES['video_files']['name'][0])) {
            $titles = $_POST['video_titles'] ?? [];
            $count  = count($_FILES['video_files']['name']);

            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['video_files']['error'][$i] === UPLOAD_ERR_OK) {
                    // Reconstruit un $_FILES compatible avec addVideo()
                    $_FILES['_single_video'] = [
                        'name'     => $_FILES['video_files']['name'][$i],
                        'type'     => $_FILES['video_files']['type'][$i],
                        'tmp_name' => $_FILES['video_files']['tmp_name'][$i],
                        'error'    => $_FILES['video_files']['error'][$i],
                        'size'     => $_FILES['video_files']['size'][$i],
                    ];
                    $title = trim($titles[$i] ?? '') ?: 'Chapitre ' . ($i + 1);
                    $controlV->addVideo($savedCourseId, $title, '_single_video');
                }
            }
        }

        header("Location: admin-courses.php");
        exit;
    }

    if (isset($_GET['delete'])) {
        $controlC->deleteCourse($_GET['delete']);
        header("Location: admin-courses.php");
        exit;
    }

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

    // Compte les vidéos par cours
    $videoCountByCourse = [];
    foreach ($allCourses as $row) {
        $vids = $controlV->getVideosByCourse($row['CourseID']);
        $videoCountByCourse[$row['CourseID']] = count($vids);
    }

    $panelData  = $editCourse ?? $viewCourse ?? null;

    $sortLabels = [
        'default'    => '⇅  Tri par défaut',
        'alpha_asc'  => 'Alphabétique',
        'alpha_desc' => 'Alphabétique inverse',
        'recent'     => 'Plus récent',
        'oldest'     => 'Plus ancien',
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
    .view-mode input,
    .view-mode textarea,
    .view-mode select,
    .view-mode input[type="file"] {
      pointer-events: none; opacity: 0.75;
      border-color: rgba(126,159,228,0.15) !important;
      background: rgba(255,255,255,0.02) !important;
      cursor: default;
    }
    .view-badge { display:inline-block; font-size:11px; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; padding:4px 12px; border-radius:999px; background:rgba(111,143,216,0.14); color:#95abeb; border:1px solid rgba(111,143,216,0.25); margin-bottom:14px; }
    .link-btn.view { background:rgba(111,143,216,0.12); border-color:rgba(111,143,216,0.3); color:#95abeb; }
    .link-btn.view:hover { background:rgba(111,143,216,0.25); }
    .link-btn.videos { background:rgba(34,211,130,0.08); border-color:rgba(34,211,130,0.25); color:#4ade80; }
    .link-btn.videos:hover { background:rgba(34,211,130,0.18); }
    .toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-top:4px; }
    .search-form { display:flex; align-items:center; flex:1; min-width:200px; }
    .search-form input[type="text"] { flex:1; }
    .search-form button { display:none; }
    .clear-btn { background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.5); text-decoration:none; padding:8px 14px; border-radius:999px; font-size:13px; white-space:nowrap; margin-left:8px; transition:0.2s; }
    .clear-btn:hover { background:rgba(255,255,255,0.1); color:#fff; }
    .sort-form { display:flex; align-items:center; }
    .sort-select { appearance:none; -webkit-appearance:none; padding:8px 36px 8px 14px; border-radius:999px; border:1px solid rgba(126,159,228,0.22); background:rgba(255,255,255,0.04) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='rgba(149,171,235,0.7)'/%3E%3C/svg%3E") no-repeat right 14px center; color:rgba(255,255,255,0.82); font-size:13px; font-weight:600; font-family:inherit; cursor:pointer; transition:0.2s; min-width:200px; }
    .sort-select:hover { background-color:rgba(255,255,255,0.08); border-color:rgba(126,159,228,0.4); }
    .sort-select:focus { outline:none; border-color:#95abeb; }
    .sort-select option { background:#0d1528; color:#f5f3ee; }
    .result-info { font-size:12px; color:rgba(255,255,255,0.38); margin-top:10px; padding:0 2px; }
    .result-info strong { color:#95abeb; }
    .field video { max-width:100%; border-radius:12px; margin-top:8px; }
    .field a { color:#95abeb; text-decoration:none; }
    .field a:hover { text-decoration:underline; }

    /* ── Multi-video upload ── */
    .video-upload-zone { border:1px dashed rgba(111,143,216,0.3); border-radius:14px; padding:16px; background:rgba(111,143,216,0.04); }
    .video-upload-zone label { font-size:12px; color:rgba(255,255,255,0.5); margin-bottom:8px; display:block; }
    .video-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:10px 12px; }
    .video-row input[type="text"] { flex:1; font-size:13px; padding:6px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:#f5f3ee; font-family:inherit; }
    .video-row input[type="text"]::placeholder { color:rgba(255,255,255,0.3); }
    .video-row input[type="file"] { flex:1.5; font-size:12px; }
    .video-row-num { font-size:11px; font-weight:800; color:#95abeb; min-width:20px; }
    .btn-remove-video { background:rgba(255,100,69,0.1); border:1px solid rgba(255,100,69,0.2); color:#ff8564; border-radius:8px; padding:4px 10px; font-size:12px; cursor:pointer; font-family:inherit; transition:0.2s; white-space:nowrap; }
    .btn-remove-video:hover { background:rgba(255,100,69,0.2); }
    .btn-add-video-row { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#95abeb; background:rgba(111,143,216,0.08); border:1px solid rgba(111,143,216,0.2); border-radius:999px; padding:7px 16px; cursor:pointer; font-family:inherit; transition:0.2s; margin-top:8px; }
    .btn-add-video-row:hover { background:rgba(111,143,216,0.18); }
    .php-limits { font-size:11px; color:rgba(255,255,255,0.3); margin-top:6px; }
    .php-limits code { background:rgba(255,255,255,0.06); padding:1px 5px; border-radius:4px; }
    .video-count-badge { display:inline-flex; align-items:center; gap:4px; font-size:11px; padding:2px 8px; border-radius:999px; background:rgba(34,211,130,0.1); color:#4ade80; border:1px solid rgba(34,211,130,0.2); margin-left:6px; }
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
        <a class="nav-item" href="admin-course-videos.php"><span>Course Videos</span><span>New</span></a>
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
                <form method="GET" action="admin-courses.php" class="search-form">
                  <?php if ($sortBy !== 'default'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                  <?php endif; ?>
                  <input type="text" name="search" id="search-input"
                    placeholder="🔍 Recherche par titre..."
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    autocomplete="off" />
                  <button type="submit"></button>
                  <?php if ($searchQuery !== '' || $sortBy !== 'default'): ?>
                    <a href="admin-courses.php" class="clear-btn">✕ Reset</a>
                  <?php endif; ?>
                </form>
                <form method="GET" action="admin-courses.php" class="sort-form">
                  <?php if ($searchQuery !== ''): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                  <?php endif; ?>
                  <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <?php foreach ($sortLabels as $key => $label): ?>
                      <option value="<?= $key ?>" <?= $sortBy === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </div>
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
                <?php foreach ($allCourses as $row):
                    $nbVideos = $videoCountByCourse[$row['CourseID']] ?? 0;
                ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($row['Title']) ?></strong>
                    <?php if ($nbVideos > 0): ?>
                      <span class="video-count-badge">🎬 <?= $nbVideos ?></span>
                    <?php endif; ?>
                  </td>
                  <td><span class="category-chip"><?= htmlspecialchars($row['Difficulty']) ?></span></td>
                  <td><?= htmlspecialchars($row['Duration']) ?></td>
                  <td><?= htmlspecialchars($row['Categorie']) ?></td>
                  <td class="table-actions">
                    <a class="link-btn view" href="admin-courses.php?view=<?= (int)$row['CourseID'] ?>">View</a>
                    <a class="link-btn" href="admin-courses.php?update=<?= (int)$row['CourseID'] ?>" onclick="return confirm('Update this course?')">Edit</a>
                    <a class="link-btn" href="admin-courses.php?delete=<?= (int)$row['CourseID'] ?>" onclick="return confirm('Delete this course?')">Delete</a>
                    <a class="link-btn videos" href="admin-course-videos.php?course_id=<?= (int)$row['CourseID'] ?>">🎬 Vidéos (<?= $nbVideos ?>)</a>
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

          <form method="post" enctype="multipart/form-data"
            onsubmit="return <?= $mode === 'view' ? 'false' : 'validerCourse()' ?>"
            action="admin-courses.php?update=<?= $_GET['update'] ?? '' ?>">

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

              <!-- ── Multi-upload vidéos ── -->
              <div class="field">
                <label style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;">
                  <span>🎬 Vidéos du cours</span>
                  <?php if ($mode !== 'add' && !empty($panelData['CourseID'])): ?>
                    <a href="admin-course-videos.php?course_id=<?= (int)$panelData['CourseID'] ?>"
                       style="font-size:12px;color:#4ade80;text-decoration:none;border:1px solid rgba(34,211,130,0.25);padding:3px 10px;border-radius:999px;">
                       Gérer les vidéos →
                    </a>
                  <?php endif; ?>
                </label>

                <?php
                // En mode view/edit, affiche les vidéos existantes
                if ($mode !== 'add' && !empty($panelData['CourseID'])):
                    $existingVideos = $controlV->getVideosByCourse($panelData['CourseID']);
                    if (!empty($existingVideos)):
                ?>
                  <div style="margin-bottom:12px;">
                    <?php foreach ($existingVideos as $i => $ev):
                        $evpath = $ev['video_path'];
                        $evpath = ltrim($evpath, '/');
                        if (stripos($evpath, 'careerstrand/') === 0) $evpath = substr($evpath, strlen('careerstrand/'));
                    ?>
                      <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;margin-bottom:6px;">
                        <span style="font-size:11px;font-weight:800;color:#95abeb;min-width:20px;">#<?= $i+1 ?></span>
                        <span style="font-size:13px;color:#f5f3ee;flex:1;"><?= htmlspecialchars($ev['title']) ?></span>
                        <video style="height:36px;border-radius:6px;background:#000;" preload="none">
                          <source src="/careerstrand/<?= htmlspecialchars($evpath) ?>" type="video/mp4">
                        </video>
                        <?php if ((int)$ev['video_id'] > 0): ?>
                          <a href="admin-course-videos.php?delete_video=<?= (int)$ev['video_id'] ?>&course_id=<?= (int)$panelData['CourseID'] ?>"
                             onclick="return confirm('Supprimer cette vidéo ?')"
                             style="font-size:11px;color:#ff8564;text-decoration:none;border:1px solid rgba(255,110,69,0.2);padding:3px 8px;border-radius:6px;">🗑</a>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($mode !== 'view'): ?>
                <div class="video-upload-zone">
                  <label>Ajouter une ou plusieurs vidéos :</label>
                  <div id="video-rows">
                    <div class="video-row" data-index="0">
                      <span class="video-row-num">1</span>
                      <input type="text" name="video_titles[]" placeholder="Titre (ex: Introduction)">
                      <input type="file" name="video_files[]" accept="video/mp4,video/webm,video/ogg">
                      <button type="button" class="btn-remove-video" onclick="removeVideoRow(this)" style="display:none;">✕</button>
                    </div>
                  </div>
                  <button type="button" class="btn-add-video-row" onclick="addVideoRow()">+ Ajouter une autre vidéo</button>
                  <div class="php-limits">
                    Limite PHP : <code><?= ini_get('upload_max_filesize') ?></code> par fichier · <code><?= ini_get('post_max_size') ?></code> total
                  </div>
                </div>
                <?php endif; ?>
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
  <script>
  // ── Gestion des lignes multi-vidéo ──
  var videoRowCount = 1;

  function addVideoRow() {
    videoRowCount++;
    var container = document.getElementById('video-rows');
    var div = document.createElement('div');
    div.className = 'video-row';
    div.setAttribute('data-index', videoRowCount - 1);
    div.innerHTML =
      '<span class="video-row-num">' + videoRowCount + '</span>' +
      '<input type="text" name="video_titles[]" placeholder="Titre (ex: Chapitre ' + videoRowCount + ')">' +
      '<input type="file" name="video_files[]" accept="video/mp4,video/webm,video/ogg">' +
      '<button type="button" class="btn-remove-video" onclick="removeVideoRow(this)">✕</button>';
    container.appendChild(div);

    // Affiche le bouton ✕ sur la première ligne si plusieurs lignes
    updateRemoveButtons();
  }

  function removeVideoRow(btn) {
    var row = btn.closest('.video-row');
    row.remove();
    // Renumérote
    var rows = document.querySelectorAll('#video-rows .video-row');
    rows.forEach(function(r, i) {
      r.querySelector('.video-row-num').textContent = i + 1;
    });
    videoRowCount = rows.length;
    updateRemoveButtons();
  }

  function updateRemoveButtons() {
    var rows = document.querySelectorAll('#video-rows .video-row');
    rows.forEach(function(r, i) {
      var btn = r.querySelector('.btn-remove-video');
      btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
    });
  }
  </script>
</body>
</html>
