<?php
class Opportunity {
    private PDO $db;

    // Database mapping
    private const TABLE          = 'Opportunity';
    private const PK             = 'opportunityId';
    private const FK_MANAGER     = 'managerId';
    private const COL_TITLE      = 'title';
    private const COL_DESC       = 'description';
    private const COL_TYPE       = 'type';
    private const COL_CATEGORY   = 'category';
    private const COL_DEADLINE   = 'deadline';
    private const COL_LEVEL      = 'requiredLevel';
    private const COL_STATUS     = 'status';
    private const COL_CREATED    = 'createdAt';

    // Join tables
    private const TABLE_USER     = 'User';
    private const TABLE_APP      = 'Application';
    private const USER_PK        = 'userId';
    private const USER_NAME      = 'fullName';
    private const APP_PK         = 'applicationId';

    // Allowed values
    private const VALID_TYPES      = ['internship', 'job', 'freelance', 'volunteer'];
    private const VALID_CATEGORIES = ['Technical', 'Creativity', 'Business', 'Communication', 'Leadership'];
    private const VALID_LEVELS     = ['Beginner', 'Intermediate', 'Advanced'];
    private const VALID_STATUSES   = ['published', 'draft', 'archived'];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getDb(): PDO {
        return $this->db;
    }

    public function getAll(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'o.' . self::COL_STATUS . ' = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[]            = 'o.' . self::COL_CATEGORY . ' = :category';
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['requiredLevel'])) {
            $where[]                 = 'o.' . self::COL_LEVEL . ' = :requiredLevel';
            $params['requiredLevel'] = $filters['requiredLevel'];
        }
        if (!empty($filters['search'])) {
            $where[]          = '(o.' . self::COL_TITLE . ' LIKE :search OR o.' . self::COL_DESC . ' LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT 
                    o." . self::PK . ",
                    o." . self::FK_MANAGER . ",
                    o." . self::COL_TITLE . ",
                    o." . self::COL_DESC . ",
                    o." . self::COL_TYPE . ",
                    o." . self::COL_CATEGORY . ",
                    o." . self::COL_DEADLINE . ",
                    o." . self::COL_LEVEL . ",
                    o." . self::COL_STATUS . ",
                    o." . self::COL_CREATED . ",
                    u." . self::USER_NAME . " AS managerName,
                    COUNT(a." . self::APP_PK . ") AS applicationCount
                FROM " . self::TABLE . " o
                LEFT JOIN " . self::TABLE_USER . " u ON o." . self::FK_MANAGER . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_APP . " a ON o." . self::PK . " = a." . self::PK . "
                WHERE " . implode(' AND ', $where) . "
                GROUP BY o." . self::PK . "
                ORDER BY o." . self::COL_CREATED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $sql = "SELECT 
                    o.*,
                    u." . self::USER_NAME . " AS managerName,
                    COUNT(a." . self::APP_PK . ") AS applicationCount
                FROM " . self::TABLE . " o
                LEFT JOIN " . self::TABLE_USER . " u ON o." . self::FK_MANAGER . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_APP . " a ON o." . self::PK . " = a." . self::PK . "
                WHERE o." . self::PK . " = :id
                GROUP BY o." . self::PK;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getPublished(array $filters = []): array {
        $filters['status'] = 'published';
        return $this->getAll($filters);
    }

    public function validate(array $data, bool $requireAll = true): array {
        $errors = [];

        if ($requireAll || isset($data[self::COL_TITLE])) {
            if (empty(trim($data[self::COL_TITLE] ?? '')))
                $errors[] = 'Title is required.';
            elseif (strlen($data[self::COL_TITLE]) > 255)
                $errors[] = 'Title must be under 255 characters.';
        }
        if ($requireAll || isset($data[self::COL_DESC])) {
            if (empty(trim($data[self::COL_DESC] ?? '')))
                $errors[] = 'Description is required.';
        }
        if ($requireAll || isset($data[self::COL_TYPE])) {
            if (!in_array($data[self::COL_TYPE] ?? '', self::VALID_TYPES))
                $errors[] = 'Type must be one of: ' . implode(', ', self::VALID_TYPES);
        }
        if ($requireAll || isset($data[self::COL_CATEGORY])) {
            if (!in_array($data[self::COL_CATEGORY] ?? '', self::VALID_CATEGORIES))
                $errors[] = 'Category must be one of: ' . implode(', ', self::VALID_CATEGORIES);
        }
        if ($requireAll || isset($data[self::COL_LEVEL])) {
            if (!in_array($data[self::COL_LEVEL] ?? '', self::VALID_LEVELS))
                $errors[] = 'Level must be one of: ' . implode(', ', self::VALID_LEVELS);
        }
        if ($requireAll || isset($data[self::COL_DEADLINE])) {
            $d = $data[self::COL_DEADLINE] ?? '';
            if (empty($d) || strtotime($d) === false)
                $errors[] = 'A valid deadline date is required.';
            elseif (strtotime($d) < strtotime('today'))
                $errors[] = 'Deadline cannot be in the past.';
        }
        if (isset($data[self::COL_STATUS]) && !in_array($data[self::COL_STATUS], self::VALID_STATUSES))
            $errors[] = 'Status must be one of: ' . implode(', ', self::VALID_STATUSES);
        if ($requireAll && empty($data[self::FK_MANAGER]))
            $errors[] = 'Manager ID is required.';

        return $errors;
    }
    public function titleExists(string $title, int $excludeId = 0): bool {
    $sql  = "SELECT COUNT(*) FROM " . self::TABLE . " 
             WHERE " . self::COL_TITLE . " = :title 
             AND " . self::COL_PK . " != :excludeId
             AND " . self::COL_STATUS . " != 'archived'";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['title' => $title, 'excludeId' => $excludeId]);
    return (int)$stmt->fetchColumn() > 0;
}
}