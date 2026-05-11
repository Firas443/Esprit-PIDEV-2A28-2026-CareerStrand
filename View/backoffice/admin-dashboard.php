<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../utils/AuthRedirect.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}

$user = $_SESSION['user'];

if (!isBackOfficeRole($user['role'] ?? '')) {
    header('Location: ../FrontOffice/profile.php');
    exit;
}

$pdo = config::getConnexion();
$controller = new UserController();
$stats = $controller->getStats();

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboardTableExists(PDO $pdo, string $table): bool
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

function dashboardCount(PDO $pdo, string $table, string $where = '1 = 1'): int
{
    if (!dashboardTableExists($pdo, $table)) {
        return 0;
    }

    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `$table` WHERE $where")->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function dashboardRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $query = $pdo->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function dashboardPercent(int $part, int $total): int
{
    return $total > 0 ? max(0, min(100, (int) round(($part / $total) * 100))) : 0;
}

$userCount = (int) ($stats['userCount'] ?? 0);
$activeUsers = (int) ($stats['activeCount'] ?? 0);
$adminCount = (int) ($stats['adminCount'] ?? 0);
$managerCount = (int) ($stats['managerCount'] ?? 0);
$studentCount = max(0, $userCount - $adminCount - $managerCount);

$profileCount = dashboardCount($pdo, 'Profile');
$questionnaireUsers = dashboardTableExists($pdo, 'UserQuestionnaire')
    ? (int) ($pdo->query('SELECT COUNT(DISTINCT userId) FROM UserQuestionnaire')->fetchColumn() ?: 0)
    : 0;
$courseCount = dashboardCount($pdo, 'Course');
$publishedCourses = dashboardCount($pdo, 'Course', "LOWER(COALESCE(status, '')) IN ('published', 'active', 'live')");
$calendarCount = dashboardCount($pdo, 'Calendar');
$hubCount = dashboardCount($pdo, 'SkillHub');
$challengeCount = dashboardCount($pdo, 'Challenge');
$submissionCount = dashboardCount($pdo, 'Submission');
$pendingSubmissions = dashboardCount($pdo, 'Submission', 'score IS NULL');
$reviewedSubmissions = max(0, $submissionCount - $pendingSubmissions);
$postCount = dashboardCount($pdo, 'Post');
$opportunityCount = dashboardCount($pdo, 'Opportunity');
$liveOpportunities = dashboardCount($pdo, 'Opportunity', "LOWER(COALESCE(status, '')) IN ('published', 'active', 'open')");
$applicationCount = dashboardCount($pdo, 'Application');
$pendingApplications = dashboardCount($pdo, 'Application', "LOWER(COALESCE(status, 'pending')) = 'pending'");
$acceptedApplications = dashboardCount($pdo, 'Application', "LOWER(COALESCE(status, '')) = 'accepted'");
$eventCount = dashboardCount($pdo, 'Event');
$upcomingEvents = dashboardCount($pdo, 'Event', "date IS NOT NULL AND date >= CURDATE()");
$participationCount = dashboardCount($pdo, 'Participation');

$profileRate = dashboardPercent($profileCount, $userCount);
$questionnaireRate = dashboardPercent($questionnaireUsers, $userCount);
$courseActivityRate = dashboardPercent($calendarCount, max(1, $userCount));
$submissionReviewRate = dashboardPercent($reviewedSubmissions, $submissionCount);
$applicationAcceptanceRate = dashboardPercent($acceptedApplications, $applicationCount);
$eventParticipationRate = dashboardPercent($participationCount, max(1, $eventCount * 10));

$recentUsers = dashboardRows(
    $pdo,
    "SELECT fullName, email, role, status, createdAt
     FROM Users
     ORDER BY createdAt DESC, userId DESC
     LIMIT 5"
);

$recentApplications = dashboardRows(
    $pdo,
    "SELECT a.applicationId, a.status, a.appliedAt, a.compatibilityScore, u.fullName, o.title
     FROM Application a
     LEFT JOIN Users u ON u.userId = a.userId
     LEFT JOIN Opportunity o ON o.opportunityId = a.opportunityId
     ORDER BY a.appliedAt DESC, a.applicationId DESC
     LIMIT 5"
);

$recentChallenges = dashboardRows(
    $pdo,
    "SELECT c.challengeId, c.title, c.type, c.status, sh.name AS hubName,
            COUNT(s.submissionId) AS submissionCount
     FROM Challenge c
     LEFT JOIN SkillHub sh ON sh.groupId = c.groupId
     LEFT JOIN Submission s ON s.challengeId = c.challengeId
     GROUP BY c.challengeId, c.title, c.type, c.status, sh.name
     ORDER BY c.createdAt DESC, c.challengeId DESC
     LIMIT 5"
);

$adminSidebarCardTitle = 'Live dashboard';
$adminSidebarCardText = 'This page now reads from the database and highlights queues that need attention.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-shell">
        <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

        <main class="admin-main">
            <header class="page-header">
                <div>
                    <h2>CareerStrand Live Dashboard</h2>
                    <p>Track the platform from one control room: users, learning, Skill Hub practice, events, opportunities, and review queues.</p>
                </div>
                <div class="header-actions">
                    <a class="btn btn-soft" href="admin-users.php">Manage users</a>
                    <a class="btn btn-main" href="admin-applications.php">Review applications</a>
                    <a class="btn btn-soft" href="../FrontOffice/logout.php">Sign out</a>
                </div>
            </header>

            <section class="hero-panel">
                <div class="hero-grid">
                    <div class="hero-copy">
                        <div class="eyebrow">Platform pulse</div>
                        <h3>Live signals instead of static numbers.</h3>
                        <p>The dashboard now reflects the current database, so admins can see where users are moving and which queues need decisions.</p>
                        <div class="mini-grid">
                            <article class="metric-tile">
                                <div class="metric-label">Active Users</div>
                                <div class="metric-value"><?= h((string) $activeUsers); ?></div>
                                <div class="metric-sub"><?= $userCount; ?> total accounts</div>
                            </article>
                            <article class="metric-tile">
                                <div class="metric-label">Pending Reviews</div>
                                <div class="metric-value"><?= h((string) ($pendingApplications + $pendingSubmissions)); ?></div>
                                <div class="metric-sub">Applications + submissions</div>
                            </article>
                            <article class="metric-tile">
                                <div class="metric-label">Live Opportunities</div>
                                <div class="metric-value"><?= h((string) $liveOpportunities); ?></div>
                                <div class="metric-sub"><?= $opportunityCount; ?> total openings</div>
                            </article>
                            <article class="metric-tile">
                                <div class="metric-label">Upcoming Events</div>
                                <div class="metric-value"><?= h((string) $upcomingEvents); ?></div>
                                <div class="metric-sub"><?= $eventCount; ?> events in database</div>
                            </article>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="panel-title">
                            <h3>Admin attention</h3>
                            <p>Fast links for the highest-impact work.</p>
                        </div>
                        <div class="action-list" style="margin-top: 18px;">
                            <a class="action-item" href="admin-applications.php">
                                <span>Pending applications</span>
                                <strong><?= h((string) $pendingApplications); ?></strong>
                            </a>
                            <a class="action-item" href="admin-skills.php">
                                <span>Skill Hub submissions waiting</span>
                                <strong><?= h((string) $pendingSubmissions); ?></strong>
                            </a>
                            <a class="action-item" href="admin-feedback.php">
                                <span>Event registrations</span>
                                <strong><?= h((string) $participationCount); ?></strong>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="tile-grid" style="margin-top: 24px;">
                <article class="metric-tile">
                    <div class="metric-label">Students</div>
                    <div class="metric-value"><?= h((string) $studentCount); ?></div>
                    <div class="metric-sub"><?= $managerCount; ?> managers and recruiters</div>
                </article>
                <article class="metric-tile">
                    <div class="metric-label">Profiles</div>
                    <div class="metric-value"><?= h((string) $profileCount); ?></div>
                    <div class="metric-sub"><?= $profileRate; ?>% profile coverage</div>
                </article>
                <article class="metric-tile">
                    <div class="metric-label">Courses</div>
                    <div class="metric-value"><?= h((string) $courseCount); ?></div>
                    <div class="metric-sub"><?= $publishedCourses; ?> published or active</div>
                </article>
                <article class="metric-tile">
                    <div class="metric-label">Skill Hubs</div>
                    <div class="metric-value"><?= h((string) $hubCount); ?></div>
                    <div class="metric-sub"><?= $challengeCount; ?> challenges, <?= $postCount; ?> posts</div>
                </article>
            </section>

            <section class="chart-grid" style="margin-top: 24px;">
                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Progression Funnel</h3>
                            <p>How much of the ecosystem has active data today.</p>
                        </div>
                        <div class="filters">
                            <span class="filter">Live DB</span>
                            <span class="filter"><?= date('M j, Y'); ?></span>
                        </div>
                    </div>
                    <div class="bars">
                        <div class="bar-row"><span>Profiles</span><div class="bar-track"><div class="bar-fill" style="width: <?= $profileRate; ?>%;"></div></div><strong><?= $profileRate; ?>%</strong></div>
                        <div class="bar-row"><span>Questionnaire</span><div class="bar-track"><div class="bar-fill" style="width: <?= $questionnaireRate; ?>%;"></div></div><strong><?= $questionnaireRate; ?>%</strong></div>
                        <div class="bar-row"><span>Course plans</span><div class="bar-track"><div class="bar-fill" style="width: <?= $courseActivityRate; ?>%;"></div></div><strong><?= $courseActivityRate; ?>%</strong></div>
                        <div class="bar-row"><span>Submissions reviewed</span><div class="bar-track"><div class="bar-fill" style="width: <?= $submissionReviewRate; ?>%;"></div></div><strong><?= $submissionReviewRate; ?>%</strong></div>
                        <div class="bar-row"><span>Applications accepted</span><div class="bar-track"><div class="bar-fill" style="width: <?= $applicationAcceptanceRate; ?>%;"></div></div><strong><?= $applicationAcceptanceRate; ?>%</strong></div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Opportunity Pipeline</h3>
                            <p>Current moderation and hiring movement.</p>
                        </div>
                    </div>
                    <div class="donut-layout">
                        <div class="donut">
                            <div class="donut-center">
                                <strong><?= h((string) $applicationCount); ?></strong>
                                <span>applications</span>
                            </div>
                        </div>
                        <div class="legend">
                            <div class="legend-item"><span><i class="dot" style="background: var(--yellow);"></i>Pending</span><strong><?= h((string) $pendingApplications); ?></strong></div>
                            <div class="legend-item"><span><i class="dot" style="background: var(--green);"></i>Accepted</span><strong><?= h((string) $acceptedApplications); ?></strong></div>
                            <div class="legend-item"><span><i class="dot" style="background: var(--blue);"></i>Live opportunities</span><strong><?= h((string) $liveOpportunities); ?></strong></div>
                            <div class="legend-item"><span><i class="dot" style="background: var(--red);"></i>Upcoming events</span><strong><?= h((string) $upcomingEvents); ?></strong></div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="split-grid" style="margin-top: 24px;">
                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Newest Users</h3>
                            <p>Latest accounts created on the platform.</p>
                        </div>
                        <a class="link-btn" href="admin-users.php">Open users</a>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Role</th><th>Status</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentUsers)) { ?>
                                <tr><td colspan="4" class="empty-cell">No users found yet.</td></tr>
                            <?php } else { ?>
                                <?php foreach ($recentUsers as $recentUser) { ?>
                                    <tr>
                                        <td><strong><?= h($recentUser['fullName'] ?? 'Unknown user'); ?></strong><br><span class="subtle"><?= h($recentUser['email'] ?? ''); ?></span></td>
                                        <td><?= h($recentUser['role'] ?? 'user'); ?></td>
                                        <td><span class="status-chip <?= strtolower((string) ($recentUser['status'] ?? '')) === 'active' ? 'status-active' : 'status-draft'; ?>"><?= h($recentUser['status'] ?? 'unknown'); ?></span></td>
                                        <td><?= h((string) ($recentUser['createdAt'] ?? '-')); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Recent Applications</h3>
                            <p>Newest opportunity applications and fit scores.</p>
                        </div>
                        <a class="link-btn" href="admin-applications.php">Open applications</a>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Candidate</th><th>Opportunity</th><th>Status</th><th>Fit</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentApplications)) { ?>
                                <tr><td colspan="4" class="empty-cell">No applications found yet.</td></tr>
                            <?php } else { ?>
                                <?php foreach ($recentApplications as $application) { ?>
                                    <?php $status = strtolower((string) ($application['status'] ?? 'pending')); ?>
                                    <tr>
                                        <td><strong><?= h($application['fullName'] ?? 'Unknown candidate'); ?></strong><br><span class="subtle"><?= h((string) ($application['appliedAt'] ?? '-')); ?></span></td>
                                        <td><?= h($application['title'] ?? 'Unknown opportunity'); ?></td>
                                        <td><span class="status-chip <?= $status === 'accepted' ? 'status-accepted' : ($status === 'pending' ? 'status-pending' : 'status-draft'); ?>"><?= h($application['status'] ?? 'pending'); ?></span></td>
                                        <td><?= h((string) ($application['compatibilityScore'] ?? 0)); ?>%</td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </article>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <div class="panel-header">
                    <div class="panel-title">
                        <h3>Skill Hub Workload</h3>
                        <p>Newest challenges and their submission volume.</p>
                    </div>
                    <a class="link-btn" href="admin-skills.php">Open Skill Hub</a>
                </div>
                <table>
                    <thead>
                        <tr><th>Challenge</th><th>Hub</th><th>Type</th><th>Status</th><th>Submissions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentChallenges)) { ?>
                            <tr><td colspan="5" class="empty-cell">No challenges found yet.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($recentChallenges as $challenge) { ?>
                                <tr>
                                    <td><strong><?= h($challenge['title'] ?? 'Untitled challenge'); ?></strong></td>
                                    <td><?= h($challenge['hubName'] ?? 'Skill Hub'); ?></td>
                                    <td><span class="category-chip"><?= h(ucfirst((string) ($challenge['type'] ?? 'task'))); ?></span></td>
                                    <td><span class="status-chip <?= in_array(strtolower((string) ($challenge['status'] ?? '')), ['published', 'active', 'open'], true) ? 'status-published' : 'status-draft'; ?>"><?= h($challenge['status'] ?? 'draft'); ?></span></td>
                                    <td><?= h((string) ($challenge['submissionCount'] ?? 0)); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </section>

            <div class="footer-note">
                Learning coverage: <?= $courseActivityRate; ?>% | Event participation signal: <?= $eventParticipationRate; ?>% | Review completion: <?= $submissionReviewRate; ?>%
            </div>
        </main>
    </div>
    <script src="assets/js/admin.js"></script>
</body>
</html>
