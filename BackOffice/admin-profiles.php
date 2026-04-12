<?php
require_once '../FrontOffice/config.php';

$admin = [
    'fullName' => 'Admin User',
    'email' => 'admin@careerstrand.io',
    'role' => 'admin',
    'status' => 'active',
    'userId' => null,
    'location' => 'Paris, France',
    'lastLogin' => 'Today, 09:42'
];

if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    $admin = [
        'fullName' => $_SESSION['user']['fullName'],
        'email' => $_SESSION['user']['email'],
        'role' => $_SESSION['user']['role'],
        'status' => $_SESSION['user']['status'] ?? 'active',
        'userId' => $_SESSION['user']['userId'],
        'location' => 'Paris, France',
        'lastLogin' => date('d/m/Y H:i')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin Profile</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
<div class="brand">
        <img src="images/Capture%20d%27%C3%A9cran%202026-04-12%20131757.png" alt="CareerStrand" style="height:48px;width:auto;display:block;">
      </div>      <div class="side-label">Main Menu</div>
      <nav class="nav-list">
        <a class="nav-item" href="dashboard.php"><span>Dashboard</span><span>Home</span></a>
        <a class="nav-item" href="admin-users.php"><span>Users</span><span>1.2k</span></a>
        <a class="nav-item active" href="admin-profiles.php"><span>Profiles</span><span>842</span></a>
        <a class="nav-item" href="admin-courses.html"><span>Courses</span><span>24</span></a>
        <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>18</span></a>
        <a class="nav-item" href="admin-opportunities.html"><span>Opportunities</span><span>36</span></a>
        <a class="nav-item" href="admin-applications.html"><span>Applications</span><span>128</span></a>
        <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
        <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>12</span></a>
        <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>New</span></a>
      </nav>
    </aside>
    <main class="admin-main">

      <header class="page-header">
        <div>
          <div class="eyebrow">Personal Settings</div>
          <h2>My Administrator Profile</h2>
          <p>Manage your account information, security access, and notification preferences for the CareerStrand console.</p>
        </div>
        <div class="header-actions">
          <button class="btn btn-soft">Activity Log</button>
          <button class="btn btn-main">Save Changes</button>
        </div>
      </header>

      <section class="detail-grid">

        <article class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <h3>General Information</h3>
              <p>These details are visible to other admin team members.</p>
            </div>
          </div>

          <form class="field-grid">
            <div class="split-grid" style="gap: 15px;">
              <div class="field">
                <label>First Name</label>
                <input type="text" value="<?php echo htmlspecialchars(explode(' ', $admin['fullName'])[0]); ?>">
              </div>
              <div class="field">
                <label>Last Name</label>
                <input type="text" value="<?php echo htmlspecialchars(explode(' ', $admin['fullName'], 2)[1] ?? ''); ?>">
              </div>
            </div>

            <div class="field">
              <label>Professional Email Address</label>
              <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
            </div>

            <div class="field">
              <label>Bio / Short Description</label>
              <textarea>ADN progression supervisor and opportunities audit manager.</textarea>
            </div>

            <div class="panel-header" style="margin-top: 20px; border-top: 1px solid var(--border-soft); padding-top: 20px;">
              <div class="panel-title">
                <h3>Security</h3>
                <p>Update your password to maintain back-office security.</p>
              </div>
            </div>

            <div class="field">
              <label>Current Password</label>
              <input type="password" placeholder="••••••••">
            </div>

            <div class="split-grid" style="gap: 15px;">
              <div class="field">
                <label>New Password</label>
                <input type="password" placeholder="Minimum 12 characters">
              </div>
              <div class="field">
                <label>Confirm Password</label>
                <input type="password" placeholder="Repeat password">
              </div>
            </div>
          </form>
        </article>

        <aside class="detail-card">
          <div class="profile-top">
            <div class="profile-meta">
              <div class="avatar" style="width: 64px; height: 64px; font-size: 24px;"><?php echo strtoupper(substr($admin['fullName'], 0, 1)); ?></div>
              <div>
                <h4><?php echo htmlspecialchars($admin['fullName']); ?></h4>
                <span class="status-chip status-active"><?php echo htmlspecialchars(ucfirst($admin['role'])); ?></span>
              </div>
            </div>
          </div>

          <div class="info-list" style="margin-top: 20px;">
            <div class="info-item"><span>Admin ID</span><strong>#CS-<?php echo htmlspecialchars($admin['userId'] ?? '0000'); ?></strong></div>
            <div class="info-item"><span>Access Level</span><strong>Full Access (Root)</strong></div>
            <div class="info-item"><span>Last Login</span><strong><?php echo htmlspecialchars($admin['lastLogin']); ?></strong></div>
            <div class="info-item"><span>Location</span><strong><?php echo htmlspecialchars($admin['location']); ?></strong></div>
          </div>

          <div class="side-label" style="margin-top: 30px;">Moderation Statistics</div>
          <div class="mini-grid" style="grid-template-columns: 1fr 1fr; margin-top: 10px;">
            <div class="metric-tile" style="padding: 15px; border-radius: 20px;">
              <div class="small-label">Validated Profiles</div>
              <div class="kpi" style="font-size: 22px;">1,248</div>
            </div>
            <div class="metric-tile" style="padding: 15px; border-radius: 20px;">
              <div class="small-label">Admin Actions</div>
              <div class="kpi" style="font-size: 22px;">342</div>
            </div>
          </div>

          <div class="action-list" style="margin-top: 30px;">
            <div class="action-item" style="cursor: pointer; border-color: var(--blue);">
              <span>Download My Logs</span>
              <span class="tiny-chip">Export</span>
            </div>
            <div class="action-item" style="cursor: pointer; border-color: var(--red);">
              <span style="color: var(--red);">Force Logout</span>
              <span class="tiny-chip" style="background: var(--red); color: #fff;" onclick="window.location.href='../FrontOffice/logout.php'">Sign Out</span>
            </div>
          </div>
        </aside>

      </section>
    </main>
  </div>
  <script src="assets/js/admin.js"></script>
</body>
</html>




