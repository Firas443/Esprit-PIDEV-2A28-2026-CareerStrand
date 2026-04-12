<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add User - CareerStrand Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css" />
  <script>
    function validateForm() {
      let errors = [];
      const fullName = document.getElementById('fullName').value;
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      const role = document.getElementById('role').value;

      if (fullName.length < 2) errors.push('Full name must be at least 2 characters.');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid email.');
      if (password.length < 6) errors.push('Password must be at least 6 characters.');
      if (!['admin', 'manager', 'manager recruiter', 'user'].includes(role)) errors.push('Invalid role.');

      if (errors.length > 0) {
        alert(errors.join('\n'));
        return false;
      }
      return true;
    }
  </script>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <!-- Same sidebar as index -->
      <div class="brand"><div class="brand-mark"><img class="brand-logo" src="images/Capture%20d%27%C3%A9cran%202026-04-12%20131757.png" alt="CareerStrand logo" /></div><div><h1>CareerStrand Admin</h1><p>Back office console</p></div></div>
      <div class="side-label">Main Menu</div>
      <nav class="nav-list">
        <a class="nav-item" href="dashboard.php"><span>Dashboard</span><span>Home</span></a>
        <a class="nav-item active" href="admin-users.php"><span>Users</span><span>1.2k</span></a>
        <!-- Other nav items -->
      </nav>
    </aside>
    <main class="admin-main">
      <header class="page-header">
        <div><h2>Add New User</h2><p>Create a new user account.</p></div>
      </header>
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Create New User</h3>
            <p>Fill in the details to add a new user to the system.</p>
          </div>
        </div>
        <form action="admin-users.php?action=store" method="post" onsubmit="return validateForm()" style="padding: 20px;">
          <div style="margin-bottom: 15px;">
            <label for="fullName" style="display: block; margin-bottom: 5px;">Full Name:</label>
            <input type="text" id="fullName" name="fullName" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
          </div>

          <div style="margin-bottom: 15px;">
            <label for="email" style="display: block; margin-bottom: 5px;">Email:</label>
            <input type="email" id="email" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
          </div>

          <div style="margin-bottom: 15px;">
            <label for="password" style="display: block; margin-bottom: 5px;">Password:</label>
            <input type="password" id="password" name="password" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
          </div>

          <div style="margin-bottom: 15px;">
            <label for="role" style="display: block; margin-bottom: 5px;">Role:</label>
            <select id="role" name="role" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
              <option value="user">User</option>
              <option value="manager">Manager</option>
              <option value="manager recruiter">Manager Recruiter</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-main">Create User</button>
            <a href="admin-users.php" class="btn btn-soft">Cancel</a>
          </div>
        </form>
        <?php if (isset($errors)): ?>
          <div style="padding: 20px; color: red;">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>


