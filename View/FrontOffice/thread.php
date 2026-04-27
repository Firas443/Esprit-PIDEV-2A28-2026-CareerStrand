<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../Model/SkillHubEngagement.php';

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();
$threadPostErrors = ['title' => '', 'content' => '', 'postType' => ''];
$threadCommentErrors = [];
$submissionErrors = ['projectLink' => '', 'description' => ''];

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
$allComments = $engagementController->getAllComments();
$allSubmissions = $engagementController->getAllSubmissions();

$challengeId = isset($_GET['challengeId']) ? (int) $_GET['challengeId'] : 0;
$groupId = isset($_GET['groupId']) ? (int) $_GET['groupId'] : 0;
$selectedPostId = isset($_GET['postId']) ? (int) $_GET['postId'] : 0;
$initialView = $_GET['view'] ?? 'overview';

if ($challengeId === 0 && $groupId > 0) {
    $redirect = 'forum.php?groupId=' . $groupId;
    if ($selectedPostId > 0) {
        $redirect .= '&postId=' . $selectedPostId;
    }
    header('Location: ' . $redirect);
    exit;
}

if ($challengeId === 0 && $groupId === 0) {
    usort($allHubs, static fn(array $left, array $right): int => (int) $left['groupId'] <=> (int) $right['groupId']);
    $firstHubId = !empty($allHubs) ? (int) $allHubs[0]['groupId'] : null;
    if ($firstHubId !== null) {
        $items = array_values(array_filter($allChallenges, static fn(array $challenge): bool => (int) $challenge['groupId'] === $firstHubId));
        if (!empty($items)) {
            $challengeId = (int) $items[0]['challengeId'];
        } else {
            $groupId = $firstHubId;
        }
    }
}

$currentMember = null;
$workItem = null;
if ($challengeId > 0) {
    $challengeRow = $coreController->getChallengeById($challengeId);
    if ($challengeRow !== null) {
        $hub = null;
        foreach ($allHubs as $hubRow) {
            if ((int) $hubRow['groupId'] === (int) $challengeRow['groupId']) {
                $hub = $hubRow;
                break;
            }
        }
        $manager = $userMap[(int) $challengeRow['managerId']] ?? ['fullName' => 'Unknown'];
        $workItem = $challengeRow;
        $workItem['hubName'] = $hub['name'] ?? 'Skill Hub';
        $workItem['managerName'] = $manager['fullName'] ?? 'Unknown';
    }
}
if ($workItem !== null) {
    $groupId = (int) $workItem['groupId'];
}

