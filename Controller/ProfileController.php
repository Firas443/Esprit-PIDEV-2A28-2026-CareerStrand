<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Profile.php';
require_once __DIR__ . '/../Model/UserSkill.php';

class ProfileController
{
    private PDO $pdo;
    private const PROFILE_PHOTO_DIR = __DIR__ . '/../uploads/profile-photos/';

    private const THEME_OPTIONS = [
        'midnight' => [
            'label' => 'Midnight',
            'description' => 'Deep navy with a sharp accent and high-contrast finishes.',
            'portfolioPrimary' => '#8fb1ff',
            'portfolioSecondary' => '#ff7a50',
            'cvPrimary' => '#1f1b1b',
            'cvSecondary' => '#efe7df',
        ],
        'aurora' => [
            'label' => 'Aurora',
            'description' => 'Electric blue and violet with a luminous glow.',
            'portfolioPrimary' => '#4de1ff',
            'portfolioSecondary' => '#9f7bff',
            'cvPrimary' => '#15223b',
            'cvSecondary' => '#eef4ff',
        ],
        'ember' => [
            'label' => 'Ember',
            'description' => 'Warm rust, coral and gold for a premium editorial feel.',
            'portfolioPrimary' => '#ff8b5c',
            'portfolioSecondary' => '#ffcc6b',
            'cvPrimary' => '#2b1f1d',
            'cvSecondary' => '#f5ede6',
        ],
        'graphite' => [
            'label' => 'Graphite',
            'description' => 'Neutral greys with a calm monochrome print layout.',
            'portfolioPrimary' => '#9da6b8',
            'portfolioSecondary' => '#6d7482',
            'cvPrimary' => '#1f2329',
            'cvSecondary' => '#e6e2de',
        ],
    ];

    public function __construct()
    {
        $this->pdo = config::getConnexion();
        $this->ensureProfileSchema();
    }

    private function ensureProfileSchema(): void
    {
        $columns = [
            'careerGoal' => "ALTER TABLE Profile ADD COLUMN careerGoal VARCHAR(255) DEFAULT NULL",
            'portfolioTheme' => "ALTER TABLE Profile ADD COLUMN portfolioTheme VARCHAR(50) NOT NULL DEFAULT 'midnight'",
            'portfolioPrimaryColor' => "ALTER TABLE Profile ADD COLUMN portfolioPrimaryColor VARCHAR(20) NOT NULL DEFAULT '#8fb1ff'",
            'portfolioSecondaryColor' => "ALTER TABLE Profile ADD COLUMN portfolioSecondaryColor VARCHAR(20) NOT NULL DEFAULT '#ff7a50'",
            'cvTheme' => "ALTER TABLE Profile ADD COLUMN cvTheme VARCHAR(50) NOT NULL DEFAULT 'graphite'",
            'cvPrimaryColor' => "ALTER TABLE Profile ADD COLUMN cvPrimaryColor VARCHAR(20) NOT NULL DEFAULT '#1f1b1b'",
            'cvSecondaryColor' => "ALTER TABLE Profile ADD COLUMN cvSecondaryColor VARCHAR(20) NOT NULL DEFAULT '#efe7df'",
            'skillCatalogId' => "ALTER TABLE UserSkill ADD COLUMN skillCatalogId INT(11) DEFAULT NULL",
            'approvalStatus' => "ALTER TABLE UserSkill ADD COLUMN approvalStatus VARCHAR(20) NOT NULL DEFAULT 'approved'",
            'reviewedBy' => "ALTER TABLE UserSkill ADD COLUMN reviewedBy INT(11) DEFAULT NULL",
            'reviewedAt' => "ALTER TABLE UserSkill ADD COLUMN reviewedAt DATETIME DEFAULT NULL",
            'reviewNote' => "ALTER TABLE UserSkill ADD COLUMN reviewNote VARCHAR(255) DEFAULT NULL",
        ];

        foreach ($columns as $column => $sql) {
            $table = in_array($column, ['careerGoal', 'portfolioTheme', 'portfolioPrimaryColor', 'portfolioSecondaryColor', 'cvTheme', 'cvPrimaryColor', 'cvSecondaryColor'], true)
                ? 'Profile'
                : 'UserSkill';

            if (!$this->columnExists($table, $column)) {
                try {
                    $this->pdo->exec($sql);
                } catch (PDOException $e) {
                    // Ignore environments where the column already exists or schema access is restricted.
                }
            }
        }

        $this->ensureSkillCatalogSchema();
    }

