<?php 
    require_once __DIR__ . '/../../Controller/ControlCourses.php';
    require_once __DIR__ . '/../../Controller/ControlCourseVideos.php';
    require_once __DIR__ . '/../../Controller/ProfileController.php';

    $controlC = new ControlCourses();
    $controlV = new ControlCourseVideos();
    $profileController = new ProfileController();
    $skillCatalog = $profileController->getSkillCatalog(['active' => 1]);

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
        $submittedSkill = $_POST['Skill'] ?? '';
        if (is_array($submittedSkill)) {
            $submittedSkill = implode(', ', array_values(array_filter(array_map('trim', $submittedSkill))));
        }

        $certificateTemplates = courseCertificateTemplates();
        $certificateTemplateType = trim((string) ($_POST['certificateTemplateType'] ?? 'preset'));
        if (!in_array($certificateTemplateType, ['preset', 'custom'], true)) {
            $certificateTemplateType = 'preset';
        }

        $certificatePresetKey = trim((string) ($_POST['certificatePresetKey'] ?? 'nvidia'));
        if (!array_key_exists($certificatePresetKey, $certificateTemplates)) {
            $certificatePresetKey = 'nvidia';
        }

        $certificateBackgroundUrl = trim((string) ($_POST['certificateBackgroundUrl'] ?? ''));
        $certificateLogoUrl = trim((string) ($_POST['certificateLogoUrl'] ?? ''));
        $certificateIssuer = trim((string) ($_POST['certificateIssuer'] ?? 'CareerStrand')) ?: 'CareerStrand';
        $certificateTitle = trim((string) ($_POST['certificateTitle'] ?? 'Certificate of Completion')) ?: 'Certificate of Completion';
        $certificateSubtitle = trim((string) ($_POST['certificateSubtitle'] ?? 'This certifies that')) ?: 'This certifies that';
        $certificateSignerName = trim((string) ($_POST['certificateSignerName'] ?? 'Course Manager')) ?: 'Course Manager';
        $certificateSignatureData = trim((string) ($_POST['certificateSignatureData'] ?? ''));

        $uploadedBackground = courseCertificateUpload($_FILES['certificate_background_file'] ?? [], 'certificate-bg');
        if ($uploadedBackground !== null) {
            $certificateBackgroundUrl = $uploadedBackground;
            $certificateTemplateType = 'custom';
        }

        $uploadedLogo = courseCertificateUpload($_FILES['certificate_logo_file'] ?? [], 'certificate-logo');
        if ($uploadedLogo !== null) {
            $certificateLogoUrl = $uploadedLogo;
            if ($certificateTemplateType !== 'custom' && $certificateBackgroundUrl !== '') {
                $certificateTemplateType = 'custom';
            }
        }

        if ($certificateTemplateType !== 'custom') {
            $certificateBackgroundUrl = '';
            $certificateLogoUrl = '';
        }

        $c = new Courses(
            $_POST['Title'],
            $_POST['Description'],
            $_POST['Categorie'],
            $submittedSkill,
            $_POST['Difficulty'],
            (int)$_POST['Duration'],
            $_POST['Statut'],
            new DateTime($_POST['Published_AT']),
            null,
            $certificateTemplateType,
            $certificatePresetKey,
            $certificateBackgroundUrl !== '' ? $certificateBackgroundUrl : null,
            $certificateLogoUrl !== '' ? $certificateLogoUrl : null,
            $certificateIssuer,
            $certificateTitle,
            $certificateSubtitle,
            $certificateSignerName,
            $certificateSignatureData !== '' ? $certificateSignatureData : null
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
        'default'    => '⇅ Default sort',
        'alpha_asc'  => 'Alphabetical',
        'alpha_desc' => 'Alphabetical reverse',
        'recent'     => 'Newest',
        'oldest'     => 'Oldest',
    ];

