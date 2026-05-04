<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();

$users = $pdo->query("SELECT userId, fullName, role FROM Users ORDER BY userId ASC")->fetchAll();
$defaultUserId = !empty($users) ? (int) $users[0]['userId'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $defaultUserId !== null) {
    $action = $_POST['action'] ?? '';
    $targetGroupId = (int) ($_POST['groupId'] ?? 0);

    if ($action === 'join_hub' && $targetGroupId > 0) {
        $existingMember = $coreController->getGroupMemberByGroupAndUser($targetGroupId, $defaultUserId);
        if ($existingMember === null) {
            $coreController->createGroupMember(
                new GroupMemberEntity(
                    null,
                    $targetGroupId,
                    $defaultUserId,
                    date('Y-m-d'),
                    'active'
                )
            );
        }

        header('Location: skillhub.php?membership=joined');
        exit;
    }

    if ($action === 'leave_hub' && $targetGroupId > 0) {
        $coreController->deleteGroupMemberByGroupAndUser($targetGroupId, $defaultUserId);
        header('Location: skillhub.php?membership=left');
        exit;
    }
}

$hubRows = $coreController->getAllSkillHubs();
$memberRows = $coreController->getAllGroupMembers();
$challengeRows = $coreController->getAllChallenges();
$postRows = $engagementController->getAllPosts();

$memberCounts = [];
foreach ($memberRows as $memberRow) {
    $memberCounts[(int) $memberRow['groupId']] = ($memberCounts[(int) $memberRow['groupId']] ?? 0) + 1;
}

$workCounts = [];
foreach ($challengeRows as $challengeRow) {
    $workCounts[(int) $challengeRow['groupId']] = ($workCounts[(int) $challengeRow['groupId']] ?? 0) + 1;
}

$threadCounts = [];
foreach ($postRows as $postRow) {
    $threadCounts[(int) $postRow['groupId']] = ($threadCounts[(int) $postRow['groupId']] ?? 0) + 1;
}

usort($hubRows, static function (array $left, array $right): int {
    $createdComparison = strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    if ($createdComparison !== 0) {
        return $createdComparison;
    }

    return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
});

$hubs = array_map(static function (array $hub) use ($memberCounts, $workCounts, $threadCounts): array {
    $groupId = (int) $hub['groupId'];
    $hub['memberCount'] = $memberCounts[$groupId] ?? 0;
    $hub['workCount'] = $workCounts[$groupId] ?? 0;
    $hub['threadCount'] = $threadCounts[$groupId] ?? 0;
    return $hub;
}, $hubRows);

$managerCountQuery = $pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(role) IN ('manager', 'admin')");
$stats = [
    'hubCount' => count($hubRows),
    'managerCount' => (int) $managerCountQuery->fetchColumn(),
    'workCount' => count($challengeRows),
    'threadCount' => count($postRows),
];

$joinedGroupIds = [];
if ($defaultUserId !== null) {
    foreach ($memberRows as $memberRow) {
        if ((int) $memberRow['userId'] === $defaultUserId) {
            $joinedGroupIds[(int) $memberRow['groupId']] = true;
        }
    }
}

