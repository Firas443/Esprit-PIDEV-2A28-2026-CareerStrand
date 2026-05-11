<?php
/**
 * update_participation.php  (BackOffice)
 * POST: attendanceStatus, status, rating, feedback
 * GET param: id
 */
require_once __DIR__ . '/../../Controller/ParticipationController.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ID manquant.']);
    exit;
}

$pc = new ParticipationController();
$p  = $pc->getById($id);
if (!$p) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Participation introuvable.']);
    exit;
}

$attendance = trim($_POST['attendanceStatus'] ?? $p->getAttendanceStatus());
$status     = trim($_POST['status']           ?? $p->getStatus());
$ratingRaw  = $_POST['rating'] ?? '';
$rating     = ($ratingRaw !== '' && $ratingRaw !== null) ? (int)$ratingRaw : $p->getRating();
$feedback   = trim($_POST['feedback'] ?? ($p->getFeedback() ?? ''));

// Keep the existing feedback if it was a name|email store and new feedback is empty
$existingFb = $p->getFeedback() ?? '';
if (empty($feedback) && strpos($existingFb, '|') !== false) {
    $feedback = $existingFb; // preserve name|email for lookup
}

$p->setAttendanceStatus($attendance);
$p->setStatus($status);
$p->setRating($rating);
$p->setFeedback($feedback);

try {
    $pc->updateParticipation($id, $p);
    echo json_encode(['success' => true, 'message' => 'Participation mise à jour.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
