<?php
require_once __DIR__ . '/../../Controller/ControlCourses.php';
require_once __DIR__ . '/../../Controller/ControlCourseQuizzes.php';

$controlC = new ControlCourses();
$controlQ = new ControlCourseQuizzes();

function qh($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['save_quiz'])) {
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $quizTitle = trim((string) ($_POST['quiz_title'] ?? 'Final quiz'));
    $passingScore = (int) ($_POST['passing_score'] ?? 70);
    $payload = json_decode((string) ($_POST['quiz_payload'] ?? '{}'), true);
    $questions = is_array($payload['questions'] ?? null) ? $payload['questions'] : [];

    if ($courseId > 0 && $controlQ->saveQuizForCourse($courseId, $quizTitle, $passingScore, $questions)) {
        header('Location: admin-course-quizzes.php?course_id=' . $courseId . '&saved=1');
        exit;
    }

    $errorMessage = 'Unable to save the quiz. Make sure at least one valid question exists.';
}

if (isset($_GET['delete_quiz'])) {
    $courseId = (int) ($_GET['course_id'] ?? 0);
    $controlQ->deleteQuizForCourse((int) $_GET['delete_quiz']);
    header('Location: admin-course-quizzes.php?course_id=' . $courseId . '&deleted=1');
    exit;
}

$selectedCourseId = (int) ($_GET['course_id'] ?? 0);
$selectedCourse = $selectedCourseId > 0 ? $controlC->getCourseById($selectedCourseId) : null;
$selectedQuiz = $selectedCourseId > 0 ? $controlQ->getQuizByCourseId($selectedCourseId) : null;

$coursesRaw = $controlC->listeCourse();
$allCourses = $coursesRaw->fetchAll(PDO::FETCH_ASSOC);
$saved = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

