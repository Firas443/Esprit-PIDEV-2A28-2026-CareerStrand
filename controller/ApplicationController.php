<?php
ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "$errstr in $errfile line $errline"]);
    exit;
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(200); exit; }

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../Model/Application.php';

class ApplicationController {
    private Application $model;
    private string $method;
    private int $id;
    private array $body;
    private string $source;

    public function __construct(PDO $db) {
        $this->model  = new Application($db);
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $this->body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->source = $_GET['source'] ?? 'front';
    }

    public function handle(): void {
        // Special case: status update
        if ($this->method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'status') {
            $this->updateStatus();
            return;
        }

        switch ($this->method) {
            case 'GET':    $this->getApplications(); break;
            case 'POST':   $this->createApplication(); break;
            case 'PUT':    $this->updateApplication(); break;
            case 'DELETE': $this->deleteApplication(); break;
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        }
    }

    private function getApplications(): void {
        if ($this->source === 'back') {
            $filters = [];
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
            $data   = $this->model->getWithFilters($filters);
            $counts = $this->model->getStatusCounts();
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $data, 'counts' => $counts]);
        } else {
            $userId = isset($_GET['userId']) ? (int)$_GET['userId'] : 1;
            $data   = $this->model->getByUserId($userId);
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $data]);
        }
    }

    private function createApplication(): void {
        if (empty($this->body)) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Request body is empty.']);
            return;
        }

        $errors = $this->model->validate($this->body);
        if (!empty($errors)) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        $db = $this->model->getDb();

        // Check duplicate
        $check = $db->prepare("SELECT applicationId FROM Application WHERE userId=:u AND opportunityId=:o");
        $check->execute(['u' => $this->body['userId'], 'o' => $this->body['opportunityId']]);
        if ($check->fetch()) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'You have already applied to this opportunity.']);
            return;
        }

        $stmt = $db->prepare("INSERT INTO Application (userId, opportunityId, motivation, portfolio, compatibilityScore, status, appliedAt)
                               VALUES (:userId, :opportunityId, :motivation, :portfolio, :score, 'pending', CURDATE())");
        $stmt->execute([
            'userId'        => $this->body['userId'],
            'opportunityId' => $this->body['opportunityId'],
            'motivation'    => trim($this->body['motivation'] ?? ''),
            'portfolio'     => trim($this->body['portfolio'] ?? '') ?: null,
            'score'         => $this->body['compatibilityScore'] ?? rand(60, 99),
        ]);

        http_response_code(201);
        ob_end_clean();
        echo json_encode(['success' => true, 'applicationId' => (int)$db->lastInsertId(), 'message' => 'Application submitted.']);
    }

    private function updateApplication(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing application ID.']);
            return;
        }

        $existing = $this->model->getById($this->id);
        if (!$existing) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            return;
        }
        if ($existing['status'] !== 'pending') {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Only pending applications can be edited.']);
            return;
        }

        $db   = $this->model->getDb();
        $stmt = $db->prepare("UPDATE Application SET motivation=:motivation, portfolio=:portfolio WHERE applicationId=:id");
        $stmt->execute([
            'motivation' => trim($this->body['motivation'] ?? $existing['motivation']),
            'portfolio'  => isset($this->body['portfolio']) ? (trim($this->body['portfolio']) ?: null) : $existing['portfolio'],
            'id'         => $this->id,
        ]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Application updated.']);
    }

    private function updateStatus(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing application ID.']);
            return;
        }

        $status = $this->body['status'] ?? '';
        if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            return;
        }

        $existing = $this->model->getById($this->id);
        if (!$existing) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            return;
        }

        $db   = $this->model->getDb();
        $stmt = $db->prepare("UPDATE Application SET status=:status WHERE applicationId=:id");
        $stmt->execute(['status' => $status, 'id' => $this->id]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => "Application marked as {$status}."]);
    }

    private function deleteApplication(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing application ID.']);
            return;
        }

        $existing = $this->model->getById($this->id);
        if (!$existing) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            return;
        }
        if ($existing['status'] !== 'pending') {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Only pending applications can be withdrawn.']);
            return;
        }

        $db = $this->model->getDb();
        $db->prepare("DELETE FROM Application WHERE applicationId=:id")->execute(['id' => $this->id]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Application withdrawn.']);
    }
}

try {
    $db         = (new Database())->connect();
    $controller = new ApplicationController($db);
    $controller->handle();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}