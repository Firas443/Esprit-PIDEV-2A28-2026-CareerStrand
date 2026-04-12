<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../FrontOffice/models/Profile.php';

class UserController {
    private $userModel;
    private $profileModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo          = $pdo;
        $this->userModel    = new User($pdo);
        $this->profileModel = new Profile($pdo);
    }

    // ── LIST ALL USERS ──────────────────────────────────────
    public function index() {
        $search = $_GET['search'] ?? '';
        $sort   = $_GET['sort']   ?? 'fullName';
        $order  = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $allowedSort = ['fullName', 'email', 'role', 'status', 'createdAt'];
        if (!in_array($sort, $allowedSort)) $sort = 'fullName';

        if ($search) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM User WHERE fullName LIKE ? OR email LIKE ? ORDER BY $sort $order"
            );
            $stmt->execute(["%$search%", "%$search%"]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM User ORDER BY $sort $order");
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if a user is selected via ?view=id
        $selectedUser = null;
        $selectedProfile = null;
        $selectedSkills  = [];
        $mode = 'view'; // default

        if (isset($_GET['view'])) {
            $selectedUser    = $this->userModel->read($_GET['view']);
            $selectedProfile = $this->profileModel->getByUserId($_GET['view']);
            $selectedSkills  = $this->profileModel->getSkillsByUserId($_GET['view']);
        }

        $errors  = [];
        $success = isset($_GET['updated']);

        include __DIR__ . '/../views/user/index.php';
    }

    // ── SHOW CREATE FORM ────────────────────────────────────
    public function create() {
        $search = $_GET['search'] ?? '';
        $sort   = $_GET['sort']   ?? 'fullName';
        $order  = 'ASC';
        $stmt   = $this->pdo->query("SELECT * FROM User ORDER BY fullName ASC");
        $users  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mode   = 'create';
        $errors = [];
        $old    = [];
        $selectedUser = null;

        include __DIR__ . '/../views/user/index.php';
    }

    // ── STORE NEW USER ──────────────────────────────────────
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowedRoles = ['admin', 'manager', 'manager recruiter', 'user'];
            $submittedRole = $_POST['role'] ?? 'user';
            $data = [
                'fullName' => trim($_POST['fullName'] ?? ''),
                'email'    => trim($_POST['email']    ?? ''),
                'password' => $_POST['password']      ?? '',
                'role'     => in_array($submittedRole, $allowedRoles) ? $submittedRole : 'user',
                'status'   => $_POST['status'] ?? 'active',
            ];
            $result = $this->userModel->create($data);
            if ($result['success']) {
                header('Location: admin-users.php?updated=1');
                exit;
            } else {
                $errors = array_values($result['errors']);
                $old    = $data;
                $stmt   = $this->pdo->query("SELECT * FROM User ORDER BY fullName ASC");
                $users  = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $mode   = 'create';
                $selectedUser = null;
                include __DIR__ . '/../views/user/index.php';
            }
        }
    }

    // ── SHOW EDIT FORM ──────────────────────────────────────
    public function edit($id) {
        $selectedUser = $this->userModel->read($id);
        if (!$selectedUser) {
            header('Location: admin-users.php');
            exit;
        }
        $selectedProfile = $this->profileModel->getByUserId($id);
        $selectedSkills  = $this->profileModel->getSkillsByUserId($id);

        $stmt  = $this->pdo->query("SELECT * FROM User ORDER BY fullName ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mode  = 'edit';
        $errors = [];

        include __DIR__ . '/../views/user/index.php';
    }

    // ── UPDATE USER ─────────────────────────────────────────
    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowedRoles = ['admin', 'manager', 'manager recruiter', 'user'];
            $submittedRole = $_POST['role'] ?? 'user';
            $data = [
                'fullName' => trim($_POST['fullName'] ?? ''),
                'email'    => trim($_POST['email']    ?? ''),
                'role'     => in_array($submittedRole, $allowedRoles) ? $submittedRole : 'user',
                'status'   => $_POST['status'] ?? 'active',
            ];
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }
            $result = $this->userModel->update($id, $data);

            if ($result['success']) {
                header('Location: admin-users.php?view=' . $id . '&updated=1');
                exit;
            } else {
                $errors       = array_values($result['errors']);
                $selectedUser = $this->userModel->read($id);
                $selectedProfile = $this->profileModel->getByUserId($id);
                $selectedSkills  = $this->profileModel->getSkillsByUserId($id);
                $stmt  = $this->pdo->query("SELECT * FROM User ORDER BY fullName ASC");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $mode  = 'edit';
                include __DIR__ . '/../views/user/index.php';
            }
        }
    }

    // ── DELETE USER ─────────────────────────────────────────
    public function destroy($id) {
        $this->userModel->delete($id);
        header('Location: admin-users.php');
        exit;
    }

    // ── SIGNUP (front office) ───────────────────────────────
    public function signup() {
        include __DIR__ . '/../../FrontOffice/views/signup.html';
    }
}
?>