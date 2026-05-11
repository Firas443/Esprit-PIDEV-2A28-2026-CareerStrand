<?php
$adminSidebarCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
$adminSidebarBadges = $adminSidebarBadges ?? [];

if (!function_exists('adminSidebarCountTable')) {
    function adminSidebarCountTable(string $table): ?int
    {
        try {
            if (!class_exists('config')) {
                require_once dirname(__DIR__, 2) . '/../config.php';
            }

            $pdo = config::getConnexion();
            $exists = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $exists->execute([$table]);

            if ((int) $exists->fetchColumn() < 1) {
                return null;
            }

            return (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
        } catch (Throwable $e) {
            return null;
        }
    }
}

$adminSidebarTableBadges = [
    'users' => 'users',
    'profiles' => 'profile',
    'courses' => 'course',
    'course-videos' => 'course_videos',
    'calendar' => 'calendar',
    'questions' => 'userquestionnaire',
    'skillhub' => 'skillhub',
    'opportunities' => 'opportunity',
    'applications' => 'application',
    'events' => 'event',
];

foreach ($adminSidebarTableBadges as $adminSidebarBadgeKey => $adminSidebarTable) {
    if (!isset($adminSidebarBadges[$adminSidebarBadgeKey])) {
        $adminSidebarCount = adminSidebarCountTable($adminSidebarTable);
        if ($adminSidebarCount !== null) {
            $adminSidebarBadges[$adminSidebarBadgeKey] = $adminSidebarCount;
        }
    }
}

if (!isset($adminSidebarBadges['users']) && isset($users) && is_countable($users)) {
    $adminSidebarBadges['users'] = count($users);
}

if (!isset($adminSidebarBadges['courses']) && isset($allCourses) && is_countable($allCourses)) {
    $adminSidebarBadges['courses'] = count($allCourses);
}

if (!isset($adminSidebarBadges['skillhub']) && isset($stats['hubCount'])) {
    $adminSidebarBadges['skillhub'] = $stats['hubCount'];
}

if (!isset($adminSidebarBadges['events']) && isset($dbEvents) && is_countable($dbEvents)) {
    $adminSidebarBadges['events'] = count($dbEvents);
}

$adminSidebarItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin-dashboard.php', 'badge' => 'Home'],
    ['key' => 'users', 'label' => 'Users', 'href' => 'admin-users.php', 'badge' => '-'],
    ['key' => 'profiles', 'label' => 'Profiles', 'href' => 'admin-profiles.php', 'badge' => '-'],
    ['key' => 'courses', 'label' => 'Courses', 'href' => 'admin-courses.php', 'badge' => '-'],
    ['key' => 'course-videos', 'label' => 'Course Videos', 'href' => 'admin-course-videos.php', 'badge' => 'New'],
    ['key' => 'calendar', 'label' => 'Calendar', 'href' => 'admin-calendrier.php', 'badge' => '-'],
    ['key' => 'questions', 'label' => 'Questions', 'href' => 'admin-questions.php', 'badge' => '-'],
    ['key' => 'skillhub', 'label' => 'Skill Hub', 'href' => 'admin-skills.php', 'badge' => '-'],
    ['key' => 'opportunities', 'label' => 'Opportunities', 'href' => 'admin-opportunities.php', 'badge' => '-'],
    ['key' => 'applications', 'label' => 'Applications', 'href' => 'admin-applications.php', 'badge' => '-'],
    ['key' => 'events', 'label' => 'Events', 'href' => 'admin-feedback.php', 'badge' => '-'],
];

$adminSidebarEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<aside class="admin-sidebar">
    <div class="brand">
        <div class="brand-badge">
            <img src="../FrontOffice/images/CareerStrand_logo_mark.png" alt="CareerStrand logo">
        </div>
        <div>
            <h1>CareerStrand Admin</h1>
            <p>Back office console</p>
        </div>
    </div>

    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
        <?php foreach ($adminSidebarItems as $adminSidebarItem) {
            $adminSidebarIsActive = $adminSidebarCurrent === $adminSidebarItem['href'];
            $adminSidebarBadge = $adminSidebarBadges[$adminSidebarItem['key']] ?? $adminSidebarItem['badge'];
            $adminSidebarBadgeClass = trim('badge ' . ($adminSidebarItem['badgeClass'] ?? ''));
            $adminSidebarBadgeId = $adminSidebarIsActive && in_array($adminSidebarItem['key'], ['applications', 'opportunities'], true)
                ? ' id="sideCount"'
                : '';
        ?>
            <a class="nav-item<?= $adminSidebarIsActive ? ' active' : ''; ?>" href="<?= $adminSidebarEscape($adminSidebarItem['href']); ?>"<?= $adminSidebarIsActive ? ' aria-current="page"' : ''; ?>>
                <span><?= $adminSidebarEscape($adminSidebarItem['label']); ?></span>
                <span class="<?= $adminSidebarEscape($adminSidebarBadgeClass); ?>"<?= $adminSidebarBadgeId; ?>><?= $adminSidebarEscape($adminSidebarBadge); ?></span>
            </a>
        <?php } ?>
    </nav>

    <?php if (!empty($adminSidebarCardTitle) || !empty($adminSidebarCardText) || !empty($adminSidebarCardHtml)) { ?>
        <div class="sidebar-card">
            <?php if (!empty($adminSidebarCardTitle)) { ?>
                <h3><?= $adminSidebarEscape($adminSidebarCardTitle); ?></h3>
            <?php } ?>
            <?php if (!empty($adminSidebarCardText)) { ?>
                <p><?= $adminSidebarEscape($adminSidebarCardText); ?></p>
            <?php } ?>
            <?php if (!empty($adminSidebarCardHtml)) { ?>
                <?= $adminSidebarCardHtml; ?>
            <?php } ?>
        </div>
    <?php } ?>
</aside>
<?php
unset(
    $adminSidebarItems,
    $adminSidebarItem,
    $adminSidebarCurrent,
    $adminSidebarIsActive,
    $adminSidebarBadge,
    $adminSidebarBadgeClass,
    $adminSidebarBadgeId,
    $adminSidebarEscape,
    $adminSidebarBadges,
    $adminSidebarCardTitle,
    $adminSidebarCardText,
    $adminSidebarCardHtml,
    $adminSidebarTableBadges,
    $adminSidebarBadgeKey,
    $adminSidebarTable,
    $adminSidebarCount
);
?>
