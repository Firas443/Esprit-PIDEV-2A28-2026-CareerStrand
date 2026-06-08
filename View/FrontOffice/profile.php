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
$questionnaireStatus = $userObj && method_exists($userObj, 'getQuestionnaireStatus') ? $userObj->getQuestionnaireStatus() : 'pending';
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
                $profileResult = $profileController->createOrUpdate($userId, [
                    'bio'         => trim($_POST['bio']         ?? ''),
                    'location'    => trim($_POST['location']    ?? ''),
                    'preferences' => trim($_POST['preferences'] ?? ''),
                    'careerGoal'  => trim($_POST['careerGoal']  ?? ''),
                    'portfolioTheme' => trim($_POST['portfolioTheme'] ?? 'midnight'),
                    'portfolioPrimaryColor' => trim($_POST['portfolioPrimaryColor'] ?? ''),
                    'portfolioSecondaryColor' => trim($_POST['portfolioSecondaryColor'] ?? ''),
                    'cvTheme' => trim($_POST['cvTheme'] ?? 'graphite'),
                    'cvPrimaryColor' => trim($_POST['cvPrimaryColor'] ?? ''),
                    'cvSecondaryColor' => trim($_POST['cvSecondaryColor'] ?? ''),
                ], $_FILES);

                if (!$profileResult['success']) {
                    foreach (($profileResult['errors'] ?? []) as $field => $msg) {
                        $errors[$field] = $msg;
                    }
                }

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

                if (empty($errors)) {
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
            foreach (($result['errors'] ?? []) as $field => $msg) {
                $errorKey = is_string($field) ? 'skill_' . $field : 'skill_' . $field;
                $errors[$errorKey] = $msg;
            }
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
$themeOptions = ProfileController::themeOptions();
$portfolioTheme = $profile ? ProfileController::normalizeTheme($profile->getPortfolioTheme()) : 'midnight';
$cvTheme = $profile ? ProfileController::normalizeTheme($profile->getCvTheme()) : 'graphite';
$portfolioPalette = ProfileController::themePalette($portfolioTheme);
$cvPalette = ProfileController::themePalette($cvTheme);
$portfolioPrimary = $profile && $profile->getPortfolioPrimaryColor() ? $profile->getPortfolioPrimaryColor() : $portfolioPalette['portfolioPrimary'];
$portfolioSecondary = $profile && $profile->getPortfolioSecondaryColor() ? $profile->getPortfolioSecondaryColor() : $portfolioPalette['portfolioSecondary'];
$cvPrimary = $profile && $profile->getCvPrimaryColor() ? $profile->getCvPrimaryColor() : $cvPalette['cvPrimary'];
$cvSecondary = $profile && $profile->getCvSecondaryColor() ? $profile->getCvSecondaryColor() : $cvPalette['cvSecondary'];
$careerGoal = $profile ? $profile->getCareerGoal() : '';
$portfolioUrl = 'public_portfolio.php?userId=' . $userId;
$cvUrl = 'export_cv.php?userId=' . $userId;
$skillCatalog = $profileController->getSkillCatalog(['active' => 1]);
$skillRequests = $profileController->getSkillRequestsByUserId($userId, ['pending', 'rejected']);
$mentorEligible = $frontUser['userId'] !== $userId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile - CareerStrand</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--bg:#040816;--bg-2:#071126;--bg-card:rgba(255,255,255,0.03);--border:rgba(255,255,255,0.08);--border-hi:rgba(111,143,216,0.35);--text:#f5f3ee;--muted:rgba(245,243,238,0.55);--blue:#6f8fd8;--blue-2:#95abeb;--red:#ff6e45;--green:#4ade80;--shadow:0 24px 80px rgba(0,0,0,0.45);}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:radial-gradient(circle at 10% 15%,rgba(111,143,216,.10) 0%,transparent 40%),radial-gradient(circle at 90% 80%,rgba(255,110,69,.07) 0%,transparent 35%),linear-gradient(160deg,#030712 0%,#071126 55%,#090514 100%);color:var(--text);min-height:100vh;}
    .brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
    .brand-logo{height:48px;width:auto;display:block;}
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
    .skill-suggestions{display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 16px;}
    .skill-suggestion-chip{
      padding:9px 12px;border-radius:999px;border:1px solid rgba(111,143,216,.22);
      background:rgba(111,143,216,.1);color:var(--text);cursor:pointer;font-size:12px;font-weight:700;
      transition:transform .2s ease, background .2s ease, border-color .2s ease;
    }
    .skill-suggestion-chip:hover{transform:translateY(-1px);background:rgba(111,143,216,.16);border-color:rgba(111,143,216,.35);}
    .skill-request-list{display:grid;gap:14px;margin-bottom:18px;}
    .skill-request-card{
      padding:16px 18px;border-radius:18px;border:1px solid rgba(255,255,255,.08);
      background:rgba(255,255,255,.04);display:grid;gap:8px;
    }
    .skill-request-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;}
    .skill-request-meta{color:var(--muted);font-size:12px;}
    .skill-request-status{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;}
    .skill-request-status.pending{background:rgba(255,210,90,.14);color:#ffd66f;border:1px solid rgba(255,210,90,.22);}
    .skill-request-status.rejected{background:rgba(255,110,69,.12);color:#ffb197;border:1px solid rgba(255,110,69,.22);}
    .skill-request-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;}
    .skill-request-actions .btn{padding:8px 14px;min-height:36px;font-size:12px;}
    .empty-state{text-align:center;padding:48px 24px;color:var(--muted);}
    .empty-icon{font-size:40px;margin-bottom:12px;}
    .skill-errors{background:rgba(255,110,69,.08);border:1px solid rgba(255,110,69,.25);border-radius:14px;padding:12px 16px;margin-bottom:16px;}
    .skill-errors li{font-size:13px;color:var(--red);margin-left:16px;}
    @media(max-width:860px){.page-wrap{grid-template-columns:1fr;}.sidebar-card{position:static;}.field-grid{grid-template-columns:1fr;}.field-full,.section-sep,.section-sub-title{grid-column:span 1;}}
    @media(max-width:480px){.form-card{padding:20px;}.tabs{width:100%;}.tab-btn{flex:1;text-align:center;}}
    @keyframes spin{to{transform:rotate(360deg)}}
  </style>
  <style>
    :root{
      --profile-bg:#eef4ec;
      --profile-paper:#fffdf7;
      --profile-ink:#17211d;
      --profile-muted:#65736b;
      --profile-line:#d5dfd6;
      --profile-sage:#7aa585;
      --profile-coral:#e86f4d;
      --profile-gold:#c99c45;
      --profile-clay:#f4d9cc;
      --text:var(--profile-ink);
      --muted:var(--profile-muted);
      --muted-2:#7d8b82;
      --blue:var(--profile-sage);
      --blue-2:#38664d;
      --red:var(--profile-coral);
      --green:#3d8c62;
      --border:var(--profile-line);
      --border-hi:rgba(122,165,133,.55);
    }
    body{
      color:var(--profile-ink);
      background:
        linear-gradient(90deg,rgba(23,33,29,.045) 1px,transparent 1px),
        linear-gradient(rgba(23,33,29,.04) 1px,transparent 1px),
        radial-gradient(circle at 12% 14%,rgba(122,165,133,.28),transparent 28%),
        radial-gradient(circle at 88% 12%,rgba(232,111,77,.18),transparent 25%),
        linear-gradient(135deg,#f8faf3 0%,var(--profile-bg) 58%,#e3eee7 100%);
      background-size:44px 44px,44px 44px,100% 100%,100% 100%,100% 100%;
    }
    body::before,.webgl-neuron,.cinema-bg,.particle-field{display:none!important}
    body::after{
      content:""!important;
      position:fixed!important;
      inset:0!important;
      z-index:-1!important;
      pointer-events:none!important;
      display:block!important;
      opacity:.58!important;
      background-image:
        linear-gradient(rgba(23,33,29,.07) 1px,transparent 1px),
        linear-gradient(90deg,rgba(23,33,29,.06) 1px,transparent 1px)!important;
      background-size:56px 56px,56px 56px!important;
      mask-image:radial-gradient(circle at 50% 34%,#000 0%,rgba(0,0,0,.78) 56%,transparent 92%)!important;
    }
    .page-wrap{
      width:min(1220px,100%);
      display:block;
      padding:0 22px 90px;
    }
    .profile-view{display:block}
    .edit-workbench{display:none}
    .page-wrap.is-editing .profile-view{display:none}
    .page-wrap.is-editing .edit-workbench{display:grid;grid-template-columns:320px minmax(0,1fr);gap:24px;align-items:start}
    .profile-shell{
      background:rgba(255,253,247,.82);
      border:1px solid rgba(23,33,29,.12);
      box-shadow:0 28px 80px rgba(45,63,53,.16);
      border-radius:30px;
      overflow:hidden;
    }
    .profile-cover{
      min-height:250px;
      padding:34px;
      color:#fffdf7;
      background:
        linear-gradient(120deg,rgba(23,33,29,.94),rgba(47,76,58,.9)),
        radial-gradient(circle at 85% 20%,rgba(232,111,77,.55),transparent 34%);
      display:grid;
      grid-template-columns:minmax(0,1fr) auto;
      gap:26px;
      align-items:end;
    }
    .profile-kicker{
      width:max-content;
      max-width:100%;
      margin-bottom:18px;
      padding:8px 13px;
      border:1px solid rgba(255,253,247,.24);
      border-radius:999px;
      color:rgba(255,253,247,.78);
      font-size:10px;
      font-weight:900;
      letter-spacing:.18em;
      text-transform:uppercase;
    }
    .profile-cover h1{
      max-width:720px;
      margin:0;
      font-family:'Syne',sans-serif;
      font-size:clamp(42px,8vw,86px);
      line-height:.88;
      letter-spacing:-.06em;
    }
    .profile-cover p{
      max-width:680px;
      margin-top:20px;
      color:rgba(255,253,247,.74);
      font-size:16px;
      line-height:1.65;
    }
    .profile-mode{
      display:flex;
      gap:10px;
      align-items:center;
      justify-content:flex-end;
      align-self:start;
    }
    .mode-btn,.btn{
      min-height:44px;
      padding:11px 18px;
      border-radius:999px;
      border:1px solid rgba(23,33,29,.14);
      background:#fffdf7;
      color:var(--profile-ink);
      font-weight:900;
      cursor:pointer;
      text-decoration:none;
      transition:.2s ease;
    }
    .mode-btn.primary,.btn-main{
      border-color:transparent;
      color:#fffdf7;
      background:linear-gradient(120deg,var(--profile-ink),#52775e 55%,var(--profile-coral));
      box-shadow:0 16px 34px rgba(23,33,29,.18);
      animation:none;
    }
    .mode-btn:hover,.btn:hover{transform:translateY(-1px)}
    .profile-body{
      display:grid;
      grid-template-columns:330px minmax(0,1fr);
      gap:0;
      min-height:560px;
    }
    .profile-rail{
      padding:28px;
      background:#f7fbf1;
      border-right:1px solid rgba(23,33,29,.1);
    }
    .profile-main-view{padding:30px;display:grid;gap:26px}
    .portrait{
      width:160px;
      height:190px;
      border-radius:24px;
      background:linear-gradient(145deg,var(--profile-sage),var(--profile-coral));
      display:grid;
      place-items:center;
      overflow:hidden;
      color:#fffdf7;
      font-family:'Syne',sans-serif;
      font-size:64px;
      font-weight:900;
      box-shadow:12px 12px 0 #dce8dc;
      margin-bottom:26px;
    }
    .portrait img{width:100%;height:100%;object-fit:cover}
    .profile-name{font-family:'Syne',sans-serif;font-size:30px;line-height:.98;letter-spacing:-.05em;margin-bottom:8px}
    .profile-email{font-size:14px;color:var(--profile-muted);word-break:break-all}
    .profile-tag{
      display:inline-flex;
      margin-top:16px;
      padding:8px 12px;
      border-radius:999px;
      background:#e5f0e6;
      color:#38664d;
      border:1px solid #c9dacb;
      font-size:10px;
      font-weight:900;
      letter-spacing:.16em;
      text-transform:uppercase;
    }
    .rail-list{display:grid;gap:0;margin-top:26px;border-top:1px solid rgba(23,33,29,.1)}
    .rail-row{padding:15px 0;border-bottom:1px solid rgba(23,33,29,.1)}
    .rail-row span,.section-label{display:block;color:var(--profile-muted);font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;margin-bottom:5px}
    .rail-row strong{display:block;font-size:15px;line-height:1.35}
    .completion-stamp{
      margin-top:26px;
      padding:18px;
      border:1px solid rgba(23,33,29,.12);
      border-radius:22px;
      background:#fffdf7;
    }
    .completion-stamp strong{font-size:38px;line-height:1;font-family:'Syne',sans-serif}
    .completion-stamp span{display:block;margin-top:6px;color:var(--profile-muted);font-size:12px;font-weight:800}
    .view-section{
      padding:26px;
      border:1px solid rgba(23,33,29,.1);
      border-radius:26px;
      background:#fffdf7;
    }
    .view-section h2{
      font-family:'Syne',sans-serif;
      font-size:28px;
      line-height:1;
      letter-spacing:-.045em;
      margin-bottom:14px;
    }
    .bio-text{font-size:17px;line-height:1.75;color:#33413a}
    .profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .fact{
      padding:18px;
      border-radius:20px;
      background:#f7fbf1;
      border:1px solid rgba(23,33,29,.08);
    }
    .fact strong{display:block;font-size:16px}
    .skill-strip{display:flex;flex-wrap:wrap;gap:10px}
    .skill-pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding:10px 13px;
      border-radius:999px;
      background:#17211d;
      color:#fffdf7;
      font-weight:900;
      font-size:13px;
    }
    .skill-pill span{color:#f4b08f;font-size:12px}
    .empty-note{color:var(--profile-muted);font-size:15px;line-height:1.6}
    .security-line{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:18px;
      padding:18px;
      border-radius:22px;
      background:#f7fbf1;
      border:1px solid rgba(23,33,29,.08);
    }
    .security-mark{
      width:54px;
      height:54px;
      border-radius:50%;
      display:grid;
      place-items:center;
      flex:0 0 auto;
      color:#fffdf7;
      background:var(--profile-coral);
      font-weight:900;
    }
    .security-mark.on{background:#3d8c62}
    .sidebar-card,.form-card,.profile-hero{
      background:#fffdf7;
      border:1px solid rgba(23,33,29,.12);
      box-shadow:0 18px 50px rgba(45,63,53,.12);
    }
    .sidebar-card::before,.form-card::before,.profile-hero::before{display:none}
    .sidebar-card{position:sticky;top:108px;background:#f7fbf1}
    .avatar{border-radius:24px;box-shadow:8px 8px 0 #dce8dc}
    .profile-hero{display:none}
    .tabs{margin-bottom:0}
    .tab-btn{background:#fffdf7;color:var(--profile-ink);border-color:rgba(23,33,29,.14);box-shadow:none}
    .tab-btn.active{background:var(--profile-ink);color:#fffdf7;border-color:var(--profile-ink);box-shadow:none}
    .tab-count,.status-pill{background:#e5f0e6;color:#38664d;border-color:#c9dacb}
    .form-card input,.form-card textarea,.form-card select,.field input,.field textarea,.field select{
      background:#f7fbf1;
      color:var(--profile-ink);
      border-color:rgba(23,33,29,.14);
    }
    .form-card input:focus,.form-card textarea:focus,.form-card select:focus,.field input:focus,.field textarea:focus,.field select:focus{
      border-color:var(--profile-sage);
      background:#fffdf7;
      box-shadow:0 0 0 4px rgba(122,165,133,.16);
    }
    .skill-card,.add-skill-form,.empty-state,.face-status,.toggle-card,.face-tip{background:#f7fbf1;border-color:rgba(23,33,29,.1)}
    .skill-source{background:#e5f0e6;color:#38664d;border-color:#c9dacb}
    .skill-meter span{background:linear-gradient(90deg,var(--profile-sage),var(--profile-coral))}
    .face-camera{border-color:rgba(23,33,29,.16)}
    @media(max-width:920px){
      .profile-cover,.profile-body,.page-wrap.is-editing .edit-workbench{grid-template-columns:1fr}
      .profile-rail{border-right:0;border-bottom:1px solid rgba(23,33,29,.1)}
      .profile-mode{justify-content:flex-start}
    }
    @media(max-width:640px){
      .page-wrap{padding:22px 14px 70px}
      .profile-cover,.profile-rail,.profile-main-view{padding:22px}
      .profile-grid{grid-template-columns:1fr}
      .profile-mode{flex-direction:column;align-items:stretch}
      .mode-btn{width:100%}
      .profile-cover h1{font-size:44px}
      .security-line{align-items:flex-start;flex-direction:column}
    }
  </style>
  <style>
    :root{
      --bg:#030611;
      --card:#070d1d;
      --panel:#0a1124;
      --panel-2:#0d1831;
      --text:#f8fbff;
      --muted:rgba(232,239,255,.72);
      --muted-2:rgba(232,239,255,.48);
      --border:rgba(255,255,255,.10);
      --border-hi:rgba(111,143,216,.42);
      --blue:#6f8fd8;
      --blue-2:#9db7ff;
      --orange:#ff6e45;
      --red:#ff6e45;
      --green:#4ade80;
      --shadow:0 30px 90px rgba(0,0,0,.42);
    }
    html{min-height:100%}
    body{
      min-height:100vh;
      font-family:'DM Sans',Inter,Arial,sans-serif;
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
    button,input,select,textarea{font:inherit}
    input,select,textarea{color-scheme:dark}
    .cinema-bg,.particle-field,.webgl-neuron{position:fixed;inset:0;pointer-events:none}
    .webgl-neuron{width:100%;height:100%;z-index:-3;opacity:.62}
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
    .btn{min-height:44px;padding:11px 20px;border-radius:999px;font-weight:850;font-size:13px;cursor:pointer;border:0;transition:.22s ease;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}
    .btn-ghost{background:#0b1225;border:1px solid var(--border);color:var(--muted)}
    .btn-ghost:hover{border-color:var(--border-hi);color:var(--text);transform:translateY(-1px)}
    .btn-main{background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 30%,#ff7a50 70%,#ffae6c 100%);background-size:220% 100%;color:#fff;box-shadow:0 18px 42px rgba(255,110,69,.22),0 13px 36px rgba(111,143,216,.22),inset 0 1px 0 rgba(255,255,255,.34);animation:gradientFlow 7s ease infinite}
    .btn-main:hover{filter:saturate(1.12);transform:translateY(-2px)}
    .page-wrap{width:min(1280px,100%);max-width:none;margin:0 auto;padding:32px 22px 86px;display:grid;grid-template-columns:340px minmax(0,1fr);gap:28px;align-items:start;animation:pageIn .8s ease both}
    .sidebar-card,.form-card,.profile-hero{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow)}
    .sidebar-card::before,.form-card::before,.profile-hero::before{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(180deg,rgba(255,255,255,.02),transparent 30%),radial-gradient(circle at 10% 0%,rgba(111,143,216,.11),transparent 38%),radial-gradient(circle at 100% 100%,rgba(255,110,69,.08),transparent 40%)}
    .sidebar-card{padding:28px;position:sticky;top:108px}
    .avatar-wrap{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:14px;margin-bottom:24px;text-align:center}
    .avatar{width:116px;height:116px;border-radius:32px;background:linear-gradient(135deg,var(--blue),var(--orange));display:grid;place-items:center;font-family:'Syne',sans-serif;font-size:42px;font-weight:800;color:#fff;border:1px solid rgba(255,255,255,.24);box-shadow:0 22px 48px rgba(0,0,0,.34),0 0 46px rgba(111,143,216,.22);overflow:hidden}
    .avatar img{width:100%;height:100%;object-fit:cover;border-radius:0}
    .user-name{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;line-height:1.05;letter-spacing:-.03em}
    .user-email{font-size:13px;color:var(--muted);word-break:break-all;margin-top:5px}
    .role-chip,.tab-count,.status-pill,.eyebrow{display:inline-flex;align-items:center;gap:8px;border-radius:999px;border:1px solid rgba(207,222,255,.16);background:#11182b;color:rgba(232,239,255,.78);text-transform:uppercase;letter-spacing:.14em;font-size:10px;font-weight:900}
    .role-chip{padding:7px 13px;color:var(--blue-2);border-color:rgba(111,143,216,.3)}
    .progress-wrap{position:relative;z-index:1;margin:22px 0}
    .progress-label{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:9px}
    .progress-label strong{color:var(--text)}
    .progress-bar{height:9px;background:#0b1225;border:1px solid rgba(207,222,255,.1);border-radius:999px;overflow:hidden}
    .progress-fill{height:100%;border-radius:999px;background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 35%,#ff7a50 100%);transition:width .6s ease}
    .level-badge{position:relative;z-index:1;display:flex;justify-content:center;padding:10px 12px;border-radius:16px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:var(--green);font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;text-align:center}
    .info-rows{position:relative;z-index:1;margin-top:22px;display:grid;gap:0}
    .info-row{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px}
    .info-row span{color:var(--muted-2);font-weight:800;text-transform:uppercase;letter-spacing:.12em;font-size:10px}
    .info-row strong{color:var(--text);text-align:right;max-width:170px;word-break:break-word}
    .sidebar-divider{position:relative;z-index:1;height:1px;background:var(--border);margin:20px 0}
    .sidebar-bio{position:relative;z-index:1;font-size:13px;color:var(--muted);line-height:1.7}
    .main-panel{display:flex;flex-direction:column;gap:22px;min-width:0}
    .profile-hero{padding:30px;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(260px,.75fr);gap:24px;align-items:end}
    .profile-hero>*{position:relative;z-index:1}
    .eyebrow{width:max-content;padding:8px 14px;margin-bottom:16px}
    .eyebrow::before{content:"";width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--blue-2),var(--orange));box-shadow:0 0 18px rgba(111,143,216,.75)}
    .hero-title{font-family:'Syne',sans-serif;font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.055em;margin-bottom:13px}
    .hero-title span{background:linear-gradient(100deg,var(--blue-2) 0%,#dfe8ff 35%,var(--orange) 92%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
    .hero-copy{max-width:650px;color:var(--muted);font-size:15px;line-height:1.7}
    .hero-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .hero-stat{padding:16px;border-radius:18px;background:#0a1124;border:1px solid var(--border);min-height:86px}
    .hero-stat strong{display:block;font-size:28px;font-weight:900;line-height:1;margin-bottom:8px}
    .hero-stat span{display:block;color:var(--muted-2);font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
    .tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;background:transparent;border:0;border-radius:0;padding:0;width:100%}
    .tab-btn{min-height:54px;padding:12px 16px;border-radius:999px;border:1px solid rgba(207,222,255,.14);background:#0b1225;color:var(--muted);cursor:pointer;transition:.22s ease;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;white-space:nowrap}
    .tab-btn.active{color:#fff;border-color:rgba(157,183,255,.55);background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 36%,#ff7a50 100%);box-shadow:0 14px 34px rgba(111,143,216,.22),0 12px 30px rgba(255,110,69,.12)}
    .tab-btn:hover:not(.active){color:var(--text);border-color:rgba(207,222,255,.26);background:#10182c;transform:translateY(-1px)}
    .tab-count{padding:3px 8px;font-size:9px;letter-spacing:0;background:rgba(255,255,255,.1);color:#fff}
    .status-pill{padding:3px 8px;font-size:9px;letter-spacing:0;color:var(--green);background:rgba(74,222,128,.14);border-color:rgba(74,222,128,.25)}
    .tab-panel.active{display:block;animation:fieldIn .35s ease both}
    .form-card{padding:34px}
    .form-card>*{position:relative;z-index:1}
    .card-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:28px}
    .card-title{font-family:'Syne',sans-serif;font-size:26px;line-height:1;font-weight:800;letter-spacing:-.04em;margin-bottom:7px}
    .card-sub{font-size:13px;color:var(--muted);line-height:1.55}
    .field-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px}
    .field{gap:9px;min-width:0}
    .field label{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.2em;color:var(--muted-2)}
    .field input,.field textarea,.field select{width:100%;min-height:54px;padding:15px 16px;background:#0a1124;border:1px solid var(--border);border-radius:16px;color:var(--text);font-size:15px;outline:none;transition:.22s ease;resize:vertical}
    .field input:hover,.field textarea:hover,.field select:hover{border-color:rgba(255,255,255,.18);background:#0c1429}
    .field input:focus,.field textarea:focus,.field select:focus{border-color:var(--blue);background:rgba(111,143,216,.07);box-shadow:0 0 0 4px rgba(111,143,216,.10);transform:translateY(-1px)}
    .palette-control{display:grid;grid-template-columns:minmax(0,1fr) 58px;gap:10px;align-items:center}
    .palette-control input[type="color"]{height:54px;min-height:54px;padding:6px;border-radius:16px;cursor:pointer}
    .field.has-error input,.field.has-error textarea,.field.has-error select{border-color:var(--red);background:rgba(255,110,69,.08)}
    .field-error{font-size:12px;color:#ff987d;margin-top:2px;display:flex;align-items:center;gap:5px}
    .field-error::before{content:"!";font-size:11px;font-weight:900}
    .field textarea{min-height:130px;line-height:1.55}
    .section-sub-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:800;color:var(--blue-2);margin-bottom:-4px}
    .security-block{margin-top:30px;padding-top:26px;border-top:1px solid var(--border)}
    .alert{border-radius:16px}
    .skills-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:28px}
    .skill-card{position:relative;overflow:hidden;background:#0a1124;border:1px solid var(--border);border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:12px;transition:.22s ease}
    .skill-card::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 100% 0%,rgba(111,143,216,.12),transparent 38%);pointer-events:none}
    .skill-card:hover{border-color:var(--border-hi);transform:translateY(-2px);box-shadow:0 18px 40px rgba(0,0,0,.24)}
    .skill-card>*{position:relative;z-index:1}
    .skill-top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}
    .skill-name{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;letter-spacing:-.03em}
    .skill-source{width:max-content;max-width:100%;font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:var(--blue-2);font-weight:900;background:rgba(111,143,216,.12);border:1px solid rgba(111,143,216,.24);border-radius:999px;padding:6px 10px}
    .skill-cert a{color:var(--blue-2);text-decoration:none;font-size:13px;font-weight:800}
    .skill-delete{background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--muted);cursor:pointer;font-size:0;width:34px;height:34px;border-radius:12px;display:grid;place-items:center;transition:.2s}
    .skill-delete::before{content:"X";font-size:13px;font-weight:900}
    .skill-delete:hover{background:rgba(255,110,69,.15);border-color:rgba(255,110,69,.28);color:#ffd0c3}
    .skill-meter{height:7px;background:#071126;border:1px solid rgba(207,222,255,.1);border-radius:999px;overflow:hidden}
    .skill-meter span{display:block;height:100%;border-radius:999px;background:linear-gradient(115deg,var(--blue),var(--orange))}
    .add-skill-form{background:rgba(111,143,216,.06);border:1px dashed rgba(111,143,216,.32);border-radius:22px;padding:24px;margin-top:8px}
    .add-skill-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;margin-bottom:18px;color:var(--blue-2)}
    .empty-state{text-align:center;padding:48px 24px;color:var(--muted);background:#0a1124;border:1px solid var(--border);border-radius:22px;margin-bottom:24px}
    .empty-icon{font-size:0;margin-bottom:12px}
    .empty-icon::before{content:"+";display:inline-grid;place-items:center;width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,var(--blue),var(--orange));font-size:26px;color:#fff}
    .face-card-grid{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:24px;align-items:start}
    .face-camera{position:relative;width:100%;max-width:540px;margin:0 auto 20px;border-radius:22px;overflow:hidden;border:1px solid var(--border);background:#000;box-shadow:0 18px 48px rgba(0,0,0,.34)}
    #face-video{width:100%;display:block;background:#000}
    #face-overlay{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none}
    #face-loading{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(4,8,22,.74);color:var(--muted);font-size:13px;gap:10px}
    .spinner-ring{width:28px;height:28px;border:2px solid var(--blue);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite}
    #face-msg{text-align:center;min-height:24px;font-size:13px;margin-bottom:16px;color:var(--muted)}
    .face-actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-bottom:28px}
    .face-status,.toggle-card{padding:18px;border-radius:18px;border:1px solid var(--border);background:#0a1124}
    .face-status{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px;color:var(--muted)}
    .face-status.is-active{background:rgba(74,222,128,.08);border-color:rgba(74,222,128,.22);color:var(--green)}
    .face-status-icon{font-size:20px;line-height:1}
    .toggle-card{display:flex;align-items:center;justify-content:space-between;gap:16px}
    .toggle-title{font-size:14px;font-weight:850}
    .toggle-help{font-size:12px;color:var(--muted);margin-top:4px;line-height:1.5}
    .toggle-label{display:flex;align-items:center;gap:8px;cursor:pointer}
    .toggle-shell{position:relative;width:48px;height:28px;flex:0 0 auto}
    .toggle-shell input{position:absolute;opacity:0;width:100%;height:100%;cursor:pointer;margin:0}
    #toggle-track{width:48px;height:28px;border-radius:999px;transition:background .2s}
    #toggle-thumb{position:absolute;top:4px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left .2s;box-shadow:0 4px 12px rgba(0,0,0,.25)}
    .face-side{display:grid;gap:12px}
    .face-tip{padding:16px;border-radius:18px;background:#0a1124;border:1px solid var(--border);color:var(--muted);font-size:13px;line-height:1.6}
    .face-tip strong{display:block;color:var(--text);margin-bottom:5px}
    @media(max-width:980px){.page-wrap{grid-template-columns:1fr}.sidebar-card{position:static}.profile-hero,.face-card-grid{grid-template-columns:1fr}}
    @media(max-width:680px){.page-wrap{padding:24px 14px 70px}.form-card,.profile-hero,.sidebar-card{padding:24px;border-radius:22px}.field-grid,.hero-stats,.tabs{grid-template-columns:1fr}.field-full,.section-sep,.section-sub-title{grid-column:span 1}.card-header{display:block}.tab-btn{width:100%}.form-actions .btn{width:100%}}
    @media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
    @keyframes ambientDrift{0%{transform:translate3d(0,0,0) scale(1)}100%{transform:translate3d(2.4%,1.8%,0) scale(1.04)}}
    @keyframes glowFloatA{50%{transform:translate(38px,26px) scale(1.08)}}
    @keyframes glowFloatB{50%{transform:translate(-34px,-22px) scale(1.06)}}
    @keyframes particleDrift{to{background-position:180px 180px,-220px 220px,260px -260px,-210px 210px}}
    @keyframes pageIn{from{opacity:0}to{opacity:1}}
    @keyframes fieldIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    @keyframes gradientFlow{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
  </style>
  <style>
    :root{
      --profile-bg:#eef4ec;
      --profile-paper:#fffdf7;
      --profile-ink:#17211d;
      --profile-muted:#65736b;
      --profile-line:#d5dfd6;
      --profile-sage:#7aa585;
      --profile-coral:#e86f4d;
      --text:var(--profile-ink);
      --muted:var(--profile-muted);
      --muted-2:#7d8b82;
      --blue:var(--profile-sage);
      --blue-2:#38664d;
      --red:var(--profile-coral);
      --green:#3d8c62;
      --border:var(--profile-line);
      --border-hi:rgba(122,165,133,.55);
    }
    body{
      color:var(--profile-ink)!important;
      background:
        linear-gradient(90deg,rgba(23,33,29,.045) 1px,transparent 1px),
        linear-gradient(rgba(23,33,29,.04) 1px,transparent 1px),
        radial-gradient(circle at 12% 14%,rgba(122,165,133,.28),transparent 28%),
        radial-gradient(circle at 88% 12%,rgba(232,111,77,.18),transparent 25%),
        linear-gradient(135deg,#f8faf3 0%,var(--profile-bg) 58%,#e3eee7 100%)!important;
      background-size:44px 44px,44px 44px,100% 100%,100% 100%,100% 100%!important;
    }
    body::before,.webgl-neuron,.cinema-bg,.particle-field{display:none!important}
    body::after{
      content:""!important;
      position:fixed!important;
      inset:0!important;
      z-index:-1!important;
      pointer-events:none!important;
      display:block!important;
      opacity:.58!important;
      background-image:
        linear-gradient(rgba(23,33,29,.07) 1px,transparent 1px),
        linear-gradient(90deg,rgba(23,33,29,.06) 1px,transparent 1px)!important;
      background-size:56px 56px,56px 56px!important;
      mask-image:radial-gradient(circle at 50% 34%,#000 0%,rgba(0,0,0,.78) 56%,transparent 92%)!important;
    }
    .page-wrap{width:min(1220px,100%)!important;max-width:none!important;display:block!important;padding:38px 22px 90px!important;margin:0 auto!important;animation:none!important}
    .profile-view{display:block!important}
    .edit-workbench{display:none!important}
    .page-wrap.is-editing .profile-view{display:none!important}
    .page-wrap.is-editing .edit-workbench{display:grid!important;grid-template-columns:320px minmax(0,1fr)!important;gap:24px!important;align-items:start!important}
    .profile-shell{background:rgba(255,253,247,.82)!important;border:1px solid rgba(23,33,29,.12)!important;box-shadow:0 28px 80px rgba(45,63,53,.16)!important;border-radius:30px!important;overflow:hidden!important}
    .profile-cover{min-height:250px!important;padding:34px!important;color:#fffdf7!important;background:linear-gradient(120deg,rgba(23,33,29,.94),rgba(47,76,58,.9)),radial-gradient(circle at 85% 20%,rgba(232,111,77,.55),transparent 34%)!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:26px!important;align-items:end!important}
    .profile-cover h1{max-width:720px!important;margin:0!important;font-family:'Syne',sans-serif!important;font-size:clamp(42px,8vw,86px)!important;line-height:.88!important;letter-spacing:-.06em!important;color:#fffdf7!important}
    .profile-cover p{max-width:680px!important;margin-top:20px!important;color:rgba(255,253,247,.74)!important;font-size:16px!important;line-height:1.65!important}
    .profile-kicker{width:max-content!important;max-width:100%!important;margin-bottom:18px!important;padding:8px 13px!important;border:1px solid rgba(255,253,247,.24)!important;border-radius:999px!important;color:rgba(255,253,247,.78)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.18em!important;text-transform:uppercase!important;background:transparent!important}
    .profile-body{display:grid!important;grid-template-columns:330px minmax(0,1fr)!important;gap:0!important;min-height:560px!important}
    .profile-rail{padding:28px!important;background:#f7fbf1!important;border-right:1px solid rgba(23,33,29,.1)!important}
    .profile-main-view{padding:30px!important;display:grid!important;gap:26px!important}
    .portrait{width:160px!important;height:190px!important;border-radius:24px!important;background:linear-gradient(145deg,var(--profile-sage),var(--profile-coral))!important;display:grid!important;place-items:center!important;overflow:hidden!important;color:#fffdf7!important;font-family:'Syne',sans-serif!important;font-size:64px!important;font-weight:900!important;box-shadow:12px 12px 0 #dce8dc!important;margin-bottom:26px!important}
    .portrait img{width:100%!important;height:100%!important;object-fit:cover!important}
    .profile-name{font-family:'Syne',sans-serif!important;font-size:30px!important;line-height:.98!important;letter-spacing:-.05em!important;margin-bottom:8px!important;color:var(--profile-ink)!important}
    .profile-email{font-size:14px!important;color:var(--profile-muted)!important;word-break:break-all!important}
    .profile-tag{display:inline-flex!important;margin-top:16px!important;padding:8px 12px!important;border-radius:999px!important;background:#e5f0e6!important;color:#38664d!important;border:1px solid #c9dacb!important;font-size:10px!important;font-weight:900!important;letter-spacing:.16em!important;text-transform:uppercase!important}
    .rail-list{display:grid!important;gap:0!important;margin-top:26px!important;border-top:1px solid rgba(23,33,29,.1)!important}
    .rail-row{padding:15px 0!important;border-bottom:1px solid rgba(23,33,29,.1)!important}
    .rail-row span,.section-label{display:block!important;color:var(--profile-muted)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.16em!important;text-transform:uppercase!important;margin-bottom:5px!important}
    .rail-row strong{display:block!important;font-size:15px!important;line-height:1.35!important;color:var(--profile-ink)!important}
    .completion-stamp{margin-top:26px!important;padding:18px!important;border:1px solid rgba(23,33,29,.12)!important;border-radius:22px!important;background:#fffdf7!important}
    .completion-stamp strong{font-size:38px!important;line-height:1!important;font-family:'Syne',sans-serif!important;color:var(--profile-ink)!important}
    .completion-stamp span{display:block!important;margin-top:6px!important;color:var(--profile-muted)!important;font-size:12px!important;font-weight:800!important}
    .view-section{padding:26px!important;border:1px solid rgba(23,33,29,.1)!important;border-radius:26px!important;background:#fffdf7!important;box-shadow:none!important}
    .view-section h2{font-family:'Syne',sans-serif!important;font-size:28px!important;line-height:1!important;letter-spacing:-.045em!important;margin-bottom:14px!important;color:var(--profile-ink)!important}
    .bio-text{font-size:17px!important;line-height:1.75!important;color:#33413a!important}
    .profile-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:14px!important}
    .fact{padding:18px!important;border-radius:20px!important;background:#f7fbf1!important;border:1px solid rgba(23,33,29,.08)!important}
    .fact strong{display:block!important;font-size:16px!important;color:var(--profile-ink)!important}
    .skill-strip{display:flex!important;flex-wrap:wrap!important;gap:10px!important}
    .skill-pill{display:inline-flex!important;align-items:center!important;gap:10px!important;padding:10px 13px!important;border-radius:999px!important;background:#17211d!important;color:#fffdf7!important;font-weight:900!important;font-size:13px!important}
    .skill-pill span{color:#f4b08f!important;font-size:12px!important}
    .empty-note{color:var(--profile-muted)!important;font-size:15px!important;line-height:1.6!important}
    .security-line{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:18px!important;padding:18px!important;border-radius:22px!important;background:#f7fbf1!important;border:1px solid rgba(23,33,29,.08)!important}
    .security-mark{width:54px!important;height:54px!important;border-radius:50%!important;display:grid!important;place-items:center!important;flex:0 0 auto!important;color:#fffdf7!important;background:var(--profile-coral)!important;font-weight:900!important}
    .security-mark.on{background:#3d8c62!important}
    .sidebar-card,.form-card{background:#fffdf7!important;border:1px solid rgba(23,33,29,.12)!important;box-shadow:0 18px 50px rgba(45,63,53,.12)!important;color:var(--profile-ink)!important}
    .sidebar-card::before,.form-card::before,.profile-hero::before{display:none!important}
    .sidebar-card{position:sticky!important;top:108px!important;background:#f7fbf1!important}
    .profile-hero{display:none!important}
    .tabs{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:10px!important;background:transparent!important;border:0!important;padding:0!important;width:100%!important}
    .tab-btn{background:#fffdf7!important;color:var(--profile-ink)!important;border:1px solid rgba(23,33,29,.14)!important;box-shadow:none!important}
    .tab-btn.active{background:var(--profile-ink)!important;color:#fffdf7!important;border-color:var(--profile-ink)!important;box-shadow:none!important}
    .mode-btn,.btn{background:#fffdf7!important;color:var(--profile-ink)!important;border:1px solid rgba(23,33,29,.14)!important;box-shadow:none!important}
    .mode-btn.primary,.btn-main{border-color:transparent!important;color:#fffdf7!important;background:linear-gradient(120deg,var(--profile-ink),#52775e 55%,var(--profile-coral))!important;box-shadow:0 16px 34px rgba(23,33,29,.18)!important}
    .form-card input,.form-card textarea,.form-card select,.field input,.field textarea,.field select{background:#f7fbf1!important;color:var(--profile-ink)!important;border-color:rgba(23,33,29,.14)!important}
    .form-card input:focus,.form-card textarea:focus,.form-card select:focus,.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--profile-sage)!important;background:#fffdf7!important;box-shadow:0 0 0 4px rgba(122,165,133,.16)!important}
    .skill-card,.add-skill-form,.empty-state,.face-status,.toggle-card,.face-tip{background:#f7fbf1!important;border-color:rgba(23,33,29,.1)!important;color:var(--profile-ink)!important}
    .skill-source,.tab-count,.status-pill{background:#e5f0e6!important;color:#38664d!important;border-color:#c9dacb!important}
    .skill-meter span{background:linear-gradient(90deg,var(--profile-sage),var(--profile-coral))!important}
    .face-camera{border-color:rgba(23,33,29,.16)!important}
    @media(max-width:920px){.profile-cover,.profile-body,.page-wrap.is-editing .edit-workbench{grid-template-columns:1fr!important}.profile-rail{border-right:0!important;border-bottom:1px solid rgba(23,33,29,.1)!important}.profile-mode{justify-content:flex-start!important}}
    @media(max-width:640px){.page-wrap{padding:22px 14px 70px!important}.profile-cover,.profile-rail,.profile-main-view{padding:22px!important}.profile-grid,.tabs{grid-template-columns:1fr!important}.profile-mode{flex-direction:column!important;align-items:stretch!important}.mode-btn{width:100%!important}.profile-cover h1{font-size:44px!important}.security-line{align-items:flex-start!important;flex-direction:column!important}}
  </style>
  <link rel="stylesheet" href="assets/css/front-nav.css">
  <style>
    :root{
      --profile-bg:#eef4ec;
      --profile-paper:#fffdf7;
      --profile-panel:#f7fbf1;
      --profile-ink:#17211d;
      --profile-muted:#65736b;
      --profile-line:#d5dfd6;
      --profile-sage:#7aa585;
      --profile-coral:#e86f4d;
      --text:var(--profile-ink);
      --muted:var(--profile-muted);
      --muted-2:#7d8b82;
      --blue:var(--profile-sage);
      --blue-2:#38664d;
      --red:var(--profile-coral);
      --green:#3d8c62;
      --border:var(--profile-line);
      --border-hi:rgba(122,165,133,.55);
    }
    body{
      color:var(--profile-ink)!important;
      padding-top:104px!important;
      background:
        radial-gradient(circle at 12% 14%,rgba(122,165,133,.24),transparent 28%),
        radial-gradient(circle at 88% 12%,rgba(232,111,77,.16),transparent 25%),
        linear-gradient(135deg,#f8faf3 0%,var(--profile-bg) 58%,#e3eee7 100%)!important;
      overflow-x:hidden!important;
    }
    body::before{
      content:""!important;
      display:block!important;
      position:fixed!important;
      inset:0!important;
      z-index:0!important;
      pointer-events:none!important;
      opacity:.74!important;
      background-image:
        linear-gradient(rgba(23,33,29,.16) 1px,transparent 1px),
        linear-gradient(90deg,rgba(23,33,29,.16) 1px,transparent 1px),
        linear-gradient(rgba(122,165,133,.24) 1px,transparent 1px),
        linear-gradient(90deg,rgba(122,165,133,.24) 1px,transparent 1px)!important;
      background-size:34px 34px,34px 34px,136px 136px,136px 136px!important;
      background-position:0 0,0 0,-1px -1px,-1px -1px!important;
      mask-image:linear-gradient(180deg,rgba(0,0,0,.95),rgba(0,0,0,.85) 60%,rgba(0,0,0,.62))!important;
    }
    body::after,.webgl-neuron,.cinema-bg,.particle-field{display:none!important}
    .site-header.cs-front-header{
      position:fixed!important;
      top:0!important;
      left:0!important;
      right:0!important;
      width:100%!important;
      margin-top:0!important;
      z-index:60!important;
    }
    .page-wrap{width:min(1220px,100%)!important;max-width:none!important;display:block!important;padding:28px 22px 90px!important;margin:0 auto 0!important;animation:none!important}
    .profile-view{display:block!important;margin-top:8px!important}
    .edit-workbench{display:none!important}
    .page-wrap.is-editing .profile-view{display:none!important}
    .page-wrap.is-editing .edit-workbench{display:grid!important;grid-template-columns:320px minmax(0,1fr)!important;gap:24px!important;align-items:start!important}
    .profile-shell{background:rgba(255,253,247,.76)!important;backdrop-filter:blur(2px)!important;border:1px solid rgba(23,33,29,.12)!important;box-shadow:0 28px 80px rgba(45,63,53,.16)!important;border-radius:30px!important;overflow:hidden!important}
    .profile-cover{min-height:250px!important;padding:34px!important;color:#fffdf7!important;background:linear-gradient(120deg,rgba(23,33,29,.94),rgba(47,76,58,.9)),radial-gradient(circle at 85% 20%,rgba(232,111,77,.55),transparent 34%)!important;display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:26px!important;align-items:end!important}
    .profile-cover h1{max-width:720px!important;margin:0!important;font-family:'Syne',sans-serif!important;font-size:clamp(42px,8vw,86px)!important;line-height:.88!important;letter-spacing:-.06em!important;color:#fffdf7!important}
    .profile-cover p{max-width:680px!important;margin-top:20px!important;color:rgba(255,253,247,.74)!important;font-size:16px!important;line-height:1.65!important}
    .profile-kicker{width:max-content!important;max-width:100%!important;margin-bottom:18px!important;padding:8px 13px!important;border:1px solid rgba(255,253,247,.24)!important;border-radius:999px!important;color:rgba(255,253,247,.78)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.18em!important;text-transform:uppercase!important;background:transparent!important}
    .profile-body{display:grid!important;grid-template-columns:330px minmax(0,1fr)!important;gap:0!important;min-height:560px!important}
    .profile-rail{padding:28px!important;background:rgba(247,251,241,.86)!important;border-right:1px solid rgba(23,33,29,.1)!important}
    .profile-main-view{padding:30px!important;display:grid!important;gap:26px!important}
    .portrait{width:160px!important;height:190px!important;border-radius:24px!important;background:linear-gradient(145deg,var(--profile-sage),var(--profile-coral))!important;display:grid!important;place-items:center!important;overflow:hidden!important;color:#fffdf7!important;font-family:'Syne',sans-serif!important;font-size:64px!important;font-weight:900!important;box-shadow:12px 12px 0 #dce8dc!important;margin-bottom:26px!important}
    .portrait img{width:100%!important;height:100%!important;object-fit:cover!important}
    .profile-name{font-family:'Syne',sans-serif!important;font-size:30px!important;line-height:.98!important;letter-spacing:-.05em!important;margin-bottom:8px!important;color:var(--profile-ink)!important}
    .profile-email{font-size:14px!important;color:var(--profile-muted)!important;word-break:break-all!important}
    .profile-tag{display:inline-flex!important;margin-top:16px!important;padding:8px 12px!important;border-radius:999px!important;background:#e5f0e6!important;color:#38664d!important;border:1px solid #c9dacb!important;font-size:10px!important;font-weight:900!important;letter-spacing:.16em!important;text-transform:uppercase!important}
    .rail-list{display:grid!important;gap:0!important;margin-top:26px!important;border-top:1px solid rgba(23,33,29,.1)!important}
    .rail-row{padding:15px 0!important;border-bottom:1px solid rgba(23,33,29,.1)!important}
    .rail-row span,.section-label{display:block!important;color:var(--profile-muted)!important;font-size:10px!important;font-weight:900!important;letter-spacing:.16em!important;text-transform:uppercase!important;margin-bottom:5px!important}
    .rail-row strong{display:block!important;font-size:15px!important;line-height:1.35!important;color:var(--profile-ink)!important}
    .completion-stamp{margin-top:26px!important;padding:18px!important;border:1px solid rgba(23,33,29,.12)!important;border-radius:22px!important;background:rgba(255,253,247,.9)!important}
    .completion-stamp strong{font-size:38px!important;line-height:1!important;font-family:'Syne',sans-serif!important;color:var(--profile-ink)!important}
    .completion-stamp span{display:block!important;margin-top:6px!important;color:var(--profile-muted)!important;font-size:12px!important;font-weight:800!important}
    .view-section{padding:26px!important;border:1px solid rgba(23,33,29,.1)!important;border-radius:26px!important;background:rgba(255,253,247,.88)!important;box-shadow:none!important}
    .view-section h2{font-family:'Syne',sans-serif!important;font-size:28px!important;line-height:1!important;letter-spacing:-.045em!important;margin-bottom:14px!important;color:var(--profile-ink)!important}
    .bio-text{font-size:17px!important;line-height:1.75!important;color:#33413a!important}
    .profile-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:14px!important}
    .fact{padding:18px!important;border-radius:20px!important;background:rgba(247,251,241,.86)!important;border:1px solid rgba(23,33,29,.08)!important}
    .fact strong{display:block!important;font-size:16px!important;color:var(--profile-ink)!important}
    .skill-strip{display:flex!important;flex-wrap:wrap!important;gap:10px!important}
    .skill-pill{display:inline-flex!important;align-items:center!important;gap:10px!important;padding:10px 13px!important;border-radius:999px!important;background:#17211d!important;color:#fffdf7!important;font-weight:900!important;font-size:13px!important}
    .skill-pill span{color:#f4b08f!important;font-size:12px!important}
    .empty-note{color:var(--profile-muted)!important;font-size:15px!important;line-height:1.6!important}
    .security-line{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:18px!important;padding:18px!important;border-radius:22px!important;background:rgba(247,251,241,.86)!important;border:1px solid rgba(23,33,29,.08)!important}
    .security-mark{width:54px!important;height:54px!important;border-radius:50%!important;display:grid!important;place-items:center!important;flex:0 0 auto!important;color:#fffdf7!important;background:var(--profile-coral)!important;font-weight:900!important}
    .security-mark.on{background:#3d8c62!important}
    .sidebar-card,.form-card{background:rgba(255,253,247,.9)!important;border:1px solid rgba(23,33,29,.12)!important;box-shadow:0 18px 50px rgba(45,63,53,.12)!important;color:var(--profile-ink)!important}
    .sidebar-card::before,.form-card::before,.profile-hero::before{display:none!important}
    .sidebar-card{position:sticky!important;top:108px!important;background:rgba(247,251,241,.9)!important}
    .profile-hero{display:none!important}
    .tabs{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:10px!important;background:transparent!important;border:0!important;padding:0!important;width:100%!important}
    .tab-btn{background:#fffdf7!important;color:var(--profile-ink)!important;border:1px solid rgba(23,33,29,.14)!important;box-shadow:none!important}
    .tab-btn.active{background:var(--profile-ink)!important;color:#fffdf7!important;border-color:var(--profile-ink)!important;box-shadow:none!important}
    .mode-btn,.btn{background:#fffdf7!important;color:var(--profile-ink)!important;border:1px solid rgba(23,33,29,.14)!important;box-shadow:none!important}
    .mode-btn.primary,.btn-main{border-color:transparent!important;color:#fffdf7!important;background:linear-gradient(120deg,var(--profile-ink),#52775e 55%,var(--profile-coral))!important;box-shadow:0 16px 34px rgba(23,33,29,.18)!important}
    .form-card input,.form-card textarea,.form-card select,.field input,.field textarea,.field select{background:#f7fbf1!important;color:var(--profile-ink)!important;border-color:rgba(23,33,29,.14)!important}
    .form-card input:focus,.form-card textarea:focus,.form-card select:focus,.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--profile-sage)!important;background:#fffdf7!important;box-shadow:0 0 0 4px rgba(122,165,133,.16)!important}
    .skill-card,.add-skill-form,.empty-state,.face-status,.toggle-card,.face-tip{background:#f7fbf1!important;border-color:rgba(23,33,29,.1)!important;color:var(--profile-ink)!important}
    .skill-source,.tab-count,.status-pill{background:#e5f0e6!important;color:#38664d!important;border-color:#c9dacb!important}
    .skill-meter span{background:linear-gradient(90deg,var(--profile-sage),var(--profile-coral))!important}
    .face-camera{border-color:rgba(23,33,29,.16)!important}
    @media(max-width:920px){.profile-cover,.profile-body,.page-wrap.is-editing .edit-workbench{grid-template-columns:1fr!important}.profile-rail{border-right:0!important;border-bottom:1px solid rgba(23,33,29,.1)!important}.profile-mode{justify-content:flex-start!important}}
    @media(max-width:640px){.page-wrap{padding:22px 14px 70px!important;margin-top:0!important}.profile-view{margin-top:12px!important}.profile-cover,.profile-rail,.profile-main-view{padding:22px!important}.profile-grid,.tabs{grid-template-columns:1fr!important}.profile-mode{flex-direction:column!important;align-items:stretch!important}.mode-btn{width:100%!important}.profile-cover h1{font-size:44px!important}.security-line{align-items:flex-start!important;flex-direction:column!important}}
  </style>
  <style>
    :root{
      --profile-bg:#030611;
      --profile-paper:#070d1d;
      --profile-ink:#f8fbff;
      --profile-muted:rgba(232,239,255,.72);
      --profile-line:rgba(255,255,255,.10);
      --profile-sage:#6f8fd8;
      --profile-coral:#ff6e45;
      --text:#f8fbff;
      --muted:rgba(232,239,255,.72);
      --muted-2:rgba(232,239,255,.48);
      --blue:#6f8fd8;
      --blue-2:#9db7ff;
      --red:#ff6e45;
      --green:#4ade80;
      --border:rgba(255,255,255,.10);
      --border-hi:rgba(111,143,216,.42);
    }
    body{
      color:#f8fbff!important;
      background:
        radial-gradient(circle at 16% 12%,rgba(111,143,216,.36),transparent 28%),
        radial-gradient(circle at 84% 88%,rgba(255,110,69,.28),transparent 30%),
        radial-gradient(circle at 52% 48%,rgba(123,97,255,.18),transparent 28%),
        linear-gradient(135deg,#02040c 0%,#061229 44%,#080914 100%)!important;
      background-size:124% 124%,128% 128%,132% 132%,100% 100%!important;
      animation:profileSpaceDrift 8s linear infinite!important;
    }
    body::before{
      content:""!important;
      display:block!important;
      position:fixed!important;
      inset:-12%!important;
      z-index:0!important;
      pointer-events:none!important;
      opacity:.56!important;
      background-image:
        linear-gradient(rgba(157,183,255,.16) 1px,transparent 1px),
        linear-gradient(90deg,rgba(157,183,255,.14) 1px,transparent 1px),
        linear-gradient(rgba(255,110,69,.10) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,110,69,.09) 1px,transparent 1px)!important;
      background-size:148px 148px,148px 148px,296px 296px,296px 296px!important;
      background-position:0 0,0 0,-2px -2px,-2px -2px!important;
      mask-image:linear-gradient(180deg,rgba(0,0,0,.98),rgba(0,0,0,.88) 58%,rgba(0,0,0,.56))!important;
      will-change:transform!important;
      animation:profileGridRiver 4s linear infinite!important;
    }
    body::after{display:none!important}
    .profile-shell{background:rgba(7,13,29,.86)!important;border-color:rgba(255,255,255,.10)!important;box-shadow:0 30px 90px rgba(0,0,0,.42)!important}
    .profile-cover{background:radial-gradient(circle at 85% 18%,rgba(255,110,69,.34),transparent 34%),radial-gradient(circle at 18% 0%,rgba(111,143,216,.34),transparent 38%),linear-gradient(120deg,rgba(4,8,22,.98),rgba(7,17,38,.96))!important}
    .profile-cover h1,.profile-name,.rail-row strong,.completion-stamp strong,.view-section h2,.fact strong{color:#f8fbff!important}
    .profile-cover p,.bio-text{color:rgba(232,239,255,.78)!important}
    .profile-kicker,.profile-tag,.skill-source,.tab-count,.status-pill{background:rgba(111,143,216,.14)!important;color:#9db7ff!important;border-color:rgba(111,143,216,.30)!important}
    .profile-rail,.completion-stamp,.view-section,.fact,.security-line,.sidebar-card,.form-card,.skill-card,.add-skill-form,.empty-state,.face-status,.toggle-card,.face-tip{background:#070d1d!important;border-color:rgba(255,255,255,.10)!important;color:#f8fbff!important;box-shadow:none!important}
    .profile-rail{border-right-color:rgba(255,255,255,.08)!important}
    .profile-email,.rail-row span,.section-label,.completion-stamp span,.empty-note{color:rgba(232,239,255,.55)!important}
    .portrait{background:linear-gradient(135deg,#6f8fd8,#ff6e45)!important;box-shadow:12px 12px 0 rgba(111,143,216,.16)!important}
    .skill-pill{background:#0b1225!important;color:#f8fbff!important;border:1px solid rgba(207,222,255,.12)!important}
    .skill-pill span{color:#ffb197!important}
    .security-mark{background:#ff6e45!important;color:#fff!important}
    .security-mark.on{background:#4ade80!important;color:#061229!important}
    .tab-btn,.mode-btn,.btn{background:#0b1225!important;color:rgba(232,239,255,.78)!important;border-color:rgba(207,222,255,.14)!important}
    .tab-btn.active,.mode-btn.primary,.btn-main{background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 36%,#ff7a50 100%)!important;color:#fff!important;border-color:rgba(157,183,255,.55)!important;box-shadow:0 14px 34px rgba(111,143,216,.22),0 12px 30px rgba(255,110,69,.12)!important}
    .form-card input,.form-card textarea,.form-card select,.field input,.field textarea,.field select{background:#0a1124!important;color:#f8fbff!important;border-color:rgba(255,255,255,.10)!important}
    .form-card input:focus,.form-card textarea:focus,.form-card select:focus,.field input:focus,.field textarea:focus,.field select:focus{border-color:#6f8fd8!important;background:rgba(111,143,216,.07)!important;box-shadow:0 0 0 4px rgba(111,143,216,.10)!important}
    .field input[type="file"]{padding:12px 14px!important;cursor:pointer}
    .field input[type="file"]::file-selector-button,
    .field input[type="file"]::-webkit-file-upload-button{
      margin-right:14px;
      padding:10px 16px;
      border:1px solid rgba(157,183,255,.52);
      border-radius:12px;
      background:linear-gradient(115deg,#6f8fd8 0%,#8fb1ff 36%,#ff7a50 100%);
      color:#fff;
      font-family:'Syne',sans-serif;
      font-size:13px;
      font-weight:700;
      cursor:pointer;
      transition:transform .2s ease, filter .2s ease;
    }
    .field input[type="file"]::file-selector-button:hover,
    .field input[type="file"]::-webkit-file-upload-button:hover{filter:brightness(1.08);transform:translateY(-1px)}
    .skill-meter{background:#0b1225!important;border-color:rgba(207,222,255,.10)!important}
    .skill-meter span{background:linear-gradient(90deg,#6f8fd8,#ff6e45)!important}
    .profile-confirm-bar{
      position:fixed;
      bottom:28px;
      left:50%;
      transform:translateX(-50%);
      z-index:1200;
      display:none;
      align-items:center;
      gap:14px;
      min-width:300px;
      max-width:92vw;
      padding:16px 20px;
      border:1px solid rgba(111,143,216,0.35);
      border-radius:18px;
      background:#071126;
      box-shadow:0 8px 40px rgba(0,0,0,0.55);
    }
    .profile-confirm-bar.is-open{display:flex}
    @keyframes profileSpaceDrift{
      from{background-position:0% 0%,100% 100%,50% 50%,0 0}
      to{background-position:9% 4%,91% 95%,44% 56%,0 0}
    }
    @keyframes profileGridRiver{
      from{transform:translate3d(-10vw,0,0)}
      to{transform:translate3d(10vw,0,0)}
    }
  </style>
  <style>
    html, body {
      margin: 0 !important;
      padding: 0 !important;
    }

    .page-wrap{
      padding-top: 0 !important;
      margin-top: 0 !important;
    }
    .profile-view,
    .edit-workbench{
      margin-top: 0 !important;
    }
  </style>
  <link rel="stylesheet" href="assets/css/front-nav.css">
  <style>
    :root {
      --max: 1380px;
    }

    body {
      padding-top: 0 !important;
    }

    body .cs-front-header .container,
    body .site-header.cs-front-header .container {
      width: min(var(--max), calc(100% - 34px)) !important;
      max-width: none !important;
      margin-left: auto !important;
      margin-right: auto !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

    body .site-header.cs-front-header {
      position: sticky !important;
      top: 0 !important;
      left: auto !important;
      right: auto !important;
      width: 100% !important;
      z-index: 60 !important;
    }

    body .page-wrap {
      width: min(var(--max), calc(100% - 34px)) !important;
      max-width: none !important;
      padding: 56px 0 90px !important;
      margin: 0 auto !important;
    }

    body .profile-view,
    body .edit-workbench {
      margin-top: 0 !important;
    }

    body .profile-shell {
      width: 100% !important;
      margin: 10px auto 0 !important;
    }

    @media (max-width: 640px) {
      body .cs-front-header .container,
      body .site-header.cs-front-header .container,
      body .page-wrap {
        width: min(100% - 22px, 100%) !important;
      }

      body .page-wrap {
        padding-top: 34px !important;
      }
    }
  </style>
  <link rel="stylesheet" href="assets/css/skill-assessment.css?v=3">
</head>
<body class="profile-page">
  <?php
    $activePage = 'profile';
    $brandSubtitle = 'profile command';
    include __DIR__ . '/partials/front-nav.php';
  ?>

  <div class="page-wrap <?php echo (!empty($errors) || $success || isset($_GET['tab'])) ? 'is-editing' : ''; ?>">

    <section class="profile-view" id="profileView">
      <div class="profile-shell">
        <div class="profile-cover">
          <div>
            <div class="profile-kicker">CareerStrand profile</div>
            <h1><?php echo htmlspecialchars($userArr['fullName']); ?></h1>
            <p>
              <?php echo ($profile && trim($profile->getBio()) !== '')
                ? htmlspecialchars($profile->getBio())
                : 'A focused CareerStrand profile ready to be shaped with your story, skills, preferences, and career direction.'; ?>
            </p>
          </div>
          <div class="profile-mode">
            <button type="button" class="mode-btn primary" onclick="setProfileMode('edit')">Modify profile</button>
            <a class="mode-btn" href="<?php echo htmlspecialchars($portfolioUrl); ?>" target="_blank" rel="noopener">Open portfolio</a>
            <a class="mode-btn" href="<?php echo htmlspecialchars($cvUrl); ?>" target="_blank" rel="noopener">Open CV</a>
          </div>
        </div>

        <div class="profile-body">
          <aside class="profile-rail">
            <div class="portrait">
              <?php if ($profile && !empty($profile->getPhotoUrl())): ?>
                <img src="<?php echo htmlspecialchars($profile->getPhotoUrl()); ?>" alt="Profile photo">
              <?php else: ?>
                <?php echo strtoupper(substr($userArr['fullName'], 0, 1)); ?>
              <?php endif; ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($userArr['fullName']); ?></div>
            <div class="profile-email"><?php echo htmlspecialchars($userArr['email']); ?></div>
            <span class="profile-tag"><?php echo htmlspecialchars(ucwords($userArr['role'])); ?></span>

            <?php if ($questionnaireStatus !== 'completed'): ?>
            <div class="sidebar-divider"></div>
            <div class="info-row" style="align-items:flex-start;gap:12px">
              <span>AI profile</span>
              <strong style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
                <span>
                  <?php echo $questionnaireStatus === 'skipped'
                    ? 'You skipped the AI onboarding questions. You can finish them anytime.'
                    : 'Finish your AI onboarding questions to improve your profile bio.'; ?>
                </span>
                <a class="btn btn-main" href="questionnaire.php?resume=1" style="padding:8px 14px;min-height:36px;">Complete AI questions</a>
              </strong>
            </div>
            <?php endif; ?>

            <div class="rail-list">
              <div class="rail-row">
                <span>Status</span>
                <strong><?php echo htmlspecialchars(ucfirst($userArr['status'])); ?></strong>
              </div>
              <div class="rail-row">
                <span>Member since</span>
                <strong><?php echo $userArr['createdAt'] ? date('F Y', strtotime($userArr['createdAt'])) : 'Not available'; ?></strong>
              </div>
              <div class="rail-row">
                <span>Location</span>
                <strong><?php echo ($profile && $profile->getLocation()) ? htmlspecialchars($profile->getLocation()) : 'Not set'; ?></strong>
              </div>
              <div class="rail-row">
                <span>Level</span>
                <strong><?php echo htmlspecialchars($level); ?></strong>
              </div>
            </div>

            <div class="completion-stamp">
              <strong><?php echo (int) $score; ?>%</strong>
              <span>Profile completion</span>
            </div>
          </aside>

          <main class="profile-main-view">
            <section class="view-section">
              <span class="section-label">About</span>
              <h2>Profile story</h2>
              <p class="bio-text">
                <?php echo ($profile && trim($profile->getBio()) !== '')
                  ? htmlspecialchars($profile->getBio())
                  : 'No bio has been added yet. Switch to modify mode to write a profile story that explains who you are, what you are building, and where you want your career strand to go next.'; ?>
              </p>
            </section>

            <section class="view-section">
              <span class="section-label">Details</span>
              <h2>Career signal</h2>
              <div class="profile-grid">
                <div class="fact">
                  <span class="section-label">Preferences</span>
                  <strong><?php echo ($profile && $profile->getPreferences()) ? htmlspecialchars($profile->getPreferences()) : 'Not set'; ?></strong>
                </div>
                <div class="fact">
                  <span class="section-label">Skills</span>
                  <strong><?php echo count($skills); ?> added</strong>
                </div>
                <div class="fact">
                  <span class="section-label">Career goal</span>
                  <strong><?php echo ($profile && trim($profile->getCareerGoal())) ? htmlspecialchars($profile->getCareerGoal()) : 'Not set'; ?></strong>
                </div>
                <?php if ($role === 'manager' && $managerProfile): ?>
                  <div class="fact">
                    <span class="section-label">Organization</span>
                    <strong><?php echo htmlspecialchars($managerProfile->getOrganization()); ?></strong>
                  </div>
                  <div class="fact">
                    <span class="section-label">Focus</span>
                    <strong><?php echo htmlspecialchars($managerProfile->getCategoryFocus()); ?></strong>
                  </div>
                <?php endif; ?>
                <?php if ($role === 'manager recruiter' && $recruiterProfile): ?>
                  <div class="fact">
                    <span class="section-label">Company</span>
                    <strong><?php echo htmlspecialchars($recruiterProfile->getCompanyName()); ?></strong>
                  </div>
                  <div class="fact">
                    <span class="section-label">Position</span>
                    <strong><?php echo htmlspecialchars($recruiterProfile->getJobTitle()); ?></strong>
                  </div>
                <?php endif; ?>
              </div>
            </section>

            <section class="view-section">
              <span class="section-label">Skills</span>
              <h2>Skill language</h2>
              <?php if (!empty($skills)): ?>
                <div class="skill-strip">
                  <?php foreach ($skills as $skill): ?>
                    <span class="skill-pill">
                      <?php echo htmlspecialchars($skill->getSkillName()); ?>
                      <?php if ($skill->getSkillLevel() !== null): ?><span><?php echo (int) $skill->getSkillLevel(); ?>/100</span><?php endif; ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="empty-note">No skills have been added yet. Modify mode lets you add skills, sources, certificates, and validation dates.</p>
              <?php endif; ?>
            </section>

            <section class="view-section">
              <span class="section-label">Access</span>
              <h2>Security</h2>
              <div class="security-line">
                <div>
                  <strong>Face ID is <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'enabled' : 'disabled'; ?></strong>
                  <p class="empty-note">Password changes and Face ID setup are available in modify mode.</p>
                </div>
                <div class="security-mark <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'on' : ''; ?>">
                  <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'ON' : 'OFF'; ?>
                </div>
              </div>
            </section>
          </main>
        </div>
      </div>
    </section>

    <div class="edit-workbench" id="editWorkbench">
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
      <div class="sidebar-divider"></div>
      <div class="info-row" style="align-items:flex-start;gap:12px">
        <span>Mentorship desk</span>
        <strong style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
          <span>Send a structured request to an admin for guidance or office hours.</span>
          <a class="btn btn-main" href="mentor-requests.php?sourceType=profile&sourceId=<?php echo (int) $userId; ?>&sourceLabel=<?php echo rawurlencode((string) ($userObj ? $userObj->getFullName() : 'Profile')); ?>" style="padding:8px 14px;min-height:36px;">Ask mentor</a>
        </strong>
      </div>
      <?php if ($profile && !empty($profile->getBio())): ?>
      <div class="sidebar-divider"></div>
      <p class="sidebar-bio"><?php echo htmlspecialchars($profile->getBio()); ?></p>
      <?php endif; ?>
    </aside>

    <!-- MAIN PANEL -->
    <main class="main-panel">
      <div class="profile-mode" style="justify-content:space-between;">
        <button type="button" class="mode-btn" onclick="setProfileMode('view')">Back to profile view</button>
        <span class="profile-tag" style="margin-top:0;">Modify mode</span>
      </div>

      <div class="tabs">
        <button class="tab-btn <?php echo $tab==='profile'?'active':''; ?>" onclick="switchTab('profile')">Profile Info</button>
        <button class="tab-btn <?php echo $tab==='skills'?'active':''; ?>" onclick="switchTab('skills')">
          Skills <span class="tab-count"><?php echo count($skills); ?></span>
        </button>
        <button class="tab-btn <?php echo $tab==='faceid'?'active':''; ?>" onclick="switchTab('faceid')">
          Face ID <?php if($userObj && $userObj->isFaceEnabled()): ?><span class="status-pill">ON</span><?php endif; ?>
        </button>
      </div>

      <!-- ══════════════ PROFILE TAB ══════════════ -->
      <div id="tab-profile" class="tab-panel <?php echo $tab==='profile'?'active':''; ?>">
        <div class="form-card">
          <div class="card-header">
            <div class="card-title">Personal Information</div>
            <div class="card-sub">Update your name, email, photo, bio, and portfolio/CV style settings.</div>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success">✓ Profile updated successfully!</div>
          <?php endif; ?>

          <form method="POST" action="?tab=profile" enctype="multipart/form-data">
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

              <!-- Photo upload -->
              <div class="field field-full <?php echo isset($errors['photoUrl'])?'has-error':''; ?>">
                <label>Profile Photo</label>
                <input type="file" name="photoFile" accept="image/*">
                <small style="color:var(--muted);font-size:12px;line-height:1.4;">
                  Upload a JPG, PNG, GIF, or WEBP image.
                </small>
                <?php if (isset($errors['photoUrl'])): ?>
                  <span class="field-error"><?php echo htmlspecialchars($errors['photoUrl']); ?></span>
                <?php endif; ?>
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

              <hr class="section-sep">
              <span class="section-sub-title">Portfolio & CV styling</span>

              <div class="field field-full">
                <label>Career Goal</label>
                <input type="text" name="careerGoal"
                       value="<?php echo htmlspecialchars($_POST['careerGoal'] ?? $careerGoal); ?>"
                       placeholder="Become a full-stack developer, data analyst, designer...">
              </div>

              <div class="field">
                <label>Portfolio Theme</label>
                <select name="portfolioTheme">
                  <?php foreach ($themeOptions as $themeKey => $themeData): ?>
                    <option value="<?php echo htmlspecialchars($themeKey); ?>" <?php echo ($portfolioTheme === $themeKey) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($themeData['label']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>CV Theme</label>
                <select name="cvTheme">
                  <?php foreach ($themeOptions as $themeKey => $themeData): ?>
                    <option value="<?php echo htmlspecialchars($themeKey); ?>" <?php echo ($cvTheme === $themeKey) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($themeData['label']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label>Portfolio Primary Color</label>
                <div class="palette-control">
                  <input type="text" name="portfolioPrimaryColor"
                         value="<?php echo htmlspecialchars($_POST['portfolioPrimaryColor'] ?? $portfolioPrimary); ?>"
                         placeholder="#8fb1ff" pattern="#[0-9a-fA-F]{6}">
                  <input type="color" aria-label="Pick portfolio primary color"
                         value="<?php echo htmlspecialchars($_POST['portfolioPrimaryColor'] ?? $portfolioPrimary); ?>"
                         oninput="this.previousElementSibling.value=this.value">
                </div>
              </div>

              <div class="field">
                <label>Portfolio Secondary Color</label>
                <div class="palette-control">
                  <input type="text" name="portfolioSecondaryColor"
                         value="<?php echo htmlspecialchars($_POST['portfolioSecondaryColor'] ?? $portfolioSecondary); ?>"
                         placeholder="#ff7a50" pattern="#[0-9a-fA-F]{6}">
                  <input type="color" aria-label="Pick portfolio secondary color"
                         value="<?php echo htmlspecialchars($_POST['portfolioSecondaryColor'] ?? $portfolioSecondary); ?>"
                         oninput="this.previousElementSibling.value=this.value">
                </div>
              </div>

              <div class="field">
                <label>CV Primary Color</label>
                <div class="palette-control">
                  <input type="text" name="cvPrimaryColor"
                         value="<?php echo htmlspecialchars($_POST['cvPrimaryColor'] ?? $cvPrimary); ?>"
                         placeholder="#1f1b1b" pattern="#[0-9a-fA-F]{6}">
                  <input type="color" aria-label="Pick CV primary color"
                         value="<?php echo htmlspecialchars($_POST['cvPrimaryColor'] ?? $cvPrimary); ?>"
                         oninput="this.previousElementSibling.value=this.value">
                </div>
              </div>

              <div class="field">
                <label>CV Secondary Color</label>
                <div class="palette-control">
                  <input type="text" name="cvSecondaryColor"
                         value="<?php echo htmlspecialchars($_POST['cvSecondaryColor'] ?? $cvSecondary); ?>"
                         placeholder="#efe7df" pattern="#[0-9a-fA-F]{6}">
                  <input type="color" aria-label="Pick CV secondary color"
                         value="<?php echo htmlspecialchars($_POST['cvSecondaryColor'] ?? $cvSecondary); ?>"
                         oninput="this.previousElementSibling.value=this.value">
                </div>
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
            <div class="security-block">
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
            <div class="card-sub">Request skills from the saved skill library. Admin approval is required before a skill becomes active.</div>
          </div>

          <?php if ($success && $tab==='skills'): ?>
            <div class="alert alert-success">✓ Skill request submitted successfully!</div>
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

          <?php if (!empty($skillRequests)): ?>
          <div class="skill-request-list">
            <?php foreach ($skillRequests as $request): ?>
              <?php $requestStatus = strtolower((string) $request->getApprovalStatus()); ?>
              <div class="skill-request-card">
                <div class="skill-request-head">
                  <div>
                    <div class="skill-name" style="font-size:16px;"><?php echo htmlspecialchars($request->getSkillName()); ?></div>
                    <div class="skill-request-meta">
                      <?php echo htmlspecialchars($request->getSource() ?: 'No source'); ?>
                      <?php if ($request->getValidatedAt()): ?> • Validated <?= date('d M Y', strtotime($request->getValidatedAt())); ?><?php endif; ?>
                    </div>
                  </div>
                  <span class="skill-request-status <?php echo in_array($requestStatus, ['pending', 'rejected'], true) ? $requestStatus : 'pending'; ?>">
                    <?php echo htmlspecialchars(ucfirst($requestStatus)); ?>
                  </span>
                </div>
                <div class="skill-request-meta">
                  <?php echo $request->getCertificateUrl() ? 'Certificate: ' . htmlspecialchars($request->getCertificateUrl()) : 'Certificate not linked yet.'; ?>
                </div>
                <?php if ($requestStatus === 'rejected'): ?>
                  <div class="skill-request-meta">
                    Rejected by admin<?php echo $request->getReviewNote() ? ': ' . htmlspecialchars($request->getReviewNote()) : '.'; ?>
                  </div>
                <?php else: ?>
                  <div class="skill-request-meta">Waiting for admin approval before it appears in your approved skills.</div>
                <?php endif; ?>
                <div class="skill-request-actions">
                  <form method="POST" action="?tab=skills" style="display:inline;">
                    <input type="hidden" name="_action" value="delete_skill">
                    <input type="hidden" name="skillId" value="<?php echo $request->getUserSkillId(); ?>">
                    <button type="button" class="btn btn-ghost" onclick="profileConfirmDelete(this.closest('form'), 'Remove this skill request?')" style="padding:8px 14px;min-height:36px;">Remove request</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
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
                  <button type="button" class="skill-delete" onclick="profileConfirmDelete(this.closest('form'), 'Remove “<?php echo addslashes(htmlspecialchars($skill->getSkillName())); ?>” from your profile?')" title="Remove">✕</button>
                </form>
              </div>
              <?php if ($skill->getSource()): ?><div class="skill-source"><?php echo htmlspecialchars($skill->getSource()); ?></div><?php endif; ?>
              <?php if ($skill->getSkillLevel() !== null): ?>
                <div class="skill-date">Level: <?php echo (int) $skill->getSkillLevel(); ?>/100</div>
                <div class="skill-meter" aria-hidden="true"><span style="width:<?php echo max(0, min(100, (int) $skill->getSkillLevel())); ?>%"></span></div>
              <?php endif; ?>
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
            <div class="empty-text">No approved skills yet. Request one below and an admin will review it.</div>
          </div>
          <?php endif; ?>

          <div class="add-skill-form">
            <div class="add-skill-title">Request a new skill</div>
            <?php if (!empty($skillCatalog)): ?>
              <div class="skill-suggestions">
                <?php foreach (array_slice($skillCatalog, 0, 14) as $catalogSkill): ?>
                  <button
                    type="button"
                    class="skill-suggestion-chip"
                    data-skill-name="<?php echo htmlspecialchars($catalogSkill['skillName'] ?? ''); ?>"
                  >
                    <?php echo htmlspecialchars($catalogSkill['skillName'] ?? 'Skill'); ?>
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="sa-trigger">
              <div class="sa-trigger-input-wrap">
                <input type="text" id="skillNameRequest" class="sa-trigger-input"
                       placeholder="e.g. React, Python, Figma…"
                       list="skillCatalogOptions" autocomplete="off">
                <datalist id="skillCatalogOptions">
                  <?php foreach ($skillCatalog as $catalogSkill): ?>
                    <option value="<?php echo htmlspecialchars($catalogSkill['skillName'] ?? ''); ?>">
                  <?php endforeach; ?>
                </datalist>
                <button type="button" class="sa-trigger-btn" id="saOpenBtn">Add skill →</button>
              </div>
              <p class="sa-trigger-hint">Type a skill name or pick a suggestion above, then choose how to verify it.</p>
            </div>
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

      <div class="face-card-grid">
        <div>
          <div class="face-camera">
            <video id="face-video" autoplay muted playsinline></video>
            <canvas id="face-overlay"></canvas>
            <div id="face-loading">
              <div class="spinner-ring"></div>
              Loading face models...
            </div>
          </div>

          <div id="face-msg"></div>

          <div class="face-actions">
            <button id="btn-start-cam" class="btn btn-ghost" onclick="startFaceCam()" disabled>Start camera</button>
            <button id="btn-capture" class="btn btn-main" onclick="captureFace()" disabled>Capture my face</button>
          </div>

          <div class="toggle-card">
            <div>
              <div class="toggle-title">Enable Face ID login</div>
              <div class="toggle-help">Capture a clean face scan first, then switch this on for passwordless access.</div>
            </div>
            <label class="toggle-label">
              <div class="toggle-shell">
                <input type="checkbox" id="faceid-toggle"
                       <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'checked' : ''; ?>
                       onchange="toggleFaceId(this.checked)">
                <div id="toggle-track" style="background:<?php echo ($userObj && $userObj->isFaceEnabled()) ? '#6f8fd8' : 'rgba(255,255,255,.15)'; ?>;"></div>
                <div id="toggle-thumb" style="left:<?php echo ($userObj && $userObj->isFaceEnabled()) ? '24px' : '4px'; ?>;"></div>
              </div>
              <span id="toggle-label" style="font-size:13px;color:var(--muted);"><?php echo ($userObj && $userObj->isFaceEnabled()) ? 'Enabled' : 'Disabled'; ?></span>
            </label>
          </div>
        </div>

        <aside class="face-side">
          <div id="faceid-status" class="face-status <?php echo ($userObj && $userObj->isFaceEnabled()) ? 'is-active' : ''; ?>">
            <span class="face-status-icon"><?php echo ($userObj && $userObj->isFaceEnabled()) ? 'OK' : '--'; ?></span>
            <div>
              <strong><?php echo ($userObj && $userObj->isFaceEnabled()) ? 'Face ID is active' : 'Face ID not configured'; ?></strong>
              <div class="toggle-help">
                <?php echo ($userObj && $userObj->isFaceEnabled())
                  ? 'You can log in using your face on the login page.'
                  : 'Capture your face below then enable Face ID.'; ?>
              </div>
            </div>
          </div>
          <div class="face-tip"><strong>Best capture</strong>Use even lighting, center your face, and keep the camera steady for one clean scan.</div>
          <div class="face-tip"><strong>Security state</strong>Your Face ID toggle can be disabled any time without changing your account password.</div>
        </aside>
      </div>
    </div>
  </div>
    </main>
    </div>
  </div>

  

  <script>
    window.CAREERSTRAND_USER = { userId: <?php echo (int) $userId; ?> };

    function setProfileMode(mode) {
      const page = document.querySelector('.page-wrap');
      if (!page) return;
      page.classList.toggle('is-editing', mode === 'edit');
      if (mode === 'view') {
        history.replaceState(null, '', 'profile.php');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    function switchTab(tabName) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

      const activeBtn = document.querySelector('.tab-btn[onclick*="' + tabName + '"]');
      if (activeBtn) activeBtn.classList.add('active');

      const activePanel = document.getElementById('tab-' + tabName);
      if (activePanel) activePanel.classList.add('active');
    }

    document.addEventListener('click', function(event) {
      const chip = event.target.closest('.skill-suggestion-chip');
      if (!chip) return;
      const name = chip.dataset.skillName || chip.textContent.trim();
      if (typeof openAssessment === 'function') openAssessment(name);
    });

    document.addEventListener('DOMContentLoaded', function () {
      const btn   = document.getElementById('saOpenBtn');
      const input = document.getElementById('skillNameRequest');
      if (btn && input) {
        btn.addEventListener('click', function () {
          const name = input.value.trim();
          if (name && typeof openAssessment === 'function') openAssessment(name);
        });
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            const name = input.value.trim();
            if (name && typeof openAssessment === 'function') openAssessment(name);
          }
        });
      }
    });
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
          if (thumb) thumb.style.left       = enabled ? '24px'    : '4px';
          if (label) label.textContent      = enabled ? 'Enabled'  : 'Disabled';
          const banner = document.getElementById('faceid-status');
          if (banner) {
            banner.classList.toggle('is-active', enabled);
            if (enabled) {
              banner.style.background = 'rgba(74,222,128,.10)';
              banner.style.border     = '1px solid rgba(74,222,128,.25)';
              banner.style.color      = 'var(--green)';
              banner.querySelector('span').textContent   = 'OK';
              banner.querySelector('strong').textContent = 'Face ID is active';
            } else {
              banner.style.background = 'rgba(255,255,255,.03)';
              banner.style.border     = '1px solid rgba(255,255,255,.08)';
              banner.style.color      = 'var(--muted)';
              banner.querySelector('span').textContent   = '--';
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

<!-- ── Skill Assessment Modal ── -->
<div class="sa-overlay" id="saOverlay" aria-modal="true" role="dialog">
  <div class="sa-modal" id="saModal">

    <div class="sa-header">
      <div>
        <div class="sa-eyebrow">Skill verification</div>
        <h2 class="sa-skill-title" id="saSkillTitle"></h2>
      </div>
      <button class="sa-close" id="saClose" aria-label="Close">&#x2715;</button>
    </div>

    <!-- choose path -->
    <div class="sa-step" id="saStepChoose">
      <p class="sa-step-lead">How did you learn <strong id="saSkillNameChoose">this skill</strong>?</p>
      <div class="sa-choice-grid">
        <button class="sa-choice-card" id="saBtnCert">
          <div class="sa-choice-card-top">
            <div class="sa-choice-icon">📜</div>
            <div class="sa-choice-label">I have a certificate</div>
          </div>
          <div class="sa-choice-body">
            <div class="sa-choice-sub">Share the URL of your certificate. An admin will review and approve the skill.</div>
          </div>
        </button>
        <button class="sa-choice-card" id="saBtnSelf">
          <div class="sa-choice-card-top">
            <div class="sa-choice-icon">🧠</div>
            <div class="sa-choice-label">I'm self-taught</div>
          </div>
          <div class="sa-choice-body">
            <div class="sa-choice-sub">Take a 5-question assessment. If automatic scoring is unavailable, your answers go to admin review.</div>
          </div>
        </button>
      </div>
    </div>

    <!-- certificate path -->
    <div class="sa-step" id="saStepCert" hidden>
      <p class="sa-step-lead">Paste the link to your certificate</p>
      <div class="sa-cert-form">
        <div class="sa-field">
          <label for="saCertUrl">Certificate URL</label>
          <input type="url" id="saCertUrl" placeholder="https://example.com/my-certificate">
          <span class="sa-field-error" id="saCertUrlError"></span>
        </div>
        <div class="sa-field-actions">
          <button type="button" class="sa-btn-ghost" id="saCertBack">← Back</button>
          <button type="button" class="sa-btn-primary" id="saCertSubmit">Submit for review</button>
        </div>
      </div>
      <p class="sa-note">An admin will review your certificate and approve or reject the request. You can track it under "Pending requests" on this page.</p>
    </div>

    <!-- loading questions -->
    <div class="sa-step" id="saStepLoading" hidden>
      <div class="sa-loading-state">
        <div class="sa-spinner"></div>
        <div class="sa-loading-label" id="saLoadingLabel">Generating questions…</div>
      </div>
    </div>

    <!-- AI unavailable — offer retry or predefined questions -->
    <div class="sa-step" id="saStepAiError" hidden>
      <div class="sa-ai-error-state">
        <div class="sa-ai-error-icon">⚠️</div>
        <h3 class="sa-ai-error-title">AI assistant is unavailable</h3>
        <p class="sa-ai-error-msg">The AI could not generate questions for <strong id="saAiErrorSkill"></strong> right now. You can wait a moment and retry, or answer a set of standard questions that an admin will review.</p>
        <div class="sa-ai-error-choices">
          <button type="button" class="sa-ai-choice-btn" id="saAiRetry">
            <span class="sa-ai-choice-icon">🔄</span>
            <span class="sa-ai-choice-label">Retry with AI</span>
            <span class="sa-ai-choice-sub">Try generating personalized questions again</span>
          </button>
          <button type="button" class="sa-ai-choice-btn" id="saAiPredefined">
            <span class="sa-ai-choice-icon">📋</span>
            <span class="sa-ai-choice-label">Answer standard questions</span>
            <span class="sa-ai-choice-sub">Predefined questions — submitted for admin review</span>
          </button>
        </div>
      </div>
    </div>

    <!-- answering questions -->
    <div class="sa-step" id="saStepQuestion" hidden>
      <div class="sa-qa-header">
        <div class="sa-qa-progress">
          <div class="sa-qa-dots" id="saQaDots"></div>
          <div class="sa-qa-count" id="saQaCount">1 / 5</div>
        </div>
        <div class="sa-pending-review-badge" id="saPendingBadge" hidden>📋 Admin review</div>
        <div class="sa-intro" id="saIntro"></div>
      </div>
      <div class="sa-question-block">
        <div class="sa-question-label">Question <span id="saQNum">1</span></div>
        <p class="sa-question-text" id="saQuestionText"></p>
      </div>
      <div class="sa-answer-block">
        <label for="saAnswerArea" class="sa-answer-label">Your answer</label>
        <textarea id="saAnswerArea" rows="5" placeholder="Type your answer here. Specific examples improve your score. (Ctrl+Enter to continue)"></textarea>
      </div>
      <div class="sa-qa-actions">
        <button type="button" class="sa-btn-ghost" id="saQBack">← Previous</button>
        <div class="sa-qa-actions-right">
          <button type="button" class="sa-btn-ghost" id="saQSkip">Skip</button>
          <button type="button" class="sa-btn-primary" id="saQNext">Next →</button>
        </div>
      </div>
    </div>

    <!-- evaluating -->
    <div class="sa-step" id="saStepEvaluating" hidden>
      <div class="sa-loading-state">
        <div class="sa-spinner"></div>
        <div class="sa-loading-label">Evaluating your answers…</div>
      </div>
    </div>

    <!-- result -->
    <div class="sa-step" id="saStepResult" hidden>
      <div class="sa-result-header">
        <div class="sa-score-ring-wrap">
          <svg class="sa-score-ring" viewBox="0 0 100 100">
            <circle class="sa-ring-bg" cx="50" cy="50" r="40"/>
            <circle class="sa-ring-fg" id="saRingFg" cx="50" cy="50" r="40"/>
          </svg>
          <div class="sa-score-center">
            <div class="sa-score-num" id="saScoreNum">0</div>
            <div class="sa-score-label">/ 100</div>
          </div>
        </div>
        <div class="sa-result-meta">
          <div class="sa-level-badge" id="saLevelBadge">Intermediate</div>
          <p class="sa-result-summary" id="saResultSummary"></p>
        </div>
      </div>
      <div class="sa-result-body">
        <div class="sa-result-col">
          <h4 class="sa-result-section-title">Strengths</h4>
          <ul class="sa-result-list" id="saStrengthsList"></ul>
        </div>
        <div class="sa-result-col">
          <h4 class="sa-result-section-title">Areas to improve</h4>
          <ul class="sa-result-list" id="saGapsList"></ul>
        </div>
      </div>
      <div class="sa-next-step" id="saNextStepBox" hidden>
        <div class="sa-next-step-label">Recommended next step</div>
        <p id="saNextStepText"></p>
      </div>
      <div class="sa-result-actions">
        <button type="button" class="sa-btn-ghost" id="saRetryAssessment">Retake assessment</button>
        <button type="button" class="sa-btn-primary" id="saSaveSkill">Save <span id="saSaveLabel"></span> to my profile</button>
      </div>
    </div>

    <!-- done -->
    <div class="sa-step" id="saStepDone" hidden>
      <div class="sa-done-state">
        <div class="sa-done-icon">&#10003;</div>
        <h3 class="sa-done-title" id="saDoneTitle">Skill added!</h3>
        <p class="sa-done-sub" id="saDoneSub">Your skill has been saved to your profile.</p>
      </div>
    </div>

    <!-- error -->
    <div class="sa-step" id="saStepError" hidden>
      <div class="sa-error-state">
        <p class="sa-error-msg" id="saErrorMsg"></p>
        <button type="button" class="sa-btn-ghost" id="saErrorRetry">← Try again</button>
      </div>
    </div>

  </div>
</div>

<script src="assets/js/skill-assessment.js?v=5"></script>

<div id="profileConfirmBar" class="profile-confirm-bar" aria-hidden="true">
  <span id="profileConfirmMsg" style="color:#f5f3ee;font-size:14px;flex:1;line-height:1.4;"></span>
  <button id="profileConfirmYes" style="background:linear-gradient(90deg,#ff6e45,#e05535);color:#fff;border:none;border-radius:10px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">Yes, remove</button>
  <button id="profileConfirmNo" style="background:rgba(111,143,216,0.12);color:#f5f3ee;border:1px solid rgba(111,143,216,0.25);border-radius:10px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">Cancel</button>
</div>
<script>
(function () {
  var _form = null;
  var bar  = document.getElementById('profileConfirmBar');
  var msg  = document.getElementById('profileConfirmMsg');
  var yes  = document.getElementById('profileConfirmYes');
  var no   = document.getElementById('profileConfirmNo');

  window.profileConfirmDelete = function (form, message) {
    _form = form;
    msg.textContent = message;
    bar.classList.add('is-open');
    bar.setAttribute('aria-hidden', 'false');
  };

  yes.addEventListener('click', function () {
    bar.classList.remove('is-open');
    bar.setAttribute('aria-hidden', 'true');
    if (_form) _form.submit();
    _form = null;
  });

  no.addEventListener('click', function () {
    bar.classList.remove('is-open');
    bar.setAttribute('aria-hidden', 'true');
    _form = null;
  });
})();
</script>
</body>
</html>

