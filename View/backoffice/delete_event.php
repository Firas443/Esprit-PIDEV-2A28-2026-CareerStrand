<?php
require_once __DIR__ . '/../../Controller/EventsController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json');

$eventC = new EventsController();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID manquant ou invalide.']);
    exit;
}

$event = $eventC->getEventById($id);
if (!$event) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Événement introuvable.']);
    exit;
}

try {
    $eventC->deleteEvent($id);
    echo json_encode(['success' => true, 'message' => 'Événement supprimé.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
