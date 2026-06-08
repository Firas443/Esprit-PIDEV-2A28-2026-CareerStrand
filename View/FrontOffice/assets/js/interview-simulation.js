(() => {
  const apiUrl = '../../api/opportunity_interview.php';
  const opportunityId = Number(
    window.CAREERSTRAND_INTERVIEW_OPPORTUNITY_ID ||
    new URLSearchParams(window.location.search).get('opportunityId') || 0
  );

  const state = {
    opportunity: null,
    intro: '',
    questions: [],
    answers: [],
    focusAreas: [],
    coachNotes: [],
    currentIndex: 0,
    finished: false,
    loading: false,
    result: null,
  };

  const el = {
    summary:          document.getElementById('opportunitySummary'),
    stage:            document.getElementById('simStageLabel'),
    progress:         document.getElementById('simProgressLabel'),
    chat:             document.getElementById('simChat'),
    questionLabel:    document.getElementById('currentQuestionLabel'),
    answerInput:      document.getElementById('answerInput'),
    backBtn:          document.getElementById('backBtn'),
    skipBtn:          document.getElementById('skipBtn'),
    nextBtn:          document.getElementById('nextBtn'),
    answerCard:       document.getElementById('answerCard'),
    focusAreas:       document.getElementById('focusAreas'),
    coachNotes:       document.getElementById('coachNotes'),
    feedbackPanel:    document.getElementById('feedbackPanel'),
    overallScore:     document.getElementById('overallScore'),
    readinessVerdict: document.getElementById('readinessVerdict'),
    feedbackSummary:  document.getElementById('feedbackSummary'),
    confidenceScore:  document.getElementById('confidenceScore'),
    structureScore:   document.getElementById('structureScore'),
    relevanceScore:   document.getElementById('relevanceScore'),
    strengthList:     document.getElementById('strengthList'),
    missingList:      document.getElementById('missingList'),
    improvementList:  document.getElementById('improvementList'),
    questionFeedback: document.getElementById('questionFeedback'),
    nextStepBox:      document.getElementById('nextStepBox'),
    nextStepText:     document.getElementById('nextStepText'),
    restartBtn:       document.getElementById('restartBtn'),
  };

  if (!el.summary || !el.chat || !el.nextBtn || !el.answerInput) { return; }

  function esc(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function setLoading(loading) {
    state.loading = loading;
    el.answerInput.disabled = loading || state.finished;
    el.backBtn.disabled     = loading || state.finished || state.currentIndex === 0;
    el.skipBtn.disabled     = loading || state.finished || !state.questions[state.currentIndex];
    el.nextBtn.disabled     = loading || state.finished || !state.questions[state.currentIndex];
    el.nextBtn.textContent  = loading
      ? 'Thinking...'
      : state.finished
        ? 'Interview complete'
        : state.currentIndex >= state.questions.length - 1
          ? 'Submit final answer'
          : 'Submit answer';
  }

  function bubble(role, title, content) {
    const msg = document.createElement('article');
    msg.className = `sim-message ${role}`;

    const av = document.createElement('div');
    av.className = 'sim-avatar';
    av.textContent = role === 'user' ? 'You' : 'AI';

    const bub = document.createElement('div');
    bub.className = 'sim-bubble';

    const strong = document.createElement('strong');
    strong.textContent = title;

    const body = document.createElement('p');
    body.textContent = content;

    bub.appendChild(strong);
    bub.appendChild(body);

    if (role === 'user') { msg.appendChild(bub); msg.appendChild(av); }
    else                 { msg.appendChild(av);  msg.appendChild(bub); }

    return msg;
  }

  function questionCard(question, index) {
    const card = document.createElement('article');
    card.className = 'sim-message';

    const av = document.createElement('div');
    av.className = 'sim-avatar';
    av.textContent = `Q${index + 1}`;

    const bub = document.createElement('div');
    bub.className = 'sim-bubble sim-question-card';

    const label = document.createElement('div');
    label.className = 'question-title';
    label.textContent = `Question ${index + 1} of ${state.questions.length}`;

    const body = document.createElement('p');
    body.textContent = question;

    bub.appendChild(label);
    bub.appendChild(body);
    card.appendChild(av);
    card.appendChild(bub);
    return card;
  }

  function answerBubble(answer) {
    return bubble('user', 'Your answer', answer && answer.trim() !== '' ? answer : 'Skipped this question.');
  }

  function renderFocusAreas() {
    el.focusAreas.innerHTML = '';
    const items = state.focusAreas.length ? state.focusAreas : ['No focus areas loaded'];
    items.forEach(item => {
      const chip = document.createElement('span');
      chip.className = 'sim-chip' + (state.focusAreas.length ? '' : ' sim-chip-muted');
      chip.textContent = item;
      el.focusAreas.appendChild(chip);
    });
  }

  function renderCoachNotes() {
    el.coachNotes.innerHTML = '';
    const notes = state.coachNotes.length
      ? state.coachNotes
      : ['Use one specific example, then close by connecting it to the role.'];
    notes.forEach(note => {
      const li = document.createElement('li');
      li.textContent = note;
      el.coachNotes.appendChild(li);
    });
  }

  function renderSummary() {
    if (!state.opportunity) { return; }
    const o = state.opportunity;
    el.summary.innerHTML = '';

    const label = document.createElement('div');
    label.className = 'summary-label';
    label.textContent = 'Opportunity snapshot';

    const title = document.createElement('strong');
    title.textContent = o.title || 'Opportunity';

    const details = document.createElement('p');
    details.textContent = [o.companyName, o.type, o.category, o.requiredLevel]
      .filter(Boolean).join(' • ');

    const desc = document.createElement('p');
    desc.textContent = o.description || 'Tailored technical questions for this role.';

    const skillRow = document.createElement('div');
    skillRow.className = 'sim-chip-row';
    (Array.isArray(o.skills) && o.skills.length ? o.skills : []).slice(0, 6).forEach(skill => {
      const chip = document.createElement('span');
      chip.className = 'sim-chip';
      chip.textContent = skill.skillName || skill;
      skillRow.appendChild(chip);
    });
    if (!skillRow.childElementCount) {
      const chip = document.createElement('span');
      chip.className = 'sim-chip sim-chip-muted';
      chip.textContent = 'No listed skills';
      skillRow.appendChild(chip);
    }

    el.summary.appendChild(label);
    el.summary.appendChild(title);
    el.summary.appendChild(details);
    el.summary.appendChild(desc);
    el.summary.appendChild(skillRow);
  }

  function updateProgress() {
    const total   = Math.max(1, state.questions.length);
    const current = Math.min(state.currentIndex + 1, total);

    el.progress.textContent = state.finished ? `${total} / ${total}` : `${current} / ${total}`;

    if (state.finished) {
      el.stage.textContent = 'Interview complete';
      el.questionLabel.textContent = 'All questions answered. Feedback is ready below.';
      return;
    }

    const q = state.questions[state.currentIndex] || '';
    el.stage.textContent       = q ? `Question ${state.currentIndex + 1}` : 'Preparing questions...';
    el.questionLabel.textContent = q || 'Loading your first question...';
  }

  function renderChat() {
    el.chat.innerHTML = '';

    if (state.intro) {
      el.chat.appendChild(bubble('assistant', 'AI interviewer', state.intro));
    }

    // Answered questions
    for (let i = 0; i < state.currentIndex; i++) {
      if (state.questions[i]) {
        el.chat.appendChild(questionCard(state.questions[i], i));
      }
      if (typeof state.answers[i] !== 'undefined') {
        el.chat.appendChild(answerBubble(state.answers[i]));
      }
    }

    // Current question
    if (!state.finished && state.questions[state.currentIndex]) {
      el.chat.appendChild(questionCard(state.questions[state.currentIndex], state.currentIndex));
    }

    if (state.finished) {
      el.chat.appendChild(bubble('assistant', 'AI interviewer', 'All done. Your detailed feedback and readiness verdict are ready below.'));
    }

    el.answerInput.value = state.answers[state.currentIndex] ?? '';
    el.answerInput.placeholder = state.finished
      ? 'The simulation is complete.'
      : 'Answer as you would in a real interview. Use specific examples and outcomes.';
    el.backBtn.disabled = state.loading || state.finished || state.currentIndex === 0;
    el.skipBtn.disabled = state.loading || state.finished || !state.questions[state.currentIndex];
    el.nextBtn.disabled = state.loading || state.finished || !state.questions[state.currentIndex];

    updateProgress();
    window.setTimeout(() => { el.chat.scrollTop = el.chat.scrollHeight; }, 0);
  }

  function renderList(target, items, fallback) {
    target.innerHTML = '';
    const list = Array.isArray(items) && items.length ? items : [fallback];
    list.forEach(item => {
      const li = document.createElement('li');
      li.textContent = item;
      target.appendChild(li);
    });
  }

  function renderQuestionFeedback(list) {
    el.questionFeedback.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      const empty = document.createElement('article');
      empty.textContent = 'No question-by-question feedback available.';
      el.questionFeedback.appendChild(empty);
      return;
    }

    list.forEach((entry, index) => {
      const article = document.createElement('article');

      const heading = document.createElement('h4');
      heading.textContent = `Question ${index + 1}`;

      const q = document.createElement('p');
      q.textContent = entry.question || '';

      const a = document.createElement('p');
      a.innerHTML = `<strong>Your answer:</strong> ${esc(entry.answer || 'Skipped this question.')}`;

      const fb = document.createElement('p');
      fb.textContent = entry.feedback || '';

      const scores = document.createElement('div');
      scores.className = 'scoreline';
      [
        `Score ${Number(entry.score || 0)}%`,
        `Confidence ${Number(entry.confidence || 0)}%`,
        `Structure ${Number(entry.structure || 0)}%`,
        `Relevance ${Number(entry.relevance || 0)}%`,
      ].forEach(label => {
        const pill = document.createElement('span');
        pill.className = 'score-pill';
        pill.textContent = label;
        scores.appendChild(pill);
      });

      article.appendChild(heading);
      article.appendChild(q);
      article.appendChild(a);
      article.appendChild(fb);
      article.appendChild(scores);
      el.questionFeedback.appendChild(article);
    });
  }

  function finalizeFeedback(result) {
    state.finished = true;
    state.result   = result;

    el.feedbackPanel.hidden = false;
    el.answerCard.hidden    = true;

    const overall    = Number(result.overallScore  || 0);
    const confidence = Number(result.confidence    || 0);
    const structure  = Number(result.structure     || 0);
    const relevance  = Number(result.relevance     || 0);
    const verdict    = String(result.readinessVerdict || '');
    const vClass     = String(result.readinessClass  || 'verdict-needs-work');
    const nextStep   = String(result.nextStep || '');
    const closingNote = String(result.closingNote || '');

    el.overallScore.textContent    = `${overall}%`;
    el.feedbackSummary.textContent = result.summary || 'Your interview feedback is ready.';
    el.confidenceScore.textContent = `${confidence}%`;
    el.structureScore.textContent  = `${structure}%`;
    el.relevanceScore.textContent  = `${relevance}%`;

    // Readiness verdict badge
    if (el.readinessVerdict && verdict) {
      el.readinessVerdict.textContent = verdict;
      el.readinessVerdict.className   = `readiness-verdict ${vClass}`;
      el.readinessVerdict.hidden      = false;
    }

    // Next step
    if (el.nextStepBox && el.nextStepText && nextStep) {
      el.nextStepText.textContent = nextStep;
      el.nextStepBox.hidden       = false;
    }

    renderList(el.strengthList,    result.strengths    || [], 'No standout strengths recorded.');
    renderList(el.missingList,     result.missingPoints || [], 'No missing points recorded.');
    renderList(el.improvementList, result.improvements  || [], 'No improvements recorded.');
    renderQuestionFeedback(result.questionFeedback || []);

    renderChat();
    if (closingNote) {
      el.chat.appendChild(bubble('assistant', 'AI interviewer', closingNote));
    }
    setLoading(false);
  }

  function showAiError(message) {
    const existing = document.getElementById('simAiWarning');
    if (existing) { existing.remove(); }
    const banner = document.createElement('div');
    banner.id = 'simAiWarning';
    banner.className = 'sim-ai-warning';
    banner.innerHTML = `<span>${esc(message)}</span> <button type="button" class="sim-retry-btn" id="simRetryBtn">Retry with AI</button>`;
    el.chat.parentElement?.insertBefore(banner, el.chat);
    const retryBtn = document.getElementById('simRetryBtn');
    if (retryBtn) { retryBtn.addEventListener('click', () => { banner.remove(); loadSimulation(); }); }
  }

  async function loadSimulation() {
    if (!opportunityId) {
      el.summary.innerHTML = '<div class="summary-label">Missing opportunity</div><strong>No opportunity selected</strong><p>Open this page from an opportunity card so we know which role to simulate.</p>';
      el.stage.textContent = 'No opportunity selected';
      el.answerCard.hidden = true;
      setLoading(false);
      return;
    }

    // Clear any previous AI warning
    const prevWarning = document.getElementById('simAiWarning');
    if (prevWarning) { prevWarning.remove(); }

    el.stage.textContent = 'Generating AI questions…';
    setLoading(true);

    try {
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'start', opportunityId }),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'The interview simulation could not be prepared.');
      }

      state.opportunity = data.opportunity || null;
      state.intro       = data.intro || `Let's practice for ${data.opportunity?.title || 'this role'}. Answer the technical questions as if this were a real interview.`;
      state.focusAreas  = Array.isArray(data.focusAreas) ? data.focusAreas : [];
      state.coachNotes  = Array.isArray(data.coachNotes) ? data.coachNotes : [];
      state.answers     = [];
      state.currentIndex = 0;
      state.finished    = false;
      state.result      = null;

      if (Array.isArray(data.questions) && data.questions.length >= 1) {
        state.questions = data.questions.map(q => String(q).trim()).filter(Boolean).slice(0, 5);
      } else {
        state.questions = [String(data.firstQuestion || '').trim()].filter(Boolean);
      }

      if (state.questions.length === 0) {
        throw new Error('No questions were returned for this opportunity.');
      }

      renderSummary();
      renderFocusAreas();
      renderCoachNotes();
      renderChat();
      setLoading(false);

      // Show warning banner when fallback questions were used
      if (data.aiError) {
        showAiError(data.aiError);
      }

      window.setTimeout(() => el.answerInput.focus(), 0);
    } catch (error) {
      el.summary.innerHTML = `<div class="summary-label">Simulation error</div><strong>Could not load the interview</strong><p>${esc(error.message || 'Unknown error')}</p>`;
      el.chat.innerHTML = '';
      el.chat.appendChild(bubble('assistant', 'AI interviewer', 'I could not start the simulation. Please try again or return to opportunities.'));
      el.answerCard.hidden = true;
      setLoading(false);
    }
  }

  async function submitAnswer(forceSkip = false) {
    if (state.finished || state.loading || !state.questions[state.currentIndex]) { return; }

    const currentAnswer = forceSkip ? '' : el.answerInput.value.trim();
    state.answers[state.currentIndex] = currentAnswer;

    const isLast = state.currentIndex >= state.questions.length - 1;

    if (!isLast) {
      // Just advance to next question — no API call needed
      state.currentIndex += 1;
      el.answerInput.value = state.answers[state.currentIndex] ?? '';
      renderChat();
      window.setTimeout(() => el.answerInput.focus(), 0);
      return;
    }

    // Last question — call evaluate
    setLoading(true);
    renderChat();

    try {
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          action:      'evaluate',
          opportunityId,
          questions:   state.questions,
          answers:     state.answers,
        }),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'The interview feedback could not be generated.');
      }

      finalizeFeedback(data.result || {});
    } catch (error) {
      state.finished = true;
      el.answerCard.hidden    = true;
      el.feedbackPanel.hidden = false;
      el.feedbackSummary.textContent = error.message || 'Feedback could not be generated right now.';
      el.overallScore.textContent    = '—';
      el.confidenceScore.textContent = '—';
      el.structureScore.textContent  = '—';
      el.relevanceScore.textContent  = '—';
      setLoading(false);
    }
  }

  function goBack() {
    if (state.loading || state.currentIndex <= 0) { return; }
    state.currentIndex -= 1;
    el.answerInput.value = state.answers[state.currentIndex] ?? '';
    renderChat();
    window.setTimeout(() => el.answerInput.focus(), 0);
  }

  el.nextBtn.addEventListener('click', () => submitAnswer(false));
  el.skipBtn.addEventListener('click', () => submitAnswer(true));
  el.backBtn.addEventListener('click', goBack);
  el.answerInput.addEventListener('keydown', event => {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      submitAnswer(false);
    }
  });

  if (el.restartBtn) {
    el.restartBtn.addEventListener('click', () => {
      state.opportunity  = null;
      state.intro        = '';
      state.questions    = [];
      state.answers      = [];
      state.focusAreas   = [];
      state.coachNotes   = [];
      state.currentIndex = 0;
      state.finished     = false;
      state.result       = null;
      el.feedbackPanel.hidden = true;
      el.answerCard.hidden    = false;
      if (el.readinessVerdict) { el.readinessVerdict.hidden = true; }
      if (el.nextStepBox)      { el.nextStepBox.hidden      = true; }
      loadSimulation();
    });
  }

  loadSimulation();
})();
