(function () {
  'use strict';

  // ── State ────────────────────────────────────────────────────────────────────
  const state = {
    skillName:     '',
    step:          'choose',
    questions:     [],
    topics:        [],
    intro:         '',
    answers:       [],
    currentQ:      0,
    result:        null,
    pendingReview: false, // true when using predefined questions (admin reviews instead of AI approving)
  };

  // ── DOM refs ──────────────────────────────────────────────────────────────────
  const el = {};

  function resolveEl() {
    el.overlay       = document.getElementById('saOverlay');
    el.modal         = document.getElementById('saModal');
    el.closeBtn      = document.getElementById('saClose');
    el.skillTitle    = document.getElementById('saSkillTitle');

    el.steps = {
      choose:     document.getElementById('saStepChoose'),
      cert:       document.getElementById('saStepCert'),
      loading:    document.getElementById('saStepLoading'),
      aiError:    document.getElementById('saStepAiError'),
      question:   document.getElementById('saStepQuestion'),
      evaluating: document.getElementById('saStepEvaluating'),
      result:     document.getElementById('saStepResult'),
      done:       document.getElementById('saStepDone'),
      error:      document.getElementById('saStepError'),
    };

    // choose
    el.skillNameChoose = document.getElementById('saSkillNameChoose');
    el.btnCert         = document.getElementById('saBtnCert');
    el.btnSelf         = document.getElementById('saBtnSelf');

    // cert
    el.certUrl         = document.getElementById('saCertUrl');
    el.certUrlError    = document.getElementById('saCertUrlError');
    el.certBack        = document.getElementById('saCertBack');
    el.certSubmit      = document.getElementById('saCertSubmit');

    // loading
    el.loadingLabel    = document.getElementById('saLoadingLabel');

    // AI error
    el.aiErrorSkill    = document.getElementById('saAiErrorSkill');
    el.aiRetryBtn      = document.getElementById('saAiRetry');
    el.aiPredefinedBtn = document.getElementById('saAiPredefined');

    // question
    el.qaDots          = document.getElementById('saQaDots');
    el.qaCount         = document.getElementById('saQaCount');
    el.qNum            = document.getElementById('saQNum');
    el.introText       = document.getElementById('saIntro');
    el.pendingBadge    = document.getElementById('saPendingBadge');
    el.questionText    = document.getElementById('saQuestionText');
    el.answerArea      = document.getElementById('saAnswerArea');
    el.qBack           = document.getElementById('saQBack');
    el.qSkip           = document.getElementById('saQSkip');
    el.qNext           = document.getElementById('saQNext');

    // result
    el.ringFg          = document.getElementById('saRingFg');
    el.scoreNum        = document.getElementById('saScoreNum');
    el.levelBadge      = document.getElementById('saLevelBadge');
    el.resultSummary   = document.getElementById('saResultSummary');
    el.strengthsList   = document.getElementById('saStrengthsList');
    el.gapsList        = document.getElementById('saGapsList');
    el.nextStepBox     = document.getElementById('saNextStepBox');
    el.nextStepText    = document.getElementById('saNextStepText');
    el.retryAssessment = document.getElementById('saRetryAssessment');
    el.saveSkill       = document.getElementById('saSaveSkill');
    el.saveLabel       = document.getElementById('saSaveLabel');

    // done
    el.doneTitle       = document.getElementById('saDoneTitle');
    el.doneSub         = document.getElementById('saDoneSub');

    // error
    el.errorMsg        = document.getElementById('saErrorMsg');
    el.errorRetry      = document.getElementById('saErrorRetry');
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const API_ASSESS  = '../../api/skill_assessment.php';
  const API_SKILL   = '../../Controller/UserSkillController.php';
  const CIRCUMFERENCE = 251.33;

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
  }

  function userId() {
    return (window.CAREERSTRAND_USER || {}).userId || 0;
  }

  async function apiFetch(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const text = await res.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      throw new Error('The assessment service returned an invalid response.');
    }

    if (!res.ok) {
      throw new Error((data && data.message) || 'The assessment service could not complete the request.');
    }

    return data || {};
  }

  // ── Step management ───────────────────────────────────────────────────────────
  function showStep(name) {
    state.step = name;
    Object.values(el.steps).forEach(s => { if (s) s.hidden = true; });
    if (el.steps[name]) el.steps[name].hidden = false;

    // Scroll modal to top when switching steps
    if (el.modal) el.modal.scrollTop = 0;
  }

  // ── Open / close ──────────────────────────────────────────────────────────────
  function openAssessment(skillName) {
    if (!el.overlay) return;
    state.skillName = (skillName || '').trim();
    if (!state.skillName) return;

    state.questions    = [];
    state.topics       = [];
    state.intro        = '';
    state.answers      = [];
    state.currentQ     = 0;
    state.result       = null;
    state.pendingReview = false;

    if (el.certUrl)      el.certUrl.value = '';
    if (el.certUrlError) { el.certUrlError.textContent = ''; el.certUrlError.classList.remove('visible'); }
    if (el.certUrl)      el.certUrl.classList.remove('has-error');

    el.skillTitle.textContent = state.skillName;
    if (el.skillNameChoose) el.skillNameChoose.textContent = state.skillName;

    showStep('choose');
    el.overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeAssessment() {
    if (!el.overlay) return;
    el.overlay.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  // ── Choose path ───────────────────────────────────────────────────────────────
  function chooseCertificate() {
    showStep('cert');
    setTimeout(() => el.certUrl?.focus(), 60);
  }

  function chooseSelfTaught() {
    loadQuestions();
  }

  // ── Certificate path ──────────────────────────────────────────────────────────
  async function submitCertificate() {
    const url = (el.certUrl?.value || '').trim();

    if (!url) {
      showCertError('Please enter the certificate URL.');
      return;
    }
    try { new URL(url); } catch {
      showCertError('Please enter a valid URL (starting with https://).');
      return;
    }

    setSubmitState(el.certSubmit, true, 'Submitting…');

    try {
      const data = await apiFetch(API_SKILL, {
        userId:         userId(),
        skillName:      state.skillName,
        source:         'Certificate',
        certificateUrl: url,
        validatedAt:    today(),
        approvalStatus: 'pending',
      });

      if (data.success) {
        showStep('done');
        if (el.doneTitle) el.doneTitle.textContent = 'Submitted for review!';
        if (el.doneSub)   el.doneSub.textContent   = `Your ${state.skillName} certificate has been submitted. An admin will review it shortly.`;
        scheduleReload();
      } else {
        showError(data.message || 'Could not save skill. Please try again.');
      }
    } catch {
      showError('Network error. Please check your connection and try again.');
    } finally {
      setSubmitState(el.certSubmit, false, 'Submit for review');
    }
  }

  function showCertError(msg) {
    if (!el.certUrlError) return;
    el.certUrlError.textContent = msg;
    el.certUrlError.classList.add('visible');
    el.certUrl?.classList.add('has-error');
  }

  // ── Assessment: load questions ─────────────────────────────────────────────────
  async function loadQuestions() {
    state.pendingReview = false;
    showStep('loading');
    if (el.loadingLabel) {
      el.loadingLabel.innerHTML = `Generating <strong>${esc(state.skillName)}</strong> questions…`;
    }

    try {
      const data = await apiFetch(API_ASSESS, { action: 'start', skillName: state.skillName });

      if (!data.success || !Array.isArray(data.questions) || data.questions.length < 3) {
        showStep('error');
        if (el.errorMsg) el.errorMsg.textContent = 'Could not load assessment questions. Please try again.';
        return;
      }

      state.questions = data.questions.slice(0, 5);
      state.topics    = data.topics    || [];
      state.intro     = data.intro     || '';
      state.answers   = new Array(state.questions.length).fill('');
      state.currentQ  = 0;

      // If AI wasn't available and we got fallback questions, offer the user a choice
      if (data.source !== 'ai') {
        showAiError();
        return;
      }

      renderQuestion(0);
      showStep('question');
    } catch {
      // Pre-load fallback questions so "use predefined" can proceed without another API call
      if (!state.questions.length) {
        const fallback = buildFallbackQuestions(state.skillName);
        state.questions = fallback;
        state.answers   = new Array(fallback.length).fill('');
        state.currentQ  = 0;
        state.intro     = '';
      }
      showAiError();
    }
  }

  function showAiError() {
    if (el.aiErrorSkill) el.aiErrorSkill.textContent = state.skillName;
    showStep('aiError');
  }

  // Fallback question set used when the API call itself throws
  function buildFallbackQuestions(skillName) {
    return [
      `What is ${skillName} and what problems does it solve? Give a practical example.`,
      `Describe the most complex thing you have built or solved using ${skillName}.`,
      `What are the core concepts you need to understand to work effectively with ${skillName}?`,
      `How do you debug common issues when working with ${skillName}? Walk through your process.`,
      `How would you rate your ${skillName} proficiency compared to industry standards, and what would you still like to learn?`,
    ];
  }

  // ── AI error step handlers ────────────────────────────────────────────────────
  function retryAI() {
    state.questions = [];
    state.answers   = [];
    loadQuestions();
  }

  function usePredefinedQuestions() {
    state.pendingReview = true;
    renderQuestion(0);
    showStep('question');
  }

  // ── Assessment: question navigation ──────────────────────────────────────────
  function renderQuestion(index) {
    const n = state.questions.length;
    state.currentQ = index;

    // Show/hide pending review badge
    if (el.pendingBadge) {
      el.pendingBadge.hidden = !state.pendingReview;
    }

    // Intro only on first question
    if (el.introText) {
      el.introText.textContent = (index === 0 && !state.pendingReview) ? (state.intro || '') : '';
    }

    if (el.questionText) el.questionText.textContent = state.questions[index] || '';
    if (el.answerArea)   el.answerArea.value = state.answers[index] || '';
    if (el.qNum)         el.qNum.textContent  = String(index + 1);
    if (el.qaCount)      el.qaCount.textContent = `${index + 1} / ${n}`;

    // dots
    if (el.qaDots) {
      el.qaDots.innerHTML = state.questions.map((_, i) => {
        const cls = i < index ? 'sa-qa-dot done' : i === index ? 'sa-qa-dot active' : 'sa-qa-dot';
        return `<span class="${cls}"></span>`;
      }).join('');
    }

    if (el.qNext) {
      el.qNext.textContent = index < n - 1 ? 'Next →' : (state.pendingReview ? 'Submit for review' : 'Submit answers');
    }
    if (el.qBack) el.qBack.style.visibility = index === 0 ? 'hidden' : 'visible';

    setTimeout(() => el.answerArea?.focus(), 60);
  }

  function saveCurrentAnswer() {
    if (el.answerArea) state.answers[state.currentQ] = el.answerArea.value;
  }

  function goNextQuestion() {
    saveCurrentAnswer();
    const n = state.questions.length;
    if (state.currentQ < n - 1) {
      renderQuestion(state.currentQ + 1);
    } else {
      state.pendingReview ? submitPendingReview() : submitAnswers();
    }
  }

  function goPrevQuestion() {
    saveCurrentAnswer();
    if (state.currentQ > 0) renderQuestion(state.currentQ - 1);
  }

  function skipQuestion() {
    state.answers[state.currentQ] = '';
    if (el.answerArea) el.answerArea.value = '';
    const n = state.questions.length;
    if (state.currentQ < n - 1) {
      renderQuestion(state.currentQ + 1);
    } else {
      state.pendingReview ? submitPendingReview() : submitAnswers();
    }
  }

  // ── Predefined questions path: save as pending (admin reviews) ────────────────
  async function submitPendingReview(reason) {
    saveCurrentAnswer();
    showStep('loading');
    if (el.loadingLabel) el.loadingLabel.innerHTML = 'Submitting for admin review…';

    try {
      const data = await apiFetch(API_SKILL, {
        userId:         userId(),
        skillName:      state.skillName,
        source:         'Self-taught',
        validatedAt:    today(),
        approvalStatus: 'pending',
        assessmentData: JSON.stringify({ questions: state.questions, answers: state.answers }),
      });

      if (data.success) {
        showStep('done');
        if (el.doneTitle) el.doneTitle.textContent = 'Submitted for review!';
        if (el.doneSub) {
          el.doneSub.textContent = reason
            ? `${reason} Your ${state.skillName} answers were sent to an admin or manager for review.`
            : `Your ${state.skillName} skill has been submitted. An admin will assess your answers and approve it.`;
        }
        scheduleReload();
      } else {
        showError(data.message || 'Could not save skill. Please try again.');
      }
    } catch {
      showError('Network error. Please check your connection and try again.');
    }
  }

  // ── AI assessment path: evaluate then save ────────────────────────────────────
  async function submitAnswers() {
    saveCurrentAnswer();
    showStep('evaluating');

    try {
      const data = await apiFetch(API_ASSESS, {
        action:    'evaluate',
        skillName: state.skillName,
        questions: state.questions,
        answers:   state.answers,
      });

      if (!data.success || typeof data.score !== 'number') {
        await submitPendingReview('The automatic evaluation could not finish.');
        return;
      }

      state.result = data;
      renderResult(data);
      showStep('result');
    } catch {
      await submitPendingReview('The automatic evaluation could not finish.');
    }
  }

  function renderResult(data) {
    const score = Math.max(0, Math.min(100, data.score || 0));
    const level = (data.level || 'Intermediate').toLowerCase();

    // Animate score ring
    if (el.ringFg) {
      el.ringFg.style.strokeDashoffset = String(CIRCUMFERENCE);
      el.ringFg.className = 'sa-ring-fg level-' + level;
      requestAnimationFrame(() => requestAnimationFrame(() => {
        el.ringFg.style.strokeDashoffset = String(CIRCUMFERENCE * (1 - score / 100));
      }));
    }

    // Animate score counter
    if (el.scoreNum) {
      let current = 0;
      const step  = Math.max(1, Math.round(score / 60));
      const timer = setInterval(() => {
        current = Math.min(score, current + step);
        el.scoreNum.textContent = String(current);
        if (current >= score) clearInterval(timer);
      }, 20);
    }

    if (el.levelBadge) {
      el.levelBadge.textContent = data.level || 'Intermediate';
      el.levelBadge.className   = 'sa-level-badge level-' + level;
    }

    if (el.resultSummary) el.resultSummary.textContent = data.summary || '';

    renderList(el.strengthsList, data.strengths || []);
    renderList(el.gapsList,      data.gaps      || []);

    const hasNextStep = data.nextStep && data.nextStep.trim();
    if (el.nextStepBox)  el.nextStepBox.hidden  = !hasNextStep;
    if (el.nextStepText) el.nextStepText.textContent = hasNextStep ? data.nextStep : '';

    if (el.saveLabel) el.saveLabel.textContent = state.skillName;
  }

  function renderList(container, items) {
    if (!container) return;
    container.innerHTML = (items || []).slice(0, 4).map(t => `<li>${esc(t)}</li>`).join('');
  }

  // ── Save AI-assessed skill ────────────────────────────────────────────────────
  async function saveAssessedSkill() {
    if (!state.result) return;
    setSubmitState(el.saveSkill, true, 'Saving…');

    const score = Math.max(0, Math.min(100, state.result.score || 0));

    try {
      const data = await apiFetch(API_SKILL, {
        userId:         userId(),
        skillName:      state.skillName,
        source:         'Self-taught',
        skillLevel:     score,
        validatedAt:    today(),
        approvalStatus: 'approved',
      });

      if (data.success) {
        showStep('done');
        if (el.doneTitle) el.doneTitle.textContent = 'Skill added to your profile!';
        if (el.doneSub)   el.doneSub.textContent   = `${state.skillName} has been saved with an AI-verified level of ${score}/100.`;
        scheduleReload();
      } else {
        showError(data.message || 'Could not save skill. Please try again.');
        setSubmitState(el.saveSkill, false, `Save ${state.skillName} to my profile`);
      }
    } catch {
      showError('Network error. Please check your connection and try again.');
      setSubmitState(el.saveSkill, false, `Save ${state.skillName} to my profile`);
    }
  }

  // ── Error state ───────────────────────────────────────────────────────────────
  function showError(msg) {
    if (el.errorMsg) el.errorMsg.textContent = msg;
    showStep('error');
  }

  // ── Utilities ─────────────────────────────────────────────────────────────────
  function setSubmitState(btn, disabled, label) {
    if (!btn) return;
    btn.disabled = disabled;
    btn.textContent = label;
  }

  function today() {
    return new Date().toISOString().slice(0, 10);
  }

  function scheduleReload() {
    setTimeout(() => window.location.reload(), 1800);
  }

  // ── Wire up events ────────────────────────────────────────────────────────────
  function bindEvents() {
    el.overlay?.addEventListener('click', e => {
      if (e.target === el.overlay) closeAssessment();
    });
    el.closeBtn?.addEventListener('click', closeAssessment);

    // choose
    el.btnCert?.addEventListener('click', chooseCertificate);
    el.btnSelf?.addEventListener('click', chooseSelfTaught);

    // cert
    el.certBack?.addEventListener('click', () => showStep('choose'));
    el.certSubmit?.addEventListener('click', submitCertificate);
    el.certUrl?.addEventListener('input', () => {
      if (el.certUrlError) { el.certUrlError.textContent = ''; el.certUrlError.classList.remove('visible'); }
      el.certUrl.classList.remove('has-error');
    });
    el.certUrl?.addEventListener('keydown', e => { if (e.key === 'Enter') submitCertificate(); });

    // AI error step
    el.aiRetryBtn?.addEventListener('click', retryAI);
    el.aiPredefinedBtn?.addEventListener('click', usePredefinedQuestions);

    // question
    el.qBack?.addEventListener('click', goPrevQuestion);
    el.qSkip?.addEventListener('click', skipQuestion);
    el.qNext?.addEventListener('click', goNextQuestion);
    el.answerArea?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) goNextQuestion();
    });

    // result
    el.retryAssessment?.addEventListener('click', () => {
      state.pendingReview = false;
      state.answers  = new Array(state.questions.length).fill('');
      state.currentQ = 0;
      renderQuestion(0);
      showStep('question');
    });
    el.saveSkill?.addEventListener('click', saveAssessedSkill);

    // error step
    el.errorRetry?.addEventListener('click', () => showStep('choose'));

    // keyboard
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && el.overlay?.classList.contains('is-open')) {
        closeAssessment();
      }
    });
  }

  // ── Init ──────────────────────────────────────────────────────────────────────
  function init() {
    resolveEl();
    if (!el.overlay) return;
    bindEvents();
  }

  document.addEventListener('DOMContentLoaded', init);

  window.openAssessment = openAssessment;
})();
