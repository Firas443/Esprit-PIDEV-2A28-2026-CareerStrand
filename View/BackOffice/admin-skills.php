<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/SkillHubCoreController.php';
require_once __DIR__ . '/../../Controller/SkillHubEngagementController.php';
require_once __DIR__ . '/../../Model/SkillHubCore.php';

$coreController = new SkillHubCoreController();
$engagementController = new SkillHubEngagementController();
$pdo = config::getConnexion();
$message = null;
$messageType = 'soft';
$hubNameError = '';
$hubCategoryError = '';
$hubStatusError = '';
$hubDescriptionError = '';
$workTitleError = '';
$workGroupError = '';
$workManagerError = '';
$workTypeError = '';
$workDifficultyError = '';
$workStatusError = '';
$deadlineError = '';
$workDescriptionError = '';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedHubName = trim((string) ($_POST['name'] ?? ''));
    $submittedHubCategory = trim((string) ($_POST['category'] ?? ''));
    $submittedHubStatus = trim((string) ($_POST['status'] ?? ''));
    $submittedHubDescription = trim((string) ($_POST['description'] ?? ''));
    $submittedWorkTitle = trim((string) ($_POST['title'] ?? ''));
    $submittedWorkGroupId = (int) ($_POST['groupId'] ?? 0);
    $submittedManagerId = (int) ($_POST['managerId'] ?? 0);
    $submittedWorkType = trim((string) ($_POST['type'] ?? ''));
    $submittedWorkDifficulty = trim((string) ($_POST['difficulty'] ?? ''));
    $submittedWorkStatus = trim((string) ($_POST['status'] ?? ''));
    $submittedDeadline = trim((string) ($_POST['deadline'] ?? ''));
    $submittedWorkDescription = trim((string) ($_POST['description'] ?? ''));

    switch ($action) {
        case 'create_hub':
            if ($submittedHubName === '') {
                $hubNameError = 'Hub name is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($submittedHubCategory === '') {
                $hubCategoryError = 'Category is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($submittedHubStatus === '') {
                $hubStatusError = 'Status is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($submittedHubDescription === '') {
                $hubDescriptionError = 'Description is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($coreController->skillHubNameExists($submittedHubName)) {
                $hubNameError = 'This hub name already exists.';
                $message = 'Please choose a different hub name.';
                $messageType = 'draft';
            } else {
                $ok = $coreController->createSkillHub(new SkillHubEntity(
                    null,
                    $submittedHubName,
                    $submittedHubCategory,
                    $submittedHubDescription,
                    date('Y-m-d'),
                    $submittedHubStatus
                ));
                $message = $ok ? 'Hub created successfully.' : 'Could not create the hub.';
                $messageType = $ok ? 'published' : 'draft';
            }
            break;

        case 'update_hub':
            $editingHubId = (int) ($_POST['groupId'] ?? 0);
            if ($submittedHubName === '') {
                $hubNameError = 'Hub name is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($submittedHubCategory === '') {
                $hubCategoryError = 'Category is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($submittedHubStatus === '') {
                $hubStatusError = 'Status is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($submittedHubDescription === '') {
                $hubDescriptionError = 'Description is required.';
                $message = 'Please complete the hub form.';
                $messageType = 'draft';
            } elseif ($coreController->skillHubNameExists($submittedHubName, $editingHubId)) {
                $hubNameError = 'This hub name already exists.';
                $message = 'Please choose a different hub name.';
                $messageType = 'draft';
            } else {
                $ok = $coreController->updateSkillHub($editingHubId, new SkillHubEntity(
                    null,
                    $submittedHubName,
                    $submittedHubCategory,
                    $submittedHubDescription,
                    null,
                    $submittedHubStatus
                ));
                $message = $ok ? 'Hub updated successfully.' : 'Could not update the hub.';
                $messageType = $ok ? 'published' : 'draft';
            }
            break;

        case 'delete_hub':
            $ok = $coreController->deleteSkillHub((int) ($_POST['groupId'] ?? 0));
            $message = $ok ? 'Hub deleted successfully.' : 'Could not delete the hub.';
            $messageType = $ok ? 'published' : 'draft';
            break;

        case 'create_work':
            if ($submittedWorkTitle === '') {
                $workTitleError = 'Title is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkGroupId <= 0) {
                $workGroupError = 'Hub is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedManagerId <= 0) {
                $workManagerError = 'Manager is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkType === '') {
                $workTypeError = 'Type is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkDifficulty === '') {
                $workDifficultyError = 'Difficulty is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkStatus === '') {
                $workStatusError = 'Status is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkDescription === '') {
                $workDescriptionError = 'Description is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($coreController->challengeTitleExists($submittedWorkTitle, $submittedWorkGroupId)) {
                $workTitleError = 'This challenge title already exists in the selected hub.';
                $message = 'Please choose a different challenge title.';
                $messageType = 'draft';
            } elseif ($submittedDeadline !== '' && strtotime($submittedDeadline) < time()) {
                $deadlineError = 'Deadline cannot be in the past.';
                $message = 'Please choose a future deadline.';
                $messageType = 'draft';
            } else {
                $ok = $coreController->createChallenge(new ChallengeEntity(
                    null,
                    $submittedWorkGroupId,
                    $submittedManagerId,
                    $submittedWorkType,
                    $submittedWorkTitle,
                    $submittedWorkDescription,
                    $submittedWorkDifficulty,
                    $_POST['deadline'] ?? null,
                    $submittedWorkStatus,
                    date('Y-m-d H:i:s')
                ));
                $message = $ok ? 'Work item published successfully.' : 'Could not publish the work item.';
                $messageType = $ok ? 'published' : 'draft';
            }
            break;

        case 'update_work':
            $editingChallengeId = (int) ($_POST['challengeId'] ?? 0);
            if ($submittedWorkTitle === '') {
                $workTitleError = 'Title is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkGroupId <= 0) {
                $workGroupError = 'Hub is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedManagerId <= 0) {
                $workManagerError = 'Manager is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkType === '') {
                $workTypeError = 'Type is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkDifficulty === '') {
                $workDifficultyError = 'Difficulty is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkStatus === '') {
                $workStatusError = 'Status is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($submittedWorkDescription === '') {
                $workDescriptionError = 'Description is required.';
                $message = 'Please complete the work item form.';
                $messageType = 'draft';
            } elseif ($coreController->challengeTitleExists($submittedWorkTitle, $submittedWorkGroupId, $editingChallengeId)) {
                $workTitleError = 'This challenge title already exists in the selected hub.';
                $message = 'Please choose a different challenge title.';
                $messageType = 'draft';
            } elseif ($submittedDeadline !== '' && strtotime($submittedDeadline) < time()) {
                $deadlineError = 'Deadline cannot be in the past.';
                $message = 'Please choose a future deadline.';
                $messageType = 'draft';
            } else {
                $ok = $coreController->updateChallenge($editingChallengeId, new ChallengeEntity(
                    null,
                    $submittedWorkGroupId,
                    $submittedManagerId,
                    $submittedWorkType,
                    $submittedWorkTitle,
                    $submittedWorkDescription,
                    $submittedWorkDifficulty,
                    $_POST['deadline'] ?? null,
                    $submittedWorkStatus,
                    null
                ));
                $message = $ok ? 'Work item updated successfully.' : 'Could not update the work item.';
                $messageType = $ok ? 'published' : 'draft';
            }
            break;

        case 'delete_work':
            $ok = $coreController->deleteChallenge((int) ($_POST['challengeId'] ?? 0));
            $message = $ok ? 'Work item deleted successfully.' : 'Could not delete the work item.';
            $messageType = $ok ? 'published' : 'draft';
            break;
    }
}

