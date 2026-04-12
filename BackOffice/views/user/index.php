<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management — CareerStrand Admin</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <style>
    /* ─── Extra styles for profile/skills panel ─── */
    .detail-tabs {
      display: flex; gap: 4px;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 14px; padding: 4px;
      margin-bottom: 20px;
    }
    .dtab {
      flex: 1; padding: 8px 10px; text-align: center;
      border-radius: 10px; font-size: 12px; font-weight: 600;
      border: none; background: transparent;
      color: rgba(245,243,238,.45); cursor: pointer;
      transition: .2s; font-family: inherit;
    }
    .dtab.active {
      background: rgba(111,143,216,.18);
      color: #f5f3ee;
      border: 1px solid rgba(111,143,216,.35);
    }
    .dtab-panel { display: none; }
    .dtab-panel.active { display: block; }

    .profile-field { margin-bottom: 14px; }
    .profile-field label {
      display: block; font-size: 10px; font-weight: 600;
      text-transform: uppercase; letter-spacing: .14em;
      color: rgba(245,243,238,.4); margin-bottom: 5px;
    }
    .profile-field input, .profile-field textarea, .profile-field select {
      width: 100%; padding: 10px 13px;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.09);
      border-radius: 11px; color: #f5f3ee;
      font-size: 13px; font-family: inherit;
      outline: none; resize: vertical;
      transition: .2s;
    }
    .profile-field input:focus, .profile-field textarea:focus {
      border-color: rgba(111,143,216,.6);
      background: rgba(111,143,216,.06);
    }
    .profile-field textarea { min-height: 72px; }

    .skill-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 13px; background: rgba(255,255,255,.03);
      border: 1px solid rgba(255,255,255,.07); border-radius: 12px;
      margin-bottom: 8px; gap: 10px;
    }
    .skill-row-name { font-size: 13px; font-weight: 600; }
    .skill-row-source { font-size: 11px; color: rgba(245,243,238,.45); }
    .skill-row-cert a { font-size: 11px; color: #95abeb; text-decoration: none; }
    .skill-row-cert a:hover { text-decoration: underline; }
    .skill-row-date { font-size: 11px; color: rgba(245,243,238,.35); }

    .progress-mini { margin: 10px 0; }
    .progress-mini-bar {
      height: 5px; background: rgba(255,255,255,.08);
      border-radius: 999px; overflow: hidden; margin-top: 5px;
    }
    .progress-mini-fill {
      height: 100%; border-radius: 999px;
      background: linear-gradient(90deg, #6f8fd8, #ff6e45);
      transition: width .5s ease;
    }

    .score-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 11px; border-radius: 999px;
      font-size: 11px; font-weight: 700;
      background: rgba(74,222,128,.10);
      border: 1px solid rgba(74,222,128,.2);
      color: #4ade80; margin-bottom: 14px;
    }

    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .success-bar {
      padding: 10px 14px; border-radius: 12px; font-size: 13px;
      background: rgba(74,222,128,.10); border: 1px solid rgba(74,222,128,.25);
      color: #4ade80; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
    }
    .error-bar {
      padding: 10px 14px; border-radius: 12px; font-size: 13px;
      background: rgba(255,110,69,.10); border: 1px solid rgba(255,110,69,.25);
      color: #ff6e45; margin-bottom: 14px;
    }
    .error-bar ul { margin: 6px 0 0 14px; }
  </style>
  <script>
    function validateForm() {
      const fullName = document.getElementById('fullName')?.value.trim() || '';
      const email    = document.getElementById('email')?.value.trim()    || '';
      const password = document.getElementById('password')?.value        || '';
      const errors   = [];
      if (fullName.length < 2) errors.push('Full name must be at least 2 characters.');
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid email.');
      if (password && password.length > 0 && password.length < 6) errors.push('Password must be at least 6 characters.');
      if (errors.length) { alert(errors.join('\n')); return false; }
      return true;
    }
    function switchDTab(name) {
      document.querySelectorAll('.dtab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.dtab-panel').forEach(p => p.classList.remove('active'));
      document.querySelector('.dtab[data-tab="' + name + '"]').classList.add('active');
      document.getElementById('dtab-' + name).classList.add('active');
    }
  </script>
</head>
<body>
<div class="admin-shell">
  <!-- ── SIDEBAR ── -->
  <aside class="admin-sidebar">
    <div class="brand">
        <img src="images/Capture%20d%27%C3%A9cran%202026-04-12%20131757.png" alt="CareerStrand" style="height:48px;width:auto;display:block;">
      </div>
    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
      <a class="nav-item" href="dashboard.php"><span>Dashboard</span><span>Home</span></a>
      <a class="nav-item active" href="admin-users.php"><span>Users</span><span><?php echo count($users); ?></span></a>
      <a class="nav-item" href="admin-profiles.php"><span>Profiles</span><span>842</span></a>
      <a class="nav-item" href="admin-courses.html"><span>Courses</span><span>24</span></a>
      <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>18</span></a>
      <a class="nav-item" href="admin-opportunities.html"><span>Opportunities</span><span>36</span></a>
      <a class="nav-item" href="admin-applications.html"><span>Applications</span><span>128</span></a>
      <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
      <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>12</span></a>
      <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>New</span></a>
    </nav>
  </aside>

  <!-- ── MAIN ── -->
  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>User Management</h2>
        <p>View all users, inspect profiles & skills, manage accounts and track completion.</p>
      </div>
      <div class="header-actions">
        <form method="GET" action="admin-users.php" style="display:inline-flex;gap:8px;align-items:center;">
          <div class="searchbar">
            <span>Search</span>
            <input type="text" name="search" placeholder="Name or email..."
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"/>
          </div>
          <button type="submit" class="btn btn-soft">Search</button>
        </form>
        <a href="admin-users.php?action=create" class="btn btn-main">+ Add user</a>
      </div>
    </header>

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

        <?php if (isset($_GET['updated'])): ?>
          <div class="success-bar" style="margin:0 0 12px;">✓ User updated successfully.</div>
        <?php endif; ?>

        <table>
          <thead>
            <tr>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'fullName','order'=>(($_GET['sort']??'')==='fullName'&&($_GET['order']??'')==='ASC')?'DESC':'ASC'])); ?>">Name</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'email','order'=>(($_GET['sort']??'')==='email'&&($_GET['order']??'')==='ASC')?'DESC':'ASC'])); ?>">Email</a></th>
              <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'role','order'=>(($_GET['sort']??'')==='role'&&($_GET['order']??'')==='ASC')?'DESC':'ASC'])); ?>">Role</a></th>
              <th>Completion</th>
              <th>Account</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $uProfile = $this->profileModel->getByUserId($u['userId']);
              $uScore   = $uProfile['completionScore'] ?? 0;
              $uLevel   = $uProfile['level'] ?? 'Starter';
              $isSelected = isset($_GET['view']) && $_GET['view'] == $u['userId'];
            ?>
            <tr style="<?php echo $isSelected ? 'background:rgba(111,143,216,.07);' : ''; ?> cursor:pointer;"
                onclick="window.location='admin-users.php?view=<?php echo $u['userId']; ?><?php echo !empty($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''; ?>'">
              <td><strong><?php echo htmlspecialchars($u['fullName']); ?></strong></td>
              <td><?php echo htmlspecialchars($u['email']); ?></td>
              <td><?php echo htmlspecialchars(ucfirst($u['role'])); ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="flex:1;height:5px;background:rgba(255,255,255,.08);border-radius:999px;min-width:60px;">
                    <div style="height:100%;width:<?php echo $uScore; ?>%;background:linear-gradient(90deg,#6f8fd8,#ff6e45);border-radius:999px;"></div>
                  </div>
                  <span style="font-size:12px;color:rgba(245,243,238,.6);"><?php echo $uScore; ?>%</span>
                </div>
              </td>
              <td>
                <span class="status-chip <?php echo $u['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                  <?php echo ucfirst($u['status']); ?>
                </span>
              </td>
              <td class="table-actions" onclick="event.stopPropagation();">
                <a href="admin-users.php?view=<?php echo $u['userId']; ?>" class="link-btn">View</a>
                <a href="admin-users.php?action=edit&id=<?php echo $u['userId']; ?>" class="link-btn">Edit</a>
                <a href="admin-users.php?action=delete&id=<?php echo $u['userId']; ?>"
                   class="status-chip status-inactive"
                   onclick="return confirm('Delete this user?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </article>

      <!-- ── RIGHT: DETAIL PANEL ── -->
      <aside class="detail-card">

        <?php if (isset($mode) && $mode === 'create'): ?>
          <!-- CREATE USER -->
          <div class="profile-top">
            <div class="profile-meta">
              <div class="avatar">+</div>
              <div><h3>New User</h3><p>Fill in the details below.</p></div>
            </div>
          </div>
          <?php if (!empty($errors)): ?>
            <div class="error-bar"><ul><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <form action="admin-users.php?action=store" method="post" onsubmit="return validateForm()">
            <div class="profile-field"><label>Full Name</label><input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($old['fullName'] ?? ''); ?>" required></div>
            <div class="profile-field"><label>Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required></div>
            <div class="profile-field"><label>Password</label><input type="password" id="password" name="password" placeholder="••••••••" required></div>
            <div class="form-row-2">
              <div class="profile-field"><label>Role</label>
                <select id="role" name="role">
                  <option value="user"             <?php echo (($old['role']??'')==='user')             ? 'selected':''; ?>>User</option>
                  <option value="manager"          <?php echo (($old['role']??'')==='manager')          ? 'selected':''; ?>>Manager</option>
                  <option value="manager recruiter"<?php echo (($old['role']??'')==='manager recruiter')? 'selected':''; ?>>Manager Recruiter</option>
                  <option value="admin"            <?php echo (($old['role']??'')==='admin')            ? 'selected':''; ?>>Admin</option>
                </select>
              </div>
              <div class="profile-field"><label>Status</label>
                <select name="status">
                  <option value="active"  <?php echo (($old['status']??'')==='active')  ? 'selected':''; ?>>Active</option>
                  <option value="inactive"<?php echo (($old['status']??'')==='inactive') ? 'selected':''; ?>>Inactive</option>
                </select>
              </div>
            </div>
            <div style="margin-top:16px;display:flex;gap:10px;">
              <button type="submit" class="btn btn-main">Create User</button>
              <a href="admin-users.php" class="btn btn-soft">Cancel</a>
            </div>
          </form>

        <?php elseif (isset($selectedUser)): ?>
          <!-- VIEW / EDIT USER + PROFILE + SKILLS -->
          <?php
            $sp = isset($selectedProfile) ? $selectedProfile : null;
            $ss = isset($selectedSkills)  ? $selectedSkills  : [];
            $score = $sp['completionScore'] ?? 0;
            $level = $sp['level']           ?? 'Starter';
          ?>

          <!-- Avatar + name -->
          <div class="profile-top">
            <div class="profile-meta">
              <div class="avatar" style="overflow:hidden;">
                <?php if (!empty($sp['photoUrl'])): ?>
                  <img src="<?php echo htmlspecialchars($sp['photoUrl']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="">
                <?php else: ?>
                  <?php echo strtoupper(substr($selectedUser['fullName'], 0, 1)); ?>
                <?php endif; ?>
              </div>
              <div>
                <h3><?php echo htmlspecialchars($selectedUser['fullName']); ?></h3>
                <p><?php echo htmlspecialchars($selectedUser['email']); ?></p>
              </div>
            </div>
          </div>

          <!-- Completion score -->
          <div class="score-badge">⚡ <?php echo $level; ?> — <?php echo $score; ?>% complete</div>
          <div class="progress-mini">
            <div class="progress-mini-bar">
              <div class="progress-mini-fill" style="width:<?php echo $score; ?>%"></div>
            </div>
          </div>

          <?php if (isset($_GET['updated'])): ?>
            <div class="success-bar">✓ Updated successfully.</div>
          <?php endif; ?>
          <?php if (!empty($errors)): ?>
            <div class="error-bar"><ul><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>

          <!-- TABS -->
          <div class="detail-tabs">
            <button class="dtab active" data-tab="user"    onclick="switchDTab('user')">User</button>
            <button class="dtab"        data-tab="profile" onclick="switchDTab('profile')">Profile</button>
            <button class="dtab"        data-tab="skills"  onclick="switchDTab('skills')">
              Skills
              <span style="margin-left:4px;background:rgba(111,143,216,.2);border-radius:999px;padding:0 6px;font-size:10px;">
                <?php echo count($ss); ?>
              </span>
            </button>
          </div>

          <!-- TAB: USER -->
          <div id="dtab-user" class="dtab-panel active">
            <form action="admin-users.php?action=update&id=<?php echo $selectedUser['userId']; ?>" method="post" onsubmit="return validateForm()">
              <div class="profile-field"><label>Full Name</label><input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($selectedUser['fullName']); ?>" required></div>
              <div class="profile-field"><label>Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($selectedUser['email']); ?>" required></div>
              <div class="profile-field"><label>New Password <span style="font-weight:400;text-transform:none;letter-spacing:0;">(leave blank to keep)</span></label><input type="password" id="password" name="password" placeholder="••••••••"></div>
              <div class="form-row-2">
                <div class="profile-field"><label>Role</label>
                  <select id="role" name="role">
                    <option value="user"             <?php echo $selectedUser['role']==='user'             ? 'selected':''; ?>>User</option>
                    <option value="manager"          <?php echo $selectedUser['role']==='manager'          ? 'selected':''; ?>>Manager</option>
                    <option value="manager recruiter"<?php echo $selectedUser['role']==='manager recruiter'? 'selected':''; ?>>Manager Recruiter</option>
                    <option value="admin"            <?php echo $selectedUser['role']==='admin'            ? 'selected':''; ?>>Admin</option>
                  </select>
                </div>
                <div class="profile-field"><label>Status</label>
                  <select name="status">
                    <option value="active"  <?php echo $selectedUser['status']==='active'  ? 'selected':''; ?>>Active</option>
                    <option value="inactive"<?php echo $selectedUser['status']==='inactive' ? 'selected':''; ?>>Inactive</option>
                  </select>
                </div>
              </div>
              <div class="info-list" style="margin:14px 0;">
                <div class="info-item"><span>Member since</span><strong><?php echo $selectedUser['createdAt'] ? date('d M Y', strtotime($selectedUser['createdAt'])) : '—'; ?></strong></div>
                <div class="info-item"><span>User ID</span><strong>#<?php echo $selectedUser['userId']; ?></strong></div>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-main">Update User</button>
                <a href="admin-users.php" class="btn btn-soft">Cancel</a>
              </div>
            </form>
          </div>

          <!-- TAB: PROFILE -->
          <div id="dtab-profile" class="dtab-panel">
            <?php if ($sp): ?>
              <div class="info-list">
                <?php if (!empty($sp['bio'])): ?>
                <div class="info-item" style="flex-direction:column;align-items:flex-start;gap:4px;">
                  <span>Bio</span>
                  <p style="font-size:13px;color:#f5f3ee;line-height:1.5;"><?php echo htmlspecialchars($sp['bio']); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($sp['location'])): ?>
                <div class="info-item"><span>Location</span><strong><?php echo htmlspecialchars($sp['location']); ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($sp['preferences'])): ?>
                <div class="info-item"><span>Preferences</span><strong><?php echo htmlspecialchars($sp['preferences']); ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($sp['photoUrl'])): ?>
                <div class="info-item"><span>Photo URL</span><strong style="word-break:break-all;font-size:11px;"><?php echo htmlspecialchars($sp['photoUrl']); ?></strong></div>
                <?php endif; ?>
                <div class="info-item"><span>Level</span><strong><?php echo htmlspecialchars($level); ?></strong></div>
                <div class="info-item"><span>Completion</span><strong><?php echo $score; ?>%</strong></div>
              </div>
            <?php else: ?>
              <div style="text-align:center;padding:32px 16px;color:rgba(245,243,238,.4);">
                <div style="font-size:32px;margin-bottom:10px;">👤</div>
                <p style="font-size:13px;">This user hasn't completed their profile yet.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- TAB: SKILLS -->
          <div id="dtab-skills" class="dtab-panel">
            <?php if (!empty($ss)): ?>
              <?php foreach ($ss as $sk): ?>
              <div class="skill-row">
                <div>
                  <div class="skill-row-name"><?php echo htmlspecialchars($sk['skillName']); ?></div>
                  <?php if (!empty($sk['source'])): ?>
                    <div class="skill-row-source"><?php echo htmlspecialchars($sk['source']); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($sk['certificateUrl'])): ?>
                    <div class="skill-row-cert"><a href="<?php echo htmlspecialchars($sk['certificateUrl']); ?>" target="_blank">Certificate →</a></div>
                  <?php endif; ?>
                  <?php if (!empty($sk['validatedAt'])): ?>
                    <div class="skill-row-date"><?php echo date('d M Y', strtotime($sk['validatedAt'])); ?></div>
                  <?php endif; ?>
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
            <div class="info-item"><span>Panel shows</span><strong>User · Profile · Skills</strong></div>
            <div class="info-item"><span>Total users</span><strong><?php echo count($users); ?></strong></div>
          </div>
        <?php endif; ?>

      </aside>
    </section>
  </main>
</div>
</body>
</html>