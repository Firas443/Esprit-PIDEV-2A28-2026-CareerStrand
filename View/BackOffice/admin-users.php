<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';
require_once __DIR__ . '/../../Controller/RoleProfileController.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}
$sessionUser = $_SESSION['user'];
if ($sessionUser['role'] !== 'admin') {
    header('Location: ../FrontOffice/profile.php');
    exit;
}

$controller       = new UserController();
$profileController = new ProfileController();
$roleController    = new RoleProfileController();
$message     = null;
$messageType = 'info';
$errors      = [];

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ── POST ACTIONS ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = $controller->createUser([
            'fullName' => $_POST['fullName'] ?? '',
            'email'    => $_POST['email']    ?? '',
            'password' => $_POST['password'] ?? '',
            'role'     => $_POST['role']     ?? 'user',
            'status'   => $_POST['status']   ?? 'active',
        ]);
        if ($result['success']) {
            $newId = $result['userId'];
            // Create blank base profile
            $profileController->createOrUpdate($newId, ['bio'=>'','photoUrl'=>'','location'=>'','preferences'=>'']);
            // Role-specific profile
            $r = $_POST['role'] ?? 'user';
            if ($r === 'manager') {
                $roleController->saveManagerProfile($newId, [
                    'organization'  => trim($_POST['organization']   ?? ''),
                    'categoryFocus' => trim($_POST['categoryFocus']  ?? ''),
                    'description'   => trim($_POST['orgDescription'] ?? ''),
                ]);
            } elseif ($r === 'manager recruiter') {
                $roleController->saveRecruiterProfile($newId, [
                    'companyName'      => trim($_POST['companyName']      ?? ''),
                    'jobTitle'         => trim($_POST['jobTitle']         ?? ''),
                    'industry'         => trim($_POST['industry']         ?? ''),
                    'companyWebsite'   => trim($_POST['companyWebsite']   ?? ''),
                    'opportunityTypes' => trim($_POST['opportunityTypes'] ?? ''),
                ]);
            }
            $message = 'User created successfully.'; $messageType = 'success';
        } else {
            $errors = array_values($result['errors'] ?? []);
            $message = 'Could not create user.'; $messageType = 'error';
        }
    }

    if ($action === 'update') {
        $data = [
            'fullName' => $_POST['fullName'] ?? '',
            'email'    => $_POST['email']    ?? '',
            'role'     => $_POST['role']     ?? 'user',
            'status'   => $_POST['status']   ?? 'active',
        ];
        if (!empty($_POST['password'])) $data['password'] = $_POST['password'];
        $result = $controller->updateUser((int)($_POST['userId'] ?? 0), $data);

        if ($result['success']) {
            $uid = (int)$_POST['userId'];
            $r   = $_POST['role'] ?? 'user';
            if ($r === 'manager') {
                $roleController->saveManagerProfile($uid, [
                    'organization'  => trim($_POST['organization']   ?? ''),
                    'categoryFocus' => trim($_POST['categoryFocus']  ?? ''),
                    'description'   => trim($_POST['orgDescription'] ?? ''),
                ]);
            } elseif ($r === 'manager recruiter') {
                $roleController->saveRecruiterProfile($uid, [
                    'companyName'      => trim($_POST['companyName']      ?? ''),
                    'jobTitle'         => trim($_POST['jobTitle']         ?? ''),
                    'industry'         => trim($_POST['industry']         ?? ''),
                    'companyWebsite'   => trim($_POST['companyWebsite']   ?? ''),
                    'opportunityTypes' => trim($_POST['opportunityTypes'] ?? ''),
                ]);
            }
            $message = 'User updated successfully.'; $messageType = 'success';
        } else {
            $errors = array_values($result['errors'] ?? []);
            $message = 'Could not update user.'; $messageType = 'error';
        }
    }

    if ($action === 'delete') {
        $uid = (int)($_POST['userId'] ?? 0);
        // Clean role-specific tables first (FK safety)
        $roleController->deleteManagerProfile($uid);
        $roleController->deleteRecruiterProfile($uid);
        $result = $controller->deleteUser($uid);
        $message = $result['success'] ? 'User deleted.' : 'Could not delete user.';
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// ── GET PARAMS ────────────────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$sort   = $_GET['sort']   ?? 'fullName';
$order  = $_GET['order']  ?? 'ASC';
$users  = $controller->getAll($search, $sort, $order);
$stats  = $controller->getStats();
$mode   = null;

$selectedUser      = null;
$selectedProfile   = null;
$selectedSkills    = [];
$selectedManagerP  = null;
$selectedRecruiterP = null;
$managerActivity   = null;
$recruiterActivity = null;

if (isset($_GET['action']) && $_GET['action'] === 'create') {
    $mode = 'create';
}
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $selectedUser = $controller->getById((int)$_GET['id']);
}
if (isset($_GET['view'])) {
    $selectedUser = $controller->getById((int)$_GET['view']);
}
if ($selectedUser) {
    $uid              = $selectedUser->getUserId();
    $selectedProfile  = $profileController->getByUserId($uid);
    $selectedSkills   = $profileController->getSkillsByUserId($uid);
    $r                = $selectedUser->getRole();
    if ($r === 'manager') {
        $selectedManagerP  = $roleController->getManagerProfile($uid);
        $managerActivity   = $roleController->getManagerActivity($uid);
    } elseif ($r === 'manager recruiter') {
        $selectedRecruiterP = $roleController->getRecruiterProfile($uid);
        $recruiterActivity  = $roleController->getRecruiterActivity($uid);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Management — CareerStrand Admin</title>
  <link rel="stylesheet" href="assets/css/admin.css"/>
  <style>
    .detail-tabs{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:4px;margin-bottom:20px;}
    .dtab{flex:1;padding:8px 10px;text-align:center;border-radius:10px;font-size:12px;font-weight:600;border:none;background:transparent;color:rgba(245,243,238,.45);cursor:pointer;transition:.2s;font-family:inherit;}
    .dtab.active{background:rgba(111,143,216,.18);color:#f5f3ee;border:1px solid rgba(111,143,216,.35);}
    .dtab-panel{display:none;} .dtab-panel.active{display:block;}
    .profile-field{margin-bottom:14px;}
    .profile-field label{display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.14em;color:rgba(245,243,238,.4);margin-bottom:5px;}
    .profile-field input,.profile-field textarea,.profile-field select{width:100%;padding:10px 13px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:11px;color:#f5f3ee;font-size:13px;font-family:inherit;outline:none;resize:vertical;transition:.2s;}
    .profile-field input:focus,.profile-field textarea:focus,.profile-field select:focus{border-color:rgba(111,143,216,.6);background:rgba(111,143,216,.06);}
    .profile-field select option{background:#071126;color:#f5f3ee;}
    .profile-field textarea{min-height:72px;}
    .form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .section-sep{border:none;border-top:1px solid rgba(255,255,255,.08);margin:14px 0 10px;}
    .section-label-sm{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#95abeb;margin-bottom:10px;display:block;}
    .skill-row{display:flex;align-items:flex-start;justify-content:space-between;padding:10px 13px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px;margin-bottom:8px;gap:10px;}
    .skill-row-name{font-size:13px;font-weight:600;}
    .skill-row-source{font-size:11px;color:rgba(245,243,238,.45);}
    .skill-row-cert a{font-size:11px;color:#95abeb;text-decoration:none;}
    .skill-row-cert a:hover{text-decoration:underline;}
    .skill-row-date{font-size:11px;color:rgba(245,243,238,.35);}
    .progress-mini-bar{height:5px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden;margin-top:5px;}
    .progress-mini-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#6f8fd8,#ff6e45);transition:width .5s ease;}
    .score-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-size:11px;font-weight:700;background:rgba(74,222,128,.10);border:1px solid rgba(74,222,128,.2);color:#4ade80;margin-bottom:14px;}
    .success-bar{padding:10px 14px;border-radius:12px;font-size:13px;background:rgba(74,222,128,.10);border:1px solid rgba(74,222,128,.25);color:#4ade80;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
    .error-bar{padding:10px 14px;border-radius:12px;font-size:13px;background:rgba(255,110,69,.10);border:1px solid rgba(255,110,69,.25);color:#ff6e45;margin-bottom:14px;}
    .error-bar ul{margin:6px 0 0 14px;}
    .stat-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px;}
    .stat-row:last-child{border-bottom:none;}
    .stat-row span{color:rgba(245,243,238,.5);}
    .stat-row strong{color:#f5f3ee;}
    .stat-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#95abeb;margin:16px 0 8px;}
    /* role-fields in create form */
    .role-create-fields{display:none;}
    .role-create-fields.visible{display:block;}
  </style>
  <script>
    function validateForm() {
      const fn = document.getElementById('fullName')?.value.trim()||'';
      const em = document.getElementById('email')?.value.trim()||'';
      const pw = document.getElementById('password')?.value||'';
      const errs=[];
      if(fn.length<2) errs.push('Full name must be at least 2 characters.');
      if(em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) errs.push('Invalid email.');
      if(pw && pw.length>0 && pw.length<6) errs.push('Password must be at least 6 characters.');
      if(errs.length){alert(errs.join('\n'));return false;}
      return true;
    }
    function switchDTab(name) {
      document.querySelectorAll('.dtab').forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('.dtab-panel').forEach(p=>p.classList.remove('active'));
      document.querySelector('.dtab[data-tab="'+name+'"]').classList.add('active');
      document.getElementById('dtab-'+name).classList.add('active');
    }
    function onRoleChange(sel) {
      document.querySelectorAll('.role-create-fields').forEach(el=>el.classList.remove('visible'));
      const r = sel.value;
      if(r==='manager')           document.getElementById('rcf-manager')?.classList.add('visible');
      if(r==='manager recruiter') document.getElementById('rcf-recruiter')?.classList.add('visible');
    }
  </script>
</head>
<body>
<div class="admin-shell">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div class="brand">
      <img src="../FrontOffice/images/CareerStrand_logo.png" alt="CareerStrand" style="height:48px;width:auto;display:block;">
    </div>
    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
      <a class="nav-item"        href="admin-dashboard.php"><span>Dashboard</span><span>Home</span></a>
      <a class="nav-item active" href="admin-users.php"><span>Users</span><span><?php echo count($users); ?></span></a>
      <a class="nav-item"        href="admin-profiles.php"><span>Profiles</span><span>842</span></a>
      <a class="nav-item"        href="admin-courses.php"><span>Courses</span><span>24</span></a>
      <a class="nav-item"        href="admin-skills.php"><span>Challenges</span><span>18</span></a>
      <a class="nav-item"        href="admin-opportunities.php"><span>Opportunities</span><span>36</span></a>
      <a class="nav-item"        href="admin-applications.php"><span>Applications</span><span>128</span></a>
      <a class="nav-item"        href="admin-analytics.php"><span>ADN Analytics</span><span>Live</span></a>
      <a class="nav-item"        href="admin-feedback.php"><span>Events</span><span>12</span></a>
      <a class="nav-item"        href="admin-settings.php"><span>Settings</span><span>New</span></a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>User Management</h2>
        <p>View all users, inspect profiles &amp; skills, manage accounts and track completion.</p>
      </div>
      <div class="header-actions">
        <form method="GET" action="admin-users.php" style="display:inline-flex;gap:8px;align-items:center;">
          <div class="searchbar">
            <span>Search</span>
            <input type="text" name="search" placeholder="Name or email..."
                   value="<?php echo h($_GET['search'] ?? ''); ?>"/>
          </div>
        </form>
        <a href="admin-users.php?action=create" class="btn btn-main">+ Add user</a>
      </div>
    </header>

    <?php if ($message): ?>
      <div class="<?php echo $messageType === 'success' ? 'success-bar' : 'error-bar'; ?>" style="margin:0 0 16px;">
        <?php if ($messageType === 'success'): ?>✓ <?php endif; ?>
        <?php echo h($message); ?>
        <?php if (!empty($errors)): ?><ul><?php foreach($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>
    <?php endif; ?>

    <section class="detail-grid">

      <!-- ── LEFT: USERS TABLE ── -->
      <article class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>All users</h3>
            <p>Click a row to inspect profile &amp; skills.</p>
          </div>
          <div class="filters">
            <span class="filter">Active</span>
            <span class="filter">ADN generated</span>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th><a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'fullName','order'=>(($_GET['sort']??'')==='fullName'&&($_GET['order']??'')==='ASC')?'DESC':'ASC'])); ?>">Name</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'email','order'=>(($_GET['sort']??'')==='email'&&($_GET['order']??'')==='ASC')?'DESC':'ASC'])); ?>">Email</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'role','order'=>(($_GET['sort']??'')==='role'&&($_GET['order']??'')==='ASC')?'DESC':'ASC'])); ?>">Role</a></th>
              <th>Completion</th>
              <th>Account</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $uObj     = $u; // already a User object
              $uProfile = $profileController->getByUserId($u->getUserId());
              $uScore   = $uProfile ? $uProfile->getCompletionScore() : 0;
              $isSelected = isset($_GET['view']) && (int)$_GET['view'] === $u->getUserId();
            ?>
            <tr style="<?php echo $isSelected ? 'background:rgba(111,143,216,.07);' : ''; ?> cursor:pointer;"
                onclick="window.location='admin-users.php?view=<?php echo $u->getUserId(); ?><?php echo !empty($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>'">
              <td><strong><?php echo h($u->getFullName()); ?></strong></td>
              <td><?php echo h($u->getEmail()); ?></td>
              <td><?php echo h(ucfirst($u->getRole())); ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="flex:1;height:5px;background:rgba(255,255,255,.08);border-radius:999px;min-width:60px;">
                    <div style="height:100%;width:<?php echo $uScore; ?>%;background:linear-gradient(90deg,#6f8fd8,#ff6e45);border-radius:999px;"></div>
                  </div>
                  <span style="font-size:12px;color:rgba(245,243,238,.6);"><?php echo $uScore; ?>%</span>
                </div>
              </td>
              <td>
                <span class="status-chip <?php echo $u->getStatus() === 'active' ? 'status-active' : 'status-inactive'; ?>">
                  <?php echo ucfirst($u->getStatus()); ?>
                </span>
              </td>
              <td class="table-actions" onclick="event.stopPropagation();">
                <a href="admin-users.php?view=<?php echo $u->getUserId(); ?>" class="link-btn">View</a>
                <a href="admin-users.php?action=edit&id=<?php echo $u->getUserId(); ?>" class="link-btn">Edit</a>
                <form method="POST" action="admin-users.php" style="display:inline;" onsubmit="return confirm('Delete this user?')">
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="userId"  value="<?php echo $u->getUserId(); ?>">
                  <button type="submit" class="status-chip status-inactive" style="border:none;cursor:pointer;">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </article>

      <!-- ── RIGHT: DETAIL PANEL ── -->
      <aside class="detail-card">

        <?php if ($mode === 'create'): ?>
        <!-- ════════════════ CREATE USER ════════════════ -->
        <div class="profile-top">
          <div class="profile-meta">
            <div class="avatar">+</div>
            <div><h3>New User</h3><p>Fill in the details below.</p></div>
          </div>
        </div>
        <form action="admin-users.php" method="post" onsubmit="return validateForm()">
          <input type="hidden" name="action" value="create">
          <div class="profile-field"><label>Full Name</label><input type="text" id="fullName" name="fullName" value="<?php echo h($_POST['fullName'] ?? ''); ?>"></div>
          <div class="profile-field"><label>Email</label><input type="text" id="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>"></div>
          <div class="profile-field"><label>Password</label><input type="password" id="password" name="password" placeholder="••••••••"></div>
          <div class="form-row-2">
            <div class="profile-field"><label>Role</label>
              <select id="role" name="role" onchange="onRoleChange(this)">
                <option value="user"             <?php echo (($_POST['role']??'')==='user')             ?'selected':''; ?>>User</option>
                <option value="manager"          <?php echo (($_POST['role']??'')==='manager')          ?'selected':''; ?>>Manager</option>
                <option value="manager recruiter"<?php echo (($_POST['role']??'')==='manager recruiter')?'selected':''; ?>>Manager Recruiter</option>
                <option value="admin"            <?php echo (($_POST['role']??'')==='admin')            ?'selected':''; ?>>Admin</option>
              </select>
            </div>
            <div class="profile-field"><label>Status</label>
              <select name="status">
                <option value="active"   <?php echo (($_POST['status']??'active')==='active')  ?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo (($_POST['status']??'')==='inactive')      ?'selected':''; ?>>Inactive</option>
              </select>
            </div>
          </div>

          <!-- Manager extra fields -->
          <div id="rcf-manager" class="role-create-fields <?php echo (($_POST['role']??'')==='manager')?'visible':''; ?>">
            <hr class="section-sep">
            <span class="section-label-sm">Organization details</span>
            <div class="profile-field"><label>Organization / Club Name</label><input type="text" name="organization" value="<?php echo h($_POST['organization'] ?? ''); ?>" placeholder="e.g. TechClub Tunis"></div>
            <div class="form-row-2">
              <div class="profile-field"><label>Category Focus</label>
                <select name="categoryFocus">
                  <option value="">Select…</option>
                  <?php foreach(['Technology','Design','Business','Science','Arts','Other'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo (($_POST['categoryFocus']??'')===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="profile-field"><label>Description</label><input type="text" name="orgDescription" value="<?php echo h($_POST['orgDescription'] ?? ''); ?>" placeholder="What do you organize?"></div>
            </div>
          </div>

          <!-- Recruiter extra fields -->
          <div id="rcf-recruiter" class="role-create-fields <?php echo (($_POST['role']??'')==='manager recruiter')?'visible':''; ?>">
            <hr class="section-sep">
            <span class="section-label-sm">Company & recruiting details</span>
            <div class="form-row-2">
              <div class="profile-field"><label>Company Name</label><input type="text" name="companyName" value="<?php echo h($_POST['companyName'] ?? ''); ?>" placeholder="e.g. TechCorp"></div>
              <div class="profile-field"><label>Job Title</label><input type="text" name="jobTitle" value="<?php echo h($_POST['jobTitle'] ?? ''); ?>" placeholder="e.g. HR Manager"></div>
            </div>
            <div class="form-row-2">
              <div class="profile-field"><label>Industry</label>
                <select name="industry">
                  <option value="">Select…</option>
                  <?php foreach(['Software & IT','Finance','Healthcare','Education','Marketing','Other'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo (($_POST['industry']??'')===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="profile-field"><label>Opportunity Types</label>
                <select name="opportunityTypes">
                  <option value="">Select…</option>
                  <?php foreach(['Internship','Job','Freelance','Internship & Job','All'] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo (($_POST['opportunityTypes']??'')===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="profile-field"><label>Company Website</label><input type="text" name="companyWebsite" value="<?php echo h($_POST['companyWebsite'] ?? ''); ?>" placeholder="https://company.com"></div>
          </div>

          <div style="margin-top:16px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-main">Create User</button>
            <a href="admin-users.php" class="btn btn-soft">Cancel</a>
          </div>
        </form>

        <?php elseif ($selectedUser): ?>
        <!-- ════════════════ VIEW / EDIT USER ════════════════ -->
        <?php
          $sp    = $selectedProfile;
          $ss    = $selectedSkills;
          $score = $sp ? $sp->getCompletionScore() : 0;
          $level = $sp ? $sp->getLevel()           : 'Starter';
          $sRole = $selectedUser->getRole();
        ?>

        <!-- Avatar + name -->
        <div class="profile-top">
          <div class="profile-meta">
            <div class="avatar" style="overflow:hidden;">
              <?php if ($sp && !empty($sp->getPhotoUrl())): ?>
                <img src="<?php echo h($sp->getPhotoUrl()); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="">
              <?php else: ?>
                <?php echo strtoupper(substr($selectedUser->getFullName(), 0, 1)); ?>
              <?php endif; ?>
            </div>
            <div>
              <h3><?php echo h($selectedUser->getFullName()); ?></h3>
              <p><?php echo h($selectedUser->getEmail()); ?></p>
            </div>
          </div>
        </div>

        <div class="score-badge">⚡ <?php echo h($level); ?> — <?php echo $score; ?>% complete</div>
        <div style="margin-bottom:16px;">
          <div class="progress-mini-bar">
            <div class="progress-mini-fill" style="width:<?php echo $score; ?>%"></div>
          </div>
        </div>

        <?php if ($messageType === 'success' && $message): ?><div class="success-bar">✓ <?php echo h($message); ?></div><?php endif; ?>
        <?php if ($messageType === 'error'   && $message): ?><div class="error-bar"><?php echo h($message); ?></div><?php endif; ?>

        <!-- TABS — change third tab based on role -->
        <div class="detail-tabs">
          <button class="dtab active" data-tab="user"   onclick="switchDTab('user')">User</button>
          <button class="dtab"        data-tab="profile" onclick="switchDTab('profile')">Profile</button>
          <button class="dtab" data-tab="activity" onclick="switchDTab('activity')">
            Skills <span style="margin-left:4px;background:rgba(111,143,216,.2);border-radius:999px;padding:0 6px;font-size:10px;"><?php echo count($ss); ?></span>
          </button>
        </div>

        <!-- TAB: USER (edit form — shared for all roles + role-specific extras) -->
        <div id="dtab-user" class="dtab-panel active">
          <form action="admin-users.php" method="post" onsubmit="return validateForm()">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="userId" value="<?php echo h($selectedUser->getUserId()); ?>">
            <div class="profile-field"><label>Full Name</label><input type="text" id="fullName" name="fullName" value="<?php echo h($selectedUser->getFullName()); ?>"></div>
            <div class="profile-field"><label>Email</label><input type="text" id="email" name="email" value="<?php echo h($selectedUser->getEmail()); ?>"></div>
            <div class="profile-field">
              <label>New Password <span style="font-weight:400;text-transform:none;letter-spacing:0;">(leave blank to keep)</span></label>
              <input type="password" id="password" name="password" placeholder="••••••••">
            </div>
            <div class="form-row-2">
              <div class="profile-field"><label>Role</label>
                <select name="role">
                  <option value="user"             <?php echo $sRole==='user'             ?'selected':''; ?>>User</option>
                  <option value="manager"          <?php echo $sRole==='manager'          ?'selected':''; ?>>Manager</option>
                  <option value="manager recruiter"<?php echo $sRole==='manager recruiter'?'selected':''; ?>>Manager Recruiter</option>
                  <option value="admin"            <?php echo $sRole==='admin'            ?'selected':''; ?>>Admin</option>
                </select>
              </div>
              <div class="profile-field"><label>Status</label>
                <select name="status">
                  <option value="active"   <?php echo $selectedUser->getStatus()==='active'  ?'selected':''; ?>>Active</option>
                  <option value="inactive" <?php echo $selectedUser->getStatus()==='inactive' ?'selected':''; ?>>Inactive</option>
                </select>
              </div>
            </div>

            <!-- Manager role-specific fields inside edit tab -->
            <?php if ($sRole === 'manager'): ?>
              <hr class="section-sep">
              <span class="section-label-sm">Organization details</span>
              <div class="profile-field"><label>Organization / Club Name</label>
                <input type="text" name="organization"
                       value="<?php echo h($selectedManagerP ? $selectedManagerP->getOrganization() : ''); ?>"
                       placeholder="e.g. TechClub Tunis">
              </div>
              <div class="form-row-2">
                <div class="profile-field"><label>Category Focus</label>
                  <select name="categoryFocus">
                    <option value="">Select…</option>
                    <?php foreach(['Technology','Design','Business','Science','Arts','Other'] as $opt): ?>
                      <option value="<?php echo $opt; ?>" <?php echo ($selectedManagerP && $selectedManagerP->getCategoryFocus()===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="profile-field"><label>Description</label>
                  <input type="text" name="orgDescription"
                         value="<?php echo h($selectedManagerP ? $selectedManagerP->getDescription() : ''); ?>"
                         placeholder="What do you organize?">
                </div>
              </div>
            <?php endif; ?>

            <!-- Recruiter role-specific fields inside edit tab -->
            <?php if ($sRole === 'manager recruiter'): ?>
              <hr class="section-sep">
              <span class="section-label-sm">Company & recruiting details</span>
              <div class="form-row-2">
                <div class="profile-field"><label>Company Name</label>
                  <input type="text" name="companyName"
                         value="<?php echo h($selectedRecruiterP ? $selectedRecruiterP->getCompanyName() : ''); ?>"
                         placeholder="TechCorp">
                </div>
                <div class="profile-field"><label>Job Title</label>
                  <input type="text" name="jobTitle"
                         value="<?php echo h($selectedRecruiterP ? $selectedRecruiterP->getJobTitle() : ''); ?>"
                         placeholder="HR Manager">
                </div>
              </div>
              <div class="form-row-2">
                <div class="profile-field"><label>Industry</label>
                  <select name="industry">
                    <option value="">Select…</option>
                    <?php foreach(['Software & IT','Finance','Healthcare','Education','Marketing','Other'] as $opt): ?>
                      <option value="<?php echo $opt; ?>" <?php echo ($selectedRecruiterP && $selectedRecruiterP->getIndustry()===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="profile-field"><label>Opportunity Types</label>
                  <select name="opportunityTypes">
                    <option value="">Select…</option>
                    <?php foreach(['Internship','Job','Freelance','Internship & Job','All'] as $opt): ?>
                      <option value="<?php echo $opt; ?>" <?php echo ($selectedRecruiterP && $selectedRecruiterP->getOpportunityTypes()===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="profile-field"><label>Company Website</label>
                <input type="text" name="companyWebsite"
                       value="<?php echo h($selectedRecruiterP ? $selectedRecruiterP->getCompanyWebsite() : ''); ?>"
                       placeholder="https://company.com">
              </div>
            <?php endif; ?>

            <div class="info-list" style="margin:14px 0;">
              <div class="info-item"><span>Member since</span><strong><?php echo $selectedUser->getCreatedAt() ? date('d M Y', strtotime($selectedUser->getCreatedAt())) : '—'; ?></strong></div>
              <div class="info-item"><span>User ID</span><strong>#<?php echo $selectedUser->getUserId(); ?></strong></div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <button type="submit" class="btn btn-main">Update User</button>
              <a href="admin-users.php" class="btn btn-soft">Cancel</a>
            </div>
          </form>
        </div>

        <!-- TAB: PROFILE (read-only summary + role-specific details) -->
        <div id="dtab-profile" class="dtab-panel">
          <?php if ($sp): ?>
            <div class="info-list">
              <?php if (!empty($sp->getPhotoUrl())): ?>
              <div class="info-item"><span>Photo URL</span><strong style="word-break:break-all;font-size:11px;"><?php echo h($sp->getPhotoUrl()); ?></strong></div>
              <?php endif; ?>
              <?php if (!empty($sp->getBio())): ?>
              <div class="info-item" style="flex-direction:column;align-items:flex-start;gap:4px;">
                <span>Bio</span>
                <p style="font-size:13px;color:#f5f3ee;line-height:1.5;"><?php echo h($sp->getBio()); ?></p>
              </div>
              <?php endif; ?>
              <?php if (!empty($sp->getLocation())): ?>
              <div class="info-item"><span>Location</span><strong><?php echo h($sp->getLocation()); ?></strong></div>
              <?php endif; ?>
              <?php if (!empty($sp->getPreferences())): ?>
              <div class="info-item"><span>Preferences</span><strong><?php echo h($sp->getPreferences()); ?></strong></div>
              <?php endif; ?>
              <div class="info-item"><span>Level</span><strong><?php echo h($level); ?></strong></div>
              <div class="info-item"><span>Completion</span><strong><?php echo $score; ?>%</strong></div>
            </div>

            <!-- Role-specific profile details (read-only) -->
            <?php if ($sRole === 'manager' && $selectedManagerP): ?>
              <div class="stat-section-title">Organization Details</div>
              <div class="stat-row"><span>Organization</span><strong><?php echo h($selectedManagerP->getOrganization()); ?></strong></div>
              <div class="stat-row"><span>Category Focus</span><strong><?php echo h($selectedManagerP->getCategoryFocus()); ?></strong></div>
              <?php if (!empty($selectedManagerP->getDescription())): ?>
              <div class="stat-row"><span>Description</span><strong><?php echo h($selectedManagerP->getDescription()); ?></strong></div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($sRole === 'manager recruiter' && $selectedRecruiterP): ?>
              <div class="stat-section-title">Company Details</div>
              <div class="stat-row"><span>Company</span><strong><?php echo h($selectedRecruiterP->getCompanyName()); ?></strong></div>
              <div class="stat-row"><span>Job Title</span><strong><?php echo h($selectedRecruiterP->getJobTitle()); ?></strong></div>
              <div class="stat-row"><span>Industry</span><strong><?php echo h($selectedRecruiterP->getIndustry()); ?></strong></div>
              <?php if (!empty($selectedRecruiterP->getCompanyWebsite())): ?>
              <div class="stat-row"><span>Website</span><strong><a href="<?php echo h($selectedRecruiterP->getCompanyWebsite()); ?>" target="_blank" style="color:#95abeb;"><?php echo h($selectedRecruiterP->getCompanyWebsite()); ?></a></strong></div>
              <?php endif; ?>
              <div class="stat-row"><span>Opp. Types</span><strong><?php echo h($selectedRecruiterP->getOpportunityTypes()); ?></strong></div>
            <?php endif; ?>

          <?php else: ?>
            <div style="text-align:center;padding:32px 16px;color:rgba(245,243,238,.4);">
              <div style="font-size:32px;margin-bottom:10px;">👤</div>
              <p style="font-size:13px;">This user hasn't completed their profile yet.</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB: SKILLS (all roles) -->
        <div id="dtab-activity" class="dtab-panel">
          <?php if (!empty($ss)): ?>
            <?php foreach ($ss as $sk): ?>
            <div class="skill-row">
              <div>
                <div class="skill-row-name"><?php echo h($sk->getSkillName()); ?></div>
                <?php if ($sk->getSource()): ?><div class="skill-row-source"><?php echo h($sk->getSource()); ?></div><?php endif; ?>
                <?php if ($sk->getCertificateUrl()): ?><div class="skill-row-cert"><a href="<?php echo h($sk->getCertificateUrl()); ?>" target="_blank">Certificate →</a></div><?php endif; ?>
                <?php if ($sk->getValidatedAt()): ?><div class="skill-row-date"><?php echo date('d M Y', strtotime($sk->getValidatedAt())); ?></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align:center;padding:32px 16px;color:rgba(245,243,238,.4);">
              <div style="font-size:32px;margin-bottom:10px;">🎯</div>
              <p style="font-size:13px;">No skills added yet by this user.</p>
            </div>
          <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- DEFAULT: no user selected -->
        <div class="profile-top">
          <div class="profile-meta">
            <div class="avatar">AD</div>
            <div><h3>User Details</h3><p>Click a row or "View" to inspect a user.</p></div>
          </div>
        </div>
        <div class="info-list" style="margin-top:20px;">
          <div class="info-item"><span>Action</span><strong>Click any user row</strong></div>
          <div class="info-item"><span>Panel shows</span><strong>User · Profile · Activity</strong></div>
          <div class="info-item"><span>Total users</span><strong><?php echo count($users); ?></strong></div>
        </div>
        <?php endif; ?>

      </aside>
    </section>
  </main>
</div>
</body>
</html>