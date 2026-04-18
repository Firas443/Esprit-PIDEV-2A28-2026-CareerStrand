<?php

require_once __DIR__ . '/../config.php';

class UserController
{
    private PDO $pdo;
    private array $columnCache = [];

    public function __construct()
    {
        $this->pdo = config::getConnexion();
    }

    // ── HELPERS ─────────────────────────────────────────────────────────────

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }
        try {
            $query = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :table
                   AND COLUMN_NAME  = :column"
            );
            $query->execute(['table' => $table, 'column' => $column]);
            $exists = (int) $query->fetchColumn() > 0;
            $this->columnCache[$cacheKey] = $exists;
            return $exists;
        } catch (PDOException $e) {
            $this->columnCache[$cacheKey] = false;
            return false;
        }
    }

    // ── STATS ────────────────────────────────────────────────────────────────

    public function getStats(): array
    {
        try {
            return [
                'userCount'    => (int) $this->pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn(),
                'adminCount'   => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(role) = 'admin'")->fetchColumn(),
                'managerCount' => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(role) IN ('manager', 'manager recruiter')")->fetchColumn(),
                'activeCount'  => (int) $this->pdo->query("SELECT COUNT(*) FROM Users WHERE LOWER(status) = 'active'")->fetchColumn(),
            ];
        } catch (PDOException $e) {
            return ['userCount' => 0, 'adminCount' => 0, 'managerCount' => 0, 'activeCount' => 0];
        }
    }

    // ── LIST ALL USERS ───────────────────────────────────────────────────────

    public function getAll(string $search = '', string $sort = 'fullName', string $order = 'ASC'): array
    {
        $allowedSort  = ['fullName', 'email', 'role', 'status', 'createdAt'];
        $allowedOrder = ['ASC', 'DESC'];
        if (!in_array($sort, $allowedSort))   $sort  = 'fullName';
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
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── FIND ONE USER ────────────────────────────────────────────────────────

    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE userId = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getByEmail(string $email): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── AUTHENTICATE ─────────────────────────────────────────────────────────

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->getByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return null;
    }

    // ── CREATE ───────────────────────────────────────────────────────────────

    public function createUser(array $data): array
    {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO Users (fullName, email, password, role, status, createdAt)
                 VALUES (:fullName, :email, :password, :role, 'active', CURDATE())"
            );
            $stmt->execute([
                ':fullName' => $data['fullName'],
                ':email'    => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':role'     => $data['role'],
            ]);
            return ['success' => true, 'userId' => (int) $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'errors' => ['db' => 'Could not create user.']];
        }
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────

    public function updateUser(int $id, array $data): array
    {
        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $setParts = [];
        $params   = [];

        if (isset($data['fullName'])) { $setParts[] = "fullName = ?"; $params[] = $data['fullName']; }
        if (isset($data['email']))    { $setParts[] = "email = ?";    $params[] = $data['email'];    }
        if (!empty($data['password'])) { $setParts[] = "password = ?"; $params[] = password_hash($data['password'], PASSWORD_DEFAULT); }
        if (isset($data['role']))     { $setParts[] = "role = ?";     $params[] = $data['role'];     }
        if (isset($data['status']))   { $setParts[] = "status = ?";   $params[] = $data['status'];   }

        if (empty($setParts)) {
            return ['success' => false, 'errors' => ['empty' => 'No fields to update.']];
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

    // ── DELETE ───────────────────────────────────────────────────────────────

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

    // ── VALIDATION ───────────────────────────────────────────────────────────

    //les controlles de validation sont basiques et peuvent être améliorés pour une meilleure expérience utilisateur

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors       = [];
        $allowedRoles = ['admin', 'manager', 'manager recruiter', 'user'];

        if (empty($data['fullName']) || strlen($data['fullName']) < 2) {
            $errors['fullName'] = 'Full name must be at least 2 characters.';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        } else {
            $sql    = "SELECT userId FROM Users WHERE email = ?" . ($excludeId ? " AND userId != ?" : "");
            $params = [$data['email']];
            if ($excludeId) $params[] = $excludeId;
            $stmt   = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already exists.';
            }
        }
        if ($excludeId) {
            if (isset($data['role']) && !in_array($data['role'], $allowedRoles)) {
                $errors['role'] = 'Invalid role.';
            }
        } else {
            if (empty($data['role']) || !in_array($data['role'], $allowedRoles)) {
                $errors['role'] = 'Invalid role.';
            }
        }

        if (!$excludeId && (empty($data['password']) || strlen($data['password']) < 6)) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        return $errors;
    }
}
