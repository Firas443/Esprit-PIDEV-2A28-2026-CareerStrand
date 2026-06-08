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
                'questionnaireStatus' => $user->getQuestionnaireStatus(),
            ];
            $questionnaire = new UserQuestionnaire();
            $needsQuestionnaire = !isBackOfficeRole($user->getRole())
                && $user->getQuestionnaireStatus() === 'pending'
                && !$questionnaire->hasAnswers($user->getUserId());
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
      --violet:#7b61ff;
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
    button,input{font:inherit;}
    input{color-scheme:dark;}
    .cinema-bg,.particle-field,.webgl-neuron{position:fixed;inset:0;pointer-events:none;}
    .webgl-neuron{
      width:100%;
      height:100%;
      z-index:-3;
      opacity:.72;
    }
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
    .login-shell{
      width:min(100%,480px);
      perspective:1400px;
    }
    .brand-lockup{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:13px;
      margin-bottom:24px;
      color:rgba(248,251,255,.88);
      animation:fieldIn .7s ease .05s both;
    }
    .brand-lockup img{
      width:44px;
      height:44px;
      object-fit:contain;
      filter:drop-shadow(0 12px 28px rgba(111,143,216,.35));
    }
    .brand-name{font-size:14px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;}
    .brand-tag{font-size:11px;letter-spacing:.28em;text-transform:uppercase;color:var(--muted-2);margin-top:2px;}
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
    .form-card::after{
      content:"";
      position:absolute;
      inset:1px;
      border-radius:29px;
      pointer-events:none;
      border:1px solid rgba(255,255,255,.025);
    }
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
      box-shadow:none;
    }
    .dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--blue-2),var(--orange));box-shadow:0 0 18px rgba(111,143,216,.75);}
    .form-title{
      color:var(--ink);
      font-size:clamp(36px,5vw,52px);
      font-weight:850;
      line-height:.96;
      letter-spacing:-.03em;
      margin-bottom:13px;
    }
    .form-title span{
      background:linear-gradient(100deg,var(--blue-2) 0%,#dfe8ff 35%,var(--orange) 92%);
      -webkit-background-clip:text;
      background-clip:text;
      -webkit-text-fill-color:transparent;
      text-shadow:0 0 30px rgba(111,143,216,.18);
    }
    .form-sub{color:var(--muted);font-size:15px;line-height:1.6;}
    .notice,.error-list{
      position:relative;
      margin:0 0 20px;
      border-radius:18px;
      padding:13px 15px;
      font-size:13px;
      line-height:1.45;
    }
    .notice{color:#c9d7ff;background:rgba(111,143,216,.1);border:1px solid rgba(111,143,216,.2);text-align:center;}
    .error-list{color:#ffd0c3;background:rgba(255,110,69,.1);border:1px solid rgba(255,110,69,.2);list-style-position:inside;}
    .fields{position:relative;display:grid;gap:17px;}
    .field{display:grid;gap:9px;animation:fieldIn .62s ease both;}
    .field:nth-child(2){animation-delay:.08s;}
    .form-options{animation:fieldIn .62s ease .14s both;}
    .btn-submit{animation:fieldIn .62s ease .2s both;}
    .field label{
      color:var(--muted-2);
      font-size:10px;
      font-weight:800;
      letter-spacing:.23em;
      text-transform:uppercase;
    }
    .field-wrap{position:relative;}
    .field-icon{
      position:absolute;
      left:16px;
      top:50%;
      z-index:1;
      width:19px;
      height:19px;
      color:rgba(157,183,255,.54);
      transform:translateY(-50%);
      transition:color .25s ease,filter .25s ease,opacity .25s ease;
    }
    .field input{
      width:100%;
      min-height:54px;
      border:1px solid rgba(255,255,255,.1);
      border-radius:14px;
      outline:none;
      padding:15px 48px 15px 48px;
      color:var(--text);
      background:#0a1124;
      box-shadow:none;
      transition:border-color .25s ease,box-shadow .25s ease,background .25s ease,transform .25s ease;
    }
    .field input::placeholder{color:rgba(232,239,255,.34);}
    .field input:-webkit-autofill,
    .field input:-webkit-autofill:hover,
    .field input:-webkit-autofill:focus{
      -webkit-text-fill-color:var(--text);
      caret-color:var(--text);
      box-shadow:0 0 0 1000px #0a1021 inset,0 0 0 4px rgba(111,143,216,.1);
      transition:background-color 9999s ease-in-out 0s;
      border-color:var(--blue);
    }
    .field-wrap:hover input{border-color:rgba(255,255,255,.18);background:#0c1429;}
    .field-wrap:focus-within input{
      border-color:var(--blue);
      background:#0b142c;
      box-shadow:0 0 0 4px rgba(111,143,216,.1);
      transform:translateY(-1px);
    }
    .field-wrap:focus-within .field-icon{color:#c7d6ff;filter:drop-shadow(0 0 10px rgba(111,143,216,.8));opacity:1;}
    .pw-toggle{
      position:absolute;
      right:10px;
      top:50%;
      z-index:2;
      display:grid;
      place-items:center;
      width:36px;
      height:36px;
      border:1px solid transparent;
      border-radius:12px;
      background:transparent;
      color:rgba(232,239,255,.56);
      cursor:pointer;
      transform:translateY(-50%);
      transition:color .2s ease,background .2s ease,border-color .2s ease;
    }
    .pw-toggle:hover,.pw-toggle:focus-visible{color:var(--ink);background:rgba(255,255,255,.07);border-color:rgba(207,222,255,.14);outline:none;}
    .pw-toggle svg{width:18px;height:18px;}
    .pw-toggle .icon-eye-off{display:none;}
    .pw-toggle.is-visible .icon-eye{display:none;}
    .pw-toggle.is-visible .icon-eye-off{display:block;}
    .form-options{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      margin-top:-2px;
      font-size:13px;
    }
    .remember{
      display:inline-flex;
      align-items:center;
      gap:10px;
      color:var(--muted);
      cursor:pointer;
      user-select:none;
    }
    .remember input{
      position:absolute;
      width:1px;
      height:1px;
      overflow:hidden;
      clip:rect(0,0,0,0);
      white-space:nowrap;
    }
    .checkmark{
      position:relative;
      width:19px;
      height:19px;
      flex:0 0 auto;
      border:1px solid rgba(207,222,255,.25);
      border-radius:7px;
      background:#0a1124;
      box-shadow:none;
      transition:border-color .2s ease,box-shadow .2s ease,background .2s ease;
    }
    .checkmark::after{
      content:"";
      position:absolute;
      left:5px;
      top:2px;
      width:6px;
      height:10px;
      border:solid #fff;
      border-width:0 2px 2px 0;
      opacity:0;
      transform:rotate(42deg) scale(.7);
      transition:opacity .2s ease,transform .2s ease;
    }
    .remember input:checked + .checkmark{
      border-color:rgba(157,183,255,.85);
      background:linear-gradient(135deg,var(--blue),var(--orange));
      box-shadow:0 0 22px rgba(111,143,216,.3);
    }
    .remember input:checked + .checkmark::after{opacity:1;transform:rotate(42deg) scale(1);}
    .remember input:focus-visible + .checkmark{outline:2px solid rgba(157,183,255,.8);outline-offset:3px;}
    .forgot{color:#bcd0ff;font-weight:750;transition:color .2s ease,text-shadow .2s ease;}
    .forgot:hover,.forgot:focus-visible{color:#fff;text-shadow:0 0 16px rgba(111,143,216,.55);outline:none;}
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
    }
    .btn-submit::before{
      content:"";
      position:absolute;
      inset:-2px;
      background:linear-gradient(110deg,transparent 0%,rgba(255,255,255,.0) 28%,rgba(255,255,255,.5) 48%,rgba(255,255,255,0) 68%,transparent 100%);
      transform:translateX(-120%);
      transition:transform .75s ease;
    }
    .btn-submit{font-size:0;}
    .btn-submit span{position:relative;z-index:1;display:inline-flex;align-items:center;gap:9px;font-size:15px;}
    .btn-submit::after{content:"Sign in ->";position:relative;z-index:1;font-size:15px;font-weight:850;letter-spacing:.02em;}
    .btn-submit:hover,.btn-submit:focus-visible{
      transform:translateY(-2px);
      filter:saturate(1.12);
      box-shadow:0 24px 56px rgba(255,110,69,.3),0 18px 50px rgba(111,143,216,.32),inset 0 1px 0 rgba(255,255,255,.42);
      outline:none;
    }
    .btn-submit:hover::before,.btn-submit:focus-visible::before{transform:translateX(120%);}
    .divider{
      position:relative;
      display:flex;
      align-items:center;
      gap:13px;
      margin:24px 0;
      color:rgba(232,239,255,.48);
      font-size:10px;
      font-weight:850;
      letter-spacing:.24em;
      text-transform:uppercase;
    }
    .divider::before,.divider::after{
      content:"";
      flex:1;
      height:1px;
      background:linear-gradient(90deg,transparent,rgba(207,222,255,.2),transparent);
    }
    .btn-face,.btn-social{
      position:relative;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      border:1px solid rgba(207,222,255,.15);
      color:var(--ink);
      background:#0b1225;
      box-shadow:0 12px 28px rgba(0,0,0,.18);
      cursor:pointer;
      transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease,background .25s ease;
    }
    .btn-face{
      width:100%;
      min-height:52px;
      border-radius:999px;
      font-size:14px;
      font-weight:800;
      color:#c9d7ff;
    }
    .btn-face::before{
      content:"";
      position:absolute;
      inset:-1px;
      border-radius:inherit;
      padding:1px;
      background:linear-gradient(100deg,rgba(111,143,216,.55),transparent 45%,rgba(255,110,69,.5));
      -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
      -webkit-mask-composite:xor;
      mask-composite:exclude;
      opacity:.7;
      pointer-events:none;
    }
    .btn-face svg,.btn-social svg{width:19px;height:19px;transition:transform .25s ease,filter .25s ease;}
    .btn-face:hover,.btn-face:focus-visible{
      transform:translateY(-2px);
      border-color:rgba(157,183,255,.42);
      background:#0d1831;
      box-shadow:0 18px 38px rgba(111,143,216,.16),0 0 30px rgba(111,143,216,.1),inset 0 1px 0 rgba(255,255,255,.16);
      outline:none;
    }
    .btn-face:hover svg{transform:scale(1.08);filter:drop-shadow(0 0 10px rgba(157,183,255,.66));}
    .social-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .btn-social{
      min-height:48px;
      border-radius:16px;
      font-size:13px;
      font-weight:800;
    }
    .btn-social:hover,.btn-social:focus-visible{
      transform:translateY(-2px);
      border-color:rgba(207,222,255,.26);
      background:#10182c;
      box-shadow:0 16px 30px rgba(0,0,0,.25);
      outline:none;
    }
    .footer-text{text-align:center;margin-top:28px;font-size:14px;color:var(--muted);}
    .footer-text a{color:#c1d4ff;font-weight:850;transition:color .2s ease,text-shadow .2s ease;}
    .footer-text a:hover,.footer-text a:focus-visible{color:#fff;text-shadow:0 0 16px rgba(111,143,216,.5);outline:none;}
    .face-modal-bg{position:fixed;inset:0;background:rgba(2,5,14,.78);backdrop-filter:blur(16px);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;}
    .face-modal-bg.open{display:flex;}
    .face-modal{background:rgba(7,17,38,.9);border:1px solid rgba(207,222,255,.16);border-radius:28px;padding:32px;width:100%;max-width:480px;position:relative;box-shadow:0 34px 100px rgba(0,0,0,.58),inset 0 1px 0 rgba(255,255,255,.14);}
    .face-modal h2{font-size:22px;font-weight:850;margin-bottom:6px;text-align:center;letter-spacing:-.02em;}
    .face-modal p{font-size:13px;color:var(--muted);text-align:center;margin-bottom:20px;}
    .face-close{position:absolute;top:15px;right:16px;display:grid;place-items:center;width:34px;height:34px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;font-size:18px;color:var(--muted);cursor:pointer;line-height:1;}
    .face-close{font-size:0;}
    .face-close::before{content:"X";font-size:18px;line-height:1;}
    #face-login-video{width:100%;border-radius:16px;background:#000;display:block;border:2px solid rgba(207,222,255,.12);}
    #face-login-video.detected{border-color:#4ade80;}
    #face-login-video.error{border-color:var(--red);}
    .face-msg-login{text-align:center;font-size:13px;min-height:20px;margin:12px 0;}
    @keyframes ambientDrift{0%{transform:translate3d(0,0,0) scale(1)}100%{transform:translate3d(2.4%,1.8%,0) scale(1.04)}}
    @keyframes glowFloatA{50%{transform:translate(38px,26px) scale(1.08)}}
    @keyframes glowFloatB{50%{transform:translate(-34px,-22px) scale(1.06)}}
    @keyframes particleDrift{to{background-position:180px 180px,-220px 220px,260px -260px,-210px 210px}}
    @keyframes pageIn{from{opacity:0}to{opacity:1}}
    @keyframes cardRise{from{opacity:0;transform:translateY(28px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes fieldIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes gradientFlow{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
    @keyframes spin{to{transform:rotate(360deg)}}
    .spinner{width:20px;height:20px;border:2px solid var(--blue);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;display:inline-block;vertical-align:middle;margin-right:8px;}
    @media (max-width:560px){
      .login-container{padding:30px 14px;}
      .form-card{padding:30px 22px;border-radius:26px;}
      .form-options{align-items:flex-start;flex-direction:column;}
      .brand-tag{letter-spacing:.2em;}
      .social-grid{grid-template-columns:1fr;}
    }
    @media (max-height:820px) and (min-width:720px){
      .login-container{align-items:start;padding:18px 20px;}
      .brand-lockup{margin-bottom:10px;}
      .brand-lockup img{width:38px;height:38px;}
      .form-card{padding:24px 36px;border-radius:28px;}
      .form-head{margin-bottom:16px;}
      .eyebrow{margin-bottom:10px;padding:7px 13px;}
      .form-title{font-size:36px;margin-bottom:7px;}
      .form-sub{font-size:14px;}
      .fields{gap:12px;}
      .field{gap:7px;}
      .field input{min-height:48px;padding-top:12px;padding-bottom:12px;}
      .form-options{font-size:12px;}
      .btn-submit{min-height:50px;}
      .divider{margin:12px 0;}
      .btn-face{min-height:48px;}
      .btn-social{min-height:44px;}
      .footer-text{margin-top:14px;}
    }
    @media (prefers-reduced-motion:reduce){
      *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important;}
    }
  </style>
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

      <div class="form-card">
      <div class="form-head">
        <div class="eyebrow"><span class="dot"></span> Secure strand access</div>
        <h1 class="form-title">Welcome <span>back</span></h1>
        <p class="form-sub">Continue your progress.</p>
      </div>

      <?php if ($notice): ?>
        <div class="notice">
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
              <svg class="field-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.9">
                <path d="M4.5 7.5l6.2 4.3a2.4 2.4 0 002.7 0l6.1-4.3" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5.8 5.5h12.4A2.8 2.8 0 0121 8.3v7.4a2.8 2.8 0 01-2.8 2.8H5.8A2.8 2.8 0 013 15.7V8.3a2.8 2.8 0 012.8-2.8z" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <input type="email" id="email" name="email" placeholder="name@example.com" autocomplete="email">
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="field-wrap">
              <svg class="field-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.9">
                <path d="M7.2 10.2V8a4.8 4.8 0 119.6 0v2.2" stroke-linecap="round"/>
                <path d="M6.6 10.2h10.8a2.6 2.6 0 012.6 2.6v4.4a2.6 2.6 0 01-2.6 2.6H6.6A2.6 2.6 0 014 17.2v-4.4a2.6 2.6 0 012.6-2.6z"/>
                <path d="M12 14.3v2.2" stroke-linecap="round"/>
              </svg>
              <input type="password" id="password" name="password" placeholder="••••••••">
              <button type="button" class="pw-toggle" id="togglePw" aria-label="Show password" aria-pressed="false">
                <svg class="icon-eye" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                  <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg class="icon-eye-off" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                  <path d="M3 3l18 18" stroke-linecap="round"/>
                  <path d="M10.7 5.2A9.8 9.8 0 0112 5c6.1 0 9.5 7 9.5 7a17.6 17.6 0 01-3.1 3.9M6.4 6.7C3.8 8.4 2.5 12 2.5 12s3.4 7 9.5 7a8.8 8.8 0 004.1-1"/>
                  <path d="M9.9 9.9a3 3 0 004.2 4.2"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-options">
            <label class="remember">
              <input type="checkbox">
              <span class="checkmark" aria-hidden="true"></span>
              <span>Remember me</span>
            </label>
            <a href="#" class="forgot">Forgot password?</a>
          </div>

          <button type="submit" class="btn-submit">Sign in →</button>
        </div>
      </form>

      <div class="divider">or</div>

      <button class="btn-face" type="button" onclick="openFaceModal()">
        <svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <path d="M8 4.5H6.6A2.1 2.1 0 004.5 6.6V8M16 4.5h1.4a2.1 2.1 0 012.1 2.1V8M8 19.5H6.6a2.1 2.1 0 01-2.1-2.1V16M16 19.5h1.4a2.1 2.1 0 002.1-2.1V16" stroke-linecap="round"/>
          <circle cx="12" cy="11" r="3.1"/>
          <path d="M7.8 16.6c1-1.4 2.5-2.1 4.2-2.1s3.2.7 4.2 2.1" stroke-linecap="round"/>
        </svg>
        Sign in with Face ID
      </button>

      <div class="divider">or continue with</div>

      <div class="social-grid">
        <button class="btn-social" type="button" onclick="window.location.href='signup.php'">
          <svg aria-hidden="true" viewBox="0 0 24 24">
            <path fill="#fff" d="M21.6 12.2c0-.7-.1-1.3-.2-1.9H12v3.6h5.4a4.6 4.6 0 01-2 3v2.4h3.2c1.8-1.7 3-4.2 3-7.1z"/>
            <path fill="#fff" opacity=".75" d="M12 22c2.7 0 5-.9 6.6-2.6l-3.2-2.4c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3.1v2.5A10 10 0 0012 22z"/>
            <path fill="#fff" opacity=".55" d="M6.4 13.9A6 6 0 016.1 12c0-.7.1-1.3.3-1.9V7.6H3.1a10 10 0 000 8.8l3.3-2.5z"/>
            <path fill="#fff" opacity=".85" d="M12 6c1.5 0 2.8.5 3.8 1.5l2.8-2.8A9.6 9.6 0 0012 2a10 10 0 00-8.9 5.6l3.3 2.5C7.2 7.8 9.4 6 12 6z"/>
          </svg>
          Google
        </button>
        <button class="btn-social" type="button" onclick="window.location.href='signup.php'">
          <svg aria-hidden="true" fill="currentColor" viewBox="0 0 24 24">
            <path d="M5.4 8.7H2.2v10.4h3.2V8.7zM3.8 7.3a1.9 1.9 0 100-3.8 1.9 1.9 0 000 3.8zM21.8 13.4c0-3.1-1.7-5.1-4.4-5.1a3.8 3.8 0 00-3.4 1.9V8.7h-3.1v10.4h3.2V14c0-1.7.8-2.7 2.2-2.7s2.2.9 2.2 2.8v5h3.2v-5.7z"/>
          </svg>
          LinkedIn
        </button>
      </div>

      <p class="footer-text">Don't have an account? <a href="signup.php">Create my profile</a></p>
      </div>
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

  <script src="https://unpkg.com/three@0.149.0/build/three.min.js"></script>
  <script src="assets/js/login-neuron.js?v=<?php echo filemtime(__DIR__ . '/assets/js/login-neuron.js'); ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script>
    const togglePw = document.getElementById('togglePw');
    const pwInput  = document.getElementById('password');
    pwInput.placeholder = 'Password';
    togglePw.addEventListener('click', () => {
      const isPassword = pwInput.type === 'password';
      pwInput.type = isPassword ? 'text' : 'password';
      togglePw.classList.toggle('is-visible', isPassword);
      togglePw.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
      togglePw.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
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

    window.openFaceModal = function() {
      modal.classList.add('open');
      loginDone = detecting = false;
      if (!faceModelsReady) {
        setMsg('<span class="spinner"></span> Loading face models...');
        loadModels();
      } else {
        setMsg('Starting camera...');
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
