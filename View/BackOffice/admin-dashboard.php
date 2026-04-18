<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}

$user = $_SESSION['user'];

if ($user['role'] !== 'admin') {
    header('Location: ../FrontOffice/profile.php');
    exit;
}

$controller = new UserController();
$stats      = $controller->getStats();

include __DIR__ . '/assets/html/admin-dashboard.html';