$joinedHubs = array_values(array_filter(
    $hubs,
    static fn(array $hub): bool => isset($joinedGroupIds[(int) $hub['groupId']])
));
$suggestedHubs = array_values(array_filter(
    $hubs,
    static fn(array $hub): bool => !isset($joinedGroupIds[(int) $hub['groupId']])
));
$recommendedChallenges = $defaultUserId !== null
    ? $coreController->getRecommendedChallengesForUser($defaultUserId, 4)
    : [];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function hubCategoryKey(string $category): string
{
    $key = strtolower(trim($category));
    return match ($key) {
        'frontend' => 'frontend',
        'design' => 'design',
        'communication' => 'communication',
        'business' => 'business',
        default => 'all',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand | Skill Hub Directory</title>
    <link rel="stylesheet" href="assets/css/skillhub.css">
</head>
<body>
    <div class="page-shell">
        <canvas class="directory-network-canvas" id="directoryNetworkCanvas" aria-hidden="true"></canvas>
        <div class="directory-aurora directory-aurora-left" aria-hidden="true"></div>
        <div class="directory-aurora directory-aurora-right" aria-hidden="true"></div>
        <div class="directory-gridline" aria-hidden="true"></div>

        <header class="site-header">
            <div class="container header-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="images/CareerStrand_logo.png" alt="CareerStrand logo">
                    <div>
                        <div class="brand-title">CareerStrand</div>
                        <div class="brand-subtitle">Hub directory</div>
                    </div>
                </a>

                <nav class="main-nav" aria-label="Primary">
                    <a href="#">Profile</a>
                    <a href="#">Education</a>
                    <a href="skillhub.php" class="active">Skill Hub</a>
                    <a href="#">Events</a>
                    <a href="#">Opportunities</a>
                </nav>

                <div class="header-actions">
                    <button class="ghost-btn" type="button">Friends</button>
                    <a class="primary-btn" href="hub.php<?= !empty($joinedHubs) ? '?groupId=' . (int) $joinedHubs[0]['groupId'] : ''; ?>">Open current hub</a>
                </div>
            </div>
        </header>

        <main class="main-area skillhub-remodel">
            <div class="container">
                <section class="command-hero">
                    <div class="command-copy">
                        <div class="eyebrow">Skill Hub Command Center</div>
                        <h1>Pick your lane, enter the hub, and turn skill practice into shipped work.</h1>
                        <p>Every hub below is pulled from your database: joined spaces, recommended communities, live work items, and discussions ready to open.</p>
                        <div class="command-actions">
                            <a class="primary-btn" href="hub.php<?= !empty($joinedHubs) ? '?groupId=' . (int) $joinedHubs[0]['groupId'] : ''; ?>">Enter current hub</a>
                            <a class="ghost-btn" href="#discoverBoard">Explore hubs</a>
                        </div>
                    </div>

                    <div class="mission-card">
                        <div class="panel-title">Launch status</div>
                        <?php if (!empty($joinedHubs)) { ?>
                            <?php $featuredHub = $joinedHubs[0]; ?>
                            <h2><?= h($featuredHub['name']); ?></h2>
                            <p><?= h($featuredHub['description']); ?></p>
                            <div class="mission-stats">
                                <span><?= h((string) $featuredHub['workCount']); ?> work items</span>
                                <span><?= h((string) $featuredHub['threadCount']); ?> threads</span>
                                <span><?= h($featuredHub['category']); ?></span>
                            </div>
                            <a class="mission-link" href="hub.php?groupId=<?= (int) $featuredHub['groupId']; ?>">Open workspace</a>
                        <?php } else { ?>
                            <h2>No joined hub yet</h2>
                            <p>Join a suggested hub below to unlock a personal launch space for tasks, projects, and discussion.</p>
                            <a class="mission-link" href="#discoverBoard">Browse suggestions</a>
                        <?php } ?>
                    </div>
                </section>

                <section class="metric-strip" aria-label="Skill hub stats">
                    <article>
                        <svg class="metric-line-icon" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M24 23a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z"></path>
                            <path d="M10 41c2.4-8.5 8-13 14-13s11.6 4.5 14 13"></path>
                        </svg>
                        <span>Joined</span>
                        <strong><?= count($joinedHubs); ?></strong>
                    </article>
                    <article>
                        <svg class="metric-line-icon" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M14 18h20l8 10-18 14L6 28l8-10Z"></path>
                            <path d="M14 18 24 42 34 18"></path>
                            <path d="M6 28h36"></path>
                        </svg>
                        <span>Hubs</span>
                        <strong><?= h((string) $stats['hubCount']); ?></strong>
                    </article>
                    <article>
                        <svg class="metric-line-icon" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M14 17h20a6 6 0 0 1 6 6v13a5 5 0 0 1-5 5H13a5 5 0 0 1-5-5V23a6 6 0 0 1 6-6Z"></path>
                            <path d="M18 17v-4a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v4"></path>
                            <path d="M8 27h32"></path>
                        </svg>
                        <span>Work</span>
                        <strong><?= h((string) $stats['workCount']); ?></strong>
                    </article>
                    <article>
                        <svg class="metric-line-icon" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M10 12h28a5 5 0 0 1 5 5v12a5 5 0 0 1-5 5H22l-10 8v-8h-2a5 5 0 0 1-5-5V17a5 5 0 0 1 5-5Z"></path>
                            <path d="M15 21h18"></path>
                            <path d="M15 27h12"></path>
                        </svg>
                        <span>Threads</span>
                        <strong><?= h((string) $stats['threadCount']); ?></strong>
                    </article>
                </section>

                <section class="hub-console" id="discoverBoard">
                    <section class="hub-board">
                        <div class="board-control">
                            <div>
                                <div class="panel-title">Discovery board</div>
                                <h2>Find the right room for your next move</h2>
                            </div>
                            <input id="hubSearch" class="search-input" type="text" placeholder="Search by hub, category, or description...">
                        </div>

                        <div class="filter-dock" aria-label="Hub filters">
                            <button class="filter-chip active" type="button" data-filter="all">All</button>
                            <button class="filter-chip" type="button" data-filter="frontend">Frontend</button>
                            <button class="filter-chip" type="button" data-filter="design">Design</button>
                            <button class="filter-chip" type="button" data-filter="communication">Communication</button>
                            <button class="filter-chip" type="button" data-filter="business">Business</button>
                        </div>

                        <div class="hub-lanes">
                            <section class="hub-lane">
                                <div class="lane-heading">
                                    <span>Current spaces</span>
                                    <strong><?= count($joinedHubs); ?></strong>
                                </div>
                                <div class="lane-grid">
                                    <?php if (empty($joinedHubs)) { ?>
                                        <article class="empty-lane-card">No joined hubs yet. Join a space from the discovery lane.</article>
                                    <?php } ?>
                                    <?php foreach ($joinedHubs as $hub) { ?>
                                        <article class="hub-card remodel-card joined" data-category="<?= h(hubCategoryKey((string) $hub['category'])); ?>" data-search="<?= h(strtolower($hub['name'] . ' ' . $hub['category'] . ' ' . $hub['description'])); ?>">
                                            <div class="remodel-card-top">
                                                <span class="hub-kicker">Joined</span>
                                                <span class="hub-badge"><?= h((string) $hub['memberCount']); ?> members</span>
                                            </div>
                                            <h3><?= h($hub['name']); ?></h3>
                                            <p><?= h($hub['description']); ?></p>
                                            <div class="hub-meta">
                                                <span><?= h((string) $hub['workCount']); ?> work items</span>
                                                <span><?= h((string) $hub['threadCount']); ?> threads</span>
                                                <span><?= h($hub['category']); ?></span>
                                            </div>
                                            <div class="hub-card-actions">
                                                <a class="primary-btn" href="hub.php?groupId=<?= (int) $hub['groupId']; ?>">Open</a>
                                                <form class="inline-action-form" method="POST">
                                                    <input type="hidden" name="action" value="leave_hub">
                                                    <input type="hidden" name="groupId" value="<?= (int) $hub['groupId']; ?>">
                                                    <button class="ghost-btn leave-btn" type="submit">Leave</button>
                                                </form>
                                            </div>
                                        </article>
                                    <?php } ?>
                                </div>
                            </section>

                            <section class="hub-lane">
                                <div class="lane-heading">
                                    <span>Discovery lane</span>
                                    <strong><?= count($suggestedHubs); ?></strong>
                                </div>
                                <div class="lane-grid">
                                    <?php if (empty($suggestedHubs)) { ?>
                                        <article class="empty-lane-card">You are already joined to every hub in the directory.</article>
                                    <?php } ?>
                                    <?php foreach ($suggestedHubs as $hub) { ?>
                                        <article class="hub-card remodel-card suggested" data-category="<?= h(hubCategoryKey((string) $hub['category'])); ?>" data-search="<?= h(strtolower($hub['name'] . ' ' . $hub['category'] . ' ' . $hub['description'])); ?>">
                                            <div class="remodel-card-top">
                                                <span class="hub-kicker">Suggested</span>
                                                <span class="hub-badge warm"><?= h($hub['status']); ?></span>
                                            </div>
                                            <h3><?= h($hub['name']); ?></h3>
                                            <p><?= h($hub['description']); ?></p>
                                            <div class="hub-meta">
                                                <span><?= h((string) $hub['workCount']); ?> work items</span>
                                                <span><?= h((string) $hub['threadCount']); ?> threads</span>
                                                <span><?= h($hub['category']); ?></span>
                                            </div>
                                            <div class="hub-card-actions">
                                                <form class="inline-action-form" method="POST">
                                                    <input type="hidden" name="action" value="join_hub">
                                                    <input type="hidden" name="groupId" value="<?= (int) $hub['groupId']; ?>">
                                                    <button class="primary-btn join-btn" type="submit">Join</button>
                                                </form>
                                                <a class="ghost-btn" href="hub.php?groupId=<?= (int) $hub['groupId']; ?>">Preview</a>
                                            </div>
                                        </article>
                                    <?php } ?>
                                </div>
                            </section>
                        </div>
                    </section>

                    <aside class="insight-rail">
                        <section class="insight-card recommendation-panel">
                            <div class="panel-title">Recommended work</div>
                            <h3 class="recommendation-panel-title">Next challenges</h3>
                            <?php if (empty($recommendedChallenges)) { ?>
                                <div class="challenge-empty-state">Join more hubs and recommendations will appear here.</div>
                            <?php } else { ?>
                                <div class="recommendation-stack">
                                    <?php foreach ($recommendedChallenges as $challenge) { ?>
                                        <article class="recommendation-item">
                                            <div class="recommendation-item-top">
                                                <strong><?= h((string) ($challenge['title'] ?? 'Untitled challenge')); ?></strong>
                                                <span class="hub-badge blue"><?= h((string) ($challenge['recommendationScore'] ?? 0)); ?>/100</span>
                                            </div>
                                            <p class="recommendation-summary">
                                                <?= h((string) ($challenge['hubName'] ?? 'Unknown hub')); ?> &middot;
                                                <?= h((string) ($challenge['difficulty'] ?? 'Open level')); ?> &middot;
                                                <?= h((string) ($challenge['type'] ?? 'Task')); ?>
                                            </p>
                                            <div class="recommendation-actions">
                                                <a class="ghost-btn compact-btn" href="thread.php?challengeId=<?= (int) $challenge['challengeId']; ?>">Open</a>
                                                <a class="ghost-btn compact-btn" href="hub.php?groupId=<?= (int) ($challenge['groupId'] ?? 0); ?>">Hub</a>
                                            </div>
                                        </article>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </section>

                        <section class="insight-card">
                            <div class="panel-title">Directory pulse</div>
                            <div class="pulse-list">
                                <div><strong><?= h((string) $stats['managerCount']); ?></strong><span>mentors and admins</span></div>
                                <div><strong><?= h((string) $stats['workCount']); ?></strong><span>live work items</span></div>
                                <div><strong><?= h((string) $stats['threadCount']); ?></strong><span>discussion threads</span></div>
                            </div>
                        </section>
                    </aside>
                </section>
            </div>
        </main>
    </div>

    <script src="assets/js/skillhub.js"></script>
</body>
</html>
