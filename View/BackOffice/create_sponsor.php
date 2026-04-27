<?php
require_once __DIR__ . '/../../Controller/SponsorController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json');

$sc = new SponsorController();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$name         = trim($_POST['name']           ?? '');
$company      = trim($_POST['company']        ?? '');
$email        = trim($_POST['email']          ?? '');
$contribution = trim($_POST['contribution']   ?? '');

$amount       = isset($_POST['amount'])       ? (float)$_POST['amount']       : 0.0;

if (empty($name) || empty($company) || empty($email)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Nom, société et email sont obligatoires.']);
    exit;
}

try {
    $userId  = !empty($_POST['userId']) ? (int)$_POST['userId'] : null;
    $sponsor = new Sponsor( $name, $company, $email, $contribution, $amount, $userId);
    $sponsor->setUserId($userId);
    $sc->addSponsor($sponsor);
    echo json_encode(['success' => true, 'message' => 'Sponsor ajouté avec succès.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
