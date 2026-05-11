<?php
require_once __DIR__ . '/../../Controller/ControlCourses.php';
require_once __DIR__ . '/../../Controller/ControlCourseVideos.php';

$controlC  = new ControlCourses();
$controlV  = new ControlCourseVideos();

// Supp vid
if (isset($_GET['delete_video'])) {
    $controlV->deleteVideo((int)$_GET['delete_video']);
    header('Location: admin-course-videos.php?course_id=' . (int)($_GET['course_id'] ?? 0));
    exit;
}

// Upload nouv vid (recup nv donnees)
if (isset($_POST['add_video']) && isset($_FILES['video_file'])) {
    $courseId = (int)$_POST['course_id'];
    $title    = trim($_POST['video_title']) ?: 'Chapter ' . date('His');

    // Vérif err avant addvid
    $uploadErr = $_FILES['video_file']['error'];
    if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
        $uploadError = 'Fichier trop volumineux. Vérifiez php.ini (upload_max_filesize / post_max_size).';
    } elseif ($uploadErr !== UPLOAD_ERR_OK && $uploadErr !== 0) {
        $uploadError = 'Erreur upload PHP : code ' . $uploadErr;
    } else {
        $ok = $controlV->addVideo($courseId, $title, 'video_file');
        if ($ok) {
            header('Location: admin-course-videos.php?course_id=' . $courseId . '&success=1');
            exit;
        } else {
            $uploadError = 'Aucun fichier reçu ou upload échoué.';
        }
    }
}

// Cours sélec
$courseId  = (int)($_GET['course_id'] ?? 0);
$course    = $courseId ? $controlC->getCourseById($courseId) : null;
$videos    = $courseId ? $controlV->getVideosByCourse($courseId) : [];