$hubRows = $coreController->getAllSkillHubs();
$memberRows = $coreController->getAllGroupMembers();
$workRows = $coreController->getAllChallenges();
$postRows = $engagementController->getAllPosts();
$users = $pdo->query("SELECT userId, fullName, role FROM Users")->fetchAll();
$managers = array_values(array_filter($users, static fn(array $user): bool => in_array(strtolower((string) $user['role']), ['manager', 'admin'], true)));

$memberCounts = [];
foreach ($memberRows as $memberRow) {
    $memberCounts[(int) $memberRow['groupId']] = ($memberCounts[(int) $memberRow['groupId']] ?? 0) + 1;
}

$workCounts = [];
foreach ($workRows as $workRow) {
    $workCounts[(int) $workRow['groupId']] = ($workCounts[(int) $workRow['groupId']] ?? 0) + 1;
}

$threadCountsByHub = [];
$threadCountsByChallenge = [];
foreach ($postRows as $postRow) {
    $threadCountsByHub[(int) $postRow['groupId']] = ($threadCountsByHub[(int) $postRow['groupId']] ?? 0) + 1;
    if (!empty($postRow['challengeId'])) {
        $threadCountsByChallenge[(int) $postRow['challengeId']] = ($threadCountsByChallenge[(int) $postRow['challengeId']] ?? 0) + 1;
    }
}

