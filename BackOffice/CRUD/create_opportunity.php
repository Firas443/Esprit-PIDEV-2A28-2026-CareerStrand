<?php
ob_start(); // Must be FIRST line, before anything else

// Catch ALL PHP errors/warnings and return them as JSON instead of HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Opportunity.php';

try {
    $input = file_get_contents('php://input');
    $body  = json_decode($input, true);

    if (empty($body)) {
        $body = $_POST;
    }

    if (empty($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Request body is empty.']);
        exit;
    }

    $db          = (new Database())->connect();
    $opportunity = new Opportunity($db);
    $result      = $opportunity->create($body);

    http_response_code($result['success'] ? 201 : 422);
    ob_end_clean();
    echo json_encode($result);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}