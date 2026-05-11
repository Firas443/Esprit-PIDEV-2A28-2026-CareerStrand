<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../Controller/RoleProfileController.php';
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';

$frontUser = requireFrontUser();

$userController    = new UserController();
$profileController = new ProfileController();
$roleController    = new RoleProfileController();

$userArr  = $frontUser;
$userId   = (int) $userArr['userId'];
$role     = $userArr['role'];

$userObj          = $userController->getById($userId);
$profile          = $profileController->getByUserId($userId);
$skills           = $profileController->getSkillsByUserId($userId);
$managerProfile   = null;
$recruiterProfile = null;
if ($role === 'manager')           $managerProfile   = $roleController->getManagerProfile($userId);
if ($role === 'manager recruiter') $recruiterProfile = $roleController->getRecruiterProfile($userId);

// keyed errors — field name => message shown directly under that input
$errors  = [];
$success = false;
$tab     = $_GET['tab'] ?? 'profile';

// helper: return the submitted value or fall back to saved value
function old(string $field, string $saved = ''): string {
    return htmlspecialchars($_POST[$field] ?? $saved, ENT_QUOTES);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    // ── UPDATE PROFILE ────────────────────────────────────
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

        // Only validate password if the user deliberately typed something
        // (ignore browser autofill by checking both fields are non-empty)
        $wantsNewPassword = ($password !== '' || $passwordConfirm !== '');
        if ($wantsNewPassword) {
            if (strlen($password) < 6)
                $errors['password'] = 'Password must be at least 6 characters.';
            elseif ($password !== $passwordConfirm)
                $errors['passwordConfirm'] = 'Passwords do not match.';
        }

        // Role-specific required fields
        if ($role === 'manager') {
            if (trim($_POST['organization'] ?? '') === '')
                $errors['organization'] = 'Organization name is required.';
            if (trim($_POST['categoryFocus'] ?? '') === '')
                $errors['categoryFocus'] = 'Please select a category focus.';
        }
        if ($role === 'manager recruiter') {
            if (trim($_POST['companyName'] ?? '') === '')
                $errors['companyName'] = 'Company name is required.';
            if (trim($_POST['jobTitle'] ?? '') === '')
                $errors['jobTitle'] = 'Job title is required.';
            if (trim($_POST['industry'] ?? '') === '')
                $errors['industry'] = 'Please select an industry.';
            $web = trim($_POST['companyWebsite'] ?? '');
            if ($web !== '' && !filter_var($web, FILTER_VALIDATE_URL))
                $errors['companyWebsite'] = 'Please enter a valid URL (https://...).';
        }

        if (empty($errors)) {
            $userData = [
                'fullName' => trim("$firstName $lastName"),
                'email'    => $email,
                'role'     => $role,
                'status'   => $userArr['status'],
            ];
            if ($wantsNewPassword && empty($errors['password']) && empty($errors['passwordConfirm']))
                $userData['password'] = $password;

            $result = $userController->updateUser($userId, $userData);
            if (!$result['success']) {
                // controller returns keyed errors (e.g. 'email' => 'Email already exists.')
                foreach ($result['errors'] as $k => $v) $errors[$k] = $v;
            } else {
                $profileController->createOrUpdate($userId, [
                    'bio'         => trim($_POST['bio']         ?? ''),
                    'photoUrl'    => trim($_POST['photoUrl']    ?? ''),
                    'location'    => trim($_POST['location']    ?? ''),
                    'preferences' => trim($_POST['preferences'] ?? ''),
                ]);

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

                $userObj = $userController->getById($userId);
                $_SESSION['user'] = [
                    'userId'    => $userObj->getUserId(),
                    'fullName'  => $userObj->getFullName(),
                    'email'     => $userObj->getEmail(),
                    'role'      => $userObj->getRole(),
                    'status'    => $userObj->getStatus(),
                    'createdAt' => $userObj->getCreatedAt(),
                ];
                $userArr  = $_SESSION['user'];
                $profile  = $profileController->getByUserId($userId);
                if ($role === 'manager')           $managerProfile   = $roleController->getManagerProfile($userId);
                if ($role === 'manager recruiter') $recruiterProfile = $roleController->getRecruiterProfile($userId);
                $success = true;
            }
        }
        $tab = 'profile';
    }

    // ── ADD SKILL ─────────────────────────────────────────
    if ($action === 'add_skill') {
        $result = $profileController->addSkill($userId, [
            'skillName'      => trim($_POST['skillName']      ?? ''),
            'source'         => trim($_POST['source']         ?? ''),
            'skillLevel'     => (int) ($_POST['skillLevel']   ?? 50),
            'certificateUrl' => trim($_POST['certificateUrl'] ?? ''),
            'validatedAt'    => $_POST['validatedAt']         ?? null,
        ]);
        if ($result['success']) {
            $skills  = $profileController->getSkillsByUserId($userId);
            $profile = $profileController->getByUserId($userId);
            $success = true;
        } else {
            // skill errors are indexed array — key them by position
            foreach ($result['errors'] as $i => $msg) $errors['skill_'.$i] = $msg;
        }
        $tab = 'skills';
    }

    // ── DELETE SKILL ──────────────────────────────────────
    if ($action === 'delete_skill') {
        $profileController->deleteSkill((int)($_POST['skillId'] ?? 0), $userId);
        $skills  = $profileController->getSkillsByUserId($userId);
        $profile = $profileController->getByUserId($userId);
        $success = true;
        $tab     = 'skills';
    }
}

