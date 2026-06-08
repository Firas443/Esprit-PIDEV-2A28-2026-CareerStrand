<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Controller/PortfolioController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../Model/Profile.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';

function page_to_array(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if ($value instanceof Traversable) {
        return iterator_to_array($value, false);
    }
    if (is_object($value)) {
        return get_object_vars($value);
    }
    return [];
}

function page_field(array|object|null $item, array $keys, string $default = ''): string
{
    if ($item === null) {
        return $default;
    }

    foreach ($keys as $key) {
        if (is_array($item) && array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
            return trim((string) $item[$key]);
        }

        if (is_object($item)) {
            if (isset($item->$key) && $item->$key !== null && $item->$key !== '') {
                return trim((string) $item->$key);
            }

            $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $key)));
            if (method_exists($item, $getter)) {
                $value = $item->$getter();
                if ($value !== null && $value !== '') {
                    return trim((string) $value);
                }
            }
        }
    }

    return $default;
}

function page_tags(array|object|null $item): array
{
    $raw = page_field($item, ['technologies', 'technology', 'skills', 'tags', 'stack', 'keywords'], '');
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[,|]+/', $raw) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
}

function page_rgb(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = preg_replace('/(.)/', '$1$1', $hex) ?? $hex;
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return '143,177,255';
    }

    return implode(',', array_map(
        static fn(string $chunk): string => (string) hexdec($chunk),
        str_split($hex, 2)
    ));
}

function page_short(string $value, int $limit = 150): string
{
    $value = trim($value);
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...');
    }
    return strlen($value) > $limit ? substr($value, 0, max(0, $limit - 3)) . '...' : $value;
}

function page_project_cards(array $portfolioData): array
{
    $sections = [
        'Featured Work' => $portfolioData['projects'] ?? $portfolioData['portfolio'] ?? [],
        'Experience' => $portfolioData['experience'] ?? $portfolioData['experiences'] ?? [],
        'Education' => $portfolioData['education'] ?? $portfolioData['courses'] ?? [],
        'Certificates' => $portfolioData['certificates'] ?? $portfolioData['applications'] ?? [],
    ];

    $cards = [];
    foreach ($sections as $sectionLabel => $items) {
        foreach (page_to_array($items) as $item) {
            if (!is_array($item) && !is_object($item)) {
                continue;
            }

            $title = page_field($item, ['title', 'name', 'projectName', 'company', 'position', 'school', 'label'], $sectionLabel);
            $body = page_field($item, ['description', 'summary', 'details', 'content', 'bio', 'notes', 'result', 'achievement'], '');
            $meta = page_field($item, ['type', 'category', 'role', 'status', 'duration', 'year', 'date', 'location'], $sectionLabel);
            $link = page_field($item, ['link', 'url', 'github', 'website', 'portfolioLink'], '');
            $image = page_field($item, ['image', 'imageUrl', 'thumbnail', 'cover', 'photo'], '');

            if ($body === '') {
                $body = page_field($item, ['problem', 'goal', 'overview'], 'A curated highlight from the portfolio data.');
            }

            $cards[] = [
                'section' => $sectionLabel,
                'title' => $title,
                'body' => $body,
                'meta' => $meta,
                'link' => $link,
                'image' => $image,
                'tags' => page_tags($item),
            ];
        }
    }

    return $cards;
}

