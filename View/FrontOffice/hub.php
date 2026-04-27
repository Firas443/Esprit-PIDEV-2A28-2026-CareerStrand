<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../Model/SkillHubEngagement.php';

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();
$hubPostErrors = [
    'title' => '',
    'content' => '',
    'postType' => '',
];

$users = $pdo->query("SELECT userId, fullName, role FROM Users")->fetchAll();
$userMap = [];
foreach ($users as $user) {
    $userMap[(int) $user['userId']] = $user;
}

$defaultUserId = !empty($users) ? (int) $users[0]['userId'] : null;
$allHubs = $coreController->getAllSkillHubs();
$allChallenges = $coreController->getAllChallenges();
$allMembers = $coreController->getAllGroupMembers();
$allPosts = $engagementController->getAllPosts();

usort($allHubs, static function (array $left, array $right): int {
    return (int) $left['groupId'] <=> (int) $right['groupId'];
});

$groupId = isset($_GET['groupId']) ? (int) $_GET['groupId'] : (!empty($allHubs) ? (int) $allHubs[0]['groupId'] : 0);
$workFilters = [
    'groupId' => $groupId,
    'search' => trim((string) ($_GET['workSearch'] ?? '')),
    'difficulty' => trim((string) ($_GET['workDifficulty'] ?? '')),
    'type' => trim((string) ($_GET['workType'] ?? '')),
    'status' => trim((string) ($_GET['workStatus'] ?? '')),
    'sort' => trim((string) ($_GET['workSort'] ?? 'newest')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $defaultUserId !== null) {
    if ($_POST['action'] === 'create_hub_post') {
        $submittedTitle = trim((string) ($_POST['title'] ?? ''));
        $submittedContent = trim((string) ($_POST['content'] ?? ''));
        $submittedPostType = trim((string) ($_POST['postType'] ?? ''));

        if ($submittedTitle === '') {
            $hubPostErrors['title'] = 'Title is required.';
        }
        if ($submittedContent === '') {
            $hubPostErrors['content'] = 'Content is required.';
        }
        if ($submittedPostType === '') {
            $hubPostErrors['postType'] = 'Post type is required.';
        }

        if (!array_filter($hubPostErrors)) {
            $engagementController->createPost(
                new PostEntity(
                    null,
                    (int) $_POST['groupId'],
                    $defaultUserId,
                    null,
                    $submittedPostType,
                    $submittedTitle,
                    $submittedContent,
                    'active',
                    null
                )
            );
            header('Location: hub.php?groupId=' . (int) $_POST['groupId']);
            exit;
        }
    }
}

$memberCounts = [];
foreach ($allMembers as $member) {
    $memberCounts[(int) $member['groupId']] = ($memberCounts[(int) $member['groupId']] ?? 0) + 1;
}

$workCounts = [];
foreach ($allChallenges as $challenge) {
    $workCounts[(int) $challenge['groupId']] = ($workCounts[(int) $challenge['groupId']] ?? 0) + 1;
}

$threadCounts = [];
foreach ($allPosts as $post) {
    $threadCounts[(int) $post['groupId']] = ($threadCounts[(int) $post['groupId']] ?? 0) + 1;
}

$allHubs = array_map(static function (array $hub) use ($memberCounts, $workCounts, $threadCounts): array {
    $groupId = (int) $hub['groupId'];
    $hub['memberCount'] = $memberCounts[$groupId] ?? 0;
    $hub['workCount'] = $workCounts[$groupId] ?? 0;
    $hub['threadCount'] = $threadCounts[$groupId] ?? 0;
    return $hub;
}, $allHubs);

$currentHub = $groupId > 0 ? $coreController->getSkillHubById($groupId) : null;

$workItems = array_values(array_filter($allChallenges, static fn(array $challenge): bool => (int) $challenge['groupId'] === $groupId));
$workItems = array_map(static function (array $challenge) use ($currentHub, $userMap): array {
    $manager = $userMap[(int) $challenge['managerId']] ?? ['fullName' => 'Manager'];
    $challenge['hubName'] = $currentHub['name'] ?? 'Skill Hub';
    $challenge['managerName'] = $manager['fullName'] ?? 'Manager';
    return $challenge;
}, $workItems);
$filteredWorkItems = $coreController->filterChallenges($workFilters);
$filteredWorkItems = array_map(static function (array $challenge) use ($currentHub, $userMap): array {
    $challenge['hubName'] = $challenge['hubName'] ?? ($currentHub['name'] ?? 'Skill Hub');
    if (empty($challenge['managerName'])) {
        $manager = $userMap[(int) $challenge['managerId']] ?? ['fullName' => 'Manager'];
        $challenge['managerName'] = $manager['fullName'] ?? 'Manager';
    }
    return $challenge;
}, $filteredWorkItems);
$workDifficulties = array_values(array_unique(array_filter(array_map(
    static fn(array $challenge): string => trim((string) ($challenge['difficulty'] ?? '')),
    $workItems
))));
sort($workDifficulties);
$workStatuses = array_values(array_unique(array_filter(array_map(
    static fn(array $challenge): string => trim((string) ($challenge['status'] ?? '')),
    $workItems
))));
sort($workStatuses);

$commentRows = $engagementController->getAllComments();
$commentCounts = [];
foreach ($commentRows as $commentRow) {
    $commentCounts[(int) $commentRow['postId']] = ($commentCounts[(int) $commentRow['postId']] ?? 0) + 1;
}

$hubPosts = array_values(array_filter(
    $allPosts,
    static fn(array $post): bool => (int) $post['groupId'] === $groupId && empty($post['challengeId'])
));
$hubPosts = array_map(static function (array $post) use ($userMap, $commentCounts): array {
    $user = $userMap[(int) $post['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $post['fullName'] = $user['fullName'];
    $post['role'] = $user['role'];
    $post['commentCount'] = $commentCounts[(int) $post['postId']] ?? 0;
    return $post;
}, $hubPosts);

$members = array_values(array_filter($allMembers, static fn(array $member): bool => (int) $member['groupId'] === $groupId));
$members = array_map(static function (array $member) use ($userMap): array {
    $user = $userMap[(int) $member['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $member['fullName'] = $user['fullName'];
    $member['role'] = $user['role'];
    return $member;
}, $members);
usort($members, static function (array $left, array $right): int {
    $joinedComparison = strcmp((string) ($right['joinedAt'] ?? ''), (string) ($left['joinedAt'] ?? ''));
    if ($joinedComparison !== 0) {
        return $joinedComparison;
    }

    return strcmp((string) ($left['fullName'] ?? ''), (string) ($right['fullName'] ?? ''));
});
$members = array_slice($members, 0, 5);

$savedItems = array_slice($workItems, 0, 4);
$pinnedItems = array_slice($savedItems, 0, 3);  

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts ?: [], 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: 'CS';
}

function avatarClass(?string $role): string
{
    $role = strtolower((string) $role);
    return match ($role) {
        'admin' => 'admin',
        'manager' => 'manager',
        default => 'user',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand | Hub</title>
    <link rel="stylesheet" href="assets/css/hub.css">
</head>
<body>
    <div class="page-shell">
        <div class="ambient ambient-a"></div>
        <div class="ambient ambient-b"></div>

        <header class="site-header">
            <div class="container header-inner">
                <a class="brand" href="index.php">
                    <div class="brand-mark">
                        <img class="brand-logo" src="images/CareerStrand_logo.png" alt="CareerStrand logo">
                    </div>
                    <div>
                        <div class="brand-title">CareerStrand</div>
                        <div class="brand-sub">social skill space</div>
                    </div>
                </a>

                <nav class="main-nav">
                    <a href="#">Profile</a>
                    <a href="#">Education</a>
                    <a class="active" href="skillhub.php">Skill Hub</a>
                    <a href="#">Events</a>
                    <a href="#">Opportunities</a>
                </nav>

                <div class="header-actions">
                    <a class="menu-btn" href="skillhub.php">Hubs</a>
                    <button class="ghost-btn" type="button">Inbox</button>
                </div>
            </div>
        </header>

        <main class="main-area">
            <div class="container app-grid">
                <section class="feed-column">
                    <div class="feed-shell">
                        <section class="social-strip">
                            <article class="strip-card emphasis">
                                <div class="hub-widget-top">
                                    <div>
                                        <div class="strip-kicker">Current hub</div>
                                        <h2><?= h($currentHub['name'] ?? 'No hub selected'); ?></h2>
                                        <p class="hub-widget-copy"><?= h($currentHub['description'] ?? 'Choose a hub to see its work, discussion, and people.'); ?></p>
                                    </div>
                                    <div class="hub-widget-badge"><?= h((string) ($currentHub['category'] ?? 'Skill Hub')); ?></div>
                                </div>
                                <div class="hub-widget-grid">
                                    <div class="hub-widget-stat">
                                        <span class="hub-stat-label">Open now</span>
                                        <strong><?= count($filteredWorkItems); ?></strong>
                                        <span class="hub-stat-copy">tasks and projects in this hub</span>
                                    </div>
                                    <div class="hub-widget-stat">
                                        <span class="hub-stat-label">Members</span>
                                        <strong><?= h((string) ($currentHub ? array_values(array_filter($allHubs, fn($hub) => (int) $hub['groupId'] === (int) $currentHub['groupId']))[0]['memberCount'] ?? 0 : 0)); ?></strong>
                                        <span class="hub-stat-copy">people currently joined</span>
                                    </div>
                                    <div class="hub-widget-stat">
                                        <span class="hub-stat-label">Discussions</span>
                                        <strong><?= count($hubPosts); ?></strong>
                                        <span class="hub-stat-copy">hub-wide threads stored</span>
                                    </div>
                                </div>
                            </article>

                            <article class="strip-card compact">
                                <div class="strip-kicker">Pinned board</div>

                                <?php if (!empty($pinnedItems)) { ?>
                                    <div class="queue-list compact-queue-list">
                                        <?php foreach ($pinnedItems as $item) { ?>
                                            <div class="queue-item">
                                                <span class="queue-status <?= $item['type'] === 'project' ? 'progress' : 'open'; ?>"></span>
                                                <div>
                                                    <strong><?= h($item['title']); ?></strong>
                                                    <p><?= h(ucfirst((string) $item['type'])); ?> · <?= h($item['status']); ?></p>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div class="queue-empty compact-empty">
                                        No pinned tasks or projects yet.
                                    </div>
                                <?php } ?>
                            </article>
                        </section>
                        <section class="hub-filter-panel">
                            <div class="hub-filter-head">
                                <div>
                                    <div class="strip-kicker">Challenge filter</div>
                                    <h3>Refine this hub's tasks and projects</h3>
                                </div>
                                <div class="hub-filter-badge"><?= count($filteredWorkItems); ?> result<?= count($filteredWorkItems) === 1 ? '' : 's'; ?></div>
                            </div>
                            <form class="hub-work-filter-form" method="GET">
                                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                <div class="hub-work-filter-grid">
                                    <label class="hub-filter-field hub-filter-field-search">
                                        <span>Keyword</span>
                                        <input type="text" name="workSearch" value="<?= h($workFilters['search']); ?>" placeholder="Search tasks, projects, or descriptions...">
                                    </label>
                                    <label class="hub-filter-field">
                                        <span>Difficulty</span>
                                        <select name="workDifficulty">
                                            <option value="">All levels</option>
                                            <?php foreach ($workDifficulties as $difficulty) { ?>
                                                <option value="<?= h($difficulty); ?>" <?= strcasecmp($workFilters['difficulty'], $difficulty) === 0 ? 'selected' : ''; ?>><?= h($difficulty); ?></option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                    <label class="hub-filter-field">
                                        <span>Type</span>
                                        <select name="workType">
                                            <option value="">All types</option>
                                            <option value="task" <?= strcasecmp($workFilters['type'], 'task') === 0 ? 'selected' : ''; ?>>Task</option>
                                            <option value="project" <?= strcasecmp($workFilters['type'], 'project') === 0 ? 'selected' : ''; ?>>Project</option>
                                        </select>
                                    </label>
                                    <label class="hub-filter-field">
                                        <span>Status</span>
                                        <select name="workStatus">
                                            <option value="">All statuses</option>
                                            <?php foreach ($workStatuses as $status) { ?>
                                                <option value="<?= h($status); ?>" <?= strcasecmp($workFilters['status'], $status) === 0 ? 'selected' : ''; ?>><?= h(ucfirst($status)); ?></option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                    <label class="hub-filter-field">
                                        <span>Sort by</span>
                                        <select name="workSort">
                                            <option value="newest" <?= $workFilters['sort'] === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                                            <option value="deadline_soon" <?= $workFilters['sort'] === 'deadline_soon' ? 'selected' : ''; ?>>Nearest deadline</option>
                                            <option value="difficulty_high" <?= $workFilters['sort'] === 'difficulty_high' ? 'selected' : ''; ?>>Highest difficulty</option>
                                            <option value="title_az" <?= $workFilters['sort'] === 'title_az' ? 'selected' : ''; ?>>Title A-Z</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="hub-work-filter-actions">
                                    <button class="primary-btn" type="submit">Apply filters</button>
                                    <a class="ghost-btn" href="hub.php?groupId=<?= (int) $groupId; ?>">Clear</a>
                                </div>
                            </form>
                        </section>
                        <section class="feed-switcher">
                            <div class="feed-mode-toggle">
                                <button class="mode-chip active" type="button" data-view="current">
                                    <span class="mode-chip-label">Current hub</span>
                                    <span class="mode-chip-subtitle">Everything inside this hub</span>
                                </button>

                                <button class="mode-chip" type="button" data-view="saved">
                                    <span class="mode-chip-label">Saved</span>
                                    <span class="mode-chip-subtitle">Saved tasks and projects</span>
                                </button>

                                
                            </div>
                        </section>

                        <section class="composer" id="postComposer">
                            <div class="composer-avatar"><?= $defaultUserId !== null ? 'AY' : 'CS'; ?></div>

                            <div class="composer-body">
                                <div class="composer-head">
                                    <div>
                                        <div class="composer-title">Create a post in <?= h($currentHub['name'] ?? 'this hub'); ?></div>
                                        <div class="composer-subtitle">Share an update, ask something, or post a resource</div>
                                    </div>
                                    <div class="composer-visibility">Visible in this hub</div>
                                </div>

                                <form method="POST" class="field-grid modern-post-form" id="hubPostForm" novalidate>
                                    <input type="hidden" name="action" value="create_hub_post">
                                    <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">

                                    <div class="post-top-row">
                                        <div class="field grow">
                                            <label>Title</label>
                                            <input type="text" id="hubPostTitle" name="title" value="<?= h($_POST['action'] ?? '' ? ($_POST['title'] ?? '') : ''); ?>" placeholder="What do you want to share?" data-required-message="Title is required.">
                                            <small class="field-error" id="hubPostTitleError"><?= h($hubPostErrors['title']); ?></small>
                                        </div>

                                        <div class="field type-field">
                                            <label>Post type</label>
                                            <select id="hubPostType" name="postType" data-required-message="Post type is required.">
                                                <option value="discussion">Discussion</option>
                                                <option value="question">Question</option>
                                                <option value="resource">Resource</option>
                                            </select>
                                            <small class="field-error" id="hubPostTypeError"><?= h($hubPostErrors['postType']); ?></small>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label>Content</label>
                                        <textarea id="hubPostContent" name="content" rows="5" placeholder="Write your post here..." data-required-message="Content is required."><?= h($_POST['action'] ?? '' ? ($_POST['content'] ?? '') : ''); ?></textarea>
                                        <small class="field-error" id="hubPostContentError"><?= h($hubPostErrors['content']); ?></small>
                                    </div>

                                    <div class="card-actions">
                                        <button class="primary-btn" type="submit">Publish post</button>
                                    </div>
                                </form>
                            </div>
                        </section>

                        <section class="feed view-panel" id="currentFeed">
                            <?php foreach ($filteredWorkItems as $item) { ?>
                                <article class="feed-card interactive-card" data-type="<?= h($item['type'] === 'project' ? 'projects' : 'tasks'); ?>" data-hub="<?= h(strtolower((string) $currentHub['category'])); ?>">
                                    <div class="card-head">
                                        <div class="author-line">
                                            <div class="avatar <?= avatarClass($item['managerName'] ? 'manager' : 'user'); ?>"><?= initials($item['managerName'] ?? 'Manager'); ?></div>
                                            <div>
                                                <div class="author-name"><?= h($item['managerName'] ?? 'Manager'); ?></div>
                                                <div class="author-meta"><?= h($item['hubName']); ?> - <?= h((string) $item['createdAt']); ?></div>
                                            </div>
                                        </div>
                                        <span class="content-tag <?= $item['type'] === 'project' ? 'project' : 'task'; ?>"><?= h(ucfirst((string) $item['type'])); ?></span>
                                    </div>
                                    <h3><?= h($item['title']); ?></h3>
                                    <p><?= h($item['description']); ?></p>
                                    <div class="content-meta">
                                        <span>Difficulty: <?= h($item['difficulty']); ?></span>
                                        <span>Deadline: <?= h((string) $item['deadline']); ?></span>
                                        <span>Status: <?= h($item['status']); ?></span>
                                    </div>
                                    <div class="card-actions slim">
                                        <a class="link-btn" href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=overview"><?= $item['type'] === 'project' ? 'Open project' : 'Start task'; ?></a>
                                        <a class="link-btn" href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=discussion">Open discussion</a>
                                    </div>
                                    <div class="hover-tray">
                                        <div class="hover-tray-meta">
                                            <span><?= h(ucfirst((string) $item['type'])); ?></span>
                                            <span><?= h($item['difficulty']); ?></span>
                                            <span><?= h($item['status']); ?></span>
                                        </div>
                                        <div class="card-actions hover-cta">
                                            <a class="primary-btn" href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=overview"><?= $item['type'] === 'project' ? 'Open project' : 'Start task'; ?></a>
                                            <a class="ghost-btn" href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=discussion">Open discussion</a>
                                        </div>
                                    </div>
                                </article>
                            <?php } ?>

                            <?php if (empty($filteredWorkItems)) { ?>
                                <article class="feed-card filter-empty-card" data-type="tasks" data-hub="<?= h(strtolower((string) ($currentHub['category'] ?? 'hub'))); ?>">
                                    <h3>No work items match these filters</h3>
                                    <p>Try a broader keyword, switch the type, or reset the hub filters to see all available tasks and projects again.</p>
                                </article>
                            <?php } ?>

                            <?php foreach ($hubPosts as $post) { ?>
                                <article class="feed-card" data-type="discussions" data-hub="<?= h(strtolower((string) ($currentHub['category'] ?? 'hub'))); ?>">
                                    <div class="card-head">
                                        <div class="author-line">
                                            <div class="avatar <?= avatarClass($post['role'] ?? 'user'); ?>"><?= initials($post['fullName']); ?></div>
                                            <div>
                                                <div class="author-name"><?= h($post['fullName']); ?></div>
                                                <div class="author-meta"><?= h($currentHub['name'] ?? 'Hub'); ?> - <?= h((string) $post['createdAt']); ?></div>
                                            </div>
                                        </div>
                                        <span class="content-tag discussion"><?= h(ucfirst((string) $post['postType'])); ?></span>
                                    </div>
                                    <h3><?= h($post['title']); ?></h3>
                                    <p><?= h($post['content']); ?></p>
                                    <div class="engagement-row">
                                        <span><?= h((string) $post['commentCount']); ?> comments</span>
                                        <span>Status: <?= h($post['status']); ?></span>
                                    </div>
                                    <div class="card-actions slim">
                                        <a class="link-btn" href="forum.php?groupId=<?= (int) $groupId; ?>&postId=<?= (int) $post['postId']; ?>">Open forum</a>
                                    </div>
                                </article>
                            <?php } ?>
                        </section>
                        <section class="saved-feed view-panel is-hidden" id="savedFeed">
    <div class="saved-feed-hero">
        <div class="strip-kicker">Saved items</div>
        <h2>Saved tasks and projects</h2>
        <p>Everything you saved for later appears here.</p>
    </div>

    <?php if (!empty($savedItems)) { ?>
        <div class="saved-feed-list">
            <?php foreach ($savedItems as $item) { ?>
                <article class="feed-card saved-work-card">
                    <div class="card-head">
                        <div class="author-line">
                            <div class="avatar <?= avatarClass($item['managerName'] ? 'manager' : 'user'); ?>">
                                <?= initials($item['managerName'] ?? 'Manager'); ?>
                            </div>
                            <div>
                                <div class="author-name"><?= h($item['managerName'] ?? 'Manager'); ?></div>
                                <div class="author-meta"><?= h($item['hubName']); ?> - <?= h((string) $item['createdAt']); ?></div>
                            </div>
                        </div>
                        <span class="content-tag <?= $item['type'] === 'project' ? 'project' : 'task'; ?>">
                            <?= h(ucfirst((string) $item['type'])); ?>
                        </span>
                    </div>

                    <h3><?= h($item['title']); ?></h3>
                    <p><?= h($item['description']); ?></p>

                    <div class="content-meta">
                        <span>Difficulty: <?= h($item['difficulty']); ?></span>
                        <span>Deadline: <?= h((string) $item['deadline']); ?></span>
                        <span>Status: <?= h($item['status']); ?></span>
                    </div>

                    <div class="card-actions slim saved-card-tools">
                        <a class="link-btn" href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=overview">
                            <?= $item['type'] === 'project' ? 'Open project' : 'Start task'; ?>
                        </a>
                        <button class="pin-btn" type="button">Pin to board</button>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="saved-feed-empty">No saved tasks or projects yet.</div>
    <?php } ?>
</section>

                    </div>
                </section>

                <aside class="right-column">
                    <section class="glass-panel">
                        <div class="panel-title">Today</div>
                        <div class="live-list">
                            <div class="live-item">
                                <strong><?= count($workItems); ?> work items</strong>
                                <span>tasks and projects currently available</span>
                            </div>
                            <div class="live-item">
                                <strong><?= count($hubPosts); ?> hub threads</strong>
                                <span>discussions visible in this community</span>
                            </div>
                            <div class="live-item">
                                <strong><?= count($members); ?> visible members</strong>
                                <span>people shown in this hub panel</span>
                            </div>
                        </div>
                    </section>

                    <section class="glass-panel" id="people">
                        <div class="panel-title">People to follow</div>
                        <div class="people-list">
                            <?php foreach ($members as $member) { ?>
                                <div class="person-row">
                                    <div class="avatar <?= avatarClass($member['role']); ?>"><?= initials($member['fullName']); ?></div>
                                    <div class="person-copy">
                                        <strong><?= h($member['fullName']); ?></strong>
                                        <p><?= h(ucfirst((string) $member['role'])); ?></p>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const chips = document.querySelectorAll('.mode-chip');
    const currentFeed = document.getElementById('currentFeed');
    const savedFeed = document.getElementById('savedFeed');
    //const pinnedFeed = document.getElementById('pinnedFeed');
    const composer = document.getElementById('postComposer');

    chips.forEach(chip => {
        chip.addEventListener('click', function () {
            chips.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            const view = this.dataset.view;

            currentFeed.classList.add('is-hidden');
            savedFeed.classList.add('is-hidden');
            //pinnedFeed.classList.add('is-hidden');
            composer.classList.add('is-hidden');

            if (view === 'current') {
                currentFeed.classList.remove('is-hidden');
                composer.classList.remove('is-hidden');
            } else if (view === 'saved') {
                savedFeed.classList.remove('is-hidden');
            } //else if (view === 'pinned') {
                //pinnedFeed.classList.remove('is-hidden');
            //}
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('hubPostForm');
    if (!form) return;

    const fieldMap = {
        hubPostTitle: 'hubPostTitleError',
        hubPostType: 'hubPostTypeError',
        hubPostContent: 'hubPostContentError'
    };

    const setFieldError = (field, message) => {
        const error = document.getElementById(fieldMap[field.id] || '');
        if (error) error.textContent = message;
        field.classList.toggle('is-invalid', Boolean(message));
        field.dataset.invalid = message ? 'true' : 'false';
    };

    const validateRequired = (field) => {
        const message = field.value.trim() ? '' : (field.dataset.requiredMessage || 'This field is required.');
        setFieldError(field, message);
        return !message;
    };

    form.querySelectorAll('[data-required-message]').forEach((field) => {
        const eventName = field.tagName === 'SELECT' ? 'change' : 'input';
        field.addEventListener(eventName, () => validateRequired(field));
        field.addEventListener('blur', () => validateRequired(field));
    });

    form.addEventListener('submit', function (event) {
        form.querySelectorAll('[data-required-message]').forEach((field) => validateRequired(field));
        const invalid = form.querySelector('[data-invalid="true"]');
        if (invalid) {
            event.preventDefault();
            invalid.focus();
        }
    });
});
</script>
</body>

</html>

