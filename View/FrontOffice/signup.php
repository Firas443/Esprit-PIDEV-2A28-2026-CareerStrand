<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../Controller/RoleProfileController.php';

session_start();

// keyed errors — field => message shown under that field
$errors = [];
$post   = []; // repopulate form values on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post      = $_POST;
    $role      = $_POST['role']      ?? 'user';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirmPassword'] ?? '';
    $terms     = isset($_POST['terms']);

    // ── Shared field validation ──────────────────────────
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

    if ($password === '')
        $errors['password'] = 'Password is required.';
    elseif (strlen($password) < 6)
        $errors['password'] = 'Password must be at least 6 characters.';

    if ($confirm === '')
        $errors['confirmPassword'] = 'Please confirm your password.';
    elseif ($password !== $confirm)
        $errors['confirmPassword'] = 'Passwords do not match.';

    if (!$terms)
        $errors['terms'] = 'You must agree to the terms of use.';

    // ── Manager-specific validation ───────────────────────
    if ($role === 'manager') {
        $org = trim($_POST['organization'] ?? '');
        $cat = trim($_POST['categoryFocus'] ?? '');
        if ($org === '')
            $errors['organization'] = 'Organization name is required.';
        elseif (strlen($org) < 2)
            $errors['organization'] = 'Organization name must be at least 2 characters.';
        if ($cat === '')
            $errors['categoryFocus'] = 'Please select a category focus.';
    }

    // ── Recruiter-specific validation ─────────────────────
    if ($role === 'manager recruiter') {
        $co  = trim($_POST['companyName'] ?? '');
        $jt  = trim($_POST['jobTitle']    ?? '');
        $ind = trim($_POST['industry']    ?? '');
        $web = trim($_POST['companyWebsite'] ?? '');
        if ($co === '')
            $errors['companyName'] = 'Company name is required.';
        elseif (strlen($co) < 2)
            $errors['companyName'] = 'Company name must be at least 2 characters.';
        if ($jt === '')
            $errors['jobTitle'] = 'Job title is required.';
        if ($ind === '')
            $errors['industry'] = 'Please select an industry.';
        if ($web !== '' && !filter_var($web, FILTER_VALIDATE_URL))
            $errors['companyWebsite'] = 'Please enter a valid URL (https://...).';
    }

    // ── Only hit the DB if no validation errors ───────────
    if (empty($errors)) {
        $userController = new UserController();
        $result = $userController->createUser([
            'fullName' => trim("$firstName $lastName"),
            'email'    => $email,
            'password' => $password,
            'role'     => $role,
        ]);

        if ($result['success']) {
            $userId = $result['userId'];

            $profileController = new ProfileController();
            $profileController->createOrUpdate($userId, ['bio'=>'','photoUrl'=>'','location'=>'','preferences'=>'']);

            $roleController = new RoleProfileController();
            if ($role === 'manager') {
                $roleController->saveManagerProfile($userId, [
                    'organization'  => trim($_POST['organization']   ?? ''),
                    'categoryFocus' => trim($_POST['categoryFocus']  ?? ''),
                    'description'   => trim($_POST['orgDescription'] ?? ''),
                ]);
            } elseif ($role === 'manager recruiter') {
                $roleController->saveRecruiterProfile($userId, [
                    'companyName'      => trim($_POST['companyName']      ?? ''),
                    'jobTitle'         => trim($_POST['jobTitle']         ?? ''),
                    'industry'         => trim($_POST['industry']         ?? ''),
                    'companyWebsite'   => trim($_POST['companyWebsite']   ?? ''),
                    'opportunityTypes' => trim($_POST['opportunityTypes'] ?? ''),
                ]);
            }

            $user = $userController->getById($userId);

            // Manager recruiter accounts must be approved by admin before login.
            if ($role === 'manager recruiter') {
    $_SESSION['temp_user_id'] = $userId;
    header('Location: questionnaire.php?pending=1');
    exit;
}

            $_SESSION['userId'] = $userId;
            $_SESSION['user']   = [
                'userId'    => $user->getUserId(),
                'fullName'  => $user->getFullName(),
                'email'     => $user->getEmail(),
                'role'      => $user->getRole(),
                'status'    => $user->getStatus(),
                'createdAt' => $user->getCreatedAt(),
                'approvalStatus' => $user->getApprovalStatus(),
            ];
            header('Location: questionnaire.php');
            exit;
        }

        // DB-level errors (e.g. email already exists) — already keyed
        foreach ($result['errors'] as $k => $v) $errors[$k] = $v;
    }
}

