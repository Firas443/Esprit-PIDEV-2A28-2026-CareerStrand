<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Model/UserQuestionnaire.php';
require_once __DIR__ . '/../../utils/AuthRedirect.php';

session_start();

$errors = [];
$notice = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'pending') {
    $notice = 'Your recruiter request was sent. Please wait for admin approval before logging in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['user'], $_SESSION['userId'], $_SESSION['temp_user_id']);

    $controller = new UserController();
    $user       = $controller->authenticate(
        trim($_POST['email']    ?? ''),
        $_POST['password'] ?? ''
    );

    if ($user) {
        if ($user->getRole() === 'manager recruiter' && $user->getApprovalStatus() === 'pending') {
            $errors[] = 'Your recruiter account is waiting for admin approval.';
        } elseif ($user->getRole() === 'manager recruiter' && $user->getApprovalStatus() === 'rejected') {
            $reason = $user->getRejectionReason();
            $errors[] = 'Your recruiter request was rejected.' . ($reason ? ' Reason: ' . $reason : '');
        } elseif ($user->getStatus() !== 'active') {
            $errors[] = 'Your account is inactive.';
        } else {
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
            $questionnaire = new UserQuestionnaire();
            $needsQuestionnaire = !isBackOfficeRole($user->getRole()) && !$questionnaire->hasAnswers($user->getUserId());
            header('Location: ' . ($needsQuestionnaire ? 'questionnaire.php' : redirectForRole($user->getRole())));
            exit;
        }
    } else {
        $errors[] = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand | Login</title>
  <link rel="stylesheet" href="assets/css/frontoffice.css" />
  <style>
    :root{--bg:#040816;--bg-2:#071126;--text:#f5f3ee;--muted:rgba(245,243,238,0.74);--muted-2:rgba(245,243,238,0.5);--blue:#6f8fd8;--blue-2:#95abeb;--red:#ff6e45;--white:#f5f3ee;--shadow:0 30px 90px rgba(0,0,0,0.38);--max:1220px;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:Inter,Arial,Helvetica,sans-serif;color:var(--text);min-height:100vh;display:flex;flex-direction:column;background:radial-gradient(circle at 15% 20%,rgba(111,143,216,.12),transparent 26%),radial-gradient(circle at 85% 18%,rgba(255,110,69,.1),transparent 24%),linear-gradient(120deg,#030712 0%,#071126 45%,#090514 100%);overflow-x:hidden;}
    .site-header{position:sticky;top:0;background:rgba(4,8,22,.62);border-bottom:1px solid rgba(255,255,255,.08);backdrop-filter:blur(18px);}
    .header-inner{max-width:var(--max);margin:0 auto;padding:0 20px;min-height:80px;display:flex;align-items:center;justify-content:space-between;}
    .brand{display:flex;align-items:center;gap:14px;}
    .brand-logo{height:52px;width:auto;display:block;}
    .login-container{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;}
    .form-card{width:100%;max-width:460px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:32px;padding:40px;backdrop-filter:blur(12px);box-shadow:var(--shadow);}
    .form-head{text-align:center;margin-bottom:32px;}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;margin-bottom:16px;padding:6px 14px;border-radius:999px;font-size:10px;text-transform:uppercase;letter-spacing:.2em;color:var(--muted);background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);}
    .dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(90deg,var(--blue),var(--red));}
    .form-title{font-size:32px;font-weight:800;letter-spacing:-.04em;margin-bottom:8px;}
    .form-title span{background:linear-gradient(90deg,var(--blue-2),var(--red));-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .form-sub{color:var(--muted);font-size:14px;}
    .fields{display:grid;gap:18px;}
    .field{display:grid;gap:8px;}
    .field label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.15em;color:var(--muted-2);}
    .field-wrap{position:relative;}
    .field-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--blue-2);opacity:.4;}
    .field input{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:14px 14px 14px 44px;color:var(--text);font-size:14px;outline:none;transition:.2s;}
    .field input:focus{border-color:var(--blue);background:rgba(111,143,216,.06);box-shadow:0 0 0 4px rgba(111,143,216,.1);}
    .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted-2);}
    .form-options{display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-top:-8px;}
    .remember{display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--muted);}
    .forgot{color:var(--blue-2);font-weight:500;}
    .forgot:hover{text-decoration:underline;}
    .btn-submit{width:100%;padding:16px;margin-top:10px;background:linear-gradient(90deg,var(--blue),var(--red));border:none;border-radius:999px;color:var(--white);font-weight:800;font-size:16px;cursor:pointer;transition:.3s;box-shadow:0 10px 30px rgba(255,110,69,.2);}
    .btn-submit:hover{transform:translateY(-2px);filter:brightness(1.1);}
    .divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:var(--muted-2);font-size:12px;text-transform:uppercase;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.1);}
    .social-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .btn-social{display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;border-radius:14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);color:var(--text);font-size:14px;font-weight:500;cursor:pointer;transition:.2s;}
    .btn-social:hover{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.2);}
    .footer-text{text-align:center;margin-top:30px;font-size:14px;color:var(--muted);}
    .footer-text a{color:var(--blue-2);font-weight:700;}
    .error-list{color:#ff8a8a;margin-top:18px;list-style:disc;padding-left:20px;}
    /* Face button */
    .btn-face{width:100%;padding:14px;border:1px solid rgba(111,143,216,.35);border-radius:999px;background:rgba(111,143,216,.08);color:var(--blue-2);font-weight:700;font-size:15px;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:10px;}
    .btn-face:hover{background:rgba(111,143,216,.15);border-color:rgba(111,143,216,.6);}
    /* Face modal */
    .face-modal-bg{position:fixed;inset:0;background:rgba(4,8,22,.85);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;z-index:1000;}
    .face-modal-bg.open{display:flex;}
    .face-modal{background:rgba(7,17,38,.95);border:1px solid rgba(255,255,255,.1);border-radius:28px;padding:32px;width:100%;max-width:480px;position:relative;}
    .face-modal h2{font-size:20px;font-weight:700;margin-bottom:6px;text-align:center;}
    .face-modal p{font-size:13px;color:var(--muted);text-align:center;margin-bottom:20px;}
    .face-close{position:absolute;top:16px;right:20px;background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer;line-height:1;}
    #face-login-video{width:100%;border-radius:14px;background:#000;display:block;border:2px solid rgba(255,255,255,.1);}
    #face-login-video.detected{border-color:#4ade80;}
    #face-login-video.error{border-color:var(--red);}
    .face-msg-login{text-align:center;font-size:13px;min-height:20px;margin:12px 0;}
    @keyframes spin{to{transform:rotate(360deg)}}
    .spinner{width:20px;height:20px;border:2px solid var(--blue);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;display:inline-block;vertical-align:middle;margin-right:8px;}
  </style>
</head>
<body>
  <main class="login-container">
    <div class="form-card">
      <div class="form-head">
        <div class="eyebrow"><span class="dot"></span> Secure</div>
        <h1 class="form-title">Welcome <span>back</span></h1>
        <p class="form-sub">Sign in to continue your progression.</p>
      </div>

      <?php if ($notice): ?>
        <div style="color:#95abeb;margin-top:18px;font-size:14px;text-align:center;">
          <?php echo htmlspecialchars($notice); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <ul class="error-list">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form id="loginForm" action="login.php" method="post">
        <div class="fields">
          <div class="field">
            <label for="email">Email Address</label>
            <div class="field-wrap">
              <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              <input type="text" id="email" name="email" placeholder="name@example.com">
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="field-wrap">
              <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              <input type="password" id="password" name="password" placeholder="••••••••">
              <button type="button" class="pw-toggle" id="togglePw">Show</button>
            </div>
          </div>

          <div class="form-options">
            <label class="remember"><input type="checkbox"> Remember me</label>
            <a href="#" class="forgot">Forgot password?</a>
          </div>

          <button type="submit" class="btn-submit">Sign in →</button>
        </div>
      </form>

      <div class="divider">or</div>

      <button class="btn-face" onclick="openFaceModal()">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <circle cx="12" cy="9" r="3.5"/>
          <path d="M9 13c-3 .5-5 2.5-5 5h16c0-2.5-2-4.5-5-5"/>
          <path d="M8.5 6.5C9.2 5.6 10.5 5 12 5s2.8.6 3.5 1.5" stroke-linecap="round"/>
        </svg>
        Sign in with my face
      </button>

      <div class="divider">or continue with</div>

      <div class="social-grid">
        <button class="btn-social" type="button" onclick="window.location.href='signup.php'">Google</button>
        <button class="btn-social" type="button" onclick="window.location.href='signup.php'">LinkedIn</button>
      </div>

      <p class="footer-text">Don't have an account? <a href="signup.php">Create my DNA</a></p>
    </div>
  </main>

  <!-- FACE LOGIN MODAL -->
  <div class="face-modal-bg" id="face-modal-bg">
    <div class="face-modal">
      <button class="face-close" onclick="closeFaceModal()">✕</button>
      <h2>Face ID Login</h2>
      <p>Look straight at the camera. Detection is automatic.</p>
      <div style="position:relative;">
        <video id="face-login-video" autoplay muted playsinline></video>
        <canvas id="face-login-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:14px;pointer-events:none;"></canvas>
      </div>
      <div class="face-msg-login" id="face-login-msg" style="color:var(--muted);">
        <span class="spinner"></span> Loading face models…
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script>
    const togglePw = document.getElementById('togglePw');
    const pwInput  = document.getElementById('password');
    togglePw.addEventListener('click', () => {
      pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
      togglePw.textContent = pwInput.type === 'password' ? 'Show' : 'Hide';
    });

    const MODEL_URL = 'assets/models';
    const API_URL   = '../../api/face.php';

    let faceStream = null, faceModelsReady = false, detecting = false, loginDone = false;

    const modal   = document.getElementById('face-modal-bg');
    const video   = document.getElementById('face-login-video');
    const overlay = document.getElementById('face-login-overlay');
    const msgEl   = document.getElementById('face-login-msg');

    function setMsg(text, color) {
      msgEl.innerHTML   = text;
      msgEl.style.color = color || 'var(--muted)';
    }

    async function loadModels() {
      try {
        await Promise.all([
          faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
          faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
          faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        faceModelsReady = true;
        startCamera();
      } catch(e) {
        setMsg('Could not load face models. Check assets/models/ folder.', 'var(--red)');
      }
    }

   async function startCamera() {
  try {
    faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
    video.srcObject = faceStream;

    video.onloadedmetadata = () => {
      video.play();
      setMsg('Look straight at the camera…');
      setTimeout(scan, 800);
    };

  } catch(e) {
    setMsg('Camera access denied.', 'var(--red)');
  }
}

    async function scan() {
      if (!faceStream || loginDone) return;

      const det = await faceapi
        .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
        .withFaceLandmarks()
        .withFaceDescriptor();

      if (det) {
        video.classList.add('detected');
        video.classList.remove('error');
        const dims = faceapi.matchDimensions(overlay, video, true);
        faceapi.draw.drawDetections(overlay, faceapi.resizeResults(det, dims));

        if (!detecting) {
          detecting = true;
          setMsg('<span class="spinner"></span> Verifying…');

          const fd = new FormData();
          fd.append('action', 'login');
          fd.append('descriptor', JSON.stringify(Array.from(det.descriptor)));

          try {
            const res  = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
              loginDone = true;
              setMsg('✓ Bienvenue, ' + data.fullName + '! Redirecting…', '#4ade80');
              stopCamera();
              setTimeout(() => { window.location.href = data.redirect; }, 1000);
            } else {
              video.classList.remove('detected');
              video.classList.add('error');
              setMsg(data.error || 'Visage non reconnu.', 'var(--red)');
              setTimeout(() => {
                video.classList.remove('error');
                detecting = false;
                scan();
              }, 2500);
            }
          } catch(e) {
            setMsg('Network error. Try again.', 'var(--red)');
            detecting = false;
            setTimeout(scan, 1000);
          }
        }
      } else {
        video.classList.remove('detected', 'error');
        overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);
        if (!detecting) setMsg('Look straight at the camera…');
        setTimeout(scan, 300);
      }
    }

    function stopCamera() {
      if (faceStream) { faceStream.getTracks().forEach(t => t.stop()); faceStream = null; }
    }

    window.openFaceModal = function() {
      modal.classList.add('open');
      loginDone = detecting = false;
      if (!faceModelsReady) {
        setMsg('<span class="spinner"></span> Loading face models…');
        loadModels();
      } else {
        setMsg('Starting camera…');
        startCamera();
      }
    };

    window.closeFaceModal = function() {
      modal.classList.remove('open');
      stopCamera();
      detecting = loginDone = false;
      video.classList.remove('detected', 'error');
      overlay.getContext('2d')?.clearRect(0, 0, overlay.width, overlay.height);
    };
  </script>
</body>
</html>
