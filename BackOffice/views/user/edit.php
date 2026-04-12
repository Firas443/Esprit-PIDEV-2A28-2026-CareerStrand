<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit User - CareerStrand Admin</title>
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
      if (password && password.length < 6) errors.push('Password must be at least 6 characters.');
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
      <!-- Same sidebar -->
    </aside>
    <main class="admin-main">
      <header class="page-header">
        <div><h2>Edit User</h2><p>Update user information.</p></div>
      </header>
      <section>
        <form action="admin-users.php?action=update&id=<?php echo $user['userId']; ?>" method="post" onsubmit="return validateForm()">
          <label for="fullName">Full Name:</label>
          <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['fullName']); ?>" required><br>

          <label for="email">Email:</label>
          <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required><br>

          <label for="password">Password (leave blank to keep current):</label>
          <input type="password" id="password" name="password"><br>

          <label for="role">Role:</label>
          <select id="role" name="role" required>
            <option value="user" <?php if ($user['role'] == 'user') echo 'selected'; ?>>User</option>
            <option value="manager" <?php if ($user['role'] == 'manager') echo 'selected'; ?>>Manager</option>
            <option value="manager recruiter" <?php if ($user['role'] == 'manager recruiter') echo 'selected'; ?>>Manager Recruiter</option>
            <option value="admin" <?php if ($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
          </select><br>

          <label for="status">Status:</label>
          <select name="status">
            <option value="active" <?php if ($user['status'] == 'active') echo 'selected'; ?>>Active</option>
            <option value="inactive" <?php if ($user['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
          </select><br>

          <button type="submit" class="btn btn-main">Update User</button>
          <a href="admin-users.php" class="btn btn-soft">Cancel</a>
        </form>
        <?php if (isset($errors)): ?>
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?php echo $error; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
