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
    private const COL_NOTES       = 'applicationNotes';
    private const COL_TIMELINE    = 'timeline';

    // Join tables
    private const TABLE_USER      = 'Users';
    private const TABLE_OPP       = 'Opportunity';
    private const USER_PK         = 'userId';
    private const USER_NAME       = 'fullName';
    private const OPP_PK          = 'opportunityId';
    private const OPP_TITLE       = 'title';
    private const OPP_TYPE        = 'type';
    private const OPP_DESC        = 'description';

    private const VALID_STATUSES  = ['saved', 'applied', 'shortlisted', 'interview', 'accepted', 'rejected'];

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ensureTrackingColumns();
    }
    public function getDb(): PDO {
    return $this->db;
}

    public function ensureAutoIncrementId(): void {
        $sql = "ALTER TABLE " . self::TABLE . " MODIFY " . self::PK . " int(11) NOT NULL AUTO_INCREMENT";
        $this->db->exec($sql);
    }

    public function ensureTrackingColumns(): void {
        $this->ensureColumn(self::COL_NOTES, "TEXT NULL");
        $this->ensureColumn(self::COL_TIMELINE, "LONGTEXT NULL");
    }

    private function ensureColumn(string $column, string $definition): void {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '" . self::TABLE . "'
                  AND COLUMN_NAME = :column";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['column' => $column]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $this->db->exec("ALTER TABLE " . self::TABLE . " ADD COLUMN " . $column . " " . $definition);
    }

    private function normalizeStatusValue(?string $status): string {
        $status = strtolower(trim((string) $status));
        if ($status === 'pending') {
            return 'applied';
        }
        if (in_array($status, self::VALID_STATUSES, true)) {
            return $status;
        }
        return 'applied';
    }

    private function decodeTimeline($raw): array {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function buildTimeline(array $row, ?string $actor = null, ?string $note = null): array {
        $timeline = $this->decodeTimeline($row[self::COL_TIMELINE] ?? null);
        if (empty($timeline)) {
            $timeline[] = [
                'stage' => 'applied',
                'label' => 'Applied',
                'note' => 'Application submitted.',
                'at' => $row[self::COL_APPLIED] ?? date('Y-m-d H:i:s'),
                'actor' => 'System',
            ];
        }

        if ($actor !== null || $note !== null) {
            $stage = $this->normalizeStatusValue($row[self::COL_STATUS] ?? 'applied');
            $timeline[] = [
                'stage' => $stage,
                'label' => ucfirst($stage),
                'note' => trim((string) ($note ?? 'Status updated.')) ?: 'Status updated.',
                'at' => date('Y-m-d H:i:s'),
                'actor' => trim((string) ($actor ?? 'System')) ?: 'System',
            ];
        }

        return array_values($timeline);
    }

    public function timelineJson(array $row, ?string $actor = null, ?string $note = null): string {
        return json_encode($this->buildTimeline($row, $actor, $note), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function normalizeRow(array $row): array {
        $row[self::COL_STATUS] = $this->normalizeStatusValue($row[self::COL_STATUS] ?? null);
        $row[self::COL_NOTES]   = (string) ($row[self::COL_NOTES] ?? '');
        $row[self::COL_TIMELINE] = $this->decodeTimeline($row[self::COL_TIMELINE] ?? null);
        return $row;
    }

    public function getById(int $id): array|false {
        $sql = "SELECT DISTINCT
                    a.*,
                    u." . self::USER_NAME . " AS applicantName,
                    u.email AS applicantEmail,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_TYPE  . " AS opportunityType,
                    o." . self::OPP_DESC  . " AS opportunityDescription
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_OPP  . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE a." . self::PK . " = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : false;
    }

    public function getByUserId(int $userId): array {
        $sql = "SELECT DISTINCT
                    a.*,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_TYPE  . " AS opportunityType
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_OPP . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE a." . self::FK_USER . " = :userId
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return array_map(fn(array $row) => $this->normalizeRow($row), $stmt->fetchAll());
    }

    public function getByOpportunityId(int $opportunityId): array {
        $sql = "SELECT DISTINCT
                    a.*,
                    u." . self::USER_NAME . " AS applicantName
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER . " = u." . self::USER_PK . "
                WHERE a." . self::FK_OPPORTUNITY . " = :opportunityId
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['opportunityId' => $opportunityId]);
        return array_map(fn(array $row) => $this->normalizeRow($row), $stmt->fetchAll());
    }

    public function getByStatus(string $status): array {
        $sql = "SELECT DISTINCT
                    a.*,
                    u." . self::USER_NAME . " AS applicantName,
                    u.email AS applicantEmail,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_DESC  . " AS opportunityDescription
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_OPP  . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE a." . self::COL_STATUS . " = :status
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status]);
        return array_map(fn(array $row) => $this->normalizeRow($row), $stmt->fetchAll());
    }

    public function getWithFilters(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $status = $this->normalizeStatusValue((string) $filters['status']);
            if ($status === 'applied') {
                $where[] = "(LOWER(COALESCE(a." . self::COL_STATUS . ", '')) IN ('applied', 'pending'))";
            } else {
                $where[]          = 'LOWER(COALESCE(a.' . self::COL_STATUS . ", '')) = :status";
                $params['status'] = $status;
            }
        }
        if (!empty($filters['search'])) {
            $where[]          = 'u.' . self::USER_NAME . ' LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['searchPosition'])) {
            $where[]                    = 'o.' . self::OPP_TITLE . ' LIKE :searchPosition';
            $params['searchPosition']   = '%' . $filters['searchPosition'] . '%';
        }
        if (!empty($filters[self::FK_USER])) {
            $where[]               = 'a.' . self::FK_USER . ' = :userId';
            $params['userId']      = $filters[self::FK_USER];
        }
        if (!empty($filters[self::FK_OPPORTUNITY])) {
            $where[]                    = 'a.' . self::FK_OPPORTUNITY . ' = :opportunityId';
            $params['opportunityId']    = $filters[self::FK_OPPORTUNITY];
        }

        $sql = "SELECT DISTINCT
                    a.*,
                    u." . self::USER_NAME . " AS applicantName,
                    o." . self::OPP_TITLE . " AS opportunityTitle,
                    o." . self::OPP_TYPE  . " AS opportunityType,
                    o." . self::OPP_DESC  . " AS opportunityDescription
                FROM " . self::TABLE . " a
                LEFT JOIN " . self::TABLE_USER . " u ON a." . self::FK_USER        . " = u." . self::USER_PK . "
                LEFT JOIN " . self::TABLE_OPP  . " o ON a." . self::FK_OPPORTUNITY . " = o." . self::OPP_PK . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a." . self::COL_APPLIED . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(fn(array $row) => $this->normalizeRow($row), $stmt->fetchAll());
    }

    public function getStatusCounts(): array {
        $sql  = "SELECT " . self::COL_STATUS . ", COUNT(*) as count FROM " . self::TABLE . " GROUP BY " . self::COL_STATUS;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows   = $stmt->fetchAll();
        $counts = ['saved' => 0, 'applied' => 0, 'shortlisted' => 0, 'interview' => 0, 'accepted' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $status = $this->normalizeStatusValue((string) ($row[self::COL_STATUS] ?? ''));
            if (isset($counts[$status])) {
                $counts[$status] = (int)$row['count'];
            }
        }
        return $counts;
    }

    public function validate(array $data): array {
        $errors = [];
        if (empty($data[self::FK_USER]) || (int)$data[self::FK_USER] <= 0) $errors[] = 'User ID is required.';
        if (empty($data[self::FK_OPPORTUNITY]) || (int)$data[self::FK_OPPORTUNITY] <= 0) $errors[] = 'Opportunity ID is required.';
        if (empty(trim($data[self::COL_MOTIVATION] ?? ''))) $errors[] = 'Motivation is required.';
        return $errors;
    }
   

}
