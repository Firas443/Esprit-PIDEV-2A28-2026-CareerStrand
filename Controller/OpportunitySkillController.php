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

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/OpportunitySkill.php';

class OpportunitySkillController {
    private OpportunitySkill $model;
    private string $method;
    private int    $id;
    private array  $body;

    public function __construct(PDO $db) {
        $this->model  = new OpportunitySkill($db);
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $this->body   = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    public function handle(): void {
        switch ($this->method) {
            case 'GET':    $this->getSkills();   break;
            case 'POST':   $this->createSkill(); break;
            case 'PUT':    $this->updateSkill(); break;
            case 'DELETE': $this->deleteSkill(); break;
            default:
                http_response_code(405);
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        }
    }

    private function getSkills(): void {
        if ($this->id) {
            $skill = $this->model->getById($this->id);
            if (!$skill) {
                http_response_code(404);
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Skill not found.']);
                return;
            }
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $skill]);
            return;
        }

        $filters = [];
        if (!empty($_GET['opportunityId'])) $filters['opportunityId'] = (int)$_GET['opportunityId'];
        if (!empty($_GET['skillName']))      $filters['skillName']     = $_GET['skillName'];
        if (isset($_GET['isPrimary']))       $filters['isPrimary']     = (int)$_GET['isPrimary'];

        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $this->model->getAll($filters)]);
    }

    private function createSkill(): void {
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

        $db   = $this->model->getDb();
        $stmt = $db->prepare(
            "INSERT INTO opportunity_skill (opportunityId, skillName, requiredLevel, weight, isPrimary)
             VALUES (:opportunityId, :skillName, :requiredLevel, :weight, :isPrimary)"
        );
        $stmt->execute([
            'opportunityId' => (int)$this->body['opportunityId'],
            'skillName'     => trim($this->body['skillName']),
            'requiredLevel' => (int)$this->body['requiredLevel'],
            'weight'        => isset($this->body['weight'])    ? (float)$this->body['weight']    : 1.0,
            'isPrimary'     => isset($this->body['isPrimary']) ? (int)$this->body['isPrimary']   : 0,
        ]);

        http_response_code(201);
        ob_end_clean();
        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Skill added.']);
    }

    private function updateSkill(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing skill ID.']);
            return;
        }
        if (empty($this->body)) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Request body is empty.']);
            return;
        }

        if (!$this->model->getById($this->id)) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Skill not found.']);
            return;
        }

        $errors = $this->model->validate($this->body, false);
        if (!empty($errors)) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        $fields = [];
        $params = ['id' => $this->id];

        if (isset($this->body['skillName']))     { $fields[] = 'skillName = :skillName';        $params['skillName']     = trim($this->body['skillName']); }
        if (isset($this->body['requiredLevel'])) { $fields[] = 'requiredLevel = :requiredLevel'; $params['requiredLevel'] = (int)$this->body['requiredLevel']; }
        if (isset($this->body['weight']))        { $fields[] = 'weight = :weight';               $params['weight']        = (float)$this->body['weight']; }
        if (isset($this->body['isPrimary']))     { $fields[] = 'isPrimary = :isPrimary';         $params['isPrimary']     = (int)$this->body['isPrimary']; }

        if (empty($fields)) {
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Nothing to update.']);
            return;
        }

        $this->model->getDb()->prepare(
            "UPDATE opportunity_skill SET " . implode(', ', $fields) . " WHERE id = :id"
        )->execute($params);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Skill updated.']);
    }

    private function deleteSkill(): void {
        // Delete all skills for an opportunity (used before re-saving)
        if (!$this->id && !empty($_GET['opportunityId'])) {
            $oppId = (int)$_GET['opportunityId'];
            $this->model->getDb()->prepare(
                "DELETE FROM opportunity_skill WHERE opportunityId = :oppId"
            )->execute(['oppId' => $oppId]);
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Skills cleared.']);
            return;
        }

        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing skill ID.']);
            return;
        }

        if (!$this->model->getById($this->id)) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Skill not found.']);
            return;
        }

        $this->model->getDb()->prepare(
            "DELETE FROM opportunity_skill WHERE id = :id"
        )->execute(['id' => $this->id]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Skill deleted.']);
    }
}

try {
    $db         = (new Database())->connect();
    $controller = new OpportunitySkillController($db);
    $controller->handle();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
