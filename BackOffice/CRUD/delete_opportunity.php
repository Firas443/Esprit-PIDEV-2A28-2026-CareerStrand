<?php
// api/delete_opportunity.php
// DELETE — removes an opportunity and its linked applications
// Query param: id

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use DELETE.']);
    exit;
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Opportunity.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid opportunity ID is required (?id=X).']);
        exit;
    }

    $db          = (new Database())->connect();
    $opportunity = new Opportunity($db);

    $result = $opportunity->delete($id);

    if (!$result['success']) {
        http_response_code(404);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}