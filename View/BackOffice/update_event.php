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

// Récupérer l'ID depuis GET ou POST
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['eventId']) ? (int)$_POST['eventId'] : 0);

if ($id <= 0) {
    http_response_code(400); //400 = Bad Request
    echo json_encode(['success' => false, 'error' => 'ID manquant ou invalide.']);
    exit;
}
// Vérifie que l'event existe
$event = $eventC->getEventById($id);
if (!$event) {
    http_response_code(404); // 404 = Pas trouvé
    echo json_encode(['success' => false, 'error' => 'Événement introuvable.']);
    exit;
}

$name        = trim($_POST['name']         ?? '');
$description = trim($_POST['description']  ?? '');
$type        = trim($_POST['type']         ?? '');
$location    = trim($_POST['location']     ?? 'TBD');
$capacity    = isset($_POST['capacity'])   ? (int)$_POST['capacity'] : 0;
$date        = trim($_POST['date']         ?? '');
$status      = trim($_POST['status']       ?? 'Upcoming');
$managerId   = !empty($_POST['managerId']) ? (int)$_POST['managerId'] : null;
$tags        = trim($_POST['tags']         ?? '');
$organiser   = trim($_POST['organiser']    ?? '');
$time        = trim($_POST['time']         ?? '');
$eventMode   = trim($_POST['eventMode'] ?? 'Online');
$sponsorId   = !empty($_POST['sponsorId']) ? (int)$_POST['sponsorId'] : null;
$duration    = isset($_POST['duration'])   ? (int)$_POST['duration']  : 0;

if (empty($name) || empty($type) || empty($date)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Titre, Type et Date sont obligatoires.']);
    exit;
}

try {
   $updatedEvent = new Event(
        $name, $description, $type, $location,
        $capacity, $date, $status,
        $managerId,
        $tags,
        $organiser,
        $time,
        $eventMode,
        $sponsorId,
        $duration
    );

    $eventC->updateEvent($id, $updatedEvent);
    echo json_encode(['success' => true, 'message' => 'Événement mis à jour avec succès.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
