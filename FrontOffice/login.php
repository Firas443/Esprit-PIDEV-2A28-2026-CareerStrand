<?php
require_once 'config.php';
require_once 'models/User.php';

if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    if ($user['role'] === 'admin') {
        header('Location: ../BackOffice/dashboard.php');
        exit;
    }
    header('Location: profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $userModel = new User($pdo);
    $user = $userModel->authenticate($email, $password);

    if ($user) {
        $_SESSION['userId'] = $user['userId'];
        $_SESSION['user'] = $user;
        if ($user['role'] === 'admin') {
            header('Location: ../BackOffice/dashboard.php');
            exit;
        } else {
            header('Location: profile.php');
            exit;
        }
    }

    $errors[] = 'Incorrect email or password.';
}

include 'views/login.html';
