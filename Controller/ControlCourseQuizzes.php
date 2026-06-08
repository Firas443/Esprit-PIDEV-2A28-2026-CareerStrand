<?php
require_once __DIR__ . '/../config.php';

class ControlCourseQuizzes
{
    private function ensureQuizTable(): void
    {
        $db = config::getConnexion();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS course_quizzes (
                quiz_id INT(11) NOT NULL AUTO_INCREMENT,
                course_id INT(11) NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT 'Final quiz',
                passing_score INT(11) NOT NULL DEFAULT 70,
                quiz_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (quiz_id),
                UNIQUE KEY uniq_course_quiz (course_id),
                KEY idx_course_quizzes_course (course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    private function normalizeQuestions(array $questions): array
    {
        $normalized = [];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }

            $prompt = trim((string) ($question['prompt'] ?? ''));
            $choices = array_values(array_filter(array_map(
                static fn($choice): string => trim((string) $choice),
                (array) ($question['choices'] ?? [])
            ), static fn(string $value): bool => $value !== ''));
            $correctIndex = isset($question['correctIndex']) && is_numeric($question['correctIndex'])
                ? (int) $question['correctIndex']
                : -1;
            $explanation = trim((string) ($question['explanation'] ?? ''));

            if ($prompt === '' || count($choices) < 2) {
                continue;
            }

            $correctIndex = max(0, min(count($choices) - 1, $correctIndex));

            $normalized[] = [
                'prompt' => $prompt,
                'choices' => array_values($choices),
                'correctIndex' => $correctIndex,
                'explanation' => $explanation,
            ];
        }

        return $normalized;
    }

    public function getQuizByCourseId(int $courseId): ?array
    {
        $this->ensureQuizTable();
        $db = config::getConnexion();
        $stmt = $db->prepare('SELECT * FROM course_quizzes WHERE course_id = ? LIMIT 1');
        $stmt->execute([$courseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $decoded = json_decode((string) ($row['quiz_json'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = ['questions' => []];
        }

        return [
            'quizId' => (int) ($row['quiz_id'] ?? 0),
            'courseId' => (int) ($row['course_id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Final quiz'),
            'passingScore' => max(0, min(100, (int) ($row['passing_score'] ?? 70))),
            'questions' => $decoded['questions'] ?? [],
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    public function saveQuizForCourse(int $courseId, string $title, int $passingScore, array $questions): bool
    {
        $this->ensureQuizTable();
        $db = config::getConnexion();
        $normalizedQuestions = $this->normalizeQuestions($questions);
        if (empty($normalizedQuestions)) {
            return false;
        }

        $payload = json_encode([
            'questions' => $normalizedQuestions,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $db->prepare(
            'INSERT INTO course_quizzes (course_id, title, passing_score, quiz_json)
             VALUES (:course_id, :title, :passing_score, :quiz_json)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                passing_score = VALUES(passing_score),
                quiz_json = VALUES(quiz_json)'
        );

        return $stmt->execute([
            'course_id' => $courseId,
            'title' => $title !== '' ? $title : 'Final quiz',
            'passing_score' => max(0, min(100, $passingScore)),
            'quiz_json' => $payload,
        ]);
    }

    public function deleteQuizForCourse(int $courseId): bool
    {
        $this->ensureQuizTable();
        $db = config::getConnexion();
        $stmt = $db->prepare('DELETE FROM course_quizzes WHERE course_id = ?');
        return $stmt->execute([$courseId]);
    }
}
?>
