<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../utils/Mailer.php';
require_once __DIR__ . '/../Controller/QuestionnaireController.php';
require_once __DIR__ . '/../Model/UserQuestionnaire.php';

class UserController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = config::getConnexion();
        $this->ensureUsersSchema();
    }

    private function ensureUsersSchema(): void
    {
        $column = $this->pdo->query("SHOW COLUMNS FROM Users LIKE 'userId'")->fetch();
        $hasAutoIncrement = $column && stripos((string)($column['Extra'] ?? ''), 'auto_increment') !== false;
        $hasPrimary = $column && strtoupper((string)($column['Key'] ?? '')) === 'PRI';

        if (!$hasPrimary) {
            $this->pdo->exec("ALTER TABLE Users ADD PRIMARY KEY (userId)");
        }
        if (!$hasAutoIncrement) {
            $this->pdo->exec("ALTER TABLE Users MODIFY userId int(11) NOT NULL AUTO_INCREMENT");
        }
    }

    // ── STATS ─────────────────────────────────────────────
    public function getStats(): array
    {
        try {
            return [
                'userCount'    => (int) $this->pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn(),
                'adminCount'   => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(role) = 'admin'")->fetchColumn(),
                'managerCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(role) IN ('manager','manager recruiter')")->fetchColumn(),
                'activeCount'  => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(status) = 'active'")->fetchColumn(),
            ];
        } catch (PDOException $e) {
            return ['userCount' => 0, 'adminCount' => 0, 'managerCount' => 0, 'activeCount' => 0];
        }
    }

    // ── LIST ALL ──────────────────────────────────────────
    public function getAll(string $search = '', string $sort = 'fullName', string $order = 'ASC'): array
    {
        $allowedSort  = ['fullName', 'email', 'role', 'status', 'createdAt'];
        $allowedOrder = ['ASC', 'DESC'];
        if (!in_array($sort, $allowedSort))               $sort  = 'fullName';
        if (!in_array(strtoupper($order), $allowedOrder)) $order = 'ASC';

        try {
            if ($search !== '') {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM Users WHERE fullName LIKE :s OR email LIKE :s2 ORDER BY $sort $order"
                );
                $stmt->execute([':s' => "%$search%", ':s2' => "%$search%"]);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM Users ORDER BY $sort $order");
            }
            $users = [];
            while ($row = $stmt->fetch()) {
                $users[] = $this->rowToUser($row);
            }
            return $users;
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── FIND ONE ──────────────────────────────────────────
    public function getById(int $id): ?User
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE userId = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ? $this->rowToUser($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getByEmail(string $email): ?User
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            return $row ? $this->rowToUser($row) : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── AUTHENTICATE (email + password) ───────────────────
    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->getByEmail($email);
        if (!$user) {
            return null;
        }

        $storedPassword = (string) $user->getPassword();
        if (password_verify($password, $storedPassword)) {
            return $user;
        }

        if (hash_equals($storedPassword, $password)) {
            $this->rehashPassword($user->getUserId(), $password);
            return $user;
        }

        return null;
    }

    private function rehashPassword(int $userId, string $password): void
    {
        $stmt = $this->pdo->prepare("UPDATE Users SET password = ? WHERE userId = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    }

    // ── CREATE ────────────────────────────────────────────
    public function createUser(array $data): array
    {
        $this->ensureUsersSchema();

        $role = $data['role'] ?? 'user';
        $status = $data['status'] ?? 'active';
        $approvalStatus = $data['approvalStatus'] ?? 'approved';

        // Manager recruiter accounts must wait for admin approval.
        // Admin-created recruiters can override this by passing approvalStatus/status.
        if ($role === 'manager recruiter' && !isset($data['approvalStatus'])) {
            $status = 'inactive';
            $approvalStatus = 'pending';
        }

        $user = new User(
            trim($data['fullName'] ?? ''),
            trim($data['email']    ?? ''),
            $data['password'] ?? '',
            $role,
            $status,
            date('Y-m-d'),
            null,
            0,
            $approvalStatus,
            null
        );

        $errors = $this->validate($user);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO Users (fullName, email, password, role, status, approvalStatus, rejectionReason, createdAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user->getFullName(),
                $user->getEmail(),
                password_hash($user->getPassword(), PASSWORD_DEFAULT),
                $user->getRole(),
                $user->getStatus(),
                $approvalStatus,
                null,
                $user->getCreatedAt(),
            ]);
            return ['success' => true, 'userId' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not create user.']];
        }
    }

    // ── UPDATE ────────────────────────────────────────────
    public function updateUser(int $id, array $data): array
    {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['id' => 'User not found.']];
        }

        $user = new User(
            trim($data['fullName'] ?? $existing->getFullName()),
            trim($data['email']    ?? $existing->getEmail()),
            $data['password'] ?? '',
            $data['role']     ?? $existing->getRole(),
            $data['status']   ?? $existing->getStatus(),
            $existing->getCreatedAt()
        );
        $user->setUserId($id);

        $errors = $this->validate($user, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $setParts = ['fullName = ?', 'email = ?', 'role = ?', 'status = ?'];
        $params   = [$user->getFullName(), $user->getEmail(), $user->getRole(), $user->getStatus()];

        if (array_key_exists('approvalStatus', $data)) {
            $setParts[] = 'approvalStatus = ?';
            $params[] = $data['approvalStatus'];
        }
        if (array_key_exists('rejectionReason', $data)) {
            $setParts[] = 'rejectionReason = ?';
            $params[] = $data['rejectionReason'];
        }

        if (!empty($data['password'])) {
            $setParts[] = 'password = ?';
            $params[]   = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $params[] = $id;

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE Users SET " . implode(', ', $setParts) . " WHERE userId = ?"
            );
            $stmt->execute($params);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not update user.']];
        }
    }

    // ── DELETE ────────────────────────────────────────────
    public function deleteUser(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'errors' => ['id' => 'Invalid user id.']];
        }

        try {
            $this->pdo->beginTransaction();

            // Delete children first to avoid foreign-key errors.
            // These helpers are safe even if some tables do not exist in your DB.
            $this->deleteIfTableAndColumnExist('Comment', 'postId',
                'DELETE FROM `Comment` WHERE postId IN (SELECT postId FROM Post WHERE userId = ?)', [$id]
            );
            $this->deleteIfTableAndColumnExist('Comment', 'userId', 'DELETE FROM `Comment` WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('Post', 'userId', 'DELETE FROM Post WHERE userId = ?', [$id]);

            $this->deleteIfTableAndColumnExist('Submission', 'challengeId',
                'DELETE FROM Submission WHERE challengeId IN (SELECT challengeId FROM Challenge WHERE managerId = ?)', [$id]
            );
            $this->deleteIfTableAndColumnExist('Submission', 'userId', 'DELETE FROM Submission WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('Challenge', 'managerId', 'DELETE FROM Challenge WHERE managerId = ?', [$id]);

            $this->deleteIfTableAndColumnExist('Participation', 'eventId',
                'DELETE FROM Participation WHERE eventId IN (SELECT eventId FROM event WHERE managerId = ?)', [$id]
            );
            $this->deleteIfTableAndColumnExist('EventForm', 'eventId',
                'DELETE FROM EventForm WHERE eventId IN (SELECT eventId FROM event WHERE managerId = ?)', [$id]
            );
            $this->deleteIfTableAndColumnExist('Participation', 'userId', 'DELETE FROM Participation WHERE userId = ?', [$id]);
            // Nullify event.sponsorId for any sponsor rows linked to this user before deleting
            $this->deleteIfTableAndColumnExist('Sponsor', 'userId',
                'UPDATE event SET sponsorId = NULL WHERE sponsorId IN (SELECT sponsorId FROM Sponsor WHERE userId = ?)', [$id]
            );
            $this->deleteIfTableAndColumnExist('Sponsor', 'userId', 'DELETE FROM Sponsor WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('event', 'managerId', 'DELETE FROM event WHERE managerId = ?', [$id]);

            $this->deleteIfTableAndColumnExist('Application', 'opportunityId',
                'DELETE FROM Application WHERE opportunityId IN (SELECT opportunityId FROM Opportunity WHERE managerId = ?)', [$id]
            );
            $this->deleteIfTableAndColumnExist('Application', 'userId', 'DELETE FROM Application WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('Opportunity', 'managerId', 'DELETE FROM Opportunity WHERE managerId = ?', [$id]);

            $this->deleteIfTableAndColumnExist('GroupMember', 'userId', 'DELETE FROM GroupMember WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('Calendar', 'userId', 'DELETE FROM Calendar WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('UserSkill', 'userId', 'DELETE FROM UserSkill WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('RecruiterProfile', 'userId', 'DELETE FROM RecruiterProfile WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('UserQuestionnaire', 'userId', 'DELETE FROM UserQuestionnaire WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('ManagerProfile', 'userId', 'DELETE FROM ManagerProfile WHERE userId = ?', [$id]);
            $this->deleteIfTableAndColumnExist('Profile', 'userId', 'DELETE FROM Profile WHERE userId = ?', [$id]);

            $stmt = $this->pdo->prepare('DELETE FROM Users WHERE userId = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                return ['success' => false, 'errors' => ['id' => 'User not found.']];
            }

            $this->pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'errors' => ['db' => 'Could not delete user: ' . $e->getMessage()]];
        }
    }

    private function deleteIfTableAndColumnExist(string $table, string $column, string $sql, array $params): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ══════════════════════════════════════════════════════
    // FACE RECOGNITION METHODS
    // ══════════════════════════════════════════════════════

    // ── SAVE FACE DESCRIPTOR ──────────────────────────────
    // Saves the 128-number JSON array from face-api.js to DB
    public function saveFaceDescriptor(int $userId, string $descriptorJson): array
    {
        // Validate it is real JSON with 128 numbers
        $arr = json_decode($descriptorJson, true);
        if (!is_array($arr) || count($arr) !== 128) {
            return ['success' => false, 'error' => 'Invalid face descriptor format.'];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE Users SET faceDescriptor = ? WHERE userId = ?"
            );
            $stmt->execute([$descriptorJson, $userId]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Could not save face descriptor.'];
        }
    }

    // ── ENABLE / DISABLE FACE LOGIN ───────────────────────
    public function toggleFaceLogin(int $userId, bool $enable): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE Users SET faceEnabled = ? WHERE userId = ?"
            );
            $stmt->execute([$enable ? 1 : 0, $userId]);
            return ['success' => true, 'faceEnabled' => $enable];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Could not update face login setting.'];
        }
    }

    // ── GET ALL USERS WITH FACE REGISTERED ───────────────
    // Used at login time to compare incoming face against all stored descriptors
    public function getAllWithFace(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT userId, fullName, email, role, status, faceDescriptor
                 FROM Users
                 WHERE faceEnabled = 1
                   AND status = 'active'
                   AND approvalStatus = 'approved'
                   AND faceDescriptor IS NOT NULL
                   AND faceDescriptor != ''"
            );
            $users = [];
            while ($row = $stmt->fetch()) {
                $u = new User(
                    $row['fullName'],
                    $row['email'],
                    '',           // never expose password hash here
                    $row['role'],
                    $row['status']
                );
                $u->setUserId((int) $row['userId']);
                $u->setFaceDescriptor($row['faceDescriptor']);
                $u->setFaceEnabled(1);
                $users[] = $u;
            }
            return $users;
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── AUTHENTICATE BY FACE ──────────────────────────────
    // Receives the 128-number descriptor from the camera,
    // compares with all stored descriptors using euclidean distance.
    // Returns the matched User or null.
    public function authenticateByFace(string $incomingJson): ?User
    {
        $incoming = json_decode($incomingJson, true);
        if (!is_array($incoming) || count($incoming) !== 128) {
            return null;
        }

        $candidates = $this->getAllWithFace();
        $threshold  = 0.55; // lower = stricter match (0.4–0.6 is typical)

        foreach ($candidates as $candidate) {
            $stored = json_decode($candidate->getFaceDescriptor(), true);
            if (!is_array($stored) || count($stored) !== 128) {
                continue;
            }

            // Euclidean distance between two 128-D vectors
            $sum = 0.0;
            for ($i = 0; $i < 128; $i++) {
                $diff = ($incoming[$i] ?? 0) - ($stored[$i] ?? 0);
                $sum += $diff * $diff;
            }
            $distance = sqrt($sum);

            if ($distance < $threshold) {
                // Match found — return full user object from DB
                return $this->getById($candidate->getUserId());
            }
        }

        return null; // no match
    }

    // ── APPROVAL WORKFLOW ─────────────────────────────────
    public function getPendingRecruiters(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT * FROM Users
                 WHERE role = 'manager recruiter'
                   AND approvalStatus = 'pending'
                 ORDER BY createdAt DESC, userId DESC"
            );
            $users = [];
            while ($row = $stmt->fetch()) {
                $users[] = $this->rowToUser($row);
            }
            return $users;
        } catch (PDOException $e) {
            return [];
        }
    }

   public function approveRecruiter(int $userId): array
{
    $user = $this->getById($userId);

    if (!$user || $user->getRole() !== 'manager recruiter') {
        return [
            'success' => false,
            'errors' => ['approval' => 'Recruiter request not found.']
        ];
    }

    try {
        $stmt = $this->pdo->prepare(
            "UPDATE Users
             SET status = 'active',
                 approvalStatus = 'approved',
                 rejectionReason = NULL
             WHERE userId = ? AND role = 'manager recruiter'"
        );

        $stmt->execute([$userId]);

        if ($stmt->rowCount() < 1) {
            return [
                'success' => false,
                'errors' => ['approval' => 'No recruiter row was updated.']
            ];
        }

        // Generate bio from questionnaire answers after admin approval
        $questionnaireModel = new UserQuestionnaire();
        $answersRows = $questionnaireModel->getAnswersByUser($userId);

        $answers = [];
        foreach ($answersRows as $row) {
            $answers[$row['question']] = $row['answer'];
        }

        if (!empty($answers)) {
            $questionnaireController = new QuestionnaireController();
            $bio = $questionnaireController->generateBio($answers);
            $questionnaireController->saveBio($userId, $bio);
        }

        // Send approval email
        $this->sendApprovalMail(
            $user->getEmail(),
            $user->getFullName(),
            true
        );

        return ['success' => true];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'errors' => ['db' => 'Could not approve recruiter: ' . $e->getMessage()]
        ];
    }
}

    public function rejectRecruiter(int $userId, string $reason = ''): array
    {
        $user = $this->getById($userId);
        if (!$user || $user->getRole() !== 'manager recruiter') {
            return ['success' => false, 'errors' => ['approval' => 'Recruiter request not found.']];
        }

        $reason = trim($reason) ?: 'Your recruiter information could not be verified.';

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE Users
                 SET status = 'inactive',
                     approvalStatus = 'rejected',
                     rejectionReason = ?
                 WHERE userId = ? AND role = 'manager recruiter'"
            );
            $stmt->execute([$reason, $userId]);

            if ($stmt->rowCount() < 1) {
                return ['success' => false, 'errors' => ['approval' => 'No recruiter row was updated.']];
            }

            $this->sendApprovalMail($user->getEmail(), $user->getFullName(), false, $reason);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not reject recruiter: ' . $e->getMessage()]];
        }
    }

    private function sendApprovalMail(string $email, string $name, bool $approved, string $reason = ''): void
    {
        // Uses utils/Mailer.php (PHPMailer + Gmail SMTP).
        // Email failure must not block approving/rejecting the recruiter.
        if (function_exists('sendApprovalEmail')) {
            sendApprovalEmail(
                $email,
                $name,
                $approved ? 'approved' : 'rejected',
                $reason
            );
        }
    }

    // ── VALIDATION ────────────────────────────────────────
    private function validate(User $user, ?int $excludeId = null): array
    {
        $errors       = [];
        $allowedRoles = ['admin', 'manager', 'manager recruiter', 'user'];

        if (strlen($user->getFullName()) < 2)
            $errors['fullName'] = 'Full name must be at least 2 characters.';

        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        } else {
            $sql    = "SELECT userId FROM Users WHERE email = ?" . ($excludeId ? " AND userId != ?" : "");
            $params = [$user->getEmail()];
            if ($excludeId) $params[] = $excludeId;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already exists.';
            }
        }

        if (!in_array($user->getRole(), $allowedRoles))
            $errors['role'] = 'Invalid role.';

        if ($excludeId === null && strlen($user->getPassword()) < 6)
            $errors['password'] = 'Password must be at least 6 characters.';

        return $errors;
    }

    // ── ROW HELPER ────────────────────────────────────────
    private function rowToUser(array $row): User
    {
        $u = new User(
            $row['fullName']  ?? '',
            $row['email']     ?? '',
            $row['password']  ?? '',
            $row['role']      ?? 'user',
            $row['status']    ?? 'active',
            $row['createdAt'] ?? '',
            $row['faceDescriptor'] ?? null,
            (int) ($row['faceEnabled'] ?? 0),
            $row['approvalStatus'] ?? 'approved',
            $row['rejectionReason'] ?? null
        );
        $u->setUserId((int) $row['userId']);
        return $u;
    }
}
?>
