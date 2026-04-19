<?php

class Application {
    private PDO $db;
    private string $table = 'Application';

    private array $validStatuses = ['pending', 'accepted', 'rejected'];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // ── GETTERS ──

    public function getById(int $id): array|false {
        $sql = "SELECT 
                    a.*,
                    u.fullName AS applicantName,
                    o.title    AS opportunityTitle,
                    o.type     AS opportunityType
                FROM {$this->table} a
                LEFT JOIN User        u ON a.userId        = u.userId
                LEFT JOIN Opportunity o ON a.opportunityId = o.opportunityId
                WHERE a.applicationId = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getByUserId(int $userId): array {
        $sql = "SELECT 
                    a.*,
                    o.title    AS opportunityTitle,
                    o.type     AS opportunityType,
                    o.category AS opportunityCategory,
                    o.deadline AS opportunityDeadline
                FROM {$this->table} a
                LEFT JOIN Opportunity o ON a.opportunityId = o.opportunityId
                WHERE a.userId = :userId
                ORDER BY a.appliedAt DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function getByOpportunityId(int $opportunityId): array {
        $sql = "SELECT 
                    a.*,
                    u.fullName AS applicantName
                FROM {$this->table} a
                LEFT JOIN User u ON a.userId = u.userId
                WHERE a.opportunityId = :opportunityId
                ORDER BY a.appliedAt DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['opportunityId' => $opportunityId]);
        return $stmt->fetchAll();
    }

    public function getByStatus(string $status): array {
        $sql = "SELECT 
                    a.*,
                    u.fullName AS applicantName,
                    o.title    AS opportunityTitle
                FROM {$this->table} a
                LEFT JOIN User        u ON a.userId        = u.userId
                LEFT JOIN Opportunity o ON a.opportunityId = o.opportunityId
                WHERE a.status = :status
                ORDER BY a.appliedAt DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function getWithFilters(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'a.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]          = 'u.fullName LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['userId'])) {
            $where[]           = 'a.userId = :userId';
            $params['userId']  = $filters['userId'];
        }

        if (!empty($filters['opportunityId'])) {
            $where[]                  = 'a.opportunityId = :opportunityId';
            $params['opportunityId']  = $filters['opportunityId'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT 
                    a.*,
                    u.fullName AS applicantName,
                    o.title    AS opportunityTitle,
                    o.type     AS opportunityType
                FROM {$this->table} a
                LEFT JOIN User        u ON a.userId        = u.userId
                LEFT JOIN Opportunity o ON a.opportunityId = o.opportunityId
                WHERE {$whereClause}
                ORDER BY a.appliedAt DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStatusCounts(): array {
        $sql = "SELECT status, COUNT(*) as count 
                FROM {$this->table} 
                GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows    = $stmt->fetchAll();
        $counts  = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int)$row['count'];
            }
        }
        return $counts;
    }

    // ── CREATE ──

    public function create(array $data): array {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check not already applied
        $check = $this->db->prepare(
            "SELECT applicationId FROM {$this->table} 
             WHERE userId = :userId AND opportunityId = :opportunityId"
        );
        $check->execute([
            'userId'        => $data['userId'],
            'opportunityId' => $data['opportunityId'],
        ]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'You have already applied to this opportunity.'];
        }

        $sql = "INSERT INTO {$this->table}
                    (userId, opportunityId, motivation, portfolio, compatibilityScore, status, appliedAt)
                VALUES
                    (:userId, :opportunityId, :motivation, :portfolio, :compatibilityScore, 'pending', CURDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'userId'             => $data['userId'],
            'opportunityId'      => $data['opportunityId'],
            'motivation'         => trim($data['motivation'] ?? ''),
            'portfolio'          => trim($data['portfolio'] ?? '') ?: null,
            'compatibilityScore' => $data['compatibilityScore'] ?? rand(60, 99),
        ]);

        $newId = (int)$this->db->lastInsertId();
        return ['success' => true, 'applicationId' => $newId, 'message' => 'Application submitted.'];
    }

    // ── UPDATE ──

    public function update(int $id, array $data): array {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Application not found.'];
        }
        if ($existing['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending applications can be edited.'];
        }

        $sql = "UPDATE {$this->table} SET
                    motivation = :motivation,
                    portfolio  = :portfolio
                WHERE applicationId = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'motivation' => trim($data['motivation'] ?? $existing['motivation']),
            'portfolio'  => isset($data['portfolio']) ? (trim($data['portfolio']) ?: null) : $existing['portfolio'],
            'id'         => $id,
        ]);

        return ['success' => true, 'message' => 'Application updated.'];
    }

    // ── UPDATE STATUS (accept / reject) ──

    public function updateStatus(int $id, string $status): array {
        if (!in_array($status, $this->validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Application not found.'];
        }

        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET status = :status WHERE applicationId = :id"
        );
        $stmt->execute(['status' => $status, 'id' => $id]);
        return ['success' => true, 'message' => "Application marked as {$status}."];
    }

    // ── DELETE ──

    public function delete(int $id): array {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Application not found.'];
        }
        if ($existing['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending applications can be withdrawn.'];
        }

        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE applicationId = :id"
        );
        $stmt->execute(['id' => $id]);
        return ['success' => true, 'message' => 'Application withdrawn.'];
    }

    // ── VALIDATE ──

    private function validate(array $data): array {
        $errors = [];
        if (empty($data['userId']))        $errors[] = 'User ID is required.';
        if (empty($data['opportunityId'])) $errors[] = 'Opportunity ID is required.';
        if (empty(trim($data['motivation'] ?? ''))) $errors[] = 'Motivation is required.';
        return $errors;
    }
}