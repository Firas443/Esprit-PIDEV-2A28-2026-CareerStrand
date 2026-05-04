<?php
/**
 * delete_participation.php  (BackOffice)
 * GET: id
 */
require_once __DIR__ . '/../../Controller/ParticipationController.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ID manquant.']);
    exit;
}

$pc = new ParticipationController();
try {
    $pc->deleteParticipation($id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
