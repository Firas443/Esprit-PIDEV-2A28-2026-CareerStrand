<?php
class OpportunitySkill {
    private PDO $db;

    // Database mapping
    private const TABLE        = 'opportunity_skill';
    private const PK           = 'id';
    private const FK_OPP       = 'opportunityId';
    private const COL_SKILL    = 'skillName';
    private const COL_LEVEL    = 'requiredLevel';
    private const COL_WEIGHT   = 'weight';
    private const COL_PRIMARY  = 'isPrimary';

    // Join table
    private const TABLE_OPP    = 'Opportunity';
    private const OPP_TITLE    = 'title';

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getDb(): PDO {
        return $this->db;
    }

    public function getAll(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['opportunityId'])) {
            $where[]                 = 'os.' . self::FK_OPP . ' = :opportunityId';
            $params['opportunityId'] = (int)$filters['opportunityId'];
        }
        if (!empty($filters['skillName'])) {
            $where[]              = 'os.' . self::COL_SKILL . ' LIKE :skillName';
            $params['skillName']  = '%' . $filters['skillName'] . '%';
        }
        if (isset($filters['isPrimary'])) {
            $where[]             = 'os.' . self::COL_PRIMARY . ' = :isPrimary';
            $params['isPrimary'] = (int)$filters['isPrimary'];
        }

        $sql = "SELECT
                    os." . self::PK . ",
                    os." . self::FK_OPP . ",
                    os." . self::COL_SKILL . ",
                    os." . self::COL_LEVEL . ",
                    os." . self::COL_WEIGHT . ",
                    os." . self::COL_PRIMARY . ",
                    o." . self::OPP_TITLE . " AS opportunityTitle
                FROM " . self::TABLE . " os
                LEFT JOIN " . self::TABLE_OPP . " o ON os." . self::FK_OPP . " = o." . self::FK_OPP . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY os." . self::COL_PRIMARY . " DESC, os." . self::COL_WEIGHT . " DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $sql = "SELECT
                    os.*,
                    o." . self::OPP_TITLE . " AS opportunityTitle
                FROM " . self::TABLE . " os
                LEFT JOIN " . self::TABLE_OPP . " o ON os." . self::FK_OPP . " = o." . self::FK_OPP . "
                WHERE os." . self::PK . " = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getByOpportunityId(int $opportunityId): array {
        return $this->getAll(['opportunityId' => $opportunityId]);
    }

    public function ensureAutoIncrementId(): void {
        $sql = "ALTER TABLE " . self::TABLE . " MODIFY " . self::PK . " int(11) NOT NULL AUTO_INCREMENT";
        $this->db->exec($sql);
    }

    public function validate(array $data, bool $requireAll = true): array {
        $errors = [];

        if ($requireAll || isset($data[self::FK_OPP])) {
            if (empty($data[self::FK_OPP]) || !is_numeric($data[self::FK_OPP]))
                $errors[] = 'A valid opportunity ID is required.';
        }
        if ($requireAll || isset($data[self::COL_SKILL])) {
            if (empty(trim($data[self::COL_SKILL] ?? '')))
                $errors[] = 'Skill name is required.';
            elseif (strlen($data[self::COL_SKILL]) > 100)
                $errors[] = 'Skill name must be under 100 characters.';
        }
        if ($requireAll || isset($data[self::COL_LEVEL])) {
            if (!isset($data[self::COL_LEVEL]) || !is_numeric($data[self::COL_LEVEL]) || (int)$data[self::COL_LEVEL] < 1)
                $errors[] = 'Required level must be a positive integer.';
        }
        if (isset($data[self::COL_WEIGHT]) && (!is_numeric($data[self::COL_WEIGHT]) || (float)$data[self::COL_WEIGHT] < 0))
            $errors[] = 'Weight must be a non-negative number.';

        return $errors;
    }
}
