<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Paths
require_once __DIR__ . '/../../BackOffice/config/Database.php';
require_once __DIR__ . '/../../BackOffice/classes/Opportunity.php';

try {
    $db          = (new Database())->connect();
    $opportunity = new Opportunity($db);

    $filters = [
        'category'      => $_GET['category']      ?? '',
        'requiredLevel' => $_GET['requiredLevel']  ?? '',
        'search'        => $_GET['search']         ?? '',
    ];

    // only show published status
    $data = $opportunity->getPublished($filters);

    echo json_encode([
        'success' => true,
        'count'   => count($data),
        'data'    => $data,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}