function renderSkillChipsCourse(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '<span style="color:rgba(255,255,255,0.4)">-</span>';
    }

    $skills = [];
    if ($raw !== '' && ($raw[0] === '[' || $raw[0] === '{')) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_array($item)) {
                    $name = trim((string) ($item['name'] ?? $item['skillName'] ?? $item['value'] ?? ''));
                } else {
                    $name = trim((string) $item);
                }
                if ($name !== '') {
                    $skills[] = $name;
                }
            }
        }
    }

    if (empty($skills)) {
        $skills = array_values(array_filter(array_map('trim', preg_split('/[,;|\n]+/', $raw) ?: [])));
    }

    if (empty($skills)) {
        return '<span style="color:rgba(255,255,255,0.4)">-</span>';
    }

    $chips = array_map(static function (string $skill): string {
        return '<span class="skill-chip">' . htmlspecialchars($skill, ENT_QUOTES, 'UTF-8') . '</span>';
    }, $skills);

    return '<div class="skill-chip-list">' . implode('', $chips) . '</div>';
}

function courseCertificateTemplates(): array
{
    return [
        'nvidia' => [
            'label' => 'Nvidia-style prestige',
            'subtitle' => 'Sharp, premium, and tech-forward.',
        ],
        'aurora' => [
            'label' => 'Aurora seal',
            'subtitle' => 'Warm gradients with a polished seal feel.',
        ],
        'obsidian' => [
            'label' => 'Obsidian signature',
            'subtitle' => 'Dark executive look with clean typography.',
        ],
        'halo' => [
            'label' => 'Halo ribbon',
            'subtitle' => 'A compact award ribbon for compact courses.',
        ],
        'custom' => [
            'label' => 'Upload your own',
            'subtitle' => 'Use your own JPEG/PNG background and logo.',
        ],
    ];
}

