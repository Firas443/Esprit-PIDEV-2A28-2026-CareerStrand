<?php
/**
 * get_my_participations.php
 * GET: userId  (integer)
 * Returns participations for this user with event title.
 */
require_once __DIR__ . '/../../Controller/ParticipationController.php';

header('Content-Type: application/json');

$userId = isset($_GET['userId']) ? (int)$_GET['userId'] : 0;
if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

$pc   = new ParticipationController();
$list = $pc->getByUser($userId);

$data = array_map(function($p) {
    // feedback column may still be "userName|email" for old rows; strip it for clean feedback text
    $raw   = $p->getFeedback() ?? '';
    $parts = explode('|', $raw, 2);
    // If it looks like a name|email pair (2 parts, 2nd has @), treat as legacy and set feedbackText to ''
    $feedbackText = (count($parts) === 2 && strpos($parts[1], '@') !== false) ? '' : $raw;

    return [
        'participationId'  => $p->getParticipationId(),
        'eventId'          => $p->getEventId(),
        'registrationDate' => $p->getRegistrationDate(),
        'attendanceStatus' => $p->getAttendanceStatus(),
        'status'           => $p->getAttendanceStatus(), // alias
        'rating'           => $p->getRating(),
        'feedback'         => $feedbackText,
    ];
}, $list);

echo json_encode($data);