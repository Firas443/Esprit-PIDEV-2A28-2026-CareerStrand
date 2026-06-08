<?php
/**
 * verify_user.php
 * POST: email, name
 * Returns JSON with user info if found, error if not.
 */
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$name  = trim($_POST['name']  ?? '');

if (empty($email) || empty($name)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Email et nom obligatoires.']);
    exit;
}

try {
    $db  = config::getConnexion();
    $sql = "SELECT userId, fullName, email FROM Users WHERE email = :email AND fullName = :name LIMIT 1";
    $req = $db->prepare($sql);
    $req->execute([':email' => $email, ':name' => $name]);
    $row = $req->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable. Vérifiez votre nom complet et email.']);
        exit;
    }

    echo json_encode([
        'success'  => true,
        'userId'   => (int)$row['userId'],
        'fullName' => $row['fullName'],
        'email'    => $row['email'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur.']);
}