$hubMap = [];
foreach ($hubRows as $hubRow) {
    $hubMap[(int) $hubRow['groupId']] = $hubRow;
}

$userMap = [];
foreach ($users as $user) {
    $userMap[(int) $user['userId']] = $user;
}

$hubs = array_map(static function (array $hub) use ($memberCounts, $workCounts, $threadCountsByHub): array {
    $groupId = (int) $hub['groupId'];
    $hub['memberCount'] = $memberCounts[$groupId] ?? 0;
    $hub['workCount'] = $workCounts[$groupId] ?? 0;
    $hub['threadCount'] = $threadCountsByHub[$groupId] ?? 0;
    return $hub;
}, $hubRows);

$workItems = array_map(static function (array $workItem) use ($hubMap, $userMap, $threadCountsByChallenge): array {
    $hub = $hubMap[(int) $workItem['groupId']] ?? ['name' => 'Skill Hub'];
    $manager = $userMap[(int) $workItem['managerId']] ?? ['fullName' => 'Unknown', 'role' => 'user'];
    $workItem['hubName'] = $hub['name'] ?? 'Skill Hub';
    $workItem['managerName'] = $manager['fullName'] ?? 'Unknown';
    $workItem['threadCount'] = $threadCountsByChallenge[(int) $workItem['challengeId']] ?? 0;
    return $workItem;
}, $workRows);

$hubSearch = trim((string) ($_GET['hubSearch'] ?? ''));
$hubCategoryFilter = trim((string) ($_GET['hubCategory'] ?? ''));
$hubStatusFilter = trim((string) ($_GET['hubStatus'] ?? ''));
$workSearchFilter = trim((string) ($_GET['workSearch'] ?? ''));
$workHubFilter = (int) ($_GET['workHub'] ?? 0);
$workTypeFilter = trim((string) ($_GET['workType'] ?? ''));
$workStatusFilter = trim((string) ($_GET['workStatus'] ?? ''));
$workDifficultyFilter = trim((string) ($_GET['workDifficulty'] ?? ''));

$hubCategories = array_values(array_unique(array_filter(array_map(
    static fn(array $hub): string => trim((string) ($hub['category'] ?? '')),
    $hubs
))));
sort($hubCategories);

$hubStatuses = array_values(array_unique(array_filter(array_map(
    static fn(array $hub): string => trim((string) ($hub['status'] ?? '')),
    $hubs
))));
sort($hubStatuses);

$workTypes = array_values(array_unique(array_filter(array_map(
    static fn(array $item): string => trim((string) ($item['type'] ?? '')),
    $workItems
))));
sort($workTypes);

$workStatuses = array_values(array_unique(array_filter(array_map(
    static fn(array $item): string => trim((string) ($item['status'] ?? '')),
    $workItems
))));
sort($workStatuses);

$workDifficulties = array_values(array_unique(array_filter(array_map(
    static fn(array $item): string => trim((string) ($item['difficulty'] ?? '')),
    $workItems
))));
sort($workDifficulties);

$filteredHubs = array_values(array_filter($hubs, static function (array $hub) use ($hubSearch, $hubCategoryFilter, $hubStatusFilter): bool {
    $matchesSearch = $hubSearch === ''
        || str_contains(
            strtolower(trim(($hub['name'] ?? '') . ' ' . ($hub['description'] ?? '') . ' ' . ($hub['category'] ?? ''))),
            strtolower($hubSearch)
        );
    $matchesCategory = $hubCategoryFilter === '' || strcasecmp((string) ($hub['category'] ?? ''), $hubCategoryFilter) === 0;
    $matchesStatus = $hubStatusFilter === '' || strcasecmp((string) ($hub['status'] ?? ''), $hubStatusFilter) === 0;

    return $matchesSearch && $matchesCategory && $matchesStatus;
}));

