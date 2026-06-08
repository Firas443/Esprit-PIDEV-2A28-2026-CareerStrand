<?php
require_once __DIR__ . '/../../../utils/FrontOfficePeople.php';
require_once __DIR__ . '/../../../Controller/ProfileController.php';

$activePage = $activePage ?? '';
$brandSubtitle = $brandSubtitle ?? 'career progression';
$frontUser = $frontUser ?? (function_exists('currentFrontUser') ? currentFrontUser() : null);
$frontUserPhotoUrl = '';

if ($frontUser && !isset($frontUser['photoUrl'])) {
    $navProfileController = new ProfileController();
    $navProfile = $navProfileController->getByUserId((int) ($frontUser['userId'] ?? 0));
    $frontUserPhotoUrl = trim((string) ($navProfile?->getPhotoUrl() ?? ''));
} elseif ($frontUser) {
    $frontUserPhotoUrl = trim((string) ($frontUser['photoUrl'] ?? ''));
}

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
                <?php if (isset($homeNotifications) && is_array($homeNotifications)): ?>
                    <button class="home-notif-button" type="button" id="homeNotifButton" aria-label="Open notifications">
                        <span class="home-notif-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 17H5l1.2-1.9A4 4 0 0 0 7 13V9a5 5 0 0 1 10 0v4c0 .7.2 1.4.8 2L19 17h-4"></path>
                                <path d="M9.5 19a2.5 2.5 0 0 0 5 0"></path>
                            </svg>
                        </span>
                        <span class="home-notif-button-count" id="homeNotifButtonCount"><?= (int) ($homeNotificationCount ?? 0); ?></span>
                    </button>
                <?php endif; ?>
                <span class="cs-user-name"><?php echo htmlspecialchars($frontUser['fullName'] ?? ''); ?></span>
                <span class="cs-user-avatar" title="<?php echo htmlspecialchars($frontUser['fullName'] ?? 'CareerStrand user'); ?>">
                    <?php if ($frontUserPhotoUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($frontUserPhotoUrl); ?>" alt="<?php echo htmlspecialchars($frontUser['fullName'] ?? 'CareerStrand user'); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars(frontUserInitials($frontUser)); ?>
                    <?php endif; ?>
                </span>
                <a class="ghost-btn" href="logout.php">Sign out</a>
                <a class="primary-btn" href="mentor-requests.php">Ask Mentor</a>
            <?php else: ?>
                <a class="ghost-btn" href="login.php">Sign in</a>
                <a class="primary-btn" href="signup.php?action=signup">Build your DNA</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php if ($frontUser): ?>
    <button
        class="cs-assistant-launcher"
        type="button"
        id="careerAssistantToggle"
        aria-expanded="false"
        aria-controls="careerAssistantPanel"
        data-user-id="<?php echo (int) ($frontUser['userId'] ?? 0); ?>"
    >
        <span class="cs-assistant-launcher-dot" aria-hidden="true"></span>
        <span class="cs-assistant-launcher-copy">
            <strong>AI Helper</strong>
            <small>Career guidance</small>
        </span>
    </button>

    <div class="cs-assistant-backdrop" id="careerAssistantBackdrop" hidden></div>
    <aside class="cs-assistant-panel" id="careerAssistantPanel" aria-hidden="true" hidden>
        <div class="cs-assistant-panel-head">
            <div>
                <span class="cs-assistant-kicker">AI Assistant and Career Helper</span>
                <h2>Ask CareerStrand anything career-related.</h2>
            </div>
            <button class="cs-assistant-close" type="button" id="careerAssistantClose" aria-label="Close assistant">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M6 6l12 12"></path>
                    <path d="M18 6L6 18"></path>
                </svg>
            </button>
        </div>
        <p class="cs-assistant-intro">
            Get quick help with course choices, opportunity fit, profile improvements, motivation letters, and dashboard data.
        </p>
        <div class="cs-assistant-prompts" aria-label="Suggested prompts">
            <button type="button" data-assistant-prompt="Which course should I take next?">Course to take next</button>
            <button type="button" data-assistant-prompt="Which opportunity fits me best?">Best-fit opportunity</button>
            <button type="button" data-assistant-prompt="How can I improve my profile?">Improve my profile</button>
            <button type="button" data-assistant-prompt="Help me write a motivation letter.">Motivation letter</button>
            <button type="button" data-assistant-prompt="Explain my dashboard data in simple language.">Explain dashboard data</button>
        </div>
        <div class="cs-assistant-messages" id="careerAssistantMessages" aria-live="polite">
            <article class="cs-assistant-message cs-assistant-message-bot">
                <div class="cs-assistant-avatar" aria-hidden="true">CS</div>
                <div class="cs-assistant-bubble">
                    <strong>Career helper ready</strong>
                    <p>I can help you decide what to do next, explain your dashboard, and turn your profile data into simple next steps.</p>
                </div>
            </article>
        </div>
        <form class="cs-assistant-form" id="careerAssistantForm">
            <label class="cs-assistant-input-label" for="careerAssistantInput">Ask a question</label>
            <textarea id="careerAssistantInput" rows="3" placeholder="Ask about courses, opportunities, profiles, motivation letters, or dashboard data..."></textarea>
            <div class="cs-assistant-form-actions">
                <button class="cs-assistant-send" type="submit">Send</button>
            </div>
        </form>
    </aside>
    <script defer src="assets/js/career-assistant.js"></script>
<?php endif; ?>
