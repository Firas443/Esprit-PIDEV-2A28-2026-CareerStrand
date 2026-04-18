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

$userController    = new UserController();
$profileController = new ProfileController();
$message    = null;
$messageType = 'info';
$errors     = [];
$oldValues  = [];
$success    = false;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['userId'] ?? $user['userId'] ?? 0);
    $oldValues = $_POST;

    switch ($action) {
        case 'update_profile':
            $fullName = trim(($_POST['firstName'] ?? '') . ' ' . ($_POST['lastName'] ?? ''));
            $email    = $_POST['email'] ?? $user['email'];
            $password = $_POST['password'] ?? '';

            $userResult = $userController->updateUser($userId, array_filter([
                'fullName' => $fullName,
                'email'    => $email,
                'password' => $password,
            ], function ($value) {
                return $value !== null && $value !== '';
            }));

            if ($userResult['success']) {
                if ($userId === $user['userId']) {
                    $_SESSION['user']['fullName'] = $fullName;
                    $_SESSION['user']['email']    = $email;
                    $user = $_SESSION['user'];
                }
            } else {
                $errors = array_merge($errors, $userResult['errors'] ?? []);
            }

            $profileResult = $profileController->createOrUpdate($userId, [
                'bio'         => $_POST['bio']         ?? '',
                'photoUrl'    => $_POST['photoUrl']    ?? '',
                'location'    => $_POST['location']    ?? '',
                'preferences' => $_POST['preferences'] ?? '',
            ]);

            if (!$profileResult['success']) {
                $errors = array_merge($errors, $profileResult['errors'] ?? []);
            }

            if (empty($errors)) {
                $success = true;
                $message = 'Profile updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Could not save profile. Please check the input.';
                $messageType = 'error';
            }
            break;

        case 'add_skill':
            $ok = $profileController->addSkill($userId, [
                'skillName'      => $_POST['skillName']      ?? '',
                'source'         => $_POST['source']         ?? '',
                'certificateUrl' => $_POST['certificateUrl'] ?? '',
                'validatedAt'    => $_POST['validatedAt']    ?? null,
            ]);
            $message     = $ok['success'] ? 'Skill added.' : 'Could not add skill.';
            $messageType = $ok['success'] ? 'success' : 'error';
            break;

        case 'delete_skill':
            $ok = $profileController->deleteSkill((int) ($_POST['skillId'] ?? 0), $userId);
            $message     = $ok['success'] ? 'Skill removed.' : 'Could not remove skill.';
            $messageType = $ok['success'] ? 'success' : 'error';
            break;
    }
}

$userProfile = $profileController->getByUserId($user['userId']);

$search  = $_GET['search'] ?? '';
$users   = $userController->getAll($search);
$stats   = $userController->getStats();

$selectedUser    = null;
$selectedProfile = null;
$selectedSkills  = [];

if (isset($_GET['view'])) {
    $uid             = (int) $_GET['view'];
    $selectedUser    = $userController->getById($uid);
    $selectedProfile = $profileController->getByUserId($uid);
    $selectedSkills  = $profileController->getSkillsByUserId($uid);
}

include __DIR__ . '/assets/html/admin-profiles.html';