$parts     = explode(' ', $userArr['fullName'] ?? '', 2);
$firstName = $parts[0] ?? '';
$lastName  = $parts[1] ?? '';
$score = $profile ? $profile->getCompletionScore() : 0;
$level = $profile ? $profile->getLevel()           : 'Starter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile — CareerStrand</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#040816;--bg-2:#071126;--bg-card:rgba(255,255,255,0.03);--border:rgba(255,255,255,0.08);--border-hi:rgba(111,143,216,0.35);--text:#f5f3ee;--muted:rgba(245,243,238,0.55);--blue:#6f8fd8;--blue-2:#95abeb;--red:#ff6e45;--green:#4ade80;--shadow:0 24px 80px rgba(0,0,0,0.45);}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:radial-gradient(circle at 10% 15%,rgba(111,143,216,.10) 0%,transparent 40%),radial-gradient(circle at 90% 80%,rgba(255,110,69,.07) 0%,transparent 35%),linear-gradient(160deg,#030712 0%,#071126 55%,#090514 100%);color:var(--text);min-height:100vh;}
    .site-header{position:sticky;top:0;z-index:50;background:rgba(4,8,22,.70);border-bottom:1px solid var(--border);backdrop-filter:blur(18px);}
    .header-inner{max-width:1200px;margin:0 auto;padding:0 24px;height:68px;display:flex;align-items:center;justify-content:space-between;}
    .brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
    .brand-logo{height:48px;width:auto;display:block;}
    .nav-right{display:flex;align-items:center;gap:12px;}
    .btn{padding:9px 20px;border-radius:999px;font-weight:600;font-size:13px;cursor:pointer;border:none;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
    .btn-ghost{background:var(--bg-card);border:1px solid var(--border);color:var(--muted);}
    .btn-ghost:hover{border-color:var(--border-hi);color:var(--text);}
    .btn-main{background:linear-gradient(90deg,var(--blue),var(--red));color:#fff;box-shadow:0 8px 24px rgba(255,110,69,.2);}
    .btn-main:hover{filter:brightness(1.1);transform:translateY(-1px);}
    .page-wrap{max-width:1200px;margin:0 auto;padding:40px 24px 80px;display:grid;grid-template-columns:300px 1fr;gap:28px;align-items:start;}
    .sidebar-card{background:var(--bg-card);border:1px solid var(--border);border-radius:24px;padding:28px;position:sticky;top:88px;}
    .avatar-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;margin-bottom:24px;}
    .avatar{width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--red));display:grid;place-items:center;font-family:'Syne',sans-serif;font-size:30px;font-weight:800;color:#fff;border:3px solid var(--border-hi);overflow:hidden;}
    .avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .user-name{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;text-align:center;}
    .user-email{font-size:13px;color:var(--muted);text-align:center;word-break:break-all;}
    .role-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;background:rgba(111,143,216,.14);border:1px solid rgba(111,143,216,.3);color:var(--blue-2);}
    .progress-wrap{margin:20px 0;}
    .progress-label{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:8px;}
    .progress-label strong{color:var(--text);}
    .progress-bar{height:6px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden;}
    .progress-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--blue),var(--red));transition:width .6s ease;}
    .level-badge{display:flex;align-items:center;justify-content:center;padding:8px 0;border-radius:14px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:var(--green);font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;}
    .info-rows{margin-top:20px;display:flex;flex-direction:column;gap:12px;}
    .info-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;}
    .info-row span{color:var(--muted);}
    .info-row strong{color:var(--text);text-align:right;max-width:160px;word-break:break-word;}
    .sidebar-divider{height:1px;background:var(--border);margin:20px 0;}
    .main-panel{display:flex;flex-direction:column;gap:24px;}
    .tabs{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:16px;padding:5px;width:fit-content;}
    .tab-btn{padding:9px 22px;border-radius:12px;font-size:13px;font-weight:600;border:none;background:transparent;color:var(--muted);cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;}
    .tab-btn.active{background:rgba(111,143,216,.18);color:var(--text);border:1px solid var(--border-hi);}
    .tab-btn:hover:not(.active){color:var(--text);}
    .tab-panel{display:none;} .tab-panel.active{display:block;}
    .form-card{background:var(--bg-card);border:1px solid var(--border);border-radius:24px;padding:32px;}
    .card-header{margin-bottom:28px;}
    .card-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:700;margin-bottom:4px;}
    .card-sub{font-size:13px;color:var(--muted);}
    .field-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    .field-full{grid-column:span 2;}
    .field{display:flex;flex-direction:column;gap:6px;}
    .field label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);}
    .field input,.field textarea,.field select{padding:13px 16px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;color:var(--text);font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s;resize:vertical;}
    .field input:focus,.field textarea:focus,.field select:focus{border-color:var(--blue);background:rgba(111,143,216,.06);box-shadow:0 0 0 4px rgba(111,143,216,.10);}
    /* red border on invalid field */
    .field.has-error input,.field.has-error textarea,.field.has-error select{border-color:var(--red);background:rgba(255,110,69,.04);}
    /* error message under the input */
    .field-error{font-size:12px;color:var(--red);margin-top:2px;display:flex;align-items:center;gap:5px;}
    .field-error::before{content:'⚠';font-size:11px;}
    .field textarea{min-height:100px;}
    .field select option{background:#071126;color:var(--text);}
    .section-sep{grid-column:span 2;border:none;border-top:1px solid var(--border);margin:8px 0;}
    .section-sub-title{grid-column:span 2;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:var(--blue-2);margin-bottom:-4px;}
    .form-actions{display:flex;gap:12px;margin-top:28px;flex-wrap:wrap;}
    .alert{padding:14px 18px;border-radius:14px;font-size:14px;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
    .alert-success{background:rgba(74,222,128,.10);border:1px solid rgba(74,222,128,.25);color:var(--green);}
    .skills-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:28px;}
    .skill-card{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:10px;transition:border-color .2s;}
    .skill-card:hover{border-color:var(--border-hi);}
    .skill-top{display:flex;justify-content:space-between;align-items:flex-start;}
    .skill-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;}
    .skill-source{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);}
    .skill-cert a{color:var(--blue-2);text-decoration:none;font-size:12px;}
    .skill-cert a:hover{text-decoration:underline;}
    .skill-date{font-size:11px;color:var(--muted);}
    .skill-delete{background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;transition:.2s;}
    .skill-delete:hover{background:rgba(255,110,69,.15);color:var(--red);}
    .add-skill-form{background:rgba(111,143,216,.05);border:1px dashed rgba(111,143,216,.25);border-radius:20px;padding:24px;margin-top:8px;}
    .add-skill-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;margin-bottom:18px;color:var(--blue-2);}
    .empty-state{text-align:center;padding:48px 24px;color:var(--muted);}
    .empty-icon{font-size:40px;margin-bottom:12px;}
    .skill-errors{background:rgba(255,110,69,.08);border:1px solid rgba(255,110,69,.25);border-radius:14px;padding:12px 16px;margin-bottom:16px;}
    .skill-errors li{font-size:13px;color:var(--red);margin-left:16px;}
    @media(max-width:860px){.page-wrap{grid-template-columns:1fr;}.sidebar-card{position:static;}.field-grid{grid-template-columns:1fr;}.field-full,.section-sep,.section-sub-title{grid-column:span 1;}}
    @media(max-width:480px){.form-card{padding:20px;}.tabs{width:100%;}.tab-btn{flex:1;text-align:center;}}
    @keyframes spin{to{transform:rotate(360deg)}}
  </style>
  <link rel="stylesheet" href="assets/css/front-nav.css">
