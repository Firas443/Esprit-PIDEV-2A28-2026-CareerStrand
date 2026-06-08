<?php
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../Controller/ProfileController.php';

$frontUser = requireFrontUser();
$profileController = new ProfileController();
$frontProfile = $profileController->getByUserId((int) $frontUser['userId']);
$opportunityId = isset($_GET['opportunityId']) ? (int) $_GET['opportunityId'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand | Interview Simulation</title>
  <link rel="stylesheet" href="assets/css/frontoffice.css" />
  <link rel="stylesheet" href="assets/css/front-nav.css" />
  <link rel="stylesheet" href="assets/css/interview-simulation.css?v=2" />
</head>
<body class="interview-simulation-page">
  <?php
    $activePage = 'opportunities';
    $brandSubtitle = 'interview simulation';
    include __DIR__ . '/partials/front-nav.php';
  ?>

  <main>
    <div class="container sim-shell">
      <section class="sim-hero">
        <div class="sim-hero-copy">
          <div class="page-eyebrow"><span class="dot"></span> opportunity interview lab</div>
          <h1>Practice the interview before you apply.</h1>
          <p>This simulation reads the selected opportunity, then asks tailored questions based on the role, company context, skill requirements, and your current profile.</p>
        </div>
        <div class="sim-summary-card" id="opportunitySummary">
          <div class="summary-label">Loading opportunity</div>
          <strong>Please wait while we prepare your simulation.</strong>
          <p>The page will load a personalized interview plan as soon as the opportunity data is ready.</p>
        </div>
      </section>

      <section class="sim-grid">
        <div class="sim-panel">
          <div class="sim-panel-head">
            <div>
              <span>AI interviewer</span>
              <strong id="simStageLabel">Preparing your simulation</strong>
            </div>
            <div class="sim-progress" id="simProgressLabel">0 / 5</div>
          </div>

          <div class="sim-chat" id="simChat" aria-live="polite"></div>

          <div class="sim-input-card" id="answerCard">
            <div class="sim-question-pill" id="currentQuestionLabel">Waiting for the first question...</div>
            <label class="sim-answer-label" for="answerInput">Your answer</label>
            <textarea id="answerInput" rows="6" placeholder="Answer as if you are in a real interview. Use examples, outcomes, and role-specific details."></textarea>
            <div class="sim-actions">
              <button class="btn-secondary" type="button" id="skipBtn">Skip question</button>
              <div class="sim-actions-right">
                <button class="btn-secondary" type="button" id="backBtn">Previous</button>
                <button class="btn-primary" type="button" id="nextBtn">Submit answer</button>
              </div>
            </div>
          </div>
        </div>

        <aside class="sim-sidebar">
          <div class="sim-side-card">
            <div class="sim-side-label">Focus areas</div>
            <div class="sim-chip-row" id="focusAreas">
              <span class="sim-chip sim-chip-muted">Loading...</span>
            </div>
          </div>
          <div class="sim-side-card">
            <div class="sim-side-label">Coach notes</div>
            <ul class="sim-note-list" id="coachNotes">
              <li>We will tailor the questions to the selected opportunity.</li>
            </ul>
          </div>
          <div class="sim-side-card">
            <div class="sim-side-label">Tip before you start</div>
            <p class="sim-side-copy">Answer like a real candidate: lead with the point, give one example, then close with why it matters for this role.</p>
          </div>
          <a class="btn-link" href="opportunities.php">Back to opportunities</a>
        </aside>
      </section>

      <section class="feedback-panel" id="feedbackPanel" hidden>
        <div class="feedback-header">
          <div>
            <div class="page-eyebrow"><span class="dot"></span> simulation feedback</div>
            <h2>How you performed</h2>
          </div>
          <div class="feedback-score" id="overallScore">0%</div>
        </div>

        <p class="feedback-summary" id="feedbackSummary"></p>
        <div class="readiness-verdict" id="readinessVerdict" hidden></div>

        <div class="feedback-metrics">
          <div class="metric-card">
            <span>Confidence</span>
            <strong id="confidenceScore">0%</strong>
          </div>
          <div class="metric-card">
            <span>Structure</span>
            <strong id="structureScore">0%</strong>
          </div>
          <div class="metric-card">
            <span>Relevance</span>
            <strong id="relevanceScore">0%</strong>
          </div>
        </div>

        <div class="feedback-columns">
          <div class="feedback-box">
            <h3>Strengths</h3>
            <ul id="strengthList"></ul>
          </div>
          <div class="feedback-box">
            <h3>Missing points</h3>
            <ul id="missingList"></ul>
          </div>
          <div class="feedback-box">
            <h3>How to improve before applying</h3>
            <ul id="improvementList"></ul>
          </div>
        </div>

        <div class="question-feedback" id="questionFeedback"></div>

        <div class="next-step-box" id="nextStepBox" hidden>
          <div class="sim-side-label">Recommended next step</div>
          <p id="nextStepText"></p>
        </div>

        <div class="feedback-actions">
          <a class="btn-primary" href="opportunities.php">Return to opportunities</a>
          <button class="btn-secondary" type="button" id="restartBtn">Try again</button>
        </div>
      </section>
    </div>
  </main>

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
        'level' => trim((string) ($frontProfile?->getLevel() ?? '')),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.CAREERSTRAND_INTERVIEW_OPPORTUNITY_ID = <?php echo json_encode($opportunityId); ?>;
  </script>
  <script src="assets/js/interview-simulation.js?v=3"></script>
</body>
</html>

