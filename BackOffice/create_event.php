<?php
require_once __DIR__ . '/../../Controller/EventsController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json');

$eventC = new EventsController();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$eventIdRaw  = trim($_POST['eventId']     ?? '');
$name        = trim($_POST['name']         ?? '');
$description = trim($_POST['description']  ?? '');
$type        = trim($_POST['type']         ?? '');
$location    = trim($_POST['location']     ?? 'TBD');
$capacity    = isset($_POST['capacity'])   ? (int)$_POST['capacity'] : 0;
$date        = trim($_POST['date']         ?? '');
$status      = trim($_POST['status']       ?? 'Upcoming');
$managerId   = !empty($_POST['managerId']) ? (int)$_POST['managerId'] : null;
$createdAt   = trim($_POST['createdAt']    ?? '');
$tags        = trim($_POST['tags']         ?? '');
$organiser   = trim($_POST['organiser']    ?? '');

if (empty($name) || empty($type) || empty($date)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Titre, Type et Date sont obligatoires.']);
    exit;
}

try {
    $event = new Event(
        $name, $description, $type, $location,
        $capacity, $date, $status,
        $createdAt ?: date('Y-m-d'),
        $managerId,
        $tags,
        $organiser
    );

    if ($eventIdRaw !== '') {
        $event->setEventId((int)$eventIdRaw);
    }

    $eventC->addEvent($event);
    echo json_encode(['success' => true, 'message' => 'Événement créé avec succès.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
