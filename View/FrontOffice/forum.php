<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../Model/SkillHubEngagement.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';

$frontUser = requireFrontUser();

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();
$forumCommentError = '';

$users = $pdo->query("SELECT userId, fullName, role FROM Users")->fetchAll();
$userMap = [];
foreach ($users as $user) {
    $userMap[(int) $user['userId']] = $user;
}

$defaultUserId = (int) $frontUser['userId'];
$allHubs = $coreController->getAllSkillHubs();
$groupId = isset($_GET['groupId']) ? (int) $_GET['groupId'] : (!empty($allHubs) ? (int) $allHubs[0]['groupId'] : 0);
$selectedPostId = isset($_GET['postId']) ? (int) $_GET['postId'] : 0;
$commentSort = strtolower(trim((string) ($_GET['commentSort'] ?? 'newest')));
$allowedCommentSorts = ['newest', 'oldest'];
if (!in_array($commentSort, $allowedCommentSorts, true)) {
    $commentSort = 'newest';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $defaultUserId !== null) {
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
            header('Location: forum.php?groupId=' . $targetGroupId . '&postId=' . $postId . '&commentSort=' . rawurlencode($commentSort));
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

usort($comments, static function (array $left, array $right) use ($commentSort): int {
    $leftTime = strtotime((string) ($left['createdAt'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['createdAt'] ?? '')) ?: 0;

    if ($commentSort === 'oldest') {
        return $leftTime <=> $rightTime;
    }

    return $rightTime <=> $leftTime;
});

$commentSortLabels = [
    'newest' => 'Newest',
    'oldest' => 'Oldest',
];

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
    <link rel="stylesheet" href="assets/css/front-nav.css">
</head>
<body>
    <div class="page-shell">
        <?php
            $activePage = 'skillhub';
            $brandSubtitle = 'hub forum';
            include __DIR__ . '/partials/front-nav.php';
        ?>

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
                            <form method="GET" class="forum-sort-form" aria-label="Sort comments">
                                <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                <input type="hidden" name="postId" value="<?= (int) $selectedPost['postId']; ?>">
                                <span class="sort-copy">Sort by</span>
                                <div class="forum-sort-options">
                                    <?php foreach ($commentSortLabels as $sortValue => $sortLabel) { ?>
                                        <button
                                            class="forum-sort-option<?= $commentSort === $sortValue ? ' is-active' : ''; ?>"
                                            type="submit"
                                            name="commentSort"
                                            value="<?= h($sortValue); ?>"
                                        >
                                            <?= h($sortLabel); ?>
                                        </button>
                                    <?php } ?>
                                </div>
                            </form>
                            <div class="search-comments">
                                <span>Search</span>
                                <input id="forumCommentSearch" type="search" autocomplete="off" placeholder="Author, content, or date" aria-label="Search comments">
                                <button class="forum-search-clear" id="forumSearchClear" type="button" aria-label="Clear comment search">Clear</button>
                            </div>
                        </div>

                        <div class="comment-thread" id="forumCommentThread">
                            <?php if (empty($comments)) { ?>
                                <div class="empty-state">No comments yet. Start the first reply in this hub thread.</div>
                            <?php } ?>
                            <?php foreach ($comments as $comment) { ?>
                                <article class="comment-node" data-comment-search="<?= h(strtolower(($comment['fullName'] ?? '') . ' ' . ($comment['content'] ?? '') . ' ' . ($comment['createdAt'] ?? ''))); ?>">
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
                            <div class="empty-state forum-search-empty" id="forumSearchEmpty">No comments match your search.</div>
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

        if (form && input && error) {
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
        }

        const searchInput = document.getElementById('forumCommentSearch');
        const searchEmpty = document.getElementById('forumSearchEmpty');
        const searchClear = document.getElementById('forumSearchClear');
        const commentNodes = Array.from(document.querySelectorAll('.comment-node'));

        if (searchInput && searchEmpty && commentNodes.length > 0) {
            const filterComments = () => {
                const query = searchInput.value.trim().toLowerCase();
                let visibleCount = 0;

                commentNodes.forEach((node) => {
                    const matches = query === '' || (node.dataset.commentSearch || '').includes(query);
                    node.classList.toggle('is-hidden', !matches);
                    if (matches) {
                        visibleCount += 1;
                    }
                });

                searchEmpty.classList.toggle('is-visible', query !== '' && visibleCount === 0);
                if (searchClear) {
                    searchClear.classList.toggle('is-visible', query !== '');
                }
            };

            searchInput.addEventListener('input', filterComments);
            if (searchClear) {
                searchClear.addEventListener('click', () => {
                    searchInput.value = '';
                    filterComments();
                    searchInput.focus();
                });
            }

            filterComments();
        }
    });
    </script>
</body>
</html>
