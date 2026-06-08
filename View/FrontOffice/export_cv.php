<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Controller/PortfolioController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../Model/Profile.php';

function cv_to_array(mixed $value): array
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

function cv_field(array|object|null $item, array $keys, string $default = ''): string
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

function cv_tags(array|object|null $item): array
{
    $raw = cv_field($item, ['technologies', 'technology', 'skills', 'tags', 'stack', 'keywords'], '');
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[,|]+/', $raw) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
}

function cv_rgb(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = preg_replace('/(.)/', '$1$1', $hex) ?? $hex;
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return '31,35,41';
    }
    return implode(',', array_map(
        static fn(string $chunk): string => (string) hexdec($chunk),
        str_split($hex, 2)
    ));
}

function cv_short(string $value, int $limit = 170): string
{
    $value = trim($value);
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...');
    }
    return strlen($value) > $limit ? substr($value, 0, max(0, $limit - 3)) . '...' : $value;
}

function cv_build_items(array $portfolioData, array $keys, string $defaultSection): array
{
    $items = [];
    foreach ($keys as $sectionLabel => $source) {
        foreach (cv_to_array($source) as $item) {
            if (!is_array($item) && !is_object($item)) {
                continue;
            }

            $title = cv_field($item, ['title', 'name', 'projectName', 'company', 'position', 'school', 'label'], $defaultSection);
            $body = cv_field($item, ['description', 'summary', 'details', 'content', 'bio', 'notes', 'result', 'achievement'], '');
            $meta = cv_field($item, ['role', 'type', 'category', 'status', 'duration', 'year', 'date', 'location'], $sectionLabel);

            if ($body === '') {
                $body = cv_field($item, ['problem', 'goal', 'overview'], 'Portfolio entry ready for additional detail.');
            }

            $items[] = [
                'section' => $sectionLabel,
                'title' => $title,
                'body' => $body,
                'meta' => $meta,
                'tags' => cv_tags($item),
            ];
        }
    }
    return $items;
}