// Liste tt les cours 
$coursesRaw = $controlC->listeCourse();
$allCourses = $coursesRaw->fetchAll(PDO::FETCH_ASSOC);

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CareerStrand — Manage Course Videos</title>
    <link rel="stylesheet" href="assets/css/admin.css" />
    <link rel="stylesheet" href="assets/css/education-admin.css" />
    <style>
        .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; margin-top: 20px; }
        .video-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; overflow: hidden; transition: 0.2s; }
        .video-card:hover { border-color: rgba(111,143,216,0.3); }
        .video-card video { width: 100%; max-height: 160px; object-fit: cover; display: block; background: #000; }
        .video-card-body { padding: 14px; }
        .video-card-title { font-size: 14px; font-weight: 700; color: #f5f3ee; margin-bottom: 4px; }
        .video-card-pos { font-size: 11px; color: rgba(255,255,255,0.4); margin-bottom: 10px; }
        .video-card-actions { display: flex; gap: 8px; }
        .no-course { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.4); }
        .select-course-form { display: flex; gap: 12px; align-items: center; margin-bottom: 28px; flex-wrap: wrap; }
        .select-course-form select { flex: 1; min-width: 200px; }
        .upload-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 22px; margin-bottom: 28px; }
        .upload-panel h4 { margin-bottom: 16px; font-size: 16px; color: #f5f3ee; }
        .empty-videos { text-align: center; padding: 40px; color: rgba(255,255,255,0.35); font-size: 14px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 14px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 18px; font-size: 14px; }
        .alert-success { background: rgba(34,211,130,0.1); border: 1px solid rgba(34,211,130,0.3); color: #4ade80; }
        .alert-error   { background: rgba(255,100,69,0.1);  border: 1px solid rgba(255,100,69,0.3);  color: #ff8564; }
        .php-info-box { background: rgba(255,200,60,0.07); border: 1px solid rgba(255,200,60,0.2); border-radius: 12px; padding: 14px 18px; margin-bottom: 18px; font-size: 13px; color: #fcd34d; }
        .php-info-box code { background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; font-size: 12px; }
        .progress-wrap { margin-top: 12px; display: none; }
        .progress-bar-ui { height: 4px; border-radius: 999px; background: rgba(255,255,255,0.08); overflow: hidden; }
        .progress-fill-ui { height: 100%; width: 0%; border-radius: 999px; background: linear-gradient(to right, #6f8fd8, #ff6e45); transition: width 0.3s; }
        .upload-status { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 6px; }

        /* Infos php.ini actuelles */
        .config-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .config-pill { font-size: 11px; padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); }
        .config-pill span { color: rgba(255,255,255,0.8); font-weight: 700; }
    </style>
</head>
<body>
<div class="admin-shell">
    <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <header class="page-header">
            <div>
                <h2>Course Videos</h2>
                <p>Ajoutez plusieurs vidéos par cours. L'utilisateur les regardera en séquence via "Enroll now".</p>
            </div>
            <div class="header-actions">
                <a href="admin-courses.php" class="btn btn-soft">← Courses</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ Vidéo uploadée avec succès !</div>
        <?php endif; ?>

        <?php if (!empty($uploadError)): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($uploadError) ?></div>
            <div class="php-info-box">
                ⚠️ Limites actuelles de PHP :
                <code>upload_max_filesize = <?= ini_get('upload_max_filesize') ?></code>
                <code>post_max_size = <?= ini_get('post_max_size') ?></code>
                <code>max_execution_time = <?= ini_get('max_execution_time') ?>s</code>
                <br><br>
                Pour augmenter les limites, modifiez <code>C:/xampp/php/php.ini</code> :<br>
                <code>upload_max_filesize = 200M</code> &nbsp;·&nbsp; <code>post_max_size = 210M</code> &nbsp;·&nbsp; <code>max_execution_time = 300</code>
                <br>Puis redémarrez Apache dans XAMPP.
            </div>
        <?php endif; ?>

        <!-- Config PHP actuelle -->
        <div class="config-pills">
            <div class="config-pill">upload_max: <span><?= ini_get('upload_max_filesize') ?></span></div>
            <div class="config-pill">post_max: <span><?= ini_get('post_max_size') ?></span></div>
            <div class="config-pill">exec_time: <span><?= ini_get('max_execution_time') ?>s</span></div>
        </div>

        <!-- Sélection du cours -->
        <form method="GET" action="admin-course-videos.php" class="select-course-form">
            <select name="course_id">
                <option value="">-- Choisir un cours --</option>
                <?php foreach ($allCourses as $c): ?>
                    <option value="<?= (int)$c['CourseID'] ?>" <?= $courseId === (int)$c['CourseID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['Title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-main">Charger</button>
        </form>

        <?php if (!$course): ?>
            <div class="no-course">
                <p>Sélectionnez un cours pour gérer ses vidéos.</p>
            </div>
        <?php else: ?>

            <!-- Info cours -->
            <div style="margin-bottom:22px;padding:16px 20px;background:rgba(111,143,216,0.08);border:1px solid rgba(111,143,216,0.2);border-radius:14px;display:flex;align-items:center;gap:14px;">
                <div>
                    <strong style="color:#95abeb"><?= htmlspecialchars($course['Title']) ?></strong>
                    <span style="color:rgba(255,255,255,0.45);font-size:13px;margin-left:12px;"><?= htmlspecialchars($course['Categorie']) ?> · <?= htmlspecialchars($course['Difficulty']) ?></span>
                </div>
                <div style="margin-left:auto;background:rgba(111,143,216,0.15);border:1px solid rgba(111,143,216,0.3);border-radius:999px;padding:4px 14px;font-size:13px;font-weight:700;color:#95abeb;">
                    🎬 <?= count($videos) ?> vidéo(s)
                </div>
            </div>

            <!-- Upload -->
            <div class="upload-panel">
                <h4>➕ Ajouter une vidéo au cours</h4>
                <form method="POST" enctype="multipart/form-data" class="field-grid" id="upload-form">
                    <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
                    <input type="hidden" name="add_video" value="1">

                    <div class="field">
                        <label>Titre du chapitre</label>
                        <input type="text" name="video_title" placeholder="ex: Introduction, Chapitre 1, Chapitre 2…">
                    </div>

                    <div class="field">
                        <label>Fichier vidéo (MP4, WebM — max <?= ini_get('upload_max_filesize') ?>)</label>
                        <input type="file" name="video_file" id="video_file" accept="video/mp4,video/webm,video/ogg" required>
                    </div>

                    <div class="progress-wrap" id="progress-wrap">
                        <div class="progress-bar-ui"><div class="progress-fill-ui" id="progress-fill"></div></div>
                        <div class="upload-status" id="upload-status">Préparation…</div>
                    </div>

                    <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
                        <button type="submit" class="btn btn-main" id="btn-upload">⬆ Uploader la vidéo</button>
                        <span id="file-size-warn" style="font-size:12px;color:#ff8564;display:none;"></span>
                    </div>
                </form>
            </div>

            <!-- Liste des vidéos -->
            <h3 style="margin-bottom:12px;font-size:18px;">Vidéos du cours (<?= count($videos) ?>)</h3>

            <?php if (empty($videos)): ?>
                <div class="empty-videos">
                    <p style="margin-bottom:6px;">Aucune vidéo ajoutée pour ce cours.</p>
                    <p style="font-size:12px;">Utilisez le formulaire ci-dessus pour ajouter la première vidéo.</p>
                </div>
            <?php else: ?>
                <div class="video-grid">
                    <?php foreach ($videos as $i => $v): ?>
                        <div class="video-card">
                            <?php
                                // Construit le bon chemin vidéo
                                $vpath = $v['video_path'];
                                $vpath = ltrim($vpath, '/');
                                if (stripos($vpath, 'careerstrand/') === 0) $vpath = substr($vpath, strlen('careerstrand/'));
                            ?>
                            <video controls preload="metadata">
                                <source src="../../<?= htmlspecialchars($vpath) ?>" type="video/mp4">
                            </video>
                            <div class="video-card-body">
                                <div class="video-card-title">
                                    <span style="color:rgba(111,143,216,0.8);margin-right:6px;">#<?= $i+1 ?></span>
                                    <?= htmlspecialchars($v['title']) ?>
                                </div>
                                <div class="video-card-pos">Position <?= (int)$v['position'] ?> · <?= htmlspecialchars($vpath) ?></div>
                                <div class="video-card-actions">
                                    <?php if ((int)$v['video_id'] > 0): ?>
                                        <a href="admin-course-videos.php?delete_video=<?= (int)$v['video_id'] ?>&course_id=<?= $courseId ?>"
                                           class="link-btn"
                                           onclick="return confirm('Supprimer cette vidéo ?')"
                                           style="color:#ff8564;border-color:rgba(255,110,69,0.3);">
                                           🗑 Supprimer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<script>
// Avertissement taille fichier avant upload
document.getElementById('video_file').addEventListener('change', function() {
    var maxBytes = <?= (int)str_replace('M','',ini_get('upload_max_filesize')) * 1024 * 1024 ?>;
    var warn = document.getElementById('file-size-warn');
    if (this.files[0] && this.files[0].size > maxBytes) {
        warn.textContent = '⚠ Fichier trop volumineux pour PHP ! (' + (this.files[0].size/1024/1024).toFixed(1) + 'MB / max ' + (maxBytes/1024/1024).toFixed(0) + 'MB)';
        warn.style.display = 'inline';
        document.getElementById('btn-upload').disabled = true;
    } else {
        warn.style.display = 'none';
        document.getElementById('btn-upload').disabled = false;
    }
});

// progression ++ l'upload
document.getElementById('upload-form').addEventListener('submit', function() {
    var wrap = document.getElementById('progress-wrap');
    var fill = document.getElementById('progress-fill');
    var status = document.getElementById('upload-status');
    wrap.style.display = 'block';
    document.getElementById('btn-upload').disabled = true;
    document.getElementById('btn-upload').textContent = '⏳ Upload en cours…';

    var pct = 0;
    var interval = setInterval(function() {
        pct = Math.min(pct + Math.random() * 8, 90);
        fill.style.width = pct + '%';
        status.textContent = 'Upload en cours… ' + Math.round(pct) + '%';
    }, 300);
});
</script>
</body>
</html>
