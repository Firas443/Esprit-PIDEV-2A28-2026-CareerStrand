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

class UserSkillController {
    private PDO    $db;
    private string $method;
    private int    $id;
    private array  $body;

    public function __construct(PDO $db) {
        $this->db     = $db;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $this->body   = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $db->exec("ALTER TABLE UserSkill ADD COLUMN IF NOT EXISTS assessmentData TEXT NULL");
        } catch (\PDOException $e) {}
    }

    public function handle(): void {
        switch ($this->method) {
            case 'GET':    $this->get();    break;
            case 'POST':   $this->create(); break;
            case 'PUT':    $this->update(); break;
            case 'DELETE': $this->delete(); break;
            default:
                http_response_code(405);
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        }
    }

    private function get(): void {
        // GET by userId → returns all skills for that user
        // GET by id     → returns one skill
        if (!empty($_GET['userId'])) {
            $includePending = !empty($_GET['includePending']);
            $approvalFilter = $includePending ? '' : " AND LOWER(COALESCE(approvalStatus, 'approved')) = 'approved'";
            $stmt = $this->db->prepare(
                "SELECT userSkillId, userId, skillName, level, level AS skillLevel, source, certificateUrl, validatedAt,
                        skillCatalogId, approvalStatus, reviewedBy, reviewedAt, reviewNote
                 FROM UserSkill
                 WHERE userId = :userId" . $approvalFilter . "
                 ORDER BY skillName"
            );
            $stmt->execute(['userId' => (int)$_GET['userId']]);
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($this->id) {
            $stmt = $this->db->prepare(
                "SELECT userSkillId, userId, skillName, level, level AS skillLevel, source, certificateUrl, validatedAt,
                        skillCatalogId, approvalStatus, reviewedBy, reviewedAt, reviewNote
                 FROM UserSkill
                 WHERE userSkillId = :id"
            );
            $stmt->execute(['id' => $this->id]);
            $row  = $stmt->fetch();
            ob_end_clean();
            if (!$row) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Skill not found.']); return; }
            echo json_encode(['success' => true, 'data' => $row]);
            return;
        }

        http_response_code(400);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Provide id or userId.']);
    }

    private function create(): void {
        $errors = $this->validate($this->body);
        if (!empty($errors)) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        // Prevent duplicate skill per user
        $check = $this->db->prepare(
            "SELECT userSkillId
             FROM UserSkill
             WHERE userId = :u
               AND LOWER(TRIM(skillName)) = LOWER(TRIM(:s))
               AND LOWER(COALESCE(approvalStatus, 'approved')) <> 'rejected'"
        );
        $check->execute(['u' => $this->body['userId'], 's' => trim($this->body['skillName'])]);
        if ($check->fetch()) {
            http_response_code(422);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'User already has this skill.']);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO UserSkill (userId, skillName, source, level, certificateUrl, validatedAt, approvalStatus, assessmentData)
             VALUES (:userId, :skillName, :source, :skillLevel, :certificateUrl, :validatedAt, :approvalStatus, :assessmentData)"
        );
        $stmt->execute([
            'userId'         => (int)$this->body['userId'],
            'skillName'      => trim($this->body['skillName']),
            'source'         => $this->body['source']         ?? null,
            'skillLevel'     => isset($this->body['skillLevel']) ? (int)$this->body['skillLevel'] : null,
            'certificateUrl' => $this->body['certificateUrl'] ?? null,
            'validatedAt'    => $this->body['validatedAt']    ?? null,
            'approvalStatus' => $this->body['approvalStatus'] ?? 'pending',
            'assessmentData' => isset($this->body['assessmentData']) ? (string)$this->body['assessmentData'] : null,
        ]);

        http_response_code(201);
        ob_end_clean();
        echo json_encode(['success' => true, 'userSkillId' => (int)$this->db->lastInsertId()]);
    }

    private function update(): void {
        if (!$this->id) {
            http_response_code(400); ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing skill ID.']);
            return;
        }

        $fields = [];
        $params = ['id' => $this->id];

        if (isset($this->body['skillName']))      { $fields[] = 'skillName = :skillName';           $params['skillName']      = trim($this->body['skillName']); }
        if (isset($this->body['source']))         { $fields[] = 'source = :source';                 $params['source']         = $this->body['source']; }
        if (isset($this->body['skillLevel']))     { $fields[] = 'level = :skillLevel';              $params['skillLevel']     = (int)$this->body['skillLevel']; }
        if (isset($this->body['certificateUrl'])) { $fields[] = 'certificateUrl = :certificateUrl'; $params['certificateUrl'] = $this->body['certificateUrl']; }
        if (isset($this->body['validatedAt']))    { $fields[] = 'validatedAt = :validatedAt';       $params['validatedAt']    = $this->body['validatedAt']; }
        if (isset($this->body['skillCatalogId'])) { $fields[] = 'skillCatalogId = :skillCatalogId'; $params['skillCatalogId'] = (int)$this->body['skillCatalogId']; }
        if (isset($this->body['approvalStatus'])) { $fields[] = 'approvalStatus = :approvalStatus'; $params['approvalStatus'] = $this->body['approvalStatus']; }
        if (isset($this->body['reviewedBy']))     { $fields[] = 'reviewedBy = :reviewedBy';         $params['reviewedBy']     = (int)$this->body['reviewedBy']; }
        if (isset($this->body['reviewedAt']))     { $fields[] = 'reviewedAt = :reviewedAt';         $params['reviewedAt']     = $this->body['reviewedAt']; }
        if (isset($this->body['reviewNote']))     { $fields[] = 'reviewNote = :reviewNote';         $params['reviewNote']     = $this->body['reviewNote']; }

        if (empty($fields)) {
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Nothing to update.']);
            return;
        }

        $this->db->prepare(
            "UPDATE UserSkill SET " . implode(', ', $fields) . " WHERE userSkillId = :id"
        )->execute($params);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Skill updated.']);
    }

    private function delete(): void {
        if (!$this->id) {
            http_response_code(400); ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing skill ID.']);
            return;
        }

        $this->db->prepare(
            "DELETE FROM UserSkill WHERE userSkillId = :id"
        )->execute(['id' => $this->id]);

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Skill deleted.']);
    }

    private function validate(array $data): array {
        $errors = [];
        if (empty($data['userId']))    $errors[] = 'userId is required.';
        if (empty($data['skillName'])) $errors[] = 'skillName is required.';
        if (isset($data['skillLevel']) && (!is_numeric($data['skillLevel']) || $data['skillLevel'] < 0))
            $errors[] = 'skillLevel must be a non-negative integer.';
        return $errors;
    }
}

try {
    $db         = config::getConnexion();
    $controller = new UserSkillController($db);
    $controller->handle();
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
