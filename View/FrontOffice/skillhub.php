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

        <main class="main-area">
            <div class="container">
                <section class="hero-panel">
                    <div class="hero-copy">
                        <div class="hero-copy-top">
                            <div class="eyebrow">Skill Hub Directory</div>
                            <h1>Find the communities where your work, questions, and momentum belong.</h1>
                            <p class="hero-description">Move through live circles of makers, mentors, and projects. Each cluster below is a real workspace connected to the people and work already in your database.</p>
                        </div>
                    </div>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <strong><?= count($joinedHubs); ?></strong>
                            <span>Shown as joined hubs</span>
                        </div>
                        <div class="hero-stat">
                            <strong><?= h((string) $stats['managerCount']); ?></strong>
                            <span>Managers and admins active</span>
                        </div>
                        <div class="hero-stat">
                            <strong><?= h((string) $stats['hubCount']); ?></strong>
                            <span>Total hubs in the database</span>
                        </div>
                    </div>
                </section>

                <section class="directory-shell">
                    <aside class="left-rail">
                        <section class="glass-panel">
                            <div class="panel-title">Your circles</div>
                            <div class="friend-stack">
                                <div class="friend-row">
                                    <div class="avatar manager">MN</div>
                                    <div>
                                        <strong>Maya Nwosu</strong>
                                        <p>Active in Frontend Systems</p>
                                    </div>
                                </div>
                                <div class="friend-row">
                                    <div class="avatar user">FK</div>
                                    <div>
                                        <strong>Fatima Kone</strong>
                                        <p>Posting in UI/UX Studio</p>
                                    </div>
                                </div>
                                <div class="friend-row">
                                    <div class="avatar user">OD</div>
                                    <div>
                                        <strong>Ola Dairo</strong>
                                        <p>Running prompts in Storytelling Lab</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="glass-panel">
                            <div class="panel-title">Browse by theme</div>
                            <div class="chip-grid">
                                <button class="filter-chip active" type="button" data-filter="all">All hubs</button>
                                <button class="filter-chip" type="button" data-filter="frontend">Frontend</button>
                                <button class="filter-chip" type="button" data-filter="design">Design</button>
                                <button class="filter-chip" type="button" data-filter="communication">Communication</button>
                                <button class="filter-chip" type="button" data-filter="business">Business</button>
                            </div>
                        </section>
                    </aside>

                    <section class="directory-column">
                        <section class="glass-panel search-panel">
                            <div class="search-row">
                                <input id="hubSearch" class="search-input" type="text" placeholder="Search hubs, mentors, themes, or workspaces...">
                                <a class="ghost-btn" href="hub.php<?= !empty($joinedHubs) ? '?groupId=' . (int) $joinedHubs[0]['groupId'] : ''; ?>">Explore</a>
                            </div>
                        </section>

                        <section class="section-block">
                            <div class="section-head">
                                <div>
                                    <div class="panel-title">My hubs</div>
                                    <h2>Spaces currently shown as joined</h2>
                                </div>
                                <?php if (!empty($joinedHubs)) { ?>
                                    <a class="section-link" href="hub.php?groupId=<?= (int) $joinedHubs[0]['groupId']; ?>">Go to current hub</a>
                                <?php } ?>
                            </div>

                            <div class="hub-grid">
                                <?php foreach ($joinedHubs as $hub) { ?>
                                    <article class="hub-card joined" data-category="<?= h(hubCategoryKey((string) $hub['category'])); ?>" data-search="<?= h(strtolower($hub['name'] . ' ' . $hub['category'] . ' ' . $hub['description'])); ?>">
                                        <div class="hub-card-top">
                                            <div>
                                                <div class="hub-kicker">Joined hub</div>
                                                <h3><?= h($hub['name']); ?></h3>
                                            </div>
                                            <span class="hub-badge"><?= h((string) $hub['memberCount']); ?> members</span>
                                        </div>
                                        <p><?= h($hub['description']); ?></p>
                                        <div class="hub-meta">
                                            <span><?= h((string) $hub['workCount']); ?> work items</span>
                                            <span><?= h((string) $hub['threadCount']); ?> threads</span>
                                            <span><?= h($hub['category']); ?></span>
                                        </div>
                                        <div class="hub-card-actions">
                                            <a class="primary-btn" href="hub.php?groupId=<?= (int) $hub['groupId']; ?>">Open hub</a>
                                            <form class="inline-action-form" method="POST">
                                                <input type="hidden" name="action" value="leave_hub">
                                                <input type="hidden" name="groupId" value="<?= (int) $hub['groupId']; ?>">
                                                <button class="ghost-btn leave-btn" type="submit">Leave hub</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php } ?>
                            </div>
                        </section>

                        <section class="section-block section-frame">
                            <div class="section-head">
                                <div>
                                    <div class="panel-title">Suggested hubs</div>
                                    <h2>Recommended from your live database</h2>
                                </div>
                            </div>

                            <div class="hub-grid">
                                <?php foreach ($suggestedHubs as $hub) { ?>
                                    <article class="hub-card suggested" data-category="<?= h(hubCategoryKey((string) $hub['category'])); ?>" data-search="<?= h(strtolower($hub['name'] . ' ' . $hub['category'] . ' ' . $hub['description'])); ?>">
                                        <div class="hub-card-top">
                                            <div>
                                                <div class="hub-kicker">Suggested</div>
                                                <h3><?= h($hub['name']); ?></h3>
                                            </div>
                                            <span class="hub-badge warm"><?= h($hub['status']); ?></span>
                                        </div>
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
                                                <button class="primary-btn join-btn" type="submit">Join hub</button>
                                            </form>
                                            <a class="ghost-btn" href="hub.php?groupId=<?= (int) $hub['groupId']; ?>">Preview</a>
                                        </div>
                                    </article>
                                <?php } ?>
                            </div>
                        </section>
                    </section>

                    <aside class="right-rail">
                        <section class="glass-panel">
                            <div class="panel-title">Directory health</div>
                            <div class="mini-list">
                                <div class="mini-row">
                                    <strong><?= h((string) $stats['hubCount']); ?></strong>
                                    <span>hubs available</span>
                                </div>
                                <div class="mini-row">
                                    <strong><?= h((string) $stats['workCount']); ?></strong>
                                    <span>tasks and projects live</span>
                                </div>
                                <div class="mini-row">
                                    <strong><?= h((string) $stats['threadCount']); ?></strong>
                                    <span>threads stored</span>
                                </div>
                            </div>
                        </section>

                        <section class="glass-panel">
                            <div class="panel-title">Friends to add</div>
                            <div class="friend-stack compact">
                                <div class="friend-row">
                                    <div class="avatar user">SB</div>
                                    <div>
                                        <strong>Samuel Bassey</strong>
                                        <p>Frontend Systems</p>
                                    </div>
                                </div>
                                <div class="friend-row">
                                    <div class="avatar user">AM</div>
                                    <div>
                                        <strong>Amira Bello</strong>
                                        <p>Motion Lab</p>
                                    </div>
                                </div>
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
