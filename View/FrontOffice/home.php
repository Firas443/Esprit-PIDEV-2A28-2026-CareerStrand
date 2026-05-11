<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';

$frontUser = requireFrontUser();
$activePage = 'home';
$brandSubtitle = 'career command center';
$pdo = config::getConnexion();
$userId = (int) ($frontUser['userId'] ?? 0);
$fullName = trim((string) ($frontUser['fullName'] ?? 'CareerStrand user'));
$firstName = trim(explode(' ', $fullName)[0] ?? 'there');

function homeEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function homeTableExists(PDO $pdo, string $table): bool
{
    try {
        $query = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $query->execute([$table]);
        return (int) $query->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function homeCountRows(PDO $pdo, string $table, string $where = '1 = 1'): int
{
    if (!homeTableExists($pdo, $table)) {
        return 0;
    }

    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `$table` WHERE $where")->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function homeFetchRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $query = $pdo->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function homePercent(int $part, int $total): int
{
    return $total > 0 ? max(0, min(100, (int) round(($part / $total) * 100))) : 0;
}

$profile = homeFetchRows($pdo, 'SELECT * FROM Profile WHERE userId = :userId LIMIT 1', ['userId' => $userId])[0] ?? null;
$profileScore = max(0, min(100, (int) ($profile['completionScore'] ?? 0)));
$profileLevel = trim((string) ($profile['level'] ?? 'Starter'));
$profileBio = trim((string) ($profile['bio'] ?? 'Keep building your profile so your work, skills, and preferences tell a stronger story.'));

$skills = homeFetchRows(
    $pdo,
    'SELECT skillName, level FROM UserSkill WHERE userId = :userId ORDER BY level DESC, skillName ASC LIMIT 5',
    ['userId' => $userId]
);

$joinedHubs = homeFetchRows(
    $pdo,
    "SELECT sh.groupId, sh.name, sh.category, gm.joinedAt
     FROM GroupMember gm
     INNER JOIN SkillHub sh ON sh.groupId = gm.groupId
     WHERE gm.userId = :userId
     ORDER BY gm.joinedAt DESC, gm.groupMemberId DESC
     LIMIT 4",
    ['userId' => $userId]
);

$learningPlans = homeFetchRows(
    $pdo,
    "SELECT c.calendarId, c.progress, c.status, co.title, co.category, co.difficulty
     FROM Calendar c
     LEFT JOIN Course co ON co.courseId = c.courseId
     WHERE c.userId = :userId
     ORDER BY c.startDate DESC, c.calendarId DESC
     LIMIT 3",
    ['userId' => $userId]
);

$applications = homeFetchRows(
    $pdo,
    "SELECT a.status, a.compatibilityScore, a.appliedAt, o.title, o.type
     FROM Application a
     LEFT JOIN Opportunity o ON o.opportunityId = a.opportunityId
     WHERE a.userId = :userId
     ORDER BY a.appliedAt DESC, a.applicationId DESC
     LIMIT 3",
    ['userId' => $userId]
);

$recommendedChallenges = homeFetchRows(
    $pdo,
    "SELECT c.challengeId, c.groupId, c.title, c.type, c.difficulty, c.deadline, sh.name AS hubName
     FROM Challenge c
     LEFT JOIN SkillHub sh ON sh.groupId = c.groupId
     WHERE LOWER(COALESCE(c.status, '')) IN ('published', 'active', 'open')
     ORDER BY c.createdAt DESC, c.challengeId DESC
     LIMIT 3"
);

$upcomingEvents = homeFetchRows(
    $pdo,
    "SELECT eventId, title, type, eventMode, date, location
     FROM Event
     WHERE date IS NULL OR date >= CURDATE()
     ORDER BY date IS NULL ASC, date ASC, eventId DESC
     LIMIT 3"
);

$openOpportunities = homeFetchRows(
    $pdo,
    "SELECT opportunityId, title, type, category, requiredLevel, deadline
     FROM Opportunity
     WHERE LOWER(COALESCE(status, '')) IN ('published', 'active', 'open')
     ORDER BY deadline IS NULL ASC, deadline ASC, opportunityId DESC
     LIMIT 3"
);

$mySubmissionCount = homeCountRows(
    $pdo,
    'Submission',
    'groupMemberId IN (SELECT groupMemberId FROM GroupMember WHERE userId = ' . $userId . ')'
);
$reviewedSubmissionCount = homeCountRows(
    $pdo,
    'Submission',
    'score IS NOT NULL AND groupMemberId IN (SELECT groupMemberId FROM GroupMember WHERE userId = ' . $userId . ')'
);
$myApplicationCount = homeCountRows($pdo, 'Application', 'userId = ' . $userId);
$myEventCount = homeCountRows($pdo, 'Participation', 'userId = ' . $userId);
$learningCount = count($learningPlans);
$readinessScore = max(
    12,
    min(
        100,
        (int) round(($profileScore * 0.45)
            + (min(5, count($skills)) * 7)
            + (min(3, count($joinedHubs)) * 7)
            + (min(3, $mySubmissionCount) * 6)
            + (min(2, $myApplicationCount) * 5))
    )
);

$nextSteps = [
    [
        'title' => $profileScore >= 75 ? 'Refresh your profile story' : 'Complete your profile',
        'copy' => $profileScore >= 75 ? 'Your foundation is strong. Add recent work and sharpen your preferences.' : 'Your profile is the first signal managers and opportunities see.',
        'href' => 'profile.php',
    ],
    [
        'title' => empty($learningPlans) ? 'Start a learning path' : 'Continue your learning plan',
        'copy' => empty($learningPlans) ? 'Pick a course that supports the kind of work you want next.' : 'Keep your course progress moving so it compounds into practical proof.',
        'href' => 'course.php',
    ],
    [
        'title' => $mySubmissionCount > 0 ? 'Submit another challenge' : 'Ship your first challenge',
        'copy' => $mySubmissionCount > 0 ? 'Challenge submissions are becoming your strongest proof layer.' : 'A submission turns learning into visible proof of skill.',
        'href' => 'skillhub.php',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand | Home</title>
    <link rel="stylesheet" href="assets/css/frontoffice.css">
    <link rel="stylesheet" href="assets/css/front-nav.css">
    <link rel="stylesheet" href="assets/css/home.css">
</head>
<body class="home-page">
    <canvas class="webgl-dna"></canvas>
    <div class="dna-blur-overlay"></div>

    <?php include __DIR__ . '/partials/front-nav.php'; ?>

    <main class="home-main">
        <section class="home-hero">
            <div class="container home-hero-grid">
                <div class="home-hero-copy">
                    <div class="home-eyebrow"><span></span> Signed-in workspace</div>
                    <h1>Welcome back, <?= homeEscape($firstName); ?>.</h1>
                    <p>Your front-office home brings together your profile, learning, Skill Hub practice, events, and opportunities without changing the public landing page.</p>
                    <div class="home-hero-actions">
                        <a class="primary-btn" href="profile.php">Open profile</a>
                        <a class="ghost-btn" href="opportunities.php">Find opportunities</a>
                    </div>
                </div>

                <aside class="home-readiness-card">
                    <div class="home-card-top">
                        <span>Career readiness</span>
                        <strong><?= homeEscape($profileLevel); ?></strong>
                    </div>
                    <div class="home-orbit" style="--score: <?= $readinessScore; ?>%;">
                        <div>
                            <strong><?= $readinessScore; ?></strong>
                            <span>/100</span>
                        </div>
                    </div>
                    <div class="home-signal-grid">
                        <div><span>Profile</span><strong><?= $profileScore; ?>%</strong></div>
                        <div><span>Skills</span><strong><?= count($skills); ?></strong></div>
                        <div><span>Hubs</span><strong><?= count($joinedHubs); ?></strong></div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="container home-metrics">
            <article><span>Learning plans</span><strong><?= $learningCount; ?></strong><small>courses in progress</small></article>
            <article><span>Submissions</span><strong><?= $mySubmissionCount; ?></strong><small><?= $reviewedSubmissionCount; ?> reviewed</small></article>
            <article><span>Applications</span><strong><?= $myApplicationCount; ?></strong><small>opportunity attempts</small></article>
            <article><span>Events</span><strong><?= $myEventCount; ?></strong><small>registrations</small></article>
        </section>

        <section class="container home-section">
            <div class="home-section-head">
                <div>
                    <span>Next best moves</span>
                    <h2>A focused path for today.</h2>
                </div>
                <a class="home-link" href="skillhub.php">Open Skill Hub</a>
            </div>
            <div class="home-next-grid">
                <?php foreach ($nextSteps as $step) { ?>
                    <a class="home-step-card" href="<?= homeEscape($step['href']); ?>">
                        <span></span>
                        <h3><?= homeEscape($step['title']); ?></h3>
                        <p><?= homeEscape($step['copy']); ?></p>
                    </a>
                <?php } ?>
            </div>
        </section>

        <section class="container home-section">
            <div class="home-board-grid">
                <article class="home-panel home-profile-panel">
                    <div class="home-card-top">
                        <span>Profile foundation</span>
                        <strong><?= $profileScore; ?>%</strong>
                    </div>
                    <h3><?= $profile ? homeEscape($profileLevel) . ' profile' : 'Profile setup needed'; ?></h3>
                    <p><?= homeEscape($profileBio); ?></p>
                    <div class="home-progress"><span style="width: <?= max(4, $profileScore); ?>%;"></span></div>
                    <?php if (!empty($skills)) { ?>
                        <div class="home-chip-list">
                            <?php foreach ($skills as $skill) { ?>
                                <span><?= homeEscape($skill['skillName'] ?? 'Skill'); ?> <?= homeEscape((string) ($skill['level'] ?? '')); ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </article>

                <article class="home-panel">
                    <div class="home-card-top">
                        <span>Joined hubs</span>
                        <strong><?= count($joinedHubs); ?></strong>
                    </div>
                    <div class="home-list">
                        <?php foreach ($joinedHubs as $hub) { ?>
                            <a href="hub.php?groupId=<?= (int) $hub['groupId']; ?>">
                                <strong><?= homeEscape($hub['name'] ?? 'Skill Hub'); ?></strong>
                                <span><?= homeEscape($hub['category'] ?? 'Community'); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($joinedHubs)) { ?>
                            <p class="home-empty">Join a hub to unlock challenges and discussions.</p>
                        <?php } ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="container home-section">
            <div class="home-section-head">
                <div>
                    <span>Live opportunities</span>
                    <h2>Explore what you can act on next.</h2>
                </div>
            </div>
            <div class="home-resource-grid">
                <article class="home-panel">
                    <div class="home-card-top"><span>Learning</span><strong><?= count($learningPlans); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($learningPlans as $plan) { ?>
                            <a href="course.php">
                                <strong><?= homeEscape($plan['title'] ?? 'Course plan'); ?></strong>
                                <span><?= homeEscape(($plan['category'] ?? 'Learning') . ' / ' . ($plan['progress'] ?? 0) . '%'); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($learningPlans)) { ?>
                            <a href="course.php"><strong>Browse courses</strong><span>Start a structured learning path</span></a>
                        <?php } ?>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-card-top"><span>Challenges</span><strong><?= count($recommendedChallenges); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($recommendedChallenges as $challenge) { ?>
                            <a href="thread.php?challengeId=<?= (int) $challenge['challengeId']; ?>">
                                <strong><?= homeEscape($challenge['title'] ?? 'Challenge'); ?></strong>
                                <span><?= homeEscape(($challenge['hubName'] ?? 'Skill Hub') . ' / ' . ($challenge['difficulty'] ?? 'Open')); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($recommendedChallenges)) { ?>
                            <p class="home-empty">No published challenges available right now.</p>
                        <?php } ?>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-card-top"><span>Opportunities</span><strong><?= count($openOpportunities); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($openOpportunities as $opportunity) { ?>
                            <a href="opportunities.php">
                                <strong><?= homeEscape($opportunity['title'] ?? 'Opportunity'); ?></strong>
                                <span><?= homeEscape(($opportunity['type'] ?? 'Role') . ' / ' . ($opportunity['requiredLevel'] ?? 'Open level')); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($openOpportunities)) { ?>
                            <p class="home-empty">No live opportunities available right now.</p>
                        <?php } ?>
                    </div>
                </article>

                <article class="home-panel">
                    <div class="home-card-top"><span>Events</span><strong><?= count($upcomingEvents); ?></strong></div>
                    <div class="home-list">
                        <?php foreach ($upcomingEvents as $event) { ?>
                            <a href="events.php">
                                <strong><?= homeEscape($event['title'] ?? 'Event'); ?></strong>
                                <span><?= homeEscape(($event['eventMode'] ?? 'Event') . ' / ' . ($event['date'] ?? 'Date TBA')); ?></span>
                            </a>
                        <?php } ?>
                        <?php if (empty($upcomingEvents)) { ?>
                            <p class="home-empty">No upcoming events available right now.</p>
                        <?php } ?>
                    </div>
                </article>
            </div>
        </section>

        <?php if (!empty($applications)) { ?>
            <section class="container home-section home-last-section">
                <div class="home-section-head">
                    <div>
                        <span>Application activity</span>
                        <h2>Your recent opportunity moves.</h2>
                    </div>
                    <a class="home-link" href="opportunities.php">Open opportunities</a>
                </div>
                <div class="home-application-strip">
                    <?php foreach ($applications as $application) { ?>
                        <article>
                            <span><?= homeEscape($application['status'] ?? 'pending'); ?></span>
                            <strong><?= homeEscape($application['title'] ?? 'Opportunity'); ?></strong>
                            <small><?= homeEscape(($application['compatibilityScore'] ?? 0) . '% compatibility'); ?></small>
                        </article>
                    <?php } ?>
                </div>
            </section>
        <?php } ?>
    </main>
    <script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
    <script src="assets/js/home-dna.js"></script>
</body>
</html>
