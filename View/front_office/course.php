<?php
include "C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php";
$controlC = new ControlCourses();
$stmt = $controlC->listeCourse();
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  <style>
    .dna-blur-overlay {
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
      background: rgba(0,0,0,0.5) !important;
    }

    .two-columns { display: flex; gap: 32px; align-items: flex-start; }
    .courses-list { flex: 1.2; min-width: 280px; }

    .course-detail-panel {
      flex: 0.8;
      position: sticky;
      top: 100px;
      background: rgba(7,17,38,0.92);
      border-radius: 32px;
      border: 1px solid var(--border-soft);
      padding: 24px;
      transition: all 0.2s;
      backdrop-filter: none !important;
    }

    .courses-grid { display: flex; flex-direction: column; gap: 18px; }

    .course-card {
      width: 100%; border-radius: 28px; padding: 22px;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      display: flex; flex-direction: column; gap: 14px;
      transition: border-color 0.25s, background 0.25s, transform 0.25s;
      cursor: pointer;
    }
    .course-card.selected {
      border-color: var(--blue);
      background: rgba(111,143,216,0.1);
      box-shadow: 0 0 15px rgba(111,143,216,0.3);
    }
    .course-card:hover {
      border-color: var(--border);
      background: var(--panel);
      transform: translateY(-4px);
    }

    .cat-badge {
      font-size: 11px; font-weight: 700; letter-spacing: 0.16em;
      text-transform: uppercase; padding: 4px 12px; border-radius: 999px;
      background: rgba(111,143,216,0.14); color: var(--blue-2);
      border: 1px solid rgba(111,143,216,0.2);
    }
    .status-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; }
    .status-badge.availeble, .status-badge.available { background: rgba(34,211,130,0.1); color: #4ade80; border: 1px solid rgba(34,211,130,0.2); }
    .status-badge.not, .status-badge.draft { background: rgba(255,200,60,0.1); color: #fcd34d; border: 1px solid rgba(255,200,60,0.2); }

    .course-skill-tag {
      display: inline-flex; align-items: center; gap: 6px; font-size: 12px;
      background: var(--panel-2); border-radius: 999px; padding: 5px 12px; width: fit-content;
    }
    .course-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .meta-chip {
      display: flex; align-items: center; gap: 7px;
      background: rgba(255,255,255,0.03); border: 1px solid var(--border-soft);
      border-radius: 14px; padding: 8px 12px; font-size: 13px; color: var(--muted);
    }
    .btn-view {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 700; padding: 9px 18px; border-radius: 999px;
      background: rgba(111,143,216,0.08); color: var(--blue-2);
      cursor: pointer; border: 1px solid rgba(126,159,228,0.32);
      transition: 0.2s; font-family: inherit;
    }
    .btn-view:hover { background: rgba(111,143,216,0.18); }

    /* Panneau détail */
    .detail-title { font-size: 26px; font-weight: 800; margin-bottom: 10px; color: var(--white); letter-spacing: -0.03em; }
    .detail-meta { display: flex; flex-wrap: wrap; gap: 10px; margin: 16px 0; }
    .detail-meta span { background: var(--panel-2); border: 1px solid var(--border-soft); border-radius: 999px; padding: 6px 14px; font-size: 12px; color: var(--muted); }
    .detail-description { color: var(--muted); line-height: 1.75; margin: 16px 0; font-size: 14px; }
    .detail-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 16px 0; }
    .info-box { background: var(--panel); border: 1px solid var(--border-soft); border-radius: 18px; padding: 14px; text-align: center; }
    .info-box .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.15em; color: var(--muted-2); margin-bottom: 6px; }
    .info-box .value { font-size: 18px; font-weight: 700; color: var(--white); }

    /* Vidéo */
    .detail-video-wrap { margin: 16px 0; border-radius: 18px; overflow: hidden; border: 1px solid var(--border-soft); background: rgba(0,0,0,0.4); }
    .detail-video-wrap video { width: 100%; display: block; border-radius: 18px; max-height: 240px; object-fit: cover; }
    .detail-video-label { font-size: 10px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--muted-2); margin-bottom: 8px; }

    /* Bouton Enroll */
    .enroll-wrap {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      align-items: center;
    }

    .btn-enroll-detail {
      background: linear-gradient(90deg, var(--blue), var(--red));
      border: none; padding: 14px 24px;
      border-radius: 999px; font-weight: 800;
      flex: 1;
      cursor: pointer; color: white; font-size: 15px;
      font-family: inherit; letter-spacing: 0.02em;
      transition: filter 0.2s, transform 0.2s;
    }
    .btn-enroll-detail:hover { filter: brightness(1.1); transform: translateY(-2px); }

    /* Bouton cours suivant */
    .btn-next-course {
      display: flex; align-items: center; justify-content: center;
      gap: 6px; padding: 14px 20px; border-radius: 999px;
      border: 1px solid rgba(126,159,228,0.32);
      background: rgba(111,143,216,0.08);
      color: var(--blue-2); font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: inherit;
      transition: 0.2s; white-space: nowrap;
    }
    .btn-next-course:hover { background: rgba(111,143,216,0.18); border-color: var(--blue); }
    .btn-next-course:disabled { opacity: 0.35; cursor: not-allowed; }

    /* Mini-info cours suivant */
    .next-course-hint {
      font-size: 11px; color: var(--muted-2);
      text-align: center; margin-top: 8px;
    }
    .next-course-hint strong { color: var(--muted); }

    .empty-detail { text-align: center; color: var(--muted-2); padding: 48px 0; }
    .empty-detail svg { margin-bottom: 16px; opacity: 0.25; }
    .empty-detail p { font-size: 14px; }

    .published-date { font-size: 12px; color: var(--muted-2); }
    .card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.07); }

    /* Indicateur de progression */
    .course-progress-bar {
      height: 3px; border-radius: 999px;
      background: rgba(255,255,255,0.08);
      margin-bottom: 18px; overflow: hidden;
    }
    .course-progress-fill {
      height: 100%; border-radius: 999px;
      background: linear-gradient(to right, var(--blue), var(--red));
      transition: width 0.4s ease;
    }
    .course-progress-label {
      font-size: 11px; color: var(--muted-2);
      margin-bottom: 6px; text-align: right;
    }

    @media (max-width: 900px) {
      .two-columns { flex-direction: column; }
      .course-detail-panel { position: static; margin-top: 20px; }
    }
  </style>
