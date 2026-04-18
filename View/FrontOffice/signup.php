<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';

session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new UserController();
    $fullName   = trim(($_POST['firstName'] ?? '') . ' ' . ($_POST['lastName'] ?? ''));
    $result     = $controller->createUser([
        'fullName' => $fullName,
        'email'    => trim($_POST['email']    ?? ''),
        'password' => $_POST['password'] ?? '',
        'role'     => $_POST['role'] ?? 'user',
    ]);

    if ($result['success']) {
        $user = (new UserController())->getById($result['userId']);
        $_SESSION['userId'] = $result['userId'];
        $_SESSION['user']   = $user;
        header('Location: profile.php');
        exit;
    }

    $errors = array_values($result['errors'] ?? []);
}

include __DIR__ . '/assets/html/signup.html';
