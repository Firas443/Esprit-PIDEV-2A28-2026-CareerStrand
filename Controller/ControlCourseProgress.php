<?php
require_once __DIR__ . '/../config.php';

class ControlCourseProgress
{
    private function ensureTable(): void
    {
        $db = config::getConnexion();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS course_progress (
                progress_id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                course_id INT(11) NOT NULL,
                enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_lesson_index INT(11) NOT NULL DEFAULT 0,
                completed_lessons LONGTEXT NOT NULL,
                quiz_score INT(11) DEFAULT NULL,
                quiz_passed TINYINT(1) NOT NULL DEFAULT 0,
                quiz_submitted_at DATETIME DEFAULT NULL,
                quiz_answers LONGTEXT DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (progress_id),
                UNIQUE KEY uniq_user_course_progress (user_id, course_id),
                KEY idx_course_progress_user (user_id),
                KEY idx_course_progress_course (course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    private function decodeJsonArray(?string $value): array
    {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJson($value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded !== false ? $encoded : '[]';
    }

    private function normalizeLessons(array $lessons): array
    {
        $lessons = array_map('intval', $lessons);
        $lessons = array_values(array_unique(array_filter($lessons, static fn(int $value): bool => $value >= 0)));
        sort($lessons);
        return $lessons;
    }

    public function getProgressRowsForUser(int $userId): array
    {
        $this->ensureTable();
        $db = config::getConnexion();
        $stmt = $db->prepare('SELECT * FROM course_progress WHERE user_id = ? ORDER BY updated_at DESC, course_id DESC');
        $stmt->execute([$userId]);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $courseId = (int) ($row['course_id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }

            $rows[(string) $courseId] = [
                'courseId' => $courseId,
                'userId' => (int) ($row['user_id'] ?? 0),
                'enrolledAt' => $row['enrolled_at'] ?? null,
                'lastLessonIndex' => (int) ($row['last_lesson_index'] ?? 0),
                'completedLessons' => $this->decodeJsonArray($row['completed_lessons'] ?? '[]'),
                'quizScore' => $row['quiz_score'] !== null ? (int) $row['quiz_score'] : null,
                'quizPassed' => (bool) ($row['quiz_passed'] ?? 0),
                'quizSubmittedAt' => $row['quiz_submitted_at'] ?? null,
                'quizAnswers' => $this->decodeJsonArray($row['quiz_answers'] ?? '[]'),
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }

        return $rows;
    }

    public function getProgressForUserAndCourse(int $userId, int $courseId): ?array
    {
        $all = $this->getProgressRowsForUser($userId);
        return $all[(string) $courseId] ?? null;
    }

    public function saveLessonProgress(int $userId, int $courseId, array $completedLessons, int $lastLessonIndex): bool
    {
        $this->ensureTable();
        $db = config::getConnexion();
        $payload = $this->encodeJson($this->normalizeLessons($completedLessons));

        $stmt = $db->prepare(
            'INSERT INTO course_progress (user_id, course_id, completed_lessons, last_lesson_index)
             VALUES (:user_id, :course_id, :completed_lessons, :last_lesson_index)
             ON DUPLICATE KEY UPDATE
                completed_lessons = VALUES(completed_lessons),
                last_lesson_index = VALUES(last_lesson_index)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'completed_lessons' => $payload,
            'last_lesson_index' => max(0, $lastLessonIndex),
        ]);
    }

    public function saveQuizResult(int $userId, int $courseId, int $score, bool $passed, array $answers, ?string $submittedAt = null): bool
    {
        $this->ensureTable();
        $db = config::getConnexion();

        $stmt = $db->prepare(
            'INSERT INTO course_progress (user_id, course_id, completed_lessons, last_lesson_index, quiz_score, quiz_passed, quiz_submitted_at, quiz_answers)
             VALUES (:user_id, :course_id, :completed_lessons, :last_lesson_index, :quiz_score, :quiz_passed, :quiz_submitted_at, :quiz_answers)
             ON DUPLICATE KEY UPDATE
                quiz_score = VALUES(quiz_score),
                quiz_passed = VALUES(quiz_passed),
                quiz_submitted_at = VALUES(quiz_submitted_at),
                quiz_answers = VALUES(quiz_answers)'
        );

        $existing = $this->getProgressForUserAndCourse($userId, $courseId) ?? [];
        $completedLessons = $this->encodeJson($existing['completedLessons'] ?? []);
        $lastLessonIndex = (int) ($existing['lastLessonIndex'] ?? 0);

        return $stmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'completed_lessons' => $completedLessons,
            'last_lesson_index' => $lastLessonIndex,
            'quiz_score' => max(0, min(100, $score)),
            'quiz_passed' => $passed ? 1 : 0,
            'quiz_submitted_at' => $submittedAt ?: date('Y-m-d H:i:s'),
            'quiz_answers' => $this->encodeJson($answers),
        ]);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../utils/FrontOfficeAuth.php';

    header('Content-Type: application/json; charset=utf-8');

    try {
        $user = currentFrontUser();
        if ($user === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            exit;
        }

        $controller = new ControlCourseProgress();
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'GET') {
            $courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
            if ($courseId > 0) {
                $progress = $controller->getProgressForUserAndCourse((int) $user['userId'], $courseId);
                echo json_encode(['success' => true, 'data' => $progress]);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $controller->getProgressRowsForUser((int) $user['userId'])]);
            exit;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
            exit;
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? 'lesson')));
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing course ID.']);
            exit;
        }

        if ($action === 'quiz') {
            $score = (int) ($_POST['score'] ?? 0);
            $passed = filter_var($_POST['passed'] ?? false, FILTER_VALIDATE_BOOL);
            $answers = json_decode((string) ($_POST['answers'] ?? '{}'), true);
            if (!is_array($answers)) {
                $answers = [];
            }

            $ok = $controller->saveQuizResult(
                (int) $user['userId'],
                $courseId,
                $score,
                $passed,
                $answers,
                trim((string) ($_POST['submitted_at'] ?? '')) ?: null
            );

            echo json_encode(['success' => $ok, 'data' => $controller->getProgressForUserAndCourse((int) $user['userId'], $courseId)]);
            exit;
        }

        $completedLessons = json_decode((string) ($_POST['completed_lessons'] ?? '[]'), true);
        if (!is_array($completedLessons)) {
            $completedLessons = [];
        }
        $lastLessonIndex = (int) ($_POST['last_lesson_index'] ?? 0);

        $ok = $controller->saveLessonProgress(
            (int) $user['userId'],
            $courseId,
            $completedLessons,
            $lastLessonIndex
        );

        echo json_encode(['success' => $ok, 'data' => $controller->getProgressForUserAndCourse((int) $user['userId'], $courseId)]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
