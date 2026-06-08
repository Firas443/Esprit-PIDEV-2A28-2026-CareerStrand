<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Controller/UserController.php';
require_once __DIR__ . '/../Controller/ProfileController.php';
require_once __DIR__ . '/../Controller/RoleProfileController.php';
require_once __DIR__ . '/../utils/AiProfileService.php';
require_once __DIR__ . '/../utils/FrontOfficeAuth.php';

function assistantTableExists(PDO $pdo, string $table): bool
{
    try {
        $query = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $query->execute([$table]);
        return (int) $query->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function assistantFetchRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $query = $pdo->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function assistantText(string $value, int $limit = 180): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return '';
    }

    return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
}

$frontUser = currentFrontUser();
if ($frontUser === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be signed in to use the assistant.']);
    exit;
}

$userId = (int) ($frontUser['userId'] ?? 0);
$message = '';
$history = [];

$payload = json_decode((string) file_get_contents('php://input'), true);
if (is_array($payload)) {
    $message = trim((string) ($payload['message'] ?? ''));
    $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];
} else {
    $message = trim((string) ($_POST['message'] ?? ''));
}

if ($message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please type a question for the assistant.']);
    exit;
}

$pdo = config::getConnexion();
$userController = new UserController();
$profileController = new ProfileController();
$roleController = new RoleProfileController();
$ai = new AiProfileService();

$user = $userController->getById($userId);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session is no longer valid.']);
    exit;
}

$profile = $profileController->getByUserId($userId);
$skills = $profileController->getSkillsByUserId($userId);
$managerProfile = null;
$recruiterProfile = null;
if (method_exists($roleController, 'getManagerProfile')) {
    $managerProfile = $roleController->getManagerProfile($userId);
}
if (method_exists($roleController, 'getRecruiterProfile')) {
    $recruiterProfile = $roleController->getRecruiterProfile($userId);
}

$learningRows = assistantFetchRows(
    $pdo,
    "SELECT c.progress, c.status, co.title, co.category, co.difficulty
     FROM Calendar c
     LEFT JOIN Course co ON co.courseId = c.courseId
     WHERE c.userId = :userId
     ORDER BY c.startDate DESC, c.calendarId DESC
     LIMIT 4",
    ['userId' => $userId]
);

$applicationRows = assistantFetchRows(
    $pdo,
    "SELECT a.status, a.compatibilityScore, o.title, o.type, o.requiredLevel
     FROM Application a
     LEFT JOIN Opportunity o ON o.opportunityId = a.opportunityId
     WHERE a.userId = :userId
     ORDER BY a.appliedAt DESC, a.applicationId DESC
     LIMIT 4",
    ['userId' => $userId]
);

$opportunityRows = assistantFetchRows(
    $pdo,
    "SELECT title, type, category, requiredLevel, deadline
     FROM Opportunity
     WHERE LOWER(COALESCE(status, '')) IN ('published', 'active', 'open')
     ORDER BY deadline IS NULL ASC, deadline ASC, opportunityId DESC
     LIMIT 4"
);

$hubRows = assistantFetchRows(
    $pdo,
    "SELECT sh.name, sh.category
     FROM GroupMember gm
     INNER JOIN SkillHub sh ON sh.groupId = gm.groupId
     WHERE gm.userId = :userId
     ORDER BY gm.joinedAt DESC, gm.groupMemberId DESC
     LIMIT 4",
    ['userId' => $userId]
);

$eventRows = assistantFetchRows(
    $pdo,
    "SELECT title, eventMode, date, location
     FROM Event
     WHERE date IS NULL OR date >= CURDATE()
     ORDER BY date IS NULL ASC, date ASC, eventId DESC
     LIMIT 3"
);

$profileScore = (int) ($profile?->getCompletionScore() ?? 0);
$profileLevel = trim((string) ($profile?->getLevel() ?? 'Starter'));

$learningSummary = [];
foreach ($learningRows as $row) {
    $learningSummary[] = assistantText(
        trim((string) (($row['title'] ?? 'Course') . ' / ' . ($row['category'] ?? 'Learning') . ' / ' . ($row['progress'] ?? 0) . '% progress')),
        180
    );
}

