<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../Controller/UserController.php';

session_start();

$admin = $_SESSION['user'] ?? null;
if (!$admin || strtolower((string) ($admin['role'] ?? '')) !== 'admin') {
    header('Location: ../FrontOffice/login.php');
    exit;
}

$profileController = new ProfileController();
$userController = new UserController();
$adminId = (int) ($admin['userId'] ?? 0);
$message = '';
$messageType = 'info';
$errors = [];

function skillLibraryH(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $requestId = (int) ($_POST['userSkillId'] ?? 0);
    $catalogId = (int) ($_POST['skillCatalogId'] ?? 0);

    if ($action === 'update_catalog' && $catalogId > 0) {
        $result = $profileController->updateSkillCatalog($catalogId, [
            'skillName' => trim((string) ($_POST['skillName'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'isActive' => !empty($_POST['isActive']) ? 1 : 0,
        ], $adminId);

        if (!empty($result['success'])) {
            $message = 'Skill updated.';
            $messageType = 'success';
        } else {
            $errors = $result['errors'] ?? [];
            $message = 'Could not update the skill.';
            $messageType = 'error';
        }
    }

    if ($action === 'add_catalog') {
        $result = $profileController->addSkillCatalog([
            'skillName' => trim((string) ($_POST['skillName'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ], $adminId);

        if (!empty($result['success'])) {
            $message = 'Skill added to the catalogue.';
            $messageType = 'success';
        } else {
            $errors = $result['errors'] ?? [];
            $message = 'Could not add the skill.';
            $messageType = 'error';
        }
    }

    if ($action === 'approve_request' && $requestId > 0) {
        $result = $profileController->approveSkillRequest($requestId, $adminId, [
            'skillCatalogId' => (int) ($_POST['skillCatalogId'] ?? 0),
            'catalogName' => trim((string) ($_POST['catalogName'] ?? '')),
            'reviewNote' => trim((string) ($_POST['reviewNote'] ?? '')),
            'skillLevel' => trim((string) ($_POST['skillLevel'] ?? '')),
        ]);

        if (!empty($result['success'])) {
            $message = 'Skill request approved.';
            $messageType = 'success';
        } else {
            $errors = $result['errors'] ?? [];
            $message = 'Could not approve the request.';
            $messageType = 'error';
        }
    }

    if ($action === 'approve_add_catalog' && $requestId > 0) {
        $result = $profileController->approveSkillRequest($requestId, $adminId, [
            'createCatalog' => true,
            'catalogName' => trim((string) ($_POST['catalogName'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'reviewNote' => trim((string) ($_POST['reviewNote'] ?? '')),
            'skillLevel' => trim((string) ($_POST['skillLevel'] ?? '')),
        ]);

        if (!empty($result['success'])) {
            $message = 'Skill approved and added to the library.';
            $messageType = 'success';
        } else {
            $errors = $result['errors'] ?? [];
            $message = 'Could not approve and add the skill.';
            $messageType = 'error';
        }
    }

    if ($action === 'reject_request' && $requestId > 0) {
        $result = $profileController->rejectSkillRequest($requestId, $adminId, trim((string) ($_POST['reviewNote'] ?? '')));

        if (!empty($result['success'])) {
            $message = 'Skill request rejected.';
            $messageType = 'success';
        } else {
            $errors = $result['errors'] ?? [];
            $message = 'Could not reject the request.';
            $messageType = 'error';
        }
    }
}

$catalogSearch = trim((string) ($_GET['catalogSearch'] ?? ''));
$requestSearch = trim((string) ($_GET['requestSearch'] ?? ''));
$editingSkillId = (int) ($_GET['editSkillId'] ?? 0);
$editingSkill = $editingSkillId > 0 ? $profileController->getSkillCatalogById($editingSkillId) : null;
$skillCatalog = $profileController->getSkillCatalog(['search' => $catalogSearch]);
$pendingRequests = $profileController->getPendingSkillRequests(200);
$approvedCount = 0;
$pendingCount = count($pendingRequests);
try {
    $approvedCount = (int) config::getConnexion()->query(
        "SELECT COUNT(*) FROM UserSkill WHERE LOWER(COALESCE(approvalStatus, 'approved')) = 'approved'"
    )->fetchColumn();
} catch (Throwable $exception) {
    $approvedCount = 0;
}

$usageCounts = [];
try {
    $usageStmt = config::getConnexion()->query(
        "SELECT skillCatalogId, COUNT(*) AS usageCount
         FROM UserSkill
         WHERE LOWER(COALESCE(approvalStatus, 'approved')) = 'approved'
           AND skillCatalogId IS NOT NULL
         GROUP BY skillCatalogId"
    );
    foreach ($usageStmt->fetchAll() as $usageRow) {
        $usageCounts[(int) ($usageRow['skillCatalogId'] ?? 0)] = (int) ($usageRow['usageCount'] ?? 0);
    }
} catch (Throwable $exception) {
    $usageCounts = [];
}

$pendingRequests = array_values(array_filter($pendingRequests, static function (array $request) use ($requestSearch): bool {
    if ($requestSearch === '') {
        return true;
    }
    $blob = strtolower(trim((string) ($request['skillName'] ?? '') . ' ' . ($request['fullName'] ?? '') . ' ' . ($request['email'] ?? '') . ' ' . ($request['source'] ?? '')));
    return str_contains($blob, strtolower($requestSearch));
}));
$pendingCount = count($pendingRequests);
$catalogCount = count($skillCatalog);
$adminSidebarBadges = ['skilllibrary' => $pendingCount];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand Admin Â· Skill Library</title>
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <style>
        .library-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
            gap: 24px;
        }
        .skill-card, .request-card, .add-card {
            background: var(--panel);
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            padding: 18px;
        }
        .catalog-list, .request-list {
            display: grid;
            gap: 14px;
        }
        .meta-line {
            color: var(--muted);
            font-size: 13px;
            margin-top: 6px;
        }
        .skill-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.04);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }
        .request-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .request-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: start;
            margin-bottom: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(126, 159, 228, 0.12);
        }
        .request-title-block {
            display: grid;
            gap: 10px;
            min-width: 0;
        }
        .request-title-block .skill-pill {
            width: fit-content;
        }
        .request-requester {
            display: grid;
            gap: 4px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }
        .request-requester strong {
            color: var(--text);
            font-size: 15px;
        }
        .request-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 14px 0 16px;
        }
        .request-meta-item {
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(126, 159, 228, 0.10);
            min-width: 0;
        }
        .request-meta-item span {
            display: block;
            color: var(--muted-2);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            margin-bottom: 6px;
        }
        .request-meta-item strong {
            display: block;
            color: var(--text);
            font-size: 13px;
            line-height: 1.55;
            word-break: break-word;
        }
        .request-meta-item.full {
            grid-column: 1 / -1;
        }
        .request-note {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }
        .request-status {
            display: inline-flex;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .request-status.pending { background: rgba(255, 205, 92, 0.12); color: #ffd66f; }
        .request-status.rejected { background: rgba(255, 110, 69, 0.12); color: #ffb197; }
        .request-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .request-actions .btn,
        .skill-card .btn {
            min-height: 38px;
            padding: 0 14px;
            font-size: 12px;
        }
        .field-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .field {
            display: grid;
            gap: 6px;
        }
        .field label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
        }
        .field input,
        .field textarea,
        .field select {
            width: 100%;
            border: 1px solid var(--border-soft);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            border-radius: 14px;
            min-height: 46px;
            padding: 12px 14px;
            font: inherit;
        }
        .field textarea {
            min-height: 96px;
            resize: vertical;
        }
        .field input::placeholder,
        .field textarea::placeholder {
            color: rgba(245, 243, 238, 0.42);
        }
        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 18px;
        }
        .alert.success { background: rgba(74, 222, 128, 0.12); color: #bbf7d0; border: 1px solid rgba(74, 222, 128, 0.2); }
        .alert.error { background: rgba(255, 110, 69, 0.12); color: #ffd0c3; border: 1px solid rgba(255, 110, 69, 0.2); }
        @media (max-width: 1100px) {
            .library-grid, .field-row { grid-template-columns: 1fr; }
            .request-summary,
            .request-meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="admin-main">
        <header class="page-header">
            <div>
                <h2>Skill Library</h2>
                <p>Manage saved skill names, review pending user requests, and approve new skills into the shared catalogue.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <?php echo skillLibraryH($message); ?>
            </div>
        <?php endif; ?>

        <section class="tile-grid">
            <article class="metric-tile">
                <div class="metric-label">Skill library</div>
                <div class="metric-value"><?php echo $catalogCount; ?></div>
                <div class="metric-sub">Saved skill names loaded from the database</div>
            </article>
            <article class="metric-tile">
                <div class="metric-label">Pending requests</div>
                <div class="metric-value"><?php echo $pendingCount; ?></div>
                <div class="metric-sub">Waiting for admin approval</div>
            </article>
            <article class="metric-tile">
                <div class="metric-label">Admin account</div>
                <div class="metric-value"><?php echo skillLibraryH((string) ($admin['fullName'] ?? 'Admin')); ?></div>
                <div class="metric-sub">Current reviewer</div>
            </article>
            <article class="metric-tile">
                <div class="metric-label">Approved skills</div>
                <div class="metric-value"><?php echo $approvedCount; ?></div>
                <div class="metric-sub">Approved user skill records</div>
            </article>
        </section>

        <section class="library-grid" style="margin-top: 24px;">
            <article class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <h3>Skill catalogue</h3>
                        <p>These names are used across profile requests, recommendations, and skill matching.</p>
                    </div>
                </div>

                <div class="panel panel-soft" style="margin-bottom: 16px;">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h4>Search library</h4>
                            <p>Find an existing skill quickly, then edit it or use it in moderation.</p>
                        </div>
                    </div>
                    <form method="get">
                        <div class="field-row">
                            <div class="field">
                                <label>Search skills</label>
                                <input type="text" name="catalogSearch" value="<?php echo skillLibraryH($catalogSearch); ?>" placeholder="Search by skill or category">
                            </div>
                            <div class="field">
                                <label>Search requests</label>
                                <input type="text" name="requestSearch" value="<?php echo skillLibraryH($requestSearch); ?>" placeholder="Search by user or skill">
                            </div>
                        </div>
                        <div class="request-actions" style="margin-top:12px;">
                            <button class="btn btn-main" type="submit">Apply search</button>
                            <a class="btn btn-soft" href="admin-skill-library.php">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="add-card" style="margin-bottom: 16px;">
                    <h4 style="margin:0 0 10px;"><?php echo $editingSkill ? 'Edit library skill' : 'Add a library skill'; ?></h4>
                    <form method="post">
                        <input type="hidden" name="action" value="<?php echo $editingSkill ? 'update_catalog' : 'add_catalog'; ?>">
                        <input type="hidden" name="skillCatalogId" value="<?php echo (int) ($editingSkill['skillCatalogId'] ?? 0); ?>">
                        <div class="field-row">
                            <div class="field">
                                <label>Skill name</label>
                                <input type="text" name="skillName" placeholder="e.g. HTML, Figma, Data Analysis" required value="<?php echo skillLibraryH((string) ($editingSkill['skillName'] ?? '')); ?>">
                            </div>
                            <div class="field">
                                <label>Category</label>
                                <input type="text" name="category" placeholder="Frontend, Design, Data, Business" value="<?php echo skillLibraryH((string) ($editingSkill['category'] ?? '')); ?>">
                            </div>
                            <div class="field" style="grid-column:1/-1;">
                                <label>Description</label>
                                <textarea name="description" placeholder="Optional description for the catalogue entry"><?php echo skillLibraryH((string) ($editingSkill['description'] ?? '')); ?></textarea>
                            </div>
                            <div class="field">
                                <label>Active</label>
                                <select name="isActive">
                                    <option value="1" <?php echo !isset($editingSkill['isActive']) || (int) ($editingSkill['isActive'] ?? 1) === 1 ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo isset($editingSkill['isActive']) && (int) $editingSkill['isActive'] === 0 ? 'selected' : ''; ?>>Hidden</option>
                                </select>
                            </div>
                        </div>
                        <div class="request-actions">
                            <button class="btn btn-main" type="submit"><?php echo $editingSkill ? 'Update skill' : 'Save skill'; ?></button>
                            <?php if ($editingSkill): ?>
                                <a class="btn btn-soft" href="admin-skill-library.php">Cancel edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="catalog-list">
                    <?php if (empty($skillCatalog)): ?>
                        <div class="skill-card">No skill catalogue entries yet.</div>
                    <?php else: ?>
                        <?php foreach ($skillCatalog as $skill): ?>
                            <?php
                                $skillId = (int) ($skill['skillCatalogId'] ?? 0);
                                $usage = $usageCounts[$skillId] ?? 0;
                            ?>
                            <div class="skill-card">
                                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                                    <div>
                                        <div class="skill-pill"><?php echo skillLibraryH($skill['skillName'] ?? 'Skill'); ?></div>
                                        <div class="meta-line">
                                            <?php echo skillLibraryH($skill['category'] ?? 'Uncategorised'); ?>
                                            Â· Used in <?php echo $usage; ?> approved skill<?php echo $usage === 1 ? '' : 's'; ?>
                                        </div>
                                    </div>
                                    <div class="request-actions" style="margin:0;">
                                        <span class="request-status pending" style="background:rgba(74,222,128,0.1);color:#86efac;"><?php echo !empty($skill['isActive']) ? 'Active' : 'Hidden'; ?></span>
                                        <a class="btn btn-soft" style="min-height:30px;padding:0 12px;font-size:11px;" href="?editSkillId=<?php echo $skillId; ?>">Edit</a>
                                    </div>
                                </div>
                                <?php if (!empty($skill['description'])): ?>
                                    <div class="meta-line"><?php echo skillLibraryH($skill['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <h3>Pending skill requests</h3>
                        <p>Approve a request, link it to an existing catalogue skill, or create a new skill while approving it.</p>
                    </div>
                </div>

                <div class="request-list">
                    <?php if (empty($pendingRequests)): ?>
                        <div class="skill-card">No pending requests right now.</div>
                    <?php else: ?>
                        <?php foreach ($pendingRequests as $request): ?>
                            <?php
                                $requestId = (int) ($request['userSkillId'] ?? 0);
                                $requestName = (string) ($request['skillName'] ?? '');
                                $requestStatus = strtolower((string) ($request['approvalStatus'] ?? 'pending'));
                                $matchedCatalog = $profileController->resolveSkillCatalog($requestName);
                                $requestUserName = (string) ($request['fullName'] ?? 'Unknown user');
                                $requestEmail = (string) ($request['email'] ?? '');
                                $assessmentData = null;
                                if (!empty($request['assessmentData'])) {
                                    $decoded = json_decode((string) $request['assessmentData'], true);
                                    if (is_array($decoded) && !empty($decoded['questions'])) {
                                        $assessmentData = $decoded;
                                    }
                                }
                            ?>
                            <div class="request-card">
                                <div class="request-summary">
                                    <div class="request-title-block">
                                        <div class="skill-pill"><?php echo skillLibraryH($requestName); ?></div>
                                        <div class="request-requester">
                                            <strong><?php echo skillLibraryH($requestUserName); ?></strong>
                                            <span><?php echo $requestEmail !== '' ? skillLibraryH($requestEmail) : 'No email captured'; ?></span>
                                        </div>
                                    </div>
                                    <span class="request-status <?php echo $requestStatus === 'rejected' ? 'rejected' : 'pending'; ?>">
                                        <?php echo skillLibraryH(ucfirst($requestStatus)); ?>
                                    </span>
                                </div>

                                <div class="request-meta-grid">
                                    <div class="request-meta-item">
                                        <span>Source</span>
                                        <strong><?php echo skillLibraryH((string) ($request['source'] ?? '')); ?></strong>
                                    </div>
                                    <div class="request-meta-item">
                                        <span>Validated</span>
                                        <strong><?php echo !empty($request['validatedAt']) ? skillLibraryH(date('d M Y', strtotime((string) $request['validatedAt']))) : 'Not provided'; ?></strong>
                                    </div>
                                    <div class="request-meta-item full">
                                        <span>Certificate</span>
                                        <strong><?php echo !empty($request['certificateUrl']) ? skillLibraryH((string) $request['certificateUrl']) : 'No certificate URL provided.'; ?></strong>
                                    </div>
                                    <div class="request-meta-item full">
                                        <span>Matched catalogue skill</span>
                                        <strong><?php echo $matchedCatalog ? skillLibraryH((string) ($matchedCatalog['skillName'] ?? '')) : 'No match linked yet.'; ?></strong>
                                    </div>
                                </div>

                                <?php if (!empty($request['reviewNote'])): ?>
                                    <p class="request-note"><strong>Admin note:</strong> <?php echo skillLibraryH((string) $request['reviewNote']); ?></p>
                                <?php endif; ?>

                                <?php if ($assessmentData !== null): ?>
                                    <div style="margin:0 0 18px; padding:16px; border-radius:16px; background:rgba(111,143,216,0.06); border:1px solid rgba(111,143,216,0.18);">
                                        <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.14em; color:var(--muted); margin-bottom:14px;">
                                            User Answers &mdash; <?php echo count($assessmentData['questions']); ?> question<?php echo count($assessmentData['questions']) !== 1 ? 's' : ''; ?>
                                        </div>
                                        <?php foreach ($assessmentData['questions'] as $qi => $qaQuestion): ?>
                                            <?php $qaAnswer = (string) ($assessmentData['answers'][$qi] ?? ''); ?>
                                            <div style="margin-bottom:<?php echo $qi < count($assessmentData['questions']) - 1 ? '16px' : '0'; ?>; <?php echo $qi < count($assessmentData['questions']) - 1 ? 'padding-bottom:16px; border-bottom:1px solid rgba(126,159,228,0.10);' : ''; ?>">
                                                <div style="font-size:11px; font-weight:700; color:rgba(111,143,216,0.9); margin-bottom:5px;">Q<?php echo $qi + 1; ?></div>
                                                <div style="font-size:13px; color:var(--text); margin-bottom:8px; line-height:1.55;"><?php echo skillLibraryH($qaQuestion); ?></div>
                                                <?php if ($qaAnswer !== ''): ?>
                                                    <div style="font-size:13px; color:rgba(245,243,238,0.82); background:rgba(255,255,255,0.04); border:1px solid rgba(126,159,228,0.12); border-radius:10px; padding:10px 14px; line-height:1.65; white-space:pre-line;"><?php echo skillLibraryH($qaAnswer); ?></div>
                                                <?php else: ?>
                                                    <div style="font-size:12px; color:var(--muted-2); font-style:italic;">No answer provided.</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post">
                                    <input type="hidden" name="userSkillId" value="<?php echo $requestId; ?>">
                                    <div class="field-row">
                                        <div class="field">
                                            <label>Existing library match</label>
                                            <select name="skillCatalogId">
                                                <option value="">Use request name</option>
                                                <?php foreach ($skillCatalog as $skill): ?>
                                                    <?php $catalogId = (int) ($skill['skillCatalogId'] ?? 0); ?>
                                                    <option value="<?php echo $catalogId; ?>" <?php echo $matchedCatalog && $catalogId === (int) ($matchedCatalog['skillCatalogId'] ?? 0) ? 'selected' : ''; ?>>
                                                        <?php echo skillLibraryH((string) ($skill['skillName'] ?? '')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>New catalogue name</label>
                                            <input type="text" name="catalogName" placeholder="Only needed when adding a brand new library skill">
                                        </div>
                                        <div class="field">
                                            <label>Category</label>
                                            <input type="text" name="category" placeholder="Optional catalogue category">
                                        </div>
                                        <div class="field">
                                            <label>Review note</label>
                                            <input type="text" name="reviewNote" placeholder="Optional note for the user">
                                        </div>
                                        <div class="field">
                                            <label>Skill Score (0–100)</label>
                                            <input type="number" name="skillLevel" min="0" max="100" step="1" placeholder="Optional — sets the user's skill level">
                                        </div>
                                        <div class="field" style="grid-column:1/-1;">
                                            <label>Description</label>
                                            <textarea name="description" placeholder="Optional description for a new library skill"></textarea>
                                        </div>
                                    </div>

                                    <div class="request-actions">
                                        <button class="btn btn-main" type="submit" name="action" value="approve_request">Approve</button>
                                        <button class="btn btn-main" type="submit" name="action" value="approve_add_catalog">Approve &amp; add to library</button>
                                        <button class="btn btn-soft" type="submit" name="action" value="reject_request">Reject</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>


