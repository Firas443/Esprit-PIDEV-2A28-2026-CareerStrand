<?php
require_once 'config.php';
require_once 'models/User.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
if ($user['role'] !== 'user') {
    header('Location: profile.php');
    exit;
}

include 'views/profile.html';
?>