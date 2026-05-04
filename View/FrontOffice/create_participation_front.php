<?php
/**
 * create_participation_front.php
 * POST: eventId, userId, userName, userEmail, registrationDate
 * Inserts a Pending participation linked to real userId.
 */
require_once __DIR__ . '/../../Controller/ParticipationController.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$eventId   = isset($_POST['eventId'])  ? (int)$_POST['eventId']  : 0;
$userId    = isset($_POST['userId'])   ? (int)$_POST['userId']   : 0;
$userName  = trim($_POST['userName']  ?? '');
$userEmail = trim($_POST['userEmail'] ?? '');
$regDate   = trim($_POST['registrationDate'] ?? date('Y-m-d'));

if ($eventId <= 0 || $userId <= 0 || empty($userName) || empty($userEmail)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Données manquantes.']);
    exit;
}

$pc = new ParticipationController();

// Check duplicate by userId + eventId
if ($pc->alreadyRegistered($userId, $eventId)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Vous êtes déjà inscrit à cet événement.']);
    exit;
}

try {
    $pc->addParticipationFront($eventId, $userId, $userName, $userEmail, $regDate);
    echo json_encode(['success' => true, 'message' => 'Demande envoyée. En attente de confirmation.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}