<?php

ob_start();
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): void {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Assessment service error. Please try again.',
    ]);
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    if (ob_get_length() !== false) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Assessment service stopped unexpectedly. Please try again.',
    ]);
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../utils/SkillAssessmentService.php';

$frontUser = currentFrontUser();
if ($frontUser === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be signed in to take a skill assessment.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action    = trim((string) ($payload['action']    ?? 'start'));
$skillName = trim((string) ($payload['skillName'] ?? ''));

if ($skillName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Skill name is required.']);
    exit;
}

$service = new SkillAssessmentService();

if ($action === 'evaluate') {
    $questions = is_array($payload['questions'] ?? null) ? $payload['questions'] : [];
    $answers   = is_array($payload['answers']   ?? null) ? $payload['answers']   : [];

    $result = $service->evaluateAnswers($skillName, $questions, $answers);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 'start' action — generate questions
$result = $service->generateQuestions($skillName);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
