<?php
ader('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use PUT.']);
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

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body) {
        $body = $_POST;
    }

    if (empty($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Request body is empty.']);
        exit;
    }

    $db          = (new Database())->connect();
    $opportunity = new Opportunity($db);

    $result = $opportunity->update($id, $body);

    if (!$result['success']) {
        http_response_code(isset($result['errors']) ? 422 : 404);
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}