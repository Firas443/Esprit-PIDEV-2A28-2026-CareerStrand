<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin — Applications</title>
  <link rel="stylesheet" href="assets/css/admin.css?v=5" />
  <link rel="stylesheet" href="assets/css/applications.css?v=4" />
</head>
<body>
<div class="admin-shell">
  <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>Applications</h2>
        <p>Review student applications, compare ADN compatibility scores, and manage accept/reject decisions.</p>
      </div>
      <div class="header-actions">
        <div style="display:flex;flex-direction:column;gap:8px;">
          <div class="searchbar">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
              <circle cx="7" cy="7" r="4.5"/><line x1="10.2" y1="10.2" x2="13.5" y2="13.5"/>
            </svg>
            <input type="text" id="searchInput" placeholder="Search by applicant name..." />
          </div>
          <div class="searchbar">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
              <circle cx="7" cy="7" r="4.5"/><line x1="10.2" y1="10.2" x2="13.5" y2="13.5"/>
            </svg>
            <input type="text" id="searchPosition" placeholder="Search by position..." />
          </div>
        </div>
      </div>
    </header>

    <section class="split-grid" style="margin-bottom: 24px;">
      <article class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <h3>Application queue</h3>
              <p id="tableCaption">Loading...</p>
            </div>
            <div class="filter-status-row">
              <button class="filter-status-btn active" data-status="">All</button>
              <button class="filter-status-btn" data-status="saved">Saved</button>
              <button class="filter-status-btn" data-status="applied">Applied</button>
              <button class="filter-status-btn" data-status="shortlisted">Shortlisted</button>
              <button class="filter-status-btn" data-status="interview">Interview</button>
              <button class="filter-status-btn" data-status="accepted">Accepted</button>
              <button class="filter-status-btn" data-status="rejected">Rejected</button>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Applicant</th>
              <th>Position</th>
              <th>Score</th>
              <th>Applied</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="appBody">
            <tr><td colspan="6" class="empty-row">Loading applications...</td></tr>
          </tbody>
        </table>
      </article>

      <article class="panel">
        <div class="panel-header">
          <div class="panel-title"><h3>Review summary</h3><p>Current queue breakdown.</p></div>
        </div>
        <div class="bars" style="display:grid;gap:14px;">
          <div class="bar-row">
            <span>Saved</span>
            <div class="bar-track"><div class="bar-fill" id="barSaved" style="width:0%"></div></div>
            <strong id="countSaved">0</strong>
          </div>
          <div class="bar-row">
            <span>Applied</span>
            <div class="bar-track"><div class="bar-fill" id="barApplied" style="width:0%"></div></div>
            <strong id="countApplied">0</strong>
          </div>
          <div class="bar-row">
            <span>Shortlisted</span>
            <div class="bar-track"><div class="bar-fill" id="barShortlisted" style="width:0%"></div></div>
            <strong id="countShortlisted">0</strong>
          </div>
          <div class="bar-row">
            <span>Interview</span>
            <div class="bar-track"><div class="bar-fill" id="barInterview" style="width:0%"></div></div>
            <strong id="countInterview">0</strong>
          </div>
          <div class="bar-row">
            <span>Accepted</span>
            <div class="bar-track"><div class="bar-fill" id="barAccepted" style="width:0%"></div></div>
            <strong id="countAccepted">0</strong>
          </div>
          <div class="bar-row">
            <span>Rejected</span>
            <div class="bar-track"><div class="bar-fill" id="barRejected" style="width:0%"></div></div>
            <strong id="countRejected">0</strong>
          </div>
        </div>
      </article>
    </section>
  </main>
</div>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="detailModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Application details</h3>
      <button class="modal-close" id="detailModalClose">&#x2715;</button>
    </div>
    <div class="detail-grid">
      <div class="detail-field">
        <span class="detail-label">Applicant</span>
        <div class="detail-value" id="dName">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Position</span>
        <div class="detail-value" id="dPosition">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Compatibility score</span>
        <div class="detail-value" id="dScore">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Applied on</span>
        <div class="detail-value" id="dDate">—</div>
      </div>
      <div class="detail-field">
        <span class="detail-label">Status</span>
        <div class="detail-value" id="dStatus">—</div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Portfolio &amp; CV</span>
        <div class="detail-links-row" id="dRecruiterLinks">
          <a class="detail-link-btn" id="dPortfolioLinkBtn" href="#" target="_blank" rel="noopener" hidden>Submitted portfolio →</a>
          <a class="detail-link-btn" id="dPublicPortfolio" href="#" target="_blank" rel="noopener" hidden>Public portfolio page</a>
          <a class="detail-link-btn" id="dCvExport" href="#" target="_blank" rel="noopener" hidden>CV / resume</a>
          <span id="dNoLinks" style="color:var(--muted);font-size:13px;">No portfolio or CV on file.</span>
        </div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Motivation</span>
        <div class="detail-value" id="dMotivation" style="min-height:80px;">—</div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Opportunity description</span>
        <div class="detail-value" id="dDescription" style="min-height:80px;">—</div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Application notes</span>
        <div class="detail-value" id="dNotes" style="min-height:80px;">â€”</div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Progress stages</span>
        <div class="stage-track" id="dStageTrack"></div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <span class="detail-label">Timeline</span>
        <div class="timeline-box" id="dTimeline"></div>
      </div>
      <div class="detail-field" style="grid-column: 1 / -1;">
        <div class="ai-summary-head">
          <span class="detail-label">AI fit summary</span>
          <button class="btn-summarize" id="detailSummarize" type="button">Summarize with AI</button>
        </div>
        <div class="ai-summary-box" id="aiSummaryBox">
          <p class="ai-empty">Click summarize to review the portfolio, motivation, and job description.</p>
        </div>
      </div>
    </div>
    <div class="modal-actions" id="detailActions">
      <button class="btn-close-detail" id="detailModalCancel">Close</button>
      <button class="btn-stage" id="detailShortlist">Shortlist</button>
      <button class="btn-stage" id="detailInterview">Interview</button>
      <button class="btn-accept"  id="detailAccept">Accept</button>
      <button class="btn-reject"  id="detailReject">Reject</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="assets/js/admin.js"></script>
<script src="assets/js/applications.js?v=8"></script>
</body>
</html>
