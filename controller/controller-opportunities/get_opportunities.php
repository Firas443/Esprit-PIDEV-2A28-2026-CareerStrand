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
require_once __DIR__ . '/../../Model/Opportunity.php';

try {
    $db          = (new Database())->connect();
    $opportunity = new Opportunity($db);

    $filters = [];
    if (!empty($_GET['status']))        $filters['status']        = $_GET['status'];
    if (!empty($_GET['category']))      $filters['category']      = $_GET['category'];
    if (!empty($_GET['requiredLevel'])) $filters['requiredLevel'] = $_GET['requiredLevel'];
    if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];

    // BackOffice gets all, FrontOffice only gets published
    $source = $_GET['source'] ?? 'front';
    $data   = $source === 'back'
                ? $opportunity->getAll($filters)
                : $opportunity->getPublished($filters);

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}