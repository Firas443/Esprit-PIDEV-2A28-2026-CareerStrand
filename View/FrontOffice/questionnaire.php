<?php
session_start();

require_once __DIR__ . '/../../Controller/QuestionnaireController.php';
require_once __DIR__ . '/../../Controller/UserController.php';

$userId = $_SESSION['userId'] ?? $_SESSION['temp_user_id'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$userController = new UserController();
$userObj = $userController->getById((int)$userId);

if (!$userObj) {
    header('Location: login.php');
    exit;
}

$role = $userObj->getRole();
$isPendingRecruiter = isset($_GET['pending']) || $role === 'manager recruiter';
$questionnaireStatus = method_exists($userObj, 'getQuestionnaireStatus') ? $userObj->getQuestionnaireStatus() : 'pending';
$resumeMode = isset($_GET['resume']);
$questionnaireModel = new UserQuestionnaire();
$savedQuestionnaire = [];
foreach ($questionnaireModel->getAnswersByUser((int) $userId) as $row) {
    $savedQuestionnaire[(string) ($row['question'] ?? '')] = (string) ($row['answer'] ?? '');
}

$controller = new QuestionnaireController();
$userController = new UserController();
$errors = [];

function hq(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = [
        'field'       => trim($_POST['field'] ?? ''),
        'experience'  => trim($_POST['experience'] ?? ''),
        'skills'      => trim($_POST['skills'] ?? ''),
        'workStyle'   => trim($_POST['workStyle'] ?? ''),
        'goal'        => trim($_POST['goal'] ?? ''),
        'aiQuestion1' => trim($_POST['aiQuestion1'] ?? ''),
        'aiAnswer1'   => trim($_POST['aiAnswer1'] ?? ''),
        'aiQuestion2' => trim($_POST['aiQuestion2'] ?? ''),
        'aiAnswer2'   => trim($_POST['aiAnswer2'] ?? ''),
        'aiQuestion3' => trim($_POST['aiQuestion3'] ?? ''),
        'aiAnswer3'   => trim($_POST['aiAnswer3'] ?? ''),
        'aiBio'       => trim($_POST['aiBio'] ?? ''),
    ];
    $aiSkipped = isset($_POST['aiSkipped']) && (string) $_POST['aiSkipped'] === '1';
    $hasAiAnswers = trim($answers['aiAnswer1']) !== '' || trim($answers['aiAnswer2']) !== '' || trim($answers['aiAnswer3']) !== '';

    foreach (['field','experience','skills','workStyle','goal','aiAnswer1','aiAnswer2','aiAnswer3'] as $required) {
        if ($answers[$required] === '' && (!in_array($required, ['aiAnswer1', 'aiAnswer2', 'aiAnswer3'], true) || !$aiSkipped)) {
            $errors[$required] = 'This question is required.';
        }
    }

    if (empty($errors)) {
        $controller->saveAnswers((int)$userId, $answers);
        $bio = $controller->generateBio($answers, $role, $userObj->getFullName());
        $finalQuestionnaireStatus = ($aiSkipped && !$hasAiAnswers) ? 'skipped' : 'completed';
        $userController->updateQuestionnaireStatus((int) $userId, $finalQuestionnaireStatus);
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['questionnaireStatus'] = $finalQuestionnaireStatus;
        }

        if (!$isPendingRecruiter) {
            $controller->saveBio((int)$userId, $bio);
        }

        if ($isPendingRecruiter) {
            header('Location: login.php?msg=pending');
            exit;
        }

        header('Location: profile.php');
        exit;
    }
}

