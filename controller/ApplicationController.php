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
        if ($this->method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'summarize') {
            $this->summarizeApplication();
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
            if (!empty($_GET['status']))         $filters['status']         = $_GET['status'];
            if (!empty($_GET['search']))          $filters['search']          = $_GET['search'];
            if (!empty($_GET['searchPosition']))  $filters['searchPosition']  = $_GET['searchPosition'];
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

    private function summarizeApplication(): void {
        if (!$this->id) {
            http_response_code(400);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Missing application ID.']);
            return;
        }

        $app = $this->model->getById($this->id);
        if (!$app) {
            http_response_code(404);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Application not found.']);
            return;
        }

        $apiKey = getenv('HF_TOKEN') ?: (getenv('HUGGINGFACE_API_KEY') ?: '');
        if ($apiKey === '') {
            http_response_code(500);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'HF_TOKEN is not configured on the server.']);
            return;
        }

        $portfolioText = '';
        $portfolioNote = 'No portfolio URL was provided.';
        if (!empty($app['portfolio'])) {
            $portfolioResult = $this->fetchPortfolioText($app['portfolio']);
            $portfolioText = $portfolioResult['text'];
            $portfolioNote = $portfolioResult['note'];
        }

        $input = [
            'applicantName'          => $app['applicantName'] ?? '',
            'opportunityTitle'       => $app['opportunityTitle'] ?? '',
            'opportunityType'        => $app['opportunityType'] ?? '',
            'opportunityDescription' => $app['opportunityDescription'] ?? '',
            'compatibilityScore'     => $app['compatibilityScore'] ?? null,
            'motivation'             => $app['motivation'] ?? '',
            'portfolioUrl'           => $app['portfolio'] ?? '',
            'portfolioFetchNote'     => $portfolioNote,
            'portfolioText'          => $portfolioText,
        ];

        $summary = $this->callHuggingFaceSummary($apiKey, $input);
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $summary]);
    }

    private function fetchPortfolioText(string $url): array {
        $url = trim($url);
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return ['text' => '', 'note' => 'Portfolio URL was not a valid HTTP or HTTPS URL.'];
        }

        $host = $parts['host'];
        $ip = gethostbyname($host);
        if ($ip === $host || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['text' => '', 'note' => 'Portfolio was not fetched because the host could not be safely resolved.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'CareerStrandApplicationReviewer/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,text/plain,application/json;q=0.8,*/*;q=0.2'],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 400) {
            return ['text' => '', 'note' => 'Portfolio could not be fetched' . ($err ? ': ' . $err : '.')];
        }
        if ($type && !preg_match('/(text|html|json|xml)/i', $type)) {
            return ['text' => '', 'note' => 'Portfolio was fetched, but its content type is not text.'];
        }

        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));

        return [
            'text' => mb_substr($text, 0, 6000),
            'note' => $text === '' ? 'Portfolio was fetched but no readable text was found.' : 'Portfolio text was fetched and summarized.',
        ];
    }

    private function callHuggingFaceSummary(string $apiKey, array $input): array {
        $sourceText = $this->buildSummarySourceText($input);

        $payload = [
            'inputs' => $sourceText,
            'parameters' => [
                'min_length' => 40,
                'max_length' => 180,
            ],
        ];

        $model = getenv('HF_SUMMARY_MODEL') ?: 'facebook/bart-large-cnn';
        $modelPath = implode('/', array_map('rawurlencode', explode('/', $model)));
        $result = $this->postHuggingFace(
            'https://router.huggingface.co/hf-inference/models/' . $modelPath,
            $apiKey,
            $payload
        );
        if ($result['code'] < 200 || $result['code'] >= 300) {
            $message = $this->getHuggingFaceErrorMessage($result);
            if (stripos($message, 'Model not supported') !== false) {
                $result = $this->postHuggingFace(
                    'https://api-inference.huggingface.co/models/' . $modelPath,
                    $apiKey,
                    $payload
                );
            }
        }

        if ($result['raw'] === false) {
            throw new Exception('Hugging Face request failed: ' . $result['error']);
        }

        $json = json_decode($result['raw'], true);
        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new Exception($this->getHuggingFaceErrorMessage($result));
        }

        $summaryText = $this->extractHuggingFaceText($json);
        if ($summaryText === '') {
            throw new Exception('Hugging Face returned an empty summary.');
        }

        return $this->buildFitSummary($input, $summaryText);
    }

    private function postHuggingFace(string $url, string $apiKey, array $payload): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['raw' => $raw, 'error' => $err, 'code' => $code];
    }

    private function getHuggingFaceErrorMessage(array $result): string {
        $message = 'Hugging Face API returned HTTP ' . ($result['code'] ?? 0);
        $json = is_string($result['raw']) ? json_decode($result['raw'], true) : null;
        if (is_array($json)) {
            if (isset($json['error']) && is_string($json['error'])) {
                $message = $json['error'];
            } elseif (isset($json['error']['message'])) {
                $message = $json['error']['message'];
            } elseif (isset($json['message'])) {
                $message = is_string($json['message']) ? $json['message'] : json_encode($json['message']);
            }
        } elseif (!empty($result['raw'])) {
            $message .= ': ' . mb_substr((string)$result['raw'], 0, 300);
        }
        return $message;
    }

    private function buildSummarySourceText(array $input): string {
        $portfolioText = trim((string)($input['portfolioText'] ?? ''));
        if ($portfolioText === '') {
            $portfolioText = (string)($input['portfolioFetchNote'] ?? 'No readable portfolio text was available.');
        }

        $text = "Job title: " . ($input['opportunityTitle'] ?? '') . ". ";
        $text .= "Job type: " . ($input['opportunityType'] ?? '') . ". ";
        $text .= "Job description: " . ($input['opportunityDescription'] ?? '') . ". ";
        $text .= "Applicant motivation: " . ($input['motivation'] ?? '') . ". ";
        $text .= "Portfolio evidence: " . $portfolioText . ".";

        return mb_substr(preg_replace('/\s+/', ' ', trim($text)), 0, 5000);
    }

    private function buildFitSummary(array $input, string $summaryText): array {
        $score = is_numeric($input['compatibilityScore'] ?? null) ? (int)$input['compatibilityScore'] : 0;
        if ($score >= 75) {
            $decision = 'Great fit';
            $recommendation = 'Strong candidate based on the compatibility score and summarized application evidence.';
        } elseif ($score >= 50) {
            $decision = 'Possible fit';
            $recommendation = 'Review manually before deciding; the candidate has some relevant signals but may need validation.';
        } else {
            $decision = 'Not a fit';
            $recommendation = 'Consider rejecting unless the portfolio or motivation contains evidence that offsets the low compatibility score.';
        }

        $hasPortfolio = trim((string)($input['portfolioUrl'] ?? '')) !== '';
        $hasPortfolioText = trim((string)($input['portfolioText'] ?? '')) !== '';
        $hasMotivation = trim((string)($input['motivation'] ?? '')) !== '';

        $strengths = [];
        if ($score >= 50) $strengths[] = 'Compatibility score shows at least partial alignment with the opportunity.';
        if ($hasMotivation) $strengths[] = 'The applicant provided motivation for the role.';
        if ($hasPortfolioText) $strengths[] = 'Readable portfolio evidence was available for summarization.';

        $concerns = [];
        if ($score < 75) $concerns[] = 'Compatibility score is below the strongest-fit threshold.';
        if (!$hasPortfolio) $concerns[] = 'No portfolio URL was provided.';
        elseif (!$hasPortfolioText) $concerns[] = $input['portfolioFetchNote'] ?? 'Portfolio text could not be read.';
        if (!$hasMotivation) $concerns[] = 'Motivation text is missing or very limited.';

        return [
            'fitDecision' => $decision,
            'fitScore' => $score,
            'summary' => $summaryText,
            'portfolioSummary' => $hasPortfolio
                ? ($hasPortfolioText ? 'Portfolio content was included in the summary.' : ($input['portfolioFetchNote'] ?? 'Portfolio content could not be summarized.'))
                : 'No portfolio URL was provided.',
            'strengths' => $strengths ?: ['No strong evidence was detected from the available fields.'],
            'concerns' => $concerns ?: ['No major concerns were detected from the available fields.'],
            'recommendation' => $recommendation,
        ];
    }

    private function extractHuggingFaceText(array $response): string {
        if (isset($response[0]['summary_text'])) {
            return (string)$response[0]['summary_text'];
        }
        if (isset($response['summary_text'])) {
            return (string)$response['summary_text'];
        }
        if (isset($response[0]['generated_text'])) {
            return (string)$response[0]['generated_text'];
        }
        if (isset($response['generated_text'])) {
            return (string)$response['generated_text'];
        }
        if (isset($response['choices'][0]['message']['content'])) {
            return (string)$response['choices'][0]['message']['content'];
        }
        return '';
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
