<?php

require_once __DIR__ . '/../config.php';

class ProfileController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────

    public function getByUserId(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Profile WHERE userId = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getSkillsByUserId(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM UserSkill WHERE userId = ? ORDER BY validatedAt DESC"
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── CREATE OR UPDATE PROFILE ─────────────────────────────────────────────

    public function createOrUpdate(int $userId, array $data): array
    {
        $bio         = $data['bio']         ?? '';
        $photoUrl    = $data['photoUrl']    ?? '';
        $location    = $data['location']    ?? '';
        $preferences = $data['preferences'] ?? '';

        $score = $this->calculateScore($userId, $data);
        $level = $this->calculateLevel($score);

        try {
            $existing = $this->getByUserId($userId);
            if ($existing) {
                $stmt = $this->pdo->prepare(
                    "UPDATE Profile
                     SET bio = ?, photoUrl = ?, location = ?, preferences = ?,
                         completionScore = ?, level = ?
                     WHERE userId = ?"
                );
                $stmt->execute([$bio, $photoUrl, $location, $preferences, $score, $level, $userId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO Profile (userId, bio, photoUrl, location, preferences, completionScore, level)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $bio, $photoUrl, $location, $preferences, $score, $level]);
            }
            return ['success' => true, 'score' => $score, 'level' => $level];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save profile.']];
        }
    }

    // ── SCORE / LEVEL ─────────────────────────────────────────────────────────

    public function calculateScore(int $userId, array $profileData = []): int
    {
        $score = 0;

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE userId = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                if (!empty($user['fullName'])) $score += 10;
                if (!empty($user['email']))    $score += 10;
            }

            if (!empty($profileData['bio']))         $score += 15;
            if (!empty($profileData['photoUrl']))    $score += 15;
            if (!empty($profileData['location']))    $score += 10;
            if (!empty($profileData['preferences'])) $score += 10;

            $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM UserSkill WHERE userId = ?");
            $stmt2->execute([$userId]);
            $skillCount = (int) $stmt2->fetchColumn();
            if ($skillCount >= 1) $score += 10;
            if ($skillCount >= 3) $score += 10;
            if ($skillCount >= 5) $score += 10;
        } catch (PDOException $e) {
            // return partial score
        }

        return min($score, 100);
    }

    public function calculateLevel(int $score): string
    {
        if ($score >= 80) return 'Expert';
        if ($score >= 60) return 'Advanced';
        if ($score >= 40) return 'Intermediate';
        if ($score >= 20) return 'Beginner';
        return 'Starter';
    }

    // ── SKILLS ────────────────────────────────────────────────────────────────

    public function addSkill(int $userId, array $data): array
    {
        // Trim all inputs
        $data['skillName']      = trim($data['skillName'] ?? '');
        $data['source']         = trim($data['source'] ?? '');
        $data['certificateUrl'] = trim($data['certificateUrl'] ?? '');
        $data['validatedAt']    = trim($data['validatedAt'] ?? '');
        
        // Validate all fields
        $errors = $this->validateSkill($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO UserSkill (userId, skillName, source, certificateUrl, validatedAt)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $data['skillName'],
                $data['source']         ?: '',
                $data['certificateUrl'] ?: '',
                !empty($data['validatedAt']) ? $data['validatedAt'] : null,
            ]);
            return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not add skill.']];
        }
    }

    public function updateSkill(int $userSkillId, int $userId, array $data): array
    {
        // Trim all inputs
        $data['skillName']      = trim($data['skillName'] ?? '');
        $data['source']         = trim($data['source'] ?? '');
        $data['certificateUrl'] = trim($data['certificateUrl'] ?? '');
        $data['validatedAt']    = trim($data['validatedAt'] ?? '');
        
        // Validate all fields
        $errors = $this->validateSkill($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE UserSkill 
                 SET skillName = ?, source = ?, certificateUrl = ?, validatedAt = ?
                 WHERE userSkillId = ? AND userId = ?"
            );
            $stmt->execute([
                $data['skillName'],
                $data['source']         ?: '',
                $data['certificateUrl'] ?: '',
                !empty($data['validatedAt']) ? $data['validatedAt'] : null,
                $userSkillId,
                $userId,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not update skill.']];
        }
    }

    public function deleteSkill(int $userSkillId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM UserSkill WHERE userSkillId = ? AND userId = ?"
            );
            $stmt->execute([$userSkillId, $userId]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not delete skill.']];
        }
    }

    private function validateSkill(array $data): array
    {
        $errors = [];
        
        // Validate skillName (required)
        if (empty($data['skillName']) || strlen(trim($data['skillName'])) < 2) {
            $errors[] = 'Skill name is required and must be at least 2 characters.';
        }
        
        // Validate source (required)
        if (empty($data['source']) || strlen(trim($data['source'])) < 2) {
            $errors[] = 'Source is required and must be at least 2 characters.';
        }
        
        // Validate certificateUrl (required and must be a valid URL)
        if (empty($data['certificateUrl'])) {
            $errors[] = 'Certificate URL is required.';
        } else {
            $certificateUrl = trim($data['certificateUrl']);
            if (!filter_var($certificateUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Certificate URL must be a valid URL (https://...).';
            }
        }
        
        // Validate validatedAt (required and must be a valid date in YYYY-MM-DD format)
        if (empty($data['validatedAt'])) {
            $errors[] = 'Validated date is required.';
        } else {
            $validatedAt = trim($data['validatedAt']);
            $date = DateTime::createFromFormat('Y-m-d', $validatedAt);
            if (!$date || $date->format('Y-m-d') !== $validatedAt) {
                $errors[] = 'Validated date must be in YYYY-MM-DD format (e.g., 2026-04-18).';
            }
        }
        
        return $errors;
    }
}
