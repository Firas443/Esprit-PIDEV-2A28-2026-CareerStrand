<?php
require_once __DIR__ . '/../../Controller/EventFormController.php';

header('Content-Type: application/json');

$formC = new EventFormController();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID manquant ou invalide.']);
    exit;
}

$existing = $formC->getById($id);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Formulaire introuvable.']);
    exit;
}

try {
    $formC->deleteForm($id);
    echo json_encode(['success' => true, 'message' => 'Formulaire supprimé.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}