$userId = (int) ($_GET['userId'] ?? $_GET['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo 'Missing user ID.';
    exit;
}

$portfolioController = new PortfolioController();
$profileController = new ProfileController();
$viewer = function_exists('currentFrontUser') ? currentFrontUser() : null;
$viewerId = (int) ($viewer['userId'] ?? 0);
$portfolioData = method_exists($portfolioController, 'getPortfolioData')
    ? (array) ($portfolioController->getPortfolioData($userId) ?? [])
    : [];

$profile = $portfolioData['profile'] ?? $profileController->getByUserId($userId);

if ($profile instanceof Profile) {
    $theme = $profile->getPortfolioTheme();
    $accent = $profile->getPortfolioPrimaryColor();
    $accent2 = $profile->getPortfolioSecondaryColor();
    $bio = trim($profile->getBio());
    $location = trim($profile->getLocation());
    $preferences = trim($profile->getPreferences());
    $careerGoal = trim($profile->getCareerGoal());
    $photoUrl = trim($profile->getPhotoUrl());
    $completionScore = $profile->getCompletionScore();
    $level = $profile->getLevel();
} else {
    $theme = 'midnight';
    $themePalette = ProfileController::themePalette($theme);
    $accent = $themePalette['portfolioPrimary'];
    $accent2 = $themePalette['portfolioSecondary'];
    $bio = '';
    $location = '';
    $preferences = '';
    $careerGoal = '';
    $photoUrl = '';
    $completionScore = 0;
    $level = 'Starter';
}

$themePalette = ProfileController::themePalette($theme);
$accent = $accent ?: $themePalette['portfolioPrimary'];
$accent2 = $accent2 ?: $themePalette['portfolioSecondary'];
$accentRgb = page_rgb($accent);
$accent2Rgb = page_rgb($accent2);

$displayName = page_field($portfolioData['user'] ?? null, ['fullName', 'name', 'displayName', 'username'], '');
if ($displayName === '') {
    $displayName = 'Creative Portfolio';
}

$headline = $careerGoal !== '' ? $careerGoal : page_field($portfolioData['headline'] ?? null, ['title', 'headline'], 'Digital work with a clean point of view.');
$summary = $bio !== '' ? $bio : page_field($portfolioData['summary'] ?? null, ['description', 'summary'], 'A focused portfolio built to present skills, projects, and career direction with a premium first impression.');
$cards = page_project_cards($portfolioData);
$focusAreas = array_values(array_filter(array_map('trim', preg_split('/[,|]+/', $preferences) ?: [])));
$skillItems = !empty($focusAreas) ? $focusAreas : ['Frontend', 'Database', 'UI Design', 'Problem Solving', 'Communication'];
$experienceItems = array_slice(page_to_array($portfolioData['experience'] ?? $portfolioData['experiences'] ?? []), 0, 4);
$educationItems = array_slice(page_to_array($portfolioData['education'] ?? $portfolioData['courses'] ?? []), 0, 4);
$achievements = array_slice(page_to_array($portfolioData['achievements'] ?? []), 0, 4);
$certificates = array_slice(page_to_array($portfolioData['certificates'] ?? []), 0, 4);
$serviceRows = array_slice($skillItems, 0, 4);
while (count($serviceRows) < 4) {
    $serviceRows[] = ['Branding Design', 'UI/UX Design', 'Web Design', 'App Design'][count($serviceRows)];
}

$stats = [
    ['label' => 'Profile score', 'value' => $completionScore . '%'],
    ['label' => 'Level', 'value' => $level],
    ['label' => 'Projects', 'value' => (string) max(1, count($cards))],
    ['label' => 'Location', 'value' => $location !== '' ? $location : 'Remote'],
];
$mentorRequestUrl = 'mentor-requests.php?sourceType=profile&sourceId=' . $userId . '&sourceLabel=' . rawurlencode($displayName);
$downloadMode = isset($_GET['download']) && $_GET['download'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($displayName) ?> - Portfolio</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #050509;
            --panel: #0d0a13;
            --panel-2: #111622;
            --text: #f7f4ff;
            --muted: rgba(247, 244, 255, 0.68);
            --line: rgba(255, 255, 255, 0.1);
            --accent: <?= htmlspecialchars($accent) ?>;
            --accent-2: <?= htmlspecialchars($accent2) ?>;
            --accent-rgb: <?= htmlspecialchars($accentRgb) ?>;
            --accent-2-rgb: <?= htmlspecialchars($accent2Rgb) ?>;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at 88% 8%, rgba(var(--accent-rgb), .28), transparent 26rem),
                radial-gradient(circle at 12% 35%, rgba(var(--accent-2-rgb), .18), transparent 25rem),
                linear-gradient(180deg, #050509 0%, #09050f 48%, #050509 100%);
            font-family: "Space Grotesk", "Segoe UI", Arial, sans-serif;
        }

        a { color: inherit; text-decoration: none; }
        .page { width: min(1160px, calc(100% - 32px)); margin: 0 auto; }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(5, 5, 9, .82);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--line);
        }
        .topbar-inner {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .brand { display: flex; align-items: center; gap: 12px; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
        .brand-mark { width: 38px; height: 38px; border-radius: 8px; display: grid; place-items: center; color: #050509; font-weight: 900; background: linear-gradient(135deg, var(--accent), var(--accent-2)); }
        .nav { display: flex; gap: 18px; flex-wrap: wrap; color: var(--muted); font-size: 13px; }
        .nav a:hover { color: var(--text); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 11px 14px;
            background: rgba(255, 255, 255, .04);
            color: var(--text);
            font-weight: 800;
            font-size: 13px;
        }
        .btn.primary { border: 0; color: #050509; background: linear-gradient(135deg, var(--accent), var(--accent-2)); }

        .hero {
            min-height: calc(100vh - 76px);
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(280px, .78fr);
            gap: clamp(24px, 5vw, 64px);
            align-items: center;
            padding: 54px 0 42px;
        }
        .kicker { color: var(--muted); margin: 0 0 10px; font-size: 14px; }
        h1, h2, h3, p { margin-top: 0; }
        .hero h1 { max-width: 780px; margin-bottom: 18px; font-size: clamp(48px, 8vw, 92px); line-height: .92; letter-spacing: 0; }
        .hero h1 span { display: block; color: var(--accent); }
        .lead { max-width: 720px; color: var(--muted); font-size: 18px; line-height: 1.75; }
        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 28px; }
        .portrait {
            position: relative;
            min-height: 430px;
            border-radius: 8px;
            overflow: hidden;
            background:
                radial-gradient(circle at center, rgba(var(--accent-rgb), .3), transparent 58%),
                linear-gradient(145deg, #14101d, #050509);
            border: 1px solid var(--line);
            box-shadow: 0 38px 80px rgba(var(--accent-rgb), .18);
        }
        .portrait img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            filter: contrast(1.08) saturate(1.05);
        }
        .portrait-empty { position: absolute; inset: 0; display: grid; place-items: center; padding: 28px; color: var(--muted); text-align: center; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 44px; }
        .stat { min-width: 0; display: grid; gap: 6px; }
        .stat strong { display: block; font-size: clamp(20px, 2.4vw, 34px); line-height: 1; letter-spacing: -0.03em; white-space: nowrap; }
        .stat span { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }

        .section { padding: 72px 0; border-top: 1px solid var(--line); }
        .section-head { display: grid; gap: 8px; justify-items: center; text-align: center; margin-bottom: 30px; }
        .section-head h2 { font-size: clamp(30px, 4vw, 48px); margin: 0; }
        .section-head p { color: var(--muted); max-width: 700px; line-height: 1.7; }
        .services { display: grid; gap: 10px; }
        .service-row {
            display: grid;
            grid-template-columns: 70px minmax(180px, .45fr) minmax(0, 1fr) 42px;
            gap: 18px;
            align-items: center;
            min-height: 72px;
            padding: 0 20px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.035);
        }
        .service-row:first-child { background: linear-gradient(90deg, rgba(var(--accent-rgb), .92), rgba(var(--accent-2-rgb), .72)); color: #050509; }
        .service-row p { margin: 0; color: inherit; opacity: .82; }
        .num { font-weight: 900; color: var(--accent); }
        .service-row:first-child .num { color: #050509; }

        .work-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .work-card {
            min-height: 280px;
            padding: 22px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background:
                linear-gradient(180deg, rgba(255,255,255,.055), rgba(255,255,255,.025)),
                radial-gradient(circle at top right, rgba(var(--accent-rgb), .16), transparent 52%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .work-image {
            height: 160px;
            margin: -2px -2px 20px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background:
                linear-gradient(135deg, rgba(var(--accent-rgb), .24), rgba(var(--accent-2-rgb), .18)),
                repeating-linear-gradient(90deg, rgba(255,255,255,.08) 0 1px, transparent 1px 20px);
            overflow: hidden;
        }
        .work-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pill, .tag { display: inline-flex; width: fit-content; border-radius: 8px; padding: 7px 10px; border: 1px solid var(--line); color: var(--muted); font-size: 12px; }
        .work-card h3 { margin: 14px 0 10px; font-size: 24px; }
        .work-card p { color: var(--muted); line-height: 1.65; }
        .tag-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
        .tag { color: var(--text); background: rgba(var(--accent-rgb), .1); }

        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
        .timeline { display: grid; gap: 12px; }
        .timeline-card { padding: 18px; border-radius: 8px; background: rgba(var(--accent-rgb), .09); border: 1px solid var(--line); }
        .timeline-card small { color: var(--accent); font-weight: 900; }
        .timeline-card strong { display: block; margin: 6px 0; font-size: 18px; }
        .timeline-card span { color: var(--muted); }

        .skills { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
        .skill { min-height: 120px; border-radius: 8px; border: 1px solid var(--line); background: rgba(255,255,255,.035); display: grid; place-items: center; text-align: center; padding: 16px; }
        .skill strong { display: block; font-size: 15px; }
        .skill span { color: var(--muted); font-size: 12px; }
        .skill i { width: 34px; height: 34px; border-radius: 8px; display: grid; place-items: center; margin: 0 auto 10px; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #050509; font-style: normal; font-weight: 900; }

        .contact { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, .55fr); gap: 22px; align-items: stretch; }
        .contact-panel { border-radius: 8px; border: 1px solid var(--line); padding: 28px; background: rgba(255,255,255,.035); }
        .contact-panel h2 { font-size: clamp(32px, 5vw, 64px); line-height: .98; }
        .contact-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
        .contact-list li { display: flex; justify-content: space-between; gap: 16px; border-bottom: 1px solid var(--line); padding-bottom: 12px; color: var(--muted); }
        .contact-list strong { color: var(--text); }

        @media (max-width: 900px) {
            .hero, .split, .contact { grid-template-columns: 1fr; }
            .stats, .skills { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .work-grid { grid-template-columns: 1fr; }
            .service-row { grid-template-columns: 44px 1fr; }
            .service-row p, .service-row .arrow { grid-column: 2; }
            .topbar-inner { min-height: auto; padding: 14px 0; align-items: flex-start; flex-direction: column; }
        }

        @media (max-width: 560px) {
            .page { width: min(100% - 20px, 100%); }
            .stats, .skills { grid-template-columns: 1fr; }
            .hero h1 { font-size: 44px; }
        }

        @media print {
            body { background: #fff !important; color: #111 !important; }
            .topbar, .hero-actions, .contact .actions { display: none !important; }
            .page { width: 100%; }
            .hero { min-height: auto; }
            .section { padding: 28px 0; }
            .service-row, .work-card, .timeline-card, .skill, .contact-panel { break-inside: avoid; border-color: #ddd !important; background: #fff !important; color: #111 !important; }
        }
    </style>
    <?php if ($downloadMode): ?>
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    <?php endif; ?>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="#top">
            <span class="brand-mark"><?= htmlspecialchars(strtoupper(substr($displayName, 0, 1))) ?></span>
            <span><?= htmlspecialchars($displayName) ?></span>
        </a>
        <nav class="nav" aria-label="Portfolio sections">
            <a href="#services">Services</a>
            <a href="#work">Works</a>
            <a href="#experience">Resume</a>
            <a href="#skills">Skills</a>
            <a href="#contact">Contact</a>
        </nav>
        <div class="actions">
            <a class="btn" href="profile.php">Profile</a>
            <a class="btn primary" href="export_cv.php?userId=<?= $userId ?>">Open CV</a>
        </div>
    </div>
</header>

<main id="top" class="page">
    <section class="hero">
        <div>
            <p class="kicker">I am <?= htmlspecialchars($displayName) ?></p>
            <h1><?= htmlspecialchars($headline) ?><span><?= htmlspecialchars($level) ?></span></h1>
            <p class="lead"><?= htmlspecialchars($summary) ?></p>
            <div class="hero-actions">
                <a class="btn primary" href="#work">Selected work</a>
                <a class="btn" href="#contact">Contact me</a>
                <a class="btn" href="?userId=<?= $userId ?>&download=1">Download PDF</a>
                <?php if ($viewerId > 0 && $viewerId !== $userId): ?>
                    <a class="btn" href="<?= htmlspecialchars($mentorRequestUrl) ?>">Request admin support</a>
                <?php endif; ?>
            </div>
            <div class="stats">
                <?php foreach ($stats as $stat): ?>
                    <div class="stat">
                        <strong><?= htmlspecialchars($stat['value']) ?></strong>
                        <span><?= htmlspecialchars($stat['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <aside class="portrait">
            <?php if ($photoUrl !== ''): ?>
                <img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($displayName) ?>">
            <?php else: ?>
                <div class="portrait-empty">Add a profile photo to complete the portfolio hero.</div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="section" id="services">
        <div class="section-head">
            <h2>My Quality Services</h2>
            <p>Services are shaped from your profile preferences and skills, then presented in a clean editorial layout.</p>
        </div>
        <div class="services">
            <?php foreach ($serviceRows as $index => $service): ?>
                <article class="service-row">
                    <strong class="num"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></strong>
                    <strong><?= htmlspecialchars((string) $service) ?></strong>
                    <p><?= htmlspecialchars('I turn ' . strtolower((string) $service) . ' into focused, portfolio-ready output with clear structure and polish.') ?></p>
                    <span class="arrow">/</span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section" id="work">
        <div class="section-head">
            <h2>My Recent Works</h2>
            <p>Project cards use your real portfolio data when available and keep a visual placeholder when the item has no image yet.</p>
        </div>
        <div class="work-grid">
            <?php if (!empty($cards)): ?>
                <?php foreach (array_slice($cards, 0, 6) as $card): ?>
                    <article class="work-card">
                        <div>
                            <div class="work-image">
                                <?php if ($card['image'] !== ''): ?>
                                    <img src="<?= htmlspecialchars($card['image']) ?>" alt="<?= htmlspecialchars($card['title']) ?>">
                                <?php endif; ?>
                            </div>
                            <span class="pill"><?= htmlspecialchars($card['section']) ?></span>
                            <h3><?= htmlspecialchars($card['title']) ?></h3>
                            <p><?= htmlspecialchars(page_short($card['body'], 170)) ?></p>
                            <?php if (!empty($card['tags'])): ?>
                                <div class="tag-row">
                                    <?php foreach (array_slice($card['tags'], 0, 4) as $tag): ?>
                                        <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($card['link'] !== ''): ?>
                            <a class="btn" href="<?= htmlspecialchars($card['link']) ?>" target="_blank" rel="noopener">Open project</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <article class="work-card">
                    <div>
                        <div class="work-image"></div>
                        <span class="pill">Portfolio</span>
                        <h3>No project data yet</h3>
                        <p>Add projects, applications, courses, or certificates and this area will become a polished work grid.</p>
                    </div>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="section split" id="experience">
        <div>
            <div class="section-head" style="justify-items:start;text-align:left">
                <h2>My Experience</h2>
            </div>
            <div class="timeline">
                <?php if (!empty($experienceItems)): ?>
                    <?php foreach ($experienceItems as $item): ?>
                        <article class="timeline-card">
                            <small><?= htmlspecialchars(page_field($item, ['year', 'date', 'duration', 'status'], 'Experience')) ?></small>
                            <strong><?= htmlspecialchars(page_field($item, ['position', 'title', 'company', 'name'], 'Experience entry')) ?></strong>
                            <span><?= htmlspecialchars(page_field($item, ['description', 'summary', 'details', 'role'], '')) ?></span>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="timeline-card"><small>Next step</small><strong>Add experience</strong><span>Your work history will appear here.</span></article>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="section-head" style="justify-items:start;text-align:left">
                <h2>My Education</h2>
            </div>
            <div class="timeline">
                <?php if (!empty($educationItems)): ?>
                    <?php foreach ($educationItems as $item): ?>
                        <article class="timeline-card">
                            <small><?= htmlspecialchars(page_field($item, ['year', 'date', 'duration', 'status'], 'Education')) ?></small>
                            <strong><?= htmlspecialchars(page_field($item, ['title', 'name', 'school', 'label'], 'Education entry')) ?></strong>
                            <span><?= htmlspecialchars(page_field($item, ['description', 'summary', 'details', 'content'], '')) ?></span>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <article class="timeline-card"><small>Next step</small><strong>Add education</strong><span>Courses and education entries will appear here.</span></article>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section" id="skills">
        <div class="section-head">
            <h2>My Skills</h2>
            <p>These skills come from your profile preferences and can be tuned by changing your profile data.</p>
        </div>
        <div class="skills">
            <?php foreach (array_slice($skillItems, 0, 10) as $skill): ?>
                <div class="skill">
                    <div>
                        <i><?= htmlspecialchars(strtoupper(substr((string) $skill, 0, 1))) ?></i>
                        <strong><?= htmlspecialchars((string) $skill) ?></strong>
                        <span><?= htmlspecialchars($level) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section contact" id="contact">
        <div class="contact-panel">
            <h2>Let's work together.</h2>
            <p class="lead"><?= htmlspecialchars($summary) ?></p>
            <div class="actions">
                <a class="btn primary" href="export_cv.php?userId=<?= $userId ?>">Open CV</a>
                <a class="btn" href="profile.php">Edit profile</a>
            </div>
        </div>
        <aside class="contact-panel">
            <ul class="contact-list">
                <li><strong>Name</strong><span><?= htmlspecialchars($displayName) ?></span></li>
                <li><strong>Location</strong><span><?= htmlspecialchars($location !== '' ? $location : 'Remote') ?></span></li>
                <li><strong>Badges</strong><span><?= count($achievements) ?></span></li>
                <li><strong>Certificates</strong><span><?= count($certificates) ?></span></li>
            </ul>
        </aside>
    </section>
</main>
</body>
</html>
