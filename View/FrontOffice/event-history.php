<?php
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../Controller/ParticipationController.php';

$frontUser = requireFrontUser();
$participationC = new ParticipationController();
$history = $participationC->getHistoryByUser((int) $frontUser['userId']);

function formatHistoryDate(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTime($value))->format('d M Y');
    } catch (Throwable $e) {
        return $value;
    }
}

function statusClass(string $status): string
{
    return match (strtolower($status)) {
        'confirmed' => 'badge badge-new',
        'pending' => 'badge badge-closing',
        'cancelled' => 'badge badge-remote',
        default => 'badge badge-new',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Attendance History - CareerStrand</title>
  <link rel="stylesheet" href="assets/css/events.css" />
  <link rel="stylesheet" href="assets/css/front-nav.css" />
  <style>
    .history-shell { padding: 40px 0 72px; }
    .history-grid { display: grid; gap: 14px; margin-top: 22px; }
    .history-card {
      border-radius: 24px;
      padding: 22px;
      background: linear-gradient(180deg, rgba(12,22,46,.92), rgba(7,13,29,.96));
      border: 1px solid rgba(126,159,228,.14);
      box-shadow: 0 14px 40px rgba(0,0,0,.18);
    }
    .history-top { display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;margin-bottom:14px; }
    .history-title { font-size:18px;font-weight:800;letter-spacing:-.03em;color:var(--white);margin-bottom:4px; }
    .history-sub { color:var(--muted);font-size:13px;line-height:1.6; }
    .history-meta { display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-top:14px; }
    .history-item { padding:12px 14px;border-radius:16px;background:rgba(111,143,216,.07);border:1px solid rgba(126,159,228,.09); }
    .history-item span { display:block;font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:var(--muted-2);margin-bottom:6px; }
    .history-item strong { color:var(--white);font-size:13px;line-height:1.5; }
    .history-empty {
      padding: 36px 20px;
      text-align: center;
      border-radius: 24px;
      background: rgba(12,22,46,.72);
      border: 1px solid rgba(126,159,228,.12);
      color: var(--muted);
    }
  </style>
</head>
<body>
<?php
  $activePage = 'events';
  $brandSubtitle = 'attendance history';
  include __DIR__ . '/partials/front-nav.php';
?>

<main class="history-shell">
  <div class="container">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
      <div>
        <div class="page-eyebrow"><span class="dot"></span> event participation record</div>
        <h1 style="font-size:clamp(32px,5vw,48px);font-weight:900;letter-spacing:-.05em;margin-top:10px">Attendance History</h1>
        <p style="margin-top:10px;color:var(--muted);max-width:720px;line-height:1.75">
          Every event you checked into through QR attendance, with the date, status, and scan timestamp when available.
        </p>
      </div>
      <a class="btn btn-ghost" href="events.php">Back to events</a>
    </div>

    <?php if (empty($history)): ?>
      <div class="history-empty" style="margin-top:24px">
        <h3 style="margin:0 0 8px;color:var(--white)">No attendance history yet</h3>
        <p style="margin:0">Join an event and check in with QR to start building your record here.</p>
      </div>
    <?php else: ?>
      <div class="history-grid">
        <?php foreach ($history as $item): ?>
          <?php
            $status = (string) ($item['attendanceStatus'] ?? $item['status'] ?? 'Pending');
            $eventTitle = trim((string) ($item['eventTitle'] ?? ''));
            $eventLocation = trim((string) ($item['eventLocation'] ?? ''));
            $eventType = trim((string) ($item['eventType'] ?? ''));
            $eventDate = formatHistoryDate($item['eventDate'] ?? null);
            $registered = formatHistoryDate($item['registrationDate'] ?? null);
            $scanAt = $item['attendanceScanAt'] ? (new DateTime($item['attendanceScanAt']))->format('d M Y, H:i') : 'Not scanned';
          ?>
          <article class="history-card">
            <div class="history-top">
              <div>
                <div class="history-title"><?= htmlspecialchars($eventTitle !== '' ? $eventTitle : 'Event #' . (int) ($item['eventId'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="history-sub">
                  <?= htmlspecialchars($eventType !== '' ? $eventType : 'Event', ENT_QUOTES, 'UTF-8'); ?>
                  <?php if ($eventLocation !== ''): ?>
                    &middot; <?= htmlspecialchars($eventLocation, ENT_QUOTES, 'UTF-8'); ?>
                  <?php endif; ?>
                </div>
              </div>
              <span class="<?= htmlspecialchars(statusClass($status), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="history-meta">
              <div class="history-item">
                <span>Event date</span>
                <strong><?= htmlspecialchars($eventDate, ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
              <div class="history-item">
                <span>Registered on</span>
                <strong><?= htmlspecialchars($registered, ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
              <div class="history-item">
                <span>QR check-in</span>
                <strong><?= htmlspecialchars($scanAt, ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
              <div class="history-item">
                <span>Attendance status</span>
                <strong><?= htmlspecialchars(ucfirst(strtolower($status)), ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
