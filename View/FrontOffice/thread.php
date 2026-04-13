<?php
require_once __DIR__ . '/../../Controller/SkillHubController.php';

$controller = new SkillHubController();
$defaultUserId = $controller->getDefaultUserId();
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
    $firstHubId = $controller->getFirstHubId();
    if ($firstHubId !== null) {
        $items = $controller->getWorkItems($firstHubId);
        if (!empty($items)) {
            $challengeId = (int) $items[0]['challengeId'];
        } else {
            $groupId = $firstHubId;
        }
    }
}

$workItem = $challengeId > 0 ? $controller->getWorkItemById($challengeId) : null;
if ($workItem !== null) {
    $groupId = (int) $workItem['groupId'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $defaultUserId !== null) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_thread_post') {
        $targetGroupId = (int) ($_POST['groupId'] ?? $groupId);
        $targetChallengeId = (int) ($_POST['challengeId'] ?? $challengeId);
        $controller->createThreadPost(
            $targetGroupId,
            $targetChallengeId,
            $defaultUserId,
            trim((string) ($_POST['title'] ?? 'Thread reply')),
            trim((string) ($_POST['content'] ?? '')),
            trim((string) ($_POST['postType'] ?? 'discussion'))
        );
        header('Location: thread.php?challengeId=' . $targetChallengeId . '&view=discussion');
        exit;
    }

    if ($action === 'create_comment') {
        $postId = (int) ($_POST['postId'] ?? 0);
        $targetChallengeId = (int) ($_POST['challengeId'] ?? $challengeId);
        if ($postId > 0) {
            $controller->createComment($postId, $defaultUserId, trim((string) ($_POST['content'] ?? '')));
        }
        header('Location: thread.php?challengeId=' . $targetChallengeId . '&postId=' . $postId . '&view=discussion');
        exit;
    }
}

$threadPosts = $groupId > 0 ? $controller->getThreadPostsForChallenge($groupId, $challengeId) : [];
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

$comments = $selectedPostId > 0 ? $controller->getCommentsByPost($selectedPostId) : [];
$members = $groupId > 0 ? $controller->getHubMembers($groupId, 5) : [];

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
                                    <form method="POST">
                                        <input type="hidden" name="action" value="create_thread_post">
                                        <input type="hidden" name="groupId" value="<?= (int) $groupId; ?>">
                                        <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                        <label class="discussion-input" for="threadTitle">
                                            <strong>Thread title</strong>
                                            <span>Questions, clarification, or a resource for this work item.</span>
                                            <input id="threadTitle" name="title" type="text" placeholder="Open a new thread in this task discussion" required style="width:100%;margin-top:14px;padding:16px 18px;border-radius:18px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#f5f3ee;">
                                            <textarea name="content" rows="4" placeholder="Write your reply, question, or resource note here..." required></textarea>
                                        </label>
                                        <div class="discussion-actions">
                                            <select name="postType" class="tool-chip" style="appearance:none;">
                                                <option value="discussion">Discussion</option>
                                                <option value="question">Question</option>
                                                <option value="resource">Resource</option>
                                            </select>
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
                                                    <form method="POST" class="thread-comment-entry">
                                                        <input type="hidden" name="action" value="create_comment">
                                                        <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                                        <input type="hidden" name="postId" value="<?= (int) $post['postId']; ?>">
                                                        <div class="avatar user"><?= $defaultUserId !== null ? 'AY' : 'CS'; ?></div>
                                                        <div class="thread-comment-box">
                                                            <label class="discussion-input thread-inline-input">
                                                                <strong>Join the conversation</strong>
                                                                <span>Respond inside this thread so the discussion stays with the task.</span>
                                                                <textarea name="content" rows="4" placeholder="Write a comment..." required></textarea>
                                                            </label>
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
                        <div class="panel-title">Quick links</div>
                        <div class="link-stack">
                            <a href="hub.php?groupId=<?= (int) $groupId; ?>" class="related-link">
                                <strong>Back to current hub</strong>
                                <span>Return to the community feed</span>
                            </a>
                            <a href="skillhub.php" class="related-link">
                                <strong>Browse all hubs</strong>
                                <span>Explore more communities and suggestions</span>
                            </a>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </div>
</body>
</html>