</head>
<body>
<canvas class="webgl-dna"></canvas>
<div class="dna-blur-overlay"></div>

<header class="site-header">
  <div class="container header-inner">
    <div class="brand">
      <div class="brand-mark"><img class="brand-logo" src="images/CareerStrand_logo.png" alt="CareerStrand logo" /></div>
      <div><div class="brand-title">CareerStrand</div><div class="brand-sub">career progression platform</div></div>
    </div>
    <nav class="nav">
      <a href="#dna">DNA</a>
      <a href="#engine">Progression</a>
      <a href="#matches">Opportunities</a>
      <a href="#command">Readiness</a>
      <a href="course.php" style="color:var(--white);font-weight:700;">Courses</a>
      <a href="http://localhost/careerstrand/View/backoffice/admin-courses.php">Management</a>
    </nav>
    <div class="header-actions">
      <button class="btn btn-ghost">Sign in</button>
      <button class="btn btn-main">Build your DNA</button>
    </div>
  </div>
</header>

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
        <div class="courses-count">
          Showing <strong id="visible-count"><?= count($all) ?></strong> of <strong><?= count($all) ?></strong> courses
        </div>
      </div>
    </div>

    <div class="two-columns">
      <!-- Liste -->
      <div class="courses-list">
        <div class="courses-grid" id="courses-grid">
          <?php foreach ($all as $course):
            $statut    = strtolower(trim($course['Statut'] ?? 'draft'));
            $diff      = strtolower(trim($course['Difficulty'] ?? ''));
            $diffClass = match(true) {
              str_contains($diff, 'easy')   => 'diff-easy',
              str_contains($diff, 'medium') => 'diff-medium',
              str_contains($diff, 'hard')   => 'diff-hard',
              default => ''
            };
            $date       = !empty($course['Published_AT']) ? date('M j, Y', strtotime($course['Published_AT'])) : '—';
            $courseJson = htmlspecialchars(json_encode($course), ENT_QUOTES, 'UTF-8');
          ?>
            <div class="course-card" data-course='<?= $courseJson ?>' data-category="<?= htmlspecialchars($course['Categorie']) ?>">
              <div class="card-top" style="display:flex;align-items:center;justify-content:space-between;">
                <span class="cat-badge"><?= htmlspecialchars($course['Categorie']) ?></span>
                <span class="status-badge <?= $statut ?>"><?= ucfirst($statut) ?></span>
              </div>
              <h3 style="font-size:19px;font-weight:800;color:var(--white);"><?= htmlspecialchars($course['Title']) ?></h3>
              <p style="font-size:14px;color:var(--muted);line-height:1.7;">
                <?= htmlspecialchars(substr($course['Description'] ?? '', 0, 100)) ?>…
              </p>
              <?php if (!empty($course['Skill'])): ?>
                <span class="course-skill-tag">✔ <?= htmlspecialchars($course['Skill']) ?></span>
              <?php endif; ?>
              <div class="course-meta">
                <div class="meta-chip">⏱️ <?= htmlspecialchars($course['Duration']) ?> sem.</div>
                <div class="meta-chip <?= $diffClass ?>">📊 <?= ucfirst($course['Difficulty']) ?></div>
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
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/>
            <path d="M2 12l10 5 10-5"/>
          </svg>
          <p>Click "View course" on any course to see details here</p>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="footer-note">
  &copy; <?= date('Y') ?> CareerStrand — career progression platform
