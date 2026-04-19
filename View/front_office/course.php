<?php
include "C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php";
$controlC = new ControlCourses();
$courses   = $controlC->listeCourse();
$all       = $courses->fetchAll(PDO::FETCH_ASSOC);

// Collect unique categories for filter pills
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
    /* ── Courses page extras (layout only, uses existing CSS variables) ── */
    .courses-hero {
      padding: 80px 0 50px;
      text-align: center;
    }

    .courses-hero .eyebrow { margin: 0 auto 22px; }

    .courses-hero h1 {
      font-size: clamp(36px, 5vw, 64px);
      line-height: 1;
      letter-spacing: -0.05em;
      color: var(--white);
      max-width: 14ch;
      margin: 0 auto 16px;
    }

    .courses-hero p {
      color: var(--muted);
      font-size: 17px;
      max-width: 480px;
      margin: 0 auto;
      line-height: 1.85;
    }

    /* ── Controls bar ── */
    .courses-controls { padding: 24px 0 32px; }

    .controls-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
    }

    .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }

    .filter-pill {
      padding: 8px 16px;
      border-radius: 999px;
      font-size: 13px;
      letter-spacing: 0.06em;
      cursor: pointer;
      border: 1px solid var(--border);
      background: var(--panel-2);
      color: var(--muted);
      font-family: inherit;
      transition: 0.22s ease;
    }

    .filter-pill:hover { background: var(--panel); color: var(--white); }

    .filter-pill.active {
      background: linear-gradient(90deg, var(--blue), var(--red));
      border-color: transparent;
      color: #fff;
      font-weight: 700;
      box-shadow: 0 0 18px rgba(255,110,69,0.22);
    }

    .courses-count { font-size: 13px; color: var(--muted-2); }
    .courses-count strong { color: var(--muted); font-weight: 600; }

    /* ── Grid ── */
    .courses-section { padding-bottom: 80px; }

    .courses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 18px;
    }

    /* ── Card ── */
    .course-card {
      border-radius: 28px;
      padding: 22px;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.08);
      display: flex;
      flex-direction: column;
      gap: 14px;
      transition: border-color 0.25s, background 0.25s, transform 0.25s, box-shadow 0.25s;
      animation: fadeUp 0.45s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .course-card:nth-child(1)  { animation-delay: .05s; }
    .course-card:nth-child(2)  { animation-delay: .10s; }
    .course-card:nth-child(3)  { animation-delay: .15s; }
    .course-card:nth-child(4)  { animation-delay: .20s; }
    .course-card:nth-child(5)  { animation-delay: .25s; }
    .course-card:nth-child(6)  { animation-delay: .30s; }
    .course-card:nth-child(7)  { animation-delay: .35s; }
    .course-card:nth-child(8)  { animation-delay: .40s; }
    .course-card:nth-child(9)  { animation-delay: .45s; }
    .course-card:nth-child(10) { animation-delay: .50s; }

    .course-card:hover {
      border-color: var(--border);
      background: var(--panel);
      transform: translateY(-4px);
      box-shadow: var(--shadow);
    }

    .card-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 8px;
    }

    .cat-badge {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      padding: 4px 12px;
      border-radius: 999px;
      background: rgba(111,143,216,0.14);
      color: var(--blue-2);
      border: 1px solid rgba(111,143,216,0.2);
      white-space: nowrap;
    }

    .status-badge {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    .status-badge.published {
      background: rgba(34,211,130,0.1);
      color: #4ade80;
      border: 1px solid rgba(34,211,130,0.2);
    }

    .status-badge.draft {
      background: rgba(255,200,60,0.1);
      color: #fcd34d;
      border: 1px solid rgba(255,200,60,0.2);
    }

    .status-badge.archived {
      background: rgba(255,110,69,0.1);
      color: var(--red-2);
      border: 1px solid rgba(255,110,69,0.2);
    }

    .course-card h3 {
      font-size: 19px;
      font-weight: 800;
      line-height: 1.25;
      letter-spacing: -0.025em;
      color: var(--white);
    }

    .course-desc {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.75;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .course-skill-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--muted);
      background: var(--panel-2);
      border: 1px solid var(--border-soft);
      padding: 5px 12px;
      border-radius: 999px;
      width: fit-content;
    }

    .course-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }

    .meta-chip {
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 13px;
      color: var(--muted);
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border-soft);
      border-radius: 14px;
      padding: 8px 12px;
    }

    .meta-chip svg { opacity: 0.55; flex-shrink: 0; }
    .meta-chip span { color: var(--text); font-weight: 600; }

    .diff-easy   { color: #4ade80 !important; }
    .diff-medium { color: #fcd34d !important; }
    .diff-hard   { color: var(--red-2) !important; }

    .card-divider { height: 1px; background: var(--border-soft); }

    .card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: auto;
    }

    .published-date { font-size: 12px; color: var(--muted-2); }

    .btn-view {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 700;
      padding: 9px 18px;
      border-radius: 999px;
      border: 1px solid rgba(126,159,228,0.32);
      background: rgba(111,143,216,0.08);
      color: var(--blue-2);
      cursor: pointer;
      font-family: inherit;
      transition: 0.22s ease;
      text-decoration: none;
    }

    .btn-view:hover {
      background: rgba(111,143,216,0.18);
      border-color: var(--blue);
      transform: translateX(2px);
    }

    .btn-view svg { transition: transform 0.2s; }
    .btn-view:hover svg { transform: translateX(3px); }

    /* ── Empty state ── */
    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 6rem 2rem;
      color: var(--muted-2);
    }

    .empty-state svg { margin-bottom: 1.2rem; opacity: 0.25; }
    .empty-state h3  { font-size: 22px; color: var(--muted); margin-bottom: 8px; }
    .empty-state p   { font-size: 14px; }

    /* ── Fix: blur overlay must stay BEHIND all page content ── */
    .dna-blur-overlay {
      z-index: 0 !important;
      backdrop-filter: blur(4px) !important;
      -webkit-backdrop-filter: blur(4px) !important;
    }

    .site-header { z-index: 50 !important; }

    .courses-hero,
    .courses-section,
    .footer-note {
      position: relative;
      z-index: 2;
    }

    @media (max-width: 768px) {
      .courses-grid   { grid-template-columns: 1fr; }
      .controls-inner { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

  <canvas class="webgl-dna"></canvas>
  <div class="dna-blur-overlay"></div>

  <!-- ── Header ── -->
  <header class="site-header">
    <div class="container header-inner">
      <div class="brand">
        <div class="brand-mark">
          <img class="brand-logo" src="images/CareerStrand_logo.png" alt="CareerStrand logo" />
        </div>
        <div>
          <div class="brand-title">CareerStrand</div>
          <div class="brand-sub">career progression platform</div>
        </div>
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


  <!-- ── Hero ── -->
  <section class="courses-hero">
    <div class="container">
      <div class="eyebrow">
        <span class="dot"></span>
        Live curriculum
      </div>
      <h1>Courses built for real career moves</h1>
      <p>Each course targets a specific strand of your professional DNA — closing gaps, unlocking roles.</p>
    </div>
  </section>


  <!-- ── Course listing ── -->
  <section class="courses-section">
    <div class="container">

      <!-- Filter + count bar -->
      <div class="courses-controls">
        <div class="controls-inner">
          <div class="filter-pills" id="filter-pills">
            <button class="filter-pill active" data-filter="all">All courses</button>
            <?php foreach ($categories as $cat): ?>
              <button class="filter-pill" data-filter="<?= htmlspecialchars($cat) ?>">
                <?= htmlspecialchars($cat) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="courses-count">
            Showing <strong id="visible-count"><?= count($all) ?></strong>
            of <strong><?= count($all) ?></strong> courses
          </div>
        </div>
      </div>

      <!-- Grid -->
      <div class="courses-grid" id="courses-grid">

        <?php if (empty($all)): ?>
          <div class="empty-state">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
              <path d="M12 2L2 7l10 5 10-5-10-5z"/>
              <path d="M2 17l10 5 10-5"/>
              <path d="M2 12l10 5 10-5"/>
            </svg>
            <h3>No courses yet</h3>
            <p>Courses will appear here once they are added from the management panel.</p>
          </div>

        <?php else: ?>
          <?php foreach ($all as $course): ?>
            <?php
              $statut    = strtolower(trim($course['Statut'] ?? 'draft'));
              $diff      = strtolower(trim($course['Difficulty'] ?? ''));
              $diffClass = match(true) {
                str_contains($diff, 'easy')   => 'diff-easy',
                str_contains($diff, 'medium') => 'diff-medium',
                str_contains($diff, 'hard')   => 'diff-hard',
                default                        => ''
              };
              $date = !empty($course['Published_AT'])
                      ? date('M j, Y', strtotime($course['Published_AT']))
                      : '—';
            ?>
            <div class="course-card" data-category="<?= htmlspecialchars($course['Categorie']) ?>">

              <!-- Category + Status -->
              <div class="card-top">
                <span class="cat-badge"><?= htmlspecialchars($course['Categorie']) ?></span>
                <span class="status-badge <?= htmlspecialchars($statut) ?>">
                  <?= htmlspecialchars(ucfirst($statut)) ?>
                </span>
              </div>

              <!-- Title -->
              <h3><?= htmlspecialchars($course['Title']) ?></h3>

              <!-- Description -->
              <p class="course-desc"><?= htmlspecialchars($course['Description']) ?></p>

              <!-- Skill -->
              <?php if (!empty($course['Skill'])): ?>
                <span class="course-skill-tag">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                  </svg>
                  <?= htmlspecialchars($course['Skill']) ?>
                </span>
              <?php endif; ?>

              <!-- Duration + Difficulty -->
              <div class="course-meta">
                <div class="meta-chip">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                  <span><?= htmlspecialchars($course['Duration']) ?></span>
                </div>
                <div class="meta-chip">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 20V10M12 20V4M6 20v-6"/>
                  </svg>
                  <span class="<?= $diffClass ?>"><?= htmlspecialchars(ucfirst($course['Difficulty'])) ?></span>
                </div>
              </div>

              <div class="card-divider"></div>

              <!-- Footer -->
              <div class="card-footer">
                <span class="published-date"><?= $date ?></span>
                <a href="course-detail.php?id=<?= (int)$course['CourseID'] ?>" class="btn-view">
                  View course
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                  </svg>
                </a>
              </div>

            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div><!-- /courses-grid -->
    </div>
  </section>


  <!-- ── Footer ── -->
  <footer class="footer-note">
    &copy; <?= date('Y') ?> CareerStrand — career progression platform
  </footer>


  <script src="./assets/js/frontoffice.js"></script>
  <script>
    const pills   = document.querySelectorAll('.filter-pill');
    const cards   = document.querySelectorAll('.course-card');
    const counter = document.getElementById('visible-count');

    function applyFilter(filter) {
      let count = 0;
      cards.forEach(card => {
        const match = filter === 'all' || card.dataset.category === filter;
        card.style.display = match ? '' : 'none';
        if (match) count++;
      });
      counter.textContent = count;
    }

    pills.forEach(pill => {
      pill.addEventListener('click', () => {
        pills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        applyFilter(pill.dataset.filter);
      });
    });
  </script>

</body>
</html>