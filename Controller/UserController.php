<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/User.php';

class UserController
{
    private PDO   $pdo;
    private array $columnCache = [];

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    // ── STATS ────────────────────────────────────────────

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

    // ── LIST ALL ─────────────────────────────────────────

    public function getAll(string $search = '', string $sort = 'fullName', string $order = 'ASC'): array
    {
        $allowedSort  = ['fullName', 'email', 'role', 'status', 'createdAt'];
        $allowedOrder = ['ASC', 'DESC'];
        if (!in_array($sort,  $allowedSort))              $sort  = 'fullName';
        if (!in_array(strtoupper($order), $allowedOrder)) $order = 'ASC';

        try {
            if ($search !== '') {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM Users
                     WHERE fullName LIKE :s OR email LIKE :s2
                     ORDER BY $sort $order"
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

    // ── FIND ONE ─────────────────────────────────────────

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

    // ── AUTHENTICATE ─────────────────────────────────────

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->getByEmail($email);
        if ($user && password_verify($password, $user->getPassword())) {
            return $user;
        }
        return null;
    }

    // ── CREATE ───────────────────────────────────────────

    public function createUser(array $data): array
    {
        $user = new User(
            trim($data['fullName'] ?? ''),
            trim($data['email']    ?? ''),
            $data['password'] ?? '',
            $data['role']     ?? 'user',
            'active',
            date('Y-m-d')
        );

        $errors = $this->validate($user);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO Users (fullName, email, password, role, status, createdAt)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user->getFullName(),
                $user->getEmail(),
                password_hash($user->getPassword(), PASSWORD_DEFAULT),
                $user->getRole(),
                $user->getStatus(),
                $user->getCreatedAt(),
            ]);
            return ['success' => true, 'userId' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not create user.']];
        }
    }

    // ── UPDATE ───────────────────────────────────────────

    public function updateUser(int $id, array $data): array
    {
        $existing = $this->getById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['id' => 'User not found.']];
        }

        // Build updated User object from merged data
        $user = new User(
            trim($data['fullName'] ?? $existing->getFullName()),
            trim($data['email']    ?? $existing->getEmail()),
            $data['password'] ?? '',          // blank = no change
            $data['role']     ?? $existing->getRole(),
            $data['status']   ?? $existing->getStatus(),
            $existing->getCreatedAt()
        );
        $user->setUserId($id);

        $errors = $this->validate($user, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $setParts = [
            'fullName = ?',
            'email    = ?',
            'role     = ?',
            'status   = ?',
        ];
        $params = [
            $user->getFullName(),
            $user->getEmail(),
            $user->getRole(),
            $user->getStatus(),
        ];

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

    // ── DELETE ───────────────────────────────────────────

    public function deleteUser(int $id): array
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Users WHERE userId = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not delete user.']];
        }
    }

    // ── VALIDATION ───────────────────────────────────────

    private function validate(User $user, ?int $excludeId = null): array
    {
        $errors       = [];
        $allowedRoles = ['admin', 'manager', 'manager recruiter', 'user'];

        if (strlen($user->getFullName()) < 2) {
            $errors['fullName'] = 'Full name must be at least 2 characters.';
        }
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
        if (!in_array($user->getRole(), $allowedRoles)) {
            $errors['role'] = 'Invalid role.';
        }
        // Password required only on create (excludeId === null)
        if ($excludeId === null && strlen($user->getPassword()) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }
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
            $row['createdAt'] ?? ''
        );
        $u->setUserId((int) $row['userId']);
        return $u;
    }
}
?>
