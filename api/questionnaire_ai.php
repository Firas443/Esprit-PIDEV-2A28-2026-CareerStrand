<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../Controller/UserController.php';
require_once __DIR__ . '/../utils/AiProfileService.php';

$userId = $_SESSION['userId'] ?? $_SESSION['temp_user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}

$userController = new UserController();
$userObj = $userController->getById((int) $userId);
if (!$userObj) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User session is no longer valid.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string) ($payload['action'] ?? ''));
$answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];

try {
    $ai = new AiProfileService();

    if ($action === 'questions') {
        foreach (['field', 'experience', 'skills', 'workStyle', 'goal'] as $required) {
            if (trim((string) ($answers[$required] ?? '')) === '') {
                throw new InvalidArgumentException('Please answer the first five questions before generating AI questions.');
            }
        }

        $questions = $ai->generateQuestions([
            'fullName' => $userObj->getFullName(),
            'role' => $userObj->getRole(),
            'field' => trim((string) $answers['field']),
            'experience' => trim((string) $answers['experience']),
            'skills' => trim((string) $answers['skills']),
            'workStyle' => trim((string) $answers['workStyle']),
            'goal' => trim((string) $answers['goal']),
        ]);

        echo json_encode(['success' => true, 'questions' => $questions]);
        exit;
    }

    if ($action === 'bio') {
        foreach (['field', 'experience', 'skills', 'workStyle', 'goal', 'aiAnswer1', 'aiAnswer2', 'aiAnswer3'] as $required) {
            if (trim((string) ($answers[$required] ?? '')) === '') {
                throw new InvalidArgumentException('Please answer every question before generating the bio.');
            }
        }

        $bio = $ai->generateBio($answers, $userObj->getRole(), $userObj->getFullName());
        echo json_encode(['success' => true, 'bio' => $bio]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown AI action.']);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>