</footer>

<script src="./assets/js/frontoffice.js"></script>
<script>
(function () {

  // ── Index du cours actuellement affiché ──
  var currentIndex = 0;
  var visibleCards = [];

  function esc(str) {
    if (!str && str !== 0) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Met à jour la liste des cartes visibles (après filtrage)
  function updateVisibleCards() {
    visibleCards = Array.from(document.querySelectorAll('.course-card'))
      .filter(c => c.style.display !== 'none');
  }

  function displayCourseByIndex(index) {
    updateVisibleCards();
    if (index < 0 || index >= visibleCards.length) return;
    currentIndex = index;

    // Sélectionne la carte dans la liste
    document.querySelectorAll('.course-card').forEach(c => c.classList.remove('selected'));
    visibleCards[currentIndex].classList.add('selected');

    // Scroll vers la carte dans la liste
    visibleCards[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    try {
      const course = JSON.parse(visibleCards[currentIndex].getAttribute('data-course'));
      displayCourseDetails(course);
    } catch (err) { console.error(err); }
  }

  function displayCourseDetails(course) {
    updateVisibleCards();

    const statusClass  = (course.Statut || 'draft').toLowerCase();
    const statusColor  = statusClass.includes('availeble') || statusClass === 'available' ? '#4ade80' : '#fcd34d';
    const publishedDate = course.Published_AT
      ? new Date(course.Published_AT).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
      : 'Not published';
    const diff = (course.Difficulty || '').toLowerCase();
    let diffColor = 'var(--muted)';
    if (diff.includes('beginner') || diff.includes('easy'))       diffColor = '#4ade80';
    else if (diff.includes('intermediate') || diff.includes('medium')) diffColor = '#fcd34d';
    else if (diff.includes('advanced') || diff.includes('hard'))  diffColor = '#ff6e45';

    // Vidéo
    let videoHtml = '';
    if (course.upload_video && course.upload_video.trim() !== '') {
      videoHtml = `
        <div class="detail-video-label">🎬 Course video</div>
        <div class="detail-video-wrap">
          <video controls preload="metadata">
            <source src="/careerstrand/${esc(course.upload_video)}" type="video/mp4">
            <source src="/careerstrand/${esc(course.upload_video)}" type="video/webm">
          </video>
        </div>`;
    }

    // Infos cours suivant
    const nextIndex = currentIndex + 1;
    const hasNext   = nextIndex < visibleCards.length;
    let nextCourse  = null;
    if (hasNext) {
      try { nextCourse = JSON.parse(visibleCards[nextIndex].getAttribute('data-course')); } catch(e) {}
    }

    // Barre de progression dans la liste
    const progressPct = visibleCards.length > 1
      ? Math.round((currentIndex / (visibleCards.length - 1)) * 100)
      : 100;

    // Hint cours suivant
    const nextHint = nextCourse
      ? `<div class="next-course-hint">Next: <strong>${esc(nextCourse.Title)}</strong></div>`
      : `<div class="next-course-hint" style="color:var(--blue-2)">🎉 Last course in the list!</div>`;

    document.getElementById('detailContent').innerHTML = `
      <div class="course-progress-label">Course ${currentIndex + 1} of ${visibleCards.length}</div>
      <div class="course-progress-bar">
        <div class="course-progress-fill" style="width:${progressPct}%"></div>
      </div>

      <h2 class="detail-title">${esc(course.Title)}</h2>

      <div class="detail-meta">
        <span>⏱️ ${esc(course.Duration)} semaine(s)</span>
        <span style="color:${diffColor}">📊 ${esc(course.Difficulty)}</span>
        <span>📅 ${publishedDate}</span>
      </div>

      <p class="detail-description">${esc(course.Description || '').replace(/\n/g, '<br>')}</p>

      <div class="detail-info-grid">
        <div class="info-box"><div class="label">Category</div><div class="value">${esc(course.Categorie)}</div></div>
        <div class="info-box"><div class="label">Status</div><div class="value" style="color:${statusColor}">${esc(course.Statut)}</div></div>
        <div class="info-box"><div class="label">Skill</div><div class="value" style="font-size:15px">${esc(course.Skill || '—')}</div></div>
        <div class="info-box"><div class="label">Course ID</div><div class="value">#${esc(String(course.CourseID))}</div></div>
      </div>

      ${videoHtml}

      <div class="enroll-wrap">
        <button class="btn-enroll-detail" id="btn-enroll">🧬 Enroll now</button>
        <button class="btn-next-course" id="btn-next" ${!hasNext ? 'disabled' : ''}>
          Next →
        </button>
      </div>
      ${nextHint}
    `;

    // ── Enroll now : passe au cours suivant ──
    document.getElementById('btn-enroll').addEventListener('click', function () {
      if (hasNext) {
        displayCourseByIndex(currentIndex + 1);
        document.getElementById('detailPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else {
        // Dernier cours — message de félicitations
        this.textContent = '✅ Enrolled!';
        this.style.background = 'linear-gradient(90deg, #22d3a5, #4ade80)';
        this.disabled = true;
        setTimeout(() => {
          this.textContent = '🧬 Enroll now';
          this.style.background = '';
          this.disabled = false;
        }, 2500);
      }
    });

    // ── Bouton Next → ──
    document.getElementById('btn-next').addEventListener('click', function () {
      if (hasNext) displayCourseByIndex(currentIndex + 1);
    });
  }

  // ── Clic sur "View course" dans la liste ──
  document.querySelectorAll('.view-course-btn').forEach(function(btn, idx) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      updateVisibleCards();
      const card = this.closest('.course-card');
      currentIndex = visibleCards.indexOf(card);
      if (currentIndex === -1) currentIndex = 0;
      document.querySelectorAll('.course-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      try {
        const course = JSON.parse(card.getAttribute('data-course'));
        displayCourseDetails(course);
        document.getElementById('detailPanel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } catch (err) { console.error(err); }
    });
  });

  // ── Filtres ──
  const pills   = document.querySelectorAll('.filter-pill');
  const cards   = document.querySelectorAll('.course-card');
  const counter = document.getElementById('visible-count');

  function applyFilter(filter) {
    let count = 0;
    cards.forEach(card => {
      const match = filter === 'all' || card.dataset.category === filter;
      card.style.display = match ? '' : 'none';
      if (match) count++;
      if (!match && card.classList.contains('selected')) {
        card.classList.remove('selected');
        document.getElementById('detailContent').innerHTML = `
          <div class="empty-detail">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
            </svg>
            <p>Select a course to see details.</p>
          </div>`;
      }
    });
    counter.textContent = count;
    // Réinitialise l'index après filtrage
    updateVisibleCards();
    currentIndex = 0;
  }

  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      pills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      applyFilter(pill.dataset.filter);
    });
  });

  // ── Premier cours par défaut ──
  updateVisibleCards();
  const firstBtn = document.querySelector('.view-course-btn');
  if (firstBtn) firstBtn.click();

})();
</script>
</body>
</html>