// helper
function val(string $field, string $default = ''): string {
    global $post;
    return htmlspecialchars($post[$field] ?? $default, ENT_QUOTES);
}
function err(string $field): string {
    global $errors;
    if (!isset($errors[$field])) return '';
    return '<span class="field-error">• ' . htmlspecialchars($errors[$field]) . '</span>';
}
function hasErr(string $field): string {
    global $errors;
    return isset($errors[$field]) ? ' input-error' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CareerStrand | Create my DNA</title>
  <link rel="stylesheet" href="assets/css/frontoffice.css"/>
  <style>
    :root{
      --bg:#030611;
      --bg-2:#071126;
      --ink:#f8fbff;
      --text:#f5f3ee;
      --muted:rgba(232,239,255,.72);
      --muted-2:rgba(232,239,255,.5);
      --blue:#6f8fd8;
      --blue-2:#9db7ff;
      --orange:#ff6e45;
      --red:#ff6e45;
      --white:#f5f3ee;
      --shadow:0 34px 100px rgba(0,0,0,.58),0 0 80px rgba(111,143,216,.1);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    html{min-height:100%;}
    body{
      min-height:100vh;
      font-family:Inter,Arial,Helvetica,sans-serif;
      color:var(--text);
      background:var(--bg);
      overflow-x:hidden;
      isolation:isolate;
    }
    body::before{
      content:"";
      position:fixed;
      inset:-18%;
      z-index:-5;
      background:
        radial-gradient(circle at 16% 12%,rgba(111,143,216,.36),transparent 28%),
        radial-gradient(circle at 84% 88%,rgba(255,110,69,.28),transparent 30%),
        radial-gradient(circle at 52% 48%,rgba(123,97,255,.18),transparent 28%),
        linear-gradient(135deg,#02040c 0%,#061229 44%,#080914 100%);
      animation:ambientDrift 18s ease-in-out infinite alternate;
    }
    body::after{
      content:"";
      position:fixed;
      inset:0;
      z-index:-1;
      pointer-events:none;
      opacity:.46;
      background-image:
        linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.022) 1px,transparent 1px),
        radial-gradient(circle at center,transparent 0,rgba(0,0,0,.2) 68%);
      background-size:72px 72px,72px 72px,100% 100%;
      mask-image:radial-gradient(circle at 50% 44%,#000 0%,rgba(0,0,0,.7) 48%,transparent 86%);
    }
    a{color:inherit;text-decoration:none;}
    button,input,select{font:inherit;}
    input,select,textarea{color-scheme:dark;}
    .cinema-bg,.particle-field,.webgl-neuron{position:fixed;inset:0;pointer-events:none;}
    .webgl-neuron{width:100%;height:100%;z-index:-3;opacity:.72;}
    .cinema-bg{z-index:-4;overflow:hidden;}
    .glow{position:absolute;border-radius:999px;filter:blur(60px);opacity:.78;mix-blend-mode:screen;}
    .glow-blue{width:440px;height:440px;left:-140px;top:-100px;background:rgba(88,132,255,.42);animation:glowFloatA 14s ease-in-out infinite;}
    .glow-orange{width:520px;height:520px;right:-170px;bottom:-180px;background:rgba(255,105,59,.34);animation:glowFloatB 16s ease-in-out infinite;}
    .glow-violet{width:620px;height:380px;left:50%;top:45%;transform:translate(-50%,-50%);background:rgba(94,75,210,.22);filter:blur(82px);}
    .particle-field{
      z-index:-2;
      background:
        radial-gradient(circle at 18% 38%,rgba(157,183,255,.5) 0 1px,transparent 2px),
        radial-gradient(circle at 76% 24%,rgba(255,141,93,.46) 0 1px,transparent 2px),
        radial-gradient(circle at 64% 78%,rgba(255,255,255,.35) 0 1px,transparent 2px),
        radial-gradient(circle at 34% 74%,rgba(157,183,255,.34) 0 1px,transparent 2px);
      background-size:180px 180px,220px 220px,260px 260px,210px 210px;
      opacity:.24;
      animation:particleDrift 24s linear infinite;
    }
    .login-container{
      min-height:100vh;
      display:grid;
      place-items:center;
      padding:48px 20px;
      animation:pageIn .8s ease both;
    }
    .login-shell{width:min(100%,820px);perspective:1400px;}
    .brand-lockup{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:13px;
      margin-bottom:24px;
      color:rgba(248,251,255,.88);
      animation:fieldIn .7s ease .05s both;
    }
    .brand-lockup img{width:44px;height:44px;object-fit:contain;filter:drop-shadow(0 12px 28px rgba(111,143,216,.35));}
    .brand-name{display:block;font-size:14px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;}
    .brand-tag{display:block;font-size:11px;letter-spacing:.28em;text-transform:uppercase;color:var(--muted-2);margin-top:2px;}
    .form-card{
      position:relative;
      width:100%;
      overflow:hidden;
      border:1px solid rgba(255,255,255,.085);
      border-radius:24px;
      padding:40px;
      background:#070d1d;
      box-shadow:0 30px 90px rgba(0,0,0,.42);
      animation:cardRise .82s cubic-bezier(.2,.8,.2,1) .08s both;
      transition:border-color .25s ease,box-shadow .25s ease,background .25s ease;
    }
    .form-card::before{
      content:"";
      position:absolute;
      inset:0;
      pointer-events:none;
      background:
        linear-gradient(180deg,rgba(255,255,255,.018),transparent 28%),
        radial-gradient(circle at 50% 0%,rgba(111,143,216,.07),transparent 42%);
    }
    .form-card::after{content:"";position:absolute;inset:1px;border-radius:23px;pointer-events:none;border:1px solid rgba(255,255,255,.025);}
    .form-card:hover{border-color:rgba(255,255,255,.12);background:#080f22;box-shadow:0 30px 90px rgba(0,0,0,.44);}
    .form-head{position:relative;text-align:center;margin-bottom:31px;}
    .eyebrow{
      display:inline-flex;
      align-items:center;
      gap:9px;
      margin-bottom:17px;
      padding:8px 15px;
      border:1px solid rgba(207,222,255,.16);
      border-radius:999px;
      background:#11182b;
      color:rgba(232,239,255,.74);
      font-size:10px;
      font-weight:800;
      letter-spacing:.28em;
      text-transform:uppercase;
    }
    .dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--blue-2),var(--orange));box-shadow:0 0 18px rgba(111,143,216,.75);}
    .form-title{color:var(--ink);font-size:clamp(36px,5vw,52px);font-weight:850;line-height:.96;letter-spacing:-.03em;margin-bottom:13px;}
    .form-title span{
      background:linear-gradient(100deg,var(--blue-2) 0%,#dfe8ff 35%,var(--orange) 92%);
      -webkit-background-clip:text;
      background-clip:text;
      -webkit-text-fill-color:transparent;
      text-shadow:0 0 30px rgba(111,143,216,.18);
    }
    .form-sub{color:var(--muted);font-size:15px;line-height:1.6;}
    .signup-card{
      border-color:rgba(157,183,255,.12);
    }
    .signup-card::before{
      background:
        linear-gradient(180deg,rgba(255,255,255,.018),transparent 28%),
        radial-gradient(circle at 18% 6%,rgba(255,110,69,.09),transparent 32%),
        radial-gradient(circle at 84% 10%,rgba(157,183,255,.09),transparent 34%);
    }
    .creator-strip{
      position:relative;
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:10px;
      margin:-8px 0 26px;
      animation:fieldIn .62s ease .1s both;
    }
    .creator-pill{
      min-height:66px;
      padding:13px 14px;
      border:1px solid rgba(207,222,255,.12);
      border-radius:18px;
      background:#0a1124;
    }
    .creator-pill strong{
      display:block;
      color:var(--ink);
      font-size:18px;
      font-weight:850;
      line-height:1;
      margin-bottom:6px;
    }
    .creator-pill span{
      display:block;
      color:var(--muted-2);
      font-size:11px;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .role-tabs{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:28px;width:100%;}
    .role-tab{
      min-height:46px;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:10px 8px;
      text-align:center;
      border:1px solid rgba(207,222,255,.15);
      border-radius:999px;
      background:#0b1225;
      color:var(--muted);
      cursor:pointer;
      transition:transform .25s ease,border-color .25s ease,background .25s ease,color .25s ease,box-shadow .25s ease;
      font-size:13px;
      font-weight:800;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      user-select:none;
    }
    .role-tab:hover{transform:translateY(-1px);border-color:rgba(207,222,255,.26);background:#10182c;color:var(--ink);}
    .role-tab.active{
      color:#fff;
      border-color:rgba(157,183,255,.55);
      background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 36%,#ff7a50 100%);
      box-shadow:0 14px 34px rgba(111,143,216,.22),0 12px 30px rgba(255,110,69,.12);
    }
    .grid-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;margin-bottom:20px;width:100%;}
    .field{position:relative;display:flex;flex-direction:column;min-width:0;gap:9px;animation:fieldIn .62s ease both;}
    .field label{color:var(--muted-2);font-size:10px;font-weight:800;letter-spacing:.23em;text-transform:uppercase;}
    .field input,.field select{
      width:100%;
      min-height:54px;
      padding:15px 16px;
      border:1px solid rgba(255,255,255,.1);
      border-radius:14px;
      outline:none;
      color:var(--text);
      background:#0a1124;
      box-shadow:none;
      font-size:15px;
      transition:border-color .25s ease,box-shadow .25s ease,background .25s ease,transform .25s ease;
    }
    .field input::placeholder{color:rgba(232,239,255,.34);}
    .field input:-webkit-autofill,
    .field input:-webkit-autofill:hover,
    .field input:-webkit-autofill:focus,
    .field select:-webkit-autofill,
    .field select:-webkit-autofill:hover,
    .field select:-webkit-autofill:focus{
      -webkit-text-fill-color:var(--text);
      caret-color:var(--text);
      box-shadow:0 0 0 1000px #0a1124 inset,0 0 0 4px rgba(111,143,216,.1);
      transition:background-color 9999s ease-in-out 0s;
      border-color:var(--blue);
    }
    .field input:not(:placeholder-shown){background:#0a1124;color:var(--text);}
    .field input:hover,.field select:hover{border-color:rgba(255,255,255,.18);background:#0c1429;}
    .field input:focus,.field select:focus{border-color:var(--blue);background:#0b142c;box-shadow:0 0 0 4px rgba(111,143,216,.1);transform:translateY(-1px);}
    .field select option{background:#071126;color:var(--text);}
    .input-error{border-color:var(--red) !important;background:rgba(255,110,69,.08) !important;}
    .field-error{font-size:12px;color:#ffd0c3;display:flex;align-items:center;gap:4px;}
    .full{grid-column:span 2;}
    .section-divider{grid-column:span 2;border:none;border-top:1px solid rgba(207,222,255,.12);margin:4px 0 8px;}
    .section-label{grid-column:span 2;font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.24em;color:var(--blue-2);margin-bottom:-4px;}
    .role-fields{display:contents;}
    .role-fields.hidden{display:none;}
    .terms-wrap{position:relative;margin-bottom:8px;animation:fieldIn .62s ease .14s both;}
    .terms{display:flex;align-items:flex-start;gap:10px;font-size:13px;line-height:1.45;color:var(--muted);cursor:pointer;}
    .terms input{width:18px;height:18px;margin-top:1px;accent-color:var(--blue);flex-shrink:0;}
    .terms-error{font-size:12px;color:#ffd0c3;margin-top:8px;margin-bottom:12px;}
    .btn-submit{
      position:relative;
      width:100%;
      min-height:56px;
      overflow:hidden;
      border:0;
      border-radius:999px;
      color:#fff;
      background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 30%,#ff7a50 70%,#ffae6c 100%);
      background-size:220% 100%;
      box-shadow:0 18px 42px rgba(255,110,69,.22),0 13px 36px rgba(111,143,216,.22),inset 0 1px 0 rgba(255,255,255,.34);
      cursor:pointer;
      font-size:15px;
      font-weight:850;
      letter-spacing:.02em;
      transition:transform .25s ease,box-shadow .25s ease,filter .25s ease;
      animation:fieldIn .62s ease .2s both,gradientFlow 7s ease infinite;
      margin-top:8px;
    }
    .btn-submit::before{
      content:"";
      position:absolute;
      inset:-2px;
      background:linear-gradient(110deg,transparent 0%,rgba(255,255,255,0) 28%,rgba(255,255,255,.5) 48%,rgba(255,255,255,0) 68%,transparent 100%);
      transform:translateX(-120%);
      transition:transform .75s ease;
    }
    .btn-submit:hover,.btn-submit:focus-visible{
      transform:translateY(-2px);
      filter:saturate(1.12);
      box-shadow:0 24px 56px rgba(255,110,69,.3),0 18px 50px rgba(111,143,216,.32),inset 0 1px 0 rgba(255,255,255,.42);
      outline:none;
    }
    .btn-submit:hover::before,.btn-submit:focus-visible::before{transform:translateX(120%);}
    .foot-link{text-align:center;margin-top:25px;font-size:14px;color:var(--muted);}
    .foot-link a{color:#c1d4ff;text-decoration:none;font-weight:850;transition:color .2s ease,text-shadow .2s ease;}
    .foot-link a:hover,.foot-link a:focus-visible{color:#fff;text-shadow:0 0 16px rgba(111,143,216,.5);outline:none;}
    @keyframes ambientDrift{0%{transform:translate3d(0,0,0) scale(1)}100%{transform:translate3d(2.4%,1.8%,0) scale(1.04)}}
    @keyframes glowFloatA{50%{transform:translate(38px,26px) scale(1.08)}}
    @keyframes glowFloatB{50%{transform:translate(-34px,-22px) scale(1.06)}}
    @keyframes particleDrift{to{background-position:180px 180px,-220px 220px,260px -260px,-210px 210px}}
    @keyframes pageIn{from{opacity:0}to{opacity:1}}
    @keyframes cardRise{from{opacity:0;transform:translateY(28px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes fieldIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes gradientFlow{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
    @media (max-width:760px){
      .login-container{padding:30px 14px;}
      .form-card{padding:30px 22px;border-radius:26px;}
      .creator-strip{grid-template-columns:1fr;}
      .role-tabs{grid-template-columns:1fr;}
      .grid-fields{grid-template-columns:1fr;}
      .full,.section-divider,.section-label{grid-column:span 1;}
      .brand-tag{letter-spacing:.2em;}
    }
    @media (prefers-reduced-motion:reduce){
      *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important;}
    }
  </style>
  <script>
    function selectRole(role) {
      document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
      document.querySelector('.role-tab[data-role="' + role + '"]').classList.add('active');
      document.getElementById('role').value = role;
      document.querySelectorAll('.role-fields').forEach(el => el.classList.add('hidden'));
      const target = document.getElementById('fields-' + role.replace(' ', '-'));
      if (target) target.classList.remove('hidden');
    }
  </script>
</head>
<body>
  <canvas class="webgl-neuron" aria-hidden="true"></canvas>
  <div class="cinema-bg" aria-hidden="true">
    <span class="glow glow-blue"></span>
    <span class="glow glow-orange"></span>
    <span class="glow glow-violet"></span>
  </div>
  <div class="particle-field" aria-hidden="true"></div>

  <main class="login-container">
    <div class="login-shell">
      <a class="brand-lockup" href="index.php" aria-label="CareerStrand home">
        <img src="images/CareerStrand_logo_mark.png" alt="">
        <span>
          <span class="brand-name">CareerStrand</span>
          <span class="brand-tag">Progression DNA</span>
        </span>
      </a>

      <div class="form-card signup-card">
      <div class="form-head">
        <div class="eyebrow"><span class="dot"></span> Identity strand setup</div>
        <h1 class="form-title">Create your <span>strand</span></h1>
        <p class="form-sub">Choose your role and shape the account CareerStrand will build around you.</p>
      </div>

      <div class="creator-strip" aria-hidden="true">
        <div class="creator-pill"><strong>01</strong><span>Account</span></div>
        <div class="creator-pill"><strong>02</strong><span>Role</span></div>
        <div class="creator-pill"><strong>03</strong><span>DNA</span></div>
      </div>

      <!-- Role tabs -->
      <div class="role-tabs">
        <div class="role-tab active" data-role="user"              onclick="selectRole('user')">User</div>
        <div class="role-tab"        data-role="manager"           onclick="selectRole('manager')">Manager</div>
        <div class="role-tab"        data-role="manager recruiter" onclick="selectRole('manager recruiter')">Manager Recruiter</div>
      </div>

      <form method="POST" action="signup.php" novalidate>
        <input type="hidden" id="role" name="role" value="<?php echo val('role','user'); ?>">

        <div class="grid-fields">

          <!-- First Name -->
          <div class="field">
            <label>First Name</label>
            <input type="text" name="firstName" placeholder="Amine"
                   class="<?php echo hasErr('firstName'); ?>"
                   value="<?php echo val('firstName'); ?>">
            <?php echo err('firstName'); ?>
          </div>

          <!-- Last Name -->
          <div class="field">
            <label>Last Name</label>
            <input type="text" name="lastName" placeholder="Ben Ali"
                   class="<?php echo hasErr('lastName'); ?>"
                   value="<?php echo val('lastName'); ?>">
            <?php echo err('lastName'); ?>
          </div>

          <!-- Email -->
          <div class="field">
            <label>Email</label>
            <input type="text" name="email" placeholder="name@example.com"
                   class="<?php echo hasErr('email'); ?>"
                   value="<?php echo val('email'); ?>">
            <?php echo err('email'); ?>
          </div>

          <!-- Password -->
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••"
                   class="<?php echo hasErr('password'); ?>">
            <?php echo err('password'); ?>
          </div>

          <!-- Confirm Password -->
          <div class="field full">
            <label>Confirm Password</label>
            <input type="password" name="confirmPassword" placeholder="••••••••"
                   class="<?php echo hasErr('confirmPassword'); ?>">
            <?php echo err('confirmPassword'); ?>
          </div>

          <!-- ── MANAGER FIELDS ── -->
          <div id="fields-manager" class="role-fields hidden">
            <hr class="section-divider">
            <span class="section-label">Organization details</span>

            <div class="field full">
              <label>Organization / Club Name</label>
              <input type="text" name="organization" placeholder="e.g. TechClub Tunis"
                     class="<?php echo hasErr('organization'); ?>"
                     value="<?php echo val('organization'); ?>">
              <?php echo err('organization'); ?>
            </div>

            <div class="field">
              <label>Category Focus</label>
              <select name="categoryFocus" class="<?php echo hasErr('categoryFocus'); ?>">
                <option value="">Select…</option>
                <?php foreach(['Technology','Design','Business','Science','Arts','Other'] as $o): ?>
                  <option value="<?php echo $o; ?>" <?php echo val('categoryFocus')===$o?'selected':''; ?>><?php echo $o; ?></option>
                <?php endforeach; ?>
              </select>
              <?php echo err('categoryFocus'); ?>
            </div>

            <div class="field">
              <label>Location</label>
              <input type="text" name="orgLocation" placeholder="City, Country"
                     value="<?php echo val('orgLocation'); ?>">
            </div>

            <div class="field full">
              <label>Short Description</label>
              <input type="text" name="orgDescription" placeholder="What does your organization do?"
                     value="<?php echo val('orgDescription'); ?>">
            </div>
          </div>

          <!-- ── MANAGER RECRUITER FIELDS ── -->
          <div id="fields-manager-recruiter" class="role-fields hidden">
            <hr class="section-divider">
            <span class="section-label">Company & recruiting details</span>

            <div class="field">
              <label>Company Name</label>
              <input type="text" name="companyName" placeholder="e.g. TechCorp"
                     class="<?php echo hasErr('companyName'); ?>"
                     value="<?php echo val('companyName'); ?>">
              <?php echo err('companyName'); ?>
            </div>

            <div class="field">
              <label>Your Job Title</label>
              <input type="text" name="jobTitle" placeholder="e.g. HR Manager"
                     class="<?php echo hasErr('jobTitle'); ?>"
                     value="<?php echo val('jobTitle'); ?>">
              <?php echo err('jobTitle'); ?>
            </div>

            <div class="field">
              <label>Industry / Sector</label>
              <select name="industry" class="<?php echo hasErr('industry'); ?>">
                <option value="">Select…</option>
                <?php foreach(['Software & IT','Finance','Healthcare','Education','Marketing','Other'] as $o): ?>
                  <option value="<?php echo $o; ?>" <?php echo val('industry')===$o?'selected':''; ?>><?php echo $o; ?></option>
                <?php endforeach; ?>
              </select>
              <?php echo err('industry'); ?>
            </div>

            <div class="field">
              <label>Company Website</label>
              <input type="text" name="companyWebsite" placeholder="https://company.com"
                     class="<?php echo hasErr('companyWebsite'); ?>"
                     value="<?php echo val('companyWebsite'); ?>">
              <?php echo err('companyWebsite'); ?>
            </div>

            <div class="field full">
              <label>Types of Opportunities Offered</label>
              <select name="opportunityTypes">
                <option value="">Select…</option>
                <?php foreach(['Internship','Job','Freelance','Internship & Job','All'] as $o): ?>
                  <option value="<?php echo $o; ?>" <?php echo val('opportunityTypes')===$o?'selected':''; ?>><?php echo $o; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

        </div><!-- end grid-fields -->

        <!-- Terms -->
        <div class="terms-wrap">
          <label class="terms">
            <input type="checkbox" name="terms" <?php echo isset($post['terms'])?'checked':''; ?>>
            <span>I agree to the terms of use and privacy policy.</span>
          </label>
          <?php if (isset($errors['terms'])): ?>
            <div class="terms-error">• <?php echo htmlspecialchars($errors['terms']); ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit">Create my DNA →</button>
      </form>

      <div class="foot-link">
        Already have an account? <a href="login.php">Sign in</a>
      </div>
      </div>
    </div>
  </main>

  <script src="https://unpkg.com/three@0.149.0/build/three.min.js"></script>
  <script src="assets/js/login-neuron.js?v=<?php echo filemtime(__DIR__ . '/assets/js/login-neuron.js'); ?>"></script>
  <script>
    // Restore correct role tab if form was resubmitted with errors
    const savedRole = <?php echo json_encode($post['role'] ?? 'user'); ?>;
    if (savedRole !== 'user') selectRole(savedRole);
  </script>
</body>
</html>
