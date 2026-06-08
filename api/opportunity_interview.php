<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Controller/ProfileController.php';
require_once __DIR__ . '/../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../utils/OpportunityInterviewService.php';

function interviewFetchOpportunity(PDO $pdo, int $opportunityId): array|false
{
    $stmt = $pdo->prepare(
        "SELECT o.*, u.fullName AS managerName
         FROM Opportunity o
         LEFT JOIN Users u ON u.userId = o.managerId
         WHERE o.opportunityId = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $opportunityId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $skillStmt = $pdo->prepare(
        "SELECT skillName, requiredLevel, weight, isPrimary
         FROM opportunity_skill
         WHERE opportunityId = :id
         ORDER BY isPrimary DESC, weight DESC, id ASC"
    );
    $skillStmt->execute(['id' => $opportunityId]);
    $row['skills'] = $skillStmt->fetchAll() ?: [];

    return $row;
}

$frontUser = currentFrontUser();
if ($frontUser === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be signed in to start an interview simulation.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string) ($payload['action'] ?? 'start'));
$opportunityId = (int) ($payload['opportunityId'] ?? 0);

if ($opportunityId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid opportunity ID is required.']);
    exit;
}

$pdo = config::getConnexion();
$profileController = new ProfileController();
$service = new OpportunityInterviewService();
$userId = (int) ($frontUser['userId'] ?? 0);

$opportunity = interviewFetchOpportunity($pdo, $opportunityId);
if (!$opportunity) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Opportunity not found.']);
    exit;
}

$allowedStatuses = ['published', 'active', 'open'];
$status = strtolower(trim((string) ($opportunity['status'] ?? '')));
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This opportunity is not available for interview practice right now.']);
    exit;
}

$profile = $profileController->getByUserId($userId);
$skills = $profileController->getSkillsByUserId($userId);
$profileData = [
    'bio' => trim((string) ($profile?->getBio() ?? '')),
    'location' => trim((string) ($profile?->getLocation() ?? '')),
    'preferences' => trim((string) ($profile?->getPreferences() ?? '')),
    'careerGoal' => trim((string) ($profile?->getCareerGoal() ?? '')),
    'level' => trim((string) ($profile?->getLevel() ?? 'Starter')),
];

if ($action === 'evaluate') {
    $questions = is_array($payload['questions'] ?? null) ? $payload['questions'] : [];
    $answers   = is_array($payload['answers']   ?? null) ? $payload['answers']   : [];

    $result = $service->evaluateInterview($opportunity, $profileData, $skills, $questions, $answers);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 'start' action — generate all technical questions upfront
$result = $service->generateTechnicalQuestions($opportunity, $profileData, $skills);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
