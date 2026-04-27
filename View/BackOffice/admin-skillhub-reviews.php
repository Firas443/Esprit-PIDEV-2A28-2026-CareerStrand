<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();

$challengeId = isset($_GET['challengeId']) ? (int) $_GET['challengeId'] : 0;
$reviewSubmissionId = isset($_GET['reviewSubmission']) ? (int) $_GET['reviewSubmission'] : 0;
$editingSubmissionId = isset($_GET['editSubmission']) ? (int) $_GET['editSubmission'] : 0;
$challenge = $challengeId > 0 ? $coreController->getChallengeById($challengeId) : null;
$message = null;
$messageType = 'soft';
$scoreErrors = [];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'evaluate_submission') {
    $challengeId = (int) ($_POST['challengeId'] ?? $challengeId);
    $submissionId = (int) ($_POST['submissionId'] ?? 0);
    $submittedScore = trim((string) ($_POST['score'] ?? ''));
    $submittedStatus = trim((string) ($_POST['status'] ?? 'reviewed'));

    if ($submittedScore === '') {
        $scoreErrors[$submissionId] = 'Score is required.';
        $message = 'Please complete the score before saving the evaluation.';
        $messageType = 'draft';
    } elseif (filter_var($submittedScore, FILTER_VALIDATE_INT) === false) {
        $scoreErrors[$submissionId] = 'Score must be an integer.';
        $message = 'The score must be a whole number.';
        $messageType = 'draft';
    } else {
        $score = (int) $submittedScore;
        if ($score < 0 || $score > 100) {
            $scoreErrors[$submissionId] = 'Score must be between 0 and 100.';
            $message = 'Choose a score between 0 and 100.';
            $messageType = 'draft';
        } else {
            $ok = $engagementController->evaluateSubmission($submissionId, $score, $submittedStatus);
            if ($ok) {
                header('Location: admin-skillhub-reviews.php?challengeId=' . $challengeId . '&review=saved');
                exit;
            }

            $message = 'The evaluation could not be saved.';
            $messageType = 'draft';
        }
    }
}

if (($_GET['review'] ?? '') === 'saved') {
    $message = 'Submission saved. The review queue has been refreshed.';
    $messageType = 'published';
}

$challenge = $challengeId > 0 ? $coreController->getChallengeById($challengeId) : null;
$hub = $challenge !== null && !empty($challenge['groupId']) ? $coreController->getSkillHubById((int) $challenge['groupId']) : null;
$users = $pdo->query("SELECT userId, fullName, role FROM Users")->fetchAll();
$userMap = [];
foreach ($users as $user) {
    $userMap[(int) $user['userId']] = $user;
}

$submissions = $challengeId > 0 ? $engagementController->getSubmissionsByChallenge($challengeId) : [];
$managerName = $challenge !== null ? ($userMap[(int) ($challenge['managerId'] ?? 0)]['fullName'] ?? 'Unknown manager') : 'Unknown manager';
$reviewedCount = count(array_filter($submissions, static fn(array $submission): bool => $submission['score'] !== null));
$pendingSubmissions = array_values(array_filter($submissions, static fn(array $submission): bool => $submission['score'] === null));
$reviewedSubmissions = array_values(array_filter($submissions, static fn(array $submission): bool => $submission['score'] !== null));

$activePendingSubmission = null;
if (!empty($pendingSubmissions)) {
    foreach ($pendingSubmissions as $submission) {
        if ((int) $submission['submissionId'] === $reviewSubmissionId) {
            $activePendingSubmission = $submission;
            break;
        }
    }

    if ($activePendingSubmission === null) {
        $activePendingSubmission = $pendingSubmissions[0];
    }
}

$activeReviewedSubmission = null;
if ($editingSubmissionId > 0) {
    foreach ($reviewedSubmissions as $submission) {
        if ((int) $submission['submissionId'] === $editingSubmissionId) {
            $activeReviewedSubmission = $submission;
            break;
        }
    }
}

