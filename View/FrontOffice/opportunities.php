<?php
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';

$frontUser = requireFrontUser();
$profileController = new ProfileController();
$frontProfile = $profileController->getByUserId((int) $frontUser['userId']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand | Opportunities</title>
  <link rel="stylesheet" href="assets/css/frontoffice.css" />
  <link rel="stylesheet" href="assets/css/opportunities.css?v=10" />
  <link rel="stylesheet" href="assets/css/front-nav.css" />

</head>
<body class="opportunities-page">
  <canvas class="webgl-dna opportunity-canvas"></canvas>
  <div class="dna-blur-overlay opportunity-veil"></div>

  <?php
    $activePage = 'opportunities';
    $brandSubtitle = 'opportunity desk';
    include __DIR__ . '/partials/front-nav.php';
  ?>

  <main>
    <div class="container oppo-shell">

      <section class="oppo-hero fade-up" aria-label="Opportunity overview">
        <div class="oppo-hero-copy">
          <div class="page-eyebrow"><span class="dot"></span> live opportunity radar</div>
          <h1>Find your next opening.</h1>
          <p>Browse roles, internships, freelance missions, and volunteer paths ranked by your current skill signal and local relevance when location data is available.</p>
          <div class="oppo-hero-actions">
            <button class="btn btn-main" type="button" onclick="document.getElementById('searchInput').focus()">Search roles</button>
            <button class="btn btn-ghost" type="button" id="heroDrawerBtn">Review applications</button>
          </div>
        </div>
        <div class="oppo-hero-signal">
          <div class="signal-top">
            <span>Opportunity signal</span>
            <strong id="heroMatchLabel">Scanning</strong>
          </div>
          <div class="signal-row">
            <div><span>Live</span><strong id="statOpen">0</strong></div>
            <div><span>Closing</span><strong id="statClosing">0</strong></div>
            <div><span>Applied</span><strong id="statApplied">0</strong></div>
            <div><span>Best match</span><strong id="heroBestMatch">0%</strong></div>
          </div>
        </div>
      </section>

      <div class="oppo-header fade-up">
        <div class="oppo-header-top fade-up">
          <div class="oppo-title-block">
            <div class="page-eyebrow"><span class="dot"></span>matched to your profile</div>
            <h2>Your opportunity desk</h2>
            <p>Filter the market by match strength, role type, skill, deadline, and application status.</p>
          </div>
        </div>

        <div class="filter-bar">
          <div class="filter-tabs">
            <button class="filter-tab" data-filter="nearby">Nearby <span class="tab-count">0</span></button>
            <button class="filter-tab active" data-filter="recommended">Recommended <span class="tab-count">4</span></button>
            <button class="filter-tab" data-filter="all">All <span class="tab-count">9</span></button>
            <button class="filter-tab" data-filter="new">New <span class="tab-count">3</span></button>
            <button class="filter-tab" data-filter="closing">Closing soon <span class="tab-count">2</span></button>
            <button class="filter-tab" data-filter="applied">Applied <span class="tab-count">1</span></button>
            <button class="filter-tab" data-filter="saved">Saved <span class="tab-count">0</span></button>
          </div>
          <div class="search-sort">
            <div class="search-box">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <circle cx="7" cy="7" r="4.5"/><line x1="10.2" y1="10.2" x2="13.5" y2="13.5"/>
              </svg>
              <input type="text" id="searchInput" placeholder="Search roles" />
            </div>
            <select class="pill-select" id="typeFilter">
              <option value="">All types</option>
              <option value="internship">Internship</option>
              <option value="job">Job</option>
              <option value="freelance">Freelance</option>
              <option value="volunteer">Volunteer</option>
            </select>
            <select class="pill-select" id="sortSelect">
              <option value="match">Best match</option>
              <option value="newest">Newest</option>
              <option value="deadline">Deadline</option>
            </select>
          </div>
        </div>

        <div class="filter-dropdown-row">
          <select class="pill-select" id="categoryFilter">
            <option value="all">All categories</option>
            <option value="technical">Technical</option>
            <option value="creativity">Creativity</option>
            <option value="business">Business</option>
            <option value="communication">Communication</option>
            <option value="leadership">Leadership</option>
          </select>
          <select class="pill-select" id="skillFilter">
            <option value="">All skills</option>
          </select>
        </div>
      </div>

      <div class="results-meta">
        <span><strong id="resultCount">0</strong> opportunities found</span>
        <span id="filterLabel">Showing all categories</span>
      </div>

      <div class="oppo-grid" id="oppoGrid"></div>

    </div>
  </main>

  <div class="modal-overlay" id="applyModal">
    <div class="modal">
      <div class="modal-header">
        <div>
          <div class="modal-role"    id="modalRole">Role</div>
          <div class="modal-company" id="modalCompany">Company</div>
        </div>
        <button class="modal-close" id="modalClose">&#x2715;</button>
      </div>
      <div class="modal-body">
        <div class="modal-compat">
          <div class="modal-compat-label">Opportunity fit</div>
          <div class="compat-bar">
            <div class="compat-label-row"><span>Match strength</span><span id="modalScore">0%</span></div>
            <div class="compat-track"><div class="compat-fill" id="modalBar" style="width:0%"></div></div>
          </div>
        </div>
        <div class="modal-field">
          <label>Why are you a great fit?</label>
          <textarea id="modalMotivation" rows="4" placeholder="Tell them how your skills and journey align with this opportunity..."></textarea>
        </div>
        <div class="modal-field">
          <label>Portfolio or relevant link (optional)</label>
          <input id="modalPortfolio" type="url" placeholder="https://your-work.com" pattern="https://.*"/>
        </div>
        <div class="modal-actions">
          <button class="btn-cancel"       id="modalCancel">Cancel</button>
          <button class="btn-submit-apply" id="modalSubmit">Submit application</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-overlay detail-modal-overlay" id="detailModal">
    <div class="modal detail-modal">
      <div class="detail-modal-hero">
        <div class="detail-modal-kicker"><span class="dot"></span> Opportunity detail</div>
        <div class="detail-modal-top">
          <div>
            <div class="modal-company" id="detailCompany">Company</div>
            <div class="modal-role" id="detailRole">Role</div>
          </div>
          <button class="modal-close" id="detailClose">&#x2715;</button>
        </div>
        <p class="detail-modal-summary" id="detailSummary">Opportunity summary</p>
        <div class="detail-modal-meta" id="detailBadges"></div>
      </div>
      <div class="detail-modal-body">
        <div class="detail-modal-main">
          <div class="detail-panel">
            <div class="detail-panel-label">What this looks like</div>
            <p id="detailDescription"></p>
          </div>
          <div class="detail-panel">
            <div class="detail-panel-label">Skills requested</div>
            <div class="detail-chip-row" id="detailSkills"></div>
          </div>
          <div class="detail-panel">
            <div class="detail-panel-label">Why this was recommended</div>
            <div class="reason-list" id="detailReasons"></div>
          </div>
        </div>
        <aside class="detail-modal-side">
          <div class="detail-stat">
            <span>Fit</span>
            <strong id="detailMatch">0%</strong>
          </div>
          <div class="detail-stat">
            <span>Type</span>
            <strong id="detailType">—</strong>
          </div>
          <div class="detail-stat">
            <span>Deadline</span>
            <strong id="detailDeadline">—</strong>
          </div>
          <div class="detail-stat">
            <span>Level</span>
            <strong id="detailLevel">—</strong>
          </div>
          <div class="detail-stat">
            <span>Location</span>
            <strong id="detailLocation">—</strong>
          </div>
          <div class="detail-stat">
            <span>Work mode</span>
            <strong id="detailWorkMode">—</strong>
          </div>
          <button class="btn-simulate detail-sim-btn" id="detailSimBtn">Interview simulation</button>
          <button class="btn-submit-apply detail-apply-btn" id="detailApplyBtn">Apply now</button>
        </aside>
      </div>
    </div>
  </div>

  <div class="footer-note">CareerStrand concept demo &middot; Opportunities page</div>



  <button class="drawer-fab" id="drawerBtn" title="My applications">
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="2" width="14" height="16" rx="3"/>
      <path d="M7 7h6M7 10.5h4"/>
    </svg>
    <span>My applications</span>
    <span class="drawer-badge" id="drawerBadge" style="display:none">0</span>
  </button>


  <div class="drawer-backdrop" id="drawerBackdrop"></div>

  
  <aside class="submissions-drawer" id="submissionsDrawer">
    <div class="drawer-header">
      <div>
        <div class="drawer-title">My applications</div>
        <div class="drawer-sub" id="drawerSub">Applications you've submitted</div>
      </div>
      <button class="drawer-close" id="drawerClose">&#x2715;</button>
    </div>
    <div class="drawer-list" id="drawerList"></div>
  </aside>

  <div class="toast" id="toast"></div>

  <script>
    window.CAREERSTRAND_USER = <?php echo json_encode([
        'userId' => (int) $frontUser['userId'],
        'name' => $frontUser['fullName'] ?? '',
        'email' => $frontUser['email'] ?? '',
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.CAREERSTRAND_PROFILE = <?php echo json_encode([
        'location' => trim((string) ($frontProfile?->getLocation() ?? '')),
        'preferences' => trim((string) ($frontProfile?->getPreferences() ?? '')),
        'careerGoal' => trim((string) ($frontProfile?->getCareerGoal() ?? '')),
        'bio' => trim((string) ($frontProfile?->getBio() ?? '')),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="https://unpkg.com/three@0.160.0/build/three.min.js"></script>
  <script src="assets/js/opportunity-field.js?v=2"></script>
  <script src="assets/js/opportunities.js?v=21"></script>
  <script src="assets/js/applications.js?v=7"></script>
</body>
</html>
