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
$errors      = [];
$oldValues   = [];
$success     = false;
$message     = null;
$messageType = 'info';

function h(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $userId   = (int) ($_POST['userId'] ?? $user['userId'] ?? 0);
    $oldValues = $_POST;

    if ($action === 'update_profile') {
        $firstName       = trim($_POST['firstName']       ?? '');
        $lastName        = trim($_POST['lastName']        ?? '');
        $email           = trim($_POST['email']           ?? '');
        $password        = $_POST['password']             ?? '';
        $passwordConfirm = $_POST['passwordConfirm']      ?? '';

        // ── Field-level validation ──
        if ($firstName === '')
            $errors['firstName'] = 'First name is required.';
        elseif (strlen($firstName) < 2)
            $errors['firstName'] = 'First name must be at least 2 characters.';

        if ($lastName === '')
            $errors['lastName'] = 'Last name is required.';
        elseif (strlen($lastName) < 2)
            $errors['lastName'] = 'Last name must be at least 2 characters.';

        if ($email === '')
            $errors['email'] = 'Email address is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Please enter a valid email address.';

        if ($password !== '' && strlen($password) < 6)
            $errors['password'] = 'Password must be at least 6 characters.';
        if ($password !== '' && $password !== $passwordConfirm)
            $errors['passwordConfirm'] = 'Passwords do not match.';

        if (empty($errors)) {
            $userData = [
                'fullName' => trim("$firstName $lastName"),
                'email'    => $email,
                'role'     => 'admin',
                'status'   => $user['status'] ?? 'active',
            ];
            if ($password !== '') $userData['password'] = $password;

            $userResult = $userController->updateUser($userId, $userData);
            if (!$userResult['success']) {
                foreach ($userResult['errors'] as $k => $v) $errors[$k] = $v;
            } else {
                $_SESSION['user']['fullName'] = $userData['fullName'];
                $_SESSION['user']['email']    = $email;
                $user = $_SESSION['user'];

                $profileController->createOrUpdate($userId, [
                    'bio'         => trim($_POST['bio']         ?? ''),
                    'photoUrl'    => $oldValues['photoUrl']     ?? '',
                    'location'    => $oldValues['location']     ?? '',
                    'preferences' => $oldValues['preferences']  ?? '',
                ]);

                $success     = true;
                $message     = 'Profile updated successfully.';
                $messageType = 'success';
                $oldValues   = []; // clear so fields show fresh DB values
            }
        }

        if (!empty($errors)) {
            $message     = 'Could not save profile. Please check the fields below.';
            $messageType = 'error';
        }
    }
}

$profileObj  = $profileController->getByUserId((int)$user['userId']);
$userProfile = [
    'bio'         => $profileObj ? $profileObj->getBio()         : '',
    'photoUrl'    => $profileObj ? $profileObj->getPhotoUrl()    : '',
    'location'    => $profileObj ? $profileObj->getLocation()    : '',
    'preferences' => $profileObj ? $profileObj->getPreferences() : '',
];

// Split fullName into first/last for the form
$nameParts    = explode(' ', $user['fullName'] ?? '', 2);
$adminFirst   = $nameParts[0] ?? '';
$adminLast    = $nameParts[1] ?? '';

