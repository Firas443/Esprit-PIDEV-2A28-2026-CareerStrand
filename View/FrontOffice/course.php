<?php
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../Controller/ControlCourses.php';
require_once __DIR__ . '/../../Controller/ControlCourseVideos.php';
require_once __DIR__ . '/../../Controller/ControlCourseQuizzes.php';
require_once __DIR__ . '/../../Controller/ControlCourseProgress.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../utils/RecommendationEngine.php';
require_once __DIR__ . '/../../utils/FrontOfficePeople.php';

$frontUser = currentFrontUser();
$controlC = new ControlCourses();
$controlV = new ControlCourseVideos();
$controlQ = new ControlCourseQuizzes();
$controlP = new ControlCourseProgress();
$profileController = new ProfileController();
$frontProfile = $frontUser ? $profileController->getByUserId((int) $frontUser['userId']) : null;
$frontPerson = [
    'userId' => (int) ($frontUser['userId'] ?? 0),
    'fullName' => (string) ($frontUser['fullName'] ?? 'Guest learner'),
    'role' => (string) ($frontUser['role'] ?? 'user'),
    'photoUrl' => trim((string) ($frontProfile?->getPhotoUrl() ?? '')),
];

function course_extract_labels($value): array {
    if (is_string($value)) {
        $label = trim($value);
        return $label !== '' ? [$label] : [];
    }

    if (!is_array($value)) {
        return [];
    }

    $labels = [];
    foreach (['name', 'skillName', 'title', 'label', 'value'] as $key) {
        $candidate = trim((string) ($value[$key] ?? ''));
        if ($candidate !== '' && !is_numeric($candidate)) {
            $labels[] = $candidate;
        }
    }

    foreach ($value as $child) {
        foreach (course_extract_labels($child) as $label) {
            $labels[] = $label;
        }
    }

    return $labels;
}

function course_skill_label($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Career skill';
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $labels = course_extract_labels($decoded);
        if (!empty($labels)) {
            return implode(', ', array_slice(array_values(array_unique($labels)), 0, 3));
        }
    }

    return $raw;
}

$stmt = $controlC->listeCourse();
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all as &$course) {
    $course['Skill'] = course_skill_label($course['Skill'] ?? '');
    $course['videos'] = $controlV->getVideosByCourse($course['CourseID']);
    $course['videoCount'] = count($course['videos']);
    $course['quiz'] = $controlQ->getQuizByCourseId((int) $course['CourseID']);
    $course['quizQuestionCount'] = !empty($course['quiz']['questions']) && is_array($course['quiz']['questions'])
        ? count($course['quiz']['questions'])
        : 0;
}
unset($course);

function course_h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$categories = [];
$levels = [];
$totalVideos = 0;
$totalWeeks = 0;

