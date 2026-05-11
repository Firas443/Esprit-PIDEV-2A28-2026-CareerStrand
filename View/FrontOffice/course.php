<?php
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../Controller/ControlCourses.php';
require_once __DIR__ . '/../../Controller/ControlCourseVideos.php';

$frontUser = currentFrontUser();
$controlC = new ControlCourses();
$controlV = new ControlCourseVideos();

$stmt = $controlC->listeCourse();
$all  = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all as &$course) {
    $course['videos'] = $controlV->getVideosByCourse($course['CourseID']);
}
unset($course);

$categories = [];
foreach ($all as $c) {
    if (!empty($c['Categorie']) && !in_array($c['Categorie'], $categories)) {
        $categories[] = $c['Categorie'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand | Courses</title>
  <link rel="stylesheet" href="assets/css/frontoffice.css" />
  <link rel="stylesheet" href="assets/css/course-frontoffice.css" />
  <link rel="stylesheet" href="assets/css/front-nav.css" />
  <style>
    .dna-blur-overlay { backdrop-filter:none!important; -webkit-backdrop-filter:none!important; background:rgba(0,0,0,0.18)!important; }
    .courses-hero,
    .courses-section { position:relative; z-index:2; }
    .courses-hero { padding:72px 0 64px; }
    .courses-hero .eyebrow { display:inline-flex; align-items:center; gap:10px; padding:10px 18px; border-radius:999px; background:rgba(255,255,255,0.045); border:1px solid rgba(255,255,255,0.08); color:var(--muted); font-size:12px; font-weight:800; letter-spacing:0.24em; text-transform:uppercase; }
    .courses-hero .eyebrow .dot { width:8px; height:8px; border-radius:50%; background:var(--red); box-shadow:0 0 18px rgba(255,110,69,0.55); }
    .courses-hero h1 { margin-top:24px; font-size:42px; line-height:1.05; color:var(--white); letter-spacing:-0.04em; }
    .courses-hero p { margin-top:8px; max-width:640px; color:var(--muted); font-size:17px; line-height:1.6; }
    .courses-section { padding:0 0 80px; }
    .courses-controls { margin-bottom:24px; }
    .controls-inner { display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap; }
    .filter-pills { display:flex; gap:10px; flex-wrap:wrap; }
    .filter-pill { border:1px solid rgba(126,159,228,0.24); background:rgba(255,255,255,0.045); color:var(--muted); border-radius:999px; padding:9px 15px; font:700 12px/1 Inter, Arial, sans-serif; letter-spacing:0.08em; text-transform:uppercase; cursor:pointer; transition:0.2s; }
    .filter-pill:hover,
    .filter-pill.active { color:var(--white); background:rgba(111,143,216,0.18); border-color:rgba(149,171,235,0.48); }
    .courses-count { color:var(--muted); font-size:14px; }
    .courses-count strong { color:var(--white); }
    .two-columns { display:flex; gap:32px; align-items:flex-start; }
    .courses-list { flex:1.2; min-width:280px; }
    .course-detail-panel { flex:0.8; position:sticky; top:100px; background:rgba(7,17,38,0.92); border-radius:32px; border:1px solid var(--border-soft); padding:24px; backdrop-filter:none!important; }
    .courses-grid { display:flex; flex-direction:column; gap:18px; }
    .course-card { width:100%; border-radius:28px; padding:22px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); display:flex; flex-direction:column; gap:14px; transition:0.25s; cursor:pointer; }
    .course-card.selected { border-color:var(--blue); background:rgba(111,143,216,0.1); box-shadow:0 0 15px rgba(111,143,216,0.3); }
    .course-card:hover { border-color:var(--border); background:var(--panel); transform:translateY(-4px); }
    .cat-badge { font-size:11px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; padding:4px 12px; border-radius:999px; background:rgba(111,143,216,0.14); color:var(--blue-2); border:1px solid rgba(111,143,216,0.2); }
    .status-badge { font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; text-transform:uppercase; }
    .status-badge.availeble,.status-badge.available { background:rgba(34,211,130,0.1); color:#4ade80; border:1px solid rgba(34,211,130,0.2); }
    .status-badge.not,.status-badge.draft { background:rgba(255,200,60,0.1); color:#fcd34d; border:1px solid rgba(255,200,60,0.2); }
    .course-skill-tag { display:inline-flex; align-items:center; gap:6px; font-size:12px; background:var(--panel-2); border-radius:999px; padding:5px 12px; width:fit-content; }
    .course-meta { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .meta-chip { display:flex; align-items:center; gap:7px; background:rgba(255,255,255,0.03); border:1px solid var(--border-soft); border-radius:14px; padding:8px 12px; font-size:13px; color:var(--muted); }
    .btn-view { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; padding:9px 18px; border-radius:999px; background:rgba(111,143,216,0.08); color:var(--blue-2); cursor:pointer; border:1px solid rgba(126,159,228,0.32); transition:0.2s; font-family:inherit; }
    .btn-view:hover { background:rgba(111,143,216,0.18); }
    .detail-title { font-size:26px; font-weight:800; margin-bottom:10px; color:var(--white); letter-spacing:-0.03em; }
    .detail-meta { display:flex; flex-wrap:wrap; gap:10px; margin:16px 0; }
    .detail-meta span { background:var(--panel-2); border:1px solid var(--border-soft); border-radius:999px; padding:6px 14px; font-size:12px; color:var(--muted); }
    .detail-description { color:var(--muted); line-height:1.75; margin:16px 0; font-size:14px; }
    .detail-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:16px 0; }
    .info-box { background:var(--panel); border:1px solid var(--border-soft); border-radius:18px; padding:14px; text-align:center; }
    .info-box .label { font-size:10px; text-transform:uppercase; letter-spacing:0.15em; color:var(--muted-2); margin-bottom:6px; }
    .info-box .value { font-size:18px; font-weight:700; color:var(--white); }
    .playlist-label { font-size:10px; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--muted-2); margin:16px 0 8px; }
    .playlist { display:flex; flex-direction:column; gap:6px; }
    .playlist-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:14px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); cursor:pointer; transition:0.2s; }
    .playlist-item:hover { background:rgba(111,143,216,0.1); }
    .playlist-item.active { background:rgba(111,143,216,0.15); border-color:rgba(111,143,216,0.3); }
    .playlist-item.done { opacity:0.6; }
    .pl-num { font-size:11px; font-weight:800; color:var(--blue-2); min-width:22px; }
    .pl-title { font-size:13px; color:var(--white); flex:1; }
    .pl-icon { font-size:13px; min-width:16px; text-align:center; }
    .video-player-wrap { margin:14px 0; border-radius:18px; overflow:hidden; border:1px solid var(--border-soft); background:#000; }
    .video-player-wrap video { width:100%; display:block; max-height:230px; object-fit:cover; }
    .video-chapter-bar { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:rgba(7,17,38,0.9); border-top:1px solid rgba(255,255,255,0.06); }
    .vcb-title { font-size:13px; font-weight:700; color:var(--white); }
    .vcb-count { font-size:11px; color:var(--muted-2); }
    .no-video { text-align:center; padding:28px; color:var(--muted-2); font-size:13px; background:rgba(255,255,255,0.02); border-radius:14px; border:1px solid var(--border-soft); margin:14px 0; }
    .enroll-wrap { display:flex; gap:10px; margin-top:18px; }
    .btn-enroll-detail { background:linear-gradient(90deg,var(--blue),var(--red)); border:none; padding:13px 22px; border-radius:999px; font-weight:800; flex:1; cursor:pointer; color:white; font-size:14px; font-family:inherit; transition:0.2s; }
    .btn-enroll-detail:hover { filter:brightness(1.1); transform:translateY(-2px); }
    .btn-enroll-detail:disabled { opacity:0.45; cursor:not-allowed; transform:none; }
    .btn-next-course { display:flex; align-items:center; gap:6px; padding:13px 18px; border-radius:999px; border:1px solid rgba(126,159,228,0.32); background:rgba(111,143,216,0.08); color:var(--blue-2); font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; transition:0.2s; white-space:nowrap; }
    .btn-next-course:hover { background:rgba(111,143,216,0.18); border-color:var(--blue); }
    .btn-next-course:disabled { opacity:0.35; cursor:not-allowed; }
    .progress-label { font-size:11px; color:var(--muted-2); text-align:right; margin-bottom:4px; }
    .progress-bar { height:3px; border-radius:999px; background:rgba(255,255,255,0.08); margin-bottom:14px; overflow:hidden; }
    .progress-fill { height:100%; border-radius:999px; background:linear-gradient(to right,var(--blue),var(--red)); transition:width 0.4s; }
    .video-progress-bar { height:2px; background:rgba(255,255,255,0.08); border-radius:999px; margin:8px 0 14px; overflow:hidden; }
    .video-progress-fill { height:100%; border-radius:999px; background:linear-gradient(to right,#6f8fd8,#4ade80); transition:width 0.4s; }
    .video-progress-label { font-size:11px; color:var(--muted-2); display:flex; justify-content:space-between; margin-bottom:4px; }
    .next-hint { font-size:11px; color:var(--muted-2); text-align:center; margin-top:8px; }
    .next-hint strong { color:var(--muted); }
    .empty-detail { text-align:center; color:var(--muted-2); padding:48px 0; }
    .empty-detail svg { margin-bottom:16px; opacity:0.25; }
    .published-date { font-size:12px; color:var(--muted-2); }
    .card-footer { display:flex; align-items:center; justify-content:space-between; padding-top:10px; border-top:1px solid rgba(255,255,255,0.07); }
    @media(max-width:900px) { .two-columns{flex-direction:column;} .course-detail-panel{position:static;margin-top:20px;} }
    .rec-video { cursor:pointer; }
    #recommendations-container { animation:fadeIn 0.3s ease; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }

    
    .rec-loading-spinner {
      display:flex; align-items:center; justify-content:center; gap:10px;
      font-size:12px; color:var(--muted-2); padding:14px; margin-top:10px;
    }
    .spinner {
      width:14px; height:14px; border:2px solid rgba(111,143,216,0.3);
      border-top-color:#6f8fd8; border-radius:50%; animation:spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform:rotate(360deg); } }
    .rec-video-card {
      display:flex; align-items:center; gap:10px; padding:10px 14px;
      border-radius:14px; background:rgba(255,255,255,0.03);
      border:1px solid rgba(255,255,255,0.06); cursor:pointer; transition:0.2s;
      margin-bottom:6px;
    }
    .rec-video-card:hover { background:rgba(111,143,216,0.12); border-color:rgba(111,143,216,0.3); }
    .rec-thumb { width:60px; height:36px; border-radius:8px; object-fit:cover; background:#111; flex-shrink:0; }
    .rec-title { font-size:12px; color:var(--white); flex:1; line-height:1.4; }
    .rec-channel { font-size:11px; color:var(--muted-2); margin-top:2px; }
    .rec-play-icon { font-size:14px; color:var(--blue-2); flex-shrink:0; }
    .rec-error { font-size:12px; color:#ff8564; text-align:center; padding:12px; }
    .rec-empty { font-size:12px; color:var(--muted-2); text-align:center; padding:12px; }
    .youtube-player-container {
      margin-top:16px; border-radius:18px; overflow:hidden;
      border:1px solid rgba(255,255,255,0.1);
    }
    .youtube-player-container iframe { display:block; width:100%; height:200px; }
  </style>
</head>
<body>
<canvas class="webgl-dna"></canvas>
<div class="dna-blur-overlay"></div>

<?php
  $activePage = 'education';
  $brandSubtitle = 'education desk';
  include __DIR__ . '/partials/front-nav.php';
?>

<section class="courses-hero">
  <div class="container">
    <div class="eyebrow"><span class="dot"></span>Live curriculum</div>
    <h1>Courses built for real career moves</h1>
    <p>Each course targets a specific strand of your professional DNA.</p>
  </div>
</section>

<section class="courses-section">
  <div class="container">
    <div class="courses-controls">
      <div class="controls-inner">
        <div class="filter-pills" id="filter-pills">
          <button class="filter-pill active" data-filter="all">All courses</button>
          <?php foreach ($categories as $cat): ?>
            <button class="filter-pill" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="courses-count">Showing <strong id="visible-count"><?= count($all) ?></strong> of <strong><?= count($all) ?></strong> courses</div>
      </div>
    </div>

    <div class="two-columns">
      <!-- Liste des cours -->
      <div class="courses-list">
        <div class="courses-grid" id="courses-grid">
          <?php foreach ($all as $course):
            $statut    = strtolower(trim($course['Statut'] ?? 'draft'));
            $diff      = strtolower(trim($course['Difficulty'] ?? ''));
            $diffClass = match(true) {
              str_contains($diff,'easy')   => 'diff-easy',
              str_contains($diff,'medium') => 'diff-medium',
              str_contains($diff,'hard')   => 'diff-hard',
              default => ''
            };
            $date = !empty($course['Published_AT']) ? date('M j, Y', strtotime($course['Published_AT'])) : '—';
            $courseJson = htmlspecialchars(json_encode($course), ENT_QUOTES, 'UTF-8');
          ?>
            <div class="course-card" data-course='<?= $courseJson ?>' data-category="<?= htmlspecialchars($course['Categorie']) ?>">
              <div class="card-top" style="display:flex;align-items:center;justify-content:space-between;">
                <span class="cat-badge"><?= htmlspecialchars($course['Categorie']) ?></span>
                <span class="status-badge <?= $statut ?>"><?= ucfirst($statut) ?></span>
              </div>
              <h3 style="font-size:19px;font-weight:800;color:var(--white);"><?= htmlspecialchars($course['Title']) ?></h3>
              <p style="font-size:14px;color:var(--muted);line-height:1.7;"><?= htmlspecialchars(substr($course['Description'] ?? '', 0, 100)) ?>…</p>
              <?php if (!empty($course['Skill'])): ?>
                <span class="course-skill-tag">✔ <?= htmlspecialchars($course['Skill']) ?></span>
              <?php endif; ?>
              <div class="course-meta">
                <div class="meta-chip">⏱️ <?= htmlspecialchars($course['Duration']) ?> sem.</div>
                <div class="meta-chip <?= $diffClass ?>">📊 <?= ucfirst($course['Difficulty']) ?></div>
                <?php if (!empty($course['videos'])): ?>
                  <div class="meta-chip" style="grid-column:span 2;">🎬 <?= count($course['videos']) ?> video(s)</div>
                <?php endif; ?>
              </div>
              <div class="card-footer">
                <span class="published-date"><?= $date ?></span>
                <button class="btn-view view-course-btn">View course →</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Panneau détail -->
      <div class="course-detail-panel" id="detailPanel">
        <div id="detailContent" class="empty-detail">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
          </svg>
          <p>Click "View course" on any course to see details here</p>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="footer-note">&copy; <?= date('Y') ?> CareerStrand — career progression platform</footer>

<script src="./assets/js/course-frontoffice.js"></script>
<script>
(function () {

  var currentCourseIndex = 0;
  var currentVideoIndex  = 0;
  var visibleCards  = [];
  var currentVideos = [];

  function esc(s) {
    if (!s && s !== 0) return '';
    return String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  function buildVideoUrl(p) {
    if (!p) return '';
    p = p.replace(/^\/+/, '');
    if (p.toLowerCase().startsWith('careerstrand/')) {
      p = p.substring('careerstrand/'.length);
    }
    if (p.startsWith('http')) return p;
    return '../../' + p;
  }

  function updateVisibleCards() {
    visibleCards = Array.from(document.querySelectorAll('.course-card'))
      .filter(function(c){ return c.style.display !== 'none'; });
  }

  function loadVideo(index) {
    if (!currentVideos[index]) return;
    currentVideoIndex = index;

    var videoEl = document.getElementById('cv-player');
    var titleEl = document.getElementById('cv-title');
    var countEl = document.getElementById('cv-count');

    if (videoEl) {
      videoEl.src = buildVideoUrl(currentVideos[index].video_path);
      videoEl.load();
      videoEl.play().catch(function(){});
    }
    if (titleEl) titleEl.textContent = currentVideos[index].title;
    if (countEl) countEl.textContent = 'Video ' + (index + 1) + ' / ' + currentVideos.length;

    document.querySelectorAll('.playlist-item').forEach(function(el, i) {
      el.classList.remove('active','done');
      el.querySelector('.pl-icon').textContent = '';
      if (i === index) { el.classList.add('active'); el.querySelector('.pl-icon').textContent = '▶'; }
      if (i < index)   { el.classList.add('done');   el.querySelector('.pl-icon').textContent = '✓'; }
    });

    var vpFill = document.getElementById('vp-fill');
    if (vpFill) {
      var pct = currentVideos.length > 1 ? Math.round((index / (currentVideos.length - 1)) * 100) : 100;
      vpFill.style.width = pct + '%';
    }
    var vpLabelLeft  = document.getElementById('vp-label-left');
    var vpLabelRight = document.getElementById('vp-label-right');
    if (vpLabelLeft)  vpLabelLeft.textContent  = 'Video ' + (index+1) + ' of ' + currentVideos.length;
    if (vpLabelRight) vpLabelRight.textContent = (index === currentVideos.length - 1) ? '✓ Completed' : Math.round(((index+1)/currentVideos.length)*100) + '%';

    updateEnrollBtn();
  }

  function updateEnrollBtn() {
    var btn = document.getElementById('btn-enroll');
    if (!btn) return;
    if (currentVideos.length === 0) {
      btn.textContent = '🧬 No videos yet';
      btn.disabled = true;
      return;
    }
    var isLast = currentVideoIndex >= currentVideos.length - 1;
    if (isLast) {
      btn.textContent = 'Restart';
      btn.disabled = false;
    } else {
      btn.textContent = '▶ Enroll now — Video ' + (currentVideoIndex + 2) + ' / ' + currentVideos.length;
      btn.disabled = false;
    }
  }

  function displayCourseDetails(course) {
    updateVisibleCards();
    currentVideos     = course.videos || [];
    currentVideoIndex = 0;

    var statusClass = (course.Statut || 'draft').toLowerCase();
    var statusColor = (statusClass.includes('availeble') || statusClass === 'available') ? '#4ade80' : '#fcd34d';
    var publishedDate = course.Published_AT
      ? new Date(course.Published_AT).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
      : 'Not published';
    var diff = (course.Difficulty || '').toLowerCase();
    var diffColor = 'var(--muted)';
    if (diff.includes('beginner')||diff.includes('easy'))            diffColor='#4ade80';
    else if (diff.includes('intermediate')||diff.includes('medium')) diffColor='#fcd34d';
    else if (diff.includes('advanced')||diff.includes('hard'))       diffColor='#ff6e45';

    var nextIdx   = currentCourseIndex + 1;
    var hasNext   = nextIdx < visibleCards.length;
    var nextTitle = '';
    if (hasNext) {
      try { nextTitle = JSON.parse(visibleCards[nextIdx].getAttribute('data-course')).Title; } catch(e){}
    }
    var progressPct = visibleCards.length > 1
      ? Math.round((currentCourseIndex / (visibleCards.length - 1)) * 100) : 100;

    var playlistHtml = '';
    if (currentVideos.length > 0) {
      playlistHtml +=
        '<div class="video-progress-label">' +
          '<span id="vp-label-left">Video 1 of ' + currentVideos.length + '</span>' +
          '<span id="vp-label-right">' + Math.round(1/currentVideos.length*100) + '%</span>' +
        '</div>' +
        '<div class="video-progress-bar"><div class="video-progress-fill" id="vp-fill" style="width:' + (currentVideos.length>1?0:100) + '%"></div></div>';

      playlistHtml += '<div class="playlist-label">🎬 Videos (' + currentVideos.length + ')</div><div class="playlist" id="playlist">';
      currentVideos.forEach(function(v, i) {
        playlistHtml +=
          '<div class="playlist-item' + (i===0?' active':'') + '" data-vi="' + i + '">' +
            '<span class="pl-num">' + (i+1) + '</span>' +
            '<span class="pl-title">' + esc(v.title) + '</span>' +
            '<span class="pl-icon">' + (i===0?'▶':'') + '</span>' +
          '</div>';
      });
      playlistHtml += '</div>';
      playlistHtml +=
        '<div class="video-player-wrap">' +
          '<video id="cv-player" controls preload="metadata">' +
            '<source src="' + buildVideoUrl(currentVideos[0].video_path) + '" type="video/mp4">' +
          '</video>' +
          '<div class="video-chapter-bar">' +
            '<span class="vcb-title" id="cv-title">' + esc(currentVideos[0].title) + '</span>' +
            '<span class="vcb-count" id="cv-count">Video 1 / ' + currentVideos.length + '</span>' +
          '</div>' +
        '</div>';
    } else {
      playlistHtml = '<div class="no-video">Aucune vidéo disponible pour ce cours.<br><small>Ajoutez des vidéos depuis le back-office.</small></div>';
    }

    var enrollLabel;
    if (currentVideos.length === 0) {
      enrollLabel = '🧬 No videos yet';
    } else if (currentVideos.length === 1) {
      enrollLabel = '▶ Enroll now';
    } else {
      enrollLabel = '▶ Enroll now — Video 2 / ' + currentVideos.length;
    }

    // recommendat
    var recommendHtml =
      '<div style="margin-top:24px;border-top:1px solid rgba(255,255,255,0.08);padding-top:20px;">' +
        '<button id="btn-recommend" class="btn-view" style="width:100%;justify-content:center;gap:8px;">' +
          ' Recommended for You' +
        '</button>' +
        '<div id="rec-loading" style="display:none;" class="rec-loading-spinner">' +
          '<div class="spinner"></div>' +
          '<span>Recherche de vidéos liées au cours...</span>' +
        '</div>' +
        '<div id="recommendations-container" style="margin-top:12px;display:none;">' +
          '<div class="playlist-label">🎥 Vidéos recommandées — <span id="rec-query-label"></span></div>' +
          '<div id="rec-videos-list"></div>' +
        '</div>' +
      '</div>';

    document.getElementById('detailContent').innerHTML =
      '<div class="progress-label">Course ' + (currentCourseIndex+1) + ' of ' + visibleCards.length + '</div>' +
      '<div class="progress-bar"><div class="progress-fill" style="width:' + progressPct + '%"></div></div>' +
      '<h2 class="detail-title">' + esc(course.Title) + '</h2>' +
      '<div class="detail-meta">' +
        '<span>⏱️ ' + esc(course.Duration) + ' sem.</span>' +
        '<span style="color:' + diffColor + '">📊 ' + esc(course.Difficulty) + '</span>' +
        '<span>📅 ' + publishedDate + '</span>' +
      '</div>' +
      '<p class="detail-description">' + esc(course.Description||'').replace(/\n/g,'<br>') + '</p>' +
      '<div class="detail-info-grid">' +
        '<div class="info-box"><div class="label">Category</div><div class="value">' + esc(course.Categorie) + '</div></div>' +
        '<div class="info-box"><div class="label">Status</div><div class="value" style="color:' + statusColor + '">' + esc(course.Statut) + '</div></div>' +
        '<div class="info-box"><div class="label">Skill</div><div class="value" style="font-size:15px">' + esc(course.Skill||'—') + '</div></div>' +
        '<div class="info-box"><div class="label">Course ID</div><div class="value">#' + esc(String(course.CourseID)) + '</div></div>' +
      '</div>' +
      playlistHtml +
      recommendHtml +
      '<div class="enroll-wrap">' +
        '<button class="btn-enroll-detail" id="btn-enroll"' + (currentVideos.length===0?' disabled':'') + '>' + enrollLabel + '</button>' +
        '<button class="btn-next-course"   id="btn-next"' + (!hasNext?' disabled':'') + '>Next course →</button>' +
      '</div>' +
      (hasNext
        ? '<div class="next-hint">Next: <strong>' + esc(nextTitle) + '</strong></div>'
        : '<div class="next-hint" style="color:var(--blue-2)"> Last course in the list!</div>');

    // Playlist clics
    var pl = document.getElementById('playlist');
    if (pl) {
      pl.querySelectorAll('.playlist-item').forEach(function(item) {
        item.addEventListener('click', function() {
          loadVideo(parseInt(this.getAttribute('data-vi')));
        });
      });
    }

    document.getElementById('btn-enroll').addEventListener('click', function() {
      if (currentVideos.length === 0) return;
      var nextVi = currentVideoIndex + 1;
      if (nextVi < currentVideos.length) {
        loadVideo(nextVi);
        var player = document.getElementById('cv-player');
        if (player) player.scrollIntoView({behavior:'smooth', block:'center'});
      } else {
        this.textContent = ' Restarting…';
        this.disabled = true;
        var self = this;
        setTimeout(function() {
          loadVideo(0);
          self.disabled = false;
        }, 1200);
      }
    });

    document.getElementById('btn-next').addEventListener('click', function() {
      if (hasNext) displayCourseByIndex(currentCourseIndex + 1);
    });

    var playerEl = document.getElementById('cv-player');
    if (playerEl) {
      playerEl.addEventListener('ended', function() {
        var nextVi = currentVideoIndex + 1;
        if (nextVi < currentVideos.length) {
          loadVideo(nextVi);
        } else {
          var btn = document.getElementById('btn-enroll');
          if (btn) { btn.textContent = 'Restart'; }
        }
      });
    }
  }

  function displayCourseByIndex(index) {
    updateVisibleCards();
    if (index < 0 || index >= visibleCards.length) return;
    currentCourseIndex = index;
    currentVideoIndex  = 0;
    document.querySelectorAll('.course-card').forEach(function(c){ c.classList.remove('selected'); });
    visibleCards[currentCourseIndex].classList.add('selected');
    visibleCards[currentCourseIndex].scrollIntoView({behavior:'smooth',block:'nearest'});
    try {
      var course = JSON.parse(visibleCards[currentCourseIndex].getAttribute('data-course'));
      displayCourseDetails(course);
      document.getElementById('detailPanel').scrollIntoView({behavior:'smooth',block:'start'});
    } catch(e){ console.error(e); }
  }

  document.querySelectorAll('.view-course-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      updateVisibleCards();
      var card = this.closest('.course-card');
      currentCourseIndex = visibleCards.indexOf(card);
      if (currentCourseIndex === -1) currentCourseIndex = 0;
      currentVideoIndex = 0;
      document.querySelectorAll('.course-card').forEach(function(c){ c.classList.remove('selected'); });
      card.classList.add('selected');
      try {
        var course = JSON.parse(card.getAttribute('data-course'));
        displayCourseDetails(course);
        document.getElementById('detailPanel').scrollIntoView({behavior:'smooth',block:'nearest'});
      } catch(e){ console.error(e); }
    });
  });

  // Filtres
  var pills   = document.querySelectorAll('.filter-pill');
  var cards   = document.querySelectorAll('.course-card');
  var counter = document.getElementById('visible-count');

  function applyFilter(filter) {
    var count = 0;
    cards.forEach(function(card) {
      var match = filter === 'all' || card.dataset.category === filter;
      card.style.display = match ? '' : 'none';
      if (match) count++;
      if (!match && card.classList.contains('selected')) {
        card.classList.remove('selected');
        document.getElementById('detailContent').innerHTML =
          '<div class="empty-detail"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg><p>Select a course to see details.</p></div>';
      }
    });
    counter.textContent = count;
    updateVisibleCards();
    currentCourseIndex = 0;
    currentVideoIndex  = 0;
  }

  pills.forEach(function(pill) {
    pill.addEventListener('click', function() {
      pills.forEach(function(p){ p.classList.remove('active'); });
      this.classList.add('active');
      applyFilter(this.dataset.filter);
    });
  });

  // ========== RECOMMANDATIONS — VERSION CORRIGÉE ==========
  document.getElementById('detailPanel').addEventListener('click', function(e) {

    // ── Bouton "Recommended for You" ──
    var recommendBtn = e.target.closest('#btn-recommend');
    if (recommendBtn) {
      var container  = document.getElementById('recommendations-container');
      var loadingDiv = document.getElementById('rec-loading');
      var listDiv    = document.getElementById('rec-videos-list');
      var queryLabel = document.getElementById('rec-query-label');

      // Toggle : si déjà ouvert, ferme
      if (container.style.display !== 'none') {
        container.style.display = 'none';
        return;
      }

      // Récupère les données du cours sélectionné
      var activeCard = document.querySelector('.course-card.selected');
      if (!activeCard) return;

      var courseData;
      try {
        courseData = JSON.parse(activeCard.getAttribute('data-course'));
      } catch(err) {
        console.error('Erreur parsing course data', err);
        return;
      }

      var title       = courseData.Title       || '';
      var category    = courseData.Categorie   || '';
      var skill       = courseData.Skill       || '';
      var description = courseData.Description || '';

      // Affiche le spinner
      loadingDiv.style.display = 'flex';
      recommendBtn.disabled = true;
      recommendBtn.textContent = ' Recherche en cours...';
      listDiv.innerHTML = '';
      container.style.display = 'none';

      // Appel au backend PHP
      var baseUrl = window.location.origin;
      var apiUrl  = baseUrl + '/Careerstrand/Controller/ControlRecommend.php';

      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title:       title,
          description: description,
          category:    category,
          skill:       skill
        })
      })
      .then(function(response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function(data) {
        loadingDiv.style.display = 'none';
        recommendBtn.disabled    = false;
        recommendBtn.textContent = ' Recommended for You';

        if (queryLabel && data.query) {
          queryLabel.textContent = '"' + data.query + '"';
        }

        if (data.videos && data.videos.length > 0) {
          listDiv.innerHTML = '';
          data.videos.forEach(function(video, idx) {
            var card = document.createElement('div');
            card.className = 'rec-video-card rec-video';
            card.setAttribute('data-video-id', video.videoId);
            card.setAttribute('data-video-title', video.title || '');

            var thumbUrl = video.thumbnail
              ? video.thumbnail
              : 'https://img.youtube.com/vi/' + video.videoId + '/mqdefault.jpg';

            card.innerHTML =
              '<img class="rec-thumb" src="' + escapeHtml(thumbUrl) + '" alt="" ' +
                'onerror="this.src=\'https://img.youtube.com/vi/' + escapeHtml(video.videoId) + '/mqdefault.jpg\'">' +
              '<div style="flex:1;min-width:0;">' +
                '<div class="rec-title">' + escapeHtml(video.title || 'Vidéo ' + (idx+1)) + '</div>' +
                (video.channelTitle
                  ? '<div class="rec-channel">' + escapeHtml(video.channelTitle) + '</div>'
                  : '') +
              '</div>' +
              '<span class="rec-play-icon">▶</span>';

            listDiv.appendChild(card);
          });
          container.style.display = 'block';
        } else {
          listDiv.innerHTML = '<div class="rec-empty">Aucune vidéo trouvée pour ce cours.</div>';
          container.style.display = 'block';
        }
      })
      .catch(function(err) {
        loadingDiv.style.display = 'none';
        recommendBtn.disabled    = false;
        recommendBtn.textContent = ' Recommended for You';
        listDiv.innerHTML = '<div class="rec-error">Erreur : ' + err.message + '</div>';
        container.style.display = 'block';
        console.error('Fetch recommendations error:', err);
      });

      return;
    }

    // ── Clic sur une vidéo recommandée → embed YouTube ──
    var recVideo = e.target.closest('.rec-video');
    if (recVideo) {
      var videoId    = recVideo.getAttribute('data-video-id');
      var videoTitle = recVideo.getAttribute('data-video-title') || '';
      if (!videoId) return;

      // Supp ancien si exis
      var oldPlayer = document.getElementById('youtube-embedded-player');
      if (oldPlayer) oldPlayer.remove();

      // Marque la carte active
      document.querySelectorAll('.rec-video-card').forEach(function(c){ c.style.borderColor = ''; });
      if (recVideo.classList.contains('rec-video-card')) {
        recVideo.style.borderColor = 'rgba(111,143,216,0.5)';
      }

      var playerDiv = document.createElement('div');
      playerDiv.id = 'youtube-embedded-player';
      playerDiv.className = 'youtube-player-container';

      playerDiv.innerHTML =
        '<iframe src="https://www.youtube.com/embed/' + escapeHtml(videoId) + '?rel=0&modestbranding=1&autoplay=1" ' +
          'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ' +
          'allowfullscreen></iframe>';

      var container = document.getElementById('recommendations-container');
      container.parentNode.insertBefore(playerDiv, container.nextSibling);
      playerDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
  }

  // Premier aff
  updateVisibleCards();
  var firstBtn = document.querySelector('.view-course-btn');
  if (firstBtn) firstBtn.click();
})();
</script>
</body>
</html>