$submissionNotice = $_GET['submission'] ?? '';
if ($groupId > 0 && $defaultUserId !== null) {
    $matchingMembers = array_values(array_filter(
        $allMembers,
        static fn(array $member): bool => (int) $member['groupId'] === $groupId && (int) $member['userId'] === $defaultUserId
    ));
    usort($matchingMembers, static function (array $left, array $right): int {
        $leftRank = in_array(strtolower((string) ($left['status'] ?? '')), ['active', 'joined'], true) ? 0 : 1;
        $rightRank = in_array(strtolower((string) ($right['status'] ?? '')), ['active', 'joined'], true) ? 0 : 1;
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcmp((string) ($right['joinedAt'] ?? ''), (string) ($left['joinedAt'] ?? ''));
    });
    $currentMember = $matchingMembers[0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $defaultUserId !== null) {
    $action = $_POST['action'] ?? '';

    if ($action === 'join_submission_group') {
        $targetChallengeId = (int) ($_POST['challengeId'] ?? $challengeId);
        $targetGroupId = (int) ($_POST['groupId'] ?? $groupId);
        $member = null;

        foreach ($allMembers as $memberRow) {
            if ((int) $memberRow['groupId'] === $targetGroupId && (int) $memberRow['userId'] === $defaultUserId) {
                $member = $memberRow;
                break;
            }
        }

        if ($member === null && $targetGroupId > 0) {
            $created = $coreController->createGroupMember(
                new GroupMemberEntity(
                    null,
                    $targetGroupId,
                    $defaultUserId,
                    date('Y-m-d'),
                    'active'
                )
            );

            header('Location: thread.php?challengeId=' . $targetChallengeId . '&view=overview&submission=' . ($created ? 'ready' : 'failed'));
            exit;
        }

        header('Location: thread.php?challengeId=' . $targetChallengeId . '&view=overview&submission=ready');
        exit;
    }

    if ($action === 'create_thread_post') {
        $targetGroupId = (int) ($_POST['groupId'] ?? $groupId);
        $targetChallengeId = (int) ($_POST['challengeId'] ?? $challengeId);
        $submittedPostType = trim((string) ($_POST['postType'] ?? ''));
        $submittedTitle = trim((string) ($_POST['title'] ?? ''));
        $submittedContent = trim((string) ($_POST['content'] ?? ''));

        if ($submittedTitle === '') {
            $threadPostErrors['title'] = 'Title is required.';
        }
        if ($submittedContent === '') {
            $threadPostErrors['content'] = 'Content is required.';
        }
        if ($submittedPostType === '') {
            $threadPostErrors['postType'] = 'Post type is required.';
        }

        if (!array_filter($threadPostErrors)) {
            $engagementController->createPost(
                new PostEntity(
                    null,
                    $targetGroupId,
                    $defaultUserId,
                    $targetChallengeId,
                    $submittedPostType,
                    $submittedTitle,
                    $submittedContent,
                    'active',
                    null
                )
            );
            header('Location: thread.php?challengeId=' . $targetChallengeId . '&view=discussion');
            exit;
        }
    }

    if ($action === 'create_comment') {
        $postId = (int) ($_POST['postId'] ?? 0);
        $targetChallengeId = (int) ($_POST['challengeId'] ?? $challengeId);
        $submittedContent = trim((string) ($_POST['content'] ?? ''));
        if ($submittedContent === '') {
            $threadCommentErrors[$postId] = 'Comment is required.';
        } elseif ($postId > 0) {
            $engagementController->createComment(
                new CommentEntity(
                    null,
                    $postId,
                    $defaultUserId,
                    null,
                    $submittedContent,
                    0,
                    'active'
                )
            );
            header('Location: thread.php?challengeId=' . $targetChallengeId . '&postId=' . $postId . '&view=discussion');
            exit;
        }
    }

    if ($action === 'create_submission') {
        $targetChallengeId = (int) ($_POST['challengeId'] ?? $challengeId);
        $targetGroupId = (int) ($_POST['groupId'] ?? $groupId);
        $projectLink = trim((string) ($_POST['projectLink'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $member = null;
        foreach ($allMembers as $memberRow) {
            if ((int) $memberRow['groupId'] === $targetGroupId && (int) $memberRow['userId'] === $defaultUserId) {
                $member = $memberRow;
                break;
            }
        }
        $status = 'failed';

        if ($projectLink === '') {
            $submissionErrors['projectLink'] = 'Project link is required.';
        } elseif (!filter_var($projectLink, FILTER_VALIDATE_URL)) {
            $submissionErrors['projectLink'] = 'Enter a valid project link.';
        }
        if ($description === '') {
            $submissionErrors['description'] = 'Description is required.';
        }

        if ($targetChallengeId > 0 && !array_filter($submissionErrors)) {
            if ($member === null) {
                $status = 'not_member';
            } elseif ($engagementController->createSubmission(
                new SubmissionEntity(
                    null,
                    (int) $member['groupMemberId'],
                    $targetChallengeId,
                    $projectLink,
                    $description,
                    date('Y-m-d'),
                    null,
                    null,
                    'submitted'
                )
            )) {
                $status = 'created';
            }
        }

        if ($status === 'created' || $status === 'not_member') {
            header('Location: thread.php?challengeId=' . $targetChallengeId . '&view=overview&submission=' . $status);
            exit;
        }
    }
}

$commentCounts = [];
foreach ($allComments as $commentRow) {
    $commentCounts[(int) $commentRow['postId']] = ($commentCounts[(int) $commentRow['postId']] ?? 0) + 1;
}

$threadPosts = array_values(array_filter($allPosts, static function (array $post) use ($groupId, $challengeId): bool {
    if ($challengeId > 0) {
        return ((int) $post['challengeId'] === $challengeId) || (empty($post['challengeId']) && (int) $post['groupId'] === $groupId);
    }

    return (int) $post['groupId'] === $groupId;
}));
$threadPosts = array_map(static function (array $post) use ($userMap, $commentCounts): array {
    $user = $userMap[(int) $post['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $post['fullName'] = $user['fullName'];
    $post['role'] = $user['role'];
    $post['commentCount'] = $commentCounts[(int) $post['postId']] ?? 0;
    return $post;
}, $threadPosts);
usort($threadPosts, static fn(array $left, array $right): int => strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? '')));
if ($selectedPostId === 0 && !empty($threadPosts)) {
    $selectedPostId = (int) $threadPosts[0]['postId'];
}

$selectedPost = null;
foreach ($threadPosts as $post) {
    if ((int) $post['postId'] === $selectedPostId) {
        $selectedPost = $post;
        break;
    }
}

$comments = array_values(array_filter($allComments, static fn(array $comment): bool => (int) $comment['postId'] === $selectedPostId));
$comments = array_map(static function (array $comment) use ($userMap): array {
    $user = $userMap[(int) $comment['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $comment['fullName'] = $user['fullName'];
    $comment['role'] = $user['role'];
    return $comment;
}, $comments);

$members = array_values(array_filter($allMembers, static fn(array $member): bool => (int) $member['groupId'] === $groupId));
$members = array_map(static function (array $member) use ($userMap): array {
    $user = $userMap[(int) $member['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $member['fullName'] = $user['fullName'];
    $member['role'] = $user['role'];
    return $member;
}, $members);
usort($members, static fn(array $left, array $right): int => strcmp((string) ($right['joinedAt'] ?? ''), (string) ($left['joinedAt'] ?? '')));
$members = array_slice($members, 0, 5);

$submissions = array_values(array_filter($allSubmissions, static fn(array $submission): bool => (int) $submission['challengeId'] === $challengeId));
$groupMemberMap = [];
foreach ($allMembers as $memberRow) {
    $groupMemberMap[(int) $memberRow['groupMemberId']] = $memberRow;
}
$submissions = array_map(static function (array $submission) use ($groupMemberMap, $userMap): array {
    $member = $groupMemberMap[(int) $submission['groupMemberId']] ?? null;
    $user = $member !== null ? ($userMap[(int) $member['userId']] ?? ['fullName' => 'Unknown member', 'role' => 'user']) : ['fullName' => 'Unknown member', 'role' => 'user'];
    $submission['fullName'] = $user['fullName'];
    $submission['role'] = $user['role'];
    return $submission;
}, $submissions);
usort($submissions, static function (array $left, array $right): int {
    $submittedComparison = strcmp((string) ($right['submittedAt'] ?? ''), (string) ($left['submittedAt'] ?? ''));
    if ($submittedComparison !== 0) {
        return $submittedComparison;
    }

    return ((int) ($right['submissionId'] ?? 0)) <=> ((int) ($left['submissionId'] ?? 0));
});

$latestSubmission = null;
if ($currentMember !== null) {
    foreach ($submissions as $submission) {
        if ((int) $submission['groupMemberId'] === (int) $currentMember['groupMemberId']) {
            $latestSubmission = $submission;
            break;
        }
    }
}
$showSubmissionForm = ($_POST['action'] ?? '') === 'create_submission' || !empty(array_filter($submissionErrors));
$submissionCount = count($submissions);
$rankedSubmissions = $submissions;
usort($rankedSubmissions, static function (array $left, array $right): int {
    $leftScore = $left['score'] === null ? -1 : (int) $left['score'];
    $rightScore = $right['score'] === null ? -1 : (int) $right['score'];
    if ($leftScore !== $rightScore) {
        return $rightScore <=> $leftScore;
    }

    $leftRank = $left['submissionRank'] === null ? PHP_INT_MAX : (int) $left['submissionRank'];
    $rightRank = $right['submissionRank'] === null ? PHP_INT_MAX : (int) $right['submissionRank'];
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    $submittedComparison = strcmp((string) ($right['submittedAt'] ?? ''), (string) ($left['submittedAt'] ?? ''));
    if ($submittedComparison !== 0) {
        return $submittedComparison;
    }

    return ((int) ($right['submissionId'] ?? 0)) <=> ((int) ($left['submissionId'] ?? 0));
});
$scoredSubmissions = array_values(array_filter($rankedSubmissions, static fn(array $submission): bool => $submission['score'] !== null));
$topSubmission = $scoredSubmissions[0] ?? null;
$recentSubmissions = array_slice($rankedSubmissions, 0, 4);
$submissionFeedback = match ($submissionNotice) {
    'created' => 'Your work was submitted under your hub membership.',
    'ready' => 'You are now a hub member and can submit your work.',
    'not_member' => 'You need to belong to this hub before you can submit work for it.',
    'failed' => 'The submission could not be saved. Please try again.',
    default => '',
};

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

function formatDateLabel(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Not set';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y', $timestamp) : $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand | Thread</title>
    <link rel="stylesheet" href="assets/css/thread.css">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="container header-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="images/CareerStrand_logo.png" alt="CareerStrand logo">
                    <div>
                        <div class="brand-title">CareerStrand</div>
                        <div class="brand-subtitle">Thread workspace</div>
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
                    <a class="primary-btn" href="hub.php?groupId=<?= (int) $groupId; ?>">Open hub</a>
                </div>
            </div>
        </header>

        <main class="main-area">
            <div class="container page-grid">
                <section class="thread-column">
                    <section class="hero-panel">
                        <div class="hero-top">
                            <div>
                                <div class="eyebrow"><?= h(($workItem['hubName'] ?? 'Skill Hub') . ' / ' . ucfirst((string) ($workItem['type'] ?? 'task')) . ' Thread'); ?></div>
                                <h1><?= h($workItem['title'] ?? 'Thread workspace'); ?></h1>
                                <p class="hero-copy"><?= h($workItem['description'] ?? 'This page combines the work item and its discussion space.'); ?></p>
                            </div>
                            <div class="hero-badge">Status: <?= h($workItem['status'] ?? 'active'); ?></div>
                        </div>

                        <div class="hero-meta-grid">
                            <div class="meta-card">
                                <span class="meta-label">Difficulty</span>
                                <strong><?= h($workItem['difficulty'] ?? 'Not set'); ?></strong>
                                <span class="meta-copy">Current challenge level from the database.</span>
                            </div>
                            <div class="meta-card">
                                <span class="meta-label">Manager</span>
                                <strong><?= h($workItem['managerName'] ?? 'Unknown'); ?></strong>
                                <span class="meta-copy">Current owner of this task or project.</span>
                            </div>
                            <div class="meta-card">
                                <span class="meta-label">Deadline</span>
                                <strong><?= h((string) ($workItem['deadline'] ?? 'Not set')); ?></strong>
                                <span class="meta-copy">Stored deadline for this work item.</span>
                            </div>
                        </div>

                        <div class="hero-actions">
                            <a class="primary-btn" href="thread.php?challengeId=<?= (int) $challengeId; ?>&view=overview">Overview</a>
                            <a class="ghost-btn" href="thread.php?challengeId=<?= (int) $challengeId; ?>&view=discussion">Join discussion</a>
                        </div>
                    </section>

                    <section class="view-switcher" aria-label="Thread view switcher">
                        <a class="view-chip <?= $initialView !== 'discussion' ? 'active' : ''; ?>" href="thread.php?challengeId=<?= (int) $challengeId; ?>&view=overview">
                            <span class="view-chip-title">Overview</span>
                            <span class="view-chip-copy">Brief, deliverables, timeline</span>
                        </a>
                        <a class="view-chip <?= $initialView === 'discussion' ? 'active' : ''; ?>" href="thread.php?challengeId=<?= (int) $challengeId; ?>&view=discussion">
                            <span class="view-chip-title">Discussion</span>
                            <span class="view-chip-copy">Replies, questions, resources</span>
                        </a>
                    </section>

                    <?php if ($initialView !== 'discussion') { ?>
                        <section class="thread-view" id="overviewView">
                            <?php if ($submissionFeedback !== '') { ?>
                                <article class="panel wide">
                                    <div class="panel-title">Submission update</div>
                                    <p><?= h($submissionFeedback); ?></p>
                                </article>
                            <?php } ?>

                            <div class="content-grid">
                                <article class="panel wide">
                                    <div class="panel-title">Work brief</div>
                                    <h2><?= h($workItem['title'] ?? 'No item selected'); ?></h2>
                                    <p><?= h($workItem['description'] ?? 'Choose a task or project from a hub to see its full brief here.'); ?></p>
                                    <div class="stack-list">
                                        <div class="stack-item">
                                            <strong>Type</strong>
                                            <span><?= h(ucfirst((string) ($workItem['type'] ?? 'task'))); ?></span>
                                        </div>
                                        <div class="stack-item">
                                            <strong>Hub</strong>
                                            <span><?= h($workItem['hubName'] ?? 'Skill Hub'); ?></span>
                                        </div>
                                        <div class="stack-item">
                                            <strong>Discussion threads</strong>
                                            <span><?= count($threadPosts); ?> posts currently linked to this page.</span>
                                        </div>
                                    </div>
                                </article>

                                <article class="panel">
                                    <div class="panel-title">Submission status</div>
                                    <div class="submission-card">
                                        <div class="submission-row">
                                            <span>Status</span>
                                            <strong><?= h($latestSubmission['status'] ?? 'Not submitted yet'); ?></strong>
                                        </div>
                                        <div class="submission-row">
                                            <span>Submitted as</span>
                                            <strong><?= $currentMember !== null ? 'Group member #' . (int) $currentMember['groupMemberId'] : 'Hub member required'; ?></strong>
                                        </div>
                                        <div class="submission-row">
                                            <span>Challenge submissions</span>
                                            <strong><?= $submissionCount; ?></strong>
                                        </div>
                                        <div class="submission-row">
                                            <span>Scored submissions</span>
                                            <strong><?= count($scoredSubmissions); ?></strong>
                                        </div>
                                        <div class="submission-row">
                                            <span>Latest date</span>
                                            <strong><?= h(formatDateLabel($latestSubmission['submittedAt'] ?? null)); ?></strong>
                                        </div>
                                        <?php if (!empty($latestSubmission['projectLink'])) { ?>
                                            <div class="submission-row">
                                                <span>Latest link</span>
                                                <strong><a href="<?= h($latestSubmission['projectLink']); ?>" target="_blank" rel="noopener noreferrer">Open submission</a></strong>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </article>


                                <article class="panel submit-panel">
                                    <div class="panel-title">Submit work</div>
                                    <?php if ($defaultUserId === null) { ?>
                                        <p>A user is required before a submission can be created.</p>
                                    <?php } elseif ($currentMember === null) { ?>
                                        <div class="submission-actions">
                                            <p>This page now submits through `groupmember`, so the current user must first be a member of this hub.</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="join_submission_group">
                                                <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                                <button class="primary-btn full" type="submit">Join hub and open submission</button>
                                            </form>
                                        </div>
                                    <?php } else { ?>
                                        <div class="submission-actions">
                                            <p>Open the submission form when you are ready to send your final work for this challenge.</p>
                                            <button
                                                class="primary-btn full"
                                                type="button"
                                                id="toggleSubmissionFormButton"
                                                aria-expanded="<?= $showSubmissionForm ? 'true' : 'false'; ?>"
                                                aria-controls="submissionFormPanel"
                                            >
                                                <?= $showSubmissionForm ? 'Hide submission form' : 'Start submission'; ?>
                                            </button>
                                        </div>
                                        <div id="submissionFormPanel" <?= $showSubmissionForm ? '' : 'hidden'; ?>>
                                            <form method="POST" class="submission-card submission-form-shell" id="submissionForm" novalidate>
                                                <input type="hidden" name="action" value="create_submission">
                                                <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                                <label class="discussion-input" for="projectLink">
                                                    <strong>Project link</strong>
                                                    <span>Share the repo, demo, or document link for this entry.</span>
                                                    <input id="projectLink" name="projectLink" type="text" value="<?= h($_POST['action'] ?? '' ? ($_POST['projectLink'] ?? '') : ''); ?>" placeholder="https://example.com/my-work" data-required-message="Project link is required." data-validation="url" style="width:100%;margin-top:14px;padding:16px 18px;border-radius:18px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#f5f3ee;">
                                                </label>
                                                <small class="field-error" id="submissionProjectLinkError"><?= h($submissionErrors['projectLink']); ?></small>
                                                <label class="discussion-input" for="submissionDescription" style="margin-top:14px;">
                                                    <strong>Description</strong>
                                                    <span>Summarize what you built, decisions made, or what reviewers should check.</span>
                                                    <textarea id="submissionDescription" name="description" rows="4" placeholder="Describe the submitted work..." data-required-message="Description is required."><?= h($_POST['action'] ?? '' ? ($_POST['description'] ?? '') : ''); ?></textarea>
                                                </label>
                                                <small class="field-error" id="submissionDescriptionError"><?= h($submissionErrors['description']); ?></small>
                                                <div class="discussion-actions" style="margin-top:14px;">
                                                    <button class="primary-btn full" type="submit">Submit as group member</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php } ?>
                                </article>

                                <article class="panel wide">
                                    <div class="panel-title">Recent submissions</div>
                                    <?php if (empty($recentSubmissions)) { ?>
                                        <p>No one has submitted work for this challenge yet.</p>
                                    <?php } else { ?>
                                        <div class="recent-submission-grid">
                                            <?php foreach ($recentSubmissions as $submission) { ?>
                                                <article class="recent-submission-card">
                                                    <div class="recent-submission-top">
                                                        <div class="recent-submission-identity">
                                                            <span class="rank-badge">#<?= h((string) ($submission['submissionRank'] ?? '-')); ?></span>
                                                            <div>
                                                                <strong><?= h($submission['fullName'] ?? 'Unknown member'); ?></strong>
                                                                <span><?= h(formatDateLabel($submission['submittedAt'] ?? null)); ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="recent-submission-badges">
                                                            <span class="score-badge"><?= $submission['score'] !== null ? h((string) $submission['score']) . '/100' : 'Pending'; ?></span>
                                                            <span class="status-pill"><?= h(ucfirst((string) ($submission['status'] ?? 'submitted'))); ?></span>
                                                        </div>
                                                    </div>
                                                    <p class="recent-submission-copy"><?= h($submission['description'] ?? 'No description provided.'); ?></p>
                                                    <div class="recent-submission-footer">
                                                        <span>Submitted by hub member</span>
                                                        <?php if (!empty($submission['projectLink'])) { ?>
                                                            <a href="<?= h($submission['projectLink']); ?>" target="_blank" rel="noopener noreferrer">Open work</a>
                                                        <?php } else { ?>
                                                            <span>No link shared</span>
                                                        <?php } ?>
                                                    </div>
                                                </article>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </article>
                            </div>
                        </section>
                    <?php } else { ?>
                        <section class="thread-view" id="discussionView">
                            <section class="discussion-composer">
                                <div class="composer-avatar"><?= $defaultUserId !== null ? 'AY' : 'CS'; ?></div>
                                <div class="discussion-composer-body">
                                    <div class="discussion-heading">
                                        <strong>Reply inside this thread</strong>
                                        <span>Create a real post stored in the discussion table.</span>
                                    </div>
                                    <form method="POST" id="threadPostForm" novalidate>
                                        <input type="hidden" name="action" value="create_thread_post">
                                        <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                        <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                        <label class="discussion-input" for="threadTitle">
                                            <strong>Thread title</strong>
                                            <span>Questions, clarification, or a resource for this work item.</span>
                                            <input id="threadTitle" name="title" type="text" value="<?= h($_POST['action'] ?? '' ? ($_POST['title'] ?? '') : ''); ?>" placeholder="Open a new thread in this task discussion" data-required-message="Title is required." style="width:100%;margin-top:14px;padding:16px 18px;border-radius:18px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#f5f3ee;">
                                            <small class="field-error" id="threadPostTitleError"><?= h($threadPostErrors['title']); ?></small>
                                            <textarea id="threadPostContent" name="content" rows="4" placeholder="Write your reply, question, or resource note here..." data-required-message="Content is required."><?= h($_POST['action'] ?? '' ? ($_POST['content'] ?? '') : ''); ?></textarea>
                                        </label>
                                        <small class="field-error" id="threadPostContentError"><?= h($threadPostErrors['content']); ?></small>
                                        <div class="discussion-actions">
                                            <select id="threadPostType" name="postType" class="tool-chip" style="appearance:none;" data-required-message="Post type is required.">
                                                <option value="discussion">Discussion</option>
                                                <option value="question">Question</option>
                                                <option value="resource">Resource</option>
                                            </select>
                                            <small class="field-error" id="threadPostTypeError"><?= h($threadPostErrors['postType']); ?></small>
                                            <button class="primary-btn" type="submit">Post reply</button>
                                        </div>
                                    </form>
                                </div>
                            </section>

                            <section class="discussion-list">
                                <?php foreach ($threadPosts as $post) { ?>
                                    <article class="discussion-card <?= $selectedPostId === (int) $post['postId'] ? 'featured-reply' : ''; ?>">
                                        <div class="discussion-head">
                                            <div class="author-line">
                                                <div class="avatar <?= avatarClass($post['role']); ?>"><?= initials($post['fullName']); ?></div>
                                                <div>
                                                    <div class="author-name"><?= h($post['fullName']); ?></div>
                                                    <div class="author-meta"><?= h((string) $post['createdAt']); ?></div>
                                                </div>
                                            </div>
                                            <span class="tag-pill"><?= h(ucfirst((string) $post['postType'])); ?></span>
                                        </div>
                                        <h3><?= h($post['title']); ?></h3>
                                        <p><?= h($post['content']); ?></p>
                                        <div class="reply-meta">
                                            <span><?= h((string) $post['commentCount']); ?> comments</span>
                                            <span><?= h($post['status']); ?></span>
                                        </div>
                                        <div class="reply-actions">
                                            <a class="link-btn" href="thread.php?challengeId=<?= (int) $challengeId; ?>&postId=<?= (int) $post['postId']; ?>&view=discussion">Open thread</a>
                                        </div>

                                        <?php if ($selectedPostId === (int) $post['postId']) { ?>
                                            <section class="thread-comments" style="margin-top:18px;">
                                                <div class="thread-comments-header">
                                                    <div class="thread-comments-title">
                                                        <strong>Comments</strong>
                                                        <span><?= count($comments); ?> comments</span>
                                                    </div>
                                                </div>
                                                <div class="thread-comments-list">
                                                    <?php foreach ($comments as $comment) { ?>
                                                        <article class="thread-comment">
                                                            <div class="thread-comment-head">
                                                                <div class="avatar <?= avatarClass($comment['role']); ?>"><?= initials($comment['fullName']); ?></div>
                                                                <div>
                                                                    <div class="author-name"><?= h($comment['fullName']); ?></div>
                                                                    <div class="thread-comment-meta"><?= h((string) $comment['createdAt']); ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="thread-comment-body"><?= h($comment['content']); ?></div>
                                                        </article>
                                                    <?php } ?>
                                                </div>

                                                <section class="thread-comment-composer">
                                                    <form method="POST" class="thread-comment-entry threadCommentForm" novalidate>
                                                        <input type="hidden" name="action" value="create_comment">
                                                        <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                                        <input type="hidden" name="postId" value="<?= (int) $post['postId']; ?>">
                                                        <div class="avatar user"><?= $defaultUserId !== null ? 'AY' : 'CS'; ?></div>
                                                        <div class="thread-comment-box">
                                                            <label class="discussion-input thread-inline-input">
                                                                <strong>Join the conversation</strong>
                                                                <span>Respond inside this thread so the discussion stays with the task.</span>
                                                                <textarea id="threadCommentInput<?= (int) $post['postId']; ?>" name="content" rows="4" placeholder="Write a comment..." data-required-message="Comment is required."><?= h(($_POST['action'] ?? '') === 'create_comment' && ((int) ($_POST['postId'] ?? 0) === (int) $post['postId']) ? ($_POST['content'] ?? '') : ''); ?></textarea>
                                                            </label>
                                                            <small class="field-error" id="threadCommentError<?= (int) $post['postId']; ?>"><?= h($threadCommentErrors[(int) $post['postId']] ?? ''); ?></small>
                                                            <div class="discussion-actions thread-comment-actions">
                                                                <button class="primary-btn" type="submit">Comment</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </section>
                                            </section>
                                        <?php } ?>
                                    </article>
                                <?php } ?>
                            </section>
                        </section>
                    <?php } ?>
                </section>

                <aside class="side-column">
                    <section class="panel">
                        <div class="panel-title">Thread status</div>
                        <div class="status-list">
                            <div class="status-row">
                                <span>Open discussion</span>
                                <strong>Yes</strong>
                            </div>
                            <div class="status-row">
                                <span>Posts linked</span>
                                <strong><?= count($threadPosts); ?></strong>
                            </div>
                            <div class="status-row">
                                <span>Comments</span>
                                <strong><?= count($comments); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-title">People in this thread</div>
                        <div class="people-list">
                            <?php foreach ($members as $member) { ?>
                                <div class="person-row">
                                    <div class="avatar <?= avatarClass($member['role']); ?>"><?= initials($member['fullName']); ?></div>
                                    <div>
                                        <strong><?= h($member['fullName']); ?></strong>
                                        <p><?= h(ucfirst((string) $member['role'])); ?></p>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-title">Leaderboard</div>
                        <?php if ($topSubmission === null) { ?>
                            <p>No scored submissions yet. Rankings will appear here after a manager evaluates the work.</p>
                        <?php } else { ?>
                            <div class="submission-card leaderboard-card champion-card">
                                <div class="leaderboard-rank">#<?= h((string) ($topSubmission['submissionRank'] ?? 1)); ?></div>
                                <div class="leaderboard-copy">
                                    <span class="leaderboard-label">Current leader</span>
                                    <strong><?= h($topSubmission['fullName'] ?? 'Unknown member'); ?></strong>
                                    <span><?= h((string) ($topSubmission['score'] ?? 0)); ?>/100 | <?= h((string) ($topSubmission['status'] ?? 'reviewed')); ?></span>
                                    <span><?= h(formatDateLabel($topSubmission['submittedAt'] ?? null)); ?></span>
                                </div></div><?php } ?>
                    </section>
                </aside>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const setFieldError = (field, error, message) => {
            if (error) error.textContent = message;
            field.classList.toggle('is-invalid', Boolean(message));
            field.dataset.invalid = message ? 'true' : 'false';
        };

        const validateRequired = (field, error) => {
            const message = field.value.trim() ? '' : (field.dataset.requiredMessage || 'This field is required.');
            setFieldError(field, error, message);
            return !message;
        };

        const validateUrl = (field, error) => {
            const value = field.value.trim();
            if (!value) {
                setFieldError(field, error, field.dataset.requiredMessage || 'Project link is required.');
                return false;
            }
            try {
                new URL(value);
                setFieldError(field, error, '');
                return true;
            } catch (errorObj) {
                setFieldError(field, error, 'Enter a valid project link.');
                return false;
            }
        };

        const threadPostForm = document.getElementById('threadPostForm');
        if (threadPostForm) {
            const title = document.getElementById('threadTitle');
            const titleError = document.getElementById('threadPostTitleError');
            const content = document.getElementById('threadPostContent');
            const contentError = document.getElementById('threadPostContentError');
            const type = document.getElementById('threadPostType');
            const typeError = document.getElementById('threadPostTypeError');

            title.addEventListener('input', () => validateRequired(title, titleError));
            title.addEventListener('blur', () => validateRequired(title, titleError));
            content.addEventListener('input', () => validateRequired(content, contentError));
            content.addEventListener('blur', () => validateRequired(content, contentError));
            type.addEventListener('change', () => validateRequired(type, typeError));
            type.addEventListener('blur', () => validateRequired(type, typeError));

            threadPostForm.addEventListener('submit', function (event) {
                const valid = [
                    validateRequired(title, titleError),
                    validateRequired(content, contentError),
                    validateRequired(type, typeError)
                ].every(Boolean);
                if (!valid) {
                    event.preventDefault();
                    (threadPostForm.querySelector('[data-invalid="true"]') || title).focus();
                }
            });
        }

        const submissionForm = document.getElementById('submissionForm');
        if (submissionForm) {
            const submissionToggleButton = document.getElementById('toggleSubmissionFormButton');
            const submissionFormPanel = document.getElementById('submissionFormPanel');
            const projectLink = document.getElementById('projectLink');
            const projectLinkError = document.getElementById('submissionProjectLinkError');
            const description = document.getElementById('submissionDescription');
            const descriptionError = document.getElementById('submissionDescriptionError');

            const syncSubmissionButton = () => {
                if (!submissionToggleButton || !submissionFormPanel) {
                    return;
                }

                const expanded = !submissionFormPanel.hidden;
                submissionToggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                submissionToggleButton.textContent = expanded ? 'Hide submission form' : 'Start submission';
            };

            if (submissionToggleButton && submissionFormPanel) {
                submissionToggleButton.addEventListener('click', function () {
                    submissionFormPanel.hidden = !submissionFormPanel.hidden;
                    syncSubmissionButton();

                    if (!submissionFormPanel.hidden) {
                        projectLink.focus();
                    }
                });

                syncSubmissionButton();
            }

            projectLink.addEventListener('input', () => validateUrl(projectLink, projectLinkError));
            projectLink.addEventListener('blur', () => validateUrl(projectLink, projectLinkError));
            description.addEventListener('input', () => validateRequired(description, descriptionError));
            description.addEventListener('blur', () => validateRequired(description, descriptionError));

            submissionForm.addEventListener('submit', function (event) {
                const valid = [
                    validateUrl(projectLink, projectLinkError),
                    validateRequired(description, descriptionError)
                ].every(Boolean);
                if (!valid) {
                    event.preventDefault();
                    (submissionForm.querySelector('[data-invalid="true"]') || projectLink).focus();
                }
            });
        }

        document.querySelectorAll('.threadCommentForm').forEach((form) => {
            const textarea = form.querySelector('textarea[name="content"]');
            const error = form.querySelector('.field-error');
            if (!textarea || !error) return;

            textarea.addEventListener('input', () => validateRequired(textarea, error));
            textarea.addEventListener('blur', () => validateRequired(textarea, error));

            form.addEventListener('submit', function (event) {
                if (!validateRequired(textarea, error)) {
                    event.preventDefault();
                    textarea.focus();
                }
            });
        });
    });
    </script>
</body>
</html>





