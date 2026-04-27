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
require_once __DIR__ . '/../Model/Opportunity.php';

class OpportunityController {
    private Opportunity $model;
    private string $method;
    private int $id;
    private array $body;
    private string $source;

    public function __construct(PDO $db) {
        $this->model  = new Opportunity($db);
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $this->body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->source = $_GET['source'] ?? 'front';
    }
    //check if title exist in database 
private function checkTitle(): void {
    $title     = trim($_GET['title'] ?? '');
    $excludeId = isset($_GET['excludeId']) ? (int)$_GET['excludeId'] : 0;

    if (empty($title)) {
        ob_end_clean();
        echo json_encode(['exists' => false]);
        return;
    }

    $exists = $this->model->titleExists($title, $excludeId);
    ob_end_clean();
    echo json_encode(['exists' => $exists]);
}
    public function handle(): void {
        switch ($this->method) {
            case 'GET':
         if (isset($_GET['action']) && $_GET['action'] === 'checkTitle') {
             $this->checkTitle();
            return;
         }
        $this->getOpportunities();
        break;
            case 'POST':   $this->createOpportunity(); break;
            case 'PUT':    $this->updateOpportunity(); break;
            case 'DELETE': $this->deleteOpportunity(); break;
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        }
    }

    private function getOpportunities(): void {
        $filters = [];
        if (!empty($_GET['status']))        $filters['status']        = $_GET['status'];
        if (!empty($_GET['category']))      $filters['category']      = $_GET['category'];
        if (!empty($_GET['requiredLevel'])) $filters['requiredLevel'] = $_GET['requiredLevel'];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];

        $data = $this->source === 'back'
            ? $this->model->getAll($filters)
            : $this->model->getPublished($filters);

        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    private function createOpportunity(): void {
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

        $db  = $this->model->getDb();

        $dup = $db->prepare("SELECT opportunityId FROM Opportunity WHERE managerId = :managerId AND LOWER(title) = LOWER(:title)");
        $dup->execute(['managerId' => $this->body['managerId'], 'title' => trim($this->body['title'])]);
        if ($dup->fetch()) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => ['An opportunity with this title already exists for your account.']]);
            return;
        }

        $sql  = "INSERT INTO Opportunity (managerId, title, description, type, category, deadline, requiredLevel, status)
                 VALUES (:managerId, :title, :description, :type, :category, :deadline, :requiredLevel, :status)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'managerId'     => $this->body['managerId'],
            'title'         => trim($this->body['title']),
            'description'   => trim($this->body['description']),
            'type'          => $this->body['type'],
            'category'      => $this->body['category'],
            'deadline'      => $this->body['deadline'],
            'requiredLevel' => $this->body['requiredLevel'],
            'status'        => $this->body['status'] ?? 'draft',
        ]);

        http_response_code(201);
        ob_end_clean();
        echo json_encode(['success' => true, 'opportunityId' => (int)$db->lastInsertId(), 'message' => 'Opportunity created.']);
    }

    private function updateOpportunity(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing opportunity ID.']);
            return;
        }
        if (empty($this->body)) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Request body is empty.']);
            return;
        }

        $existing = $this->model->getById($this->id);
        if (!$existing) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Opportunity not found.']);
            return;
        }

        $errors = $this->model->validate($this->body, false);
        if (!empty($errors)) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        $db  = $this->model->getDb();

        $dup = $db->prepare("SELECT opportunityId FROM Opportunity WHERE managerId = :managerId AND LOWER(title) = LOWER(:title) AND opportunityId != :id");
        $dup->execute(['managerId' => $existing['managerId'], 'title' => trim($this->body['title']), 'id' => $this->id]);
        if ($dup->fetch()) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => ['An opportunity with this title already exists for your account.']]);
            return;
        }

        $sql  = "UPDATE Opportunity SET title=:title, description=:description, type=:type,
                 category=:category, deadline=:deadline, requiredLevel=:requiredLevel, status=:status
                 WHERE opportunityId=:id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'title'         => trim($this->body['title']),
            'description'   => trim($this->body['description']),
            'type'          => $this->body['type'],
            'category'      => $this->body['category'],
            'deadline'      => $this->body['deadline'],
            'requiredLevel' => $this->body['requiredLevel'],
            'status'        => $this->body['status'],
            'id'            => $this->id,
        ]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Opportunity updated.']);
    }

    private function deleteOpportunity(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing opportunity ID.']);
            return;
        }

        $existing = $this->model->getById($this->id);
        if (!$existing) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Opportunity not found.']);
            return;
        }

        $db = $this->model->getDb();
        $db->prepare("DELETE FROM Application WHERE opportunityId = :id")->execute(['id' => $this->id]);
        $db->prepare("DELETE FROM Opportunity WHERE opportunityId = :id")->execute(['id' => $this->id]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Opportunity deleted.']);
    }
}

try {
    $db         = (new Database())->connect();
    $controller = new OpportunityController($db);
    $controller->handle();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