$firstName = explode(' ', $userObj->getFullName())[0] ?? 'there';
$roleLabel = ucwords($role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CareerStrand | AI Questionnaire</title>

  <style>
    :root{
      --bg:#030611;
      --ink:#f8fbff;
      --text:#f5f3ee;
      --muted:rgba(232,239,255,.72);
      --muted2:rgba(232,239,255,.48);
      --blue:#6f8fd8;
      --blue2:#9db7ff;
      --red:#ff6e45;
      --orange:#ff6e45;
      --green:#4ade80;
      --card:#070d1d;
      --panel:#0a1124;
      --border:rgba(255,255,255,.10);
      --borderHi:rgba(111,143,216,.42);
      --shadow:0 30px 90px rgba(0,0,0,.42);
      --max:1180px;
    }

    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{min-height:100%}

    body{
      min-height:100vh;
      font-family:Inter,Arial,sans-serif;
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

    a{color:inherit;text-decoration:none}
    .cinema-bg,.particle-field,.webgl-neuron{position:fixed;inset:0;pointer-events:none}
    .webgl-neuron{width:100%;height:100%;z-index:-3;opacity:.72}
    .cinema-bg{z-index:-4;overflow:hidden}
    .glow{position:absolute;border-radius:999px;filter:blur(60px);opacity:.78;mix-blend-mode:screen}
    .glow-blue{width:440px;height:440px;left:-140px;top:-100px;background:rgba(88,132,255,.42);animation:glowFloatA 14s ease-in-out infinite}
    .glow-orange{width:520px;height:520px;right:-170px;bottom:-180px;background:rgba(255,105,59,.34);animation:glowFloatB 16s ease-in-out infinite}
    .glow-violet{width:620px;height:380px;left:50%;top:45%;transform:translate(-50%,-50%);background:rgba(94,75,210,.22);filter:blur(82px)}
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

    .brand-lockup{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:13px;
      width:max-content;
      margin:32px auto 0;
      color:rgba(248,251,255,.88);
      animation:fieldIn .7s ease .05s both;
    }

    .brand-lockup img{width:44px;height:44px;object-fit:contain;filter:drop-shadow(0 12px 28px rgba(111,143,216,.35))}
    .brand-name{display:block;font-size:14px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
    .brand-tag{display:block;font-size:11px;letter-spacing:.28em;text-transform:uppercase;color:var(--muted2);margin-top:2px}
    .account-back{position:fixed;top:28px;right:24px;z-index:10}

    .btn{
      border:0;
      border-radius:999px;
      padding:12px 22px;
      color:#fff;
      text-decoration:none;
      font-weight:800;
      font-size:13px;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      transition:transform .22s ease,border-color .22s ease,background .22s ease,box-shadow .22s ease,color .22s ease;
      font-family:Inter,Arial,sans-serif;
    }

    .btn-main{
      background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 30%,#ff7a50 70%,#ffae6c 100%);
      box-shadow:0 18px 42px rgba(255,110,69,.22),0 13px 36px rgba(111,143,216,.22),inset 0 1px 0 rgba(255,255,255,.34);
    }

    .btn-main:hover{transform:translateY(-2px);filter:saturate(1.12)}

    .btn-ghost{
      background:#0b1225;
      border:1px solid var(--border);
      color:var(--muted);
    }

    .btn-ghost:hover{color:var(--text);border-color:var(--borderHi)}

    .page{
      max-width:var(--max);
      margin:0 auto;
      padding:28px 22px 80px;
      display:grid;
      grid-template-columns:330px minmax(0,1fr);
      gap:28px;
      align-items:start;
      animation:pageIn .8s ease both;
    }

    .intro,.wizard{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:24px;
      box-shadow:var(--shadow);
    }

    .intro{
      padding:30px;
      position:sticky;
      top:104px;
      overflow:hidden;
    }

    .intro::before{
      content:'';
      position:absolute;
      inset:0;
      background:
        linear-gradient(180deg,rgba(255,255,255,.018),transparent 28%),
        radial-gradient(circle at 20% 0%,rgba(111,143,216,.1),transparent 42%);
      pointer-events:none;
    }

    .eyebrow{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:7px 13px;
      border-radius:999px;
      background:#11182b;
      border:1px solid rgba(207,222,255,.16);
      color:rgba(232,239,255,.74);
      font-size:10px;
      font-weight:900;
      letter-spacing:.18em;
      text-transform:uppercase;
      margin-bottom:18px;
    }

    .dot{
      width:8px;
      height:8px;
      border-radius:50%;
      background:linear-gradient(135deg,var(--blue2),var(--orange));
      box-shadow:0 0 18px rgba(111,143,216,.75);
    }

    h1{
      font-size:36px;
      line-height:1;
      letter-spacing:-.055em;
      margin-bottom:13px;
    }

    h1 span{
      background:linear-gradient(100deg,var(--blue2) 0%,#dfe8ff 35%,var(--orange) 92%);
      -webkit-background-clip:text;
      background-clip:text;
      -webkit-text-fill-color:transparent;
    }

    .intro p{
      color:var(--muted);
      font-size:14px;
      line-height:1.65;
    }

    .role-chip{
      display:inline-flex;
      margin-top:18px;
      padding:8px 14px;
      border-radius:999px;
      background:#0b1225;
      border:1px solid rgba(111,143,216,.28);
      color:var(--blue2);
      font-size:11px;
      font-weight:900;
      letter-spacing:.1em;
      text-transform:uppercase;
    }

    .pending-note{
      margin-top:18px;
      padding:14px 16px;
      border-radius:16px;
      background:var(--panel);
      border:1px solid var(--border);
      color:var(--muted);
      font-size:13px;
      line-height:1.55;
    }

    .mini-list{margin-top:26px;display:grid;gap:12px}

    .mini-item{
      display:flex;
      gap:12px;
      padding:14px;
      border:1px solid var(--border);
      border-radius:18px;
      background:var(--panel);
    }

    .mini-num{
      width:28px;
      height:28px;
      border-radius:10px;
      display:grid;
      place-items:center;
      background:linear-gradient(135deg,rgba(111,143,216,.28),rgba(255,110,69,.16));
      color:var(--blue2);
      font-weight:900;
      flex:0 0 auto;
    }

    .mini-item strong{display:block;font-size:13px;margin-bottom:3px}
    .mini-item span{display:block;font-size:12px;color:var(--muted);line-height:1.45}

    .wizard{
      padding:34px;
      min-height:610px;
      position:relative;
      overflow:hidden;
      display:flex;
      flex-direction:column;
    }

    .wizard::after{
      content:'';
      position:absolute;
      inset:1px;
      border-radius:23px;
      border:1px solid rgba(255,255,255,.025);
      pointer-events:none;
    }

    .wizard::before{
      content:'';
      position:absolute;
      inset:0;
      background:
        linear-gradient(180deg,rgba(255,255,255,.018),transparent 28%),
        radial-gradient(circle at 100% 100%,rgba(255,110,69,.08),transparent 38%);
      pointer-events:none;
    }

    .topline{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:20px;
      margin-bottom:26px;
      position:relative;
      z-index:1;
    }

    .title h2{font-size:26px;letter-spacing:-.04em;margin-bottom:6px}
    .title p{font-size:13px;color:var(--muted);line-height:1.55}

    .progress-box{min-width:210px}
    .progress-info{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:8px}
    .bar{height:8px;background:#0b1225;border:1px solid rgba(207,222,255,.1);border-radius:999px;overflow:hidden}
    .bar-fill{height:100%;width:11%;background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 35%,#ff7a50 100%);border-radius:999px;transition:.35s ease}

    form{
      flex:1;
      display:flex;
      flex-direction:column;
      position:relative;
      z-index:1;
    }

    .question{
      display:none;
      min-height:360px;
      justify-content:center;
      animation:fadeSlide .35s ease;
    }

    .question.active{
      display:flex;
      flex-direction:column;
    }

    @keyframes fadeSlide{
      from{opacity:0;transform:translateY(14px)}
      to{opacity:1;transform:translateY(0)}
    }

    .q-badge{
      display:inline-flex;
      width:max-content;
      align-items:center;
      gap:8px;
      margin-bottom:18px;
      padding:8px 14px;
      border-radius:999px;
      background:rgba(255,255,255,.045);
      border:1px solid var(--border);
      color:var(--muted);
      font-size:11px;
      font-weight:900;
      letter-spacing:.14em;
      text-transform:uppercase;
    }

    .q-title{
      font-size:34px;
      letter-spacing:-.045em;
      margin-bottom:10px;
      line-height:1.1;
    }

    .q-help{
      font-size:14px;
      line-height:1.65;
      color:var(--muted);
      margin-bottom:26px;
      max-width:720px;
    }

    .answer-card{
      background:var(--panel);
      border:1px solid var(--border);
      border-radius:22px;
      padding:22px;
      max-width:760px;
    }

    label{
      font-size:11px;
      font-weight:900;
      text-transform:uppercase;
      letter-spacing:.14em;
      color:var(--muted2);
      display:block;
      margin-bottom:9px;
    }

    input,select,textarea{
      width:100%;
      background:#0a1124;
      border:1px solid var(--border);
      color:var(--text);
      color-scheme:dark;
      border-radius:16px;
      padding:15px 16px;
      outline:none;
      transition:.2s ease;
      font-size:15px;
      font-family:Inter,Arial,sans-serif;
    }

    textarea{
      min-height:150px;
      resize:vertical;
      line-height:1.55;
    }

    select option{background:#071126;color:var(--text)}

    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus,
    textarea:-webkit-autofill,
    textarea:-webkit-autofill:hover,
    textarea:-webkit-autofill:focus,
    select:-webkit-autofill,
    select:-webkit-autofill:hover,
    select:-webkit-autofill:focus{
      -webkit-text-fill-color:var(--text);
      caret-color:var(--text);
      box-shadow:0 0 0 1000px #0a1124 inset,0 0 0 4px rgba(111,143,216,.1);
      transition:background-color 9999s ease-in-out 0s;
      border-color:var(--blue);
    }

    input:focus,select:focus,textarea:focus{
      border-color:var(--blue);
      background:rgba(111,143,216,.07);
      box-shadow:0 0 0 4px rgba(111,143,216,.10);
    }

    .field-error{
      margin-top:10px;
      color:#ff987d;
      font-size:13px;
      display:none;
    }

    .choice-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:12px;
    }

    .choice{position:relative}
    .choice input{position:absolute;opacity:0;pointer-events:none}

    .choice span{
      display:flex;
      min-height:58px;
      align-items:center;
      padding:15px 16px;
      border-radius:16px;
      background:#0a1124;
      border:1px solid var(--border);
      color:var(--muted);
      font-weight:700;
      transition:.2s ease;
      cursor:pointer;
    }

    .choice input:checked + span{
      background:rgba(111,143,216,.16);
      border-color:var(--borderHi);
      color:var(--text);
      box-shadow:0 0 0 4px rgba(111,143,216,.08);
    }

    .ai-question-box{
      margin-top:14px;
      padding:16px;
      border-radius:18px;
      border:1px dashed rgba(111,143,216,.34);
      background:rgba(111,143,216,.08);
      color:var(--blue2);
      font-size:13px;
      font-weight:800;
      line-height:1.5;
    }

    .ai-status{
      margin-top:12px;
      min-height:18px;
      color:var(--muted);
      font-size:12px;
      line-height:1.5;
    }

    .preview{
      margin-top:18px;
      padding:20px;
      border-radius:20px;
      background:rgba(74,222,128,.055);
      border:1px solid rgba(74,222,128,.18);
      color:var(--muted);
      font-size:14px;
      line-height:1.7;
      max-width:760px;
    }

    .nav-actions{
      display:flex;
      justify-content:space-between;
      gap:12px;
      margin-top:auto;
      padding-top:28px;
      position:relative;
      z-index:1;
    }

    .error-global{
      display:none;
      margin-bottom:18px;
      padding:14px 16px;
      border-radius:16px;
      background:rgba(255,110,69,.08);
      border:1px solid rgba(255,110,69,.25);
      color:#ff987d;
      font-size:13px;
    }

    @media(max-width:900px){
      .brand-lockup{margin-top:26px}
      .account-back{position:static;width:max-content;margin:18px auto 0}
      .page{grid-template-columns:1fr}
      .intro{position:static}
    }

    @media(max-width:600px){
      .wizard,.intro{padding:24px}
      .topline{flex-direction:column;align-items:stretch}
      .progress-box{min-width:0}
      .choice-grid{grid-template-columns:1fr}
      .q-title{font-size:26px}
      .nav-actions{flex-direction:column-reverse}
      .btn{width:100%}
    }

    @keyframes ambientDrift{0%{transform:translate3d(0,0,0) scale(1)}100%{transform:translate3d(2.4%,1.8%,0) scale(1.04)}}
    @keyframes glowFloatA{50%{transform:translate(38px,26px) scale(1.08)}}
    @keyframes glowFloatB{50%{transform:translate(-34px,-22px) scale(1.06)}}
    @keyframes particleDrift{to{background-position:180px 180px,-220px 220px,260px -260px,-210px 210px}}
    @keyframes pageIn{from{opacity:0}to{opacity:1}}
    @keyframes fieldIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    @media (prefers-reduced-motion:reduce){
      *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}
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

<a class="brand-lockup" href="index.php" aria-label="CareerStrand home">
  <img src="images/CareerStrand_logo_mark.png" alt="">
  <span>
    <span class="brand-name">CareerStrand</span>
    <span class="brand-tag">Progression DNA</span>
  </span>
</a>
<a href="index.php" class="btn btn-ghost account-back">Back to Home</a>

<main class="page">
  <aside class="intro">
    <div class="eyebrow"><span class="dot"></span> AI Profile Studio</div>
    <h1>Hello, <span><?php echo hq($firstName); ?></span></h1>
    <p>Answer one question at a time. CareerStrand will use your answers to generate a stronger professional profile description.</p>
    <span class="role-chip"><?php echo hq($roleLabel); ?></span>

    <div class="mini-list">
      <div class="mini-item">
        <div class="mini-num">1</div>
        <div><strong>5 fixed questions</strong><span>Core profile questions for everyone.</span></div>
      </div>
      <div class="mini-item">
        <div class="mini-num">2</div>
        <div><strong>3 AI questions</strong><span>Generated depending on role and field.</span></div>
      </div>
      <div class="mini-item">
        <div class="mini-num">3</div>
        <div><strong>Realistic bio</strong><span>Answers become your public profile description.</span></div>
      </div>
    </div>

    <?php if ($isPendingRecruiter): ?>
      <div class="pending-note">
        Your recruiter answers will be saved now. The generated description will be applied after admin approval.
      </div>
    <?php endif; ?>
    <?php if ($resumeMode || $questionnaireStatus === 'skipped'): ?>
      <div class="pending-note">
        You can finish these AI questions now, or come back later from your profile page.
      </div>
    <?php endif; ?>
  </aside>

  <section class="wizard">
    <div class="topline">
      <div class="title">
        <h2>Build your Career DNA</h2>
        <p>A guided profile workshop with fixed questions, smart prompts, and a final bio preview.</p>
      </div>
      <div class="progress-box">
        <div class="progress-info">
          <span id="stepText">Step 1 of 9</span>
          <span id="percentText">11%</span>
        </div>
        <div class="bar"><div class="bar-fill" id="barFill"></div></div>
      </div>
    </div>

    <div class="error-global" id="errorBox">Please answer this question before continuing.</div>

    <form method="POST" id="questionForm">
      <input type="hidden" name="aiBio" id="aiBio">
      <input type="hidden" name="aiSkipped" id="aiSkipped" value="0">
      <div class="question active" data-step="0" data-required="field">
        <div class="q-badge">Question 1</div>
        <h3 class="q-title">What field are you interested in?</h3>
        <p class="q-help">Choose the field that best represents your current career direction.</p>
        <div class="answer-card">
          <label>Main field</label>
          <select name="field" id="field">
            <option value="">Select your field...</option>
            <option>Web Development</option>
            <option>UI/UX Design</option>
            <option>Data / AI</option>
            <option>Marketing</option>
            <option>Business</option>
            <option>Education</option>
            <option>Human Resources</option>
            <option>Finance</option>
            <option>Other</option>
          </select>
          <div class="field-error">Please choose your field.</div>
        </div>
      </div>

      <div class="question" data-step="1" data-required="experience">
        <div class="q-badge">Question 2</div>
        <h3 class="q-title">What is your experience level?</h3>
        <p class="q-help">This helps the system create a description that matches your real profile.</p>
        <div class="answer-card">
          <label>Experience level</label>
          <div class="choice-grid">
            <label class="choice"><input type="radio" name="experience" value="Beginner"><span>Beginner</span></label>
            <label class="choice"><input type="radio" name="experience" value="Intermediate"><span>Intermediate</span></label>
            <label class="choice"><input type="radio" name="experience" value="Advanced"><span>Advanced</span></label>
            <label class="choice"><input type="radio" name="experience" value="Expert"><span>Expert</span></label>
          </div>
          <div class="field-error">Please choose your experience level.</div>
        </div>
      </div>

      <div class="question" data-step="2" data-required="skills">
        <div class="q-badge">Question 3</div>
        <h3 class="q-title">What are your strongest skills?</h3>
        <p class="q-help">Write technical skills, soft skills, tools, languages, or anything important about you.</p>
        <div class="answer-card">
          <label>Skills</label>
          <textarea name="skills" placeholder="Example: PHP, JavaScript, teamwork, communication..."></textarea>
          <div class="field-error">Please write at least one skill.</div>
        </div>
      </div>

      <div class="question" data-step="3" data-required="workStyle">
        <div class="q-badge">Question 4</div>
        <h3 class="q-title">How do you prefer to work?</h3>
        <p class="q-help">Your work style makes your profile more human and useful for other users.</p>
        <div class="answer-card">
          <label>Work style</label>
          <div class="choice-grid">
            <label class="choice"><input type="radio" name="workStyle" value="I prefer teamwork"><span>I prefer teamwork</span></label>
            <label class="choice"><input type="radio" name="workStyle" value="I prefer working independently"><span>I prefer working independently</span></label>
            <label class="choice"><input type="radio" name="workStyle" value="I like leading teams"><span>I like leading teams</span></label>
            <label class="choice"><input type="radio" name="workStyle" value="I like creative problem solving"><span>I like creative problem solving</span></label>
          </div>
          <div class="field-error">Please choose your work style.</div>
        </div>
      </div>

      <div class="question" data-step="4" data-required="goal">
        <div class="q-badge">Question 5</div>
        <h3 class="q-title">What is your main career goal?</h3>
        <p class="q-help">Tell us what you want to become, build, manage, or achieve.</p>
        <div class="answer-card">
          <label>Career goal</label>
          <input type="text" name="goal" placeholder="Example: become a full-stack developer">
          <div class="field-error">Please describe your goal.</div>
        </div>
      </div>

      <div class="question" data-step="5" data-required="aiAnswer1">
        <div class="q-badge">AI Question 1</div>
        <h3 class="q-title">Personalized career question</h3>
        <p class="q-help">Generated from your role and selected field.</p>
        <div class="answer-card">
          <label>Generated question</label>
          <div class="ai-question-box" id="aiQuestionBox1"></div>
          <div class="ai-status" id="aiStatus1"></div>
          <input type="hidden" name="aiQuestion1" id="aiQuestion1">
          <label style="margin-top:16px">Your answer</label>
          <textarea name="aiAnswer1" placeholder="Answer the first AI question..."></textarea>
          <div class="field-error">Please answer this AI question.</div>
        </div>
      </div>

      <div class="question" data-step="6" data-required="aiAnswer2">
        <div class="q-badge">AI Question 2</div>
        <h3 class="q-title">Smart skills question</h3>
        <p class="q-help">This question goes deeper into your skills, methods, or recruitment approach.</p>
        <div class="answer-card">
          <label>Generated question</label>
          <div class="ai-question-box" id="aiQuestionBox2"></div>
          <div class="ai-status" id="aiStatus2"></div>
          <input type="hidden" name="aiQuestion2" id="aiQuestion2">
          <label style="margin-top:16px">Your answer</label>
          <textarea name="aiAnswer2" placeholder="Answer the second AI question..."></textarea>
          <div class="field-error">Please answer this AI question.</div>
        </div>
      </div>

      <div class="question" data-step="7" data-required="aiAnswer3">
        <div class="q-badge">AI Question 3</div>
        <h3 class="q-title">Smart future question</h3>
        <p class="q-help">This final AI question helps generate a more realistic profile description.</p>
        <div class="answer-card">
          <label>Generated question</label>
          <div class="ai-question-box" id="aiQuestionBox3"></div>
          <div class="ai-status" id="aiStatus3"></div>
          <input type="hidden" name="aiQuestion3" id="aiQuestion3">
          <label style="margin-top:16px">Your answer</label>
          <textarea name="aiAnswer3" placeholder="Answer the third AI question..."></textarea>
          <div class="field-error">Please answer this AI question.</div>
        </div>
      </div>

      <div class="question" data-step="8">
        <div class="q-badge">Final preview</div>
        <h3 class="q-title">Your description is ready</h3>
        <p class="q-help">Click finish to save your answers and generate your profile bio.</p>
        <div class="preview" id="bioPreview"></div>
      </div>

      <div class="nav-actions">
        <button type="button" class="btn btn-ghost" id="prevBtn">&larr; Back</button>
        <button type="button" class="btn btn-ghost" id="skipAiBtn" style="display:none;">Skip for now</button>
        <button type="button" class="btn btn-main" id="nextBtn">Next &rarr;</button>
        <button type="submit" class="btn btn-main" id="submitBtn" style="display:none;">Finish &amp; Save &rarr;</button>
      </div>
    </form>
  </section>
</main>

<script src="https://unpkg.com/three@0.149.0/build/three.min.js"></script>
<script src="assets/js/login-neuron.js?v=<?php echo filemtime(__DIR__ . '/assets/js/login-neuron.js'); ?>"></script>
<script>
const role = <?php echo json_encode($role); ?>;
const firstName = <?php echo json_encode($firstName); ?>;
const savedQuestionnaire = <?php echo json_encode($savedQuestionnaire, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const resumeMode = <?php echo json_encode($resumeMode || $questionnaireStatus === 'skipped'); ?>;
const startStep = (resumeMode && Object.keys(savedQuestionnaire).length > 0) ? 5 : 0;
const questions = Array.from(document.querySelectorAll('.question'));
const totalSteps = questions.length;
let current = 0;

const nextBtn = document.getElementById('nextBtn');
const prevBtn = document.getElementById('prevBtn');
const skipAiBtn = document.getElementById('skipAiBtn');
const submitBtn = document.getElementById('submitBtn');
const errorBox = document.getElementById('errorBox');
const barFill = document.getElementById('barFill');
const stepText = document.getElementById('stepText');
const percentText = document.getElementById('percentText');
const aiSkippedInput = document.getElementById('aiSkipped');
const fieldSelect = document.getElementById('field');
const aiEndpoint = '../../api/questionnaire_ai.php';
let aiQuestions = [];
let aiQuestionsSignature = '';
let aiBioSignature = '';
let aiBusy = false;
let aiSkipped = aiSkippedInput.value === '1';

function getValue(name) {
  const checked = document.querySelector(`[name="${name}"]:checked`);
  if (checked) return checked.value.trim();

  const input = document.querySelector(`[name="${name}"]`);
  return input ? input.value.trim() : '';
}

function collectAnswers() {
  return {
    field: getValue('field'),
    experience: getValue('experience'),
    skills: getValue('skills'),
    workStyle: getValue('workStyle'),
    goal: getValue('goal'),
    aiQuestion1: getValue('aiQuestion1'),
    aiAnswer1: getValue('aiAnswer1'),
    aiQuestion2: getValue('aiQuestion2'),
    aiAnswer2: getValue('aiAnswer2'),
    aiQuestion3: getValue('aiQuestion3'),
    aiAnswer3: getValue('aiAnswer3')
  };
}

function restoreSavedAnswers() {
  const field = savedQuestionnaire['Main field'] || '';
  const experience = savedQuestionnaire['Experience level'] || '';
  const skills = savedQuestionnaire['Strongest skills'] || '';
  const workStyle = savedQuestionnaire['Work style'] || '';
  const goal = savedQuestionnaire['Career goal'] || '';

  if (field) {
    fieldSelect.value = field;
  }

  if (experience) {
    const expChoice = Array.from(document.querySelectorAll('input[name="experience"]')).find(input => input.value === experience);
    if (expChoice) expChoice.checked = true;
  }

  if (skills) {
    const skillsInput = document.querySelector('[name="skills"]');
    if (skillsInput) skillsInput.value = skills;
  }

  if (workStyle) {
    const workChoice = Array.from(document.querySelectorAll('input[name="workStyle"]')).find(input => input.value === workStyle);
    if (workChoice) workChoice.checked = true;
  }

  if (goal) {
    const goalInput = document.querySelector('[name="goal"]');
    if (goalInput) goalInput.value = goal;
  }
}

function safeText(value) {
  return String(value || '').trim();
}

function fallbackAiQuestions() {
  const field = safeText(getValue('field')) || 'your field';
  const roleLabel = role === 'manager recruiter'
    ? 'recruiter'
    : role === 'manager'
      ? 'manager'
      : 'CareerStrand member';

  if (role === 'manager recruiter') {
    return [
      `What qualities make a candidate stand out to you in ${field}?`,
      'How do you decide when someone is ready for an opportunity?',
      'What kind of growth do you want to help people achieve?'
    ];
  }

  if (role === 'manager') {
    return [
      `What kind of work or projects do you want your ${field} community to build?`,
      'How do you usually support people when they need guidance?',
      'What impact do you want your organization to make?'
    ];
  }

  return [
    `What kind of work in ${field} motivates you the most?`,
    'Which skill or habit has helped you grow recently?',
    'What opportunity would help you move closer to your goal?'
  ];
}

function fallbackBioText() {
  const first = firstName || 'This member';
  const field = safeText(getValue('field')) || 'their chosen field';
  const experience = safeText(getValue('experience')) || 'motivated';
  const skills = safeText(getValue('skills')) || 'continuous learning';
  const workStyle = safeText(getValue('workStyle')) || 'a professional work style';
  const goal = safeText(getValue('goal')) || 'grow professionally';

  if (role === 'manager recruiter') {
    return `${first} is a ${experience} recruiter focused on ${field}. They bring strengths in ${skills}. ${workStyle}, and their goal is to ${goal}.`;
  }

  if (role === 'manager') {
    return `${first} is a ${experience} manager interested in ${field}. They bring strengths in ${skills}. ${workStyle}, and their goal is to ${goal}.`;
  }

  return `${first} is a ${experience} CareerStrand member interested in ${field}. They bring strengths in ${skills}. ${workStyle}, and their goal is to ${goal}.`;
}

function signature(keys) {
  const answers = collectAnswers();
  return JSON.stringify(keys.reduce((acc, key) => {
    acc[key] = answers[key] || '';
    return acc;
  }, { role }));
}

function setAiStatus(text, isError = false) {
  [1, 2, 3].forEach(i => {
    const el = document.getElementById(`aiStatus${i}`);
    if (el) {
      el.textContent = text;
      el.style.color = isError ? '#ff987d' : 'var(--muted)';
    }
  });
}

function applyAiQuestions(qs) {
  aiQuestions = qs;

  document.getElementById('aiQuestionBox1').textContent = qs[0];
  document.getElementById('aiQuestionBox2').textContent = qs[1];
  document.getElementById('aiQuestionBox3').textContent = qs[2];

  document.getElementById('aiQuestion1').value = qs[0];
  document.getElementById('aiQuestion2').value = qs[1];
  document.getElementById('aiQuestion3').value = qs[2];
  setAiStatus('Generated by AI from your previous answers.');
}

function markAiSkipped(skipped) {
  aiSkipped = !!skipped;
  aiSkippedInput.value = aiSkipped ? '1' : '0';
}

function updateAiStepUi(index) {
  const isAiStep = index >= 5 && index <= 7;
  skipAiBtn.style.display = isAiStep ? 'inline-flex' : 'none';
  skipAiBtn.disabled = aiBusy || index < 5 || index > 7;
}

async function callAi(action, answers) {
  const res = await fetch(aiEndpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, answers })
  });
  const data = await res.json().catch(() => null);
  if (!res.ok || !data || !data.success) {
    throw new Error((data && data.error) ? data.error : 'The AI request failed.');
  }
  return data;
}

async function ensureAiQuestions() {
  const sig = signature(['field', 'experience', 'skills', 'workStyle', 'goal']);
  if (aiQuestions.length === 3 && aiQuestionsSignature === sig) return true;

  aiBusy = true;
  nextBtn.disabled = true;
  nextBtn.textContent = 'Asking AI...';
  setAiStatus('Generating personalized AI questions...');

  try {
    const data = await callAi('questions', collectAnswers());
    if (!Array.isArray(data.questions) || data.questions.length < 3) {
      throw new Error('AI did not return enough questions.');
    }
    applyAiQuestions(data.questions.slice(0, 3));
    aiQuestionsSignature = sig;
    return true;
  } catch (e) {
    const fallback = fallbackAiQuestions();
    applyAiQuestions(fallback);
    aiQuestionsSignature = sig;
    setAiStatus('AI is unavailable right now, so we filled in quick questions instead.', false);
    errorBox.style.display = 'none';
    return true;
  } finally {
    aiBusy = false;
    nextBtn.disabled = false;
    nextBtn.innerHTML = 'Next &rarr;';
  }
}

async function ensureBioPreview() {
  const sig = signature([
    'field', 'experience', 'skills', 'workStyle', 'goal',
    'aiQuestion1', 'aiAnswer1', 'aiQuestion2', 'aiAnswer2', 'aiQuestion3', 'aiAnswer3'
  ]);
  const aiBio = document.getElementById('aiBio');
  if (aiBio.value && aiBioSignature === sig) return true;

  aiBusy = true;
  nextBtn.disabled = true;
  nextBtn.textContent = 'Analyzing...';
  document.getElementById('bioPreview').textContent = 'AI is analyzing your answers and writing your bio...';

  try {
    const data = await callAi('bio', collectAnswers());
    aiBio.value = data.bio;
    aiBioSignature = sig;
    buildPreview();
    return true;
  } catch (e) {
    aiBio.value = fallbackBioText();
    aiBioSignature = sig;
    buildPreview();
    errorBox.style.display = 'none';
    return true;
  } finally {
    aiBusy = false;
    nextBtn.disabled = false;
    nextBtn.innerHTML = 'Next &rarr;';
  }
}

function buildPreview() {
  const bio = document.getElementById('aiBio').value.trim() || fallbackBioText();
  document.getElementById('bioPreview').innerHTML =
    `<strong>Generated profile description:</strong><br><br>` +
    (bio ? bio.replace(/[&<>"']/g, ch => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[ch])) : 'AI bio is not generated yet.');
}

function validateStep() {
  const q = questions[current];
  const required = q.dataset.required;
  const aiRequired = aiSkipped && q.dataset.step && Number(q.dataset.step) >= 5;

  if (!required) return true;
  if (aiRequired) return true;

  const value = getValue(required);
  const fieldError = q.querySelector('.field-error');

  if (!value) {
    if (fieldError) fieldError.style.display = 'block';
    errorBox.textContent = 'Please answer this question before continuing.';
    errorBox.style.display = 'block';
    return false;
  }

  if (fieldError) fieldError.style.display = 'none';
  errorBox.style.display = 'none';
  return true;
}

function showStep(index) {
  questions.forEach(q => q.classList.remove('active'));
  questions[index].classList.add('active');
  updateAiStepUi(index);

  const progress = Math.round(((index + 1) / totalSteps) * 100);
  barFill.style.width = progress + '%';
  stepText.textContent = `Step ${index + 1} of ${totalSteps}`;
  percentText.textContent = progress + '%';

  prevBtn.style.visibility = index === 0 ? 'hidden' : 'visible';

  if (index === totalSteps - 1) {
    nextBtn.style.display = 'none';
    submitBtn.style.display = 'inline-flex';
    buildPreview();
  } else {
    nextBtn.style.display = 'inline-flex';
    submitBtn.style.display = 'none';
  }

  errorBox.style.display = 'none';
}

nextBtn.addEventListener('click', async () => {
  if (aiBusy) return;
  if (!validateStep()) return;

  if (current === 4 && !(await ensureAiQuestions())) return;
  if (current === 7 && !(await ensureBioPreview())) return;

  if (current < totalSteps - 1) {
    current++;
    showStep(current);
  }
});

prevBtn.addEventListener('click', () => {
  if (current > 0) {
    current--;
    showStep(current);
  }
});

fieldSelect.addEventListener('change', () => {
  markAiSkipped(false);
  aiQuestions = [];
  aiQuestionsSignature = '';
  aiBioSignature = '';
  document.getElementById('aiBio').value = '';
  applyAiQuestions([
    'AI will generate this after you answer the first five questions.',
    'AI will generate this after you answer the first five questions.',
    'AI will generate this after you answer the first five questions.'
  ]);
  setAiStatus('');
});

skipAiBtn.addEventListener('click', () => {
  markAiSkipped(true);
  document.getElementById('aiBio').value = fallbackBioText();
  aiBioSignature = '';
  buildPreview();
  current = 8;
  showStep(current);
  errorBox.textContent = 'You can finish the AI questions later from your profile page.';
  errorBox.style.display = 'block';
});

['aiAnswer1', 'aiAnswer2', 'aiAnswer3'].forEach(name => {
  document.querySelectorAll(`[name="${name}"]`).forEach(el => {
    el.addEventListener('input', () => {
      if (safeText(el.value) !== '') {
        markAiSkipped(false);
      }
    });
  });
});

restoreSavedAnswers();
markAiSkipped(aiSkipped || resumeMode);
current = startStep;
showStep(current);
if (current > 0) {
  void ensureAiQuestions();
}
applyAiQuestions([
  'AI will generate this after you answer the first five questions.',
  'AI will generate this after you answer the first five questions.',
  'AI will generate this after you answer the first five questions.'
]);
setAiStatus('');
</script>
</body>
</html>
