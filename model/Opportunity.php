<?php

class Opportunity {
    private PDO $db;
    private string $table = 'Opportunity';

    // Allowed values for validation
    private array $validTypes       = ['internship', 'job', 'freelance', 'volunteer'];
    private array $validCategories  = ['Technical', 'Creativity', 'Business', 'Communication', 'Leadership'];
    private array $validLevels      = ['Beginner', 'Intermediate', 'Advanced'];
    private array $validStatuses    = ['published', 'draft', 'archived'];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

//read all

    public function getAll(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'o.status = :status';
            $params['status']  = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where[]             = 'o.category = :category';
            $params['category']  = $filters['category'];
        }

        if (!empty($filters['requiredLevel'])) {
            $where[]                = 'o.requiredLevel = :requiredLevel';
            $params['requiredLevel'] = $filters['requiredLevel'];
        }

        if (!empty($filters['search'])) {
            $where[]           = '(o.title LIKE :search OR o.description LIKE :search)';
            $params['search']  = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT 
                    o.opportunityId,
                    o.managerId,
                    o.title,
                    o.description,
                    o.type,
                    o.category,
                    o.deadline,
                    o.requiredLevel,
                    o.status,
                    o.createdAt,
                    u.fullName AS managerName,
                    COUNT(a.applicationId) AS applicationCount
                FROM {$this->table} o
                LEFT JOIN User u ON o.managerId = u.userId
                LEFT JOIN Application a ON o.opportunityId = a.opportunityId
                WHERE {$whereClause}
                GROUP BY o.opportunityId
                ORDER BY o.createdAt DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

//what the backoffice will see

    public function getById(int $id): array|false {
        $sql = "SELECT 
                    o.*,
                    u.fullName AS managerName,
                    COUNT(a.applicationId) AS applicationCount
                FROM {$this->table} o
                LEFT JOIN User u ON o.managerId = u.userId
                LEFT JOIN Application a ON o.opportunityId = a.opportunityId
                WHERE o.opportunityId = :id
                GROUP BY o.opportunityId";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

//what the frontoffice will see

    public function getPublished(array $filters = []): array {
        $filters['status'] = 'published';
        return $this->getAll($filters);
    }

//create

    public function create(array $data): array {
        $errors = $this->validate($data); //controle de saisie
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $sql = "INSERT INTO {$this->table} 
            (managerId, title, description, type, category, deadline, requiredLevel, status)
         VALUES 
            (:managerId, :title, :description, :type, :category, :deadline, :requiredLevel, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'managerId'     => $data['managerId'],
            'title'         => trim($data['title']),
            'description'   => trim($data['description']),
            'type'          => $data['type'],
            'category'      => $data['category'],
            'deadline'      => $data['deadline'],
            'requiredLevel' => $data['requiredLevel'],
            'status'        => $data['status'] ?? 'draft',
        ]);

        $newId = (int)$this->db->lastInsertId();
        return ['success' => true, 'opportunityId' => $newId, 'message' => 'Opportunity created.'];
    }

   //update

    public function update(int $id, array $data): array {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Opportunity not found.'];
        }

        $errors = $this->validate($data, false);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $sql = "UPDATE {$this->table} SET
                    title         = :title,
                    description   = :description,
                    type          = :type,
                    category      = :category,
                    deadline      = :deadline,
                    requiredLevel = :requiredLevel,
                    status        = :status
                WHERE opportunityId = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'title'         => trim($data['title']),
            'description'   => trim($data['description']),
            'type'          => $data['type'],
            'category'      => $data['category'],
            'deadline'      => $data['deadline'],
            'requiredLevel' => $data['requiredLevel'],
            'status'        => $data['status'],
            'id'            => $id,
        ]);

        return ['success' => true, 'message' => 'Opportunity updated.'];
    }

    //delete

    public function delete(int $id): array {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Opportunity not found.'];
        }

        // Remove linked applications first (FK constraint)
        $this->db->prepare("DELETE FROM Application WHERE opportunityId = :id")->execute(['id' => $id]);

        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE opportunityId = :id");
        $stmt->execute(['id' => $id]);

        return ['success' => true, 'message' => 'Opportunity deleted.'];
    }

    //verifie les erreurs

    private function validate(array $data, bool $requireAll = true): array {
        $errors = [];

        if ($requireAll || isset($data['title'])) {
            if (empty(trim($data['title'] ?? ''))) {
                $errors[] = 'Title is required.';
            } elseif (strlen($data['title']) > 255) {
                $errors[] = 'Title must be under 255 characters.';
            }
        }

        if ($requireAll || isset($data['description'])) {
            if (empty(trim($data['description'] ?? ''))) {
                $errors[] = 'Description is required.';
            }
        }

        if ($requireAll || isset($data['type'])) {
            if (!in_array($data['type'] ?? '', $this->validTypes)) {
                $errors[] = 'Type must be one of: ' . implode(', ', $this->validTypes);
            }
        }

        if ($requireAll || isset($data['category'])) {
            if (!in_array($data['category'] ?? '', $this->validCategories)) {
                $errors[] = 'Category must be one of: ' . implode(', ', $this->validCategories);
            }
        }

        if ($requireAll || isset($data['requiredLevel'])) {
            if (!in_array($data['requiredLevel'] ?? '', $this->validLevels)) {
                $errors[] = 'Level must be one of: ' . implode(', ', $this->validLevels);
            }
        }

        if ($requireAll || isset($data['deadline'])) {
            $d = $data['deadline'] ?? '';
            if (empty($d) || strtotime($d) === false) {
                $errors[] = 'A valid deadline date is required.';
            } elseif (strtotime($d) < strtotime('today')) {
                $errors[] = 'Deadline cannot be in the past.';
            }
        }

        if (isset($data['status']) && !in_array($data['status'], $this->validStatuses)) {
            $errors[] = 'Status must be one of: ' . implode(', ', $this->validStatuses);
        }

        if ($requireAll && empty($data['managerId'])) {
            $errors[] = 'Manager ID is required.';
        }

        return $errors;
    }
}