foreach ($all as $course) {
    $cat = trim((string) ($course['Categorie'] ?? ''));
    $level = trim((string) ($course['Difficulty'] ?? ''));
    if ($cat !== '' && !in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
    if ($level !== '' && !in_array($level, $levels, true)) {
        $levels[] = $level;
    }
    $totalVideos += (int) ($course['videoCount'] ?? 0);
    if (preg_match('/\d+/', (string) ($course['Duration'] ?? ''), $m)) {
        $totalWeeks += (int) $m[0];
    }
}

sort($categories);
sort($levels);

$recommendationContext = $frontUser
    ? RecommendationEngine::buildProfileContext($profileController, (int) $frontUser['userId'])
    : [
        'location' => '',
        'preferences' => '',
        'careerGoal' => '',
        'bio' => '',
        'level' => 'Starter',
        'completionScore' => 0,
        'skills' => [],
        'skillNames' => [],
        'skillLevels' => [],
        'averageSkillLevel' => 0,
    ];
$recommendedCourses = RecommendationEngine::rankCourses($all, $recommendationContext, 3);
$courseProgress = $frontUser ? $controlP->getProgressRowsForUser((int) $frontUser['userId']) : [];

$courseJson = json_encode($all, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand | Education</title>
  <link rel="stylesheet" href="assets/css/front-nav.css" />
  <link rel="stylesheet" href="assets/css/course-frontoffice.css" />
</head>
<body>
<div class="learning-shell">
  <div class="learning-river" aria-hidden="true">
    <?php
      $riverIcons = [
        '<svg viewBox="0 0 64 64"><path d="M13 12h24a10 10 0 0 1 10 10v30H23a10 10 0 0 0-10 0V12Z"/><path d="M47 18h4a4 4 0 0 1 4 4v30h-8"/><path d="M21 22h16M21 31h16M21 40h11"/></svg>',
        '<svg viewBox="0 0 64 64"><path d="m15 49 8-3 27-27a6 6 0 0 0-8-8L15 38l-3 8a3 3 0 0 0 3 3Z"/><path d="m37 16 11 11"/><path d="m19 37 8 8"/></svg>',
        '<svg viewBox="0 0 64 64"><path d="M8 24 32 13l24 11-24 11L8 24Z"/><path d="M18 30v11c0 5 6 9 14 9s14-4 14-9V30"/><path d="M54 25v17"/></svg>',
        '<svg viewBox="0 0 64 64"><rect x="15" y="9" width="34" height="46" rx="6"/><path d="M23 19h18M24 31h4M36 31h4M24 41h4M36 41h4"/></svg>',
        '<svg viewBox="0 0 64 64"><path d="M29 12h6v18l13 21a4 4 0 0 1-3 6H19a4 4 0 0 1-3-6l13-21V12Z"/><path d="M24 12h16"/><path d="M22 45h20"/></svg>',
        '<svg viewBox="0 0 64 64"><path d="M18 17h28a6 6 0 0 1 6 6v24H18a6 6 0 0 0-6 6V23a6 6 0 0 1 6-6Z"/><path d="M22 27h20M22 36h15"/></svg>',
      ];

      for ($lane = 0; $lane < 3; $lane++):
    ?>
      <div class="river-lane lane-<?= $lane + 1; ?>">
        <div class="river-track">
          <?php for ($copy = 0; $copy < 2; $copy++): ?>
            <?php foreach ($riverIcons as $iconIndex => $icon): ?>
              <span class="river-icon icon-<?= $iconIndex + 1; ?>"><?= $icon; ?></span>
            <?php endforeach; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
<?php
  $activePage = 'education';
  $brandSubtitle = 'learning studio';
  include __DIR__ . '/partials/front-nav.php';
?>

<main>
  <section class="academy-hero">
    <div class="container hero-grid">
      <div class="hero-copy">
        <span class="academy-kicker">CareerStrand Education</span>
        <h1>Choose a course. Build proof. Earn your certificate.</h1>
        <p>Browse career-ready courses, enroll in the ones that fit your next move, study lesson by lesson, and leave with a completion certificate you can add to your profile.</p>
        <div class="hero-actions">
          <a class="primary-action" href="#catalog">Browse catalog</a>
          <a class="secondary-action" href="#my-learning">My learning</a>
        </div>
      </div>

      <div class="student-desk" aria-label="Learning summary">
        <div class="desk-top">
          <div class="desk-top-copy">
            <span>Student dashboard</span>
            <strong><?= course_h($frontUser['fullName'] ?? 'Guest learner'); ?></strong>
          </div>
          <a class="desk-profile-link" href="<?= course_h(frontPersonProfileUrl((int) ($frontPerson['userId'] ?? 0))); ?>" aria-label="Open your public profile">
            <span class="desk-avatar <?= frontPersonPhotoUrl($frontPerson) !== '' ? 'has-photo' : ''; ?>">
              <?php if (frontPersonPhotoUrl($frontPerson) !== '') { ?>
                <img src="<?= course_h(frontPersonPhotoUrl($frontPerson)); ?>" alt="<?= course_h(frontPersonDisplayName($frontPerson)); ?>">
              <?php } else { ?>
                <?= course_h(frontPersonInitials(frontPersonDisplayName($frontPerson))); ?>
              <?php } ?>
            </span>
          </a>
        </div>
        <div class="desk-metrics">
          <article>
            <strong><?= count($all); ?></strong>
            <span>Courses</span>
          </article>
          <article>
            <strong><?= $totalVideos; ?></strong>
            <span>Lessons</span>
          </article>
          <article>
            <strong><?= max(1, $totalWeeks); ?></strong>
            <span>Study weeks</span>
          </article>
        </div>
        <div class="desk-path">
          <div class="path-step active"><span>1</span><b>Enroll</b></div>
          <div class="path-line"></div>
          <div class="path-step"><span>2</span><b>Study</b></div>
          <div class="path-line"></div>
          <div class="path-step"><span>3</span><b>Certify</b></div>
        </div>
      </div>
    </div>
  </section>

  <section class="recommended-strip" aria-label="Personalized course recommendations">
    <div class="container">
      <div class="section-heading">
        <span>Recommended for you</span>
        <h2>Courses ranked from your skills, level, and career direction</h2>
      </div>
      <?php if (empty($recommendedCourses)) { ?>
        <div class="catalog-empty" style="margin-top:18px">
          <strong>No recommendations yet.</strong>
          <span>Add a few skills, a goal, or a location in your profile and this section will start personalizing itself.</span>
        </div>
      <?php } else { ?>
        <div class="course-market recommended-market" style="margin-top:18px">
          <?php foreach ($recommendedCourses as $recommended): 
            $course = $recommended['course'] ?? [];
            $id = (int) ($course['CourseID'] ?? 0);
            $title = trim((string) ($course['Title'] ?? 'Untitled course'));
            $category = trim((string) ($course['Categorie'] ?? 'General'));
            $skill = trim((string) ($course['Skill'] ?? 'Career skill'));
            $difficulty = trim((string) ($course['Difficulty'] ?? 'Open'));
            $duration = trim((string) ($course['Duration'] ?? 'Self-paced'));
            $durationLabel = is_numeric($duration) ? $duration . ' weeks' : $duration;
            $status = trim((string) ($course['Statut'] ?? 'Available'));
            $description = trim((string) ($course['Description'] ?? ''));
            $shortDescription = strlen($description) > 138 ? substr($description, 0, 138) . '...' : $description;
            $videoCount = (int) ($course['videoCount'] ?? 0);
          ?>
            <article class="course-tile mint recommended-course" data-course-id="<?= $id; ?>">
              <button class="course-open" type="button" data-course-id="<?= $id; ?>" aria-label="Open <?= course_h($title); ?>"></button>
              <div class="course-body">
                <div class="course-tags">
                  <span>Recommended</span>
                  <span><?= course_h($difficulty); ?></span>
                </div>
                <h3><?= course_h($title); ?></h3>
                <p><?= course_h($shortDescription); ?></p>
                <div class="course-facts">
                  <span><?= course_h($durationLabel); ?></span>
                  <span><?= $videoCount; ?> lessons</span>
                  <span><?= course_h($skill); ?></span>
                </div>
                <?php if (!empty($recommended['recommendationReasons'])) { ?>
                  <div class="course-reason-list">
                    <?php foreach (array_slice($recommended['recommendationReasons'], 0, 3) as $reason) { ?>
                      <span class="reason-chip"><?= course_h($reason); ?></span>
                    <?php } ?>
                  </div>
                <?php } ?>
              </div>
              <div class="course-footer">
                <span class="status-pill"><?= course_h((string) ($recommended['recommendationScore'] ?? 0)); ?>/100</span>
                <button class="tile-enroll" type="button" data-course-id="<?= $id; ?>">View course</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php } ?>
    </div>
  </section>

  <section class="learning-overview" id="my-learning">
    <div class="container overview-grid">
      <div class="overview-main">
        <div class="section-heading">
          <span>My learning</span>
          <h2>Keep your active courses moving</h2>
        </div>
        <div class="enrolled-strip" id="enrolled-strip">
          <div class="empty-learning">
            <strong>No courses enrolled yet</strong>
            <span>Pick a course below and your learning queue will appear here.</span>
          </div>
        </div>
      </div>

      <aside class="certificate-panel">
        <span class="panel-label">Certificate tracker</span>
        <strong id="certificate-count">0 ready</strong>
        <p>Complete every lesson in an enrolled course to unlock a downloadable CareerStrand certificate preview.</p>
      </aside>
    </div>
  </section>

  <section class="catalog-section" id="catalog">
    <div class="container">
      <div class="catalog-head">
        <div class="section-heading">
          <span>Course catalog</span>
          <h2>Explore paths like a real learning marketplace</h2>
        </div>
        <div class="catalog-count"><strong id="visible-count"><?= count($all); ?></strong> visible</div>
      </div>

      <div class="catalog-toolbar">
        <label class="search-box" for="course-search">
          <span>Search</span>
          <input id="course-search" type="search" placeholder="Search by title, skill, or category" autocomplete="off" />
        </label>
        <div class="select-row">
          <label>
            <span>Category</span>
            <select id="category-filter">
              <option value="all">All categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= course_h($cat); ?>"><?= course_h($cat); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span>Level</span>
            <select id="level-filter">
              <option value="all">All levels</option>
              <?php foreach ($levels as $level): ?>
                <option value="<?= course_h($level); ?>"><?= course_h($level); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </div>

      <?php if (empty($all)): ?>
        <div class="catalog-empty">
          <strong>No courses have been published yet.</strong>
          <span>Add courses and videos from the back office to populate this learning marketplace.</span>
        </div>
      <?php else: ?>
        <div class="course-market" id="course-market">
          <?php foreach ($all as $index => $course):
            $id = (int) ($course['CourseID'] ?? 0);
            $title = trim((string) ($course['Title'] ?? 'Untitled course'));
            $category = trim((string) ($course['Categorie'] ?? 'General'));
            $skill = trim((string) ($course['Skill'] ?? 'Career skill'));
            $difficulty = trim((string) ($course['Difficulty'] ?? 'Open'));
            $duration = trim((string) ($course['Duration'] ?? 'Self-paced'));
            $durationLabel = is_numeric($duration) ? $duration . ' weeks' : $duration;
            $status = trim((string) ($course['Statut'] ?? 'Available'));
            $description = trim((string) ($course['Description'] ?? ''));
            $shortDescription = strlen($description) > 138 ? substr($description, 0, 138) . '...' : $description;
            $videoCount = (int) ($course['videoCount'] ?? 0);
            $accent = ['mint', 'amber', 'coral', 'sky', 'steel'][$index % 5];
          ?>
            <article
              class="course-tile <?= course_h($accent); ?>"
              data-course-id="<?= $id; ?>"
              data-title="<?= course_h(strtolower($title)); ?>"
              data-category="<?= course_h($category); ?>"
              data-level="<?= course_h($difficulty); ?>"
              data-search="<?= course_h(strtolower($title . ' ' . $category . ' ' . $skill . ' ' . $description)); ?>"
            >
              <button class="course-open" type="button" data-course-id="<?= $id; ?>" aria-label="Open <?= course_h($title); ?>"></button>
              <div class="course-art">
                <span><?= course_h(substr($category, 0, 2)); ?></span>
              </div>
              <div class="course-body">
                <div class="course-tags">
                  <span><?= course_h($category); ?></span>
                  <span><?= course_h($difficulty); ?></span>
                </div>
                <h3><?= course_h($title); ?></h3>
                <p><?= course_h($shortDescription); ?></p>
                <div class="course-facts">
                  <span><?= course_h($durationLabel); ?></span>
                  <span><?= $videoCount; ?> lessons</span>
                  <span><?= course_h($skill); ?></span>
                </div>
              </div>
              <div class="course-footer">
                <span class="status-pill"><?= course_h($status); ?></span>
                <button class="tile-enroll" type="button" data-course-id="<?= $id; ?>">View course</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<div class="course-modal" id="course-modal" aria-hidden="true">
  <div class="modal-backdrop" data-close-modal></div>
  <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <button class="modal-close" type="button" data-close-modal aria-label="Close course">X</button>
    <div id="modal-content"></div>
  </section>
</div>

<footer class="footer-note">&copy; <?= date('Y'); ?> CareerStrand - education marketplace</footer>
</div>

<script>
window.CAREERSTRAND_PROFILE = <?php echo json_encode([
  'location' => trim((string) ($frontProfile?->getLocation() ?? '')),
  'preferences' => trim((string) ($frontProfile?->getPreferences() ?? '')),
  'careerGoal' => trim((string) ($frontProfile?->getCareerGoal() ?? '')),
  'bio' => trim((string) ($frontProfile?->getBio() ?? '')),
  'level' => trim((string) ($frontProfile?->getLevel() ?? 'Starter')),
  'completionScore' => (int) ($frontProfile?->getCompletionScore() ?? 0),
  'skillLevels' => array_values(array_filter(array_map(static function ($skill) {
    if (is_object($skill) && method_exists($skill, 'getSkillLevel')) {
      return $skill->getSkillLevel();
    }
    return null;
  }, $profileController->getSkillsByUserId((int) ($frontUser['userId'] ?? 0))))),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.CAREERSTRAND_COURSE_PROGRESS = <?php echo json_encode($courseProgress, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script id="course-data" type="application/json"><?= $courseJson ?: '[]'; ?></script>
<script src="./assets/js/course-frontoffice.js"></script>
</body>
</html>
