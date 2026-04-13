<?php
require_once __DIR__ . '/../../Controller/SkillHubController.php';

$controller = new SkillHubController();
$message = null;
$messageType = 'soft';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_hub':
            $ok = $controller->createHub([
                'name' => $_POST['name'] ?? '',
                'category' => $_POST['category'] ?? '',
                'description' => $_POST['description'] ?? '',
                'status' => $_POST['status'] ?? 'active',
            ]);
            $message = $ok ? 'Hub created successfully.' : 'Could not create the hub.';
            $messageType = $ok ? 'published' : 'draft';
            break;

        case 'update_hub':
            $ok = $controller->updateHub((int) ($_POST['groupId'] ?? 0), [
                'name' => $_POST['name'] ?? '',
                'category' => $_POST['category'] ?? '',
                'description' => $_POST['description'] ?? '',
                'status' => $_POST['status'] ?? 'active',
            ]);
            $message = $ok ? 'Hub updated successfully.' : 'Could not update the hub.';
            $messageType = $ok ? 'published' : 'draft';
            break;

        case 'delete_hub':
            $ok = $controller->deleteHub((int) ($_POST['groupId'] ?? 0));
            $message = $ok ? 'Hub deleted successfully.' : 'Could not delete the hub.';
            $messageType = $ok ? 'published' : 'draft';
            break;

        case 'create_work':
            $ok = $controller->createWorkItem([
                'groupId' => $_POST['groupId'] ?? 0,
                'managerId' => $_POST['managerId'] ?? 0,
                'type' => $_POST['type'] ?? 'task',
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'difficulty' => $_POST['difficulty'] ?? '',
                'deadline' => $_POST['deadline'] ?? null,
                'status' => $_POST['status'] ?? 'published',
            ]);
            $message = $ok ? 'Work item published successfully.' : 'Could not publish the work item.';
            $messageType = $ok ? 'published' : 'draft';
            break;

        case 'update_work':
            $ok = $controller->updateWorkItem((int) ($_POST['challengeId'] ?? 0), [
                'groupId' => $_POST['groupId'] ?? 0,
                'managerId' => $_POST['managerId'] ?? 0,
                'type' => $_POST['type'] ?? 'task',
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'difficulty' => $_POST['difficulty'] ?? '',
                'deadline' => $_POST['deadline'] ?? null,
                'status' => $_POST['status'] ?? 'published',
            ]);
            $message = $ok ? 'Work item updated successfully.' : 'Could not update the work item.';
            $messageType = $ok ? 'published' : 'draft';
            break;

        case 'delete_work':
            $ok = $controller->deleteWorkItem((int) ($_POST['challengeId'] ?? 0));
            $message = $ok ? 'Work item deleted successfully.' : 'Could not delete the work item.';
            $messageType = $ok ? 'published' : 'draft';
            break;
    }
}

$stats = $controller->getStats();
$hubs = $controller->afficherHubs();
$workItems = $controller->getWorkItems();
$managers = $controller->getManagers();

$editingHub = null;
if (isset($_GET['editHub'])) {
    $editingHub = $controller->getHubById((int) $_GET['editHub']);
}

