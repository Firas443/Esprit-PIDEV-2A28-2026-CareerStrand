<?php
// =============================================
// api/face.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Controller/UserController.php';
require_once __DIR__ . '/../utils/AuthRedirect.php';

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$controller = new UserController();
$action     = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Helper ────────────────────────────────────────────────
function jsonOut(array $data): void {
    echo json_encode($data);
    exit;
}

// ══════════════════════════════════════════════════════════
// ACTION: save
// Called from profile.php Face ID tab after camera capture
// Saves the 128-number descriptor to the logged-in user's row
// ══════════════════════════════════════════════════════════
if ($action === 'save') {
    if (!isset($_SESSION['user'])) {
        jsonOut(['success' => false, 'error' => 'Not logged in.']);
    }

    $userId     = (int) $_SESSION['user']['userId'];
    $descriptor = trim($_POST['descriptor'] ?? '');

    if (empty($descriptor)) {
        jsonOut(['success' => false, 'error' => 'No descriptor received.']);
    }

    $result = $controller->saveFaceDescriptor($userId, $descriptor);
    jsonOut($result);
}

// ══════════════════════════════════════════════════════════
// ACTION: toggle
// Called from profile.php to enable/disable face login
// ══════════════════════════════════════════════════════════
if ($action === 'toggle') {
    if (!isset($_SESSION['user'])) {
        jsonOut(['success' => false, 'error' => 'Not logged in.']);
    }

    $userId = (int) $_SESSION['user']['userId'];
    $enable = filter_var($_POST['enable'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Must have a descriptor saved before enabling
    if ($enable) {
        $user = $controller->getById($userId);
        if (!$user || empty($user->getFaceDescriptor())) {
            jsonOut(['success' => false, 'error' => 'Please capture your face first before enabling Face ID.']);
        }
    }

    $result = $controller->toggleFaceLogin($userId, $enable);

    // Keep session in sync
    if ($result['success']) {
        $_SESSION['user']['faceEnabled'] = $enable ? 1 : 0;
    }

    jsonOut($result);
}

// ══════════════════════════════════════════════════════════
// ACTION: login
// Called from login.php when user clicks "Se connecter avec mon visage"
// Compares incoming descriptor with all stored descriptors
// On match: creates session and returns role for redirect
// ══════════════════════════════════════════════════════════
if ($action === 'login') {
    $descriptor = trim($_POST['descriptor'] ?? '');

    if (empty($descriptor)) {
        jsonOut(['success' => false, 'error' => 'No face descriptor received.']);
    }

    $user = $controller->authenticateByFace($descriptor);

    if (!$user) {
       jsonOut(['success' => false, 'error' => 'Face not recognised. Please try again.']);;
    }

    if ($user->getRole() === 'manager recruiter' && $user->getApprovalStatus() === 'pending') {
        jsonOut(['success' => false, 'error' => 'Your recruiter account is pending admin approval.']);
    }

    if ($user->getRole() === 'manager recruiter' && $user->getApprovalStatus() === 'rejected') {
        jsonOut(['success' => false, 'error' => 'Your recruiter application has been rejected.']);
    }

    if ($user->getStatus() !== 'active') {
        jsonOut(['success' => false, 'error' => 'Your account is inactive.']);
    }

    // Create session — same structure as normal login
    $_SESSION['userId'] = $user->getUserId();
    $_SESSION['user']   = [
        'userId'      => $user->getUserId(),
        'fullName'    => $user->getFullName(),
        'email'       => $user->getEmail(),
        'role'        => $user->getRole(),
        'status'      => $user->getStatus(),
        'createdAt'   => $user->getCreatedAt(),
        'faceEnabled' => $user->getFaceEnabled(),
        'approvalStatus' => $user->getApprovalStatus(),
    ];

    // Return role so JS can redirect to the right page
    $redirect = redirectForRole($user->getRole(), true);

    jsonOut([
        'success'  => true,
        'role'     => $user->getRole(),
        'fullName' => $user->getFullName(),
        'redirect' => $redirect,
    ]);
}

// Unknown action
jsonOut(['success' => false, 'error' => 'Unknown action.']);
?>
