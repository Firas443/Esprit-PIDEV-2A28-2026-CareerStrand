<?php
/**
 * submit_feedback_front.php
 * POST: participationId, rating (0-5), comment
 * Updates feedback + rating for a participation.
 */
require_once __DIR__ . '/../../Controller/ParticipationController.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$participationId = isset($_POST['participationId']) ? (int)$_POST['participationId'] : 0;
$rating          = isset($_POST['rating'])          ? (int)$_POST['rating']          : 0;
$comment         = trim($_POST['comment'] ?? '');

if ($participationId <= 0 || $rating < 0 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Données invalides (participationId ou rating manquant).']);
    exit;
}

$pc = new ParticipationController();
try {
    $pc->updateFeedback($participationId, $rating, $comment);
    echo json_encode(['success' => true, 'message' => 'Feedback enregistré.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
