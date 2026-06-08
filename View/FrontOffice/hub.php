<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../Controller/MentorshipRequestController.php';
require_once __DIR__ . '/../../Model/SkillHubEngagement.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../utils/FrontOfficePeople.php';

$frontUser = requireFrontUser();

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$mentorshipController = new MentorshipRequestController();
$pdo = config::getConnexion();
$hubPostErrors = [
    'title' => '',
    'content' => '',
    'postType' => '',
];

$userMap = frontPeopleMap($pdo);

$defaultUserId = (int) $frontUser['userId'];
$defaultPerson = $userMap[$defaultUserId] ?? [
    'userId' => $defaultUserId,
    'fullName' => (string) ($frontUser['fullName'] ?? 'CareerStrand user'),
    'role' => (string) ($frontUser['role'] ?? 'user'),
    'photoUrl' => '',
];
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
    $action = (string) ($_POST['action'] ?? '');
    $targetGroupId = (int) ($_POST['groupId'] ?? $groupId);

    if ($action === 'follow_member') {
        $engagementController->followHubMember($targetGroupId, $defaultUserId, (int) ($_POST['followedId'] ?? 0));
        header('Location: hub.php?groupId=' . $targetGroupId . '#people');
        exit;
    }

    if ($action === 'unfollow_member') {
        $engagementController->unfollowHubMember($targetGroupId, $defaultUserId, (int) ($_POST['followedId'] ?? 0));
        header('Location: hub.php?groupId=' . $targetGroupId . '#people');
        exit;
    }

    if ($action === 'send_hub_message') {
        $engagementController->createHubMessage(
            $targetGroupId,
            $defaultUserId,
            (int) ($_POST['receiverId'] ?? 0),
            (string) ($_POST['message'] ?? '')
        );
        header('Location: hub.php?groupId=' . $targetGroupId . '&chatWith=' . (int) ($_POST['receiverId'] ?? 0) . '#people');
        exit;
    }

    if ($action === 'request_hub_mentor') {
        $mentorResult = $mentorshipController->createRequest([
            'requesterId' => $defaultUserId,
            'mentorId' => (int) ($_POST['mentorId'] ?? 0),
            'sourceType' => 'hub',
            'sourceId' => $targetGroupId,
            'requestType' => 'office_hours',
            'sessionMode' => 'chat',
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'details' => trim((string) ($_POST['details'] ?? '')),
            'durationMinutes' => 30,
        ]);
        $mentorFlag = !empty($mentorResult['success']) ? 'mentor=requested' : 'mentor=failed';
        header('Location: hub.php?groupId=' . $targetGroupId . '&' . $mentorFlag . '#askMentor');
        exit;
    }

    if ($action === 'create_hub_post') {
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

$allSubmissions = $engagementController->getAllSubmissions();
$submissionCounts = [];
$reviewedSubmissionCounts = [];
foreach ($allSubmissions as $submissionRow) {
    $challengeId = (int) ($submissionRow['challengeId'] ?? 0);
    if ($challengeId <= 0) {
        continue;
    }
    $submissionCounts[$challengeId] = ($submissionCounts[$challengeId] ?? 0) + 1;
    if (($submissionRow['score'] ?? null) !== null) {
        $reviewedSubmissionCounts[$challengeId] = ($reviewedSubmissionCounts[$challengeId] ?? 0) + 1;
    }
}

$featuredWork = $filteredWorkItems[0] ?? ($workItems[0] ?? null);
$dueSoonWork = array_values(array_filter($workItems, static function (array $item): bool {
    $deadline = strtotime((string) ($item['deadline'] ?? ''));
    if ($deadline === false) {
        return false;
    }
    $days = (int) floor(($deadline - time()) / 86400);
    return $days >= 0 && $days <= 14;
}));
usort($dueSoonWork, static fn(array $left, array $right): int => strtotime((string) ($left['deadline'] ?? '')) <=> strtotime((string) ($right['deadline'] ?? '')));
$dueSoonWork = array_slice($dueSoonWork, 0, 3);
$recommendedWork = array_values(array_filter(
    $coreController->getRecommendedChallengesForUser($defaultUserId, 12),
    static fn(array $item): bool => (int) ($item['groupId'] ?? 0) === $groupId
));
if (empty($recommendedWork)) {
    $recommendedWork = array_slice($filteredWorkItems, 0, 3);
}

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
$followedMemberIds = $engagementController->getFollowedMemberIds($groupId, $defaultUserId);
$followedMemberSet = array_fill_keys($followedMemberIds, true);
$followerCounts = $engagementController->getFollowerCounts($groupId);
$reputationScores = $engagementController->getReputationByHub($groupId);
$chatWithUserId = (int) ($_GET['chatWith'] ?? 0);
$members = array_map(static function (array $member) use ($userMap): array {
    $user = $userMap[(int) $member['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $member['fullName'] = $user['fullName'];
    $member['role'] = $user['role'];
    $member['photoUrl'] = $user['photoUrl'] ?? '';
    return $member;
}, $members);
usort($members, static function (array $left, array $right): int {
    $joinedComparison = strcmp((string) ($right['joinedAt'] ?? ''), (string) ($left['joinedAt'] ?? ''));
    if ($joinedComparison !== 0) {
        return $joinedComparison;
    }

    return strcmp((string) ($left['fullName'] ?? ''), (string) ($right['fullName'] ?? ''));
});
$visibleMembers = array_slice($members, 0, 6);
$visibleMembers = array_map(static function (array $member) use ($engagementController, $groupId, $defaultUserId, $followedMemberSet, $followerCounts, $reputationScores): array {
    $memberUserId = (int) ($member['userId'] ?? 0);
    $member['isFollowed'] = isset($followedMemberSet[$memberUserId]);
    $member['followerCount'] = $followerCounts[$memberUserId] ?? 0;
    $member['reputationScore'] = $reputationScores[$memberUserId] ?? 0;
    $member['conversation'] = $memberUserId > 0
        ? $engagementController->getHubConversation($groupId, $defaultUserId, $memberUserId, 8)
        : [];
    return $member;
}, $visibleMembers);
$reputationLeaders = array_slice(array_keys($reputationScores), 0, 3);
$mentorCandidates = array_values(array_filter(
    $mentorshipController->listEligibleMentors(20),
    static fn(array $mentor): bool => (int) ($mentor['userId'] ?? 0) !== $defaultUserId
));

$weekStart = new DateTimeImmutable('monday this week');
$weeklyFocus = $featuredWork !== null
    ? (string) ($featuredWork['title'] ?? 'Choose a challenge')
    : 'Start a hub conversation';
$weeklyRitual = [
    'range' => $weekStart->format('M j') . ' - ' . $weekStart->modify('+6 days')->format('M j'),
    'focus' => $weeklyFocus,
    'shipTarget' => count($dueSoonWork) > 0 ? count($dueSoonWork) . ' deadline-sensitive work item' . (count($dueSoonWork) === 1 ? '' : 's') : 'one useful post or submission',
    'reviewTarget' => max(1, count($hubPosts)),
];

$hubFitReasons = [];
if ($currentHub !== null) {
    $hubFitReasons[] = 'This hub has ' . count($workItems) . ' task/project item' . (count($workItems) === 1 ? '' : 's') . ' you can turn into visible proof.';
    if (!empty($currentHub['skillFocus'])) {
        $hubFitReasons[] = 'Its focus area is ' . (string) $currentHub['skillFocus'] . ', which makes the practice path easier to understand.';
    }
    if (!empty($members)) {
        $hubFitReasons[] = 'There are ' . count($members) . ' members in the room, so feedback can come from peers as well as managers.';
    }
}

$savedItems = array_slice($workItems, 0, 4);
$pinnedItems = array_slice($savedItems, 0, 3);  

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand | Hub</title>
    <link rel="stylesheet" href="assets/css/hub.css?v=2">
    <link rel="stylesheet" href="assets/css/front-nav.css">
</head>
<body>
    <div class="page-shell">
        <div class="ambient ambient-a"></div>
        <div class="ambient ambient-b"></div>

        <?php
            $activePage = 'skillhub';
            $brandSubtitle = 'social skill space';
            include __DIR__ . '/partials/front-nav.php';
        ?>

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

                        <section class="hub-upgrade-grid" aria-label="Hub growth layer">
                            <article class="hub-upgrade-card weekly-ritual-card">
                                <div class="strip-kicker">Weekly ritual</div>
                                <h3><?= h($weeklyRitual['range']); ?></h3>
                                <p>Focus on <?= h($weeklyRitual['focus']); ?>, then leave one useful update before the week closes.</p>
                                <div class="ritual-steps">
                                    <span>Plan: pick one item</span>
                                    <span>Ship: <?= h($weeklyRitual['shipTarget']); ?></span>
                                    <span>Review: respond to <?= h((string) $weeklyRitual['reviewTarget']); ?> thread<?= (int) $weeklyRitual['reviewTarget'] === 1 ? '' : 's'; ?></span>
                                </div>
                            </article>

                            <article class="hub-upgrade-card">
                                <div class="strip-kicker">Why this hub fits</div>
                                <h3><?= h($currentHub['name'] ?? 'This hub'); ?></h3>
                                <div class="reason-stack">
                                    <?php foreach (array_slice($hubFitReasons, 0, 3) as $reason) { ?>
                                        <span><?= h($reason); ?></span>
                                    <?php } ?>
                                </div>
                            </article>
                        </section>

                        <section class="work-layer-panel">
                            <div class="hub-filter-head">
                                <div>
                                    <div class="strip-kicker">Work layer</div>
                                    <h3>Portfolio-ready work inside this hub</h3>
                                </div>
                                <div class="hub-filter-badge"><?= count($workItems); ?> total</div>
                            </div>
                            <div class="work-layer-grid">
                                <article class="work-spotlight <?= $featuredWork === null ? 'is-empty' : ''; ?>">
                                    <?php if ($featuredWork !== null) { ?>
                                        <span class="content-tag <?= ($featuredWork['type'] ?? '') === 'project' ? 'project' : 'task'; ?>">
                                            <?= h(ucfirst((string) ($featuredWork['type'] ?? 'task'))); ?>
                                        </span>
                                        <h4><?= h((string) ($featuredWork['title'] ?? 'Featured work')); ?></h4>
                                        <p><?= h((string) ($featuredWork['description'] ?? '')); ?></p>
                                        <div class="content-meta">
                                            <span><?= h((string) ($featuredWork['difficulty'] ?? 'Open level')); ?></span>
                                            <span><?= h((string) ($submissionCounts[(int) $featuredWork['challengeId']] ?? 0)); ?> submissions</span>
                                            <span><?= h((string) ($reviewedSubmissionCounts[(int) $featuredWork['challengeId']] ?? 0)); ?> reviewed</span>
                                        </div>
                                        <a class="primary-btn compact-action" href="thread.php?challengeId=<?= (int) $featuredWork['challengeId']; ?>&view=overview">Open work</a>
                                    <?php } else { ?>
                                        <div class="strip-kicker">Nothing published yet</div>
                                        <h4>Work will appear here once this hub gets tasks or projects.</h4>
                                        <p>As soon as a manager posts a challenge, this spotlight becomes the hub's main launch point.</p>
                                    <?php } ?>
                                </article>

                                <div class="work-rail">
                                    <div class="work-rail-block">
                                        <strong>Recommended next</strong>
                                        <?php if (empty($recommendedWork)) { ?>
                                            <span class="quiet-line">No recommendations yet because this hub does not have active work.</span>
                                        <?php } ?>
                                        <?php foreach (array_slice($recommendedWork, 0, 3) as $item) { ?>
                                            <a href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=overview">
                                                <span><?= h((string) ($item['title'] ?? 'Untitled work')); ?></span>
                                                <em><?= h((string) ($item['recommendationScore'] ?? $item['difficulty'] ?? 'Fit')); ?><?= isset($item['recommendationScore']) ? '/100' : ''; ?></em>
                                            </a>
                                        <?php } ?>
                                    </div>
                                    <div class="work-rail-block">
                                        <strong>Due soon</strong>
                                        <?php if (empty($dueSoonWork)) { ?>
                                            <span class="quiet-line">No urgent deadlines in this hub.</span>
                                        <?php } ?>
                                        <?php foreach ($dueSoonWork as $item) { ?>
                                            <a href="thread.php?challengeId=<?= (int) $item['challengeId']; ?>&view=overview">
                                                <span><?= h((string) $item['title']); ?></span>
                                                <em><?= h((string) $item['deadline']); ?></em>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
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
                            <div class="composer-avatar <?= frontPersonPhotoUrl($defaultPerson) !== '' ? 'has-photo' : ''; ?>">
                                <?php if (frontPersonPhotoUrl($defaultPerson) !== '') { ?>
                                    <img src="<?= h(frontPersonPhotoUrl($defaultPerson)); ?>" alt="<?= h(frontPersonDisplayName($defaultPerson)); ?>">
                                <?php } else { ?>
                                    <?= h(frontPersonInitials(frontPersonDisplayName($defaultPerson))); ?>
                                <?php } ?>
                            </div>

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
                                            <?php $managerPerson = $userMap[(int) ($item['managerId'] ?? 0)] ?? ['fullName' => (string) ($item['managerName'] ?? 'Manager'), 'role' => 'manager', 'photoUrl' => '']; ?>
                                            <div class="avatar <?= frontPersonRoleClass($managerPerson['role'] ?? 'manager'); ?> <?= frontPersonPhotoUrl($managerPerson) !== '' ? 'has-photo' : ''; ?>">
                                                <?php if (frontPersonPhotoUrl($managerPerson) !== '') { ?>
                                                    <img src="<?= h(frontPersonPhotoUrl($managerPerson)); ?>" alt="<?= h(frontPersonDisplayName($managerPerson, 'Manager')); ?>">
                                                <?php } else { ?>
                                                    <?= h(frontPersonInitials(frontPersonDisplayName($managerPerson, 'Manager'))); ?>
                                                <?php } ?>
                                            </div>
                                            <div>
                                                <div class="author-name">
                                                    <a class="person-link" href="<?= h(frontPersonProfileUrl((int) ($managerPerson['userId'] ?? 0))); ?>"><?= h(frontPersonDisplayName($managerPerson, 'Manager')); ?></a>
                                                </div>
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
                                        <?php if (!empty($item['managerId'])) { ?>
                                            <a class="link-btn" href="mentor-requests.php?sourceType=hub&sourceId=<?= (int) $groupId; ?>&sourceLabel=<?= rawurlencode((string) ($currentHub['name'] ?? 'Skill Hub')); ?>">Request admin office hours</a>
                                        <?php } ?>
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
                                            <?php if (!empty($item['managerId'])) { ?>
                                                <a class="ghost-btn" href="mentor-requests.php?sourceType=hub&sourceId=<?= (int) $groupId; ?>&sourceLabel=<?= rawurlencode((string) ($currentHub['name'] ?? 'Skill Hub')); ?>">Admin office hours</a>
                                            <?php } ?>
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
                                    <?php $postPerson = $userMap[(int) ($post['userId'] ?? 0)] ?? ['fullName' => (string) ($post['fullName'] ?? 'Unknown'), 'role' => (string) ($post['role'] ?? 'user'), 'photoUrl' => '']; ?>
                                    <div class="card-head">
                                        <div class="author-line">
                                            <div class="avatar <?= frontPersonRoleClass($postPerson['role'] ?? 'user'); ?> <?= frontPersonPhotoUrl($postPerson) !== '' ? 'has-photo' : ''; ?>">
                                                <?php if (frontPersonPhotoUrl($postPerson) !== '') { ?>
                                                    <img src="<?= h(frontPersonPhotoUrl($postPerson)); ?>" alt="<?= h(frontPersonDisplayName($postPerson, 'Unknown')); ?>">
                                                <?php } else { ?>
                                                    <?= h(frontPersonInitials(frontPersonDisplayName($postPerson, 'Unknown'))); ?>
                                                <?php } ?>
                                            </div>
                                            <div>
                                                <div class="author-name">
                                                    <a class="person-link" href="<?= h(frontPersonProfileUrl((int) ($postPerson['userId'] ?? 0))); ?>"><?= h(frontPersonDisplayName($postPerson, 'Unknown')); ?></a>
                                                </div>
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
                    <?php $savedManager = $userMap[(int) ($item['managerId'] ?? 0)] ?? ['fullName' => (string) ($item['managerName'] ?? 'Manager'), 'role' => 'manager', 'photoUrl' => '']; ?>
                    <div class="card-head">
                        <div class="author-line">
                            <div class="avatar <?= frontPersonRoleClass($savedManager['role'] ?? 'manager'); ?> <?= frontPersonPhotoUrl($savedManager) !== '' ? 'has-photo' : ''; ?>">
                                <?php if (frontPersonPhotoUrl($savedManager) !== '') { ?>
                                    <img src="<?= h(frontPersonPhotoUrl($savedManager)); ?>" alt="<?= h(frontPersonDisplayName($savedManager, 'Manager')); ?>">
                                <?php } else { ?>
                                    <?= h(frontPersonInitials(frontPersonDisplayName($savedManager, 'Manager'))); ?>
                                <?php } ?>
                            </div>
                            <div>
                                <div class="author-name">
                                    <a class="person-link" href="<?= h(frontPersonProfileUrl((int) ($savedManager['userId'] ?? 0))); ?>"><?= h(frontPersonDisplayName($savedManager, 'Manager')); ?></a>
                                </div>
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
                                <strong><?= count($visibleMembers); ?> visible members</strong>
                                <span>people shown in this hub panel</span>
                            </div>
                        </div>
                    </section>

                    <section class="glass-panel reputation-panel">
                        <div class="panel-title">Reputation</div>
                        <h3>Hub credibility</h3>
                        <div class="reputation-list">
                            <?php if (empty($reputationLeaders)) { ?>
                                <div class="quiet-line">Reputation appears after posts, replies, follows, and reviewed work.</div>
                            <?php } ?>
                            <?php foreach ($reputationLeaders as $leaderId) { ?>
                                <?php $leader = $userMap[(int) $leaderId] ?? ['fullName' => 'Hub member', 'role' => 'user', 'photoUrl' => '']; ?>
                                <div class="reputation-row">
                                    <div class="avatar <?= frontPersonRoleClass($leader['role'] ?? 'user'); ?> <?= frontPersonPhotoUrl($leader) !== '' ? 'has-photo' : ''; ?>">
                                        <?php if (frontPersonPhotoUrl($leader) !== '') { ?>
                                            <img src="<?= h(frontPersonPhotoUrl($leader)); ?>" alt="<?= h(frontPersonDisplayName($leader)); ?>">
                                        <?php } else { ?>
                                            <?= h(frontPersonInitials(frontPersonDisplayName($leader))); ?>
                                        <?php } ?>
                                    </div>
                                    <div>
                                        <strong><?= h(frontPersonDisplayName($leader)); ?></strong>
                                        <span><?= h((string) ($reputationScores[(int) $leaderId] ?? 0)); ?> reputation</span>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </section>

                    <section class="glass-panel ask-mentor-panel" id="askMentor">
                        <div class="panel-title">Ask a mentor</div>
                        <h3>Get help for this hub</h3>
                        <?php if (isset($_GET['mentor']) && $_GET['mentor'] === 'requested') { ?>
                            <div class="hub-success-note">Mentor request sent for this hub.</div>
                        <?php } elseif (isset($_GET['mentor']) && $_GET['mentor'] === 'failed') { ?>
                            <div class="hub-error-note">Could not send the request. Choose a mentor and add a short brief.</div>
                        <?php } ?>
                        <?php if (empty($mentorCandidates)) { ?>
                            <p class="quiet-line">No mentors are available right now.</p>
                        <?php } else { ?>
                            <form method="POST" class="mentor-mini-form">
                                <input type="hidden" name="action" value="request_hub_mentor">
                                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                <label>
                                    <span>Mentor</span>
                                    <select name="mentorId" required>
                                        <?php foreach ($mentorCandidates as $mentor) { ?>
                                            <option value="<?= (int) $mentor['userId']; ?>"><?= h((string) $mentor['fullName']); ?> - <?= h((string) $mentor['role']); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Subject</span>
                                    <input name="subject" value="<?= h(($currentHub['name'] ?? 'Skill Hub') . ' support request'); ?>" required>
                                </label>
                                <label>
                                    <span>Brief</span>
                                    <textarea name="details" rows="3" required placeholder="What do you need help with in this hub?"></textarea>
                                </label>
                                <button class="primary-btn" type="submit">Send request</button>
                            </form>
                        <?php } ?>
                    </section>

                    <section class="glass-panel" id="people">
                        <div class="panel-title">People to follow</div>
                        <div class="people-list">
                            <?php foreach ($visibleMembers as $member) { ?>
                                <?php $memberPerson = $userMap[(int) ($member['userId'] ?? 0)] ?? ['fullName' => (string) ($member['fullName'] ?? 'Unknown'), 'role' => (string) ($member['role'] ?? 'user'), 'photoUrl' => '']; ?>
                                <div class="person-row">
                                    <div class="avatar <?= frontPersonRoleClass($memberPerson['role'] ?? 'user'); ?> <?= frontPersonPhotoUrl($memberPerson) !== '' ? 'has-photo' : ''; ?>">
                                        <?php if (frontPersonPhotoUrl($memberPerson) !== '') { ?>
                                            <img src="<?= h(frontPersonPhotoUrl($memberPerson)); ?>" alt="<?= h(frontPersonDisplayName($memberPerson, 'Unknown')); ?>">
                                        <?php } else { ?>
                                            <?= h(frontPersonInitials(frontPersonDisplayName($memberPerson, 'Unknown'))); ?>
                                        <?php } ?>
                                    </div>
                                    <div class="person-copy">
                                        <strong><a class="person-link" href="<?= h(frontPersonProfileUrl((int) ($memberPerson['userId'] ?? 0))); ?>"><?= h(frontPersonDisplayName($memberPerson, 'Unknown')); ?></a></strong>
                                        <p><?= h(ucfirst((string) $member['role'])); ?> - <?= h((string) $member['reputationScore']); ?> rep - <?= h((string) $member['followerCount']); ?> followers</p>
                                    </div>
                                    <?php if ((int) ($member['userId'] ?? 0) !== $defaultUserId) { ?>
                                        <div class="person-actions">
                                            <form method="POST">
                                                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                                <input type="hidden" name="followedId" value="<?= (int) $member['userId']; ?>">
                                                <input type="hidden" name="action" value="<?= !empty($member['isFollowed']) ? 'unfollow_member' : 'follow_member'; ?>">
                                                <button class="mini-action-btn" type="submit"><?= !empty($member['isFollowed']) ? 'Following' : 'Follow'; ?></button>
                                            </form>
                                            <button
                                                class="mini-action-btn"
                                                type="button"
                                                data-chat-open
                                                data-user-id="<?= (int) $member['userId']; ?>"
                                                data-user-name="<?= h(frontPersonDisplayName($memberPerson, 'Unknown')); ?>"
                                                data-conversation="<?= h(json_encode($member['conversation'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'); ?>"
                                            >Message</button>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </div>
    <div class="hub-chat-modal" id="hubChatModal" aria-hidden="true">
        <div class="hub-chat-backdrop" data-chat-close></div>
        <section class="hub-chat-dialog" role="dialog" aria-modal="true" aria-labelledby="hubChatTitle">
            <div class="hub-chat-head">
                <div>
                    <div class="panel-title">Hub message</div>
                    <h3 id="hubChatTitle">Conversation</h3>
                </div>
                <button class="drawer-close" type="button" data-chat-close>Close</button>
            </div>
            <div class="hub-chat-log" id="hubChatLog">
                <div class="quiet-line">No messages yet.</div>
            </div>
            <form class="hub-chat-form" method="POST">
                <input type="hidden" name="action" value="send_hub_message">
                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                <input type="hidden" name="receiverId" id="hubChatReceiverId" value="">
                <textarea name="message" rows="3" required placeholder="Write a message..."></textarea>
                <button class="primary-btn" type="submit">Send</button>
            </form>
        </section>
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
    const modal = document.getElementById('hubChatModal');
    const title = document.getElementById('hubChatTitle');
    const log = document.getElementById('hubChatLog');
    const receiver = document.getElementById('hubChatReceiverId');

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    document.querySelectorAll('[data-chat-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const userName = button.dataset.userName || 'Hub member';
            const userId = button.dataset.userId || '';
            let conversation = [];
            try {
                conversation = JSON.parse(button.dataset.conversation || '[]');
            } catch (error) {
                conversation = [];
            }

            title.textContent = userName;
            receiver.value = userId;
            log.innerHTML = conversation.length
                ? conversation.map((message) => `
                    <div class="hub-chat-bubble ${Number(message.senderId) === <?= (int) $defaultUserId; ?> ? 'mine' : ''}">
                        <strong>${escapeHtml(message.senderName || 'Member')}</strong>
                        <p>${escapeHtml(message.message || '')}</p>
                        <span>${escapeHtml(message.createdAt || '')}</span>
                    </div>
                `).join('')
                : '<div class="quiet-line">No messages yet. Start the conversation from this hub.</div>';

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelectorAll('[data-chat-close]').forEach((button) => {
        button.addEventListener('click', () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
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