$filteredWorkItems = array_values(array_filter($workItems, static function (array $item) use ($workSearchFilter, $workHubFilter, $workTypeFilter, $workStatusFilter, $workDifficultyFilter): bool {
    $matchesSearch = $workSearchFilter === ''
        || str_contains(
            strtolower(trim(($item['title'] ?? '') . ' ' . ($item['description'] ?? '') . ' ' . ($item['hubName'] ?? '') . ' ' . ($item['managerName'] ?? ''))),
            strtolower($workSearchFilter)
        );
    $matchesHub = $workHubFilter <= 0 || (int) ($item['groupId'] ?? 0) === $workHubFilter;
    $matchesType = $workTypeFilter === '' || strcasecmp((string) ($item['type'] ?? ''), $workTypeFilter) === 0;
    $matchesStatus = $workStatusFilter === '' || strcasecmp((string) ($item['status'] ?? ''), $workStatusFilter) === 0;
    $matchesDifficulty = $workDifficultyFilter === '' || strcasecmp((string) ($item['difficulty'] ?? ''), $workDifficultyFilter) === 0;

    return $matchesSearch && $matchesHub && $matchesType && $matchesStatus && $matchesDifficulty;
}));

$stats = [
    'hubCount' => count($hubRows),
    'managerCount' => count($managers),
    'workCount' => count($workRows),
    'threadCount' => count($postRows),
];

$editingHub = null;
if (isset($_GET['editHub'])) {
    $editingHub = $coreController->getSkillHubById((int) $_GET['editHub']);
}

