<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/SkillHubCore.php';

class SkillHubCoreController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return substr(str_replace('T', ' ', trim($value)), 0, 19);
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
        $query = $this->pdo->prepare("DELETE FROM SkillHub WHERE groupId = :groupId");
        return $query->execute(['groupId' => $groupId]);
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
        $query = $this->pdo->prepare("DELETE FROM Challenge WHERE challengeId = :challengeId");
        return $query->execute(['challengeId' => $challengeId]);
    }
}