$editingWork = null;
if (isset($_GET['editWork'])) {
    $editingWork = $controller->getWorkItemById((int) $_GET['editWork']);
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
                    <p>Real CRUD is now connected for hubs and work items. You can create, edit, and delete hubs plus tasks/projects from this page.</p>
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
                    <div class="metric-sub">Communities available in the database</div>
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
                            <p>Read, edit, and delete hubs from the database</p>
                        </div>
                    </div>
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
                            <?php foreach ($hubs as $hub) { ?>
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
                        </tbody>
                    </table>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3><?= $editingHub ? 'Edit hub' : 'Create hub'; ?></h3>
                            <p><?= $editingHub ? 'Update the selected community.' : 'Create a new community directly in the database.'; ?></p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editingHub ? 'update_hub' : 'create_hub'; ?>">
                        <?php if ($editingHub) { ?>
                            <input type="hidden" name="groupId" value="<?= (int) $editingHub['groupId']; ?>">
                        <?php } ?>
                        <div class="field-grid">
                            <div class="field">
                                <label>Hub title</label>
                                <input type="text" name="name" value="<?= h($editingHub['name'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label>Category</label>
                                <select name="category" required>
                                    <?php
                                    $categories = ['Frontend', 'Design', 'Communication', 'Business'];
                                    $selectedCategory = $editingHub['category'] ?? '';
                                    foreach ($categories as $category) {
                                        $selected = $selectedCategory === $category ? 'selected' : '';
                                        echo "<option value=\"" . h($category) . "\" $selected>" . h($category) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <select name="status" required>
                                    <?php
                                    $statuses = ['active', 'draft', 'archived'];
                                    $selectedStatus = $editingHub['status'] ?? 'active';
                                    foreach ($statuses as $status) {
                                        $selected = strtolower((string) $selectedStatus) === $status ? 'selected' : '';
                                        echo "<option value=\"" . h($status) . "\" $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Created date</label>
                                <input type="text" value="<?= h((string) ($editingHub['createdAt'] ?? date('Y-m-d H:i:s'))); ?>" disabled>
                            </div>
                            <div class="field">
                                <label>Description</label>
                                <textarea name="description" required><?= h($editingHub['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="table-foot">
                            <span>Current schema stores the hub name, category, description, status, and creation date.</span>
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
                            <p>Read, edit, and delete the work items stored in Challenge</p>
                        </div>
                    </div>
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
                            <?php foreach ($workItems as $item) { ?>
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
                                        <form method="POST">
                                            <input type="hidden" name="action" value="delete_work">
                                            <input type="hidden" name="challengeId" value="<?= (int) $item['challengeId']; ?>">
                                            <button class="link-btn" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <h3><?= $editingWork ? 'Edit task or project' : 'Publish task or project'; ?></h3>
                            <p><?= $editingWork ? 'Update the selected work item in Challenge.' : 'Create a new task or project inside a hub.'; ?></p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $editingWork ? 'update_work' : 'create_work'; ?>">
                        <?php if ($editingWork) { ?>
                            <input type="hidden" name="challengeId" value="<?= (int) $editingWork['challengeId']; ?>">
                        <?php } ?>

                        <div class="field-grid">
                            <div class="field">
                                <label>Title</label>
                                <input type="text" name="title" value="<?= h($editingWork['title'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label>Hub</label>
                                <select name="groupId" required>
                                    <?php
                                    $selectedGroupId = (int) ($editingWork['groupId'] ?? 0);
                                    foreach ($hubs as $hub) {
                                        $selected = $selectedGroupId === (int) $hub['groupId'] ? 'selected' : '';
                                        echo "<option value=\"" . (int) $hub['groupId'] . "\" $selected>" . h($hub['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Manager</label>
                                <select name="managerId" required>
                                    <?php
                                    $selectedManagerId = (int) ($editingWork['managerId'] ?? 0);
                                    foreach ($managers as $manager) {
                                        $selected = $selectedManagerId === (int) $manager['userId'] ? 'selected' : '';
                                        echo "<option value=\"" . (int) $manager['userId'] . "\" $selected>" . h($manager['fullName']) . " (" . h($manager['role']) . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Type</label>
                                <select name="type" required>
                                    <?php
                                    $types = ['task', 'project'];
                                    $selectedType = strtolower((string) ($editingWork['type'] ?? 'task'));
                                    foreach ($types as $type) {
                                        $selected = $selectedType === $type ? 'selected' : '';
                                        echo "<option value=\"" . h($type) . "\" $selected>" . ucfirst($type) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Difficulty</label>
                                <select name="difficulty" required>
                                    <?php
                                    $difficulties = ['Beginner', 'Intermediate', 'Advanced'];
                                    $selectedDifficulty = $editingWork['difficulty'] ?? 'Intermediate';
                                    foreach ($difficulties as $difficulty) {
                                        $selected = $selectedDifficulty === $difficulty ? 'selected' : '';
                                        echo "<option value=\"" . h($difficulty) . "\" $selected>" . h($difficulty) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <select name="status" required>
                                    <?php
                                    $statuses = ['published', 'draft', 'closed'];
                                    $selectedWorkStatus = strtolower((string) ($editingWork['status'] ?? 'published'));
                                    foreach ($statuses as $status) {
                                        $selected = $selectedWorkStatus === $status ? 'selected' : '';
                                        echo "<option value=\"" . h($status) . "\" $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Deadline</label>
                                <input type="datetime-local" name="deadline" value="<?= !empty($editingWork['deadline']) ? h(date('Y-m-d\TH:i', strtotime((string) $editingWork['deadline']))) : ''; ?>">
                            </div>
                            <div class="field">
                                <label>Description</label>
                                <textarea name="description" required><?= h($editingWork['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="table-foot">
                            <span>Publishing stores the item in Challenge. Posts/comments stay linked through the discussion tables.</span>
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
                            <p>Quick live read from the stored Skill Hub records</p>
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
