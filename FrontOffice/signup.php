<?php
require_once 'config.php';
require_once 'controllers/UserController.php';

$controller = new UserController($pdo);

$action = $_GET['action'] ?? 'signup';

if ($action === 'signup') {
    $controller->signup();
} elseif ($action === 'store') {
    $controller->store();
}
?>