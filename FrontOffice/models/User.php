<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($data) {
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO User (fullName, email, password, role, status, createdAt) VALUES (?, ?, ?, ?, 'active', CURDATE())");
        $stmt->execute([$data['fullName'], $data['email'], $hashedPassword, $data['role']]);
        return ['success' => true, 'userId' => $this->pdo->lastInsertId()];
    }

    public function read($id = null) {
        if ($id) {
            $stmt = $this->pdo->prepare("SELECT * FROM User WHERE userId = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM User");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function update($id, $data) {
        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $setParts = [];
        $params = [];
        if (isset($data['fullName'])) {
            $setParts[] = "fullName = ?";
            $params[] = $data['fullName'];
        }
        if (isset($data['email'])) {
            $setParts[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $setParts[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['role'])) {
            $setParts[] = "role = ?";
            $params[] = $data['role'];
        }
        if (isset($data['status'])) {
            $setParts[] = "status = ?";
            $params[] = $data['status'];
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare("UPDATE User SET " . implode(', ', $setParts) . " WHERE userId = ?");
        $stmt->execute($params);
        return ['success' => true];
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM User WHERE userId = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }

    public function authenticate($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM User WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }

        return false;
    }

    public function getByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM User WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function validate($data, $id = null) {
        $errors = [];
        if (empty($data['fullName']) || strlen($data['fullName']) < 2) {
            $errors['fullName'] = 'Full name must be at least 2 characters.';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        } else {
            // Check unique email
            $stmt = $this->pdo->prepare("SELECT userId FROM User WHERE email = ? " . ($id ? "AND userId != ?" : ""));
            $params = [$data['email']];
            if ($id) $params[] = $id;
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already exists.';
            }
        }
        if (empty($data['role']) || !in_array($data['role'], ['admin', 'manager', 'manager recruiter', 'user'])) {
            $errors['role'] = 'Invalid role.';
        }
        if (!$id && (empty($data['password']) || strlen($data['password']) < 6)) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }
        return $errors;
    }
}
?>