$userId = (int) ($_GET['userId'] ?? $_GET['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo 'Missing user ID.';
    exit;
}

$portfolioController = new PortfolioController();
$profileController = new ProfileController();
$portfolioData = method_exists($portfolioController, 'getPortfolioData')
    ? (array) ($portfolioController->getPortfolioData($userId) ?? [])
    : [];

$profile = $portfolioData['profile'] ?? $profileController->getByUserId($userId);

if ($profile instanceof Profile) {
    $theme = $profile->getCvTheme();
    $accent = $profile->getCvPrimaryColor();
    $accent2 = $profile->getCvSecondaryColor();
    $bio = trim($profile->getBio());
    $location = trim($profile->getLocation());
    $preferences = trim($profile->getPreferences());
    $careerGoal = trim($profile->getCareerGoal());
    $photoUrl = trim($profile->getPhotoUrl());
    $completionScore = $profile->getCompletionScore();
    $level = $profile->getLevel();
} else {
    $theme = 'graphite';
    $themePalette = ProfileController::themePalette($theme);
    $accent = $themePalette['cvPrimary'];
    $accent2 = $themePalette['cvSecondary'];
    $bio = '';
    $location = '';
    $preferences = '';
    $careerGoal = '';
    $photoUrl = '';
    $completionScore = 0;
    $level = 'Starter';
}

$themePalette = ProfileController::themePalette($theme);
$accent = $accent ?: $themePalette['cvPrimary'];
$accent2 = $accent2 ?: $themePalette['cvSecondary'];
$accentRgb = cv_rgb($accent);
$accent2Rgb = cv_rgb($accent2);

$displayName = cv_field($portfolioData['user'] ?? null, ['fullName', 'name', 'displayName', 'username'], '');
if ($displayName === '') {
    $displayName = 'Curriculum Vitae';
}

$titleLine = $careerGoal !== '' ? $careerGoal : cv_field($portfolioData['headline'] ?? null, ['title', 'headline'], 'Professional profile');
$summary = $bio !== '' ? $bio : cv_field($portfolioData['summary'] ?? null, ['description', 'summary'], 'A concise professional summary appears here once the profile is filled in.');
$skillsSource = array_values(array_filter(array_map('trim', preg_split('/[,|]+/', $preferences) ?: [])));
if (empty($skillsSource)) {
    $skillsSource = ['Communication', 'Problem Solving', 'Frontend', 'Database'];
}

$sections = [
    'Experience' => $portfolioData['experience'] ?? $portfolioData['experiences'] ?? [],
    'Projects' => $portfolioData['projects'] ?? $portfolioData['portfolio'] ?? [],
    'Education' => $portfolioData['education'] ?? $portfolioData['courses'] ?? [],
    'Certificates' => $portfolioData['certificates'] ?? $portfolioData['applications'] ?? [],
];

$experienceItems = cv_build_items($portfolioData, ['Experience' => $sections['Experience']], 'Experience');
$projectItems = cv_build_items($portfolioData, ['Projects' => $sections['Projects']], 'Project');
$educationItems = cv_build_items($portfolioData, ['Education' => $sections['Education'], 'Certificates' => $sections['Certificates']], 'Education');
$downloadMode = isset($_GET['download']) && $_GET['download'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($displayName) ?> - CV</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #15171c;
            --muted: #5d6472;
            --paper: #ffffff;
            --soft: #f4f6f9;
            --line: #d9dee8;
            --accent: <?= htmlspecialchars($accent) ?>;
            --accent-2: <?= htmlspecialchars($accent2) ?>;
            --accent-rgb: <?= htmlspecialchars($accentRgb) ?>;
            --accent-2-rgb: <?= htmlspecialchars($accent2Rgb) ?>;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 0%, rgba(var(--accent-rgb), .12), transparent 24rem),
                radial-gradient(circle at 90% 6%, rgba(var(--accent-2-rgb), .16), transparent 24rem),
                #e8ebef;
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.55;
        }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(1120px, calc(100% - 32px)); margin: 0 auto; padding: 22px 0 36px; }
        .toolbar {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            margin-bottom: 18px;
        }
        .brand { display: flex; align-items: center; gap: 10px; color: var(--muted); font-size: 13px; text-transform: uppercase; letter-spacing: .08em; }
        .brand-mark { width: 38px; height: 38px; border-radius: 8px; display: grid; place-items: center; color: #fff; font-weight: 900; background: linear-gradient(135deg, var(--accent), var(--accent-2)); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 10px 13px; font-weight: 800; font-size: 13px; }
        .btn.primary { background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; border: 0; }

        .cv {
            display: grid;
            grid-template-columns: 310px minmax(0, 1fr);
            min-height: 1120px;
            background: var(--paper);
            border: 1px solid rgba(21, 23, 28, .08);
            box-shadow: 0 24px 70px rgba(21, 23, 28, .16);
        }
        .side {
            background:
                linear-gradient(180deg, rgba(var(--accent-rgb), .94), rgba(var(--accent-rgb), .84)),
                var(--accent);
            color: #fff;
            padding: 28px 24px;
            display: grid;
            align-content: start;
            gap: 22px;
        }
        .avatar { width: 154px; height: 154px; border-radius: 8px; overflow: hidden; border: 4px solid rgba(255,255,255,.34); background: rgba(255,255,255,.14); }
        .avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .avatar-fallback { width: 100%; height: 100%; display: grid; place-items: center; text-align: center; font-weight: 900; font-size: 42px; }
        .side-section { border-top: 1px solid rgba(255,255,255,.24); padding-top: 18px; }
        .side h3 { margin: 0 0 12px; font-size: 13px; text-transform: uppercase; letter-spacing: .12em; }
        .side-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .side-list li { display: grid; gap: 2px; font-size: 14px; }
        .side-list strong { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; opacity: .72; }
        .skill-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill { border: 1px solid rgba(255,255,255,.26); border-radius: 8px; padding: 6px 9px; background: rgba(255,255,255,.1); font-size: 12px; font-weight: 700; }

        .main { padding: 34px 38px; }
        .cv-head {
            padding-bottom: 24px;
            margin-bottom: 26px;
            border-bottom: 3px solid rgba(var(--accent-rgb), .18);
            display: grid;
            gap: 12px;
        }
        .eyebrow { margin: 0; color: var(--accent); font-weight: 900; text-transform: uppercase; letter-spacing: .1em; font-size: 13px; }
        h1 { margin: 0; font-size: clamp(44px, 6vw, 72px); line-height: .94; letter-spacing: 0; }
        .title-line { margin: 0; color: var(--muted); font-size: 18px; max-width: 760px; }
        .summary { margin: 8px 0 0; color: #343a46; max-width: 840px; font-size: 15px; }

        .section { margin-top: 28px; }
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: 13px;
            font-weight: 900;
            color: var(--accent);
        }
        .section-title::after { content: ""; height: 1px; flex: 1; background: var(--line); }
        .entries { display: grid; gap: 14px; }
        .entry {
            display: grid;
            grid-template-columns: 150px minmax(0, 1fr);
            gap: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }
        .entry:last-child { border-bottom: 0; padding-bottom: 0; }
        .meta { color: var(--muted); font-size: 13px; }
        .entry h3 { margin: 0 0 6px; font-size: 18px; }
        .entry p { margin: 0; color: #404858; }
        .tags { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
        .tag { border: 1px solid rgba(var(--accent-rgb), .18); background: rgba(var(--accent-rgb), .07); color: var(--accent); border-radius: 8px; padding: 5px 8px; font-size: 12px; font-weight: 800; }
        .highlight-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .highlight { background: var(--soft); border: 1px solid var(--line); border-radius: 8px; padding: 14px; }
        .highlight strong { display: block; font-size: 18px; color: var(--accent); }
        .highlight span { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }

        .empty { padding: 16px; border: 1px dashed var(--line); background: var(--soft); color: var(--muted); border-radius: 8px; }

        @media (max-width: 900px) {
            .cv { grid-template-columns: 1fr; }
            .side { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .avatar { width: 120px; height: 120px; }
        }
        @media (max-width: 620px) {
            .shell { width: min(100% - 18px, 100%); }
            .toolbar { align-items: stretch; flex-direction: column; }
            .side { grid-template-columns: 1fr; }
            .main { padding: 24px 18px; }
            .entry { grid-template-columns: 1fr; gap: 6px; }
            .highlight-grid { grid-template-columns: 1fr; }
            h1 { font-size: 42px; }
        }
        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff !important; }
            .shell { width: 100%; padding: 0; margin: 0; }
            .toolbar { display: none !important; }
            .cv { min-height: 100vh; border: 0; box-shadow: none; }
            .side { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .entry, .highlight { break-inside: avoid; }
        }
    </style>
    <?php if ($downloadMode): ?>
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    <?php endif; ?>
</head>
<body>
<div class="shell">
    <header class="toolbar">
        <div class="brand">
            <span class="brand-mark"><?= htmlspecialchars(strtoupper(substr($displayName, 0, 1))) ?></span>
            <span><?= htmlspecialchars($displayName) ?> CV</span>
        </div>
        <div class="actions">
            <a class="btn" href="profile.php">Back to profile</a>
            <a class="btn" href="public_portfolio.php?userId=<?= $userId ?>">Open portfolio</a>
            <a class="btn primary" href="?userId=<?= $userId ?>&download=1">Download PDF</a>
        </div>
    </header>

    <article class="cv">
        <aside class="side">
            <div class="avatar">
                <?php if ($photoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($displayName) ?>">
                <?php else: ?>
                    <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr($displayName, 0, 1))) ?></div>
                <?php endif; ?>
            </div>

            <section class="side-section">
                <h3>Contact</h3>
                <ul class="side-list">
                    <li><strong>Name</strong><span><?= htmlspecialchars($displayName) ?></span></li>
                    <li><strong>Location</strong><span><?= htmlspecialchars($location !== '' ? $location : 'Remote') ?></span></li>
                    <li><strong>Level</strong><span><?= htmlspecialchars($level) ?></span></li>
                    <li><strong>Profile Score</strong><span><?= (int) $completionScore ?>%</span></li>
                </ul>
            </section>

            <section class="side-section">
                <h3>Core Skills</h3>
                <div class="skill-list">
                    <?php foreach (array_slice($skillsSource, 0, 12) as $skill): ?>
                        <span class="skill"><?= htmlspecialchars($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </section>

        </aside>

        <main class="main">
            <header class="cv-head">
                <p class="eyebrow">Professional CV</p>
                <h1><?= htmlspecialchars($displayName) ?></h1>
                <p class="title-line"><?= htmlspecialchars($titleLine) ?></p>
                <p class="summary"><?= htmlspecialchars($summary) ?></p>
            </header>

            <section class="highlight-grid">
                <div class="highlight"><strong><?= (int) $completionScore ?>%</strong><span>Profile score</span></div>
                <div class="highlight"><strong><?= htmlspecialchars($level) ?></strong><span>Career level</span></div>
                <div class="highlight"><strong><?= htmlspecialchars($location !== '' ? $location : 'Remote') ?></strong><span>Location</span></div>
            </section>

            <section class="section">
                <div class="section-title">Experience</div>
                <?php if (!empty($experienceItems)): ?>
                    <div class="entries">
                        <?php foreach (array_slice($experienceItems, 0, 5) as $item): ?>
                            <article class="entry">
                                <div class="meta"><?= htmlspecialchars($item['meta'] ?: $item['section']) ?></div>
                                <div>
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(cv_short($item['body'], 230)) ?></p>
                                    <?php if (!empty($item['tags'])): ?>
                                        <div class="tags">
                                            <?php foreach (array_slice($item['tags'], 0, 5) as $tag): ?>
                                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">Add experience data to fill this section.</div>
                <?php endif; ?>
            </section>

            <section class="section">
                <div class="section-title">Selected Projects</div>
                <?php if (!empty($projectItems)): ?>
                    <div class="entries">
                        <?php foreach (array_slice($projectItems, 0, 4) as $item): ?>
                            <article class="entry">
                                <div class="meta"><?= htmlspecialchars($item['meta'] ?: 'Project') ?></div>
                                <div>
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(cv_short($item['body'], 210)) ?></p>
                                    <?php if (!empty($item['tags'])): ?>
                                        <div class="tags">
                                            <?php foreach (array_slice($item['tags'], 0, 5) as $tag): ?>
                                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">Project entries will appear here when portfolio data is added.</div>
                <?php endif; ?>
            </section>

            <section class="section">
                <div class="section-title">Education & Certifications</div>
                <?php if (!empty($educationItems)): ?>
                    <div class="entries">
                        <?php foreach (array_slice($educationItems, 0, 5) as $item): ?>
                            <article class="entry">
                                <div class="meta"><?= htmlspecialchars($item['meta'] ?: $item['section']) ?></div>
                                <div>
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(cv_short($item['body'], 190)) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">Education and certificate entries will appear here.</div>
                <?php endif; ?>
            </section>
        </main>
    </article>
</div>
</body>
</html>