function courseCertificateUpload(array $file, string $prefix): ?string
{
    if (empty($file['name']) || !is_string($file['name']) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        return null;
    }

    $targetDir = __DIR__ . '/../../uploads/certificates';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '-', $prefix) ?: 'cert';
    $filename = $safePrefix . '_' . uniqid('', true) . '.' . $ext;
    $target = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return null;
    }

    return 'uploads/certificates/' . $filename;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin Courses</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <link rel="stylesheet" href="assets/css/skill-multi.css" />
  <link rel="stylesheet" href="assets/css/education-admin.css" />
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
    .certificate-templates { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .template-card {
      padding:14px;
      border-radius:14px;
      background:rgba(255,255,255,0.03);
      border:1px solid rgba(255,255,255,0.08);
      cursor:pointer;
      transition:0.2s ease;
    }
    .template-card:hover { border-color: rgba(111,143,216,0.32); transform: translateY(-1px); }
    .template-card input { margin-right:8px; }
    .template-card strong { display:block; margin-bottom:4px; color:#f5f3ee; }
    .template-card span { color:rgba(255,255,255,0.5); font-size:12px; line-height:1.5; }
    .certificate-preview-swatch {
      height:70px;
      border-radius:12px;
      margin-bottom:10px;
      border:1px solid rgba(126,159,228,0.2);
      background:linear-gradient(135deg,#0b1225,#142747 46%,#0c1020);
      position:relative;
      overflow:hidden;
    }
    .certificate-preview-swatch::after{
      content:"";
      position:absolute;
      inset:12px;
      border-radius:10px;
      border:1px solid rgba(255,255,255,0.16);
      background:linear-gradient(135deg,rgba(111,143,216,0.18),rgba(255,110,69,0.08));
    }
    .certificate-preview-swatch.aurora{background:linear-gradient(135deg,#101b37,#1d2f54 48%,#1a1030)}
    .certificate-preview-swatch.obsidian{background:linear-gradient(135deg,#070b14,#111827 48%,#0d1422)}
    .certificate-preview-swatch.halo{background:linear-gradient(135deg,#091b1b,#0e2336 48%,#1d1520)}
    .certificate-preview-swatch.custom{background:linear-gradient(135deg,#29140e,#0f1628 48%,#0d1018)}
    .certificate-upload-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:12px; }
    .certificate-upload-grid .field { margin-bottom:0; }
    .certificate-modal input[type="file"] {
      width:100%;
      color:rgba(245,243,238,0.8);
      font:inherit;
      border-radius:16px;
      border:1px solid rgba(126,159,228,0.18);
      background:rgba(255,255,255,0.03);
      padding:8px;
      box-sizing:border-box;
    }
    .certificate-modal input[type="file"]::file-selector-button {
      margin-right:12px;
      border:1px solid rgba(126,159,228,0.26);
      background:linear-gradient(180deg,rgba(30,42,72,0.98),rgba(14,22,44,0.98));
      color:#f5f3ee;
      padding:10px 16px;
      border-radius:12px;
      font:inherit;
      font-weight:700;
      cursor:pointer;
      transition:0.2s ease;
    }
    .certificate-modal input[type="file"]::file-selector-button:hover {
      border-color:rgba(149,171,235,0.52);
      background:linear-gradient(180deg,rgba(38,52,90,0.98),rgba(19,31,58,0.98));
    }
    .certificate-modal input[type="file"]::-webkit-file-upload-button {
      margin-right:12px;
      border:1px solid rgba(126,159,228,0.26);
      background:linear-gradient(180deg,rgba(30,42,72,0.98),rgba(14,22,44,0.98));
      color:#f5f3ee;
      padding:10px 16px;
      border-radius:12px;
      font:inherit;
      font-weight:700;
      cursor:pointer;
      transition:0.2s ease;
    }
    .certificate-signature-box {
      margin-top:12px;
      padding:16px;
      border-radius:18px;
      background:rgba(255,255,255,0.03);
      border:1px solid rgba(126,159,228,0.16);
    }
    .certificate-signature-box .signature-head {
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:flex-start;
      margin-bottom:10px;
    }
    .certificate-signature-box .signature-head strong {
      display:block;
      color:#f5f3ee;
      font-size:14px;
      margin-bottom:4px;
    }
    .certificate-signature-box .signature-head p {
      margin:0;
      color:rgba(255,255,255,0.5);
      font-size:12px;
      line-height:1.5;
      max-width:56ch;
    }
    .signature-pad-wrap {
      border-radius:18px;
      overflow:hidden;
      border:1px solid rgba(126,159,228,0.2);
      background:
        radial-gradient(circle at top left, rgba(111,143,216,0.16), transparent 28%),
        linear-gradient(135deg, rgba(7,12,24,0.98), rgba(12,22,42,0.98));
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.05);
    }
    .signature-pad {
      width:100%;
      height:180px;
      display:block;
      cursor:crosshair;
      touch-action:none;
      user-select:none;
      -webkit-user-select:none;
    }
    .signature-actions {
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      align-items:center;
      margin-top:10px;
    }
    .signature-actions .btn {
      padding-inline:14px;
    }
    .signature-hint {
      font-size:12px;
      color:rgba(255,255,255,0.45);
      line-height:1.5;
      margin-top:8px;
    }
    .certificate-note { margin-top:8px; font-size:12px; color:rgba(255,255,255,0.45); line-height:1.5; }
    .certificate-modal {
      position:fixed;
      inset:0;
      z-index:110;
      display:none;
      align-items:center;
      justify-content:center;
      padding:24px;
    }
    .certificate-modal.is-open { display:flex; }
    .certificate-modal-backdrop {
      position:absolute;
      inset:0;
      background:rgba(2,5,14,0.74);
      backdrop-filter:blur(10px);
    }
    .certificate-modal-panel {
      position:relative;
      width:min(980px, 100%);
      max-height:min(88vh, 920px);
      overflow:auto;
      padding:24px;
      border-radius:24px;
      background:linear-gradient(180deg,rgba(12,22,46,.98),rgba(7,13,29,.98));
      border:1px solid rgba(126,159,228,.2);
      box-shadow:0 30px 90px rgba(0,0,0,.45);
    }
    .certificate-modal-head {
      display:flex;
      justify-content:space-between;
      gap:16px;
      align-items:flex-start;
      margin-bottom:18px;
    }
    .certificate-modal-head h4 {
      margin:0;
      font-size:24px;
      letter-spacing:-.03em;
      color:#f5f3ee;
    }
    .certificate-modal-head p {
      margin:6px 0 0;
      color:rgba(255,255,255,0.6);
      line-height:1.6;
      max-width:66ch;
    }
    .certificate-modal-close {
      min-width:42px;
      min-height:42px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,.12);
      background:rgba(255,255,255,.05);
      color:#f5f3ee;
      cursor:pointer;
      font-size:18px;
      line-height:1;
    }
    .certificate-modal-grid {
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
    }
    .certificate-modal-grid .field.full { grid-column:1 / -1; }

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
    <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
      <header class="page-header">
        <div><h2>Courses Management</h2><p>Manage the education module by creating courses, tracking enrollments, and shaping the learning stage of the CareerStrand progression journey.</p></div>
        <div class="header-actions"><button class="btn btn-soft">Enrollment view</button></div>
        <!-- <div class="header-actions"><a class="btn btn-soft" href="../FrontOffice/course.php">Front</a></div> -->
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
                    <strong><?= count($allCourses) ?></strong> result(s) for "<strong><?= htmlspecialchars($searchQuery) ?></strong>"
                    <?php if ($sortBy !== 'default'): ?> · sorted: <strong><?= $sortLabels[$sortBy] ?></strong><?php endif; ?>
                <?php elseif ($sortBy !== 'default'): ?>
                  Sorted: <strong><?= $sortLabels[$sortBy] ?></strong> · <strong><?= count($allCourses) ?></strong> courses
                <?php else: ?>
                  <strong><?= count($allCourses) ?></strong> courses total
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
                <th>Skills</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($allCourses)): ?>
                <tr>
                  <td colspan="6" style="text-align:center;padding:30px;color:rgba(255,255,255,0.4);">
                    No courses found for "<?= htmlspecialchars($searchQuery) ?>"
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
                  <td><?= renderSkillChipsCourse($row['Skill'] ?? null); ?></td>
                  <td class="table-actions">
                    <a class="link-btn view" href="admin-courses.php?view=<?= (int)$row['CourseID'] ?>">View</a>
                    <a class="link-btn" href="admin-courses.php?update=<?= (int)$row['CourseID'] ?>" onclick="return confirm('Update this course?')">Edit</a>
                    <a class="link-btn" href="admin-courses.php?delete=<?= (int)$row['CourseID'] ?>" onclick="return confirm('Delete this course?')">Delete</a>
                    <a class="link-btn videos" href="admin-course-videos.php?course_id=<?= (int)$row['CourseID'] ?>">🎬 Videos (<?= $nbVideos ?>)</a>
                    <a class="link-btn" href="admin-course-quizzes.php?course_id=<?= (int)$row['CourseID'] ?>" style="border-color:rgba(245,191,101,0.28);color:#f5bf65;">📝 Quiz</a>
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
                  placeholder="Enter course description"><?= htmlspecialchars($panelData['Description'] ?? '') ?></textarea>
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
                <div class="skill-multi" data-skill-multi data-skill-target="Skill" data-skill-store="json" data-skill-with-level="1" data-skill-with-primary="1" data-skill-parts-name="SkillParts[]" data-skill-datalist="skillCatalogOptions" data-skill-placeholder="Search or type a skill">
                  <input type="hidden" id="Skill" name="Skill" value="<?= htmlspecialchars($panelData['Skill'] ?? '') ?>" />
                  <div class="skill-multi-list"></div>
                  <button type="button" class="btn btn-soft btn-sm skill-multi-add">+ Add skill</button>
                  <div class="skill-helper">Add one or more skills from the shared library. They are saved as searchable course skills.</div>
                </div>
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
                  <?php foreach (['Available','Unavailable'] as $opt): ?>
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
              <div class="field">
                <label>Certificate tools</label>
                <button type="button" class="btn btn-soft" id="open-certificate-modal">Configure certificate design</button>
                <div class="certificate-note">Open the popup to choose a preset template or upload a custom certificate background and logo.</div>
              </div>

              <!-- ── Multi-upload vidéos ── -->
              <div class="field">
                <label style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;">
                  <span>🎬 Course videos</span>
                  <?php if ($mode !== 'add' && !empty($panelData['CourseID'])): ?>
                    <a href="admin-course-videos.php?course_id=<?= (int)$panelData['CourseID'] ?>"
                       style="font-size:12px;color:#4ade80;text-decoration:none;border:1px solid rgba(34,211,130,0.25);padding:3px 10px;border-radius:999px;">
                       Manage videos →
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
                          <source src="../../<?= htmlspecialchars($evpath) ?>" type="video/mp4">
                        </video>
                        <?php if ((int)$ev['video_id'] > 0): ?>
                          <a href="admin-course-videos.php?delete_video=<?= (int)$ev['video_id'] ?>&course_id=<?= (int)$panelData['CourseID'] ?>"
                             onclick="return confirm('Delete this video?')"
                             style="font-size:11px;color:#ff8564;text-decoration:none;border:1px solid rgba(255,110,69,0.2);padding:3px 8px;border-radius:6px;">🗑</a>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($mode !== 'view'): ?>
                <div class="video-upload-zone">
                  <label>Add one or more videos:</label>
                  <div id="video-rows">
                    <div class="video-row" data-index="0">
                      <span class="video-row-num">1</span>
                      <input type="text" name="video_titles[]" placeholder="Title (e.g. Introduction)">
                      <input type="file" name="video_files[]" accept="video/mp4,video/webm,video/ogg">
                      <button type="button" class="btn-remove-video" onclick="removeVideoRow(this)" style="display:none;">✕</button>
                    </div>
                  </div>
                  <button type="button" class="btn-add-video-row" onclick="addVideoRow()">+ Add another video</button>
                  <div class="php-limits">
                    PHP limit: <code><?= ini_get('upload_max_filesize') ?></code> per file · <code><?= ini_get('post_max_size') ?></code> total
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

          <div class="certificate-modal" id="certificate-modal" aria-hidden="true">
            <div class="certificate-modal-backdrop" data-certificate-close></div>
            <section class="certificate-modal-panel" role="dialog" aria-modal="true" aria-labelledby="certificate-modal-title">
              <div class="certificate-modal-head">
                <div>
                  <h4 id="certificate-modal-title">Certificate design</h4>
                  <p>Pick a ready-made template or upload your own background and logo. The completed certificate will still render with the learner name and issue date automatically.</p>
                </div>
                <button type="button" class="certificate-modal-close" data-certificate-close aria-label="Close certificate modal">×</button>
              </div>

              <div class="certificate-modal-grid">
                <div class="field full">
                  <label>Certificate template</label>
                  <div class="certificate-templates">
                    <?php foreach (courseCertificateTemplates() as $key => $template): ?>
                      <label class="template-card">
                        <div class="certificate-preview-swatch <?= htmlspecialchars($key); ?>"></div>
                        <input type="radio" name="certificatePresetKey" value="<?= htmlspecialchars($key); ?>" <?= ($panelData['certificatePresetKey'] ?? 'nvidia') === $key ? 'checked' : ''; ?>>
                        <strong><?= htmlspecialchars($template['label']); ?></strong>
                        <span><?= htmlspecialchars($template['subtitle']); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <input type="hidden" name="certificateTemplateType" id="certificateTemplateType" value="<?= htmlspecialchars($panelData['certificateTemplateType'] ?? 'preset'); ?>">
                  <div class="certificate-note">Choose one preset, or switch to the custom upload fields below. The template choice only affects the certificate artwork.</div>
                </div>

                <div class="field">
                  <label>Certificate issuer</label>
                  <input type="text" name="certificateIssuer" value="<?= htmlspecialchars($panelData['certificateIssuer'] ?? 'CareerStrand'); ?>" placeholder="CareerStrand" />
                </div>

                <div class="field">
                  <label>Certificate title</label>
                  <input type="text" name="certificateTitle" value="<?= htmlspecialchars($panelData['certificateTitle'] ?? 'Certificate of Completion'); ?>" placeholder="Certificate of Completion" />
                </div>

                <div class="field full">
                  <label>Certificate subtitle</label>
                  <input type="text" name="certificateSubtitle" value="<?= htmlspecialchars($panelData['certificateSubtitle'] ?? 'This certifies that'); ?>" placeholder="This certifies that" />
                </div>

                <div class="field">
                  <label>Manager name</label>
                  <input type="text" name="certificateSignerName" value="<?= htmlspecialchars($panelData['certificateSignerName'] ?? 'Course Manager'); ?>" placeholder="Course Manager" />
                </div>

                <div class="field">
                  <label>Manager role</label>
                  <input type="text" value="Course manager" readonly />
                </div>

                <div class="field">
                  <label>Custom certificate background (JPEG / PNG / WebP)</label>
                  <input type="file" name="certificate_background_file" accept="image/png,image/jpeg,image/webp">
                  <input type="hidden" name="certificateBackgroundUrl" value="<?= htmlspecialchars($panelData['certificateBackgroundUrl'] ?? ''); ?>">
                </div>

                <div class="field">
                  <label>Custom logo overlay</label>
                  <input type="file" name="certificate_logo_file" accept="image/png,image/jpeg,image/webp">
                  <input type="hidden" name="certificateLogoUrl" value="<?= htmlspecialchars($panelData['certificateLogoUrl'] ?? ''); ?>">
                </div>

                <div class="field full">
                  <div class="certificate-signature-box">
                    <div class="signature-head">
                      <div>
                        <strong>Draw manager signature</strong>
                        <p>Sign with your mouse, trackpad, or touchscreen. The drawn signature will be embedded on the learner certificate.</p>
                      </div>
                      <button type="button" class="btn btn-soft btn-sm" id="certificate-signature-clear">Clear signature</button>
                    </div>
                    <div class="signature-pad-wrap">
                      <canvas id="certificate-signature-pad" class="signature-pad" width="900" height="220"></canvas>
                    </div>
                    <input type="hidden" name="certificateSignatureData" id="certificateSignatureData" value="<?= htmlspecialchars($panelData['certificateSignatureData'] ?? ''); ?>">
                    <div class="signature-actions">
                      <span class="certificate-note" style="margin:0;">The signature is saved as part of the course certificate settings.</span>
                    </div>
                    <div class="signature-hint">Tip: keep the signature centered and use a slightly slower stroke for a cleaner certificate mark.</div>
                  </div>
                </div>
              </div>
            </section>
          </div>
        </aside>
      </section>
    </main>
  </div>

  <datalist id="skillCatalogOptions">
    <?php foreach ($skillCatalog as $skill): ?>
      <option value="<?= htmlspecialchars((string) ($skill['skillName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?= htmlspecialchars((string) ($skill['skillName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      </option>
    <?php endforeach; ?>
  </datalist>
  <script src="assets/js/admin.js"></script>
  <script src="assets/js/skill-multi.js"></script>
  <script src="./assets/js/courses.js"></script>
  <script>
  (function () {
    var templateType = document.getElementById('certificateTemplateType');
    var radios = document.querySelectorAll('input[name="certificatePresetKey"]');
    var openButton = document.getElementById('open-certificate-modal');
    var modal = document.getElementById('certificate-modal');
    var closeButtons = document.querySelectorAll('[data-certificate-close]');
    if (!templateType || !radios.length) return;
    function syncTemplateType() {
      var selected = document.querySelector('input[name="certificatePresetKey"]:checked');
      templateType.value = selected && selected.value === 'custom' ? 'custom' : 'preset';
    }
    radios.forEach(function (radio) {
      radio.addEventListener('change', syncTemplateType);
    });
    if (openButton && modal) {
      openButton.addEventListener('click', function () {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      });
    }
    if (modal) {
      closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
        });
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
        }
      });
    }
    syncTemplateType();
  })();

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
  <script>
  (function () {
    var canvas = document.getElementById('certificate-signature-pad');
    var hidden = document.getElementById('certificateSignatureData');
    var clearButton = document.getElementById('certificate-signature-clear');
    var openButton = document.getElementById('open-certificate-modal');
    var modal = document.getElementById('certificate-modal');
    if (!canvas || !hidden) return;

    var ctx = canvas.getContext('2d');
    var drawing = false;
    var lastPoint = null;
    var saveTimer = null;

    function renderStoredSignature() {
      if (!hidden.value) return;
      var rect = canvas.getBoundingClientRect();
      var width = Math.max(1, rect.width);
      var height = Math.max(1, rect.height);
      var img = new Image();
      img.onload = function () {
        var ratio = Math.min(width / img.width, height / img.height, 1);
        var drawWidth = img.width * ratio;
        var drawHeight = img.height * ratio;
        var drawLeft = (width - drawWidth) / 2;
        var drawTop = (height - drawHeight) / 2;
        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(img, drawLeft, drawTop, drawWidth, drawHeight);
      };
      img.src = hidden.value;
    }

    function sizeCanvas() {
      var rect = canvas.getBoundingClientRect();
      var width = Math.max(1, Math.round(rect.width));
      var height = Math.max(1, Math.round(rect.height));
      var dpr = window.devicePixelRatio || 1;
      canvas.width = Math.round(width * dpr);
      canvas.height = Math.round(height * dpr);
      canvas.style.width = width + 'px';
      canvas.style.height = height + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0, 0, width, height);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#f5f3ee';
      ctx.lineWidth = 4;
      ctx.shadowColor = 'rgba(255,255,255,0.12)';
      ctx.shadowBlur = 1;
      renderStoredSignature();
    }

    function canvasPoint(clientX, clientY) {
      var rect = canvas.getBoundingClientRect();
      return {
        x: clientX - rect.left,
        y: clientY - rect.top
      };
    }

    function touchPoint(event) {
      var touch = event.touches && event.touches[0] ? event.touches[0] : (event.changedTouches && event.changedTouches[0] ? event.changedTouches[0] : null);
      if (!touch) return null;
      return canvasPoint(touch.clientX, touch.clientY);
    }

    function persistSignature() {
      hidden.value = canvas.toDataURL('image/png');
    }

    function beginStroke(event) {
      event.preventDefault();
      drawing = true;
      lastPoint = event.touches ? touchPoint(event) : canvasPoint(event.clientX, event.clientY);
      if (!lastPoint) return;
      ctx.beginPath();
      ctx.arc(lastPoint.x, lastPoint.y, 1.5, 0, Math.PI * 2);
      ctx.fillStyle = ctx.strokeStyle;
      ctx.fill();
    }

    function continueStroke(event) {
      if (!drawing || !lastPoint) return;
      event.preventDefault();
      var nextPoint = event.touches ? touchPoint(event) : canvasPoint(event.clientX, event.clientY);
      if (!nextPoint) return;
      ctx.beginPath();
      ctx.moveTo(lastPoint.x, lastPoint.y);
      ctx.lineTo(nextPoint.x, nextPoint.y);
      ctx.stroke();
      lastPoint = nextPoint;
      if (saveTimer) {
        window.clearTimeout(saveTimer);
      }
      saveTimer = window.setTimeout(persistSignature, 100);
    }

    function finishStroke(event) {
      if (!drawing) return;
      event.preventDefault();
      drawing = false;
      lastPoint = null;
      if (saveTimer) {
        window.clearTimeout(saveTimer);
        saveTimer = null;
      }
      persistSignature();
    }

    function clearSignature() {
      hidden.value = '';
      var rect = canvas.getBoundingClientRect();
      ctx.clearRect(0, 0, rect.width, rect.height);
    }

    canvas.addEventListener('pointerdown', beginStroke);
    canvas.addEventListener('pointermove', continueStroke);
    canvas.addEventListener('pointerup', finishStroke);
    canvas.addEventListener('pointercancel', finishStroke);
    canvas.addEventListener('pointerleave', finishStroke);
    canvas.addEventListener('mousedown', beginStroke);
    canvas.addEventListener('mousemove', continueStroke);
    window.addEventListener('mouseup', finishStroke);
    canvas.addEventListener('touchstart', beginStroke, { passive: false });
    canvas.addEventListener('touchmove', continueStroke, { passive: false });
    canvas.addEventListener('touchend', finishStroke, { passive: false });
    canvas.addEventListener('touchcancel', finishStroke, { passive: false });
    canvas.addEventListener('contextmenu', function (event) {
      event.preventDefault();
    });

    if (clearButton) {
      clearButton.addEventListener('click', clearSignature);
    }

    function sizeSoon() {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(sizeCanvas);
      });
    }

    sizeCanvas();
    if (openButton && modal) {
      openButton.addEventListener('click', sizeSoon);
    }
    if (modal) {
      modal.addEventListener('transitionend', sizeSoon);
    }
    window.addEventListener('resize', function () {
      sizeSoon();
    });
  })();
  </script>
</body>
</html>
