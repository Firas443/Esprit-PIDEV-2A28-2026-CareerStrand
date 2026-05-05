<?php

require_once __DIR__ . '/../config.php';

class UserQuestionnaire
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    public function saveAnswer(int $userId, string $question, string $answer): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO UserQuestionnaire (userId, question, answer, createdAt)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $question, $answer]);
    }

    public function getAnswersByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM UserQuestionnaire
             WHERE userId = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function deleteAnswersByUser(int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM UserQuestionnaire WHERE userId = ?");
        $stmt->execute([$userId]);
    }
}
?>
