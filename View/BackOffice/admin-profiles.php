<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
const MODEL_URL = '../FrontOffice/assets/models';
const API_URL = '../../api/face.php';

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

$adminUserObj = $userController->getById((int)($user['userId'] ?? 0));
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


    /* Face ID admin block */
    .faceid-card {
      margin-top: 24px;
      padding-top: 24px;
      border-top: 1px solid var(--border-soft);
    }
    .faceid-status {
      padding: 14px 18px;
      border-radius: 14px;
      font-size: 14px;
      margin: 16px 0 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .faceid-camera-wrap {
      position: relative;
      width: 100%;
      max-width: 480px;
      margin: 0 auto 16px;
    }
    #admin-face-video {
      width: 100%;
      border-radius: 16px;
      border: 2px solid var(--border-soft);
      background: #000;
      display: block;
      min-height: 260px;
    }
    #admin-face-overlay {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      border-radius: 16px;
      pointer-events: none;
    }
    #admin-face-loading {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: rgba(4,8,22,.72);
      border-radius: 16px;
      color: rgba(245,243,238,.65);
      font-size: 13px;
      gap: 10px;
    }
    .face-spinner {
      width: 28px;
      height: 28px;
      border: 2px solid #6f8fd8;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    .faceid-toggle-row {
      margin-top: 20px;
      padding: 18px;
      border-radius: 16px;
      border: 1px solid var(--border-soft);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
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


            <!-- FACE ID SECTION -->
            <div class="faceid-card">
              <div class="panel-header" style="margin-bottom:12px;">
                <div class="panel-title">
                  <h3>Face ID</h3>
                  <p>Register your administrator face to log in to the back-office without a password.</p>
                </div>
              </div>

              <div id="admin-faceid-status" class="faceid-status" style="
                background:<?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? 'rgba(74,222,128,.10)' : 'rgba(255,255,255,.03)'; ?>;
                border:1px solid <?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? 'rgba(74,222,128,.25)' : 'rgba(255,255,255,.08)'; ?>;
                color:<?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? '#4ade80' : 'rgba(245,243,238,.65)'; ?>;">
                <span style="font-size:20px;"><?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? '✓' : '○'; ?></span>
                <div>
                  <strong><?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? 'Face ID is active' : 'Face ID not configured'; ?></strong>
                  <div style="font-size:12px;margin-top:2px;">
                    <?php echo ($adminUserObj && $adminUserObj->isFaceEnabled())
                      ? 'This admin account can log in using Face ID.'
                      : 'Capture your face below, then enable Face ID.'; ?>
                  </div>
                </div>
              </div>

              <div class="faceid-camera-wrap">
                <video id="admin-face-video" autoplay muted playsinline></video>
                <canvas id="admin-face-overlay"></canvas>
                <div id="admin-face-loading">
                  <div class="face-spinner"></div>
                  Loading face models…
                </div>
              </div>

              <div id="admin-face-msg" style="text-align:center;min-height:24px;font-size:13px;margin-bottom:16px;color:rgba(245,243,238,.65);"></div>

              <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:20px;">
                <button id="admin-btn-start-cam" class="btn btn-soft" type="button" onclick="startAdminFaceCam()" disabled>Start camera</button>
                <button id="admin-btn-capture" class="btn btn-main" type="button" onclick="captureAdminFace()" disabled>Capture my face</button>
              </div>

              <div class="faceid-toggle-row">
                <div>
                  <div style="font-size:14px;font-weight:700;">Enable Face ID login</div>
                  <div style="font-size:12px;color:rgba(245,243,238,.65);margin-top:4px;">You must capture your face first before enabling.</div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                  <div style="position:relative;width:44px;height:24px;">
                    <input type="checkbox" id="admin-faceid-toggle"
                           <?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? 'checked' : ''; ?>
                           onchange="toggleAdminFaceId(this.checked)"
                           style="position:absolute;opacity:0;width:100%;height:100%;cursor:pointer;margin:0;">
                    <div id="admin-toggle-track" style="width:44px;height:24px;border-radius:12px;transition:background .2s;background:<?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? '#6f8fd8' : 'rgba(255,255,255,.15)'; ?>;"></div>
                    <div id="admin-toggle-thumb" style="position:absolute;top:3px;left:<?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? '23px' : '3px'; ?>;width:18px;height:18px;border-radius:50%;background:#fff;transition:left .2s;"></div>
                  </div>
                  <span id="admin-toggle-label" style="font-size:13px;color:rgba(245,243,238,.65);"><?php echo ($adminUserObj && $adminUserObj->isFaceEnabled()) ? 'Enabled' : 'Disabled'; ?></span>
                </label>
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
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script>
  (function(){
    const MODEL_URL = '../FrontOffice/assets/models';
    const API_URL   = '../../api/face.php';
    let modelsReady = false;
    let stream = null;

    const video    = document.getElementById('admin-face-video');
    const overlay  = document.getElementById('admin-face-overlay');
    const loading  = document.getElementById('admin-face-loading');
    const msg      = document.getElementById('admin-face-msg');
    const btnStart = document.getElementById('admin-btn-start-cam');
    const btnCap   = document.getElementById('admin-btn-capture');

    function setMsg(text, type) {
      if (!msg) return;
      const colors = { info:'rgba(245,243,238,.65)', success:'#4ade80', error:'#ff6e45' };
      msg.style.color = colors[type] || colors.info;
      msg.textContent = text;
    }

    async function loadAdminFaceModels() {
      if (typeof faceapi === 'undefined') {
        setTimeout(loadAdminFaceModels, 200);
        return;
      }
      try {
        await Promise.all([
          faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
          faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
          faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        modelsReady = true;
        if (loading) loading.style.display = 'none';
        if (btnStart) btnStart.disabled = false;
        setMsg('Models ready. Click "Start camera".', 'info');
      } catch(e) {
        if (loading) loading.style.display = 'none';
        setMsg('Could not load face models. Check ../FrontOffice/assets/models folder.', 'error');
        console.error('Admin Face ID model load error:', e);
      }
    }

    window.startAdminFaceCam = async function() {
      if (!modelsReady) { setMsg('Models still loading, please wait...', 'info'); return; }
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        video.srcObject = stream;
        video.onloadedmetadata = function() {
          video.play();
          if (btnCap) btnCap.disabled = false;
          if (btnStart) btnStart.disabled = true;
          setMsg('Camera active. Look at the camera then click "Capture my face".', 'info');
          requestAnimationFrame(detectAdminFaceLoop);
        };
      } catch(e) {
        setMsg('Camera access denied. Allow camera in browser settings.', 'error');
        console.error('Admin Face ID camera error:', e);
      }
    };

    async function detectAdminFaceLoop() {
      if (!stream || !video || !overlay) return;
      try {
        const det = await faceapi
          .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
          .withFaceLandmarks();
        const ctx = overlay.getContext('2d');
        if (det) {
          const dims = faceapi.matchDimensions(overlay, video, true);
          ctx.clearRect(0, 0, overlay.width, overlay.height);
          faceapi.draw.drawDetections(overlay, faceapi.resizeResults(det, dims));
        } else {
          ctx.clearRect(0, 0, overlay.width, overlay.height);
        }
      } catch(e) {}
      requestAnimationFrame(detectAdminFaceLoop);
    }

    window.captureAdminFace = async function() {
      if (!modelsReady) { setMsg('Models still loading...', 'info'); return; }
      if (!stream)      { setMsg('Start the camera first.', 'error'); return; }
      setMsg('Detecting face...', 'info');
      if (btnCap) btnCap.disabled = true;
      try {
        const det = await faceapi
          .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
          .withFaceLandmarks()
          .withFaceDescriptor();
        if (!det) {
          setMsg('No face detected. Make sure your face is well lit and centred.', 'error');
          if (btnCap) btnCap.disabled = false;
          return;
        }
        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('descriptor', JSON.stringify(Array.from(det.descriptor)));

        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          setMsg('✓ Face captured and saved. Now enable Face ID below.', 'success');
        } else {
          setMsg('Error: ' + (data.error || 'Could not save face.'), 'error');
        }
      } catch(e) {
        setMsg('Error: ' + e.message, 'error');
        console.error(e);
      }
      if (btnCap) btnCap.disabled = false;
    };

    window.toggleAdminFaceId = async function(enabled) {
      const track = document.getElementById('admin-toggle-track');
      const thumb = document.getElementById('admin-toggle-thumb');
      const label = document.getElementById('admin-toggle-label');
      const checkbox = document.getElementById('admin-faceid-toggle');
      const fd = new FormData();
      fd.append('action', 'toggle');
      fd.append('enable', enabled ? 'true' : 'false');
      try {
        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          if (track) track.style.background = enabled ? '#6f8fd8' : 'rgba(255,255,255,.15)';
          if (thumb) thumb.style.left = enabled ? '23px' : '3px';
          if (label) label.textContent = enabled ? 'Enabled' : 'Disabled';

          const banner = document.getElementById('admin-faceid-status');
          if (banner) {
            banner.style.background = enabled ? 'rgba(74,222,128,.10)' : 'rgba(255,255,255,.03)';
            banner.style.border = enabled ? '1px solid rgba(74,222,128,.25)' : '1px solid rgba(255,255,255,.08)';
            banner.style.color = enabled ? '#4ade80' : 'rgba(245,243,238,.65)';
            banner.querySelector('span').textContent = enabled ? '✓' : '○';
            banner.querySelector('strong').textContent = enabled ? 'Face ID is active' : 'Face ID not configured';
          }
          setMsg(enabled ? 'Face ID login enabled.' : 'Face ID login disabled.', 'success');
        } else {
          setMsg(data.error || 'Could not update Face ID.', 'error');
          if (checkbox) checkbox.checked = !enabled;
        }
      } catch(e) {
        setMsg('Network error.', 'error');
        if (checkbox) checkbox.checked = !enabled;
      }
    };

    window.addEventListener('beforeunload', function() {
      if (stream) stream.getTracks().forEach(t => t.stop());
    });

    loadAdminFaceModels();
  })();
  </script>
  <script src="../js/admin.js"></script>
</body>
</html>