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

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../Model/Application.php';

try {
    $db          = (new Database())->connect();
    $application = new Application($db);
    $source      = $_GET['source'] ?? 'front';

    if ($source === 'back') {
        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
        $data   = $application->getWithFilters($filters);
        $counts = $application->getStatusCounts();
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $data, 'counts' => $counts]);
    } else {
        $userId = isset($_GET['userId']) ? (int)$_GET['userId'] : 1;
        $data   = $application->getByUserId($userId);
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $data]);
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}