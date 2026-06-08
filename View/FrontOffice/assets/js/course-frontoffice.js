(function () {
  "use strict";

  const dataEl = document.getElementById("course-data");
  const courses = dataEl ? JSON.parse(dataEl.textContent || "[]") : [];
  const storageKey = "careerstrand.education.v2";
  const profileContext = window.CAREERSTRAND_PROFILE || {};
  const serverProgress = window.CAREERSTRAND_COURSE_PROGRESS || {};
  const progressEndpoint = "../../Controller/ControlCourseProgress.php";

  const state = loadState();
  const els = {
    market: document.getElementById("course-market"),
    search: document.getElementById("course-search"),
    category: document.getElementById("category-filter"),
    level: document.getElementById("level-filter"),
    visibleCount: document.getElementById("visible-count"),
    enrolledStrip: document.getElementById("enrolled-strip"),
    certificateCount: document.getElementById("certificate-count"),
    modal: document.getElementById("course-modal"),
    modalContent: document.getElementById("modal-content")
  };

  let activeCourse = null;
  let activeLessonIndex = 0;

  function loadState() {
    try {
      const saved = JSON.parse(localStorage.getItem(storageKey)) || {};
      const merged = {
        enrolled: saved.enrolled || {},
        notes: saved.notes || {},
        reflections: saved.reflections || {},
        signals: saved.signals || {},
        quizResults: saved.quizResults || {}
      };
      hydrateServerProgress(merged);
      return merged;
    } catch (error) {
      const fresh = { enrolled: {}, notes: {}, reflections: {}, signals: {}, quizResults: {} };
      hydrateServerProgress(fresh);
      return fresh;
    }
  }

  function saveState() {
    localStorage.setItem(storageKey, JSON.stringify(state));
  }

  function hydrateServerProgress(targetState) {
    if (!targetState || !serverProgress || typeof serverProgress !== "object") return;

    Object.keys(serverProgress).forEach((courseId) => {
      const row = serverProgress[courseId];
      if (!row || typeof row !== "object") return;

      const completed = Array.isArray(row.completedLessons)
        ? row.completedLessons.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value >= 0)
        : [];

      targetState.enrolled[courseId] = {
        courseId: String(row.courseId ?? courseId),
        startedAt: row.enrolledAt || row.updatedAt || new Date().toISOString(),
        completedLessons: completed,
        lastLesson: Number(row.lastLessonIndex ?? 0)
      };

      targetState.quizResults[courseId] = {
        score: row.quizScore === null || row.quizScore === undefined ? 0 : Number(row.quizScore),
        passed: Boolean(row.quizPassed),
        submittedAt: row.quizSubmittedAt || null,
        answers: row.quizAnswers && typeof row.quizAnswers === "object" ? row.quizAnswers : {},
        lastAttempt: row.quizScore === null || row.quizScore === undefined
          ? null
          : {
              score: Number(row.quizScore || 0),
              correct: null,
              total: null
            }
      };
    });
  }

  function byId(id) {
    return courses.find((course) => String(course.CourseID) === String(id));
  }

  function text(value, fallback = "") {
    if (value === null || value === undefined || value === "") return fallback;
    return String(value);
  }

  function escapeHtml(value) {
    return text(value).replace(/[&<>"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    })[char]);
  }

  function buildVideoUrl(path) {
    let value = text(path);
    if (!value) return "";
    value = value.replace(/^\/+/, "");
    if (value.toLowerCase().startsWith("careerstrand/")) {
      value = value.substring("careerstrand/".length);
    }
    if (/^https?:\/\//i.test(value)) return value;
    return "../../" + value;
  }

  function getEnrollment(courseId) {
    const key = String(courseId);
    if (!state.enrolled[key]) {
      state.enrolled[key] = {
        courseId: key,
        startedAt: new Date().toISOString(),
        completedLessons: [],
        lastLesson: 0
      };
    }
    return state.enrolled[key];
  }

  async function persistLessonProgress(course) {
    if (!course) return;
    const enrollment = getEnrollment(course.CourseID);
    const formData = new FormData();
    formData.append("action", "lesson");
    formData.append("course_id", String(course.CourseID));
    formData.append("last_lesson_index", String(Math.max(0, Number(enrollment.lastLesson || 0))));
    formData.append("completed_lessons", JSON.stringify(enrollment.completedLessons || []));

    try {
      const response = await fetch(progressEndpoint, {
        method: "POST",
        body: formData,
        credentials: "same-origin"
      });
      const data = await response.json().catch(() => ({}));
      if (data && data.success && data.data && typeof data.data === "object") {
        hydrateCourseProgressFromServer(data.data);
        saveState();
      }
    } catch (error) {
      // Keep local progress even if the network call fails.
    }
  }

  async function persistQuizProgress(course, quizStateItem) {
    if (!course || !quizStateItem) return;
    const formData = new FormData();
    formData.append("action", "quiz");
    formData.append("course_id", String(course.CourseID));
    formData.append("score", String(Number(quizStateItem.score || 0)));
    formData.append("passed", quizStateItem.passed ? "1" : "0");
    formData.append("submitted_at", quizStateItem.submittedAt || new Date().toISOString());
    formData.append("answers", JSON.stringify(quizStateItem.answers || {}));

    try {
      const response = await fetch(progressEndpoint, {
        method: "POST",
        body: formData,
        credentials: "same-origin"
      });
      const data = await response.json().catch(() => ({}));
      if (data && data.success && data.data && typeof data.data === "object") {
        hydrateCourseProgressFromServer(data.data);
        saveState();
      }
    } catch (error) {
      // Keep local quiz state even if the network call fails.
    }
  }

  async function bootstrapServerProgress() {
    const lessonJobs = Object.keys(state.enrolled).map((courseId) => {
      const course = byId(courseId);
      return course ? persistLessonProgress(course) : Promise.resolve();
    });

    const quizJobs = Object.keys(state.quizResults).map((courseId) => {
      const course = byId(courseId);
      const quizStateItem = state.quizResults[courseId];
      if (!course || !quizStateItem || !quizStateItem.submittedAt) {
        return Promise.resolve();
      }
      return persistQuizProgress(course, quizStateItem);
    });

    try {
      await Promise.all([...lessonJobs, ...quizJobs]);
    } catch (error) {
      // Non-blocking sync only.
    }
  }

  function hydrateCourseProgressFromServer(row) {
    if (!row || typeof row !== "object") return;
    const courseId = String(row.courseId ?? row.course_id ?? "");
    if (!courseId) return;

    const completed = Array.isArray(row.completedLessons)
      ? row.completedLessons.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value >= 0)
      : [];

    state.enrolled[courseId] = {
      courseId,
      startedAt: row.enrolledAt || row.updatedAt || new Date().toISOString(),
      completedLessons: completed,
      lastLesson: Number(row.lastLessonIndex ?? 0)
    };

    state.quizResults[courseId] = {
      score: row.quizScore === null || row.quizScore === undefined ? 0 : Number(row.quizScore),
      passed: Boolean(row.quizPassed),
      submittedAt: row.quizSubmittedAt || null,
      answers: row.quizAnswers && typeof row.quizAnswers === "object" ? row.quizAnswers : {},
      lastAttempt: row.quizScore === null || row.quizScore === undefined
        ? null
        : {
            score: Number(row.quizScore || 0),
            correct: null,
            total: null
          }
    };
  }

  function isEnrolled(courseId) {
    return Boolean(state.enrolled[String(courseId)]);
  }

  function completedSet(courseId) {
    const enrollment = state.enrolled[String(courseId)];
    return new Set(enrollment ? enrollment.completedLessons || [] : []);
  }

  function progressFor(course) {
    const videos = Array.isArray(course.videos) ? course.videos : [];
    if (videos.length === 0) return 0;
    const done = completedSet(course.CourseID).size;
    return Math.round((done / videos.length) * 100);
  }

  function lessonTitle(video, index) {
    return text(video && video.title, "Lesson " + (index + 1));
  }

  function durationLabel(value) {
    const raw = text(value, "Self-paced");
    return /^\d+(\.\d+)?$/.test(raw) ? raw + " weeks" : raw;
  }

  function courseCode() {
    const category = text(activeCourse.Categorie, "CS").replace(/[^a-z0-9]/gi, "").slice(0, 3).toUpperCase() || "CS";
    return category + "-" + String(activeCourse.CourseID || "000").padStart(3, "0");
  }

  function courseSignal(courseId) {
    const key = String(courseId);
    if (!state.signals[key]) {
      state.signals[key] = {
        confidence: 55,
        mood: "steady"
      };
    }
    return state.signals[key];
  }

  function quizForCourse(course) {
    if (!course || !course.quiz || !Array.isArray(course.quiz.questions)) return null;
    return course.quiz;
  }

  function quizState(courseId) {
    const key = String(courseId);
    if (!state.quizResults[key]) {
      state.quizResults[key] = {
        score: 0,
        passed: false,
        submittedAt: null,
        answers: {},
        lastAttempt: null
      };
    }
    return state.quizResults[key];
  }

  function certificateReady(course) {
    const progress = progressFor(course);
    const quiz = quizForCourse(course);
    if (progress < 100) return false;
    if (!quiz) return true;
    return Boolean(quizState(course.CourseID).passed);
  }

  function quizStatusLabel(course) {
    const quiz = quizForCourse(course);
    if (!quiz) return "No exam required";
    const stateItem = quizState(course.CourseID);
    if (stateItem.passed) {
      return `Passed ${stateItem.score}%`;
    }
    if (progressFor(course) < 100) {
      return "Finish lessons to unlock exam";
    }
    return "Exam required";
  }

  const certificatePresets = {
    nvidia: {
      name: "Nvidia-style prestige",
      titleColor: "#f5f3ee",
      accent: "#6f8fd8",
      accent2: "#ff6e45",
      background:
        "radial-gradient(circle at top right, rgba(255,110,69,.16), transparent 30%), radial-gradient(circle at left bottom, rgba(111,143,216,.18), transparent 32%), linear-gradient(120deg,#030712,#071126 46%,#090514)",
      border: "rgba(111,143,216,.72)",
      frame: "rgba(255,255,255,.08)"
    },
    aurora: {
      name: "Aurora seal",
      titleColor: "#ffffff",
      accent: "#95abeb",
      accent2: "#f5bf65",
      background:
        "radial-gradient(circle at top left, rgba(245,191,101,.16), transparent 28%), radial-gradient(circle at 84% 14%, rgba(255,110,69,.14), transparent 28%), linear-gradient(120deg,#06121f,#0f1a36 48%,#140a1a)",
      border: "rgba(245,191,101,.55)",
      frame: "rgba(255,255,255,.1)"
    },
    obsidian: {
      name: "Obsidian signature",
      titleColor: "#f7f7fb",
      accent: "#9db7ff",
      accent2: "#ff8564",
      background:
        "radial-gradient(circle at 16% 20%, rgba(111,143,216,.14), transparent 26%), radial-gradient(circle at 84% 12%, rgba(255,110,69,.12), transparent 24%), linear-gradient(135deg,#02040a,#0b1020 42%,#05070f)",
      border: "rgba(157,183,255,.42)",
      frame: "rgba(255,255,255,.09)"
    },
    halo: {
      name: "Halo ribbon",
      titleColor: "#f5f3ee",
      accent: "#59d39b",
      accent2: "#6f8fd8",
      background:
        "radial-gradient(circle at top, rgba(89,211,155,.16), transparent 30%), radial-gradient(circle at 88% 18%, rgba(111,143,216,.14), transparent 30%), linear-gradient(135deg,#06131a,#0d2032 48%,#140b15)",
      border: "rgba(89,211,155,.42)",
      frame: "rgba(255,255,255,.1)"
    },
    custom: {
      name: "Custom upload",
      titleColor: "#ffffff",
      accent: "#ff6e45",
      accent2: "#6f8fd8",
      background: "linear-gradient(135deg,#050814,#0f162d 42%,#080a12)",
      border: "rgba(255,255,255,.28)",
      frame: "rgba(255,255,255,.12)"
    }
  };

  function certificateTemplateKey(course) {
    const mode = text(course?.certificateTemplateType, "preset").toLowerCase();
    if (mode === "custom") return "custom";
    const key = text(course?.certificatePresetKey, "nvidia").toLowerCase();
    return Object.prototype.hasOwnProperty.call(certificatePresets, key) ? key : "nvidia";
  }

  function certificateAssetUrl(path) {
    let value = text(path).trim();
    if (!value) return "";
    if (/^https?:\/\//i.test(value)) return value;
    value = value.replace(/^\/+/, "");
    if (value.toLowerCase().startsWith("careerstrand/")) {
      value = value.substring("careerstrand/".length);
    }
    return "../../" + value;
  }

  function certificateImageSrc(value) {
    const raw = text(value).trim();
    if (!raw) return "";
    if (raw.startsWith("data:image/")) return raw;
    return certificateAssetUrl(raw);
  }

  function teacherObjective(skill, title) {
    const focus = text(skill, "the core skill").toLowerCase();
    const subject = text(title, "this course");
    return "Explain the main idea behind " + subject + " and apply one " + focus + " technique in a small portfolio-ready task.";
  }

  function confidenceLabel(value) {
    const score = Number(value) || 0;
    if (score >= 78) return "Ready to stretch";
    if (score >= 48) return "Building steadily";
    return "Needs teacher support";
  }

  function renderLearningStudio(videos, progress, ready, activeVideo, signal) {
    const skill = text(activeCourse.Skill, "career skill");
    const title = text(activeCourse.Title, "this course");
    const done = completedSet(activeCourse.CourseID).size;
    const total = videos.length;
    const nextTitle = activeVideo ? lessonTitle(activeVideo, activeLessonIndex) : "Waiting for lesson content";
    const nextAction = ready ? "Download your certificate and add the proof to your profile." : activeVideo ? "Watch this lesson, write one useful note, then mark it complete." : "Check back when lesson videos have been added.";

    return `
      <section class="learning-studio" aria-label="Enrolled course studio">
        <div class="studio-head">
          <div>
            <span class="studio-kicker">Live learning room</span>
            <h3>${escapeHtml(nextTitle)}</h3>
          </div>
          <strong>${done}/${total} lessons complete</strong>
        </div>
        <div class="studio-grid">
          <article class="studio-card student-lens">
            <span>Student next move</span>
            <p>${escapeHtml(nextAction)}</p>
          </article>
          <article class="studio-card teacher-lens">
            <span>Teacher objective</span>
            <p>${escapeHtml(teacherObjective(skill, title))}</p>
          </article>
          <article class="studio-card confidence-lens">
            <span>Confidence signal</span>
            <strong id="confidence-copy">${escapeHtml(confidenceLabel(signal.confidence))}</strong>
            <label class="confidence-meter">
              <input id="confidence-range" type="range" min="0" max="100" value="${escapeHtml(signal.confidence)}" />
              <b style="width:${escapeHtml(signal.confidence)}%"></b>
            </label>
          </article>
        </div>
      </section>
    `;
  }

  function renderTeacherBoard(videos, progress, signal) {
    const skill = text(activeCourse.Skill, "Career skill");
    const activeName = videos[activeLessonIndex] ? lessonTitle(videos[activeLessonIndex], activeLessonIndex) : "Course setup";
    const support = Number(signal.confidence) < 48 ? "Slow down, rewatch the lesson, and write the confusing part as a question." : "Try the practice task without pausing the video notes.";

    return `
      <div class="side-block teacher-board">
        <span class="side-label">Teacher board</span>
        <h3>How to prove this lesson worked</h3>
        <div class="success-list">
          <span><strong>Objective</strong>${escapeHtml(skill)} applied to a realistic student project.</span>
          <span><strong>Success criteria</strong>Can explain the idea, show an example, and name the next improvement.</span>
          <span><strong>Support cue</strong>${escapeHtml(support)}</span>
        </div>
      </div>
      <div class="side-block practice-brief">
        <span class="side-label">Practice brief</span>
        <h3>Build something tiny</h3>
        <p>Create a small artifact from "${escapeHtml(activeName)}": a checklist, screenshot, paragraph, wireframe, code snippet, or decision note. Keep it simple enough to finish today.</p>
        <div class="practice-score">
          <span>${progress}%</span>
          <strong>${progress >= 100 ? "Portfolio proof ready" : "Proof in progress"}</strong>
        </div>
      </div>
    `;
  }

  function renderEnrollmentLessonPreview(videos) {
    if (!videos.length) {
      return `
        <div class="lesson-preview-empty">
          <strong>Lesson board is being prepared</strong>
          <span>The course is published, and the video sequence can be added from the back office.</span>
        </div>
      `;
    }

    return `
      <div class="lesson-preview-list">
        ${videos.slice(0, 4).map((video, index) => `
          <div class="lesson-preview-item">
            <span>${index + 1}</span>
            <strong>${escapeHtml(lessonTitle(video, index))}</strong>
          </div>
        `).join("")}
        ${videos.length > 4 ? `<div class="lesson-preview-more">+ ${videos.length - 4} more lessons after enrollment</div>` : ""}
      </div>
    `;
  }

  function renderEnrollmentShowcase(videos, lessonWord, duration, skill, level) {
    const lessonCount = videos.length || "Soon";
    const checkpointCount = Math.max(3, Math.min(7, videos.length || 3));

    return `
      <div class="enroll-showcase">
        <div class="learning-passport">
          <div class="passport-top">
            <span>Course passport</span>
            <strong>${escapeHtml(courseCode())}</strong>
          </div>
          <div class="passport-title">
            <span>Primary focus</span>
            <strong>${escapeHtml(skill)}</strong>
          </div>
          <div class="passport-grid">
            <span><strong>${escapeHtml(String(lessonCount))}</strong>${videos.length ? lessonWord : "lessons"}</span>
            <span><strong>${escapeHtml(duration)}</strong>timeframe</span>
            <span><strong>${escapeHtml(level)}</strong>level</span>
          </div>
        </div>

        <div class="launch-map" aria-label="Course launch route">
          <div class="launch-track">
            <span class="is-ready">Enroll</span>
            <span>Study</span>
            <span>Prove</span>
            <span>Certify</span>
          </div>
          <div class="checkpoint-grid">
            ${Array.from({ length: checkpointCount }).map((_, index) => `
              <span class="${index === 0 ? "active" : ""}">${String(index + 1).padStart(2, "0")}</span>
            `).join("")}
          </div>
        </div>

        <div class="enroll-panels">
          <article>
            <span>Outcome</span>
            <strong>Leave with sharper ${escapeHtml(skill.toLowerCase())} language for your profile.</strong>
          </article>
          <article>
            <span>Rhythm</span>
            <strong>Move lesson by lesson, then resume from your last checkpoint.</strong>
          </article>
          <article>
            <span>Proof</span>
            <strong>Completion unlocks a CareerStrand certificate preview.</strong>
          </article>
        </div>

        <div class="lesson-preview-panel" id="lesson-preview-panel">
          <div class="lesson-preview-head">
            <span>Inside the course</span>
            <strong>${videos.length ? videos.length + " " + lessonWord : "Lessons coming soon"}</strong>
          </div>
          ${renderEnrollmentLessonPreview(videos)}
        </div>
      </div>
    `;
  }

  function filterCourses() {
    if (!els.market) return;

    const query = text(els.search && els.search.value).trim().toLowerCase();
    const category = els.category ? els.category.value : "all";
    const level = els.level ? els.level.value : "all";
    let visible = 0;

    document.querySelectorAll(".course-tile").forEach((tile) => {
      const matchesQuery = !query || tile.dataset.search.includes(query);
      const matchesCategory = category === "all" || tile.dataset.category === category;
      const matchesLevel = level === "all" || tile.dataset.level === level;
      const show = matchesQuery && matchesCategory && matchesLevel;
      tile.hidden = !show;
      if (show) visible += 1;
    });

    if (els.visibleCount) els.visibleCount.textContent = String(visible);
  }

  function renderEnrolled() {
    const enrolledIds = Object.keys(state.enrolled).filter((id) => byId(id));
    const readyCount = enrolledIds.filter((id) => certificateReady(byId(id))).length;

    if (els.certificateCount) {
      els.certificateCount.textContent = readyCount + " ready";
    }

    if (!els.enrolledStrip) return;

    if (enrolledIds.length === 0) {
      els.enrolledStrip.innerHTML =
        '<div class="empty-learning"><strong>No courses enrolled yet</strong><span>Pick a course below and your learning queue will appear here.</span></div>';
      return;
    }

    els.enrolledStrip.innerHTML = enrolledIds.map((id) => {
      const course = byId(id);
      const progress = progressFor(course);
      const videos = Array.isArray(course.videos) ? course.videos : [];
      const last = Math.min(state.enrolled[id].lastLesson || 0, Math.max(0, videos.length - 1));
      const label = certificateReady(course)
        ? "Certificate ready"
        : progress >= 100 && quizForCourse(course)
          ? "Exam pending"
          : videos.length
            ? "Resume lesson " + (last + 1)
            : "Review course";

      return `
        <button class="enrolled-card" type="button" data-course-id="${escapeHtml(id)}">
          <span>${escapeHtml(text(course.Categorie, "Course"))}</span>
          <h3>${escapeHtml(text(course.Title, "Untitled course"))}</h3>
          <div class="mini-progress" aria-label="${progress}% complete"><span style="width:${progress}%"></span></div>
          <strong>${label}</strong>
        </button>
      `;
    }).join("");
  }

  function openCourse(courseId) {
    const course = byId(courseId);
    if (!course || !els.modal || !els.modalContent) return;

    activeCourse = course;
    const enrollment = state.enrolled[String(course.CourseID)];
    activeLessonIndex = enrollment ? enrollment.lastLesson || 0 : 0;
    renderModal();
    els.modal.classList.add("is-open");
    els.modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    if (!els.modal) return;
    els.modal.classList.remove("is-open");
    els.modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    activeCourse = null;
  }

  function renderModal() {
    if (!activeCourse || !els.modalContent) return;

    const videos = Array.isArray(activeCourse.videos) ? activeCourse.videos : [];
    if (activeLessonIndex >= videos.length) activeLessonIndex = Math.max(0, videos.length - 1);

    const enrolled = isEnrolled(activeCourse.CourseID);

    if (!enrolled) {
      els.modalContent.innerHTML = renderCourseDetails(videos);
      return;
    }

    els.modalContent.innerHTML = renderStudyRoom(videos);
    bindLessonCompletionHooks();
  }

  function bindLessonCompletionHooks() {
    const player = document.getElementById("course-player");
    if (!player) return;

    player.addEventListener("ended", () => {
      if (!activeCourse) return;
      markActiveLessonComplete();
    });
  }

  function renderCourseDetails(videos) {
    const lessonWord = videos.length === 1 ? "lesson" : "lessons";
    const skill = text(activeCourse.Skill, "Career skill");
    const level = text(activeCourse.Difficulty, "Open level");
    const duration = durationLabel(activeCourse.Duration);
    const quiz = quizForCourse(activeCourse);
    const examCopy = quiz
      ? `${quiz.questions.length} question${quiz.questions.length === 1 ? "" : "s"} · ${Number(quiz.passingScore || 70)}% passing score`
      : "No final exam configured yet";

    return `
      <div class="course-detail-view">
        <div class="detail-hero">
          <span class="modal-category">${escapeHtml(text(activeCourse.Categorie, "Course"))}</span>
          <h2 id="modal-title">${escapeHtml(text(activeCourse.Title, "Untitled course"))}</h2>
          <p class="modal-description">${escapeHtml(text(activeCourse.Description, "Review this course and enroll when it fits your next learning goal."))}</p>
          <div class="modal-meta">
            <span>${escapeHtml(level)}</span>
            <span>${escapeHtml(duration)}</span>
            <span>${videos.length} ${lessonWord}</span>
            <span>${escapeHtml(skill)}</span>
          </div>
          <div class="modal-meta" style="margin-top:-4px">
            <span>${escapeHtml(examCopy)}</span>
          </div>
          <div class="modal-actions">
            <button class="modal-action primary" type="button" id="enroll-action" ${videos.length ? "" : "disabled"}>${videos.length ? "Enroll now" : "Lessons coming soon"}</button>
            <button class="modal-action secondary" type="button" id="preview-plan-action">Preview lesson plan</button>
          </div>
          ${renderEnrollmentShowcase(videos, lessonWord, duration, skill, level)}
        </div>
        <aside class="detail-sidebar">
          <div class="detail-card enrollment-brief">
            <span class="detail-card-label">Launch brief</span>
            <h3>Your first session is already mapped</h3>
            <p>Enroll to turn this course into an active study room with lesson progress, private notes, recommendations, and a certificate tracker.</p>
          </div>
          <div class="detail-card">
            <h3>Before you enroll</h3>
            <div class="detail-facts">
              <span><strong>Level</strong>${escapeHtml(level)}</span>
              <span><strong>Time</strong>${escapeHtml(duration)}</span>
              <span><strong>Skill focus</strong>${escapeHtml(skill)}</span>
            </div>
          </div>
          <div class="detail-card">
            <h3>What unlocks after enrollment</h3>
            <ul class="student-checklist">
              <li>A focused lesson room with your current video and next step.</li>
              <li>Auto-saved private notes for ideas, links, and reminders.</li>
              <li>Related video recommendations tailored to this course topic.</li>
            </ul>
          </div>
          <div class="detail-card certificate-preview-card">
            <span class="cert-badge">${quiz ? "After lessons + exam" : "After completion"}</span>
            <h3>Certificate preview</h3>
            <p>${quiz ? "Finish the lessons, pass the final exam, and then unlock your CareerStrand certificate preview." : "Finish the course lessons after enrolling to unlock your CareerStrand certificate preview."}</p>
          </div>
        </aside>
      </div>
    `;
  }

  function renderStudyRoom(videos) {
    const progress = progressFor(activeCourse);
    const examAvailable = progress >= 100;
    const ready = certificateReady(activeCourse);
    const activeVideo = videos[activeLessonIndex];
    const noteKey = String(activeCourse.CourseID);
    const note = state.notes[noteKey] || "";
    const reflection = state.reflections[noteKey] || "";
    const signal = courseSignal(activeCourse.CourseID);
    const quiz = quizForCourse(activeCourse);
    const quizStateItem = quizState(activeCourse.CourseID);

    return `
      <div class="course-room">
        <div class="room-main">
          <span class="modal-category">${escapeHtml(text(activeCourse.Categorie, "Course"))}</span>
          <h2 id="modal-title">${escapeHtml(text(activeCourse.Title, "Untitled course"))}</h2>
          <p class="modal-description">${escapeHtml(text(activeCourse.Description, "You are enrolled. Use this room to study, take notes, and track completion."))}</p>
          <div class="modal-meta">
            <span>${escapeHtml(text(activeCourse.Difficulty, "Open level"))}</span>
            <span>${escapeHtml(durationLabel(activeCourse.Duration))}</span>
            <span>${videos.length} lessons</span>
            <span>${escapeHtml(text(activeCourse.Skill, "Career skill"))}</span>
          </div>
          <div class="modal-actions">
            <button class="modal-action primary" type="button" id="mark-complete-action" ${videos.length === 0 ? "disabled" : ""}>Mark lesson complete</button>
            <button class="modal-action secondary" type="button" id="recommend-action">Find related videos</button>
          </div>
          ${renderLearningStudio(videos, progress, ready, activeVideo, signal)}
          ${renderVideo(activeVideo, videos)}
          ${quiz ? renderFinalExam(quiz, quizStateItem, examAvailable) : ""}
          <div class="study-workbench">
            <div class="study-notes">
              <label>
                Private study notes
                <textarea id="study-note" placeholder="Write what you want to remember from this course.">${escapeHtml(note)}</textarea>
              </label>
              <span class="note-help">Saved automatically on this browser.</span>
            </div>
            <div class="study-notes reflection-notes">
              <label>
                Exit ticket
                <textarea id="reflection-note" placeholder="What can you now do, and where are you still stuck?">${escapeHtml(reflection)}</textarea>
              </label>
              <span class="note-help">Think like a teacher: name evidence, not just effort.</span>
            </div>
          </div>
        </div>
        <aside class="room-side">
          <div class="side-block">
            <h3>Course progress</h3>
            <div class="progress-large">
              <div class="progress-label-row"><span>${progress}% complete</span><span>${ready ? "Certified" : (quiz ? quizStatusLabel(activeCourse) : "In progress")}</span></div>
              <div class="progress-track"><span style="width:${progress}%"></span></div>
            </div>
          </div>
          ${renderTeacherBoard(videos, progress, signal)}
          <div class="side-block">
            <h3>Lesson plan</h3>
            ${renderLessons(videos, true)}
          </div>
          ${quiz ? `<div class="side-block">${renderQuizSidebar(quiz, quizStateItem, examAvailable)}</div>` : ""}
          <div class="side-block">
            <div class="certificate-box">
              <span class="cert-badge">${ready ? "Unlocked" : (quiz ? "Quiz pending" : "Locked")}</span>
              <h4>CareerStrand Certificate</h4>
              <p><strong>Template:</strong> ${escapeHtml(certificatePresets[certificateTemplateKey(activeCourse)]?.name || "Preset certificate")}</p>
              <p>${ready ? "You completed the learning path and the certificate preview is ready." : quiz ? "Finish all lessons, then pass the final exam to unlock the certificate." : "Finish all lessons to unlock the certificate."}</p>
              <button class="certificate-button ${ready ? "ready" : ""}" type="button" id="certificate-action" ${ready ? "" : "disabled"}>Download certificate preview</button>
            </div>
          </div>
          <div class="side-block">
            <h3>Recommended videos</h3>
            <div class="recommend-status" id="recommend-status"></div>
            <div class="recommend-list" id="recommend-list"></div>
          </div>
        </aside>
      </div>
    `;
  }

  function renderVideo(activeVideo, videos) {
    if (!activeVideo) {
      return `
        <div class="classroom-video">
          <div class="video-placeholder">
            <div>
              <strong>No lesson video yet</strong>
              <p>This course is listed in the catalog, but videos still need to be added from the back office.</p>
            </div>
          </div>
        </div>
      `;
    }

    return `
      <div class="classroom-video">
        <video id="course-player" controls preload="metadata" src="${escapeHtml(buildVideoUrl(activeVideo.video_path))}"></video>
        <div class="now-playing">
          <strong>${escapeHtml(lessonTitle(activeVideo, activeLessonIndex))}</strong>
          <span>Lesson ${activeLessonIndex + 1} / ${videos.length}</span>
        </div>
      </div>
    `;
  }

  function renderLessons(videos, enrolled) {
    if (!videos.length) {
      return '<div class="recommend-status">Lessons will appear here after videos are added.</div>';
    }

    const done = completedSet(activeCourse.CourseID);
    return `
      <div class="lesson-list">
        ${videos.map((video, index) => {
          const isDone = done.has(index);
          const active = index === activeLessonIndex;
          return `
            <button class="lesson-button ${active ? "is-active" : ""}" type="button" data-lesson-index="${index}" ${enrolled ? "" : "disabled"}>
              <span class="lesson-num">${index + 1}</span>
              <span class="lesson-copy">
                <strong>${escapeHtml(lessonTitle(video, index))}</strong>
                <span class="lesson-meta">${enrolled ? "Open lesson" : "Enroll to study"}</span>
              </span>
              <span class="lesson-state ${isDone ? "done" : ""}">${isDone ? "Done" : "Todo"}</span>
            </button>
          `;
        }).join("")}
      </div>
    `;
  }

  function renderQuizSidebar(quiz, quizStateItem, ready) {
    const questions = Array.isArray(quiz.questions) ? quiz.questions : [];
    if (!questions.length) {
      return '<div class="recommend-status">A final exam can be added from the back office.</div>';
    }

    const passingScore = Number(quiz.passingScore || 70);
    const lastScore = Number(quizStateItem.score || 0);
    const passed = Boolean(quizStateItem.passed);
    return `
      <h3>Final exam</h3>
      <div class="recommend-status">${passed ? `Passed with ${lastScore}%` : ready ? `Pass this exam to unlock your certificate. ${passingScore}% needed.` : "Complete the lessons first to unlock the exam."}</div>
      <div class="quiz-side-stack">
        ${questions.slice(0, 4).map((question, index) => `
          <div class="quiz-side-item">
            <strong>${escapeHtml(question.prompt || "Question " + (index + 1))}</strong>
            <span>${Array.isArray(question.choices) ? question.choices.length : 0} choices</span>
          </div>
        `).join("")}
      </div>
    `;
  }

  function renderFinalExam(quiz, quizStateItem, ready) {
    const questions = Array.isArray(quiz.questions) ? quiz.questions : [];
    if (!questions.length) return "";

    const passingScore = Number(quiz.passingScore || 70);
    const submitted = Number(quizStateItem.score || 0) > 0 || Boolean(quizStateItem.submittedAt);
    const passed = Boolean(quizStateItem.passed);
    const statusLabel = !ready
      ? "Complete the lessons to unlock the exam."
      : passed
        ? `Passed with ${Number(quizStateItem.score || 0)}%. Certificate unlocked.`
        : submitted
          ? `You scored ${Number(quizStateItem.score || 0)}%. You need ${passingScore}% to pass.`
          : `Answer the multiple-choice questions and score at least ${passingScore}%.`;

    return `
      <section class="quiz-section" id="final-exam-section">
        <div class="quiz-head">
          <div>
            <span class="studio-kicker">Final exam</span>
            <h3>${escapeHtml(text(quiz.title, "Course final exam"))}</h3>
          </div>
          <span class="quiz-score-pill">${escapeHtml(statusLabel)}</span>
        </div>
        ${!ready ? `
          <div class="quiz-lock-card">
            <strong>Finish the lessons first</strong>
            <p>This exam unlocks when you complete every lesson in the course.</p>
          </div>
        ` : `
          <div class="quiz-progress-note">Passing score: <strong>${passingScore}%</strong></div>
          <div class="quiz-question-list">
            ${questions.map((question, qIndex) => {
              const choices = Array.isArray(question.choices) ? question.choices : [];
              const selected = quizStateItem.answers && typeof quizStateItem.answers === "object" ? quizStateItem.answers[qIndex] : undefined;
              return `
                <article class="quiz-question" data-quiz-question data-question-index="${qIndex}">
                  <div class="quiz-question-head">
                    <strong>Question ${qIndex + 1}</strong>
                    <span>${choices.length} options</span>
                  </div>
                  <p>${escapeHtml(question.prompt || `Question ${qIndex + 1}`)}</p>
                  <div class="quiz-choice-list">
                    ${choices.map((choice, cIndex) => `
                      <label class="quiz-choice ${Number(selected) === cIndex ? "selected" : ""}">
                        <input type="radio" name="quiz-q-${qIndex}" value="${cIndex}" ${Number(selected) === cIndex ? "checked" : ""}>
                        <span>${escapeHtml(choice)}</span>
                      </label>
                    `).join("")}
                  </div>
                  ${question.explanation ? `<div class="quiz-explanation">Tip: ${escapeHtml(question.explanation)}</div>` : ""}
                </article>
              `;
            }).join("")}
          </div>
          <div class="quiz-actions">
            <button class="modal-action primary" type="button" id="submit-quiz-action">${passed ? "Retake exam" : "Submit exam"}</button>
            <div class="quiz-result" id="quiz-result">
              ${submitted ? (passed ? `Great work. You passed with ${Number(quizStateItem.score || 0)}%.` : `You scored ${Number(quizStateItem.score || 0)}%. Try again to reach ${passingScore}%.`) : "Your answer sheet will be scored instantly."}
            </div>
          </div>
        `}
      </section>
    `;
  }

  function enrollActive() {
    if (!activeCourse) return;
    getEnrollment(activeCourse.CourseID);
    saveState();
    renderEnrolled();
    renderModal();
    persistLessonProgress(activeCourse);
  }

  function markActiveLessonComplete() {
    if (!activeCourse) return;
    const videos = Array.isArray(activeCourse.videos) ? activeCourse.videos : [];
    if (!videos.length) return;

    const enrollment = getEnrollment(activeCourse.CourseID);
    const done = new Set(enrollment.completedLessons || []);
    done.add(activeLessonIndex);
    enrollment.completedLessons = Array.from(done).sort((a, b) => a - b);
    enrollment.lastLesson = Math.min(activeLessonIndex + 1, Math.max(0, videos.length - 1));
    activeLessonIndex = enrollment.lastLesson;
    saveState();
    renderEnrolled();
    renderModal();
    persistLessonProgress(activeCourse);
  }

  function saveQuizDraft(questionIndex, choiceIndex) {
    if (!activeCourse) return;
    const quiz = quizForCourse(activeCourse);
    if (!quiz) return;

    const quizStateItem = quizState(activeCourse.CourseID);
    if (!quizStateItem.answers || typeof quizStateItem.answers !== "object") {
      quizStateItem.answers = {};
    }
    quizStateItem.answers[String(questionIndex)] = Number(choiceIndex);
    saveState();
  }

  function syncQuizSelectionStyles() {
    document.querySelectorAll(".quiz-choice").forEach((label) => {
      const input = label.querySelector("input[type='radio']");
      label.classList.toggle("selected", Boolean(input && input.checked));
    });
  }

  function submitQuizAttempt() {
    if (!activeCourse) return;
    const quiz = quizForCourse(activeCourse);
    if (!quiz) return;
    if (!certificateReady(activeCourse) && progressFor(activeCourse) < 100) return;

    const questions = Array.isArray(quiz.questions) ? quiz.questions : [];
    const result = quizState(activeCourse.CourseID);
    const answers = {};
    let correct = 0;

    questions.forEach((question, qIndex) => {
      const selected = document.querySelector(`input[name="quiz-q-${qIndex}"]:checked`);
      const selectedIndex = selected ? Number(selected.value) : -1;
      answers[qIndex] = selectedIndex;
      if (selectedIndex >= 0 && Number(question.correctIndex) === selectedIndex) {
        correct += 1;
      }
    });

    const score = questions.length ? Math.round((correct / questions.length) * 100) : 0;
    result.score = score;
    result.passed = score >= Number(quiz.passingScore || 70);
    result.submittedAt = new Date().toISOString();
    result.lastAttempt = { score, correct, total: questions.length };
    result.answers = answers;
    saveState();
    renderEnrolled();
    renderModal();
    persistQuizProgress(activeCourse, result);
  }

  function saveNote(value) {
    if (!activeCourse) return;
    state.notes[String(activeCourse.CourseID)] = value;
    saveState();
  }

  function saveReflection(value) {
    if (!activeCourse) return;
    state.reflections[String(activeCourse.CourseID)] = value;
    saveState();
  }

  function saveConfidence(value) {
    if (!activeCourse) return;
    const signal = courseSignal(activeCourse.CourseID);
    signal.confidence = String(Math.max(0, Math.min(100, Number(value) || 0)));
    saveState();

    const copy = document.getElementById("confidence-copy");
    const fill = document.querySelector(".confidence-meter b");
    if (copy) copy.textContent = confidenceLabel(signal.confidence);
    if (fill) fill.style.width = signal.confidence + "%";
  }

  function downloadCertificate() {
    if (!activeCourse || !certificateReady(activeCourse)) return;

    const learner = document.querySelector(".cs-user-name");
    const learnerName = learner ? learner.textContent.trim() : "CareerStrand learner";
    const issued = new Date();
    const issuedText = issued.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
    const templateKey = certificateTemplateKey(activeCourse);
    const template = certificatePresets[templateKey] || certificatePresets.nvidia;
    const customBackground = certificateAssetUrl(activeCourse.certificateBackgroundUrl);
    const customLogo = certificateAssetUrl(activeCourse.certificateLogoUrl);
    const issuer = text(activeCourse.certificateIssuer, "CareerStrand");
    const title = text(activeCourse.certificateTitle, "Certificate of Completion");
    const subtitle = text(activeCourse.certificateSubtitle, "This certifies that");
    const signerName = text(activeCourse.certificateSignerName, "Course Manager");
    const signatureSrc = certificateImageSrc(activeCourse.certificateSignatureData);
    const backgroundStyle = customBackground
      ? `background-image: linear-gradient(135deg, rgba(5, 8, 20, .22), rgba(5, 8, 20, .42)), url('${customBackground}'); background-size: cover; background-position: center;`
      : `background-image: ${template.background};`;
    const logoMarkup = customLogo
      ? `<div class="cert-logo"><img src="${escapeHtml(customLogo)}" alt="${escapeHtml(issuer)} logo"></div>`
      : `<div class="cert-logo">${escapeHtml(issuer.substring(0, 2).toUpperCase())}</div>`;
    const html = `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>${escapeHtml(title)} · ${escapeHtml(activeCourse.Title || "Course")}</title>
        <style>
          @page { size: A4 landscape; margin: 0; }
          html,body{margin:0;width:100%;height:100%;font-family:Inter,Arial,sans-serif;background:#050814;color:#f5f3ee}
          .cert{
            width:1120px;
            height:790px;
            max-width:calc(100vw - 32px);
            max-height:calc(100vh - 32px);
            margin:16px auto;
            position:relative;
            overflow:hidden;
            border-radius:30px;
            border:14px solid ${template.border};
            box-shadow:0 30px 90px rgba(0,0,0,.42);
            ${backgroundStyle}
            background-color:#08101b;
          }
          .cert::before{
            content:"";
            position:absolute;
            inset:22px;
            border-radius:22px;
            border:1px solid ${template.frame};
            pointer-events:none;
          }
          .cert-inner{
            position:absolute;
            inset:0;
            padding:64px 72px 58px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            gap:28px;
          }
          .cert-top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px}
          .brand-copy{max-width:56%}
          .issuer{font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:${template.accent};font-weight:800}
          .title{margin:14px 0 10px;font-size:66px;line-height:.94;letter-spacing:-.05em;color:${template.titleColor};text-shadow:0 8px 26px rgba(0,0,0,.18)}
          .subtitle{margin:0;font-size:18px;letter-spacing:.2em;text-transform:uppercase;color:rgba(245,243,238,.72)}
          .learner{margin:18px 0 0;font-size:48px;line-height:.98;font-weight:900;color:#fff}
          .completed{margin:10px 0 0;font-size:22px;color:rgba(245,243,238,.78)}
          .course{margin:14px 0 0;font-size:28px;font-weight:800;color:${template.accent2}}
          .summary{max-width:700px;font-size:18px;line-height:1.75;color:rgba(245,243,238,.82)}
          .footer{display:flex;justify-content:space-between;align-items:flex-end;gap:20px}
          .issued,.course-id{font-size:14px;letter-spacing:.18em;text-transform:uppercase;color:rgba(245,243,238,.72)}
          .signoff{
            display:flex;
            flex-direction:column;
            align-items:flex-end;
            gap:8px;
            min-width:260px;
          }
          .sign-card{
            min-width:260px;
            padding:16px 18px 12px;
            border-radius:18px;
            border:1px solid rgba(255,255,255,.14);
            background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.03));
            box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
          }
          .signature-image{
            display:block;
            width:100%;
            height:64px;
            object-fit:contain;
            object-position:left center;
            margin-bottom:6px;
            filter:drop-shadow(0 8px 18px rgba(0,0,0,.22));
          }
          .signature-line{
            height:2px;
            width:100%;
            margin:4px 0 8px;
            background:linear-gradient(90deg, rgba(255,255,255,.0), rgba(255,255,255,.4), rgba(255,255,255,.0));
          }
          .sign-name{
            font-size:15px;
            font-weight:800;
            letter-spacing:.06em;
            text-transform:uppercase;
            color:#fff;
          }
          .sign-role{
            margin-top:2px;
            font-size:11px;
            letter-spacing:.18em;
            text-transform:uppercase;
            color:rgba(245,243,238,.7);
          }
          .sig-fallback{
            min-height:64px;
            display:flex;
            align-items:flex-end;
            padding-bottom:8px;
            color:rgba(245,243,238,.85);
            font-family:"Brush Script MT","Segoe Script",cursive;
            font-size:44px;
            line-height:1;
          }
          .cert-logo{
            width:124px;
            height:124px;
            border-radius:28px;
            display:grid;
            place-items:center;
            flex:0 0 auto;
            overflow:hidden;
            background:linear-gradient(135deg,rgba(255,255,255,.13),rgba(255,255,255,.04));
            border:1px solid rgba(255,255,255,.18);
            color:#fff;
            font-size:34px;
            font-weight:900;
            letter-spacing:.08em;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.12),0 18px 32px rgba(0,0,0,.18);
          }
          .cert-logo img{width:100%;height:100%;object-fit:contain;padding:14px;background:rgba(255,255,255,.02)}
          .seal{
            position:absolute;
            right:74px;
            bottom:62px;
            width:150px;
            height:150px;
            border-radius:50%;
            border:2px solid rgba(255,255,255,.2);
            display:grid;
            place-items:center;
            color:rgba(255,255,255,.86);
            font-weight:900;
            font-size:16px;
            letter-spacing:.18em;
            text-transform:uppercase;
            background:radial-gradient(circle, rgba(255,255,255,.06), transparent 62%);
          }
        </style>
      </head>
      <body>
        <main class="cert">
          <div class="cert-inner">
            <div class="cert-top">
              <div class="brand-copy">
                <div class="issuer">${escapeHtml(issuer)}</div>
                <h1 class="title">${escapeHtml(title)}</h1>
                <p class="subtitle">${escapeHtml(subtitle)}</p>
              </div>
              ${logoMarkup}
            </div>
            <div>
              <p class="completed">This certifies that</p>
              <div class="learner">${escapeHtml(learnerName)}</div>
              <p class="summary">has successfully completed the full learning journey for <strong>${escapeHtml(activeCourse.Title || "this course")}</strong> and demonstrated consistent progress in the CareerStrand education workspace.</p>
              <div class="course">${escapeHtml(activeCourse.Title || "Course")}</div>
            </div>
            <div class="footer">
              <div>
                <div class="issued">Issued ${escapeHtml(issuedText)}</div>
                <div class="course-id">Course #${escapeHtml(activeCourse.CourseID)}</div>
              </div>
              <div class="signoff">
                <div class="sign-card">
                  ${signatureSrc
                    ? `<img class="signature-image" src="${escapeHtml(signatureSrc)}" alt="Manager signature">`
                    : `<div class="sig-fallback">${escapeHtml(signerName)}</div>`}
                  <div class="signature-line"></div>
                  <div class="sign-name">${escapeHtml(signerName)}</div>
                  <div class="sign-role">${escapeHtml(issuer)} course manager</div>
                </div>
              </div>
            </div>
          </div>
          <div class="seal">Certified</div>
        </main>
      </body>
      </html>
    `;

    const blob = new Blob([html], { type: "text/html" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "careerstrand-certificate-" + activeCourse.CourseID + ".html";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  function requestRecommendations() {
    if (!activeCourse) return;

    const status = document.getElementById("recommend-status");
    const list = document.getElementById("recommend-list");
    const button = document.getElementById("recommend-action");
    if (!status || !list || !button) return;

    status.textContent = "Looking for related videos...";
    list.innerHTML = "";
    button.disabled = true;

    fetch(window.location.origin + "/Careerstrand/Controller/ControlRecommend.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        title: text(activeCourse.Title),
        description: text(activeCourse.Description),
        category: text(activeCourse.Categorie),
        skill: text(activeCourse.Skill),
        careerGoal: text(profileContext.careerGoal),
        preferences: text(profileContext.preferences),
        location: text(profileContext.location)
      })
    })
      .then((response) => {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      })
      .then((data) => {
        const videos = Array.isArray(data.videos) ? data.videos : [];
        status.textContent = videos.length
          ? (data.explanation || "Recommended because this course matches your learning topic and career direction.")
          : "No recommendations found yet.";
        list.innerHTML = videos.map((video) => {
          const id = text(video.videoId);
          const thumb = text(video.thumbnail, "https://img.youtube.com/vi/" + id + "/mqdefault.jpg");
          return `
            <button class="recommend-card" type="button" data-youtube-id="${escapeHtml(id)}">
              <img src="${escapeHtml(thumb)}" alt="">
              <span>
                <strong class="rec-title">${escapeHtml(text(video.title, "Recommended video"))}</strong>
                <span class="rec-channel">${escapeHtml(text(video.channelTitle, "YouTube"))}</span>
              </span>
            </button>
          `;
        }).join("");
      })
      .catch((error) => {
        status.textContent = "Recommendations could not be loaded: " + error.message;
      })
      .finally(() => {
        button.disabled = false;
      });
  }

  function playYoutube(videoId) {
    const main = document.querySelector(".room-main");
    if (!main || !videoId) return;

    const old = document.querySelector(".youtube-player");
    if (old) old.remove();

    const player = document.createElement("div");
    player.className = "youtube-player classroom-video";
    player.innerHTML = '<iframe src="https://www.youtube.com/embed/' + escapeHtml(videoId) + '?rel=0&modestbranding=1&autoplay=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    main.appendChild(player);
    player.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  [els.search, els.category, els.level].forEach((control) => {
    if (control) control.addEventListener("input", filterCourses);
  });

  document.addEventListener("click", (event) => {
    const openButton = event.target.closest("[data-course-id]");
    if (openButton && (openButton.classList.contains("course-open") || openButton.classList.contains("tile-enroll") || openButton.classList.contains("enrolled-card"))) {
      openCourse(openButton.dataset.courseId);
      return;
    }

    if (event.target.closest("[data-close-modal]")) {
      closeModal();
      return;
    }

    if (event.target.closest("#enroll-action")) {
      enrollActive();
      return;
    }

    if (event.target.closest("#preview-plan-action")) {
      const preview = document.getElementById("lesson-preview-panel");
      if (preview) preview.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }

    if (event.target.closest("#mark-complete-action")) {
      markActiveLessonComplete();
      return;
    }

    const lessonButton = event.target.closest("[data-lesson-index]");
    if (lessonButton && activeCourse) {
      const enrollment = getEnrollment(activeCourse.CourseID);
      activeLessonIndex = Number(lessonButton.dataset.lessonIndex) || 0;
      enrollment.lastLesson = activeLessonIndex;
      saveState();
      renderModal();
      return;
    }

    if (event.target.closest("#certificate-action")) {
      downloadCertificate();
      return;
    }

    if (event.target.closest("#recommend-action")) {
      requestRecommendations();
      return;
    }

    if (event.target.closest("#submit-quiz-action")) {
      submitQuizAttempt();
      return;
    }

    const rec = event.target.closest("[data-youtube-id]");
    if (rec) {
      playYoutube(rec.dataset.youtubeId);
    }
  });

  document.addEventListener("input", (event) => {
    if (event.target && event.target.id === "study-note") {
      saveNote(event.target.value);
    }
    if (event.target && event.target.id === "reflection-note") {
      saveReflection(event.target.value);
    }
    if (event.target && event.target.id === "confidence-range") {
      saveConfidence(event.target.value);
    }
    if (event.target && event.target.name && String(event.target.name).startsWith("quiz-q-")) {
      const qIndex = Number(String(event.target.name).replace("quiz-q-", ""));
      saveQuizDraft(qIndex, event.target.value);
      syncQuizSelectionStyles();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && els.modal && els.modal.classList.contains("is-open")) {
      closeModal();
    }
  });

  renderEnrolled();
  filterCourses();
  bootstrapServerProgress();
})();
