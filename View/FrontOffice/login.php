<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';

session_start();

if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    header($user['role'] === 'admin'
        ? 'Location: ../BackOffice/admin-dashboard.php'
        : 'Location: profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new UserController();
    $user       = $controller->authenticate(
        trim($_POST['email']    ?? ''),
        $_POST['password'] ?? ''
    );

    if ($user) {
        $_SESSION['userId'] = $user['userId'];
        $_SESSION['user']   = $user;
        header($user['role'] === 'admin'
            ? 'Location: ../BackOffice/admin-dashboard.php'
            : 'Location: profile.php');
        exit;
    }

    $errors[] = 'Incorrect email or password.';
}

include __DIR__ . '/assets/html/login.html';
