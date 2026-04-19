<?php
ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "$errstr in $errfile line $errline"]);
    exit;
});
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../Model/Application.php';

try {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body)) {
        ob_end_clean(); http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Request body is empty.']);
        exit;
    }

    $db          = (new Database())->connect();
    $application = new Application($db);
    $result      = $application->create($body);

    http_response_code($result['success'] ? 201 : 422);
    ob_end_clean();
    echo json_encode($result);
} catch (Exception $e) {
    ob_end_clean(); http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}