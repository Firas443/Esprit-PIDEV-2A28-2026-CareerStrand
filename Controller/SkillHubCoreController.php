<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/SkillHubCore.php';

class SkillHubCoreController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
        $this->ensureSkillHubCoreSchema();
    }

    private function ensureSkillHubCoreSchema(): void
    {
        $this->ensureAutoIncrementPrimaryKey('SkillHub', 'groupId');
        $this->ensureAutoIncrementPrimaryKey('GroupMember', 'groupMemberId');
        $this->ensureAutoIncrementPrimaryKey('Challenge', 'challengeId');
    }

    private function ensureAutoIncrementPrimaryKey(string $table, string $primaryKey): void
    {
        try {
            $tableExists = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $tableExists->execute([$table]);
            if ((int) $tableExists->fetchColumn() < 1) {
                return;
            }

            $column = $this->pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $this->pdo->quote($primaryKey))->fetch();
            if (!$column) {
                return;
            }

            $hasPrimary = strtoupper((string) ($column['Key'] ?? '')) === 'PRI';
            $hasAutoIncrement = stripos((string) ($column['Extra'] ?? ''), 'auto_increment') !== false;

            if (!$hasPrimary) {
                $this->pdo->exec("ALTER TABLE `$table` ADD PRIMARY KEY (`$primaryKey`)");
            }

            if (!$hasAutoIncrement) {
                $this->pdo->exec("ALTER TABLE `$table` MODIFY `$primaryKey` int(11) NOT NULL AUTO_INCREMENT");
            }
        } catch (Throwable $exception) {
            // Keep the controller usable even if the database user cannot alter schema.
        }
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return substr(str_replace('T', ' ', trim($value)), 0, 19);
    }

    private function normalizeRecommendationText(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function difficultyTarget(string $difficulty): int
    {
        return match ($this->normalizeRecommendationText($difficulty)) {
            'beginner' => 25,
            'intermediate' => 55,
            'advanced' => 85,
            default => 50,
        };
    }

    private function difficultyScore(?int $skillLevel, string $difficulty): int
    {
        if ($skillLevel === null) {
            return 10;
        }

        $difference = abs($skillLevel - $this->difficultyTarget($difficulty));

        return match (true) {
            $difference <= 10 => 30,
            $difference <= 22 => 22,
            $difference <= 35 => 14,
            default => 6,
        };
    }

    private function skillMatchesChallenge(string $skillName, string $hubCategory, string $title, string $description): bool
    {
        $skill = $this->normalizeRecommendationText($skillName);
        if ($skill === '') {
            return false;
        }

        $haystack = implode(' ', [
            $this->normalizeRecommendationText($hubCategory),
            $this->normalizeRecommendationText($title),
            $this->normalizeRecommendationText($description),
        ]);

        return str_contains($haystack, $skill)
            || str_contains($skill, $this->normalizeRecommendationText($hubCategory))
            || str_contains($this->normalizeRecommendationText($hubCategory), $skill);
    }

    public function getAllSkillHubs(): array
    {
        $query = $this->pdo->query("SELECT * FROM SkillHub ORDER BY groupId DESC");
        return $query->fetchAll();
    }

    public function getSkillHubById(int $groupId): ?array
    {
        $query = $this->pdo->prepare("SELECT * FROM SkillHub WHERE groupId = :groupId");
        $query->execute(['groupId' => $groupId]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function skillHubNameExists(string $name, ?int $excludeGroupId = null): bool
    {
        $query = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM SkillHub
             WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
               AND (:excludeGroupId IS NULL OR groupId <> :excludeGroupId)"
        );
        $query->bindValue(':name', $name, PDO::PARAM_STR);
        $query->bindValue(':excludeGroupId', $excludeGroupId, $excludeGroupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->execute();
        return (int) $query->fetchColumn() > 0;
    }

    public function createSkillHub(SkillHubEntity $skillHub): bool
    {
        $query = $this->pdo->prepare(
            "INSERT INTO SkillHub (name, category, description, createdAt, status)
             VALUES (:name, :category, :description, :createdAt, :status)"
        );

        return $query->execute([
            'name' => $skillHub->getName(),
            'category' => $skillHub->getCategory(),
            'description' => $skillHub->getDescription(),
            'createdAt' => $this->normalizeDate($skillHub->getCreatedAt()) ?? date('Y-m-d'),
            'status' => $skillHub->getStatus(),
        ]);
    }

    public function updateSkillHub(int $groupId, SkillHubEntity $skillHub): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE SkillHub
             SET name = :name,
                 category = :category,
                 description = :description,
                 status = :status
             WHERE groupId = :groupId"
        );

        return $query->execute([
            'groupId' => $groupId,
            'name' => $skillHub->getName(),
            'category' => $skillHub->getCategory(),
            'description' => $skillHub->getDescription(),
            'status' => $skillHub->getStatus(),
        ]);
    }

    public function deleteSkillHub(int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $query = $this->pdo->prepare(
                "DELETE cm
                 FROM Comments cm
                 INNER JOIN Post p ON p.postId = cm.postId
                 LEFT JOIN Challenge c ON c.challengeId = p.challengeId
                 WHERE p.groupId = :postGroupId
                    OR c.groupId = :challengeGroupId"
            );
            $query->execute([
                'postGroupId' => $groupId,
                'challengeGroupId' => $groupId,
            ]);

            $query = $this->pdo->prepare(
                "DELETE p
                 FROM Post p
                 LEFT JOIN Challenge c ON c.challengeId = p.challengeId
                 WHERE p.groupId = :postGroupId
                    OR c.groupId = :challengeGroupId"
            );
            $query->execute([
                'postGroupId' => $groupId,
                'challengeGroupId' => $groupId,
            ]);

            $query = $this->pdo->prepare(
                "DELETE s
                 FROM Submission s
                 LEFT JOIN Challenge c ON c.challengeId = s.challengeId
                 LEFT JOIN GroupMember gm ON gm.groupMemberId = s.groupMemberId
                 WHERE c.groupId = :challengeGroupId
                    OR gm.groupId = :memberGroupId"
            );
            $query->execute([
                'challengeGroupId' => $groupId,
                'memberGroupId' => $groupId,
            ]);

            $query = $this->pdo->prepare("DELETE FROM Challenge WHERE groupId = :groupId");
            $query->execute(['groupId' => $groupId]);

            $query = $this->pdo->prepare("DELETE FROM GroupMember WHERE groupId = :groupId");
            $query->execute(['groupId' => $groupId]);

            $query = $this->pdo->prepare("DELETE FROM SkillHub WHERE groupId = :groupId");
            $query->execute(['groupId' => $groupId]);

            $this->pdo->commit();
            return $query->rowCount() > 0;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    public function getAllGroupMembers(): array
    {
        $query = $this->pdo->query("SELECT * FROM GroupMember ORDER BY groupMemberId DESC");
        return $query->fetchAll();
    }

    public function getGroupMemberById(int $groupMemberId): ?array
    {
        $query = $this->pdo->prepare("SELECT * FROM GroupMember WHERE groupMemberId = :groupMemberId");
        $query->execute(['groupMemberId' => $groupMemberId]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function getGroupMemberByGroupAndUser(int $groupId, int $userId): ?array
    {
        $query = $this->pdo->prepare(
            "SELECT *
             FROM GroupMember
             WHERE groupId = :groupId
               AND userId = :userId
             ORDER BY groupMemberId DESC
             LIMIT 1"
        );
        $query->execute([
            'groupId' => $groupId,
            'userId' => $userId,
        ]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function createGroupMember(GroupMemberEntity $groupMember): bool
    {
        $query = $this->pdo->prepare(
            "INSERT INTO GroupMember (groupId, userId, joinedAt, status)
             VALUES (:groupId, :userId, :joinedAt, :status)"
        );

        return $query->execute([
            'groupId' => $groupMember->getGroupId(),
            'userId' => $groupMember->getUserId(),
            'joinedAt' => $this->normalizeDate($groupMember->getJoinedAt()) ?? date('Y-m-d'),
            'status' => $groupMember->getStatus(),
        ]);
    }

    public function updateGroupMember(int $groupMemberId, GroupMemberEntity $groupMember): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE GroupMember
             SET groupId = :groupId,
                 userId = :userId,
                 joinedAt = :joinedAt,
                 status = :status
             WHERE groupMemberId = :groupMemberId"
        );

        return $query->execute([
            'groupMemberId' => $groupMemberId,
            'groupId' => $groupMember->getGroupId(),
            'userId' => $groupMember->getUserId(),
            'joinedAt' => $this->normalizeDate($groupMember->getJoinedAt()),
            'status' => $groupMember->getStatus(),
        ]);
    }

    public function deleteGroupMember(int $groupMemberId): bool
    {
        $query = $this->pdo->prepare("DELETE FROM GroupMember WHERE groupMemberId = :groupMemberId");
        return $query->execute(['groupMemberId' => $groupMemberId]);
    }

    public function deleteGroupMemberByGroupAndUser(int $groupId, int $userId): bool
    {
        $query = $this->pdo->prepare(
            "DELETE FROM GroupMember
             WHERE groupId = :groupId
               AND userId = :userId"
        );
        return $query->execute([
            'groupId' => $groupId,
            'userId' => $userId,
        ]);
    }

    public function getAllChallenges(): array
    {
        $query = $this->pdo->query("SELECT * FROM Challenge ORDER BY challengeId DESC");
        return $query->fetchAll();
    }

    public function getChallengeById(int $challengeId): ?array
    {
        $query = $this->pdo->prepare("SELECT * FROM Challenge WHERE challengeId = :challengeId");
        $query->execute(['challengeId' => $challengeId]);
        $row = $query->fetch();
        return $row ?: null;
    }

    public function filterChallenges(array $filters = []): array
    {
        $groupId = isset($filters['groupId']) ? (int) $filters['groupId'] : 0;
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $difficulty = trim((string) ($filters['difficulty'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $sort = trim((string) ($filters['sort'] ?? 'newest'));

        $sql = "SELECT
                    c.*,
                    s.name AS hubName,
                    s.category AS hubCategory,
                    u.fullName AS managerName
                FROM Challenge c
                LEFT JOIN SkillHub s ON s.groupId = c.groupId
                LEFT JOIN Users u ON u.userId = c.managerId
                WHERE 1 = 1";

        $params = [];

        if ($groupId > 0) {
            $sql .= " AND c.groupId = :groupId";
            $params['groupId'] = $groupId;
        }

        if ($search !== '') {
            $sql .= " AND (
                LOWER(c.title) LIKE :search
                OR LOWER(COALESCE(c.description, '')) LIKE :search
                OR LOWER(COALESCE(s.name, '')) LIKE :search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
        }

        if ($category !== '') {
            $sql .= " AND LOWER(COALESCE(s.category, '')) = :category";
            $params['category'] = strtolower($category);
        }

        if ($difficulty !== '') {
            $sql .= " AND LOWER(COALESCE(c.difficulty, '')) = :difficulty";
            $params['difficulty'] = strtolower($difficulty);
        }

        if ($type !== '') {
            $sql .= " AND LOWER(COALESCE(c.type, '')) = :type";
            $params['type'] = strtolower($type);
        }

        if ($status !== '') {
            $sql .= " AND LOWER(COALESCE(c.status, '')) = :status";
            $params['status'] = strtolower($status);
        }

        $orderBy = match ($sort) {
            'deadline_soon' => " ORDER BY
                CASE WHEN c.deadline IS NULL THEN 1 ELSE 0 END ASC,
                c.deadline ASC,
                c.challengeId DESC",
            'difficulty_high' => " ORDER BY
                CASE LOWER(COALESCE(c.difficulty, ''))
                    WHEN 'advanced' THEN 0
                    WHEN 'intermediate' THEN 1
                    WHEN 'beginner' THEN 2
                    ELSE 3
                END ASC,
                c.challengeId DESC",
            'title_az' => " ORDER BY c.title ASC, c.challengeId DESC",
            default => " ORDER BY c.challengeId DESC",
        };

        $query = $this->pdo->prepare($sql . $orderBy);
        $query->execute($params);
        return $query->fetchAll();
    }

    public function getRecommendedChallengesForUser(int $userId, int $limit = 4): array
    {
        $joinedRows = $this->pdo->prepare(
            "SELECT gm.groupId, sh.category
             FROM GroupMember gm
             LEFT JOIN SkillHub sh ON sh.groupId = gm.groupId
             WHERE gm.userId = :userId"
        );
        $joinedRows->execute(['userId' => $userId]);
        $joinedHubs = $joinedRows->fetchAll();

        $joinedGroupIds = [];
        $joinedCategories = [];
        foreach ($joinedHubs as $joinedHub) {
            $joinedGroupIds[(int) $joinedHub['groupId']] = true;
            $category = $this->normalizeRecommendationText($joinedHub['category'] ?? '');
            if ($category !== '') {
                $joinedCategories[$category] = true;
            }
        }

        $skillRows = $this->pdo->prepare(
            "SELECT skillName, level
             FROM UserSkill
             WHERE userId = :userId"
        );
        $skillRows->execute(['userId' => $userId]);
        $userSkills = $skillRows->fetchAll();

        $skillLevels = [];
        $skillNames = [];
        foreach ($userSkills as $skillRow) {
            $skillName = trim((string) ($skillRow['skillName'] ?? ''));
            if ($skillName !== '') {
                $skillNames[] = $skillName;
            }

            if ($skillRow['level'] !== null && $skillRow['level'] !== '') {
                $skillLevels[] = max(0, min(100, (int) $skillRow['level']));
            }
        }

        $averageSkillLevel = !empty($skillLevels)
            ? (int) round(array_sum($skillLevels) / count($skillLevels))
            : 50;

        $submittedRows = $this->pdo->prepare(
            "SELECT DISTINCT s.challengeId
             FROM Submission s
             INNER JOIN GroupMember gm ON gm.groupMemberId = s.groupMemberId
             WHERE gm.userId = :userId"
        );
        $submittedRows->execute(['userId' => $userId]);
        $submittedChallengeIds = [];
        foreach ($submittedRows->fetchAll() as $submittedRow) {
            $submittedChallengeIds[(int) $submittedRow['challengeId']] = true;
        }

        $challengeQuery = $this->pdo->query(
            "SELECT
                c.*,
                sh.name AS hubName,
                sh.category AS hubCategory,
                u.fullName AS managerName
             FROM Challenge c
             LEFT JOIN SkillHub sh ON sh.groupId = c.groupId
             LEFT JOIN Users u ON u.userId = c.managerId
             WHERE LOWER(COALESCE(c.status, '')) IN ('published', 'active')
             ORDER BY c.challengeId DESC"
        );

        $recommendations = [];
        foreach ($challengeQuery->fetchAll() as $challenge) {
            $challengeId = (int) ($challenge['challengeId'] ?? 0);
            if ($challengeId <= 0 || isset($submittedChallengeIds[$challengeId])) {
                continue;
            }

            $score = 0;
            $reasons = [];
            $hubCategory = (string) ($challenge['hubCategory'] ?? '');
            $title = (string) ($challenge['title'] ?? '');
            $description = (string) ($challenge['description'] ?? '');

            if (isset($joinedGroupIds[(int) ($challenge['groupId'] ?? 0)])) {
                $score += 35;
                $reasons[] = 'You already belong to this hub.';
            } elseif (isset($joinedCategories[$this->normalizeRecommendationText($hubCategory)])) {
                $score += 18;
                $reasons[] = 'Its category matches one of your joined hubs.';
            }

            $matchedSkill = false;
            foreach ($skillNames as $skillName) {
                if ($this->skillMatchesChallenge($skillName, $hubCategory, $title, $description)) {
                    $matchedSkill = true;
                    break;
                }
            }
            if ($matchedSkill) {
                $score += 20;
                $reasons[] = 'It lines up with one of your saved skills.';
            }

            $difficultyPoints = $this->difficultyScore($averageSkillLevel, (string) ($challenge['difficulty'] ?? ''));
            $score += $difficultyPoints;
            if ($difficultyPoints >= 22) {
                $reasons[] = 'The difficulty fits your current skill level.';
            } elseif ($difficultyPoints >= 14) {
                $reasons[] = 'The difficulty is close to your current level.';
            }

            $deadlineRaw = trim((string) ($challenge['deadline'] ?? ''));
            if ($deadlineRaw !== '') {
                $deadline = strtotime($deadlineRaw);
                if ($deadline !== false) {
                    $daysUntilDeadline = (int) floor(($deadline - time()) / 86400);
                    if ($daysUntilDeadline >= 0 && $daysUntilDeadline <= 21) {
                        $score += 10;
                        $reasons[] = 'The deadline is still within reach.';
                    } elseif ($daysUntilDeadline > 21) {
                        $score += 6;
                    }
                }
            }

            $score += 5;

            $challenge['recommendationScore'] = min(100, $score);
            $challenge['recommendationReasons'] = array_slice(array_values(array_unique($reasons)), 0, 3);
            $challenge['skillLevelAverage'] = $averageSkillLevel;
            $recommendations[] = $challenge;
        }

        usort($recommendations, static function (array $left, array $right): int {
            $scoreComparison = (int) ($right['recommendationScore'] ?? 0) <=> (int) ($left['recommendationScore'] ?? 0);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $leftDeadline = strtotime((string) ($left['deadline'] ?? '')) ?: PHP_INT_MAX;
            $rightDeadline = strtotime((string) ($right['deadline'] ?? '')) ?: PHP_INT_MAX;
            if ($leftDeadline !== $rightDeadline) {
                return $leftDeadline <=> $rightDeadline;
            }

            return (int) ($right['challengeId'] ?? 0) <=> (int) ($left['challengeId'] ?? 0);
        });

        return array_slice($recommendations, 0, max(1, $limit));
    }

    public function challengeTitleExists(string $title, int $groupId, ?int $excludeChallengeId = null): bool
    {
        $query = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM Challenge
             WHERE LOWER(TRIM(title)) = LOWER(TRIM(:title))
               AND groupId = :groupId
               AND (:excludeChallengeId IS NULL OR challengeId <> :excludeChallengeId)"
        );
        $query->bindValue(':title', $title, PDO::PARAM_STR);
        $query->bindValue(':groupId', $groupId, PDO::PARAM_INT);
        $query->bindValue(':excludeChallengeId', $excludeChallengeId, $excludeChallengeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->execute();
        return (int) $query->fetchColumn() > 0;
    }

    public function createChallenge(ChallengeEntity $challenge): bool
    {
        $query = $this->pdo->prepare(
            "INSERT INTO Challenge (
                groupId, managerId, type, title, description, difficulty, deadline, status, createdAt
             ) VALUES (
                :groupId, :managerId, :type, :title, :description, :difficulty, :deadline, :status, :createdAt
             )"
        );

        return $query->execute([
            'groupId' => $challenge->getGroupId(),
            'managerId' => $challenge->getManagerId(),
            'type' => $challenge->getType(),
            'title' => $challenge->getTitle(),
            'description' => $challenge->getDescription(),
            'difficulty' => $challenge->getDifficulty(),
            'deadline' => $this->normalizeDate($challenge->getDeadline()),
            'status' => $challenge->getStatus(),
            'createdAt' => $this->normalizeDate($challenge->getCreatedAt()) ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function updateChallenge(int $challengeId, ChallengeEntity $challenge): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE Challenge
             SET groupId = :groupId,
                 managerId = :managerId,
                 type = :type,
                 title = :title,
                 description = :description,
                 difficulty = :difficulty,
                 deadline = :deadline,
                 status = :status
             WHERE challengeId = :challengeId"
        );

        return $query->execute([
            'challengeId' => $challengeId,
            'groupId' => $challenge->getGroupId(),
            'managerId' => $challenge->getManagerId(),
            'type' => $challenge->getType(),
            'title' => $challenge->getTitle(),
            'description' => $challenge->getDescription(),
            'difficulty' => $challenge->getDifficulty(),
            'deadline' => $this->normalizeDate($challenge->getDeadline()),
            'status' => $challenge->getStatus(),
        ]);
    }

    public function deleteChallenge(int $challengeId): bool
    {
        if ($challengeId <= 0) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $query = $this->pdo->prepare(
                "DELETE cm
                 FROM Comments cm
                 INNER JOIN Post p ON p.postId = cm.postId
                 WHERE p.challengeId = :challengeId"
            );
            $query->execute(['challengeId' => $challengeId]);

            $query = $this->pdo->prepare("DELETE FROM Post WHERE challengeId = :challengeId");
            $query->execute(['challengeId' => $challengeId]);

            $query = $this->pdo->prepare("DELETE FROM Submission WHERE challengeId = :challengeId");
            $query->execute(['challengeId' => $challengeId]);

            $query = $this->pdo->prepare("DELETE FROM Challenge WHERE challengeId = :challengeId");
            $query->execute(['challengeId' => $challengeId]);

            $this->pdo->commit();
            return $query->rowCount() > 0;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }
}