$admin = [
    'fullName'  => $user['fullName']  ?? 'Admin User',
    'email'     => $user['email']     ?? '',
    'role'      => $user['role']      ?? 'admin',
    'status'    => $user['status']    ?? 'active',
    'userId'    => $user['userId']    ?? null,
    'location'  => 'Paris, France',
    'lastLogin' => date('d/m/Y H:i'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin Profile</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <style>
    /* inline field error styles */
    .field-error-msg {
      font-size: 12px;
      color: #ff6e45;
      margin-top: 5px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .field-error-msg::before { content: '⚠'; font-size: 11px; }
    .field.has-error input,
    .field.has-error textarea {
      border-color: #ff6e45 !important;
      background: rgba(255,110,69,.04) !important;
    }
  </style>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="brand">
        <img src="../FrontOffice/images/CareerStrand_logo.png" alt="CareerStrand" style="height:48px;width:auto;display:block;">
      </div>
      <div class="side-label">Main Menu</div>
      <nav class="nav-list">
        <a class="nav-item"        href="admin-dashboard.php"><span>Dashboard</span><span>Home</span></a>
        <a class="nav-item"        href="admin-users.php"><span>Users</span><span>1.2k</span></a>
        <a class="nav-item active" href="admin-profiles.php"><span>Profiles</span><span>842</span></a>
        <a class="nav-item"        href="admin-courses.php"><span>Courses</span><span>24</span></a>
        <a class="nav-item"        href="admin-skills.php"><span>Challenges</span><span>18</span></a>
        <a class="nav-item"        href="admin-opportunities.php"><span>Opportunities</span><span>36</span></a>
        <a class="nav-item"        href="admin-applications.php"><span>Applications</span><span>128</span></a>
        <a class="nav-item"        href="admin-analytics.php"><span>ADN Analytics</span><span>Live</span></a>
        <a class="nav-item"        href="admin-feedback.php"><span>Events</span><span>12</span></a>
        <a class="nav-item"        href="admin-settings.php"><span>Settings</span><span>New</span></a>
      </nav>
    </aside>

    <main class="admin-main">
      <header class="page-header">
        <div>
          <div class="eyebrow">Personal Settings</div>
          <h2>My Administrator Profile</h2>
          <p>Manage your account information, security access, and notification preferences for the CareerStrand console.</p>
        </div>
        <div class="header-actions">
          <button class="btn btn-soft" type="button">Activity Log</button>
          <button class="btn btn-main" type="button"
                  onclick="document.getElementById('adminProfileForm').submit();">Save Changes</button>
        </div>
      </header>

      <?php if ($message): ?>
      <div class="form-alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>"
           style="margin:0 0 16px;padding:12px 16px;border-radius:12px;">
        <?php echo h($message); ?>
      </div>
      <?php endif; ?>

      <section class="detail-grid">
        <article class="panel">
          <form id="adminProfileForm" class="field-grid" method="post" action="admin-profiles.php">
            <input type="hidden" name="action"  value="update_profile">
            <input type="hidden" name="userId"  value="<?php echo h($admin['userId'] ?? 0); ?>">
            <input type="hidden" name="photoUrl"    value="<?php echo h($oldValues['photoUrl']    ?? $userProfile['photoUrl']);    ?>">
            <input type="hidden" name="location"    value="<?php echo h($oldValues['location']    ?? $userProfile['location']);    ?>">
            <input type="hidden" name="preferences" value="<?php echo h($oldValues['preferences'] ?? $userProfile['preferences']); ?>">

            <!-- First Name / Last Name -->
            <div class="split-grid" style="gap:15px;">
              <div class="field <?php echo isset($errors['firstName']) ? 'has-error' : ''; ?>">
                <label>First Name</label>
                <input type="text" name="firstName"
                       value="<?php echo h($oldValues['firstName'] ?? $adminFirst); ?>">
                <?php if (isset($errors['firstName'])): ?>
                  <span class="field-error-msg"><?php echo h($errors['firstName']); ?></span>
                <?php endif; ?>
              </div>
              <div class="field <?php echo isset($errors['lastName']) ? 'has-error' : ''; ?>">
                <label>Last Name</label>
                <input type="text" name="lastName"
                       value="<?php echo h($oldValues['lastName'] ?? $adminLast); ?>">
                <?php if (isset($errors['lastName'])): ?>
                  <span class="field-error-msg"><?php echo h($errors['lastName']); ?></span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Email -->
            <div class="field <?php echo isset($errors['email']) ? 'has-error' : ''; ?>">
              <label>Professional Email Address</label>
              <input type="text" name="email"
                     value="<?php echo h($oldValues['email'] ?? $admin['email']); ?>">
              <?php if (isset($errors['email'])): ?>
                <span class="field-error-msg"><?php echo h($errors['email']); ?></span>
              <?php endif; ?>
            </div>

            <!-- Bio -->
            <div class="field">
              <label>Bio / Short Description</label>
              <textarea name="bio"><?php echo h($oldValues['bio'] ?? $userProfile['bio'] ?: 'ADN progression supervisor and opportunities audit manager.'); ?></textarea>
            </div>

            <!-- Security section -->
            <div class="panel-header" style="margin-top:20px;border-top:1px solid var(--border-soft);padding-top:20px;">
              <div class="panel-title">
                <h3>Security</h3>
                <p>Update your password to maintain back-office security.</p>
              </div>
            </div>

            <div class="field">
              <label>Current Password</label>
              <input type="password" name="currentPassword" placeholder="••••••••">
            </div>

            <div class="split-grid" style="gap:15px;">
              <div class="field <?php echo isset($errors['password']) ? 'has-error' : ''; ?>">
                <label>New Password</label>
                <input type="password" name="password" placeholder="Minimum 6 characters">
                <?php if (isset($errors['password'])): ?>
                  <span class="field-error-msg"><?php echo h($errors['password']); ?></span>
                <?php endif; ?>
              </div>
              <div class="field <?php echo isset($errors['passwordConfirm']) ? 'has-error' : ''; ?>">
                <label>Confirm Password</label>
                <input type="password" name="passwordConfirm" placeholder="Repeat password">
                <?php if (isset($errors['passwordConfirm'])): ?>
                  <span class="field-error-msg"><?php echo h($errors['passwordConfirm']); ?></span>
                <?php endif; ?>
              </div>
            </div>

            <button type="submit" id="adminProfileSubmit" style="display:none;"></button>
          </form>
        </article>

        <aside class="detail-card">
          <div class="profile-top">
            <div class="profile-meta">
              <div class="avatar" style="width:64px;height:64px;font-size:24px;">
                <?php echo strtoupper(substr($admin['fullName'], 0, 1)); ?>
              </div>
              <div>
                <h4><?php echo h($admin['fullName']); ?></h4>
                <span class="status-chip status-active"><?php echo h(ucfirst($admin['role'])); ?></span>
              </div>
            </div>
          </div>

          <div class="info-list" style="margin-top:20px;">
            <div class="info-item"><span>Admin ID</span><strong>#CS-<?php echo h($admin['userId'] ?? '0000'); ?></strong></div>
            <div class="info-item"><span>Access Level</span><strong>Full Access (Root)</strong></div>
            <div class="info-item"><span>Last Login</span><strong><?php echo h($admin['lastLogin']); ?></strong></div>
            <div class="info-item"><span>Location</span><strong><?php echo h($admin['location']); ?></strong></div>
          </div>

          <div class="side-label" style="margin-top:30px;">Moderation Statistics</div>
          <div class="mini-grid" style="grid-template-columns:1fr 1fr;margin-top:10px;">
            <div class="metric-tile" style="padding:15px;border-radius:20px;">
              <div class="small-label">Validated Profiles</div>
              <div class="kpi" style="font-size:22px;">1,248</div>
            </div>
            <div class="metric-tile" style="padding:15px;border-radius:20px;">
              <div class="small-label">Admin Actions</div>
              <div class="kpi" style="font-size:22px;">342</div>
            </div>
          </div>

          <div class="action-list" style="margin-top:30px;">
            <div class="action-item" style="cursor:pointer;border-color:var(--blue);">
              <span>Download My Logs</span>
              <span class="tiny-chip">Export</span>
            </div>
            <div class="action-item" style="cursor:pointer;border-color:var(--red);">
              <span style="color:var(--red);">Force Logout</span>
              <span class="tiny-chip" style="background:var(--red);color:#fff;"
                    onclick="window.location.href='../FrontOffice/logout.php'">Sign Out</span>
            </div>
          </div>
        </aside>
      </section>
    </main>
  </div>
  <script src="../js/admin.js"></script>
</body>
</html>