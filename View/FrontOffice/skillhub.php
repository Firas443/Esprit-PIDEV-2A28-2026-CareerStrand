<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';

function hubSaveIcon(bool $saved): string
{
    if ($saved) {
        return '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M3 2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>';
    }

    return '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v11.5l-4.5-2.5L3 13.5V2z"/></svg>';
}

$frontUser = requireFrontUser();
$defaultUserId = (int) $frontUser['userId'];
$dbAvailable = true;
$dbErrorMessage = null;
$hubRows = [];
$memberRows = [];
$challengeRows = [];
$postRows = [];
$hubs = [];
$stats = [
    'hubCount' => 0,
    'managerCount' => 0,
    'workCount' => 0,
    'threadCount' => 0,
];
$joinedHubs = [];
$suggestedHubs = [];
$recommendedChallenges = [];

try {
    $coreController = new SkillHubCoreController();
    $engagementController = new SkillHubEngagementController();
    $pdo = config::getConnexion();

    $users = $pdo->query("SELECT userId, fullName, role FROM Users ORDER BY userId ASC")->fetchAll();

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
    $recommendedHubs = $defaultUserId !== null
        ? $coreController->getRecommendedHubsForUser($defaultUserId, 4)
        : [];
    $recommendedChallenges = $defaultUserId !== null
        ? $coreController->getRecommendedChallengesForUser($defaultUserId, 4)
        : [];
} catch (Throwable $exception) {
    $dbAvailable = false;
    $dbErrorMessage = 'Skill Hub is temporarily offline because the database connection could not be established.';
    $users = [];
}

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
    <link rel="stylesheet" href="assets/css/front-nav.css">