$applicationSummary = [];
foreach ($applicationRows as $row) {
    $applicationSummary[] = assistantText(
        trim((string) (($row['title'] ?? 'Opportunity') . ' / ' . ($row['type'] ?? 'Role') . ' / ' . ($row['compatibilityScore'] ?? 0) . '% compatibility')),
        180
    );
}

$opportunitySummary = [];
foreach ($opportunityRows as $row) {
    $opportunitySummary[] = assistantText(
        trim((string) (($row['title'] ?? 'Opportunity') . ' / ' . ($row['type'] ?? 'Role') . ' / ' . ($row['requiredLevel'] ?? 'Open level'))),
        180
    );
}

$hubSummary = [];
foreach ($hubRows as $row) {
    $hubSummary[] = assistantText(
        trim((string) (($row['name'] ?? 'Skill Hub') . ' / ' . ($row['category'] ?? 'Community'))),
        180
    );
}

$eventSummary = [];
foreach ($eventRows as $row) {
    $eventSummary[] = assistantText(
        trim((string) (($row['title'] ?? 'Event') . ' / ' . ($row['eventMode'] ?? 'Event') . ' / ' . ($row['date'] ?? 'Date TBA'))),
        180
    );
}

$dashboard = [
    'adnScore' => (int) round(
        ($profileScore * 0.28)
        + (min(5, count($skills)) * 8)
        + (min(4, count($learningRows)) * 6)
        + (min(4, count($applicationRows)) * 6)
        + (min(4, count($hubRows)) * 6)
    ),
    'profileStrength' => min(100, $profileScore),
    'learningStrength' => min(100, (int) round(array_sum(array_map(static function (array $row): float {
        return is_numeric($row['progress'] ?? null) ? (float) $row['progress'] : 0.0;
    }, $learningRows)) / max(1, count($learningRows)))),
    'communityStrength' => min(100, (count($hubRows) * 18) + (assistantTableExists($pdo, 'Post') ? (int) min(20, max(0, (int) $pdo->query('SELECT COUNT(*) FROM Post WHERE userId = ' . $userId)->fetchColumn())) : 0)),
    'opportunityReadiness' => min(100, (int) round(array_sum(array_map(static function (array $row): float {
        return is_numeric($row['compatibilityScore'] ?? null) ? (float) $row['compatibilityScore'] : 0.0;
    }, $applicationRows)) / max(1, count($applicationRows)))),
    'engagementLevel' => min(100, (count($eventRows) * 14) + (count($applicationRows) * 10) + (count($learningRows) * 8) + (count($hubRows) * 6)),
];

$assistantContext = [
    'fullName' => (string) ($frontUser['fullName'] ?? 'CareerStrand user'),
    'role' => (string) ($frontUser['role'] ?? 'user'),
    'profile' => [
        'completionScore' => $profileScore,
        'level' => $profileLevel,
        'bio' => (string) ($profile?->getBio() ?? ''),
        'location' => (string) ($profile?->getLocation() ?? ''),
        'preferences' => (string) ($profile?->getPreferences() ?? ''),
        'careerGoal' => (string) ($profile?->getCareerGoal() ?? ''),
    ],
    'skills' => array_map(static fn($skill) => trim((string) ($skill->getSkillName() ?? '')), $skills),
    'learning' => $learningSummary,
    'applications' => $applicationSummary,
    'opportunities' => $opportunitySummary,
    'hubs' => $hubSummary,
    'events' => $eventSummary,
    'dashboard' => $dashboard,
    'roleProfile' => [
        'manager' => $managerProfile ? trim((string) ($managerProfile->getOrganization() ?? '')) : '',
        'recruiter' => $recruiterProfile ? trim((string) ($recruiterProfile->getCompanyName() ?? '')) : '',
    ],
];

$result = $ai->generateCareerAssistantReply($assistantContext, $message, $history);

echo json_encode([
    'success' => (bool) ($result['ok'] ?? false),
    'reply' => (string) ($result['reply'] ?? ''),
    'source' => (string) ($result['source'] ?? 'fallback'),
    'quickPrompts' => [
        'Which course should I take next?',
        'Which opportunity fits me best?',
        'How can I improve my profile?',
        'Help me write a motivation letter.',
        'Explain my dashboard data in simple language.',
    ],
]);
