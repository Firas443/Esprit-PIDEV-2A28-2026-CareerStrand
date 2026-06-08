<?php
/**
 * cancel_participation_front.php
 * POST: participationId or (eventId + userId)
 * Marks the signed-in user's participation/request as Cancelled.
 */
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode non autorisee.']);
    exit;
}

$participationId = isset($_POST['participationId']) ? (int)$_POST['participationId'] : 0;
$eventId         = isset($_POST['eventId']) ? (int)$_POST['eventId'] : 0;
$userId          = isset($_POST['userId']) ? (int)$_POST['userId'] : 0;

if ($userId <= 0 || ($participationId <= 0 && $eventId <= 0)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Donnees manquantes.']);
    exit;
}

try {
    $db = config::getConnexion();

    if ($participationId > 0) {
        $sql = "UPDATE participation
                SET attendanceStatus = 'Cancelled', status = 'Cancelled'
                WHERE participationId = :pid AND userId = :uid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':pid' => $participationId, ':uid' => $userId]);
    } else {
        $sql = "UPDATE participation
                SET attendanceStatus = 'Cancelled', status = 'Cancelled'
                WHERE userId = :uid AND eventId = :eid AND attendanceStatus != 'Cancelled'
                ORDER BY registrationDate DESC, participationId DESC
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $userId, ':eid' => $eventId]);
    }

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Participation introuvable.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Participation annulee.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
