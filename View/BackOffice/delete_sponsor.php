<?php
require_once __DIR__ . '/../../Controller/SponsorController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json');

$sc = new SponsorController();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID manquant ou invalide.']);
    exit;
}

$existing = $sc->getById($id);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sponsor introuvable.']);
    exit;
}

try {
    $sc->deleteSponsor($id);
    echo json_encode(['success' => true, 'message' => 'Sponsor supprimé avec succès.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
