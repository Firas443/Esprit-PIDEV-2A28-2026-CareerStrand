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

$controller = new QuestionnaireController();
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

    foreach (['field','experience','skills','workStyle','goal','aiAnswer1','aiAnswer2','aiAnswer3'] as $required) {
        if ($answers[$required] === '') {
            $errors[$required] = 'This question is required.';
        }
    }

    if (empty($errors)) {
        $controller->saveAnswers((int)$userId, $answers);
        $bio = $controller->generateBio($answers, $role, $userObj->getFullName());

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
      --bg:#040816;
      --text:#f5f3ee;
      --muted:rgba(245,243,238,.64);
      --muted2:rgba(245,243,238,.44);
      --blue:#6f8fd8;
      --blue2:#95abeb;
      --red:#ff6e45;
      --green:#4ade80;
      --card:rgba(255,255,255,.04);
      --border:rgba(255,255,255,.10);
      --borderHi:rgba(111,143,216,.42);
      --shadow:0 30px 90px rgba(0,0,0,.45);
      --max:1180px;
    }

    *{box-sizing:border-box;margin:0;padding:0}

    body{
      min-height:100vh;
      font-family:Inter,Arial,sans-serif;
      color:var(--text);
      background:
        radial-gradient(circle at 12% 12%,rgba(111,143,216,.16),transparent 28%),
        radial-gradient(circle at 88% 18%,rgba(255,110,69,.13),transparent 26%),
        radial-gradient(circle at 70% 90%,rgba(111,143,216,.08),transparent 30%),
        linear-gradient(140deg,#030712 0%,#071126 52%,#090514 100%);
      overflow-x:hidden;
    }

    .site-header{
      position:sticky;
      top:0;
      z-index:50;
      background:rgba(4,8,22,.75);
      border-bottom:1px solid rgba(255,255,255,.08);
      backdrop-filter:blur(18px);
    }

    .header-inner{
      max-width:var(--max);
      margin:0 auto;
      padding:0 22px;
      min-height:78px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:18px;
    }

    .brand-logo{height:52px;width:auto;display:block}

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
      transition:.22s ease;
      font-family:Inter,Arial,sans-serif;
    }

    .btn-main{
      background:linear-gradient(90deg,var(--blue),var(--red));
      box-shadow:0 12px 30px rgba(255,110,69,.18);
    }

    .btn-main:hover{transform:translateY(-1px);filter:brightness(1.08)}

    .btn-ghost{
      background:rgba(255,255,255,.045);
      border:1px solid var(--border);
      color:var(--muted);
    }

    .btn-ghost:hover{color:var(--text);border-color:var(--borderHi)}

    .page{
      max-width:var(--max);
      margin:0 auto;
      padding:44px 22px 80px;
      display:grid;
      grid-template-columns:330px minmax(0,1fr);
      gap:28px;
      align-items:start;
    }

    .intro,.wizard{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:30px;
      box-shadow:var(--shadow);
      backdrop-filter:blur(14px);
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
      inset:-80px -80px auto auto;
      width:180px;
      height:180px;
      background:radial-gradient(circle,rgba(111,143,216,.25),transparent 65%);
      pointer-events:none;
    }

    .eyebrow{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:7px 13px;
      border-radius:999px;
      background:rgba(111,143,216,.12);
      border:1px solid rgba(111,143,216,.25);
      color:var(--blue2);
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
      background:linear-gradient(90deg,var(--blue),var(--red));
    }

    h1{
      font-size:36px;
      line-height:1;
      letter-spacing:-.055em;
      margin-bottom:13px;
    }

    h1 span{
      background:linear-gradient(90deg,var(--blue2),var(--red));
      -webkit-background-clip:text;
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
      background:rgba(111,143,216,.14);
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
      background:rgba(255,255,255,.035);
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
      background:rgba(255,255,255,.03);
    }

    .mini-num{
      width:28px;
      height:28px;
      border-radius:10px;
      display:grid;
      place-items:center;
      background:rgba(111,143,216,.16);
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
      right:-120px;
      bottom:-120px;
      width:260px;
      height:260px;
      background:radial-gradient(circle,rgba(255,110,69,.12),transparent 70%);
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
    .bar{height:7px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden}
    .bar-fill{height:100%;width:11%;background:linear-gradient(90deg,var(--blue),var(--red));border-radius:999px;transition:.35s ease}

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
      background:rgba(255,255,255,.035);
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
      background:rgba(255,255,255,.045);
      border:1px solid var(--border);
      color:var(--text);
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
      background:rgba(255,255,255,.045);
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
      background:rgba(111,143,216,.055);
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
  </style>
</head>

<body>
<header class="site-header">
  <div class="header-inner">
    <a href="/CareerStrand-template/View/FrontOffice/index.php" class="brand">
      <img src="images/CareerStrand_logo.png" alt="CareerStrand logo" class="brand-logo">
    </a>
    <a href="/CareerStrand-template/View/FrontOffice/index.php" class="btn btn-ghost">Back to Home</a>
  </div>
</header>

<main class="page">
  <aside class="intro">
    <div class="eyebrow"><span class="dot"></span> AI DNA Builder</div>
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
  </aside>

  <section class="wizard">
    <div class="topline">
      <div class="title">
        <h2>Build your Career DNA</h2>
        <p>Step-by-step questionnaire with 3 smart AI-style questions.</p>
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
        <button type="button" class="btn btn-main" id="nextBtn">Next &rarr;</button>
        <button type="submit" class="btn btn-main" id="submitBtn" style="display:none;">Finish &amp; Save &rarr;</button>
      </div>
    </form>
  </section>
</main>

<script>
const role = <?php echo json_encode($role); ?>;
const firstName = <?php echo json_encode($firstName); ?>;
const questions = Array.from(document.querySelectorAll('.question'));
const totalSteps = questions.length;
let current = 0;

const nextBtn = document.getElementById('nextBtn');
const prevBtn = document.getElementById('prevBtn');
const submitBtn = document.getElementById('submitBtn');
const errorBox = document.getElementById('errorBox');
const barFill = document.getElementById('barFill');
const stepText = document.getElementById('stepText');
const percentText = document.getElementById('percentText');
const fieldSelect = document.getElementById('field');
const aiEndpoint = '../../api/questionnaire_ai.php';
let aiQuestions = [];
let aiQuestionsSignature = '';
let aiBioSignature = '';
let aiBusy = false;

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
    errorBox.textContent = e.message;
    errorBox.style.display = 'block';
    setAiStatus(e.message, true);
    return false;
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
    errorBox.textContent = e.message;
    errorBox.style.display = 'block';
    return false;
  } finally {
    aiBusy = false;
    nextBtn.disabled = false;
    nextBtn.innerHTML = 'Next &rarr;';
  }
}

function buildPreview() {
  const bio = document.getElementById('aiBio').value.trim();
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

  if (!required) return true;

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

showStep(0);
applyAiQuestions([
  'AI will generate this after you answer the first five questions.',
  'AI will generate this after you answer the first five questions.',
  'AI will generate this after you answer the first five questions.'
]);
setAiStatus('');
</script>
</body>
</html>
