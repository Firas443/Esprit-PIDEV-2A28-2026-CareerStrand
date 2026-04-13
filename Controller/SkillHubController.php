<?php

require_once __DIR__ . '/../config.php';

class SkillHubController
{
    private PDO $pdo;
    private array $columnCache = [];

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        try {
            $query = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column"
            );
            $query->execute([
                'table' => $table,
                'column' => $column,
            ]);
            $exists = (int) $query->fetchColumn() > 0;
            $this->columnCache[$cacheKey] = $exists;
            return $exists;
        } catch (PDOException $e) {
            $this->columnCache[$cacheKey] = false;
            return false;
        }
    }

    private function normalizeDeadline(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr(str_replace('T', ' ', $value), 0, 10);
    }

    public function getStats(): array
    {
        try {
            return [
                'hubCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM SkillHub")->fetchColumn(),
                'managerCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(role) IN ('manager', 'admin')")->fetchColumn(),
                'workCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM Challenge")->fetchColumn(),
                'threadCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM Post")->fetchColumn(),
            ];
        } catch (PDOException $e) {
            return [
                'hubCount' => 0,
                'managerCount' => 0,
                'workCount' => 0,
                'threadCount' => 0,
            ];
        }
    }

    public function getManagers(): array
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT userId, fullName, role
                 FROM Users
                 WHERE LOWER(role) IN ('manager', 'admin')
                 ORDER BY fullName ASC"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getDefaultUserId(): ?int
    {
        try {
            $query = $this->pdo->prepare("SELECT userId FROM Users ORDER BY userId ASC LIMIT 1");
            $query->execute();
            $userId = $query->fetchColumn();
            return $userId !== false ? (int) $userId : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function afficherHubs(): array
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT
                    s.groupId,
                    s.name,
                    s.category,
                    s.description,
                    s.createdAt,
                    s.status,
                    COUNT(DISTINCT gm.userId) AS memberCount,
                    COUNT(DISTINCT c.challengeId) AS workCount,
                    COUNT(DISTINCT p.postId) AS threadCount
                 FROM SkillHub s
                 LEFT JOIN GroupMember gm ON gm.groupId = s.groupId
                 LEFT JOIN Challenge c ON c.groupId = s.groupId
                 LEFT JOIN Post p ON p.groupId = s.groupId
                 GROUP BY s.groupId, s.name, s.category, s.description, s.createdAt, s.status
                 ORDER BY s.createdAt DESC, s.name ASC"
            );
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
    }

    public function getHubById(int $groupId): ?array
    {
        try {
            $query = $this->pdo->prepare("SELECT * FROM SkillHub WHERE groupId = :id");
            $query->execute(['id' => $groupId]);
            $hub = $query->fetch();
            return $hub ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getFirstHubId(): ?int
    {
        try {
            $query = $this->pdo->prepare("SELECT groupId FROM SkillHub ORDER BY groupId ASC LIMIT 1");
            $query->execute();
            $groupId = $query->fetchColumn();
            return $groupId !== false ? (int) $groupId : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function createHub(array $data): bool
    {
        try {
            $query = $this->pdo->prepare(
                "INSERT INTO SkillHub (name, category, description, createdAt, status)
                 VALUES (:name, :category, :description, CURDATE(), :status)"
            );
            return $query->execute([
                'name' => trim($data['name']),
                'category' => trim($data['category']),
                'description' => trim($data['description']),
                'status' => trim($data['status']),
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateHub(int $groupId, array $data): bool
    {
        try {
            $query = $this->pdo->prepare(
                "UPDATE SkillHub
                 SET name = :name,
                     category = :category,
                     description = :description,
                     status = :status
                 WHERE groupId = :id"
            );
            return $query->execute([
                'id' => $groupId,
                'name' => trim($data['name']),
                'category' => trim($data['category']),
                'description' => trim($data['description']),
                'status' => trim($data['status']),
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function deleteHub(int $groupId): bool
    {
        try {
            $query = $this->pdo->prepare("DELETE FROM SkillHub WHERE groupId = :id");
            return $query->execute(['id' => $groupId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getWorkItems(?int $groupId = null): array
    {
        try {
            $hasType = $this->columnExists('Challenge', 'type');
            $hasPostChallenge = $this->columnExists('Post', 'challengeId');

            $typeSelect = $hasType ? "c.type" : "'task' AS type";
            $typeGroup = $hasType ? "c.type" : "'task'";
            $threadSelect = $hasPostChallenge ? "COUNT(DISTINCT p.postId) AS threadCount" : "0 AS threadCount";
            $threadJoin = $hasPostChallenge ? "LEFT JOIN Post p ON p.challengeId = c.challengeId" : "";

            $sql =
                "SELECT
                    c.challengeId,
                    c.groupId,
                    c.managerId,
                    $typeSelect,
                    c.title,
                    c.description,
                    c.difficulty,
                    c.deadline,
                    c.status,
                    c.createdAt,
                    s.name AS hubName,
                    s.category AS hubCategory,
                    u.fullName AS managerName,
                    $threadSelect
                 FROM Challenge c
                 INNER JOIN SkillHub s ON c.groupId = s.groupId
                 LEFT JOIN Users u ON c.managerId = u.userId
                 $threadJoin
                 WHERE (:groupId IS NULL OR c.groupId = :groupId)
                 GROUP BY
                    c.challengeId, c.groupId, c.managerId, $typeGroup, c.title, c.description,
                    c.difficulty, c.deadline, c.status, c.createdAt, s.name, s.category, u.fullName
                 ORDER BY c.createdAt DESC, c.deadline ASC";

            $query = $this->pdo->prepare($sql);
            $query->bindValue(':groupId', $groupId, $groupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
    }

    public function afficherWorkItems(int $groupId): array
    {
        return $this->getWorkItems($groupId);
    }

    public function getWorkItemById(int $challengeId): ?array
    {
        try {
            $hasType = $this->columnExists('Challenge', 'type');
            $typeSelect = $hasType ? "c.type" : "'task' AS type";

            $query = $this->pdo->prepare(
                "SELECT
                    c.challengeId,
                    c.groupId,
                    c.managerId,
                    $typeSelect,
                    c.title,
                    c.description,
                    c.difficulty,
                    c.deadline,
                    c.status,
                    c.createdAt,
                    s.name AS hubName,
                    s.category AS hubCategory,
                    s.description AS hubDescription,
                    u.fullName AS managerName
                 FROM Challenge c
                 INNER JOIN SkillHub s ON c.groupId = s.groupId
                 LEFT JOIN Users u ON c.managerId = u.userId
                 WHERE c.challengeId = :id"
            );
            $query->execute(['id' => $challengeId]);
            $item = $query->fetch();
            return $item ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function createWorkItem(array $data): bool
    {
        try {
            $deadline = $this->normalizeDeadline($data['deadline'] ?? null);
            $hasType = $this->columnExists('Challenge', 'type');

            if ($hasType) {
                $query = $this->pdo->prepare(
                    "INSERT INTO Challenge (
                        groupId, managerId, type, title, description, difficulty, deadline, status, createdAt
                     ) VALUES (
                        :groupId, :managerId, :type, :title, :description, :difficulty, :deadline, :status, CURDATE()
                     )"
                );

                return $query->execute([
                    'groupId' => (int) $data['groupId'],
                    'managerId' => (int) $data['managerId'],
                    'type' => trim($data['type']),
                    'title' => trim($data['title']),
                    'description' => trim($data['description']),
                    'difficulty' => trim($data['difficulty']),
                    'deadline' => $deadline,
                    'status' => trim($data['status']),
                ]);
            }

            $query = $this->pdo->prepare(
                "INSERT INTO Challenge (
                    groupId, managerId, title, description, difficulty, deadline, status, createdAt
                 ) VALUES (
                    :groupId, :managerId, :title, :description, :difficulty, :deadline, :status, CURDATE()
                 )"
            );

            return $query->execute([
                'groupId' => (int) $data['groupId'],
                'managerId' => (int) $data['managerId'],
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'difficulty' => trim($data['difficulty']),
                'deadline' => $deadline,
                'status' => trim($data['status']),
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateWorkItem(int $challengeId, array $data): bool
    {
        try {
            $deadline = $this->normalizeDeadline($data['deadline'] ?? null);
            $hasType = $this->columnExists('Challenge', 'type');

            if ($hasType) {
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
                     WHERE challengeId = :id"
                );

                return $query->execute([
                    'id' => $challengeId,
                    'groupId' => (int) $data['groupId'],
                    'managerId' => (int) $data['managerId'],
                    'type' => trim($data['type']),
                    'title' => trim($data['title']),
                    'description' => trim($data['description']),
                    'difficulty' => trim($data['difficulty']),
                    'deadline' => $deadline,
                    'status' => trim($data['status']),
                ]);
            }

            $query = $this->pdo->prepare(
                "UPDATE Challenge
                 SET groupId = :groupId,
                     managerId = :managerId,
                     title = :title,
                     description = :description,
                     difficulty = :difficulty,
                     deadline = :deadline,
                     status = :status
                 WHERE challengeId = :id"
            );

            return $query->execute([
                'id' => $challengeId,
                'groupId' => (int) $data['groupId'],
                'managerId' => (int) $data['managerId'],
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'difficulty' => trim($data['difficulty']),
                'deadline' => $deadline,
                'status' => trim($data['status']),
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function deleteWorkItem(int $challengeId): bool
    {
        try {
            $query = $this->pdo->prepare("DELETE FROM Challenge WHERE challengeId = :id");
            return $query->execute(['id' => $challengeId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getHubMembers(int $groupId, int $limit = 6): array
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT u.userId, u.fullName, u.role, gm.joinedAt, gm.status
                 FROM GroupMember gm
                 INNER JOIN Users u ON gm.userId = u.userId
                 WHERE gm.groupId = :groupId
                 ORDER BY gm.joinedAt DESC, u.fullName ASC
                 LIMIT :limit"
            );
            $query->bindValue(':groupId', $groupId, PDO::PARAM_INT);
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getHubPosts(int $groupId): array
    {
        try {
            $hasPostType = $this->columnExists('Post', 'postType');
            $hasPostStatus = $this->columnExists('Post', 'status');
            $hasChallengeId = $this->columnExists('Post', 'challengeId');

            $postTypeSelect = $hasPostType ? "p.postType" : "'discussion' AS postType";
            $statusSelect = $hasPostStatus ? "p.status" : "'active' AS status";
            $challengeFilter = $hasChallengeId ? "AND p.challengeId IS NULL" : "";

            $query = $this->pdo->prepare(
                "SELECT
                    p.postId,
                    p.groupId,
                    p.userId,
                    p.title,
                    p.content,
                    p.createdAt,
                    $postTypeSelect,
                    $statusSelect,
                    u.fullName,
                    u.role,
                    COUNT(DISTINCT c.commentId) AS commentCount
                 FROM Post p
                 INNER JOIN Users u ON p.userId = u.userId
                 LEFT JOIN Comments c ON c.postId = p.postId
                 WHERE p.groupId = :groupId
                 $challengeFilter
                 GROUP BY p.postId, p.groupId, p.userId, p.title, p.content, p.createdAt, $postTypeSelect, $statusSelect, u.fullName, u.role
                 ORDER BY p.createdAt DESC"
            );
            $query->execute(['groupId' => $groupId]);
            return $query->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function createHubPost(int $groupId, int $userId, string $title, string $content, string $postType = 'discussion'): bool
    {
        try {
            $hasPostType = $this->columnExists('Post', 'postType');
            $hasPostStatus = $this->columnExists('Post', 'status');
            $columns = ['groupId', 'userId', 'title', 'content', 'createdAt'];
            $placeholders = [':groupId', ':userId', ':title', ':content', 'NOW()'];
            $params = [
                'groupId' => $groupId,
                'userId' => $userId,
                'title' => trim($title),
                'content' => trim($content),
            ];

            if ($hasPostType) {
                $columns[] = 'postType';
                $placeholders[] = ':postType';
                $params['postType'] = trim($postType);
            }

            if ($hasPostStatus) {
                $columns[] = 'status';
                $placeholders[] = ':status';
                $params['status'] = 'active';
            }

            $query = $this->pdo->prepare(
                'INSERT INTO Post (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );

            try {
                return $query->execute($params);
            } catch (PDOException $e) {
                $columns[array_search('createdAt', $columns, true)] = 'createdAt';
                $placeholders[array_search('NOW()', $placeholders, true)] = 'CURDATE()';
                $query = $this->pdo->prepare(
                    'INSERT INTO Post (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', $placeholders) . ')'
                );
                return $query->execute($params);
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getThreadPostsForChallenge(int $groupId, int $challengeId): array
    {
        try {
            $hasPostType = $this->columnExists('Post', 'postType');
            $hasPostStatus = $this->columnExists('Post', 'status');
            $hasChallengeId = $this->columnExists('Post', 'challengeId');

            $postTypeSelect = $hasPostType ? "p.postType" : "'discussion' AS postType";
            $statusSelect = $hasPostStatus ? "p.status" : "'active' AS status";
            $whereClause = $hasChallengeId
                ? "(p.challengeId = :challengeId OR (p.challengeId IS NULL AND p.groupId = :groupId))"
                : "p.groupId = :groupId";

            $query = $this->pdo->prepare(
                "SELECT
                    p.postId,
                    p.groupId,
                    p.userId,
                    p.title,
                    p.content,
                    p.createdAt,
                    $postTypeSelect,
                    $statusSelect,
                    u.fullName,
                    u.role,
                    COUNT(DISTINCT c.commentId) AS commentCount
                 FROM Post p
                 INNER JOIN Users u ON p.userId = u.userId
                 LEFT JOIN Comments c ON c.postId = p.postId
                 WHERE $whereClause
                 GROUP BY p.postId, p.groupId, p.userId, p.title, p.content, p.createdAt, $postTypeSelect, $statusSelect, u.fullName, u.role
                 ORDER BY p.createdAt DESC"
            );
            $query->bindValue(':groupId', $groupId, PDO::PARAM_INT);
            if ($hasChallengeId) {
                $query->bindValue(':challengeId', $challengeId, PDO::PARAM_INT);
            }
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function createThreadPost(int $groupId, int $challengeId, int $userId, string $title, string $content, string $postType = 'discussion'): bool
    {
        try {
            $hasPostType = $this->columnExists('Post', 'postType');
            $hasPostStatus = $this->columnExists('Post', 'status');
            $hasChallengeId = $this->columnExists('Post', 'challengeId');

            if ($hasChallengeId) {
                $columns = ['groupId', 'userId', 'challengeId', 'title', 'content', 'createdAt'];
                $placeholders = [':groupId', ':userId', ':challengeId', ':title', ':content', 'NOW()'];
                $params = [
                    'groupId' => $groupId,
                    'userId' => $userId,
                    'challengeId' => $challengeId,
                    'title' => trim($title),
                    'content' => trim($content),
                ];

                if ($hasPostType) {
                    $columns[] = 'postType';
                    $placeholders[] = ':postType';
                    $params['postType'] = trim($postType);
                }

                if ($hasPostStatus) {
                    $columns[] = 'status';
                    $placeholders[] = ':status';
                    $params['status'] = 'active';
                }

                $query = $this->pdo->prepare(
                    'INSERT INTO Post (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', $placeholders) . ')'
                );

                try {
                    return $query->execute($params);
                } catch (PDOException $e) {
                    $placeholders[array_search('NOW()', $placeholders, true)] = 'CURDATE()';
                    $query = $this->pdo->prepare(
                        'INSERT INTO Post (' . implode(', ', $columns) . ')
                         VALUES (' . implode(', ', $placeholders) . ')'
                    );
                    return $query->execute($params);
                }
            }

            return $this->createHubPost($groupId, $userId, $title, $content, $postType);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getCommentsByPost(int $postId): array
    {
        try {
            $query = $this->pdo->prepare(
                "SELECT c.commentId, c.postId, c.userId, c.content, c.createdAt, u.fullName, u.role
                 FROM Comments c
                 INNER JOIN Users u ON c.userId = u.userId
                 WHERE c.postId = :postId
                 ORDER BY c.createdAt ASC"
            );
            $query->execute(['postId' => $postId]);
            return $query->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function createComment(int $postId, int $userId, string $content): bool
    {
        try {
            $query = $this->pdo->prepare(
                "INSERT INTO Comments (postId, userId, content, likesCount, createdAt)
                 VALUES (:postId, :userId, :content, 0, NOW())"
            );
            return $query->execute([
                'postId' => $postId,
                'userId' => $userId,
                'content' => trim($content),
            ]);
        } catch (PDOException $e) {
            $query = $this->pdo->prepare(
                "INSERT INTO Comments (postId, userId, content, likesCount, createdAt)
                 VALUES (:postId, :userId, :content, 0, CURDATE())"
            );
            return $query->execute([
                'postId' => $postId,
                'userId' => $userId,
                'content' => trim($content),
            ]);
        }
    }
}
