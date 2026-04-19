<?php 
    include "C:/xampp/htdocs/Careerstrand/Controller/ControlCourses.php";

    $controlC= new ControlCourses();

    $editCourse = null;
    if (isset($_GET['update'])) {
    $editCourse = $controlC->getCourseById($_GET['update']);
    }
    if(isset($_POST['Title'])){
      $c=new Courses(
        $_POST['Title'],
        $_POST['Description'],
        $_POST['Categorie'],
        $_POST['Skill'],
        $_POST['Difficulty'],
        (int)$_POST['Duration'],
        $_POST['Statut'],
        new DateTime($_POST['Published_AT']),
      );
      if(!empty($_POST['CourseID']))
        $controlC->updateCourse($c,$_GET['update']);
      else
        $controlC->addCourse($c);
      header("Location: admin-courses.php");
    }

    if (isset($_GET['delete'])) {
      $controlC->deleteCourse($_GET['delete']);
    }
    
    $courses = $controlC->listeCourse();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin Courses</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <script src="./assets/js/courses.js"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="brand"><div class="brand-badge"></div><div><h1>CareerStrand Admin</h1><p>Back office console</p></div></div>
      <div class="side-label">Main Menu</div>
      <nav class="nav-list">
        <a class="nav-item" href="admin-dashboard.html"><span>Dashboard</span><span>Home</span></a>
        <a class="nav-item" href="admin-users.html"><span>Users</span><span>1.2k</span></a>
        <a class="nav-item" href="admin-profiles.html"><span>Profiles</span><span>842</span></a>
        <a class="nav-item active" href="admin-courses.php"><span>Courses</span><span>24</span></a>
        <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>18</span></a>
        <a class="nav-item" href="admin-opportunities.html"><span>Opportunities</span><span>36</span></a>
        <a class="nav-item" href="admin-applications.html"><span>Applications</span><span>128</span></a>
        <a class="nav-item" href="admin-calendrier.php"><span>Calendrier</span><span>128</span></a>
        <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
        <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>12</span></a>
        <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>New</span></a>
      </nav>
    </aside>
    <main class="admin-main">
      <header class="page-header">
        <div><h2>Courses Management</h2><p>Manage the education module by creating courses, tracking enrollments, and shaping the learning stage of the CareerStrand progression journey.</p></div>
        <div class="header-actions"><button class="btn btn-soft" href="./front_office/index.html">Enrollment view</button></div>
        <div class="header-actions"><a class="btn btn-soft" href="C:/xampp/htdocs/careerstrand/View/front_office/index.html">Front</a></div>
      </header>
      <section class="detail-grid">
        <article class="panel">
          <div class="panel-header"><div class="panel-title"><h3>Course catalog</h3><p>Educational content that strengthens users before they move into practical stages.</p></div><div class="filters"><input type="text" placeholder="🔍recherche"></div></div>
          <table>
            <thead><tr><th>Course</th><th>Difficulty</th><th>Duration</th><th>Categorie</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($courses as $row): ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['Title']) ?></strong></td>
                <td><span class="category-chip"><?= htmlspecialchars($row['Difficulty']) ?></span></td>
                <td><?= htmlspecialchars($row['Duration']) ?></td>
                <td><?= htmlspecialchars($row['Categorie']) ?></td>
                <td class="table-actions">
                  <a class="link-btn" href="admin-courses.php?update=<?= $row['CourseID'] ?>" onclick="return confirm('Update this course?')">Edit</a>
                  <a class="link-btn" href="admin-courses.php?delete=<?= $row['CourseID'] ?>" onclick="return confirm('Delete this course?')">Delete</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <aside class="detail-card">
          <h4>Create new course</h4>
          <form method="post" onsubmit="return validerCourse()" action="admin-courses.php?update=<?= $_GET['update'] ?? '' ?>">
            <input type="hidden" name="CourseID" value="<?= $editCourse['CourseID'] ?? '' ?>">
            <div class="field-grid">
              <div class="field"><label>Course title</label><input type="text" id="Title" name="Title" value="<?= htmlspecialchars($editCourse['Title'] ?? '') ?>" placeholder="Enter course title"/></div>
              <div class="field"><label>Description</label><textarea id="Description" name="Description" placeholder="Enter description du course"></textarea></div>
              <div class="field"><label>Category</label><select id = "Categorie" name="Categorie"><option>Programming</option><option>Design</option><option>Marketing</option><option>Business</option><option>Mathematics</option><option>Languages</option></select></div>
              <div class="field"><label>Skills</label><select id= "Skill" name="Skill"><option>Problem solving</option><option>Critical thinking</option><option>Analytical thinking</option><option>Logical reasoning</option></select></div>
              <div class="field"><label>Difficulty</label><select id="Difficulty" name="Difficulty"><option>Beginner</option><option>Intermediate</option><option>Advanced</option></select></div>
              <div class="field"><label>Status</label><select id="Statut" name="Statut"><option>Availeble</option><option>Not Availeble</option></select></div>
              <div class="field"><label>Duration</label><input type="number" id= "Duration" name="Duration" placeholder="e.g. 4 weeks" /></div>
              <div class="field"><label>Published At</label><input type="date" id="Published" name="Published" placeholder="dd/MM/aaaa" value="<?= htmlspecialchars($editCourse['CreatedAT'] ?? '') ?>" /></div>
              <br>
              <button type="submit" class="btn btn-main"><?= isset($editCourse) ? "Update Course" : "Add Course" ?></button>
            </div>
          </form>
        </aside>
      </section>
    </main>
  </div>
  <script src="assets/js/admin.js"></script>
</body>
</html>