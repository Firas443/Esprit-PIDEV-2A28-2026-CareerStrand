<?php
require_once __DIR__ . '/../../Controller/EventFormController.php';

header('Content-Type: application/json');

$formC = new EventFormController();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$eventId     = isset($_POST['eventId']) ? (int)$_POST['eventId'] : 0;
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$formLink    = trim($_POST['formLink'] ?? '');
$status      = trim($_POST['status'] ?? 'open');

if (empty($title) || $eventId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Titre et Event ID sont obligatoires.']);
    exit;
}

try {
    $form = new EventForm($eventId, $title, $description, $formLink, $status);
    $formC->addForm($form);
    echo json_encode(['success' => true, 'message' => 'Formulaire créé avec succès.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}