</head>
<body data-user-id="<?= (int) $defaultUserId; ?>">
    <div class="page-shell">
        <div class="skillhub-atmosphere" aria-hidden="true">
            <div class="skillhub-orb orb-blue"></div>
            <div class="skillhub-orb orb-red"></div>
            <div class="skillhub-orb orb-slate"></div>
            <div class="skillhub-beam beam-left"></div>
            <div class="skillhub-beam beam-right"></div>
        </div>
        <canvas class="directory-network-canvas" id="directoryNetworkCanvas" aria-hidden="true"></canvas>
        <div class="directory-aurora directory-aurora-left" aria-hidden="true"></div>
        <div class="directory-aurora directory-aurora-right" aria-hidden="true"></div>
        <div class="directory-gridline" aria-hidden="true"></div>

        <?php
            $activePage = 'skillhub';
            $brandSubtitle = 'hub directory';
            include __DIR__ . '/partials/front-nav.php';
        ?>

        <main class="main-area skillhub-remodel">
            <div class="container">
                <?php if (!$dbAvailable) { ?>
                    <section class="hub-db-alert" role="alert">
                        <strong>Skill Hub is offline right now.</strong>
                        <span><?= h($dbErrorMessage ?? 'The database connection is unavailable.'); ?></span>
                    </section>
                <?php } ?>

                <section class="command-hero">
                    <div class="command-copy">
                        <div class="eyebrow">Skill Hub Command Center</div>
                        <h1>Welcome to your space.</h1>
                        <p>joined spaces, recommended communities, live work items, and discussions ready to open.</p>
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
                            <div class="board-actions">
                                <button class="ghost-btn saved-toggle-btn" type="button" data-saved-toggle aria-expanded="false" aria-controls="savedHubPanel">
                                    Saved hubs <span class="saved-toggle-count" data-saved-toggle-count>0</span>
                                </button>
                                <input id="hubSearch" class="search-input" type="text" placeholder="Search by hub, category, or description...">
                            </div>
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
                                        <article
                                            class="hub-card remodel-card joined"
                                            data-category="<?= h(hubCategoryKey((string) $hub['category'])); ?>"
                                            data-group-id="<?= (int) $hub['groupId']; ?>"
                                            data-hub-name="<?= h((string) $hub['name']); ?>"
                                            data-hub-description="<?= h((string) $hub['description']); ?>"
                                            data-hub-category="<?= h((string) $hub['category']); ?>"
                                            data-hub-state="joined"
                                            data-hub-members="<?= h((string) $hub['memberCount']); ?>"
                                            data-hub-work="<?= h((string) $hub['workCount']); ?>"
                                            data-hub-threads="<?= h((string) $hub['threadCount']); ?>"
                                            data-open-url="<?= h('hub.php?groupId=' . (int) $hub['groupId']); ?>"
                                            data-search="<?= h(strtolower($hub['name'] . ' ' . $hub['category'] . ' ' . $hub['description'])); ?>"
                                        >
                                            <div class="remodel-card-top">
                                                <button
                                                    class="btn-save"
                                                    type="button"
                                                    data-save-toggle
                                                    data-group-id="<?= (int) $hub['groupId']; ?>"
                                                    aria-pressed="false"
                                                    aria-label="Save hub"
                                                    title="Save"
                                                >
                                                    <?= hubSaveIcon(false); ?>
                                                </button>
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
                                        <article
                                            class="hub-card remodel-card suggested"
                                            data-category="<?= h(hubCategoryKey((string) $hub['category'])); ?>"
                                            data-group-id="<?= (int) $hub['groupId']; ?>"
                                            data-hub-name="<?= h((string) $hub['name']); ?>"
                                            data-hub-description="<?= h((string) $hub['description']); ?>"
                                            data-hub-category="<?= h((string) $hub['category']); ?>"
                                            data-hub-state="suggested"
                                            data-hub-status="<?= h((string) $hub['status']); ?>"
                                            data-hub-members="<?= h((string) $hub['memberCount']); ?>"
                                            data-hub-work="<?= h((string) $hub['workCount']); ?>"
                                            data-hub-threads="<?= h((string) $hub['threadCount']); ?>"
                                            data-open-url="<?= h('hub.php?groupId=' . (int) $hub['groupId']); ?>"
                                            data-search="<?= h(strtolower($hub['name'] . ' ' . $hub['category'] . ' ' . $hub['description'])); ?>"
                                        >
                                            <div class="remodel-card-top">
                                                <button
                                                    class="btn-save"
                                                    type="button"
                                                    data-save-toggle
                                                    data-group-id="<?= (int) $hub['groupId']; ?>"
                                                    aria-pressed="false"
                                                    aria-label="Save hub"
                                                    title="Save"
                                                >
                                                    <?= hubSaveIcon(false); ?>
                                                </button>
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
                            <div class="panel-title">Recommended communities</div>
                            <h3 class="recommendation-panel-title">Hubs to join next</h3>
                            <?php if (empty($recommendedHubs)) { ?>
                                <div class="challenge-empty-state">Join more spaces and community recommendations will appear here.</div>
                            <?php } else { ?>
                                <div class="recommendation-stack">
                                    <?php foreach ($recommendedHubs as $hub) { ?>
                                        <article class="recommendation-item">
                                            <div class="recommendation-item-top">
                                                <strong><?= h((string) ($hub['name'] ?? 'Untitled hub')); ?></strong>
                                                <span class="hub-badge blue"><?= h((string) ($hub['recommendationScore'] ?? 0)); ?>/100</span>
                                            </div>
                                            <p class="recommendation-summary">
                                                <?= h((string) ($hub['category'] ?? 'Unknown category')); ?> &middot;
                                                <?= h((string) ($hub['workCount'] ?? 0)); ?> work items &middot;
                                                <?= h((string) ($hub['threadCount'] ?? 0)); ?> threads
                                            </p>
                                            <?php if (!empty($hub['recommendationReasons'])) { ?>
                                                <div class="reason-list">
                                                    <?php foreach (array_slice($hub['recommendationReasons'], 0, 2) as $reason) { ?>
                                                        <span class="reason-chip"><?= h((string) $reason); ?></span>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                            <div class="recommendation-actions">
                                                <a class="ghost-btn compact-btn" href="hub.php?groupId=<?= (int) ($hub['groupId'] ?? 0); ?>">Open</a>
                                                <a class="ghost-btn compact-btn" href="<?= h((string) ('skillhub.php?membership=preview&groupId=' . (int) ($hub['groupId'] ?? 0))); ?>">Preview</a>
                                            </div>
                                        </article>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </section>

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

                <aside class="saved-hub-panel" id="savedHubPanel" data-saved-lane aria-hidden="true">
                    <div class="saved-hub-panel-head">
                        <div>
                            <div class="panel-title">Saved hubs</div>
                            <h3>Your shortlist</h3>
                        </div>
                        <button class="drawer-close saved-panel-close" type="button" data-saved-toggle aria-label="Close saved hubs">Close</button>
                    </div>
                    <div class="saved-hub-panel-copy">
                        Keep communities you want to revisit without adding more cards to the main grid.
                    </div>
                    <div class="saved-hub-panel-meta">
                        <span><strong data-saved-count>0</strong> saved</span>
                    </div>
                    <div class="saved-hub-panel-list" id="savedHubGrid">
                        <article class="empty-lane-card" data-saved-empty>
                            Save hubs you want to revisit later. Your shortlist will appear here when you open the panel.
                        </article>
                    </div>
                </aside>
                <div class="saved-hub-backdrop" data-saved-toggle-backdrop></div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
    <script src="assets/js/skillhub.js"></script>
</body>
</html>
