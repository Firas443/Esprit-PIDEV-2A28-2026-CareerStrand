<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/ManagerProfile.php';
require_once __DIR__ . '/../Model/RecruiterProfile.php';

class RoleProfileController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    // ══════════════════════════════════════════════════════
    // MANAGER PROFILE
    // ══════════════════════════════════════════════════════

    // ── READ ─────────────────────────────────────────────
    public function getManagerProfile(int $userId): ?ManagerProfile
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM ManagerProfile WHERE userId = ? LIMIT 1"
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ? $this->rowToManagerProfile($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── CREATE OR UPDATE ─────────────────────────────────
    public function saveManagerProfile(int $userId, array $data): array
    {
        $mp = new ManagerProfile(
            trim($data['organization']  ?? ''),
            trim($data['categoryFocus'] ?? ''),
            trim($data['description']   ?? ''),
            $userId
        );

        $errors = $this->validateManagerProfile($mp);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $existing = $this->getManagerProfile($userId);
            if ($existing) {
                $stmt = $this->pdo->prepare(
                    "UPDATE ManagerProfile
                     SET organization  = ?,
                         categoryFocus = ?,
                         description   = ?
                     WHERE userId = ?"
                );
                $stmt->execute([
                    $mp->getOrganization(),
                    $mp->getCategoryFocus(),
                    $mp->getDescription(),
                    $userId,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO ManagerProfile (userId, organization, categoryFocus, description)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    $mp->getOrganization(),
                    $mp->getCategoryFocus(),
                    $mp->getDescription(),
                ]);
            }
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save manager profile.']];
        }
    }

    // ── DELETE ────────────────────────────────────────────
    public function deleteManagerProfile(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM ManagerProfile WHERE userId = ?");
            $stmt->execute([$userId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── VALIDATE ──────────────────────────────────────────
    private function validateManagerProfile(ManagerProfile $mp): array
    {
        $errors = [];
        if (strlen($mp->getOrganization()) < 2) {
            $errors['organization'] = 'Organization name must be at least 2 characters.';
        }
        if (empty($mp->getCategoryFocus())) {
            $errors['categoryFocus'] = 'Category focus is required.';
        }
        return $errors;
    }

    // ── HELPER ────────────────────────────────────────────
    private function rowToManagerProfile(array $row): ManagerProfile
    {
        $mp = new ManagerProfile(
            $row['organization']  ?? '',
            $row['categoryFocus'] ?? '',
            $row['description']   ?? '',
            (int) $row['userId']
        );
        $mp->setManagerProfileId((int) $row['managerProfileId']);
        return $mp;
    }

    // ── ACTIVITY STATS (for admin panel) ─────────────────
    public function getManagerActivity(int $userId): array
    {
        try {
            $challenges = $this->pdo->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                 FROM Challenge WHERE managerId = ?"
            );
            $challenges->execute([$userId]);
            $cRow = $challenges->fetch();

            $events = $this->pdo->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'upcoming' OR date >= CURDATE() THEN 1 ELSE 0 END) as upcoming
                 FROM Event WHERE managerId = ?"
            );
            $events->execute([$userId]);
            $eRow = $events->fetch();

            return [
                'challenges_total'   => (int) ($cRow['total']    ?? 0),
                'challenges_active'  => (int) ($cRow['active']   ?? 0),
                'challenges_closed'  => (int) ($cRow['closed']   ?? 0),
                'events_total'       => (int) ($eRow['total']    ?? 0),
                'events_upcoming'    => (int) ($eRow['upcoming'] ?? 0),
            ];
        } catch (PDOException $e) {
            return [
                'challenges_total'  => 0, 'challenges_active' => 0,
                'challenges_closed' => 0, 'events_total'      => 0,
                'events_upcoming'   => 0,
            ];
        }
    }


    // ══════════════════════════════════════════════════════
    // RECRUITER PROFILE
    // ══════════════════════════════════════════════════════

    // ── READ ─────────────────────────────────────────────
    public function getRecruiterProfile(int $userId): ?RecruiterProfile
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM RecruiterProfile WHERE userId = ? LIMIT 1"
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            return $row ? $this->rowToRecruiterProfile($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── CREATE OR UPDATE ─────────────────────────────────
    public function saveRecruiterProfile(int $userId, array $data): array
    {
        $rp = new RecruiterProfile(
            trim($data['companyName']      ?? ''),
            trim($data['jobTitle']         ?? ''),
            trim($data['industry']         ?? ''),
            trim($data['companyWebsite']   ?? ''),
            trim($data['opportunityTypes'] ?? ''),
            $userId
        );

        $errors = $this->validateRecruiterProfile($rp);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $existing = $this->getRecruiterProfile($userId);
            if ($existing) {
                $stmt = $this->pdo->prepare(
                    "UPDATE RecruiterProfile
                     SET companyName      = ?,
                         jobTitle         = ?,
                         industry         = ?,
                         companyWebsite   = ?,
                         opportunityTypes = ?
                     WHERE userId = ?"
                );
                $stmt->execute([
                    $rp->getCompanyName(),
                    $rp->getJobTitle(),
                    $rp->getIndustry(),
                    $rp->getCompanyWebsite(),
                    $rp->getOpportunityTypes(),
                    $userId,
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO RecruiterProfile
                         (userId, companyName, jobTitle, industry, companyWebsite, opportunityTypes)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    $rp->getCompanyName(),
                    $rp->getJobTitle(),
                    $rp->getIndustry(),
                    $rp->getCompanyWebsite(),
                    $rp->getOpportunityTypes(),
                ]);
            }
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not save recruiter profile.']];
        }
    }

    // ── DELETE ────────────────────────────────────────────
    public function deleteRecruiterProfile(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM RecruiterProfile WHERE userId = ?");
            $stmt->execute([$userId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ── VALIDATE ──────────────────────────────────────────
    private function validateRecruiterProfile(RecruiterProfile $rp): array
    {
        $errors = [];
        if (strlen($rp->getCompanyName()) < 2) {
            $errors['companyName'] = 'Company name must be at least 2 characters.';
        }
        if (empty($rp->getJobTitle())) {
            $errors['jobTitle'] = 'Job title is required.';
        }
        if (empty($rp->getIndustry())) {
            $errors['industry'] = 'Industry is required.';
        }
        if (!empty($rp->getCompanyWebsite()) && !filter_var($rp->getCompanyWebsite(), FILTER_VALIDATE_URL)) {
            $errors['companyWebsite'] = 'Company website must be a valid URL.';
        }
        return $errors;
    }

    // ── HELPER ────────────────────────────────────────────
    private function rowToRecruiterProfile(array $row): RecruiterProfile
    {
        $rp = new RecruiterProfile(
            $row['companyName']      ?? '',
            $row['jobTitle']         ?? '',
            $row['industry']         ?? '',
            $row['companyWebsite']   ?? '',
            $row['opportunityTypes'] ?? '',
            (int) $row['userId']
        );
        $rp->setRecruiterProfileId((int) $row['recruiterProfileId']);
        return $rp;
    }

    // ── OPPORTUNITY STATS (for admin panel) ──────────────
    public function getRecruiterActivity(int $userId): array
    {
        try {
            $opps = $this->pdo->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'open'   THEN 1 ELSE 0 END) as open,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                 FROM Opportunity WHERE managerId = ?"
            );
            $opps->execute([$userId]);
            $oRow = $opps->fetch();

            $apps = $this->pdo->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending
                 FROM Application a
                 JOIN Opportunity o ON a.opportunityId = o.opportunityId
                 WHERE o.managerId = ?"
            );
            $apps->execute([$userId]);
            $aRow = $apps->fetch();

            return [
                'opps_total'   => (int) ($oRow['total']   ?? 0),
                'opps_open'    => (int) ($oRow['open']    ?? 0),
                'opps_closed'  => (int) ($oRow['closed']  ?? 0),
                'apps_total'   => (int) ($aRow['total']   ?? 0),
                'apps_pending' => (int) ($aRow['pending'] ?? 0),
            ];
        } catch (PDOException $e) {
            return [
                'opps_total'  => 0, 'opps_open'    => 0,
                'opps_closed' => 0, 'apps_total'   => 0,
                'apps_pending'=> 0,
            ];
        }
    }
}
?>
