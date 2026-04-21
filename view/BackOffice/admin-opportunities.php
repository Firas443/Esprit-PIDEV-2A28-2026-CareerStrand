<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin — Opportunities</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <link rel="stylesheet" href="assets/css/opportunities.css" />
</head>
<body>
<div class="admin-shell">

  
  <aside class="admin-sidebar">
    <div class="brand">
      <div class="brand-badge"></div>
      <div><h1>CareerStrand Admin</h1><p>Back office console</p></div>
    </div>
    <div class="side-label">Main Menu</div>
    <nav class="nav-list">
      <a class="nav-item" href="admin-dashboard.html"><span>Dashboard</span><span>Home</span></a>
      <a class="nav-item" href="admin-users.html"><span>Users</span><span>—</span></a>
      <a class="nav-item" href="admin-profiles.html"><span>Profiles</span><span>—</span></a>
      <a class="nav-item" href="admin-questions.html"><span>Courses</span><span>—</span></a>
      <a class="nav-item" href="admin-skills.html"><span>Challenges</span><span>—</span></a>
      <a class="nav-item active" href="admin-opportunities.php"><span>Opportunities</span><span id="sideCount">—</span></a>
      <a class="nav-item active" href="admin-applications.php"><span>Applications</span><span>—</span></a>
      <a class="nav-item" href="admin-analytics.html"><span>ADN Analytics</span><span>Live</span></a>
      <a class="nav-item" href="admin-feedback.html"><span>Events</span><span>—</span></a>
      <a class="nav-item" href="admin-settings.html"><span>Settings</span><span>—</span></a>
    </nav>
  </aside>

 
  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>Opportunities</h2>
        <p>Manage internships, junior roles, and freelance tasks. Only published opportunities are visible to users.</p>
      </div>
      <div class="header-actions">
        <div class="searchbar">
          <span>Search</span>
          <input type="text" id="searchInput" placeholder="Search by title..." />
        </div>
        <button class="btn btn-soft" id="filterPublished">Published only</button>
        <button class="btn btn-main" id="btnCreate">+ Create opportunity</button>
      </div>
    </header>

    <section class="panel" style="margin-top:0;">
      <div class="panel-header">
        <div class="panel-title">
          <h3>Opportunity library</h3>
          <p id="tableCaption">Loading...</p>
        </div>
        <div class="filter-row">
          <select id="filterCategory">
            <option value="">All categories</option>
            <option>Technical</option>
            <option>Creativity</option>
            <option>Business</option>
            <option>Communication</option>
            <option>Leadership</option>
          </select>
          <select id="filterLevel">
            <option value="">All levels</option>
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
          </select>
        </div>
      </div>

      <table id="oppoTable">
<thead>
  <tr>
    <th>Title</th>
    <th>Type</th>
    <th>Category</th>
    <th>Level</th>
    <th>Deadline</th>
    <th>Created</th>     
    <th>By</th>          
    <th>Apps</th>
    <th>Status</th>
    <th>Actions</th>
  </tr>
</thead>
        <tbody id="oppoBody">
          <tr><td colspan="10" class="empty-row" style="text-align:center;color:var(--muted);padding:32px 0;">Loading opportunities...</td></tr>
        </tbody>
      </table>
    </section>
  </main>
</div>


<div class="modal-overlay" id="formModal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modalTitle">Create opportunity</h3>
      <button class="modal-close" id="formModalClose">&#x2715;</button>
    </div>
    <input type="hidden" id="editId" value="" />
    <div class="field-grid">
      <div class="field">
        <label>Title</label>
        <input type="text" id="fTitle" placeholder="e.g. UI/UX Designer Intern" />
         <span class="field-error" id="titleError">An opportunity with this title already exists.</span>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea id="fDescription" placeholder="What will the candidate do?"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="field">
          <label>Type</label>
          <select id="fType">
            <option value="internship">Internship</option>
            <option value="job">Job</option>
            <option value="freelance">Freelance</option>
            <option value="volunteer">Volunteer</option>
          </select>
        </div>
        <div class="field">
          <label>Category</label>
          <select id="fCategory">
            <option>Technical</option>
            <option>Creativity</option>
            <option>Business</option>
            <option>Communication</option>
            <option>Leadership</option>
          </select>
        </div>
        <div class="field">
          <label>Required level</label>
          <select id="fLevel">
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
          </select>
        </div>
        <div class="field">
          <label>Deadline</label>
          <input type="date" id="fDeadline" />
        </div>
      </div>
      <div class="field">
        <label>Status</label>
        <select id="fStatus">
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel-modal" id="formModalCancel">Cancel</button>
      <button class="btn-save" id="formModalSave">Save opportunity</button>
    </div>
  </div>
</div>

<!-- ── DELETE CONFIRM MODAL ── -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-head">
      <h3>Delete opportunity</h3>
      <button class="modal-close" id="deleteModalClose">&#x2715;</button>
    </div>
    <p class="delete-confirm-text">
      You are about to permanently delete <strong id="deleteTarget">this opportunity</strong>
      and all its linked applications. This cannot be undone.
    </p>
    <div class="modal-actions">
      <button class="btn-cancel-modal" id="deleteModalCancel">Cancel</button>
      <button class="btn-delete-confirm" id="deleteModalConfirm">Yes, delete</button>
    </div>
  </div>
</div>


<div class="toast" id="toast"></div>

<script src="assets/js/admin.js"></script>
<script src="assets/js/opportunities.js"></script>
</body>
</html>