$questionData = $selectedQuiz['questions'] ?? [];
if (empty($questionData)) {
    $questionData = [
        [
            'prompt' => '',
            'choices' => ['', '', '', ''],
            'correctIndex' => 0,
            'explanation' => '',
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CareerStrand Admin - Course Quizzes</title>
  <link rel="stylesheet" href="assets/css/admin.css" />
  <style>
    .quiz-shell { display:grid; grid-template-columns: 320px minmax(0,1fr); gap:20px; }
    .quiz-panel,.quiz-editor { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:18px; padding:20px; }
    .quiz-panel h3,.quiz-editor h3 { margin:0 0 12px; color:#f5f3ee; }
    .quiz-course-list { display:grid; gap:10px; }
    .quiz-course-link { display:block; padding:12px 14px; border-radius:14px; border:1px solid rgba(126,159,228,0.14); background:rgba(255,255,255,0.03); color:#f5f3ee; text-decoration:none; transition:0.2s ease; }
    .quiz-course-link:hover,.quiz-course-link.active { border-color:rgba(111,143,216,0.3); background:rgba(111,143,216,0.08); }
    .quiz-course-link strong { display:block; margin-bottom:4px; }
    .quiz-course-link span { font-size:12px; color:rgba(255,255,255,0.55); }
    .quiz-meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
    .quiz-pill { padding:4px 10px; border-radius:999px; font-size:11px; border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.65); }
    .quiz-pill.good { color:#4ade80; border-color:rgba(34,211,130,0.25); }
    .quiz-pill.warn { color:#f5bf65; border-color:rgba(245,191,101,0.25); }
    .quiz-question { margin-top:14px; padding:16px; border-radius:16px; border:1px solid rgba(126,159,228,0.12); background:rgba(255,255,255,0.025); }
    .quiz-question-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
    .quiz-question-head strong { color:#f5f3ee; }
    .quiz-question-grid { display:grid; gap:12px; }
    .quiz-question-grid .field { margin-bottom:0; }
    .quiz-choice-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .quiz-choice-grid input,.quiz-question textarea,.quiz-question input[type="text"],.quiz-question select { width:100%; }
    .quiz-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
    .quiz-builder-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
    .quiz-empty { padding:24px; border:1px dashed rgba(126,159,228,0.18); border-radius:16px; color:rgba(255,255,255,0.55); text-align:center; }
    .notice { padding:14px 16px; border-radius:14px; margin-bottom:16px; font-size:14px; }
    .notice.success { background:rgba(34,211,130,0.1); border:1px solid rgba(34,211,130,0.25); color:#4ade80; }
    .notice.error { background:rgba(255,110,69,0.12); border:1px solid rgba(255,110,69,0.25); color:#ff8564; }
  </style>
</head>
<body>
<div class="admin-shell">
  <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

  <main class="admin-main">
    <header class="page-header">
      <div>
        <h2>Course Quizzes</h2>
        <p>Create multiple-choice final exams for your courses and control the passing score before the certificate unlocks.</p>
      </div>
      <div class="header-actions">
        <a href="admin-courses.php" class="btn btn-soft">Back to Courses</a>
      </div>
    </header>

    <?php if ($saved): ?>
      <div class="notice success">Quiz saved successfully.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
      <div class="notice success">Quiz deleted successfully.</div>
    <?php endif; ?>
    <?php if (!empty($errorMessage)): ?>
      <div class="notice error"><?= qh($errorMessage); ?></div>
    <?php endif; ?>

    <section class="quiz-shell">
      <aside class="quiz-panel">
        <h3>Courses</h3>
        <div class="quiz-course-list">
          <?php foreach ($allCourses as $course): ?>
            <a class="quiz-course-link <?= $selectedCourseId === (int) $course['CourseID'] ? 'active' : ''; ?>" href="admin-course-quizzes.php?course_id=<?= (int) $course['CourseID']; ?>">
              <strong><?= qh($course['Title']); ?></strong>
              <span><?= qh($course['Categorie']); ?> - <?= qh($course['Difficulty']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>

      <section class="quiz-editor">
        <?php if (!$selectedCourse): ?>
          <div class="quiz-empty">Select a course to create or edit its final exam.</div>
        <?php else: ?>
          <div class="quiz-meta">
            <span class="quiz-pill good">Course selected</span>
            <span class="quiz-pill"><?= qh($selectedCourse['Title']); ?></span>
            <span class="quiz-pill <?= !empty($selectedQuiz) ? 'good' : 'warn'; ?>"><?= !empty($selectedQuiz) ? 'Quiz exists' : 'No quiz yet'; ?></span>
          </div>

          <div class="field">
            <label>Quiz title</label>
            <input type="text" form="quiz-form" name="quiz_title" value="<?= qh($selectedQuiz['title'] ?? 'Final quiz'); ?>" placeholder="Final quiz">
          </div>

          <div class="field" style="max-width:220px">
            <label>Passing score (%)</label>
            <input type="number" form="quiz-form" name="passing_score" min="0" max="100" value="<?= qh((string) ($selectedQuiz['passingScore'] ?? 70)); ?>">
          </div>

          <form id="quiz-form" method="post" action="admin-course-quizzes.php">
            <input type="hidden" name="course_id" value="<?= (int) $selectedCourseId; ?>">
            <input type="hidden" name="save_quiz" value="1">
            <input type="hidden" name="quiz_payload" id="quiz_payload">

            <div id="quiz-builder"></div>

            <div class="quiz-builder-actions">
              <button type="button" class="btn btn-soft" id="add-question-btn">+ Add question</button>
              <button type="button" class="btn btn-soft" id="reset-quiz-btn">Reset form</button>
            </div>

            <div class="quiz-actions">
              <button type="submit" class="btn btn-main">Save quiz</button>
              <?php if (!empty($selectedQuiz)): ?>
                <a class="btn btn-danger" href="admin-course-quizzes.php?delete_quiz=<?= (int) ($selectedQuiz['courseId'] ?? $selectedCourseId); ?>&course_id=<?= (int) $selectedCourseId; ?>" onclick="return confirm('Delete this quiz?')">Delete quiz</a>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </section>
    </section>
  </main>
</div>

<template id="quiz-question-template">
  <article class="quiz-question">
    <div class="quiz-question-head">
      <strong>Question</strong>
      <button type="button" class="btn btn-soft btn-sm remove-question-btn">Remove</button>
    </div>
    <div class="quiz-question-grid">
      <div class="field">
        <label>Prompt</label>
        <textarea class="quiz-prompt" rows="3" placeholder="Write the question prompt"></textarea>
      </div>
      <div class="quiz-choice-grid">
        <div class="field"><label>Choice A</label><input type="text" class="quiz-choice" placeholder="Choice A"></div>
        <div class="field"><label>Choice B</label><input type="text" class="quiz-choice" placeholder="Choice B"></div>
        <div class="field"><label>Choice C</label><input type="text" class="quiz-choice" placeholder="Choice C"></div>
        <div class="field"><label>Choice D</label><input type="text" class="quiz-choice" placeholder="Choice D"></div>
      </div>
      <div class="field" style="max-width:220px">
        <label>Correct answer</label>
        <select class="quiz-correct">
          <option value="0">Choice A</option>
          <option value="1">Choice B</option>
          <option value="2">Choice C</option>
          <option value="3">Choice D</option>
        </select>
      </div>
      <div class="field">
        <label>Explanation hint</label>
        <input type="text" class="quiz-explanation" placeholder="Optional explanation for learners">
      </div>
    </div>
  </article>
</template>

<script>
(function () {
  const builder = document.getElementById('quiz-builder');
  const template = document.getElementById('quiz-question-template');
  const payload = document.getElementById('quiz_payload');
  const addBtn = document.getElementById('add-question-btn');
  const resetBtn = document.getElementById('reset-quiz-btn');
  const form = document.getElementById('quiz-form');
  const initialQuestions = <?php echo json_encode($questionData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function addQuestion(data) {
    if (!builder || !template) return;
    const node = template.content.firstElementChild.cloneNode(true);
    const prompt = node.querySelector('.quiz-prompt');
    const choices = node.querySelectorAll('.quiz-choice');
    const correct = node.querySelector('.quiz-correct');
    const explanation = node.querySelector('.quiz-explanation');
    const removeBtn = node.querySelector('.remove-question-btn');

    prompt.value = data?.prompt || '';
    choices.forEach((input, index) => {
      input.value = Array.isArray(data?.choices) ? (data.choices[index] || '') : '';
    });
    correct.value = String(Number(data?.correctIndex ?? 0));
    explanation.value = data?.explanation || '';

    removeBtn.addEventListener('click', () => {
      node.remove();
      renumberQuestions();
    });

    builder.appendChild(node);
    renumberQuestions();
  }

  function renumberQuestions() {
    if (!builder) return;
    builder.querySelectorAll('.quiz-question').forEach((card, index) => {
      const label = card.querySelector('.quiz-question-head strong');
      if (label) label.textContent = `Question ${index + 1}`;
      card.dataset.index = String(index);
    });
  }

  function serializeQuestions() {
    if (!builder) return [];
    return Array.from(builder.querySelectorAll('.quiz-question')).map((card) => {
      return {
        prompt: card.querySelector('.quiz-prompt')?.value?.trim() || '',
        choices: Array.from(card.querySelectorAll('.quiz-choice')).map((input) => input.value.trim()),
        correctIndex: Number(card.querySelector('.quiz-correct')?.value || 0),
        explanation: card.querySelector('.quiz-explanation')?.value?.trim() || ''
      };
    }).filter((item) => item.prompt && item.choices.filter(Boolean).length >= 2);
  }

  if (Array.isArray(initialQuestions) && initialQuestions.length) {
    initialQuestions.forEach((question) => addQuestion(question));
  } else {
    addQuestion();
  }

  if (addBtn) {
    addBtn.addEventListener('click', () => addQuestion());
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (!builder) return;
      builder.innerHTML = '';
      addQuestion();
    });
  }

  if (form) {
    form.addEventListener('submit', () => {
      if (payload) {
        payload.value = JSON.stringify({ questions: serializeQuestions() });
      }
    });
  }
})();
</script>
</body>
</html>
