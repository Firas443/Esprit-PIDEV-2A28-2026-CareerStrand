<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Opportunity.php';

try {
    $db          = (new Database())->connect();
    $opportunity = new Opportunity($db);

    $filters = [
        'status'        => $_GET['status']        ?? '',
        'category'      => $_GET['category']       ?? '',
        'requiredLevel' => $_GET['requiredLevel']  ?? '',
        'search'        => $_GET['search']         ?? '',
    ];

    $data = $opportunity->getAll($filters);

    echo json_encode([
        'success' => true,
        'count'   => count($data),
        'data'    => $data,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}