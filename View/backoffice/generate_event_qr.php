<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

function ensureQrTokenColumn(PDO $db): void
{
    $sql = "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'event'
              AND COLUMN_NAME = 'qrToken'";
    $exists = (int)$db->query($sql)->fetchColumn() > 0;
    if (!$exists) {
        $db->exec("ALTER TABLE event ADD COLUMN qrToken VARCHAR(255) DEFAULT NULL");
    }
}

function buildScanUrl(int $eventId, string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $frontDir = str_replace('/View/BackOffice', '/View/FrontOffice', $scriptDir);
    return $scheme . '://' . $host . $frontDir . '/scan_qr.php?eventId=' . $eventId . '&token=' . urlencode($token);
}

try {
    $eventId = isset($_GET['eventId']) ? (int)$_GET['eventId'] : 0;
    $forceGenerate = isset($_GET['generate']) && $_GET['generate'] === '1';

    if ($eventId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Event ID missing.']);
        exit;
    }

    $db = config::getConnexion();
    ensureQrTokenColumn($db);

    $stmt = $db->prepare("SELECT eventId, title, qrToken FROM event WHERE eventId = :id LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Event not found.']);
        exit;
    }

    $token = $event['qrToken'] ?? '';
    if ($forceGenerate || empty($token)) {
        $token = bin2hex(random_bytes(32));
        $update = $db->prepare("UPDATE event SET qrToken = :token WHERE eventId = :id");
        $update->execute([':token' => $token, ':id' => $eventId]);
    }

    $scanUrl = buildScanUrl($eventId, $token);
    $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($scanUrl);

    echo json_encode([
        'success' => true,
        'eventId' => $eventId,
        'title' => $event['title'],
        'token' => $token,
        'scanUrl' => $scanUrl,
        'qrImageUrl' => $qrImageUrl,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
