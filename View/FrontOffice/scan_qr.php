<?php
require_once __DIR__ . '/../../config.php';

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

function ensureAttendanceScanColumn(PDO $db): void
{
    $sql = "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'participation'
              AND COLUMN_NAME = 'attendanceScanAt'";
    $exists = (int)$db->query($sql)->fetchColumn() > 0;
    if (!$exists) {
        $db->exec("ALTER TABLE participation ADD COLUMN attendanceScanAt DATETIME DEFAULT NULL");
    }
}

function pageMessage(string $type, string $title, string $message): void
{
    $color = $type === 'success' ? '#59d39b' : ($type === 'info' ? '#95abeb' : '#ff6e45');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>QR Attendance</title>';
    echo '<style>
        :root{--bg:#040816;--bg2:#071126;--text:#f5f3ee;--muted:rgba(245,243,238,.7);--blue:#6f8fd8;--red:#ff6e45;--green:#59d39b}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Inter,Arial,sans-serif;color:var(--text);background:radial-gradient(circle at top left,rgba(111,143,216,.18),transparent 26%),linear-gradient(135deg,#02050f,#071126 48%,#0b1022)}
        .card{width:min(520px,calc(100% - 32px));padding:34px;border-radius:28px;background:linear-gradient(180deg,rgba(12,22,46,.96),rgba(7,13,29,.98));border:1px solid rgba(126,159,228,.18);box-shadow:0 34px 90px rgba(0,0,0,.45)}
        .badge{width:58px;height:58px;border-radius:20px;display:grid;place-items:center;margin-bottom:18px;background:rgba(111,143,216,.12);border:1px solid rgba(126,159,228,.18);color:' . $color . ';font-size:28px}
        h1{margin:0;font-size:28px;letter-spacing:-.04em}p{color:var(--muted);line-height:1.75;margin:12px 0 0}.btn{display:inline-flex;margin-top:24px;padding:13px 18px;border-radius:999px;color:var(--text);text-decoration:none;background:linear-gradient(90deg,var(--blue),var(--red));font-weight:800}
    </style></head><body><div class="card">';
    echo '<div class="badge">' . ($type === 'success' ? '✓' : '!') . '</div>';
    echo '<h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
    echo '<a class="btn" href="events.php">Back to Events</a></div></body></html>';
}

$eventId = isset($_GET['eventId']) ? (int)$_GET['eventId'] : 0;
$token = trim($_GET['token'] ?? '');

try {
    $db = config::getConnexion();
    ensureQrTokenColumn($db);
    ensureAttendanceScanColumn($db);

    if ($eventId <= 0 || $token === '') {
        pageMessage('error', 'Invalid QR Code', 'This attendance QR code is missing required information.');
        exit;
    }

    $eventStmt = $db->prepare("SELECT eventId, title, qrToken FROM event WHERE eventId = :eventId LIMIT 1");
    $eventStmt->execute([':eventId' => $eventId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || empty($event['qrToken']) || !hash_equals($event['qrToken'], $token)) {
        pageMessage('error', 'Invalid QR Code', 'This QR code is not valid for the selected event.');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QR Attendance</title>
  <style>
    :root{--bg:#040816;--text:#f5f3ee;--muted:rgba(245,243,238,.7);--blue:#6f8fd8;--red:#ff6e45}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Inter,Arial,sans-serif;color:var(--text);background:radial-gradient(circle at top left,rgba(111,143,216,.18),transparent 26%),linear-gradient(135deg,#02050f,#071126 48%,#0b1022)}
    .card{width:min(520px,calc(100% - 32px));padding:34px;border-radius:28px;background:linear-gradient(180deg,rgba(12,22,46,.96),rgba(7,13,29,.98));border:1px solid rgba(126,159,228,.18);box-shadow:0 34px 90px rgba(0,0,0,.45)}
    h1{margin:0;font-size:28px;letter-spacing:-.04em}.event{margin-top:10px;color:var(--muted);line-height:1.7}
    form{display:grid;gap:14px;margin-top:24px}label{font-size:11px;text-transform:uppercase;letter-spacing:.18em;color:rgba(245,243,238,.48)}input{width:100%;border:1px solid rgba(126,159,228,.16);border-radius:16px;padding:14px 16px;background:rgba(111,143,216,.08);color:var(--text);outline:none}button{border:none;border-radius:999px;padding:14px 18px;cursor:pointer;background:linear-gradient(90deg,var(--blue),var(--red));color:var(--text);font-weight:800}
  </style>
</head>
<body>
  <div class="card">
    <h1>Confirm Attendance</h1>
    <div class="event">Event: <strong><?= htmlspecialchars($event['title']) ?></strong><br>Please enter the same name and email used on the platform.</div>
    <form method="POST">
      <div><label>Full Name</label><input type="text" name="name" required></div>
      <div><label>Email</label><input type="email" name="email" required></div>
      <button type="submit">Confirm Attendance</button>
    </form>
  </div>
</body>
</html>
        <?php
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        pageMessage('error', 'Missing Information', 'Please enter your full name and email to confirm attendance.');
        exit;
    }

    $userStmt = $db->prepare("SELECT userId, fullName, email FROM user WHERE fullName = :name AND email = :email LIMIT 1");
    $userStmt->execute([':name' => $name, ':email' => $email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        pageMessage('error', 'User Not Found', 'No platform user matches this full name and email.');
        exit;
    }

    $partStmt = $db->prepare("SELECT participationId, attendanceStatus, status, attendanceScanAt FROM participation WHERE userId = :userId AND eventId = :eventId AND status != 'Cancelled' ORDER BY participationId DESC LIMIT 1");
    $partStmt->execute([':userId' => (int)$user['userId'], ':eventId' => $eventId]);
    $participation = $partStmt->fetch(PDO::FETCH_ASSOC);

    if (!$participation) {
        pageMessage('error', 'Not Registered', 'You are not registered for this event, so attendance cannot be confirmed.');
        exit;
    }

    if (!empty($participation['attendanceScanAt'])) {
        pageMessage('info', 'Already Confirmed', 'Your attendance for this event was already confirmed.');
        exit;
    }

    $update = $db->prepare("UPDATE participation SET attendanceStatus = 'Confirmed', status = 'Confirmed', attendanceScanAt = NOW() WHERE participationId = :participationId");
    $update->execute([':participationId' => (int)$participation['participationId']]);

    pageMessage('success', 'Attendance Confirmed', 'Attendance confirmed successfully for ' . $event['title'] . '.');
} catch (Exception $e) {
    pageMessage('error', 'Server Error', $e->getMessage());
}
?>
