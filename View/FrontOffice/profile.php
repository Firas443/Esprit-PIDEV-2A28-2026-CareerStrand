<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userController    = new UserController();
$profileController = new ProfileController();

$user    = $_SESSION['user'];
$userId  = (int) $user['userId'];
$profile = $profileController->getByUserId($userId);
$skills  = $profileController->getSkillsByUserId($userId);
$errors  = [];
$success = false;
$tab     = $_GET['tab'] ?? 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_profile') {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName  = trim($_POST['lastName']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $password  = $_POST['password']        ?? '';
        $passwordConfirm = $_POST['passwordConfirm'] ?? '';

        if ($password !== '' && $password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($firstName) || empty($lastName)) {
            $errors[] = 'First name and last name are required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email.';
        }

        if (empty($errors)) {
            $userData = [
                'fullName' => trim("$firstName $lastName"),
                'email'    => $email,
                'role'     => $user['role'],
                'status'   => $user['status'],
            ];
            if ($password !== '') $userData['password'] = $password;

            $result = $userController->updateUser($userId, $userData);
            if (!$result['success']) {
                $errors = array_merge($errors, array_values($result['errors']));
            } else {
                $profileController->createOrUpdate($userId, [
                    'bio'         => trim($_POST['bio']         ?? ''),
                    'photoUrl'    => trim($_POST['photoUrl']    ?? ''),
                    'location'    => trim($_POST['location']    ?? ''),
                    'preferences' => trim($_POST['preferences'] ?? ''),
                ]);
                $user             = $userController->getById($userId);
                $_SESSION['user'] = $user;
                $profile          = $profileController->getByUserId($userId);
                $success = true;
            }
        }
        $tab = 'profile';
    }

    if ($action === 'add_skill') {
        $result = $profileController->addSkill($userId, [
            'skillName'      => trim($_POST['skillName']      ?? ''),
            'source'         => trim($_POST['source']         ?? ''),
            'certificateUrl' => trim($_POST['certificateUrl'] ?? ''),
            'validatedAt'    => $_POST['validatedAt']         ?? null,
        ]);
        if ($result['success']) {
            $skills  = $profileController->getSkillsByUserId($userId);
            $profile = $profileController->getByUserId($userId);
            $success = true;
        } else {
            $errors = $result['errors'];
        }
        $tab = 'skills';
    }

    if ($action === 'update_skill') {
        $result = $profileController->updateSkill(
            (int) ($_POST['skillId'] ?? 0),
            $userId,
            [
                'skillName'      => trim($_POST['skillName']      ?? ''),
                'source'         => trim($_POST['source']         ?? ''),
                'certificateUrl' => trim($_POST['certificateUrl'] ?? ''),
                'validatedAt'    => $_POST['validatedAt']         ?? null,
            ]
        );
        if ($result['success']) {
            $skills  = $profileController->getSkillsByUserId($userId);
            $profile = $profileController->getByUserId($userId);
            $success = true;
        } else {
            $errors = $result['errors'];
        }
        $tab = 'skills';
    }

    if ($action === 'delete_skill') {
        $profileController->deleteSkill((int) ($_POST['skillId'] ?? 0), $userId);
        $skills  = $profileController->getSkillsByUserId($userId);
        $profile = $profileController->getByUserId($userId);
        $success = true;
        $tab     = 'skills';
    }
}

$parts     = explode(' ', $user['fullName'] ?? '', 2);
$firstName = $parts[0]  ?? '';
$lastName  = $parts[1]  ?? '';

include __DIR__ . '/assets/html/profile.html';