$editingWork = null;
if (isset($_GET['editWork'])) {
    $editingWork = $coreController->getChallengeById((int) $_GET['editWork']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerStrand Admin Skill Hub</title>
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
                <a class="nav-item" href="admin-users.php"><span>Users</span><span>1.2k</span></a>
                <a class="nav-item" href="admin-profiles.php"><span>Profiles</span><span>842</span></a>
                <a class="nav-item" href="admin-questions.php"><span>Courses</span><span>24</span></a>
                <a class="nav-item active" href="admin-skills.php"><span>Skill Hub</span><span><?= h((string) $stats['hubCount']); ?></span></a>
                <a class="nav-item" href="admin-opportunities.php"><span>Opportunities</span><span>36</span></a>
                <a class="nav-item" href="admin-applications.php"><span>Applications</span><span>128</span></a>
                <a class="nav-item" href="admin-analytics.php"><span>ADN Analytics</span><span>Live</span></a>
                <a class="nav-item" href="admin-feedback.php"><span>Events</span><span>12</span></a>
                <a class="nav-item" href="admin-settings.php"><span>Settings</span><span>New</span></a>
            </nav>

            <div class="sidebar-card">
                <h3>Access rule</h3>
                <p>Only admins and managers can create hubs, publish tasks, publish projects, and moderate hub discussions. Users can join hubs, discuss, save work, and build visible progression.</p>
            </div>
        </aside>

        <main class="admin-main">
            <header class="page-header">
                <div>
                    <h2>Skill Hub Control Center</h2>
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

            <section class="tile-grid">
                <article class="metric-tile">
                    <div class="metric-label">Live Hubs</div>
                    <div class="metric-value"><?= h((string) $stats['hubCount']); ?></div>
                    <div class="metric-sub">Communities available</div>
                </article>
                <article class="metric-tile">
                    <div class="metric-label">Active Managers</div>
                    <div class="metric-value"><?= h((string) $stats['managerCount']); ?></div>
                    <div class="metric-sub">Managers and admins available for publishing</div>
                </article>
                <article class="metric-tile">
                    <div class="metric-label">Open Work Items</div>
                    <div class="metric-value"><?= h((string) $stats['workCount']); ?></div>
                    <div class="metric-sub">Tasks and projects currently stored</div>
                </article>
                <article class="metric-tile">
                    <div class="metric-label">Open Threads</div>
                    <div class="metric-value"><?= h((string) $stats['threadCount']); ?></div>
                    <div class="metric-sub">Posts linked to hubs and work items</div>
                </article>
            </section>

            <section class="split-grid" style="margin-top: 24px;">
                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Hub directory</h3>
                        </div>
                    </div>
                    <form class="table-toolbar" method="GET">
                        <div class="toolbar-grid toolbar-grid-hubs">
                            <div class="toolbar-field">
                                <label for="hubSearch">Search hubs</label>
                                <input id="hubSearch" type="text" name="hubSearch" value="<?= h($hubSearch); ?>" placeholder="Search by name, category, or description">
                            </div>
                            <div class="toolbar-field">
                                <label for="hubCategory">Category</label>
                                <select id="hubCategory" name="hubCategory">
                                    <option value="">All categories</option>
                                    <?php foreach ($hubCategories as $category) { ?>
                                        <option value="<?= h($category); ?>" <?= strcasecmp($hubCategoryFilter, $category) === 0 ? 'selected' : ''; ?>><?= h($category); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="toolbar-field">
                                <label for="hubStatus">Status</label>
                                <select id="hubStatus" name="hubStatus">
                                    <option value="">All statuses</option>
                                    <?php foreach ($hubStatuses as $status) { ?>
                                        <option value="<?= h($status); ?>" <?= strcasecmp($hubStatusFilter, $status) === 0 ? 'selected' : ''; ?>><?= h(ucfirst($status)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="toolbar-actions">
                            <button class="btn btn-soft" type="submit">Apply filters</button>
                            <a class="btn btn-clear" href="admin-skills.php">Reset</a>
                        </div>
                    </form>
                    <table>
                        <thead>
                            <tr>
                                <th>Hub</th>
                                <th>Category</th>
                                <th>Members</th>
                                <th>Live work</th>
                                <th>Threads</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filteredHubs)) { ?>
                                <tr>
                                    <td colspan="7" class="empty-cell">No hubs match the current filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($filteredHubs as $hub) { ?>
                                    <tr>
                                        <td><strong><?= h($hub['name']); ?></strong></td>
                                        <td><?= h($hub['category']); ?></td>
                                        <td><?= h((string) $hub['memberCount']); ?></td>
                                        <td><?= h((string) $hub['workCount']); ?></td>
                                        <td><?= h((string) $hub['threadCount']); ?></td>
                                        <td>
                                            <span class="status-chip <?= strtolower((string) $hub['status']) === 'active' ? 'status-published' : 'status-draft'; ?>">
                                                <?= h($hub['status']); ?>
                                            </span>
                                        </td>
                                        <td class="table-actions">
                                            <a class="link-btn" href="?editHub=<?= (int) $hub['groupId']; ?>">Edit</a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete_hub">
                                                <input type="hidden" name="groupId" value="<?= (int) $hub['groupId']; ?>">
                                                <button class="link-btn" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3><?= $editingHub ? 'Edit hub' : 'Create hub'; ?></h3>
                            <p><?= $editingHub ? 'Update the selected community.' : ''; ?></p>
                        </div>
                    </div>
                    <form method="POST" id="hubForm" novalidate>
                        <input type="hidden" name="action" value="<?= $editingHub ? 'update_hub' : 'create_hub'; ?>">
                        <?php if ($editingHub) { ?>
                            <input type="hidden" name="groupId" value="<?= (int) $editingHub['groupId']; ?>">
                        <?php } ?>
                        <div class="field-grid">
                            <div class="field">
                                <label>Hub title</label>
                                <input
                                    type="text"
                                    id="hubNameInput"
                                    name="name"
                                    value="<?= h($_POST['action'] ?? '' ? ($_POST['name'] ?? '') : ($editingHub['name'] ?? '')); ?>"
                                    data-validation="hub-name"
                                    data-exclude-id="<?= (int) ($editingHub['groupId'] ?? 0); ?>"
                                    data-required-message="Hub name is required."
                                >
                                <small class="field-error" id="hubNameError"><?= h($hubNameError); ?></small>
                            </div>
                            <div class="field">
                                <label>Category</label>
                                <select id="hubCategorySelect" name="category" data-required-message="Category is required.">
                                    <?php
                                    $categories = ['Frontend', 'Design', 'Communication', 'Business'];
                                    $selectedCategory = $_POST['action'] ?? '' ? ($_POST['category'] ?? '') : ($editingHub['category'] ?? '');
                                    foreach ($categories as $category) {
                                        $selected = $selectedCategory === $category ? 'selected' : '';
                                        echo "<option value=\"" . h($category) . "\" $selected>" . h($category) . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="hubCategoryError"><?= h($hubCategoryError); ?></small>
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <select id="hubStatusSelect" name="status" data-required-message="Status is required.">
                                    <?php
                                    $statuses = ['active', 'draft', 'archived'];
                                    $selectedStatus = $_POST['action'] ?? '' ? ($_POST['status'] ?? 'active') : ($editingHub['status'] ?? 'active');
                                    foreach ($statuses as $status) {
                                        $selected = strtolower((string) $selectedStatus) === $status ? 'selected' : '';
                                        echo "<option value=\"" . h($status) . "\" $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="hubStatusError"><?= h($hubStatusError); ?></small>
                            </div>
                            <div class="field">
                                <label>Created date</label>
                                <input type="text" value="<?= h((string) ($editingHub['createdAt'] ?? date('Y-m-d H:i:s'))); ?>" disabled>
                            </div>
                            <div class="field">
                                <label>Description</label>
                                <textarea id="hubDescriptionInput" name="description" data-required-message="Description is required."><?= h($_POST['action'] ?? '' ? ($_POST['description'] ?? '') : ($editingHub['description'] ?? '')); ?></textarea>
                                <small class="field-error" id="hubDescriptionError"><?= h($hubDescriptionError); ?></small>
                            </div>
                        </div>
                        <div class="table-foot">
                            <div class="header-actions">
                                <?php if ($editingHub) { ?>
                                    <a class="btn btn-soft" href="admin-skills.php">Cancel</a>
                                <?php } ?>
                                <button class="btn btn-main" type="submit"><?= $editingHub ? 'Update hub' : 'Create hub'; ?></button>
                            </div>
                        </div>
                    </form>
                </article>
            </section>

            <section class="split-grid" style="margin-top: 24px;">
                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Tasks and projects board</h3>
                        </div>
                    </div>
                    <form class="table-toolbar" method="GET">
                        <div class="toolbar-grid toolbar-grid-work">
                            <div class="toolbar-field">
                                <label for="workSearch">Search work</label>
                                <input id="workSearch" type="text" name="workSearch" value="<?= h($workSearchFilter); ?>" placeholder="Search by title, manager, hub, or description">
                            </div>
                            <div class="toolbar-field">
                                <label for="workHub">Hub</label>
                                <select id="workHub" name="workHub">
                                    <option value="0">All hubs</option>
                                    <?php foreach ($hubs as $hub) { ?>
                                        <option value="<?= (int) $hub['groupId']; ?>" <?= $workHubFilter === (int) $hub['groupId'] ? 'selected' : ''; ?>><?= h($hub['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="toolbar-field">
                                <label for="workType">Type</label>
                                <select id="workType" name="workType">
                                    <option value="">All types</option>
                                    <?php foreach ($workTypes as $type) { ?>
                                        <option value="<?= h($type); ?>" <?= strcasecmp($workTypeFilter, $type) === 0 ? 'selected' : ''; ?>><?= h(ucfirst($type)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="toolbar-field">
                                <label for="workDifficulty">Difficulty</label>
                                <select id="workDifficulty" name="workDifficulty">
                                    <option value="">All levels</option>
                                    <?php foreach ($workDifficulties as $difficulty) { ?>
                                        <option value="<?= h($difficulty); ?>" <?= strcasecmp($workDifficultyFilter, $difficulty) === 0 ? 'selected' : ''; ?>><?= h($difficulty); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="toolbar-field">
                                <label for="workStatus">Status</label>
                                <select id="workStatus" name="workStatus">
                                    <option value="">All statuses</option>
                                    <?php foreach ($workStatuses as $status) { ?>
                                        <option value="<?= h($status); ?>" <?= strcasecmp($workStatusFilter, $status) === 0 ? 'selected' : ''; ?>><?= h(ucfirst($status)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="toolbar-actions">
                            <button class="btn btn-soft" type="submit">Apply filters</button>
                            <a class="btn btn-clear" href="admin-skills.php">Reset</a>
                        </div>
                    </form>
                    <table>
                        <thead>
                            <tr>
                                <th>Work item</th>
                                <th>Hub</th>
                                <th>Manager</th>
                                <th>Type</th>
                                <th>Threads</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filteredWorkItems)) { ?>
                                <tr>
                                    <td colspan="7" class="empty-cell">No tasks or projects match the current filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($filteredWorkItems as $item) { ?>
                                    <tr>
                                        <td><strong><?= h($item['title']); ?></strong></td>
                                        <td><?= h($item['hubName']); ?></td>
                                        <td><?= h($item['managerName'] ?? 'Unknown'); ?></td>
                                        <td><span class="category-chip"><?= h(ucfirst((string) $item['type'])); ?></span></td>
                                        <td><?= h((string) $item['threadCount']); ?></td>
                                        <td>
                                            <span class="status-chip <?= strtolower((string) $item['status']) === 'published' || strtolower((string) $item['status']) === 'open' ? 'status-published' : 'status-draft'; ?>">
                                                <?= h($item['status']); ?>
                                            </span>
                                        </td>
                                        <td class="table-actions">
                                            <a class="link-btn" href="?editWork=<?= (int) $item['challengeId']; ?>">Edit</a>
                                            <a class="link-btn" href="admin-skillhub-reviews.php?challengeId=<?= (int) $item['challengeId']; ?>">Review</a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete_work">
                                                <input type="hidden" name="challengeId" value="<?= (int) $item['challengeId']; ?>">
                                                <button class="link-btn" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3><?= $editingWork ? 'Edit task or project' : 'Publish task or project'; ?></h3>
                            <p><?= $editingWork ? 'Update the selected work item in Challenge.' : ''; ?></p>
                        </div>
                    </div>
                    <form method="POST" id="workForm" novalidate>
                        <input type="hidden" name="action" value="<?= $editingWork ? 'update_work' : 'create_work'; ?>">
                        <?php if ($editingWork) { ?>
                            <input type="hidden" name="challengeId" value="<?= (int) $editingWork['challengeId']; ?>">
                        <?php } ?>

                        <div class="field-grid">
                            <div class="field">
                                <label>Title</label>
                                <input
                                    type="text"
                                    id="challengeTitleInput"
                                    name="title"
                                    value="<?= h($_POST['action'] ?? '' ? ($_POST['title'] ?? '') : ($editingWork['title'] ?? '')); ?>"
                                    data-validation="challenge-title"
                                    data-exclude-id="<?= (int) ($editingWork['challengeId'] ?? 0); ?>"
                                    data-required-message="Title is required."
                                >
                                <small class="field-error" id="challengeTitleError"><?= h($workTitleError); ?></small>
                            </div>
                            <div class="field">
                                <label>Hub</label>
                                <select id="challengeGroupSelect" name="groupId" data-required-message="Hub is required.">
                                    <?php
                                    $selectedGroupId = (int) ($_POST['action'] ?? '' ? ($_POST['groupId'] ?? 0) : ($editingWork['groupId'] ?? 0));
                                    foreach ($hubs as $hub) {
                                        $selected = $selectedGroupId === (int) $hub['groupId'] ? 'selected' : '';
                                        echo "<option value=\"" . (int) $hub['groupId'] . "\" $selected>" . h($hub['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="challengeGroupError"><?= h($workGroupError); ?></small>
                            </div>
                            <div class="field">
                                <label>Manager</label>
                                <select id="challengeManagerSelect" name="managerId" data-required-message="Manager is required.">
                                    <?php
                                    $selectedManagerId = (int) ($_POST['action'] ?? '' ? ($_POST['managerId'] ?? 0) : ($editingWork['managerId'] ?? 0));
                                    foreach ($managers as $manager) {
                                        $selected = $selectedManagerId === (int) $manager['userId'] ? 'selected' : '';
                                        echo "<option value=\"" . (int) $manager['userId'] . "\" $selected>" . h($manager['fullName']) . " (" . h($manager['role']) . ")</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="challengeManagerError"><?= h($workManagerError); ?></small>
                            </div>
                            <div class="field">
                                <label>Type</label>
                                <select id="challengeTypeSelect" name="type" data-required-message="Type is required.">
                                    <?php
                                    $types = ['task', 'project'];
                                    $selectedType = strtolower((string) ($_POST['action'] ?? '' ? ($_POST['type'] ?? 'task') : ($editingWork['type'] ?? 'task')));
                                    foreach ($types as $type) {
                                        $selected = $selectedType === $type ? 'selected' : '';
                                        echo "<option value=\"" . h($type) . "\" $selected>" . ucfirst($type) . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="challengeTypeError"><?= h($workTypeError); ?></small>
                            </div>
                            <div class="field">
                                <label>Difficulty</label>
                                <select id="challengeDifficultySelect" name="difficulty" data-required-message="Difficulty is required.">
                                    <?php
                                    $difficulties = ['Beginner', 'Intermediate', 'Advanced'];
                                    $selectedDifficulty = $_POST['action'] ?? '' ? ($_POST['difficulty'] ?? 'Intermediate') : ($editingWork['difficulty'] ?? 'Intermediate');
                                    foreach ($difficulties as $difficulty) {
                                        $selected = $selectedDifficulty === $difficulty ? 'selected' : '';
                                        echo "<option value=\"" . h($difficulty) . "\" $selected>" . h($difficulty) . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="challengeDifficultyError"><?= h($workDifficultyError); ?></small>
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <select id="challengeStatusSelect" name="status" data-required-message="Status is required.">
                                    <?php
                                    $statuses = ['published', 'draft', 'closed'];
                                    $selectedWorkStatus = strtolower((string) ($_POST['action'] ?? '' ? ($_POST['status'] ?? 'published') : ($editingWork['status'] ?? 'published')));
                                    foreach ($statuses as $status) {
                                        $selected = $selectedWorkStatus === $status ? 'selected' : '';
                                        echo "<option value=\"" . h($status) . "\" $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="field-error" id="challengeStatusError"><?= h($workStatusError); ?></small>
                            </div>
                            <div class="field">
                                <label>Deadline</label>
                                <input
                                    type="datetime-local"
                                    id="challengeDeadlineInput"
                                    name="deadline"
                                    value="<?= h($_POST['action'] ?? '' ? ($_POST['deadline'] ?? '') : (!empty($editingWork['deadline']) ? date('Y-m-d\TH:i', strtotime((string) $editingWork['deadline'])) : '')); ?>"
                                    data-validation="deadline"
                                >
                                <small class="field-error" id="challengeDeadlineError"><?= h($deadlineError); ?></small>
                            </div>
                            <div class="field">
                                <label>Description</label>
                                <textarea id="challengeDescriptionInput" name="description" data-required-message="Description is required."><?= h($_POST['action'] ?? '' ? ($_POST['description'] ?? '') : ($editingWork['description'] ?? '')); ?></textarea>
                                <small class="field-error" id="challengeDescriptionError"><?= h($workDescriptionError); ?></small>
                            </div>
                        </div>
                        <div class="table-foot">
                            <div class="header-actions">
                                <?php if ($editingWork) { ?>
                                    <a class="btn btn-soft" href="admin-skills.php">Cancel</a>
                                <?php } ?>
                                <button class="btn btn-main" type="submit"><?= $editingWork ? 'Update item' : 'Publish item'; ?></button>
                            </div>
                        </div>
                    </form>
                </article>
            </section>

            <section class="split-grid" style="margin-top: 24px;">
                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>Manager roster</h3>
                            <p>Managers and admins available for hub publishing rights</p>
                        </div>
                    </div>
                    <div class="list">
                        <?php foreach ($managers as $manager) { ?>
                            <div class="list-item">
                                <strong><?= h($manager['fullName']); ?></strong>
                                <p class="subtle"><?= h(ucfirst((string) $manager['role'])); ?></p>
                            </div>
                        <?php } ?>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3>What is live right now</h3>
                        </div>
                    </div>
                    <div class="list">
                        <?php foreach (array_slice($workItems, 0, 4) as $item) { ?>
                            <div class="list-item">
                                <strong><?= h($item['title']); ?></strong>
                                <p class="subtle"><?= h($item['hubName']); ?> / <?= h(ucfirst((string) $item['type'])); ?> / <?= h($item['status']); ?></p>
                            </div>
                        <?php } ?>
                    </div>
                </article>
            </section>
        </main>
    </div>
    <script src="assets/js/admin.js"></script>
</body>
</html>