$activeSubmission = $activeReviewedSubmission ?? $activePendingSubmission;
$isEditingReviewed = $activeReviewedSubmission !== null;
$activeSubmissionStatus = $activeSubmission['status'] ?? ($isEditingReviewed ? 'reviewed' : 'submitted');
$activeFormSelectedStatus = (string) (
    (string) ($_POST['submissionId'] ?? '') === (string) ($activeSubmission['submissionId'] ?? '')
        ? ($_POST['status'] ?? $activeSubmissionStatus)
        : $activeSubmissionStatus
);
$activeFormScore = (string) (
    (string) ($_POST['submissionId'] ?? '') === (string) ($activeSubmission['submissionId'] ?? '')
        ? ($_POST['score'] ?? '')
        : ($activeSubmission['score'] ?? '')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand Admin | Submission Review</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="brand">
                <div class="brand-badge"></div>
                <div>
                    <h1>CareerStrand Admin</h1>
                    <p>Back office console</p>
                </div>
            </div>

            <div class="side-label">Main Menu</div>
            <nav class="nav-list">
                <a class="nav-item" href="admin-dashboard.php"><span>Dashboard</span><span>Home</span></a>
                <a class="nav-item active" href="admin-skills.php"><span>Skill Hub</span><span>Back</span></a>
            </nav>

            <div class="sidebar-card">
                <h3>Review workflow</h3>
                <p>Focus on one submission at a time: inspect the work, score it once, then let it leave the live queue and move into the reviewed archive.</p>
            </div>
        </aside>

        <main class="admin-main">
            <header class="page-header">
                <div>
                    <h2>Submission Evaluation Board</h2>
                    <p class="subtle"><?= $challenge !== null ? h($challenge['title'] ?? 'Challenge') : 'Choose a challenge from the Skill Hub board.'; ?></p>
                </div>
                <div class="header-actions">
                    <a class="btn btn-soft" href="admin-skills.php">Back to Skill Hub</a>
                </div>
            </header>

            <?php if ($message !== null) { ?>
                <section class="panel" style="margin-bottom: 20px;">
                    <div class="filters">
                        <span class="status-chip status-<?= $messageType === 'published' ? 'published' : 'draft'; ?>">
                            <?= h($message); ?>
                        </span>
                    </div>
                </section>
            <?php } ?>

            <?php if ($challenge === null) { ?>
                <section class="panel">
                    <div class="panel-title">
                        <h3>No challenge selected</h3>
                        <p>Open a task or project from the back-office board to evaluate its submissions.</p>
                    </div>
                </section>
            <?php } else { ?>
                <section class="tile-grid">
                    <article class="metric-tile">
                        <div class="metric-label">Hub</div>
                        <div class="metric-value"><?= h($hub['name'] ?? 'Skill Hub'); ?></div>
                        <div class="metric-sub"><?= h($hub['category'] ?? 'No category'); ?></div>
                    </article>
                    <article class="metric-tile">
                        <div class="metric-label">Challenge Type</div>
                        <div class="metric-value"><?= h(ucfirst((string) ($challenge['type'] ?? 'task'))); ?></div>
                        <div class="metric-sub">Managed by <?= h($managerName); ?></div>
                    </article>
                    <article class="metric-tile">
                        <div class="metric-label">Pending queue</div>
                        <div class="metric-value"><?= count($pendingSubmissions); ?></div>
                        <div class="metric-sub"><?= $reviewedCount; ?> already reviewed</div>
                    </article>
                    <article class="metric-tile">
                        <div class="metric-label">Deadline</div>
                        <div class="metric-value"><?= h((string) ($challenge['deadline'] ?? 'Not set')); ?></div>
                        <div class="metric-sub">Current status: <?= h((string) ($challenge['status'] ?? 'draft')); ?></div>
                    </article>
                </section>

                <section class="panel" style="margin-top: 24px;">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Challenge brief</h3>
                            <p><?= h((string) ($challenge['description'] ?? 'No description available.')); ?></p>
                        </div>
                    </div>
                </section>

                <section class="panel" style="margin-top: 24px;">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Ranking snapshot</h3>
                            <p>Current leaderboard based on saved scores for this challenge.</p>
                        </div>
                    </div>

                    <?php if (empty($reviewedSubmissions)) { ?>
                        <div class="list-item">
                            <strong>No ranking available</strong>
                            <p class="subtle">Scores and rank positions will appear here after submissions are reviewed.</p>
                        </div>
                    <?php } else { ?>
                        <div class="list">
                            <?php foreach (array_slice($reviewedSubmissions, 0, 5) as $submission) { ?>
                                <div class="list-item" style="padding: 18px 0;">
                                    <div class="panel-header" style="margin-bottom: 0;">
                                        <div class="panel-title">
                                            <h3>#<?= h((string) ($submission['submissionRank'] ?? '-')); ?> <?= h($submission['fullName'] ?? 'Unknown member'); ?></h3>
                                            <p><?= h((string) ($submission['status'] ?? 'reviewed')); ?> | Submitted on <?= h((string) ($submission['submittedAt'] ?? 'Unknown date')); ?></p>
                                        </div>
                                        <div class="filters">
                                            <span class="category-chip"><?= h((string) ($submission['score'] ?? 0)); ?>/100</span>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </section>

                <section class="review-workbench" style="margin-top: 24px;">
                    <article class="panel review-queue-panel">
                        <div class="panel-header">
                            <div class="panel-title">
                                <h3>Review queue</h3>
                                <p>Choose a pending submission to inspect and score.</p>
                            </div>
                        </div>

                        <?php if (empty($pendingSubmissions)) { ?>
                            <div class="list-item">
                                <strong>No pending submissions</strong>
                                <p class="subtle">Everything submitted for this challenge has already been reviewed.</p>
                            </div>
                        <?php } else { ?>
                            <div class="review-queue-list">
                                <?php foreach ($pendingSubmissions as $submission) { ?>
                                    <?php $isActive = (int) ($activePendingSubmission['submissionId'] ?? 0) === (int) $submission['submissionId'] && !$isEditingReviewed; ?>
                                    <a class="review-queue-card<?= $isActive ? ' is-active' : ''; ?>" href="admin-skillhub-reviews.php?challengeId=<?= (int) $challengeId; ?>&reviewSubmission=<?= (int) $submission['submissionId']; ?>">
                                        <div class="review-queue-top">
                                            <strong><?= h($submission['fullName'] ?? 'Unknown member'); ?></strong>
                                            <span class="status-chip status-draft"><?= h((string) ($submission['status'] ?? 'submitted')); ?></span>
                                        </div>
                                        <p><?= h($submission['role'] ?? 'user'); ?> | <?= h((string) ($submission['submittedAt'] ?? 'Unknown date')); ?></p>
                                        <span class="review-queue-link">Open review</span>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </article>

                    <article class="panel review-detail-panel">
                        <div class="panel-header">
                            <div class="panel-title">
                                <h3><?= $isEditingReviewed ? 'Edit saved evaluation' : 'Grading desk'; ?></h3>
                                <p>
                                    <?php if ($activeSubmission === null) { ?>
                                        Select a submission from the queue or archive to inspect it here.
                                    <?php } elseif ($isEditingReviewed) { ?>
                                        This submission is already reviewed. Update the score or status only when needed.
                                    <?php } else { ?>
                                        Review one submission at a time, save it, and it will leave the live queue.
                                    <?php } ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($activeSubmission === null) { ?>
                            <div class="list-item">
                                <strong>Nothing selected</strong>
                                <p class="subtle">Open a pending submission from the queue or an archived one from the reviewed section below.</p>
                            </div>
                        <?php } else { ?>
                            <div class="review-detail-grid">
                                <div class="review-detail-copy">
                                    <div class="filters" style="margin-bottom: 12px;">
                                        <span class="status-chip <?= $isEditingReviewed ? 'status-published' : 'status-draft'; ?>">
                                            <?= h((string) ($activeSubmission['status'] ?? ($isEditingReviewed ? 'reviewed' : 'submitted'))); ?>
                                        </span>
                                        <span class="category-chip"><?= $activeSubmission['score'] !== null ? h((string) $activeSubmission['score']) . '/100' : 'Pending'; ?></span>
                                        <span class="category-chip">Rank <?= h((string) ($activeSubmission['submissionRank'] ?? '-')); ?></span>
                                    </div>

                                    <h3 style="font-size: 30px; margin-bottom: 8px;"><?= h($activeSubmission['fullName'] ?? 'Unknown member'); ?></h3>
                                    <p class="subtle" style="margin-bottom: 18px;"><?= h($activeSubmission['role'] ?? 'user'); ?> | Submitted on <?= h((string) ($activeSubmission['submittedAt'] ?? 'Unknown date')); ?></p>

                                    <div class="field-grid" style="grid-template-columns: 1fr;">
                                        <div class="field">
                                            <label>Description</label>
                                            <textarea disabled><?= h((string) ($activeSubmission['description'] ?? 'No description provided.')); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="table-foot" style="justify-content: flex-start; padding: 0; margin-top: 14px;">
                                        <?php if (!empty($activeSubmission['projectLink'])) { ?>
                                            <a class="btn btn-main" href="<?= h((string) $activeSubmission['projectLink']); ?>" target="_blank" rel="noopener noreferrer">Open submitted work</a>
                                        <?php } else { ?>
                                            <span class="subtle">No link provided.</span>
                                        <?php } ?>
                                    </div>
                                </div>

                                <form method="POST" novalidate class="submission-evaluation-form review-score-panel">
                                    <input type="hidden" name="action" value="evaluate_submission">
                                    <input type="hidden" name="challengeId" value="<?= (int) $challengeId; ?>">
                                    <input type="hidden" name="submissionId" value="<?= (int) $activeSubmission['submissionId']; ?>">

                                    <div class="field-grid" style="grid-template-columns: 1fr;">
                                        <div class="field">
                                            <label>Score / 100</label>
                                            <input
                                                type="text"
                                                name="score"
                                                inputmode="numeric"
                                                class="submission-score-input"
                                                data-required-message="Score is required."
                                                value="<?= h($activeFormScore); ?>"
                                            >
                                            <small class="field-error submission-score-error"><?= h($scoreErrors[(int) $activeSubmission['submissionId']] ?? ''); ?></small>
                                        </div>
                                        <div class="field">
                                            <label>Status</label>
                                            <select name="status">
                                                <option value="reviewed" <?= strtolower($activeFormSelectedStatus) === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                <option value="accepted" <?= strtolower($activeFormSelectedStatus) === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                <option value="rejected" <?= strtolower($activeFormSelectedStatus) === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="submitted" <?= strtolower($activeFormSelectedStatus) === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Current ranking</label>
                                            <input type="text" value="<?= h((string) ($activeSubmission['submissionRank'] ?? '-')); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="table-foot">
                                        <div class="header-actions">
                                            <?php if ($isEditingReviewed) { ?>
                                                <a class="btn btn-soft" href="admin-skillhub-reviews.php?challengeId=<?= (int) $challengeId; ?>">Cancel edit</a>
                                                <button class="btn btn-main" type="submit">Update evaluation</button>
                                            <?php } else { ?>
                                                <button class="btn btn-main" type="submit">Save evaluation</button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php } ?>
                    </article>
                </section>

                <section class="panel" style="margin-top: 24px;">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Reviewed archive</h3>
                            <p>Reviewed work leaves the live queue and stays here for later edits.</p>
                        </div>
                    </div>

                    <?php if (empty($reviewedSubmissions)) { ?>
                        <div class="list-item">
                            <strong>No reviewed submissions yet</strong>
                            <p class="subtle">Once a submission gets a score, it will move here automatically.</p>
                        </div>
                    <?php } else { ?>
                        <div class="review-archive-list">
                            <?php foreach ($reviewedSubmissions as $submission) { ?>
                                <?php $isEditingThisSubmission = $editingSubmissionId === (int) $submission['submissionId']; ?>
                                <div class="review-archive-row<?= $isEditingThisSubmission ? ' is-active' : ''; ?>">
                                    <div>
                                        <strong><?= h($submission['fullName'] ?? 'Unknown member'); ?></strong>
                                        <p class="subtle"><?= h($submission['role'] ?? 'user'); ?> | <?= h((string) ($submission['submittedAt'] ?? 'Unknown date')); ?></p>
                                    </div>
                                    <div class="filters">
                                        <span class="status-chip status-published"><?= h((string) ($submission['status'] ?? 'reviewed')); ?></span>
                                        <span class="category-chip">Rank <?= h((string) ($submission['submissionRank'] ?? '-')); ?></span>
                                        <span class="category-chip"><?= h((string) ($submission['score'] ?? 0)); ?>/100</span>
                                        <a class="link-btn" href="admin-skillhub-reviews.php?challengeId=<?= (int) $challengeId; ?>&editSubmission=<?= (int) $submission['submissionId']; ?>">
                                            <?= $isEditingThisSubmission ? 'Editing' : 'Edit'; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </section>
            <?php } ?>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const setFieldError = (input, errorElement, message) => {
            if (errorElement) {
                errorElement.textContent = message;
            }

            input.classList.toggle('is-invalid', Boolean(message));
            input.dataset.invalid = message ? 'true' : 'false';
        };

        const validateScore = (input, errorElement) => {
            const value = input.value.trim();

            if (value === '') {
                setFieldError(input, errorElement, input.dataset.requiredMessage || 'Score is required.');
                return false;
            }

            if (!/^\d+$/.test(value)) {
                setFieldError(input, errorElement, 'Score must be a whole number.');
                return false;
            }

            const score = Number.parseInt(value, 10);
            if (score < 0 || score > 100) {
                setFieldError(input, errorElement, 'Score must be between 0 and 100.');
                return false;
            }

            setFieldError(input, errorElement, '');
            return true;
        };

        document.querySelectorAll('.submission-evaluation-form').forEach((form) => {
            const scoreInput = form.querySelector('.submission-score-input');
            const scoreError = form.querySelector('.submission-score-error');

            if (!scoreInput || !scoreError) {
                return;
            }

            scoreInput.addEventListener('input', () => validateScore(scoreInput, scoreError));
            scoreInput.addEventListener('blur', () => validateScore(scoreInput, scoreError));

            form.addEventListener('submit', (event) => {
                if (!validateScore(scoreInput, scoreError)) {
                    event.preventDefault();
                    scoreInput.focus();
                }
            });
        });
    });
    </script>
</body>
</html>
