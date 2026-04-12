<?php
require_once 'config.php';
require_once 'models/User.php';
require_once 'models/Profile.php';
require_once 'controllers/ProfileController.php';

$controller = new ProfileController($pdo);
$controller->show();
?>