<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        try {
            $db = $this->model->getDb();

            // Add missing columns
            $columns = ['location' => "ALTER TABLE opportunity ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER category",
                        'workMode' => "ALTER TABLE opportunity ADD COLUMN workMode VARCHAR(20) NOT NULL DEFAULT 'onsite' AFTER location"];
            foreach ($columns as $column => $sql) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunity' AND COLUMN_NAME = :column");
                $stmt->execute([':column' => $column]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $db->exec($sql);
                }
            }

            // Widen description from VARCHAR(255) to TEXT if still narrow
            $stmt = $db->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunity' AND COLUMN_NAME = 'description'");
            $stmt->execute();
            $dataType = (string) $stmt->fetchColumn();
            if (in_array(strtolower($dataType), ['varchar', 'char', 'tinytext'], true)) {
                $db->exec("ALTER TABLE opportunity MODIFY description TEXT NOT NULL");
            }
        } catch (Throwable $e) {
        }
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

    private function parseSkills(array $row): array {
        $skills = [];
        if (!empty($row['skillsRaw'])) {
            foreach (explode(';;;', $row['skillsRaw']) as $entry) {
                $parts = explode('|||', $entry);
                if (count($parts) === 3 && $parts[0] !== '') {
                    $skills[] = [
                        'skillName'     => $parts[0],
                        'requiredLevel' => (int)$parts[1],
                        'isPrimary'     => (int)$parts[2],
                    ];
                }
            }
        }
        $row['skills'] = $skills;
        unset($row['skillsRaw']);
        return $row;
    }

    private function getOpportunities(): void {
        $filters = [];
        if (!empty($_GET['status']))        $filters['status']        = $_GET['status'];
        if (!empty($_GET['category']))      $filters['category']      = $_GET['category'];
        if (!empty($_GET['requiredLevel'])) $filters['requiredLevel'] = $_GET['requiredLevel'];
        if (!empty($_GET['search']))        $filters['search']        = $_GET['search'];
        if (!empty($_GET['location']))      $filters['location']      = $_GET['location'];
        if (!empty($_GET['workMode']))      $filters['workMode']      = $_GET['workMode'];

        $rows = $this->source === 'back'
            ? $this->model->getAll($filters)
            : $this->model->getPublished($filters);

        $data = array_map([$this, 'parseSkills'], $rows);

        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    private function createOpportunity(): void {
        $managerId = (int)($_SESSION['userId'] ?? 0);
        if ($managerId <= 0) {
            http_response_code(401);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            return;
        }

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
        $this->model->ensureAutoIncrementId();

        $dup = $db->prepare("SELECT opportunityId FROM opportunity WHERE managerId = :managerId AND LOWER(title) = LOWER(:title)");
        $dup->execute(['managerId' => $managerId, 'title' => trim($this->body['title'])]);
        if ($dup->fetch()) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => ['An opportunity with this title already exists for your account.']]);
            return;
        }

        $location = trim((string) ($this->body['location'] ?? ''));
        $workMode  = strtolower(trim((string) ($this->body['workMode'] ?? 'onsite')));
        if (!in_array($workMode, ['onsite', 'hybrid', 'remote'], true)) {
            $workMode = 'onsite';
        }
        if ($workMode === 'remote') {
            $location = '';
        } elseif ($location === '') {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => ['Location is required for onsite and hybrid opportunities.']]);
            return;
        }

        $sql  = "INSERT INTO opportunity (managerId, title, description, type, category, location, workMode, deadline, requiredLevel, status)
                 VALUES (:managerId, :title, :description, :type, :category, :location, :workMode, :deadline, :requiredLevel, :status)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'managerId'     => $managerId,
            'title'         => trim($this->body['title']),
            'description'   => trim($this->body['description']),
            'type'          => $this->body['type'],
            'category'      => $this->body['category'],
            'location'      => $location !== '' ? $location : null,
            'workMode'      => $workMode,
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

        $bodyWorkMode = strtolower(trim((string) ($this->body['workMode'] ?? ($existing['workMode'] ?? 'onsite'))));
        $existingWorkMode = strtolower(trim((string) ($existing['workMode'] ?? 'onsite')));
        $statusOnly = trim((string) ($this->body['title'] ?? '')) === trim((string) ($existing['title'] ?? ''))
            && trim((string) ($this->body['description'] ?? '')) === trim((string) ($existing['description'] ?? ''))
            && trim((string) ($this->body['type'] ?? '')) === trim((string) ($existing['type'] ?? ''))
            && trim((string) ($this->body['category'] ?? '')) === trim((string) ($existing['category'] ?? ''))
            && trim((string) ($this->body['requiredLevel'] ?? '')) === trim((string) ($existing['requiredLevel'] ?? ''))
            && trim((string) ($this->body['deadline'] ?? '')) === trim((string) ($existing['deadline'] ?? ''))
            && $bodyWorkMode === $existingWorkMode
            && trim((string) ($this->body['location'] ?? '')) === trim((string) ($existing['location'] ?? ''));

        $errors = $this->model->validate($this->body, false);
        if ($statusOnly) {
            $errors = array_values(array_filter($errors, static fn(string $error): bool => stripos($error, 'location is required') === false));
        }
        if (!empty($errors)) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        $db  = $this->model->getDb();

        $dup = $db->prepare("SELECT opportunityId FROM opportunity WHERE managerId = :managerId AND LOWER(title) = LOWER(:title) AND opportunityId != :id");
        $dup->execute(['managerId' => $existing['managerId'], 'title' => trim($this->body['title']), 'id' => $this->id]);
        if ($dup->fetch()) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => ['An opportunity with this title already exists for your account.']]);
            return;
        }

        $location = trim((string) ($this->body['location'] ?? ($existing['location'] ?? '')));
        $workMode = $bodyWorkMode;
        if (!in_array($workMode, ['onsite', 'hybrid', 'remote'], true)) {
            $workMode = 'onsite';
        }
        if ($workMode === 'remote') {
            $location = '';
        } elseif ($location === '') {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => ['Location is required for onsite and hybrid opportunities.']]);
            return;
        }

        $sql  = "UPDATE opportunity SET title=:title, description=:description, type=:type,
                 category=:category, location=:location, workMode=:workMode, deadline=:deadline, requiredLevel=:requiredLevel, status=:status
                 WHERE opportunityId=:id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'title'         => trim($this->body['title']),
            'description'   => trim($this->body['description']),
            'type'          => $this->body['type'],
            'category'      => $this->body['category'],
            'location'      => $location !== '' ? $location : null,
            'workMode'      => $workMode,
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
       $db->prepare("DELETE FROM opportunity_skill WHERE opportunityId = :id")->execute(['id' => $this->id]);
$db->prepare("DELETE FROM Application WHERE opportunityId = :id")->execute(['id' => $this->id]);
$db->prepare("DELETE FROM opportunity WHERE opportunityId = :id")->execute(['id' => $this->id]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Opportunity deleted.']);
    }
}

try {
    $db         = config::getConnexion();
    $controller = new OpportunityController($db);
    $controller->handle();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
