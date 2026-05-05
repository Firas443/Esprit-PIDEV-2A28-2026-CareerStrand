<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../Model/SkillHubEngagement.php';

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();
$forumCommentError = '';

$users = $pdo->query("SELECT userId, fullName, role FROM Users")->fetchAll();
$userMap = [];
foreach ($users as $user) {
    $userMap[(int) $user['userId']] = $user;
}

$defaultUserId = !empty($users) ? (int) $users[0]['userId'] : null;
$allHubs = $coreController->getAllSkillHubs();
$groupId = isset($_GET['groupId']) ? (int) $_GET['groupId'] : (!empty($allHubs) ? (int) $allHubs[0]['groupId'] : 0);
$selectedPostId = isset($_GET['postId']) ? (int) $_GET['postId'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $defaultUserId !== null) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_comment') {
        $postId = (int) ($_POST['postId'] ?? 0);
        $targetGroupId = (int) ($_POST['groupId'] ?? $groupId);
        $submittedContent = trim((string) ($_POST['content'] ?? ''));
        if ($submittedContent === '') {
            $forumCommentError = 'Comment is required.';
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
            header('Location: forum.php?groupId=' . $targetGroupId . '&postId=' . $postId);
            exit;
        }
    }
}

$currentHub = $groupId > 0 ? $coreController->getSkillHubById($groupId) : null;
$commentRows = $engagementController->getAllComments();
$commentCounts = [];
foreach ($commentRows as $commentRow) {
    $commentCounts[(int) $commentRow['postId']] = ($commentCounts[(int) $commentRow['postId']] ?? 0) + 1;
}

$hubPosts = array_values(array_filter(
    $engagementController->getAllPosts(),
    static fn(array $post): bool => (int) $post['groupId'] === $groupId && empty($post['challengeId'])
));
$hubPosts = array_map(static function (array $post) use ($userMap, $commentCounts): array {
    $user = $userMap[(int) $post['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $post['fullName'] = $user['fullName'];
    $post['role'] = $user['role'];
    $post['commentCount'] = $commentCounts[(int) $post['postId']] ?? 0;
    return $post;
}, $hubPosts);

if ($selectedPostId === 0 && !empty($hubPosts)) {
    $selectedPostId = (int) $hubPosts[0]['postId'];
}

$selectedPost = null;
foreach ($hubPosts as $post) {
    if ((int) $post['postId'] === $selectedPostId) {
        $selectedPost = $post;
        break;
    }
}

$comments = array_values(array_filter(
    $commentRows,
    static fn(array $comment): bool => (int) $comment['postId'] === $selectedPostId
));
$comments = array_map(static function (array $comment) use ($userMap): array {
    $user = $userMap[(int) $comment['userId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $comment['fullName'] = $user['fullName'];
    $comment['role'] = $user['role'];
    return $comment;
}, $comments);

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
    <title>CareerStrand | Hub Forum</title>
    <link rel="stylesheet" href="assets/css/forum.css">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="container header-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="images/CareerStrand_logo.png" alt="CareerStrand logo">
                    <div>
                        <div class="brand-title">CareerStrand</div>
                        <div class="brand-subtitle">Hub forum</div>
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
                    <a class="ghost-btn" href="hub.php?groupId=<?= (int) $groupId; ?>">Back to hub</a>
                </div>
            </div>
        </header>

        <main class="main-area">
            <div class="container forum-container">
                <?php if ($selectedPost !== null) { ?>
                    <article class="post-shell">
                        <div class="post-header">
                            <a class="back-btn" href="hub.php?groupId=<?= (int) $groupId; ?>" aria-label="Back to hub">&larr;</a>
                            <div class="avatar <?= avatarClass($selectedPost['role']); ?>"><?= initials($selectedPost['fullName']); ?></div>
                            <div class="post-meta">
                                <div class="post-community"><?= h($currentHub['name'] ?? 'Hub'); ?> <span>&middot;</span> <?= h((string) $selectedPost['createdAt']); ?></div>
                                <div class="post-author"><?= h($selectedPost['fullName']); ?></div>
                            </div>
                        </div>

                        <span class="post-tag"><?= h(ucfirst((string) $selectedPost['postType'])); ?></span>
                        <h1 class="post-title"><?= h($selectedPost['title']); ?></h1>
                        <p class="post-body"><?= h($selectedPost['content']); ?></p>

                        <div class="post-actions">
                            <div class="action-pill"><?= h((string) $selectedPost['commentCount']); ?> comments</div>
                            <div class="action-pill"><?= h($selectedPost['status']); ?></div>
                            <a class="ghost-btn compact" href="hub.php?groupId=<?= (int) $groupId; ?>">Back to hub</a>
                        </div>
                    </article>

                    <section class="conversation-panel">
                        <form method="POST" class="join-box" id="forumCommentForm" novalidate>
                            <input type="hidden" name="action" value="create_comment">
                            <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                            <input type="hidden" name="postId" value="<?= (int) $selectedPost['postId']; ?>">
                            <textarea id="forumCommentInput" name="content" rows="3" placeholder="Join the conversation" data-required-message="Comment is required."><?= h($_POST['action'] ?? '' ? ($_POST['content'] ?? '') : ''); ?></textarea>
                            <small class="field-error" id="forumCommentError"><?= h($forumCommentError); ?></small>
                            <div class="forum-form-actions">
                                <button class="primary-btn" type="submit">Comment</button>
                            </div>
                        </form>

                        <div class="conversation-tools">
                            <div class="sort-copy">Sort by: <strong>Best</strong></div>
                            <div class="search-comments">Search comments</div>
                        </div>

                        <div class="comment-thread">
                            <?php if (empty($comments)) { ?>
                                <div class="empty-state">No comments yet. Start the first reply in this hub thread.</div>
                            <?php } ?>
                            <?php foreach ($comments as $comment) { ?>
                                <article class="comment-node">
                                    <div class="thread-line">
                                        <span class="thread-dot"></span>
                                    </div>
                                    <div class="comment-card">
                                        <div class="comment-head">
                                            <div class="avatar <?= avatarClass($comment['role']); ?>"><?= initials($comment['fullName']); ?></div>
                                            <div class="comment-meta">
                                                <div class="author-name"><?= h($comment['fullName']); ?></div>
                                                <div class="author-meta"><?= h((string) $comment['createdAt']); ?></div>
                                            </div>
                                        </div>
                                        <div class="comment-body"><?= h($comment['content']); ?></div>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    </section>
                <?php } else { ?>
                    <div class="empty-state tall">There are no hub-wide threads yet. Open a hub and publish the first discussion, question, or resource.</div>
                <?php } ?>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('forumCommentForm');
        const input = document.getElementById('forumCommentInput');
        const error = document.getElementById('forumCommentError');
        if (!form || !input || !error) return;

        const validate = () => {
            const message = input.value.trim() ? '' : (input.dataset.requiredMessage || 'Comment is required.');
            error.textContent = message;
            input.classList.toggle('is-invalid', Boolean(message));
            input.dataset.invalid = message ? 'true' : 'false';
            return !message;
        };

        input.addEventListener('input', validate);
        input.addEventListener('blur', validate);

        form.addEventListener('submit', function (event) {
            if (!validate()) {
                event.preventDefault();
                input.focus();
            }
        });
    });
    </script>
</body>
</html>
