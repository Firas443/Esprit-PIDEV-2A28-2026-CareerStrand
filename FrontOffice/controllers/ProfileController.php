<?php
require_once __DIR__ . '/../models/Profile.php';
require_once __DIR__ . '/../models/User.php';

class ProfileController {
    private $profileModel;
    private $userModel;

    public function __construct($pdo) {
        $this->profileModel = new Profile($pdo);
        $this->userModel    = new User($pdo);
    }

    public function show() {
        if (!isset($_SESSION['user'])) {
            header('Location: login.php');
            exit;
        }

        $user    = $_SESSION['user'];
        $userId  = $user['userId'];
        $profile = $this->profileModel->getByUserId($userId);
        $skills  = $this->profileModel->getSkillsByUserId($userId);

        $errors  = [];
        $success = false;
        $tab     = $_GET['tab'] ?? 'profile';

        // Handle Profile update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'update_profile') {
            $data = [
                'bio'         => trim($_POST['bio'] ?? ''),
                'photoUrl'    => trim($_POST['photoUrl'] ?? ''),
                'location'    => trim($_POST['location'] ?? ''),
                'preferences' => trim($_POST['preferences'] ?? ''),
            ];

            // Also update User base fields
            $firstName = trim($_POST['firstName'] ?? '');
            $lastName  = trim($_POST['lastName'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $password  = $_POST['password'] ?? '';
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
                    'fullName' => trim($firstName . ' ' . $lastName),
                    'email'    => $email,
                    'role'     => $user['role'],
                    'status'   => $user['status'],
                ];
                if ($password !== '') $userData['password'] = $password;

                $userResult = $this->userModel->update($userId, $userData);
                if (!$userResult['success']) {
                    $errors = array_merge($errors, array_values($userResult['errors']));
                } else {
                    $this->profileModel->createOrUpdate($userId, $data);
                    $updatedUser = $this->userModel->read($userId);
                    $_SESSION['user'] = $updatedUser;
                    $user    = $updatedUser;
                    $profile = $this->profileModel->getByUserId($userId);
                    $success = true;
                }
            }
            $tab = 'profile';
        }

        // Handle Skill add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'add_skill') {
            $skillData = [
                'skillName'      => trim($_POST['skillName'] ?? ''),
                'source'         => trim($_POST['source'] ?? ''),
                'certificateUrl' => trim($_POST['certificateUrl'] ?? ''),
                'validatedAt'    => $_POST['validatedAt'] ?? null,
            ];
            $result = $this->profileModel->addSkill($userId, $skillData);
            if ($result['success']) {
                $skills  = $this->profileModel->getSkillsByUserId($userId);
                // Recalculate score
                $profileData = $profile ? $profile : [];
                $this->profileModel->createOrUpdate($userId, $profileData);
                $profile = $this->profileModel->getByUserId($userId);
                $success = true;
            } else {
                $errors = $result['errors'];
            }
            $tab = 'skills';
        }

        // Handle Skill delete
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'delete_skill') {
            $skillId = (int)($_POST['skillId'] ?? 0);
            $this->profileModel->deleteSkill($skillId, $userId);
            $skills  = $this->profileModel->getSkillsByUserId($userId);
            $profile = $this->profileModel->getByUserId($userId);
            $success = true;
            $tab = 'skills';
        }

        // Split fullName
        $firstName = '';
        $lastName  = '';
        if (!empty($user['fullName'])) {
            $parts     = explode(' ', $user['fullName'], 2);
            $firstName = $parts[0];
            $lastName  = $parts[1] ?? '';
        }

        include __DIR__ . '/../views/profile.html';
    }
}
?>