<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/controllers/UserController.php';

$controller = new UserController($pdo);

$action = $_GET['action'] ?? 'index';
$id     = $_GET['id']     ?? null;

switch ($action) {
    case 'index':
        $controller->index();
        break;
    case 'create':
        $controller->create();
        break;
    case 'store':
        $controller->store();
        break;
    case 'edit':
        $controller->edit($id);
        break;
    case 'update':
        $controller->update($id);
        break;
    case 'delete':
        $controller->destroy($id);
        break;
    default:
        $controller->index();
}
?>