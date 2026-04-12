<?php
require_once 'models/User.php';

class UserController {
    private $userModel;

    public function __construct($pdo) {
        $this->userModel = new User($pdo);
    }

    public function signup() {
        include __DIR__ . '/../views/signup.html';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName = ($_POST['firstName'] ?? '') . ' ' . ($_POST['lastName'] ?? '');
            $submittedRole = $_POST['role'] ?? 'user';
            $allowedRoles = ['user', 'manager', 'manager recruiter'];
            $data = [
                'fullName' => trim($fullName),
                'email' => $_POST['email'] ?? '',
                'password' => $_POST['password'] ?? '',
                'role' => in_array($submittedRole, $allowedRoles) ? $submittedRole : 'user'
            ];
            $result = $this->userModel->create($data);
            if ($result['success']) {
                $user = $this->userModel->read($result['userId']);
                $_SESSION['userId'] = $result['userId'];
                $_SESSION['user'] = $user;
                if ($user['role'] === 'admin') {
                    header('Location: ../BackOffice/dashboard.php');
                    exit;
                } else {
                    header('Location: profile.php');
                    exit;
                }
            } else {
                $errors = $result['errors'];
                include __DIR__ . '/../views/signup.html';
            }
        }
    }
}
?>