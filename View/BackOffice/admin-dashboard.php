<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../utils/AuthRedirect.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}

$user = $_SESSION['user'];

if (!isBackOfficeRole($user['role'] ?? '')) {
    header('Location: ../FrontOffice/profile.php');
    exit;
}

$controller = new UserController();
$stats      = $controller->getStats();

include __DIR__ . '/assets/html/admin-dashboard.html';
