<?php
require_once __DIR__ . '/../../Controller/EventsController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json'); // Indique au navigateur que la réponse est du JSON (pas du HTML)

$eventC = new EventsController();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { //n'accepte QUE les requêtes POST
    http_response_code(405); // 405 = Méthode non autorisée
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
$tags        = trim($_POST['tags']         ?? '');
$organiser   = trim($_POST['organiser']    ?? '');
$time        = trim($_POST['time']         ?? '');
$eventMode   = trim($_POST['eventMode'] ?? 'Online');
$sponsorId   = !empty($_POST['sponsorId']) ? (int)$_POST['sponsorId'] : null;
$duration    = isset($_POST['duration'])   ? (int)$_POST['duration']  : 0;

if (empty($name) || empty($type) || empty($date)) {  // Validation côté serveur
    http_response_code(422);// 422 = Données invalides
    echo json_encode(['success' => false, 'error' => 'Titre, Type et Date sont obligatoires.']);
    exit;
}

try {
    $event = new Event(
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

    if ($eventIdRaw !== '') {
        $event->setEventId((int)$eventIdRaw);
    }

    $eventC->addEvent($event); // Appelle le Controller qui fait l'INSERT en BDD
    echo json_encode(['success' => true, 'message' => 'Événement créé avec succès.']); // Retourne la réponse JSON au JavaScript
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
