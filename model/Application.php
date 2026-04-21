<?php
class Application {
    private PDO $db;

    // Database mapping
    private const TABLE           = 'Application';
    private const PK              = 'applicationId';
    private const FK_USER         = 'userId';
    private const FK_OPPORTUNITY  = 'opportunityId';
    private const COL_MOTIVATION  = 'motivation';
    private const COL_SCORE       = 'compatibilityScore';
    private const COL_STATUS      = 'status';
    private const COL_APPLIED     = 'appliedAt';

    // Join tables
    private const TABLE_USER      = 'User';
    private const TABLE_OPP       = 'Opportunity';
    private const USER_PK         = 'userId';
    private const USER_NAME       = 'fullName';
    private const OPP_PK          = 'opportunityId';
    private const OPP_TITLE       = 'title';
    private const OPP_TYPE        = 'type';

    private const VALID_STATUSES  = ['pending', 'accepted', 'rejected'];

    public function __construct(PDO $db) {
        $this->db = $db;
    }
public function getDb(): PDO {
    return $this->db;
}
    public function getById(int $id): array|false {
        $sql = "SELECT 
                    a.*,
                    u." . self::USER_NAME . " AS applicantName,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_TYPE  . " AS opportunityType
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_OPP  . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE a." . self::PK . " = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getByUserId(int $userId): array {
        $sql = "SELECT 
                    a.*,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_TYPE  . " AS opportunityType
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_OPP . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE a." . self::FK_USER . " = :userId
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function getByOpportunityId(int $opportunityId): array {
        $sql = "SELECT 
                    a.*,
                    u." . self::USER_NAME . " AS applicantName
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER . " = u." . self::USER_PK . "
                WHERE a." . self::FK_OPPORTUNITY . " = :opportunityId
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['opportunityId' => $opportunityId]);
        return $stmt->fetchAll();
    }

    public function getByStatus(string $status): array {
        $sql = "SELECT 
                    a.*,
                    u." . self::USER_NAME . " AS applicantName,
                    o." . self::OPP_TITLE . " AS opportunityTitle
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_OPP  . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE a." . self::COL_STATUS . " = :status
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function getWithFilters(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'a.' . self::COL_STATUS . ' = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]          = 'u.' . self::USER_NAME . ' LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters[self::FK_USER])) {
            $where[]               = 'a.' . self::FK_USER . ' = :userId';
            $params['userId']      = $filters[self::FK_USER];
        }
        if (!empty($filters[self::FK_OPPORTUNITY])) {
            $where[]                    = 'a.' . self::FK_OPPORTUNITY . ' = :opportunityId';
            $params['opportunityId']    = $filters[self::FK_OPPORTUNITY];
        }

        $sql = "SELECT 
                    a.*,
                    u." . self::USER_NAME . " AS applicantName,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_TYPE  . " AS opportunityType
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER        . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_OPP  . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStatusCounts(): array {
        $sql  = "SELECT " . self::COL_STATUS . ", COUNT(*) as count FROM " . self::TABLE . " GROUP BY " . self::COL_STATUS;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows   = $stmt->fetchAll();
        $counts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            if (isset($counts[$row[self::COL_STATUS]]))
                $counts[$row[self::COL_STATUS]] = (int)$row['count'];
        }
        return $counts;
    }

    public function validate(array $data): array {
        $errors = [];
        if (empty($data[self::FK_USER]))        $errors[] = 'User ID is required.';
        if (empty($data[self::FK_OPPORTUNITY])) $errors[] = 'Opportunity ID is required.';
        if (empty(trim($data[self::COL_MOTIVATION] ?? ''))) $errors[] = 'Motivation is required.';
        return $errors;
    }
   

}