<?php
require_once __DIR__ . '/../../Controller/ProfileController.php';

session_start();
if (!isset($_SESSION['userId'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}
$currentManagerId = (int)$_SESSION['userId'];
$profileController = new ProfileController();
$skillLibrary = $profileController->getSkillCatalog(['active' => 1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin — Opportunities</title>
  <link rel="stylesheet" href="assets/css/admin.css?v=4" />
  <link rel="stylesheet" href="assets/css/opportunities.css?v=5" />
  <link rel="stylesheet" href="assets/css/skill-multi.css" />
  <style>
    select.pill-select,
    select.skill-level-select {
      display: none !important;
    }
  </style>
</head>
<body>
<div class="admin-shell">

  
  <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

 
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
          <select class="pill-select" id="filterCategory">
            <option value="">All categories</option>
            <option>Technical</option>
            <option>Creativity</option>
            <option>Business</option>
            <option>Communication</option>
            <option>Leadership</option>
          </select>
          <select class="pill-select" id="filterLevel">
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
    <th>Location / Mode</th>
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
          <tr><td colspan="11" class="empty-row" style="text-align:center;color:var(--muted);padding:32px 0;">Loading opportunities...</td></tr>
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
          <select class="pill-select" id="fType">
            <option value="internship">Internship</option>
            <option value="job">Job</option>
            <option value="freelance">Freelance</option>
            <option value="volunteer">Volunteer</option>
          </select>
        </div>
        <div class="field">
          <label>Category</label>
          <select class="pill-select" id="fCategory">
            <option>Technical</option>
            <option>Creativity</option>
            <option>Business</option>
            <option>Communication</option>
            <option>Leadership</option>
          </select>
        </div>
        <div class="field">
          <label>Required level</label>
          <select class="pill-select" id="fLevel">
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
          </select>
        </div>
        <div class="field">
          <label>Deadline</label>
          <input type="date" id="fDeadline" />
        </div>
        <div class="field">
          <label>Location</label>
          <input type="text" id="fLocation" placeholder="e.g. Tunis, Sousse, Remote office" />
          <small style="display:block;margin-top:6px;color:var(--muted);line-height:1.5">Required for onsite and hybrid roles. Leave blank for remote opportunities.</small>
        </div>
        <div class="field">
          <label>Work mode</label>
          <select class="pill-select" id="fWorkMode">
            <option value="onsite">Onsite</option>
            <option value="hybrid">Hybrid</option>
            <option value="remote">Remote</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Status</label>
        <select class="pill-select" id="fStatus">
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      <div class="field">
        <label>Required skills</label>
        <input type="hidden" id="oppSkills" value="" />
        <div
          class="skill-multi"
          data-skill-multi
          data-skill-target="oppSkills"
          data-skill-store="json"
          data-skill-with-level="1"
          data-skill-with-primary="1"
          data-skill-datalist="skillCatalogOptions"
          data-skill-placeholder="Search the shared skill library"
        >
          <div class="skill-multi-list"></div>
          <button type="button" class="btn btn-soft btn-sm skill-multi-add">+ Add skill</button>
        </div>
        <p class="skill-helper" style="margin-top:8px;">Choose one or more skills from the shared library, then mark one as Primary.</p>
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

  <datalist id="skillCatalogOptions">
    <?php foreach ($skillLibrary as $skill): ?>
      <option value="<?= htmlspecialchars((string) ($skill['skillName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?= htmlspecialchars((string) ($skill['skillName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      </option>
    <?php endforeach; ?>
  </datalist>
  <script src="assets/js/admin.js"></script>
  <script>const MANAGER_ID = <?= $currentManagerId ?>;</script>
  <script>
    window.SKILL_LIBRARY_OPTIONS = <?= json_encode(array_values(array_filter(array_map(static fn(array $skill): string => trim((string) ($skill['skillName'] ?? '')), $skillLibrary))), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="assets/js/skill-multi.js?v=2"></script>
  <script src="assets/js/opportunities.js?v=11"></script>
</body>
</html>
