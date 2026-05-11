<?php
$activePage = $activePage ?? '';
$brandSubtitle = $brandSubtitle ?? 'career progression';
$frontUser = $frontUser ?? (function_exists('currentFrontUser') ? currentFrontUser() : null);

$navItems = [
    'home' => ['label' => 'Home', 'href' => 'home.php'],
    'profile' => ['label' => 'Profile', 'href' => 'profile.php'],
    'education' => ['label' => 'Education', 'href' => 'course.php'],
    'skillhub' => ['label' => 'Skill Hub', 'href' => 'skillhub.php'],
    'events' => ['label' => 'Events', 'href' => 'events.php'],
    'opportunities' => ['label' => 'Opportunities', 'href' => 'opportunities.php'],
];
?>
<header class="site-header cs-front-header">
    <div class="container header-inner">
        <a class="brand cs-front-brand" href="index.php">
            <img class="brand-logo cs-front-logo" src="images/CareerStrand_logo_mark.png" alt="CareerStrand logo">
            <div>
                <div class="brand-title">CareerStrand</div>
                <div class="brand-subtitle"><?php echo htmlspecialchars($brandSubtitle); ?></div>
            </div>
        </a>

        <nav class="main-nav cs-front-nav" aria-label="Primary">
            <?php foreach ($navItems as $key => $item): ?>
                <?php $isActive = $activePage === $key; ?>
                <a
                    href="<?php echo htmlspecialchars($item['href']); ?>"
                    class="<?php echo $isActive ? 'active' : ''; ?>"
                ><?php echo htmlspecialchars($item['label']); ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="header-actions cs-front-actions">
            <?php if ($frontUser): ?>
                <span class="cs-user-name"><?php echo htmlspecialchars($frontUser['fullName'] ?? ''); ?></span>
                <span class="cs-user-avatar" title="<?php echo htmlspecialchars($frontUser['fullName'] ?? 'CareerStrand user'); ?>">
                    <?php echo htmlspecialchars(frontUserInitials($frontUser)); ?>
                </span>
                <a class="ghost-btn" href="logout.php">Sign out</a>
            <?php else: ?>
                <a class="ghost-btn" href="login.php">Sign in</a>
                <a class="primary-btn" href="signup.php?action=signup">Build your DNA</a>
            <?php endif; ?>
        </div>
    </div>
</header>
