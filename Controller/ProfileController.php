<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Profile.php';
require_once __DIR__ . '/../Model/UserSkill.php';

class ProfileController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    // ── READ ─────────────────────────────────────────────

    public function getByUserId(int $userId): ?Profile
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Profile WHERE userId = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ? $this->rowToProfile($row) : null;
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
            $skills = [];
            while ($row = $stmt->fetch()) {
                $skills[] = $this->rowToUserSkill($row);
            }
            return $skills;
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── CREATE OR UPDATE PROFILE ─────────────────────────

    public function createOrUpdate(int $userId, array $data): array
    {
        $profile = new Profile(
            trim($data['bio']         ?? ''),
            trim($data['photoUrl']    ?? ''),
            trim($data['location']    ?? ''),
            trim($data['preferences'] ?? ''),
            0,
            'Starter',
            $userId
        );

        $score = $this->calculateScore($userId, $data);
        $level = $this->calculateLevel($score);
        $profile->setCompletionScore($score);
        $profile->setLevel($level);

        try {
            $existing = $this->getByUserId($userId);
            if ($existing) {
                $stmt = $this->pdo->prepare(
                    "UPDATE Profile
                     SET bio             = ?,
                         photoUrl        = ?,
                         location        = ?,
                         preferences     = ?,
                         completionScore = ?,
                         level           = ?
                     WHERE userId = ?"
                );
                $stmt->execute([
                    $profile->getBio(),
                    $profile->getPhotoUrl(),
                    $profile->getLocation(),
                    $profile->getPreferences(),
                    $profile->getCompletionScore(),
                    $profile->getLevel(),
                    $userId,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO Profile (userId, bio, photoUrl, location, preferences, completionScore, level)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    $profile->getBio(),
                    $profile->getPhotoUrl(),
                    $profile->getLocation(),
                    $profile->getPreferences(),
                    $profile->getCompletionScore(),
                    $profile->getLevel(),
                ]);
            }
            return ['success' => true, 'score' => $score, 'level' => $level];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save profile.']];
        }
    }

    // ── SCORE / LEVEL ─────────────────────────────────────

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

    // ── SKILLS ────────────────────────────────────────────

    public function addSkill(int $userId, array $data): array
    {
        $skill = new UserSkill(
            null,
            $userId,
            trim($data['skillName']      ?? ''),
            trim($data['source']         ?? ''),
            trim($data['certificateUrl'] ?? ''),
            trim($data['validatedAt']    ?? '')
        );

        $errors = $this->validateSkill($skill);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO UserSkill (userId, skillName, source, certificateUrl, validatedAt)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $skill->getUserId(),
                $skill->getSkillName(),
                $skill->getSource(),
                $skill->getCertificateUrl(),
                $skill->getValidatedAt() ?: null,
            ]);
            return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not add skill.']];
        }
    }

    public function updateSkill(int $userSkillId, int $userId, array $data): array
    {
        $skill = new UserSkill(
            $userSkillId,
            $userId,
            trim($data['skillName']      ?? ''),
            trim($data['source']         ?? ''),
            trim($data['certificateUrl'] ?? ''),
            trim($data['validatedAt']    ?? '')
        );

        $errors = $this->validateSkill($skill);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE UserSkill
                 SET skillName      = ?,
                     source         = ?,
                     certificateUrl = ?,
                     validatedAt    = ?
                 WHERE userSkillId = ? AND userId = ?"
            );
            $stmt->execute([
                $skill->getSkillName(),
                $skill->getSource(),
                $skill->getCertificateUrl(),
                $skill->getValidatedAt() ?: null,
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

    // ── VALIDATE SKILL ────────────────────────────────────

    private function validateSkill(UserSkill $skill): array
    {
        $errors = [];
        if (strlen($skill->getSkillName()) < 2) {
            $errors[] = 'Skill name is required and must be at least 2 characters.';
        }
        if (strlen($skill->getSource()) < 2) {
            $errors[] = 'Source is required and must be at least 2 characters.';
        }
        if (empty($skill->getCertificateUrl())) {
            $errors[] = 'Certificate URL is required.';
        } elseif (!filter_var($skill->getCertificateUrl(), FILTER_VALIDATE_URL)) {
            $errors[] = 'Certificate URL must be a valid URL (https://...).';
        }
        if (empty($skill->getValidatedAt())) {
            $errors[] = 'Validated date is required.';
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $skill->getValidatedAt());
            if (!$date || $date->format('Y-m-d') !== $skill->getValidatedAt()) {
                $errors[] = 'Validated date must be in YYYY-MM-DD format.';
            }
        }
        return $errors;
    }

    // ── ROW HELPERS ───────────────────────────────────────

    private function rowToProfile(array $row): Profile
    {
        $p = new Profile(
            $row['bio']         ?? '',
            $row['photoUrl']    ?? '',
            $row['location']    ?? '',
            $row['preferences'] ?? '',
            (int) ($row['completionScore'] ?? 0),
            $row['level']       ?? 'Starter',
            (int) $row['userId']
        );
        $p->setProfileId((int) $row['profileId']);
        return $p;
    }

    private function rowToUserSkill(array $row): UserSkill
    {
        $s = new UserSkill(
            (int) $row['userSkillId'],
            (int) $row['userId'],
            $row['skillName']      ?? '',
            $row['source']         ?? '',
            $row['certificateUrl'] ?? '',
            $row['validatedAt']    ?? ''
        );
        return $s;
    }
}
?>