    private function ensureSkillCatalogSchema(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS skill_catalog (
                    skillCatalogId INT(11) NOT NULL AUTO_INCREMENT,
                    skillName VARCHAR(120) NOT NULL,
                    normalizedName VARCHAR(120) NOT NULL,
                    category VARCHAR(100) DEFAULT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    isActive TINYINT(1) NOT NULL DEFAULT 1,
                    createdBy INT(11) DEFAULT NULL,
                    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (skillCatalogId),
                    UNIQUE KEY uq_skill_catalog_normalized (normalizedName)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (PDOException $e) {
            // Ignore if the database user cannot create tables.
        }

        $this->seedSkillCatalogIfEmpty();
    }

    private function seedSkillCatalogIfEmpty(): void
    {
        try {
            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM skill_catalog")->fetchColumn();
            if ($count > 0) {
                return;
            }

            $seedSkills = [
                ['HTML', 'Frontend'],
                ['CSS', 'Frontend'],
                ['JavaScript', 'Frontend'],
                ['React', 'Frontend'],
                ['PHP', 'Backend'],
                ['Laravel', 'Backend'],
                ['SQL', 'Database'],
                ['Database Design', 'Database'],
                ['UI/UX Design', 'Design'],
                ['Figma', 'Design'],
                ['Communication', 'Communication'],
                ['Business Strategy', 'Business'],
                ['Project Management', 'Leadership'],
                ['Data Analysis', 'Data'],
                ['AI Literacy', 'Data'],
            ];

            $stmt = $this->pdo->prepare(
                "INSERT INTO skill_catalog (skillName, normalizedName, category, description, isActive, createdBy, createdAt)
                 VALUES (:skillName, :normalizedName, :category, :description, 1, NULL, NOW())"
            );

            foreach ($seedSkills as [$skillName, $category]) {
                $stmt->execute([
                    'skillName' => $skillName,
                    'normalizedName' => self::normalizeSkillNameKey($skillName),
                    'category' => $category,
                    'description' => null,
                ]);
            }
        } catch (PDOException $e) {
            // Ignore schema/bootstrap issues; the library can still be populated manually later.
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── READ ─────────────────────────────────────────────

    public function getByUserId(int $userId): ?Profile
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Profile WHERE userId = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ? $this->rowToProfile($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getSkillsByUserId(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM UserSkill
                 WHERE userId = ?
                   AND LOWER(COALESCE(approvalStatus, 'approved')) = 'approved'
                 ORDER BY COALESCE(reviewedAt, validatedAt, NOW()) DESC, userSkillId DESC"
            );
            $stmt->execute([$userId]);
            $skills = [];
            while ($row = $stmt->fetch()) {
                $skills[] = $this->rowToUserSkill($row);
            }
            return $skills;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getSkillRequestsByUserId(int $userId, ?array $statuses = null): array
    {
        try {
            $sql = "SELECT * FROM UserSkill WHERE userId = :userId";
            $params = [':userId' => $userId];

            if (!empty($statuses)) {
                $placeholders = [];
                foreach (array_values($statuses) as $index => $status) {
                    $placeholder = ':status' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = strtolower(trim((string) $status));
                }
                $sql .= ' AND LOWER(COALESCE(approvalStatus, \'approved\')) IN (' . implode(', ', $placeholders) . ')';
            }

            $sql .= ' ORDER BY COALESCE(reviewedAt, validatedAt, NOW()) DESC, userSkillId DESC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $skills = [];
            while ($row = $stmt->fetch()) {
                $skills[] = $this->rowToUserSkill($row);
            }
            return $skills;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getPendingSkillRequests(int $limit = 50): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT us.*, u.fullName, u.email
                 FROM UserSkill us
                 LEFT JOIN Users u ON u.userId = us.userId
                 WHERE LOWER(COALESCE(us.approvalStatus, 'approved')) = 'pending'
                 ORDER BY us.userSkillId DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getSkillCatalog(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];

            if (isset($filters['active'])) {
                $where[] = 'isActive = :isActive';
                $params[':isActive'] = (int) !empty($filters['active']);
            }
            if (!empty($filters['search'])) {
                $where[] = '(skillName LIKE :search OR category LIKE :search)';
                $params[':search'] = '%' . trim((string) $filters['search']) . '%';
            }

            $sql = "SELECT * FROM skill_catalog";
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY skillName ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── CREATE OR UPDATE PROFILE ─────────────────────────

    public function createOrUpdate(int $userId, array $data, array $files = []): array
    {
        $careerGoal = trim($data['careerGoal'] ?? '');
        $portfolioTheme = self::normalizeTheme((string) ($data['portfolioTheme'] ?? 'midnight'));
        $cvTheme = self::normalizeTheme((string) ($data['cvTheme'] ?? 'graphite'));
        $portfolioPalette = self::themePalette($portfolioTheme);
        $cvPalette = self::themePalette($cvTheme);
        $portfolioPrimaryColor = self::normalizeColor((string) ($data['portfolioPrimaryColor'] ?? ''), $portfolioPalette['portfolioPrimary']);
        $portfolioSecondaryColor = self::normalizeColor((string) ($data['portfolioSecondaryColor'] ?? ''), $portfolioPalette['portfolioSecondary']);
        $cvPrimaryColor = self::normalizeColor((string) ($data['cvPrimaryColor'] ?? ''), $cvPalette['cvPrimary']);
        $cvSecondaryColor = self::normalizeColor((string) ($data['cvSecondaryColor'] ?? ''), $cvPalette['cvSecondary']);
        $existingProfile = $this->getByUserId($userId);
        $photoResolution = $this->resolveProfilePhoto(
            $userId,
            trim((string) ($data['photoUrl'] ?? '')),
            $files['photoFile'] ?? null,
            $existingProfile ? $existingProfile->getPhotoUrl() : ''
        );

        if (!empty($photoResolution['error'])) {
            return ['success' => false, 'errors' => ['photoUrl' => $photoResolution['error']]];
        }
        $photoUrl = (string) ($photoResolution['photoUrl'] ?? '');

        $profile = new Profile(
            trim($data['bio']         ?? ''),
            $photoUrl,
            trim($data['location']    ?? ''),
            trim($data['preferences'] ?? ''),
            0,
            'Starter',
            $userId,
            $careerGoal,
            $portfolioTheme,
            $portfolioPrimaryColor,
            $portfolioSecondaryColor,
            $cvTheme,
            $cvPrimaryColor,
            $cvSecondaryColor
        );

        $score = $this->calculateScore($userId, $data);
        $level = $this->calculateLevel($score);
        $profile->setCompletionScore($score);
        $profile->setLevel($level);

        try {
            $existing = $this->getByUserId($userId);
            if ($existing) {
                $stmt = $this->pdo->prepare(
                    "UPDATE Profile
                     SET bio             = ?,
                         photoUrl        = ?,
                        location        = ?,
                        preferences     = ?,
                        careerGoal      = ?,
                        portfolioTheme  = ?,
                        portfolioPrimaryColor = ?,
                        portfolioSecondaryColor = ?,
                        cvTheme         = ?,
                        cvPrimaryColor  = ?,
                        cvSecondaryColor = ?,
                        completionScore = ?,
                        level           = ?
                     WHERE userId = ?"
                );
                $stmt->execute([
                    $profile->getBio(),
                    $profile->getPhotoUrl(),
                    $profile->getLocation(),
                    $profile->getPreferences(),
                    $profile->getCareerGoal(),
                    $profile->getPortfolioTheme(),
                    $profile->getPortfolioPrimaryColor(),
                    $profile->getPortfolioSecondaryColor(),
                    $profile->getCvTheme(),
                    $profile->getCvPrimaryColor(),
                    $profile->getCvSecondaryColor(),
                    $profile->getCompletionScore(),
                    $profile->getLevel(),
                    $userId,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO Profile (
                        userId, bio, photoUrl, location, preferences, careerGoal,
                        portfolioTheme, portfolioPrimaryColor, portfolioSecondaryColor,
                        cvTheme, cvPrimaryColor, cvSecondaryColor,
                        completionScore, level
                    )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    $profile->getBio(),
                    $profile->getPhotoUrl(),
                    $profile->getLocation(),
                    $profile->getPreferences(),
                    $profile->getCareerGoal(),
                    $profile->getPortfolioTheme(),
                    $profile->getPortfolioPrimaryColor(),
                    $profile->getPortfolioSecondaryColor(),
                    $profile->getCvTheme(),
                    $profile->getCvPrimaryColor(),
                    $profile->getCvSecondaryColor(),
                    $profile->getCompletionScore(),
                    $profile->getLevel(),
                ]);
            }
            return ['success' => true, 'score' => $score, 'level' => $level];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save profile.']];
        }
    }

    // ── SCORE / LEVEL ─────────────────────────────────────

    public function calculateScore(int $userId, array $profileData = []): int
    {
        $score = 0;
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE userId = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                if (!empty($user['fullName'])) $score += 10;
                if (!empty($user['email']))    $score += 10;
            }
            if (!empty($profileData['bio']))         $score += 15;
            if (!empty($profileData['photoUrl']))    $score += 15;
            if (!empty($profileData['location']))    $score += 10;
            if (!empty($profileData['preferences'])) $score += 10;
            if (!empty($profileData['careerGoal']))  $score += 10;

            $stmt2 = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM UserSkill
                 WHERE userId = ?
                   AND LOWER(COALESCE(approvalStatus, 'approved')) = 'approved'"
            );
            $stmt2->execute([$userId]);
            $skillCount = (int) $stmt2->fetchColumn();
            if ($skillCount >= 1) $score += 10;
            if ($skillCount >= 3) $score += 10;
            if ($skillCount >= 5) $score += 10;
        } catch (PDOException $e) {
            // return partial score
        }
        return min($score, 100);
    }

    public function calculateLevel(int $score): string
    {
        if ($score >= 80) return 'Expert';
        if ($score >= 60) return 'Advanced';
        if ($score >= 40) return 'Intermediate';
        if ($score >= 20) return 'Beginner';
        return 'Starter';
    }

    // ── SKILLS ────────────────────────────────────────────

    public function addSkill(int $userId, array $data): array
    {
        return $this->requestSkill($userId, $data);
    }

    public function updateSkill(int $userSkillId, int $userId, array $data): array
    {
        $skill = new UserSkill(
            $userSkillId,
            $userId,
            trim($data['skillName']      ?? ''),
            trim($data['source']         ?? ''),
            isset($data['skillLevel']) ? (int) $data['skillLevel'] : 50,
            trim($data['certificateUrl'] ?? ''),
            trim($data['validatedAt']    ?? ''),
            isset($data['skillCatalogId']) ? (int) $data['skillCatalogId'] : null,
            trim((string) ($data['approvalStatus'] ?? 'pending')),
            isset($data['reviewedBy']) ? (int) $data['reviewedBy'] : null,
            $data['reviewedAt'] ?? null,
            trim((string) ($data['reviewNote'] ?? ''))
        );

        $errors = $this->validateSkill($skill);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE UserSkill
                 SET skillName      = ?,
                     source         = ?,
                     level          = ?,
                     certificateUrl = ?,
                     validatedAt    = ?,
                     skillCatalogId = ?,
                     approvalStatus = ?,
                     reviewedBy     = ?,
                     reviewedAt     = ?,
                     reviewNote     = ?
                 WHERE userSkillId = ? AND userId = ?"
            );
            $stmt->execute([
                $skill->getSkillName(),
                $skill->getSource(),
                $skill->getSkillLevel(),
                $skill->getCertificateUrl(),
                $skill->getValidatedAt() ?: null,
                $skill->getSkillCatalogId(),
                $skill->getApprovalStatus(),
                $skill->getReviewedBy(),
                $skill->getReviewedAt(),
                $skill->getReviewNote(),
                $userSkillId,
                $userId,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not update skill.']];
        }
    }

    public function deleteSkill(int $userSkillId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM UserSkill WHERE userSkillId = ? AND userId = ?"
            );
            $stmt->execute([$userSkillId, $userId]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not delete skill.']];
        }
    }

    public function addSkillCatalog(array $data, ?int $createdBy = null): array
    {
        $skillName = trim((string) ($data['skillName'] ?? ''));
        if ($skillName === '') {
            return ['success' => false, 'errors' => ['skillName' => 'Skill name is required.']];
        }

        $normalized = self::normalizeSkillNameKey($skillName);
        $category = trim((string) ($data['category'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($this->skillCatalogExists($skillName)) {
            return ['success' => false, 'errors' => ['skillName' => 'This skill already exists in the library.']];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO skill_catalog (skillName, normalizedName, category, description, isActive, createdBy, createdAt)
                 VALUES (:skillName, :normalizedName, :category, :description, 1, :createdBy, NOW())"
            );
            $stmt->execute([
                ':skillName' => $skillName,
                ':normalizedName' => $normalized,
                ':category' => $category !== '' ? $category : null,
                ':description' => $description !== '' ? $description : null,
                ':createdBy' => $createdBy,
            ]);

            return ['success' => true, 'skillCatalogId' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not add skill to the library.']];
        }
    }

    public function updateSkillCatalog(int $skillCatalogId, array $data, ?int $updatedBy = null): array
    {
        $skillName = trim((string) ($data['skillName'] ?? ''));
        if ($skillCatalogId <= 0) {
            return ['success' => false, 'errors' => ['skillCatalogId' => 'Skill library item not found.']];
        }
        if ($skillName === '') {
            return ['success' => false, 'errors' => ['skillName' => 'Skill name is required.']];
        }

        $normalized = self::normalizeSkillNameKey($skillName);
        $category = trim((string) ($data['category'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $isActive = !empty($data['isActive']) ? 1 : 0;

        try {
            $check = $this->pdo->prepare("SELECT COUNT(*) FROM skill_catalog WHERE normalizedName = :normalized AND skillCatalogId <> :id");
            $check->execute([':normalized' => $normalized, ':id' => $skillCatalogId]);
            if ((int) $check->fetchColumn() > 0) {
                return ['success' => false, 'errors' => ['skillName' => 'This skill already exists in the library.']];
            }

            $stmt = $this->pdo->prepare(
                "UPDATE skill_catalog
                 SET skillName = :skillName,
                     normalizedName = :normalizedName,
                     category = :category,
                     description = :description,
                     isActive = :isActive,
                     createdBy = COALESCE(createdBy, :updatedBy)
                 WHERE skillCatalogId = :skillCatalogId"
            );
            $stmt->execute([
                ':skillName' => $skillName,
                ':normalizedName' => $normalized,
                ':category' => $category !== '' ? $category : null,
                ':description' => $description !== '' ? $description : null,
                ':isActive' => $isActive,
                ':updatedBy' => $updatedBy,
                ':skillCatalogId' => $skillCatalogId,
            ]);

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not update skill.']];
        }
    }

    public function skillCatalogExists(string $skillName): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM skill_catalog WHERE normalizedName = :normalized");
            $stmt->execute([':normalized' => self::normalizeSkillNameKey($skillName)]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function resolveSkillCatalog(string $skillName): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM skill_catalog WHERE normalizedName = :normalized LIMIT 1"
            );
            $stmt->execute([':normalized' => self::normalizeSkillNameKey($skillName)]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function approveSkillRequest(int $userSkillId, int $reviewedBy, array $data = []): array
    {
        $request = $this->getSkillRequestById($userSkillId);
        if (!$request) {
            return ['success' => false, 'errors' => ['id' => 'Skill request not found.']];
        }

        $skillCatalogId = isset($data['skillCatalogId']) ? (int) $data['skillCatalogId'] : (int) ($request['skillCatalogId'] ?? 0);
        $createCatalog = !empty($data['createCatalog']);
        $catalogName = trim((string) ($data['catalogName'] ?? ''));

        if ($skillCatalogId <= 0 && $createCatalog) {
            $catalogResult = $this->addSkillCatalog([
                'skillName' => $catalogName !== '' ? $catalogName : (string) ($request['skillName'] ?? ''),
                'category' => $data['category'] ?? '',
                'description' => $data['description'] ?? '',
            ], $reviewedBy);

            if (empty($catalogResult['success'])) {
                return $catalogResult;
            }

            $skillCatalogId = (int) $catalogResult['skillCatalogId'];
        } elseif ($skillCatalogId <= 0) {
            $resolvedCatalog = $this->resolveSkillCatalog((string) ($request['skillName'] ?? ''));
            if ($resolvedCatalog) {
                $skillCatalogId = (int) $resolvedCatalog['skillCatalogId'];
            }
        }

        $skillName = $skillCatalogId > 0
            ? (string) ($this->getSkillCatalogById($skillCatalogId)['skillName'] ?? $request['skillName'] ?? '')
            : (string) ($request['skillName'] ?? '');

        if ($createCatalog) {
            $skillName = $catalogName !== '' ? $catalogName : $skillName;
        }

        $levelSql = '';
        $approveParams = [
            ':skillName'      => $skillName,
            ':skillCatalogId' => $skillCatalogId > 0 ? $skillCatalogId : null,
            ':reviewedBy'     => $reviewedBy,
            ':reviewNote'     => trim((string) ($data['reviewNote'] ?? '')),
            ':userSkillId'    => $userSkillId,
        ];
        if (isset($data['skillLevel']) && is_numeric($data['skillLevel']) && (int) $data['skillLevel'] >= 0) {
            $levelSql = ', level = :level';
            $approveParams[':level'] = (int) $data['skillLevel'];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE UserSkill
                 SET skillName = :skillName,
                     skillCatalogId = :skillCatalogId,
                     approvalStatus = 'approved',
                     reviewedBy = :reviewedBy,
                     reviewedAt = NOW(),
                     reviewNote = :reviewNote
                     {$levelSql}
                 WHERE userSkillId = :userSkillId"
            );
            $stmt->execute($approveParams);

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not approve skill request.']];
        }
    }

    public function rejectSkillRequest(int $userSkillId, int $reviewedBy, ?string $reviewNote = null): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE UserSkill
                 SET approvalStatus = 'rejected',
                     reviewedBy = :reviewedBy,
                     reviewedAt = NOW(),
                     reviewNote = :reviewNote
                 WHERE userSkillId = :userSkillId"
            );
            $stmt->execute([
                ':reviewedBy' => $reviewedBy,
                ':reviewNote' => $reviewNote !== null ? trim($reviewNote) : null,
                ':userSkillId' => $userSkillId,
            ]);

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not reject skill request.']];
        }
    }

    public function getSkillRequestById(int $userSkillId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM UserSkill WHERE userSkillId = :id LIMIT 1");
            $stmt->execute([':id' => $userSkillId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getSkillCatalogById(int $skillCatalogId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM skill_catalog WHERE skillCatalogId = :id LIMIT 1");
            $stmt->execute([':id' => $skillCatalogId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── VALIDATE SKILL ────────────────────────────────────

    private function validateSkill(UserSkill $skill): array
    {
        $errors = [];
        if (strlen($skill->getSkillName()) < 2) {
            $errors['skillName'] = 'Skill name is required and must be at least 2 characters.';
        }
        if (strlen($skill->getSource()) < 2) {
            $errors['source'] = 'Source is required and must be at least 2 characters.';
        }
        if (empty($skill->getCertificateUrl())) {
            $errors['certificateUrl'] = 'Certificate URL is required.';
        } elseif (!filter_var($skill->getCertificateUrl(), FILTER_VALIDATE_URL)) {
            $errors['certificateUrl'] = 'Certificate URL must be a valid URL (https://...).';
        }
        if (empty($skill->getValidatedAt())) {
            $errors['validatedAt'] = 'Validated date is required.';
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $skill->getValidatedAt());
            if (!$date || $date->format('Y-m-d') !== $skill->getValidatedAt()) {
                $errors['validatedAt'] = 'Validated date must be in YYYY-MM-DD format.';
            }
        }
        return $errors;
    }

    public function requestSkill(int $userId, array $data): array
    {
        $skillName = trim((string) ($data['skillName'] ?? ''));
        $source = trim((string) ($data['source'] ?? ''));
        $skillLevel = isset($data['skillLevel']) ? (int) $data['skillLevel'] : 50;
        $certificateUrl = trim((string) ($data['certificateUrl'] ?? ''));
        $validatedAt = trim((string) ($data['validatedAt'] ?? ''));
        $skillCatalogId = isset($data['skillCatalogId']) && (int) $data['skillCatalogId'] > 0 ? (int) $data['skillCatalogId'] : null;
        $approvalStatus = strtolower(trim((string) ($data['approvalStatus'] ?? 'pending')));

        if ($skillCatalogId === null) {
            $catalog = $this->resolveSkillCatalog($skillName);
            if ($catalog) {
                $skillCatalogId = (int) $catalog['skillCatalogId'];
                $skillName = (string) $catalog['skillName'];
            }
        }

        $skill = new UserSkill(
            null,
            $userId,
            $skillName,
            $source,
            $skillLevel,
            $certificateUrl,
            $validatedAt,
            $skillCatalogId,
            $approvalStatus,
            null,
            null,
            null
        );

        $errors = $this->validateSkill($skill);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $duplicate = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM UserSkill
                 WHERE userId = :userId
                   AND LOWER(TRIM(skillName)) = LOWER(TRIM(:skillName))
                   AND LOWER(COALESCE(approvalStatus, 'approved')) <> 'rejected'"
            );
            $duplicate->execute([
                ':userId' => $userId,
                ':skillName' => $skillName,
            ]);

            if ((int) $duplicate->fetchColumn() > 0) {
                return ['success' => false, 'errors' => ['skillName' => 'You already requested or added this skill.']];
            }
        } catch (PDOException $e) {
            // Continue to insert; duplicate protection is best-effort.
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO UserSkill (
                    userId, skillName, source, level, certificateUrl, validatedAt,
                    skillCatalogId, approvalStatus, reviewedBy, reviewedAt, reviewNote
                 ) VALUES (
                    :userId, :skillName, :source, :skillLevel, :certificateUrl, :validatedAt,
                    :skillCatalogId, :approvalStatus, :reviewedBy, :reviewedAt, :reviewNote
                 )"
            );
            $stmt->execute([
                ':userId' => $userId,
                ':skillName' => $skillName,
                ':source' => $source,
                ':skillLevel' => $skillLevel,
                ':certificateUrl' => $certificateUrl,
                ':validatedAt' => $validatedAt !== '' ? $validatedAt : null,
                ':skillCatalogId' => $skillCatalogId,
                ':approvalStatus' => in_array($approvalStatus, ['pending', 'approved', 'rejected'], true) ? $approvalStatus : 'pending',
                ':reviewedBy' => null,
                ':reviewedAt' => null,
                ':reviewNote' => null,
            ]);

            return ['success' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save skill request.']];
        }
    }

    // ── ROW HELPERS ───────────────────────────────────────

    private function rowToProfile(array $row): Profile
    {
        $portfolioTheme = self::normalizeTheme((string) ($row['portfolioTheme'] ?? 'midnight'));
        $cvTheme = self::normalizeTheme((string) ($row['cvTheme'] ?? 'graphite'));
        $portfolioPalette = self::themePalette($portfolioTheme);
        $cvPalette = self::themePalette($cvTheme);

        $p = new Profile(
            $row['bio']         ?? '',
            $row['photoUrl']    ?? '',
            $row['location']    ?? '',
            $row['preferences'] ?? '',
            (int) ($row['completionScore'] ?? 0),
            $row['level']       ?? 'Starter',
            (int) $row['userId'],
            $row['careerGoal'] ?? '',
            $portfolioTheme,
            self::normalizeColor((string) ($row['portfolioPrimaryColor'] ?? ''), $portfolioPalette['portfolioPrimary']),
            self::normalizeColor((string) ($row['portfolioSecondaryColor'] ?? ''), $portfolioPalette['portfolioSecondary']),
            $cvTheme,
            self::normalizeColor((string) ($row['cvPrimaryColor'] ?? ''), $cvPalette['cvPrimary']),
            self::normalizeColor((string) ($row['cvSecondaryColor'] ?? ''), $cvPalette['cvSecondary'])
        );
        $p->setProfileId((int) $row['profileId']);
        return $p;
    }

    private function resolveProfilePhoto(int $userId, string $submittedPhotoUrl, ?array $uploadFile, string $existingPhotoUrl = ''): array
    {
        $submittedPhotoUrl = trim($submittedPhotoUrl);
        $existingPhotoUrl = trim($existingPhotoUrl);

        if (is_array($uploadFile) && array_key_exists('error', $uploadFile)) {
            $uploadError = (int) ($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_NO_FILE && $uploadError !== UPLOAD_ERR_OK) {
                return ['error' => 'The uploaded photo could not be processed. Please try a smaller JPG, PNG, GIF, or WEBP file.'];
            }
        }

        if (is_array($uploadFile) && (($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
            $stored = $this->storeProfilePhotoUpload($userId, $uploadFile);
            if (!empty($stored['error'])) {
                return $stored;
            }
            return ['photoUrl' => (string) ($stored['photoUrl'] ?? '')];
        }

        if ($submittedPhotoUrl !== '') {
            if ($existingPhotoUrl !== '' && $this->normalizePublicPath($submittedPhotoUrl) === $this->normalizePublicPath($existingPhotoUrl)) {
                return ['photoUrl' => $existingPhotoUrl];
            }

            if ($this->isLocalProfilePhotoPath($submittedPhotoUrl) || $this->isWebImageUrl($submittedPhotoUrl)) {
                if ($this->isWebImageUrl($submittedPhotoUrl)) {
                    $downloaded = $this->downloadProfilePhoto($userId, $submittedPhotoUrl);
                    if (!empty($downloaded['error'])) {
                        return $downloaded;
                    }
                    return ['photoUrl' => (string) ($downloaded['photoUrl'] ?? '')];
                }

                return ['photoUrl' => $this->normalizePublicPath($submittedPhotoUrl)];
            }

            return ['error' => 'Please provide an image file or a valid image URL.'];
        }

        return ['photoUrl' => $existingPhotoUrl];
    }

    private function storeProfilePhotoUpload(int $userId, array $uploadFile): array
    {
        if (!is_dir(self::PROFILE_PHOTO_DIR) && !mkdir(self::PROFILE_PHOTO_DIR, 0755, true) && !is_dir(self::PROFILE_PHOTO_DIR)) {
            return ['error' => 'Could not prepare the profile photo folder.'];
        }

        $tmpName = (string) ($uploadFile['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['error' => 'The uploaded photo could not be read.'];
        }

        $imageInfo = @getimagesize($tmpName);
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        $extension = $this->mimeToExtension($mime);
        if ($extension === null) {
            return ['error' => 'Unsupported image type. Please use JPG, PNG, GIF, or WEBP.'];
        }

        $filename = sprintf('profile_%d_%s.%s', $userId, bin2hex(random_bytes(8)), $extension);
        $target = self::PROFILE_PHOTO_DIR . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            return ['error' => 'Could not save the uploaded photo.'];
        }

        return ['photoUrl' => $this->profilePhotoPublicUrl($filename)];
    }

    private function downloadProfilePhoto(int $userId, string $url): array
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Please provide a valid image URL.'];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => 1,
                'user_agent' => 'CareerStrand/1.0',
            ],
            'https' => [
                'timeout' => 10,
                'follow_location' => 1,
                'user_agent' => 'CareerStrand/1.0',
            ],
        ]);

        $imageData = @file_get_contents($url, false, $context);
        if ($imageData === false || $imageData === '') {
            return ['error' => 'Could not download the image from that URL.'];
        }

        $mime = $this->detectImageMimeFromBytes($imageData);
        $extension = $this->mimeToExtension($mime);
        if ($extension === null) {
            return ['error' => 'The URL must point to a JPG, PNG, GIF, or WEBP image.'];
        }

        if (!is_dir(self::PROFILE_PHOTO_DIR) && !mkdir(self::PROFILE_PHOTO_DIR, 0755, true) && !is_dir(self::PROFILE_PHOTO_DIR)) {
            return ['error' => 'Could not prepare the profile photo folder.'];
        }

        $filename = sprintf('profile_%d_%s.%s', $userId, bin2hex(random_bytes(8)), $extension);
        $target = self::PROFILE_PHOTO_DIR . $filename;
        if (file_put_contents($target, $imageData) === false) {
            return ['error' => 'Could not save the downloaded image.'];
        }

        return ['photoUrl' => $this->profilePhotoPublicUrl($filename)];
    }

    private function detectImageMimeFromBytes(string $bytes): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $bytes) ?: '';
                finfo_close($finfo);
                if ($mime !== '') {
                    return (string) $mime;
                }
            }
        }

        $imageInfo = @getimagesizefromstring($bytes);
        return is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    }

    private function mimeToExtension(string $mime): ?string
    {
        return match (strtolower(trim($mime))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
    }

    private function isWebImageUrl(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', trim($value));
    }

    private function isLocalProfilePhotoPath(string $value): bool
    {
        $value = trim($value);
        return $value !== '' && (str_starts_with($value, '/Careerstrand/uploads/profile-photos/') || str_starts_with($value, 'uploads/profile-photos/'));
    }

    private function normalizePublicPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/Careerstrand/')) {
            return $path;
        }

        if (str_starts_with($path, 'uploads/')) {
            return '/Careerstrand/' . ltrim($path, '/');
        }

        return $path;
    }

    private function profilePhotoPublicUrl(string $filename): string
    {
        return '/Careerstrand/uploads/profile-photos/' . ltrim($filename, '/');
    }

    public static function themeOptions(): array
    {
        return self::THEME_OPTIONS;
    }

    public static function themeLabel(string $theme): string
    {
        $theme = self::normalizeTheme($theme);
        return self::THEME_OPTIONS[$theme]['label'];
    }

    public static function themeDescription(string $theme): string
    {
        $theme = self::normalizeTheme($theme);
        return self::THEME_OPTIONS[$theme]['description'];
    }

    public static function themePalette(string $theme): array
    {
        $theme = self::normalizeTheme($theme);
        return self::THEME_OPTIONS[$theme];
    }

    public static function normalizeTheme(string $theme): string
    {
        $theme = strtolower(trim($theme));
        return array_key_exists($theme, self::THEME_OPTIONS) ? $theme : 'midnight';
    }

    public static function normalizeSkillNameKey(string $skillName): string
    {
        $skillName = strtolower(trim($skillName));
        $skillName = preg_replace('/\s+/', ' ', $skillName) ?? $skillName;
        return preg_replace('/[^a-z0-9\/\+\.\-\s#]+/i', '', $skillName) ?? $skillName;
    }

    private static function normalizeColor(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    private function rowToUserSkill(array $row): UserSkill
    {
        $s = new UserSkill(
            (int) $row['userSkillId'],
            (int) $row['userId'],
            $row['skillName']      ?? '',
            $row['source']         ?? '',
            isset($row['level']) ? (int) $row['level'] : null,
            $row['certificateUrl'] ?? '',
            $row['validatedAt']    ?? '',
            isset($row['skillCatalogId']) ? (int) $row['skillCatalogId'] : null,
            $row['approvalStatus'] ?? 'approved',
            isset($row['reviewedBy']) ? (int) $row['reviewedBy'] : null,
            $row['reviewedAt'] ?? null,
            $row['reviewNote'] ?? null
        );
        return $s;
    }
}
?>
