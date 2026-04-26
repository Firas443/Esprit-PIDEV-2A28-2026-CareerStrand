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
            $_SESSION['userId'] = $userId;
            $_SESSION['user']   = [
                'userId'    => $user->getUserId(),
                'fullName'  => $user->getFullName(),
                'email'     => $user->getEmail(),
                'role'      => $user->getRole(),
                'status'    => $user->getStatus(),
                'createdAt' => $user->getCreatedAt(),
            ];
            header('Location: profile.php');
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
    :root{--bg:#040816;--text:#f5f3ee;--muted:rgba(245,243,238,0.6);--blue:#6f8fd8;--blue-2:#95abeb;--red:#ff6e45;--white:#f5f3ee;--shadow:0 30px 90px rgba(0,0,0,0.4);--max:1220px;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:Inter,Arial,sans-serif;color:var(--text);min-height:100vh;display:flex;flex-direction:column;
      background:radial-gradient(circle at 15% 20%,rgba(111,143,216,0.1),transparent 25%),
                 radial-gradient(circle at 85% 80%,rgba(255,110,69,0.08),transparent 25%),
                 linear-gradient(120deg,#030712 0%,#071126 100%);}
    .site-header{position:sticky;top:0;z-index:50;background:rgba(4,8,22,0.62);border-bottom:1px solid rgba(255,255,255,0.08);backdrop-filter:blur(18px);}
    .header-inner{max-width:var(--max);margin:0 auto;padding:0 20px;min-height:80px;display:flex;align-items:center;justify-content:space-between;}
    .brand{display:flex;align-items:center;gap:14px;text-decoration:none;}
    .brand-logo{height:52px;width:auto;display:block;}
    .btn{padding:10px 20px;border-radius:999px;font-weight:600;text-decoration:none;display:inline-block;transition:0.3s;}
    .btn-main{background:linear-gradient(90deg,var(--blue),var(--red));color:#fff;}
    .main-content{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;}
    .card{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:40px;max-width:520px;width:100%;box-shadow:var(--shadow);}
    .head{text-align:center;margin-bottom:30px;}
    .head h1{font-size:32px;font-weight:800;margin-bottom:8px;}
    .head h1 span{color:var(--blue);}
    .head p{color:var(--muted);font-size:16px;}
    .role-tabs{display:flex;gap:10px;margin-bottom:30px;width:100%;}
    .role-tab{flex:1;min-width:0;padding:12px 8px;text-align:center;border-radius:12px;background:rgba(255,255,255,0.05);color:var(--muted);cursor:pointer;transition:0.3s;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .role-tab.active{background:var(--blue);color:var(--white);}
    .grid-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;margin-bottom:20px;width:100%;}
    .field{display:flex;flex-direction:column;min-width:0;gap:6px;}
    .field label{font-size:14px;font-weight:600;color:var(--text);}
    .field input,.field select{width:100%;padding:14px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;background:rgba(255,255,255,0.05);color:var(--text);font-size:15px;transition:0.2s;}
    .field input:focus,.field select:focus{outline:none;border-color:var(--blue);background:rgba(111,143,216,0.05);}
    .field select option{background:#071126;color:var(--text);}
    /* red border on error */
    .input-error{border-color:var(--red) !important;background:rgba(255,110,69,0.05) !important;}
    /* red message under field */
    .field-error{font-size:12px;color:var(--red);display:flex;align-items:center;gap:4px;}
    .full{grid-column:span 2;}
    .section-divider{grid-column:span 2;border:none;border-top:1px solid rgba(255,255,255,0.08);margin:4px 0 8px;}
    .section-label{grid-column:span 2;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;color:var(--blue-2);margin-bottom:-4px;}
    .role-fields{display:contents;}
    .role-fields.hidden{display:none;}
    .terms-wrap{margin-bottom:8px;}
    .terms{display:flex;align-items:flex-start;gap:10px;font-size:12px;color:var(--muted);cursor:pointer;}
    .terms input{width:auto;margin-top:2px;flex-shrink:0;}
    .terms-error{font-size:12px;color:var(--red);margin-top:4px;margin-bottom:12px;}
    .btn-submit{width:100%;padding:16px;background:linear-gradient(90deg,var(--blue),var(--red));border:none;border-radius:999px;color:#fff;font-weight:800;font-size:15px;cursor:pointer;box-shadow:0 10px 25px rgba(255,110,69,0.2);transition:0.3s;margin-top:8px;}
    .btn-submit:hover{transform:translateY(-2px);filter:brightness(1.1);}
    .foot-link{text-align:center;margin-top:25px;font-size:14px;color:var(--muted);}
    .foot-link a{color:var(--blue);text-decoration:none;font-weight:600;}
    @media(max-width:500px){.grid-fields{grid-template-columns:1fr;}.full,.section-divider,.section-label{grid-column:span 1;}.card{padding:30px 20px;border-radius:0;border:none;background:transparent;box-shadow:none;}}
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
  <header class="site-header">
    <div class="header-inner">
      <a href="/CareerStrand-template/View/FrontOffice/index.php" class="brand">
        <img src="images/CareerStrand_logo.png" alt="CareerStrand logo" class="brand-logo">
      </a>
      <a href="/CareerStrand-template/View/FrontOffice/index.php" class="btn btn-main">Back to Home</a>
    </div>
  </header>

  <main class="main-content">
    <div class="card">
      <div class="head">
        <h1>Create my <span>DNA</span></h1>
        <p>Start your progression today.</p>
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
        Already have an account? <a href="/CareerStrand-template/View/FrontOffice/login.php">Sign in</a>
      </div>
    </div>
  </main>

  <script>
    // Restore correct role tab if form was resubmitted with errors
    const savedRole = <?php echo json_encode($post['role'] ?? 'user'); ?>;
    if (savedRole !== 'user') selectRole(savedRole);
  </script>
</body>
</html>