</head>
<body>
  <?php
    $activePage = 'profile';
    $brandSubtitle = 'profile command';
    include __DIR__ . '/partials/front-nav.php';
  ?>

  <div class="page-wrap">

    <!-- SIDEBAR -->
    <aside class="sidebar-card">
      <div class="avatar-wrap">
        <div class="avatar">
          <?php if ($profile && !empty($profile->getPhotoUrl())): ?>
            <img src="<?php echo htmlspecialchars($profile->getPhotoUrl()); ?>" alt="Photo">
          <?php else: ?>
            <?php echo strtoupper(substr($userArr['fullName'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="user-name"><?php echo htmlspecialchars($userArr['fullName']); ?></div>
          <div class="user-email"><?php echo htmlspecialchars($userArr['email']); ?></div>
        </div>
        <span class="role-chip"><?php echo htmlspecialchars(ucfirst($userArr['role'])); ?></span>
      </div>
      <div class="progress-wrap">
        <div class="progress-label"><span>Profile completion</span><strong><?php echo $score; ?>%</strong></div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $score; ?>%"></div></div>
      </div>
      <div class="level-badge">⚡ <?php echo htmlspecialchars($level); ?></div>
      <div class="info-rows">
        <?php if ($profile && !empty($profile->getLocation())): ?>
        <div class="info-row"><span>Location</span><strong><?php echo htmlspecialchars($profile->getLocation()); ?></strong></div>
        <?php endif; ?>
        <div class="info-row"><span>Member since</span><strong><?php echo $userArr['createdAt'] ? date('M Y', strtotime($userArr['createdAt'])) : '—'; ?></strong></div>
        <div class="info-row"><span>Skills</span><strong><?php echo count($skills); ?> added</strong></div>
        <div class="info-row">
          <span>Status</span>
          <strong style="color:<?php echo $userArr['status']==='active'?'var(--green)':'var(--red)'; ?>"><?php echo ucfirst($userArr['status']); ?></strong>
        </div>
        <?php if ($role === 'manager' && $managerProfile): ?>
          <div class="info-row"><span>Organization</span><strong><?php echo htmlspecialchars($managerProfile->getOrganization()); ?></strong></div>
          <div class="info-row"><span>Focus</span><strong><?php echo htmlspecialchars($managerProfile->getCategoryFocus()); ?></strong></div>
        <?php endif; ?>
        <?php if ($role === 'manager recruiter' && $recruiterProfile): ?>
          <div class="info-row"><span>Company</span><strong><?php echo htmlspecialchars($recruiterProfile->getCompanyName()); ?></strong></div>
          <div class="info-row"><span>Position</span><strong><?php echo htmlspecialchars($recruiterProfile->getJobTitle()); ?></strong></div>
        <?php endif; ?>
      </div>
      <?php if ($profile && !empty($profile->getBio())): ?>
      <div class="sidebar-divider"></div>
      <p style="font-size:13px;color:var(--muted);line-height:1.6;"><?php echo htmlspecialchars($profile->getBio()); ?></p>
      <?php endif; ?>
    </aside>

    <!-- MAIN PANEL -->
    <main class="main-panel">
      <div class="tabs">
        <button class="tab-btn <?php echo $tab==='profile'?'active':''; ?>" onclick="switchTab('profile')">Profile Info</button>
        <button class="tab-btn <?php echo $tab==='skills'?'active':''; ?>" onclick="switchTab('skills')">
          Skills <span style="background:rgba(111,143,216,.2);border-radius:999px;padding:1px 7px;font-size:11px;margin-left:4px;"><?php echo count($skills); ?></span>
        </button>
        <button class="tab-btn <?php echo $tab==='faceid'?'active':''; ?>" onclick="switchTab('faceid')">
          Face ID <?php if($userObj && $userObj->isFaceEnabled()): ?><span style="background:rgba(74,222,128,.2);border-radius:999px;padding:1px 7px;font-size:11px;margin-left:4px;color:#4ade80;">ON</span><?php endif; ?>
        </button>
      </div>

      <!-- ══════════════ PROFILE TAB ══════════════ -->
      <div id="tab-profile" class="tab-panel <?php echo $tab==='profile'?'active':''; ?>">
        <div class="form-card">
          <div class="card-header">
            <div class="card-title">Personal Information</div>
            <div class="card-sub">Update your name, email, photo and bio.</div>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success">✓ Profile updated successfully!</div>
          <?php endif; ?>

          <form method="POST" action="?tab=profile">
            <input type="hidden" name="_action" value="update_profile">
            <div class="field-grid">

              <!-- First Name -->
              <div class="field <?php echo isset($errors['firstName'])?'has-error':''; ?>">
                <label>First Name</label>
                <input type="text" name="firstName"
                       value="<?php echo htmlspecialchars($_POST['firstName'] ?? $firstName); ?>">
                <?php if (isset($errors['firstName'])): ?>
                  <span class="field-error"><?php echo htmlspecialchars($errors['firstName']); ?></span>
                <?php endif; ?>
              </div>

              <!-- Last Name -->
              <div class="field <?php echo isset($errors['lastName'])?'has-error':''; ?>">
                <label>Last Name</label>
                <input type="text" name="lastName"
                       value="<?php echo htmlspecialchars($_POST['lastName'] ?? $lastName); ?>">
                <?php if (isset($errors['lastName'])): ?>
                  <span class="field-error"><?php echo htmlspecialchars($errors['lastName']); ?></span>
                <?php endif; ?>
              </div>

              <!-- Email -->
              <div class="field field-full <?php echo isset($errors['email'])?'has-error':''; ?>">
                <label>Email Address</label>
                <input type="text" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? $userArr['email']); ?>">
                <?php if (isset($errors['email'])): ?>
                  <span class="field-error"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
              </div>

              <!-- Photo URL -->
              <div class="field field-full">
                <label>Profile Photo URL</label>
                <input type="text" name="photoUrl"
                       value="<?php echo htmlspecialchars($_POST['photoUrl'] ?? ($profile?$profile->getPhotoUrl():'')); ?>"
                       placeholder="https://...">
              </div>

              <!-- Bio -->
              <div class="field field-full">
                <label>Bio</label>
                <textarea name="bio" placeholder="A short description about you..."><?php echo htmlspecialchars($_POST['bio'] ?? ($profile?$profile->getBio():'')); ?></textarea>
              </div>

              <!-- Location -->
              <div class="field">
                <label>Location</label>
                <input type="text" name="location"
                       value="<?php echo htmlspecialchars($_POST['location'] ?? ($profile?$profile->getLocation():'')); ?>"
                       placeholder="City, Country">
              </div>

              <!-- Preferences -->
              <div class="field">
                <label>Preferences</label>
                <input type="text" name="preferences"
                       value="<?php echo htmlspecialchars($_POST['preferences'] ?? ($profile?$profile->getPreferences():'')); ?>"
                       placeholder="Remote, Design, Tech...">
              </div>

              <!-- ── MANAGER FIELDS ── -->
              <?php if ($role === 'manager'): ?>
                <hr class="section-sep">
                <span class="section-sub-title">Organization Details</span>

                <div class="field field-full <?php echo isset($errors['organization'])?'has-error':''; ?>">
                  <label>Organization / Club Name</label>
                  <input type="text" name="organization"
                         value="<?php echo htmlspecialchars($_POST['organization'] ?? ($managerProfile?$managerProfile->getOrganization():'')); ?>"
                         placeholder="e.g. TechClub Tunis">
                  <?php if (isset($errors['organization'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['organization']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="field <?php echo isset($errors['categoryFocus'])?'has-error':''; ?>">
                  <label>Category Focus</label>
                  <select name="categoryFocus">
                    <option value="">Select…</option>
                    <?php foreach (['Technology','Design','Business','Science','Arts','Other'] as $opt): ?>
                      <option value="<?php echo $opt; ?>"
                        <?php echo (($_POST['categoryFocus']??($managerProfile?$managerProfile->getCategoryFocus():''))===$opt)?'selected':''; ?>>
                        <?php echo $opt; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['categoryFocus'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['categoryFocus']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="field">
                  <label>Short Description</label>
                  <input type="text" name="orgDescription"
                         value="<?php echo htmlspecialchars($_POST['orgDescription'] ?? ($managerProfile?$managerProfile->getDescription():'')); ?>"
                         placeholder="What does your organization do?">
                </div>
              <?php endif; ?>

              <!-- ── RECRUITER FIELDS ── -->
              <?php if ($role === 'manager recruiter'): ?>
                <hr class="section-sep">
                <span class="section-sub-title">Company & Recruiting Details</span>

                <div class="field <?php echo isset($errors['companyName'])?'has-error':''; ?>">
                  <label>Company Name</label>
                  <input type="text" name="companyName"
                         value="<?php echo htmlspecialchars($_POST['companyName'] ?? ($recruiterProfile?$recruiterProfile->getCompanyName():'')); ?>"
                         placeholder="e.g. TechCorp">
                  <?php if (isset($errors['companyName'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['companyName']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="field <?php echo isset($errors['jobTitle'])?'has-error':''; ?>">
                  <label>Your Job Title</label>
                  <input type="text" name="jobTitle"
                         value="<?php echo htmlspecialchars($_POST['jobTitle'] ?? ($recruiterProfile?$recruiterProfile->getJobTitle():'')); ?>"
                         placeholder="e.g. HR Manager">
                  <?php if (isset($errors['jobTitle'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['jobTitle']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="field <?php echo isset($errors['industry'])?'has-error':''; ?>">
                  <label>Industry / Sector</label>
                  <select name="industry">
                    <option value="">Select…</option>
                    <?php foreach (['Software & IT','Finance','Healthcare','Education','Marketing','Other'] as $opt): ?>
                      <option value="<?php echo $opt; ?>"
                        <?php echo (($_POST['industry']??($recruiterProfile?$recruiterProfile->getIndustry():''))===$opt)?'selected':''; ?>>
                        <?php echo $opt; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['industry'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['industry']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="field <?php echo isset($errors['companyWebsite'])?'has-error':''; ?>">
                  <label>Company Website</label>
                  <input type="text" name="companyWebsite"
                         value="<?php echo htmlspecialchars($_POST['companyWebsite'] ?? ($recruiterProfile?$recruiterProfile->getCompanyWebsite():'')); ?>"
                         placeholder="https://company.com">
                  <?php if (isset($errors['companyWebsite'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['companyWebsite']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="field field-full">
                  <label>Types of Opportunities Offered</label>
                  <select name="opportunityTypes">
                    <option value="">Select…</option>
                    <?php foreach (['Internship','Job','Freelance','Internship & Job','All'] as $opt): ?>
                      <option value="<?php echo $opt; ?>"
                        <?php echo (($_POST['opportunityTypes']??($recruiterProfile?$recruiterProfile->getOpportunityTypes():''))===$opt)?'selected':''; ?>>
                        <?php echo $opt; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>

            </div><!-- end field-grid -->

            <!-- Change Password -->
            <div style="margin-top:28px;padding-top:24px;border-top:1px solid var(--border);">
              <div class="card-title" style="font-size:16px;margin-bottom:4px;">Change Password</div>
              <div class="card-sub" style="margin-bottom:20px;">Leave blank to keep your current password.</div>
              <div class="field-grid">
                <div class="field <?php echo isset($errors['password'])?'has-error':''; ?>">
                  <label>New Password</label>
                  <input type="password" name="password" placeholder="Min. 6 characters" autocomplete="new-password">
                  <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="field <?php echo isset($errors['passwordConfirm'])?'has-error':''; ?>">
                  <label>Confirm Password</label>
                  <input type="password" name="passwordConfirm" placeholder="Repeat password" autocomplete="new-password">
                  <?php if (isset($errors['passwordConfirm'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['passwordConfirm']); ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-main">Save Changes</button>
              <a href="profile.php" class="btn btn-ghost">Cancel</a>
            </div>
          </form>
        </div>
      </div>

      <!-- ══════════════ SKILLS TAB ══════════════ -->
      <div id="tab-skills" class="tab-panel <?php echo $tab==='skills'?'active':''; ?>">
        <div class="form-card">
          <div class="card-header">
            <div class="card-title">My Skills</div>
            <div class="card-sub">Add your skills, certifications and validated competencies.</div>
          </div>

          <?php if ($success && $tab==='skills'): ?>
            <div class="alert alert-success">✓ Skills updated successfully!</div>
          <?php endif; ?>

          <!-- Skill errors (shown above the add form) -->
          <?php $skillErrors = array_filter($errors, fn($k) => str_starts_with($k,'skill_'), ARRAY_FILTER_USE_KEY);
                if (!empty($skillErrors)): ?>
            <ul class="skill-errors">
              <?php foreach ($skillErrors as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($skills)): ?>
          <div class="skills-grid">
            <?php foreach ($skills as $skill): ?>
            <div class="skill-card">
              <div class="skill-top">
                <div class="skill-name"><?php echo htmlspecialchars($skill->getSkillName()); ?></div>
                <form method="POST" action="?tab=skills" style="display:inline;">
                  <input type="hidden" name="_action" value="delete_skill">
                  <input type="hidden" name="skillId" value="<?php echo $skill->getUserSkillId(); ?>">
                  <button type="submit" class="skill-delete" onclick="return confirm('Remove this skill?')" title="Remove">✕</button>
                </form>
              </div>
              <?php if ($skill->getSource()): ?><div class="skill-source"><?php echo htmlspecialchars($skill->getSource()); ?></div><?php endif; ?>
              <?php if ($skill->getSkillLevel() !== null): ?><div class="skill-date">Level: <?php echo (int) $skill->getSkillLevel(); ?>/100</div><?php endif; ?>
              <?php if ($skill->getCertificateUrl()): ?>
                <div class="skill-cert"><a href="<?php echo htmlspecialchars($skill->getCertificateUrl()); ?>" target="_blank">View certificate →</a></div>
              <?php endif; ?>
              <?php if ($skill->getValidatedAt()): ?>
                <div class="skill-date">Validated: <?php echo date('d M Y', strtotime($skill->getValidatedAt())); ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <div class="empty-text">No skills added yet. Add your first skill below.</div>
          </div>
          <?php endif; ?>

          <div class="add-skill-form">
            <div class="add-skill-title">+ Add a new skill</div>
            <form method="POST" action="?tab=skills">
              <input type="hidden" name="_action" value="add_skill">
              <div class="field-grid">
                <div class="field <?php echo isset($errors['skill_0'])?'has-error':''; ?>">
                  <label>Skill Name *</label>
                  <input type="text" name="skillName"
                         value="<?php echo htmlspecialchars($_POST['skillName'] ?? ''); ?>"
                         placeholder="e.g. UI/UX Design">
                  <?php if (isset($errors['skill_0'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['skill_0']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="field <?php echo isset($errors['skill_1'])?'has-error':''; ?>">
                  <label>Source *</label>
                  <input type="text" name="source"
                         value="<?php echo htmlspecialchars($_POST['source'] ?? ''); ?>"
                         placeholder="e.g. Coursera, Self-taught">
                  <?php if (isset($errors['skill_1'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['skill_1']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label>Skill Level</label>
                  <input type="number" name="skillLevel" min="0" max="100"
                         value="<?php echo htmlspecialchars($_POST['skillLevel'] ?? '50'); ?>"
                         placeholder="50">
                </div>
                <div class="field field-full <?php echo isset($errors['skill_2'])?'has-error':''; ?>">
                  <label>Certificate URL *</label>
                  <input type="text" name="certificateUrl"
                         value="<?php echo htmlspecialchars($_POST['certificateUrl'] ?? ''); ?>"
                         placeholder="https://example.com/certificate">
                  <?php if (isset($errors['skill_2'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['skill_2']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="field <?php echo isset($errors['skill_3'])?'has-error':''; ?>">
                  <label>Validated Date *</label>
                  <input type="date" name="validatedAt"
                         value="<?php echo htmlspecialchars($_POST['validatedAt'] ?? date('Y-m-d')); ?>"
                         max="<?php echo date('Y-m-d'); ?>">
                  <?php if (isset($errors['skill_3'])): ?>
                    <span class="field-error"><?php echo htmlspecialchars($errors['skill_3']); ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="form-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-main">Add Skill</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ══════════════ FACE ID TAB ══════════════ -->
      <div id="tab-faceid" class="tab-panel <?php echo $tab==='faceid'?'active':''; ?>">
    <div class="form-card">
      <div class="card-header">
        <div class="card-title">Face ID</div>
        <div class="card-sub">Register your face to log in without a password.</div>
      </div>

      <!-- Status banner -->
      <div id="faceid-status" style="padding:14px 18px;border-radius:14px;font-size:14px;margin-bottom:24px;display:flex;align-items:center;gap:12px;
        background:<?php echo ($userObj && $userObj->isFaceEnabled()) ? 'rgba(74,222,128,.10)' : 'rgba(255,255,255,.03)'; ?>;
        border:1px solid <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'rgba(74,222,128,.25)' : 'rgba(255,255,255,.08)'; ?>;
        color:<?php echo ($userObj && $userObj->isFaceEnabled()) ? 'var(--green)' : 'var(--muted)'; ?>;">
        <span style="font-size:20px;"><?php echo ($userObj && $userObj->isFaceEnabled()) ? '✓' : '○'; ?></span>
        <div>
          <strong><?php echo ($userObj && $userObj->isFaceEnabled()) ? 'Face ID is active' : 'Face ID not configured'; ?></strong>
          <div style="font-size:12px;margin-top:2px;">
            <?php echo ($userObj && $userObj->isFaceEnabled())
              ? 'You can log in using your face on the login page.'
              : 'Capture your face below then enable Face ID.'; ?>
          </div>
        </div>
      </div>

      <!-- Camera -->
      <div style="position:relative;width:100%;max-width:480px;margin:0 auto 20px;">
        <video id="face-video" style="width:100%;border-radius:16px;border:2px solid var(--border);background:#000;display:block;" autoplay muted playsinline></video>
        <canvas id="face-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:16px;pointer-events:none;"></canvas>
        <div id="face-loading" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(4,8,22,.7);border-radius:16px;color:var(--muted);font-size:13px;gap:10px;">
          <div style="width:28px;height:28px;border:2px solid var(--blue);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;"></div>
          Loading face models…
        </div>
      </div>

      <div id="face-msg" style="text-align:center;min-height:24px;font-size:13px;margin-bottom:16px;color:var(--muted);"></div>

      <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:28px;">
        <button id="btn-start-cam" class="btn btn-ghost" onclick="startFaceCam()" disabled>Start camera</button>
        <button id="btn-capture"   class="btn btn-main"  onclick="captureFace()"  disabled>Capture my face</button>
      </div>

      <!-- Enable / Disable toggle -->
      <div style="padding:18px;border-radius:16px;border:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:16px;">
        <div>
          <div style="font-size:14px;font-weight:600;">Enable Face ID login</div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px;">You must capture your face first before enabling.</div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <div style="position:relative;width:44px;height:24px;">
            <input type="checkbox" id="faceid-toggle"
                   <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'checked' : ''; ?>
                   onchange="toggleFaceId(this.checked)"
                   style="position:absolute;opacity:0;width:100%;height:100%;cursor:pointer;margin:0;">
            <div id="toggle-track" style="width:44px;height:24px;border-radius:12px;transition:background .2s;background:<?php echo ($userObj && $userObj->isFaceEnabled()) ? '#6f8fd8' : 'rgba(255,255,255,.15)'; ?>;"></div>
            <div id="toggle-thumb" style="position:absolute;top:3px;left:<?php echo ($userObj && $userObj->isFaceEnabled()) ? '23px' : '3px'; ?>;width:18px;height:18px;border-radius:50%;background:#fff;transition:left .2s;"></div>
          </div>
          <span id="toggle-label" style="font-size:13px;color:var(--muted);"><?php echo ($userObj && $userObj->isFaceEnabled()) ? 'Enabled' : 'Disabled'; ?></span>
        </label>
      </div>
    </div>
  </div>
    </main>
  </div>

  

  <script>
    function switchTab(tabName) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

      const activeBtn = document.querySelector('.tab-btn[onclick*="' + tabName + '"]');
      if (activeBtn) activeBtn.classList.add('active');

      const activePanel = document.getElementById('tab-' + tabName);
      if (activePanel) activePanel.classList.add('active');
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script>
  (function(){
    const MODEL_URL = 'assets/models';
    const API_URL   = '../../api/face.php';
    let modelsReady = false, stream = null;

    const video    = document.getElementById('face-video');
    const overlay  = document.getElementById('face-overlay');
    const loading  = document.getElementById('face-loading');
    const msg      = document.getElementById('face-msg');
    const btnStart = document.getElementById('btn-start-cam');
    const btnCap   = document.getElementById('btn-capture');

    function setMsg(text, type) {
      if (!msg) return;
      const colors = { info:'var(--muted)', success:'var(--green)', error:'var(--red)' };
      msg.style.color = colors[type] || 'var(--muted)';
      msg.textContent = text;
    }

    // Wait until faceapi is available then load models
    async function loadFaceModels() {
      if (typeof faceapi === 'undefined') {
        setTimeout(loadFaceModels, 200);
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
        setMsg('Could not load face models. Check /assets/models/ folder. Error: ' + e.message, 'error');
        console.error('Face model load error:', e);
      }
    }

    window.startFaceCam = async function() {
      if (!modelsReady) { setMsg('Models still loading, please wait...', 'info'); return; }
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        video.srcObject = stream;
        if (btnCap)   btnCap.disabled   = false;
        if (btnStart) btnStart.disabled = true;
        setMsg('Camera active. Look at the camera then click "Capture my face".', 'info');
        detectLoop();
      } catch(e) {
        setMsg('Camera access denied. Allow camera in browser settings.', 'error');
        console.error('Camera error:', e);
      }
    };

    async function detectLoop() {
      if (!stream || !video || !overlay) return;
      try {
        const det = await faceapi
          .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
          .withFaceLandmarks();
        if (det) {
          const dims = faceapi.matchDimensions(overlay, video, true);
          faceapi.draw.drawDetections(overlay, faceapi.resizeResults(det, dims));
        } else {
          overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);
        }
      } catch(e) {}
      requestAnimationFrame(detectLoop);
    }

    window.captureFace = async function() {
      if (!modelsReady) { setMsg('Models still loading...', 'info'); return; }
      if (!stream)      { setMsg('Start the camera first.', 'error'); return; }
      setMsg('Detecting face...', 'info');
      if (btnCap) btnCap.disabled = true;
      try {
        const det = await faceapi
          .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
          .withFaceLandmarks()
          .withFaceDescriptor();
        if (!det) {
          setMsg('No face detected. Make sure face is well lit and centred.', 'error');
          if (btnCap) btnCap.disabled = false;
          return;
        }
        const json = JSON.stringify(Array.from(det.descriptor));
        const fd   = new FormData();
        fd.append('action', 'save');
        fd.append('descriptor', json);
        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          setMsg('✓ Face captured and saved! Now enable Face ID below.', 'success');
        } else {
          setMsg('Error: ' + (data.error || 'Could not save.'), 'error');
        }
      } catch(e) {
        setMsg('Error: ' + e.message, 'error');
        console.error(e);
      }
      if (btnCap) btnCap.disabled = false;
    };

    window.toggleFaceId = async function(enabled) {
      const track = document.getElementById('toggle-track');
      const thumb = document.getElementById('toggle-thumb');
      const label = document.getElementById('toggle-label');
      const fd    = new FormData();
      fd.append('action', 'toggle');
      fd.append('enable', enabled ? 'true' : 'false');
      try {
        const res  = await fetch(API_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          if (track) track.style.background = enabled ? '#6f8fd8' : 'rgba(255,255,255,.15)';
          if (thumb) thumb.style.left       = enabled ? '23px'    : '3px';
          if (label) label.textContent      = enabled ? 'Enabled'  : 'Disabled';
          const banner = document.getElementById('faceid-status');
          if (banner) {
            if (enabled) {
              banner.style.background = 'rgba(74,222,128,.10)';
              banner.style.border     = '1px solid rgba(74,222,128,.25)';
              banner.style.color      = 'var(--green)';
              banner.querySelector('span').textContent   = '✓';
              banner.querySelector('strong').textContent = 'Face ID is active';
            } else {
              banner.style.background = 'rgba(255,255,255,.03)';
              banner.style.border     = '1px solid rgba(255,255,255,.08)';
              banner.style.color      = 'var(--muted)';
              banner.querySelector('span').textContent   = '○';
              banner.querySelector('strong').textContent = 'Face ID not configured';
            }
          }
        } else {
          setMsg(data.error || 'Could not update Face ID.', 'error');
          document.getElementById('faceid-toggle').checked = !enabled;
        }
      } catch(e) {
        setMsg('Network error.', 'error');
        document.getElementById('faceid-toggle').checked = !enabled;
      }
    };

    // Stop camera when switching away from face tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!btn.textContent.includes('Face') && stream) {
          stream.getTracks().forEach(t => t.stop());
          stream = null;
          video.srcObject = null;
          if (btnStart) btnStart.disabled = false;
          if (btnCap)   btnCap.disabled   = true;
        }
      });
    });

    // Load models when Face ID tab is clicked
    const origSwitch = window.switchTab;
    window.switchTab = function(tabName) {
      origSwitch(tabName);
      if (tabName === 'faceid' && !modelsReady) {
        if (loading) loading.style.display = 'flex';
        loadFaceModels();
      }
    };

    // Also load immediately if page loaded on faceid tab
    if (document.getElementById('tab-faceid') && 
        document.getElementById('tab-faceid').classList.contains('active')) {
      loadFaceModels();
    }

  })();
  </script>
</body>
</html>
