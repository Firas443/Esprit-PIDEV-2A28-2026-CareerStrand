<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';

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
$profileController = new ProfileController();
$message    = null;
$messageType = 'info';
$errors     = [];
$old        = [];
$selectedProfile = null;
$selectedSkills  = [];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $ok = $controller->createUser([
                'fullName' => $_POST['fullName'] ?? '',
                'email'    => $_POST['email']    ?? '',
                'password' => $_POST['password'] ?? '',
                'role'     => $_POST['role']     ?? 'user',
                'status'   => $_POST['status']   ?? 'active',
            ]);
            $message     = $ok['success'] ? 'User created successfully.' : 'Could not create user.';
            $messageType = $ok['success'] ? 'success' : 'error';
            break;

        case 'update':
            $data = [
                'fullName' => $_POST['fullName'] ?? '',
                'email'    => $_POST['email']    ?? '',
                'role'     => $_POST['role']     ?? 'user',
                'status'   => $_POST['status']   ?? 'active',
            ];
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }
            $ok = $controller->updateUser((int) ($_POST['userId'] ?? 0), $data);
            $message     = $ok['success'] ? 'User updated successfully.' : 'Could not update user.';
            $messageType = $ok['success'] ? 'success' : 'error';
            break;

        case 'delete':
            $ok = $controller->deleteUser((int) ($_POST['userId'] ?? 0));
            $message     = $ok['success'] ? 'User deleted.' : 'Could not delete user.';
            $messageType = $ok['success'] ? 'success' : 'error';
            break;
    }
}

$search  = $_GET['search'] ?? '';
$sort    = $_GET['sort']   ?? 'fullName';
$order   = $_GET['order']  ?? 'ASC';
$users   = $controller->getAll($search, $sort, $order);
$stats   = $controller->getStats();

$selectedUser = null;
$mode = null;

if (isset($_GET['action']) && $_GET['action'] === 'create') {
    $mode = 'create';
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $selectedUser = $controller->getById((int) $_GET['id']);
}

if (isset($_GET['view'])) {
    $selectedUser = $controller->getById((int) $_GET['view']);
}

if ($selectedUser) {
    $selectedProfile = $profileController->getByUserId((int) $selectedUser['userId']);
    $selectedSkills  = $profileController->getSkillsByUserId((int) $selectedUser['userId']);
}

include __DIR__ . '/assets/html/admin-users.html';
