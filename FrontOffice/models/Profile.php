<?php
class Profile {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // =====================
    // PROFILE
    // =====================

    public function getByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM Profile WHERE userId = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createOrUpdate($userId, $data) {
        $existing = $this->getByUserId($userId);

        $bio        = $data['bio'] ?? '';
        $photoUrl   = $data['photoUrl'] ?? '';
        $location   = $data['location'] ?? '';
        $preferences = $data['preferences'] ?? '';

        // Calculate completion score based on filled fields
        $score = $this->calculateScore($userId, $data);
        $level = $this->calculateLevel($score);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE Profile
                SET bio = ?, photoUrl = ?, location = ?, preferences = ?,
                    completionScore = ?, level = ?
                WHERE userId = ?
            ");
            $stmt->execute([$bio, $photoUrl, $location, $preferences, $score, $level, $userId]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO Profile (userId, bio, photoUrl, location, preferences, completionScore, level)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $bio, $photoUrl, $location, $preferences, $score, $level]);
        }

        return ['success' => true, 'score' => $score, 'level' => $level];
    }

    public function calculateScore($userId, $profileData = []) {
        $score = 0;

        // User base info (fetched from DB)
        $stmt = $this->pdo->prepare("SELECT * FROM User WHERE userId = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if (!empty($user['fullName'])) $score += 10;
            if (!empty($user['email']))    $score += 10;
        }

        // Profile fields
        if (!empty($profileData['bio']))         $score += 15;
        if (!empty($profileData['photoUrl']))    $score += 15;
        if (!empty($profileData['location']))    $score += 10;
        if (!empty($profileData['preferences'])) $score += 10;

        // Skills
        $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM UserSkill WHERE userId = ?");
        $stmt2->execute([$userId]);
        $skillCount = (int)$stmt2->fetchColumn();
        if ($skillCount >= 1) $score += 10;
        if ($skillCount >= 3) $score += 10;
        if ($skillCount >= 5) $score += 10;

        return min($score, 100);
    }

    public function calculateLevel($score) {
        if ($score >= 80) return 'Expert';
        if ($score >= 60) return 'Advanced';
        if ($score >= 40) return 'Intermediate';
        if ($score >= 20) return 'Beginner';
        return 'Starter';
    }

    // =====================
    // USER SKILLS
    // =====================

    public function getSkillsByUserId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM UserSkill WHERE userId = ? ORDER BY validatedAt DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSkill($userId, $data) {
        $errors = $this->validateSkill($data);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        $stmt = $this->pdo->prepare("
            INSERT INTO UserSkill (userId, skillName, source, certificateUrl, validatedAt)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['skillName'],
            $data['source'] ?? '',
            $data['certificateUrl'] ?? '',
            $data['validatedAt'] ?? null
        ]);
        return ['success' => true, 'id' => $this->pdo->lastInsertId()];
    }

    public function deleteSkill($userSkillId, $userId) {
        // Make sure the skill belongs to the user
        $stmt = $this->pdo->prepare("DELETE FROM UserSkill WHERE userSkillId = ? AND userId = ?");
        $stmt->execute([$userSkillId, $userId]);
        return ['success' => true];
    }

    private function validateSkill($data) {
        $errors = [];
        if (empty($data['skillName']) || strlen($data['skillName']) < 2) {
            $errors[] = 'Skill name must be at least 2 characters.';
        }
        return $errors;
    }
}
?>