<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../Controller/MentorshipRequestController.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}

$adminUser = $_SESSION['user'];
if (($adminUser['role'] ?? '') !== 'admin') {
    header('Location: ../FrontOffice/profile.php');
    exit;
}

$requestController = new MentorshipRequestController();
$adminId = (int) ($adminUser['userId'] ?? 0);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mrStatusLabel(string $status): string
{
    return match (strtolower(trim($status))) {
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}

function mrBadgeClass(string $status): string
{
    return match (strtolower(trim($status))) {
        'pending' => 'pending',
        'accepted', 'scheduled' => 'accepted',
        'declined', 'cancelled' => 'declined',
        'completed' => 'completed',
        default => 'pending',
    };
}

function mrRequestTypeLabel(string $value): string
{
    return match ($value) {
        'mentor_chat' => 'Mentor chat',
        'office_hours' => 'Office hours',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
}

function mrSessionModeLabel(string $value): string
{
    return match ($value) {
        'chat' => 'Chat',
        'video' => 'Video call',
        'in_person' => 'In person',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
}

function mrSourceLabel(array $request): string
{
    $type = strtolower(trim((string) ($request['sourceType'] ?? 'direct')));
    $id = (int) ($request['sourceId'] ?? 0);

    return match ($type) {
        'profile' => $id > 0 ? 'Profile #' . $id : 'Profile context',
        'hub' => $id > 0 ? 'Hub #' . $id : 'Hub context',
        'event' => $id > 0 ? 'Event #' . $id : 'Event context',
        default => 'Direct request',
    };
}

$requestError = '';
$requestSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'respond_request') {
        $requestId = (int) ($_POST['requestId'] ?? 0);
        $result = $requestController->respondToRequest($requestId, $adminId, [
            'status' => (string) ($_POST['status'] ?? 'accepted'),
            'responseNote' => (string) ($_POST['responseNote'] ?? ''),
            'scheduledDate' => (string) ($_POST['scheduledDate'] ?? ''),
            'scheduledTime' => (string) ($_POST['scheduledTime'] ?? ''),
            'meetingLink' => (string) ($_POST['meetingLink'] ?? ''),
        ]);

        if (!empty($result['success'])) {
            header('Location: admin-mentorship-requests.php?requestId=' . $requestId . '&status=updated');
            exit;
        }

        $requestError = implode(' ', array_values(array_filter($result['errors'] ?? ['db' => 'Could not update the request.'])));
    }

    if ($action === 'send_message') {
        $requestId = (int) ($_POST['requestId'] ?? 0);
        $message = trim((string) ($_POST['message'] ?? ''));
        $result = $requestController->addMessage($requestId, $adminId, $message);

        if (!empty($result['success'])) {
            header('Location: admin-mentorship-requests.php?requestId=' . $requestId . '&status=messaged');
            exit;
        }

        $requestError = implode(' ', array_values(array_filter($result['errors'] ?? ['db' => 'Could not send the message.'])));
    }
}

$statusNotice = (string) ($_GET['status'] ?? '');
if ($statusNotice === 'updated') {
    $requestSuccess = 'The request was updated successfully.';
} elseif ($statusNotice === 'messaged') {
    $requestSuccess = 'The message was sent.';
}

$requests = $requestController->getRequestsByMentor($adminId);
$pendingCount = count(array_filter($requests, static fn(array $row): bool => strtolower((string) ($row['status'] ?? '')) === 'pending'));
$acceptedCount = count(array_filter($requests, static fn(array $row): bool => in_array(strtolower((string) ($row['status'] ?? '')), ['accepted', 'scheduled'], true)));
$selectedRequestId = (int) ($_GET['requestId'] ?? ($requests[0]['requestId'] ?? 0));
$selectedRequest = null;

foreach ($requests as $request) {
    if ((int) ($request['requestId'] ?? 0) === $selectedRequestId) {
        $selectedRequest = $request;
        break;
    }
}

if ($selectedRequest === null && $selectedRequestId > 0) {
    $candidate = $requestController->getRequestById($selectedRequestId);
    if ($candidate !== null && (int) ($candidate['mentorId'] ?? 0) === $adminId) {
        $selectedRequest = $candidate;
    }
}

$messages = $selectedRequest !== null ? $requestController->getMessagesByRequestId((int) $selectedRequest['requestId']) : [];
$conversationOpen = $selectedRequest !== null && in_array(strtolower((string) ($selectedRequest['status'] ?? 'pending')), ['accepted', 'scheduled', 'completed'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorship Requests | CareerStrand Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        :root{
            --bg:#040816;
            --bg-2:#071126;
            --panel:linear-gradient(180deg,rgba(12,22,46,.92),rgba(7,13,29,.96));
            --border:rgba(126,159,228,.2);
            --text:#f5f3ee;
            --muted:rgba(245,243,238,.72);
            --blue:#6f8fd8;
            --orange:#ff6e45;
            --green:#4ade80;
            --yellow:#f5bf65;
            --shadow:0 24px 70px rgba(2,8,20,.42);
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            color:var(--text);
            background:
                linear-gradient(90deg,rgba(245,191,101,.045) 1px,transparent 1px),
                linear-gradient(rgba(149,171,235,.04) 1px,transparent 1px),
                radial-gradient(ellipse at 13% 7%,rgba(111,143,216,.24),transparent 34%),
                radial-gradient(ellipse at 85% 15%,rgba(245,191,101,.14),transparent 30%),
                radial-gradient(ellipse at 68% 86%,rgba(255,110,69,.16),transparent 34%),
                linear-gradient(120deg,#020713 0%,#071532 44%,#140813 100%);
            background-size:72px 72px,72px 72px,100% 100%,100% 100%,100% 100%,100% 100%;
            isolation:isolate;
            font-family:Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body::before{
            content:"";
            position:fixed;
            inset:-12%;
            z-index:-6;
            pointer-events:none;
            background:
                conic-gradient(from 220deg at 22% 22%,transparent 0 48deg,rgba(111,143,216,.28) 72deg,transparent 104deg),
                conic-gradient(from 32deg at 84% 12%,transparent 0 42deg,rgba(255,110,69,.22) 70deg,transparent 108deg),
                linear-gradient(135deg,rgba(245,191,101,.1),transparent 34%,rgba(111,143,216,.12) 64%,transparent);
            filter:blur(14px);
        }
        body::after{
            content:"";
            position:fixed;
            inset:0;
            z-index:-2;
            pointer-events:none;
            background:
                linear-gradient(115deg,transparent 0 35%,rgba(245,191,101,.1) 37%,transparent 40%),
                linear-gradient(115deg,transparent 0 61%,rgba(111,143,216,.1) 63%,transparent 66%),
                radial-gradient(ellipse at center,transparent 0,rgba(2,5,14,.28) 72%);
            opacity:.92;
        }
        .page-shell{display:flex;min-height:100vh;color:var(--text)}
        .content{flex:1;padding:28px}
        .hero{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:18px;margin-bottom:18px}
        .panel{background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:22px;backdrop-filter:blur(16px);box-shadow:var(--shadow),inset 0 1px 0 rgba(255,255,255,.05)}
        .hero h1,.card h2{margin:0}
        .hero p,.card p,.muted{color:var(--muted);line-height:1.6}
        .stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}
        .stat{padding:14px;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid rgba(126,159,228,.14)}
        .stat span{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
        .stat strong{font-size:18px}
        .grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:18px;align-items:start}
        .stack{display:grid;gap:12px}
        .request-card{padding:16px;border-radius:18px;background:linear-gradient(180deg,rgba(12,22,46,.88),rgba(7,13,29,.93));border:1px solid rgba(126,159,228,.14);box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}
        .request-card.active{border-color:rgba(111,143,216,.45);box-shadow:0 0 0 1px rgba(111,143,216,.2) inset, inset 0 1px 0 rgba(255,255,255,.05)}
        .request-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
        .request-title{font-size:17px;font-weight:800;letter-spacing:-.02em;margin:0 0 6px}
        .request-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}
        .meta{padding:10px 12px;border-radius:14px;background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.12)}
        .meta span{display:block;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:rgba(245,243,238,.55);margin-bottom:5px}
        .meta strong{font-size:13px;line-height:1.5}
        .badge{display:inline-flex;align-items:center;min-height:28px;padding:0 11px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em}
        .badge.pending{background:rgba(245,191,101,.14);color:#f5bf65}
        .badge.accepted{background:rgba(74,222,128,.14);color:#4ade80}
        .badge.declined{background:rgba(255,107,107,.14);color:#ff9a9a}
        .badge.completed{background:rgba(111,143,216,.14);color:#b8c7ff}
        .btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:999px;border:1px solid rgba(255,255,255,.1);text-decoration:none;background:linear-gradient(180deg,rgba(255,255,255,.09),rgba(255,255,255,.035));color:#f5f3ee;font-weight:700;cursor:pointer;box-shadow:inset 0 1px 0 rgba(255,255,255,.08)}
        .btn-primary{background:linear-gradient(100deg,var(--blue),var(--orange) 72%,var(--yellow));border-color:transparent;color:#fff;box-shadow:0 18px 44px rgba(255,110,69,.24),0 0 30px rgba(111,143,216,.14)}
        .btn-soft{background:linear-gradient(180deg,rgba(255,255,255,.09),rgba(255,255,255,.035));border-color:rgba(126,159,228,.2)}
        .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .field{display:flex;flex-direction:column;gap:8px}
        .field.full{grid-column:span 2}
        .field label,.section-label{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(245,243,238,.55)}
        input,textarea{width:100%;border-radius:14px;border:1px solid rgba(126,159,228,.16);background:rgba(255,255,255,.045);color:#f5f3ee;padding:12px 13px;font:inherit;outline:none}
        textarea{min-height:110px;resize:vertical}
        .section{margin-top:16px;padding-top:16px;border-top:1px solid rgba(126,159,228,.14)}
        .thread{display:grid;gap:10px;max-height:360px;overflow:auto;padding-right:6px}
        .bubble{max-width:86%;padding:12px 14px;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.12)}
        .bubble.me{margin-left:auto;background:linear-gradient(180deg,rgba(111,143,216,.2),rgba(255,110,69,.12));border-color:rgba(111,143,216,.22)}
        .bubble .meta-line{font-size:11px;color:rgba(245,243,238,.55);margin-top:6px}
        .empty{padding:18px;border-radius:16px;border:1px dashed rgba(126,159,228,.18);color:var(--muted);text-align:center;background:rgba(255,255,255,.02)}
        .notice{padding:13px 15px;border-radius:14px;margin-bottom:14px;border:1px solid rgba(126,159,228,.14);background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.025))}
        .notice.success{background:rgba(74,222,128,.1);color:#9ef0b8}
        .notice.error{background:rgba(255,107,107,.1);color:#ffb0b0}
        .topline{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px}
        .link-muted{color:#b8c7ff;text-decoration:none}
        .link-muted:hover{text-decoration:underline}
        @media (max-width: 1100px){.hero,.grid{grid-template-columns:1fr}.stats,.request-meta,.form-grid{grid-template-columns:1fr}.field.full{grid-column:span 1}}
    </style>
</head>
<body>
<div class="page-shell">
    <?php include __DIR__ . '/partials/admin-sidebar.php'; ?>

    <main class="content">
        <section class="hero">
            <article class="panel">
                <div class="section-label">Back office queue</div>
                <h1>Mentorship requests</h1>
                <p>Users submit structured requests to admins for mentor chat or office hours. From here you can accept, reject, schedule, and continue the conversation after approval.</p>
                <div class="stats">
                    <div class="stat"><span>Total requests</span><strong><?= count($requests); ?></strong></div>
                    <div class="stat"><span>Pending</span><strong><?= $pendingCount; ?></strong></div>
                    <div class="stat"><span>Active</span><strong><?= $acceptedCount; ?></strong></div>
                </div>
            </article>
            <aside class="panel">
                <div class="section-label">How it works</div>
                <h2>Accept first, then talk</h2>
                <p>The requester can only send messages once you accept or schedule the request. That keeps the workflow structured instead of feeling like casual chat.</p>
                <div class="btn-row">
                    <a class="btn btn-soft" href="admin-dashboard.php">Back to dashboard</a>
                    <a class="btn btn-soft" href="admin-users.php">Manage users</a>
                </div>
            </aside>
        </section>

        <?php if ($requestSuccess !== ''): ?>
            <div class="notice success"><?= h($requestSuccess); ?></div>
        <?php endif; ?>
        <?php if ($requestError !== ''): ?>
            <div class="notice error"><?= h($requestError); ?></div>
        <?php endif; ?>

        <section class="grid">
            <article class="panel">
                <div class="topline">
                    <div>
                        <div class="section-label">Inbox</div>
                        <h2 style="margin-top:8px;">Incoming admin requests</h2>
                    </div>
                    <span class="badge pending"><?= $pendingCount; ?> pending</span>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="empty">No mentorship requests have been assigned to your admin account yet.</div>
                <?php else: ?>
                    <div class="stack">
                        <?php foreach ($requests as $request): ?>
                            <?php $isActive = (int) ($request['requestId'] ?? 0) === $selectedRequestId; ?>
                            <article class="request-card<?= $isActive ? ' active' : ''; ?>">
                                <div class="request-head">
                                    <div>
                                        <div class="request-title"><?= h((string) ($request['subject'] ?? 'Mentorship request')); ?></div>
                                        <div class="muted">From <?= h((string) ($request['requesterName'] ?? 'Requester')); ?> · <?= h(mrSourceLabel($request)); ?></div>
                                    </div>
                                    <span class="badge <?= mrBadgeClass((string) ($request['status'] ?? 'pending')); ?>"><?= h(mrStatusLabel((string) ($request['status'] ?? 'pending'))); ?></span>
                                </div>
                                <div class="request-meta">
                                    <div class="meta"><span>Type</span><strong><?= h(mrRequestTypeLabel((string) ($request['requestType'] ?? 'mentor_chat'))); ?></strong></div>
                                    <div class="meta"><span>Mode</span><strong><?= h(mrSessionModeLabel((string) ($request['sessionMode'] ?? 'chat'))); ?></strong></div>
                                    <div class="meta"><span>Preferred slot</span><strong><?= h((string) (($request['preferredDate'] ?? 'TBA') . (!empty($request['preferredTime']) ? ' at ' . $request['preferredTime'] : ''))); ?></strong></div>
                                    <div class="meta"><span>Offer</span><strong><?= $request['offerAmount'] !== null ? h((string) $request['currency']) . ' ' . h((string) $request['offerAmount']) : 'No offer'; ?></strong></div>
                                </div>
                                <div class="btn-row">
                                    <a class="btn<?= $isActive ? ' btn-primary' : ' btn-soft'; ?>" href="admin-mentorship-requests.php?requestId=<?= (int) $request['requestId']; ?>">Open</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="panel">
                <div class="topline">
                    <div>
                        <div class="section-label">Request details</div>
                        <h2 style="margin-top:8px;">Review and respond</h2>
                    </div>
                    <?php if ($selectedRequest !== null): ?>
                        <span class="badge <?= mrBadgeClass((string) ($selectedRequest['status'] ?? 'pending')); ?>"><?= h(mrStatusLabel((string) ($selectedRequest['status'] ?? 'pending'))); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($selectedRequest === null): ?>
                    <div class="empty">Select a request on the left to view the details, decide, and open the thread.</div>
                <?php else: ?>
                    <div class="request-meta" style="margin-bottom:16px;">
                        <div class="meta"><span>Requester</span><strong><?= h((string) ($selectedRequest['requesterName'] ?? 'Requester')); ?></strong></div>
                        <div class="meta"><span>Email</span><strong><?= h((string) ($selectedRequest['requesterEmail'] ?? '')); ?></strong></div>
                        <div class="meta"><span>Context</span><strong><?= h(mrSourceLabel($selectedRequest)); ?></strong></div>
                        <div class="meta"><span>Meeting</span><strong><?= !empty($selectedRequest['meetingLink']) ? h((string) $selectedRequest['meetingLink']) : 'Not set'; ?></strong></div>
                    </div>

                    <p><?= h((string) ($selectedRequest['details'] ?? '')); ?></p>

                    <form method="POST" class="section">
                        <input type="hidden" name="action" value="respond_request">
                        <input type="hidden" name="requestId" value="<?= (int) $selectedRequest['requestId']; ?>">
                        <div class="form-grid">
                            <div class="field">
                                <label>Response note</label>
                                <textarea name="responseNote" placeholder="Explain the next step, or why you are rejecting the request."><?= h((string) ($_POST['responseNote'] ?? '')); ?></textarea>
                            </div>
                            <div class="field">
                                <label>Meeting link</label>
                                <input type="url" name="meetingLink" placeholder="https://..." value="<?= h((string) ($_POST['meetingLink'] ?? ($selectedRequest['meetingLink'] ?? ''))); ?>">
                                <label style="margin-top:12px;">Scheduled date</label>
                                <input type="date" name="scheduledDate" value="<?= h((string) ($_POST['scheduledDate'] ?? ($selectedRequest['scheduledDate'] ?? ''))); ?>">
                                <label style="margin-top:12px;">Scheduled time</label>
                                <input type="time" name="scheduledTime" value="<?= h((string) ($_POST['scheduledTime'] ?? ($selectedRequest['scheduledTime'] ?? ''))); ?>">
                            </div>
                        </div>
                        <div class="btn-row" style="margin-top:14px;">
                            <button class="btn btn-primary" type="submit" name="status" value="accepted">Accept</button>
                            <button class="btn btn-soft" type="submit" name="status" value="scheduled">Schedule</button>
                            <button class="btn" type="submit" name="status" value="declined">Reject</button>
                            <button class="btn btn-soft" type="submit" name="status" value="completed">Mark completed</button>
                        </div>
                    </form>

                    <div class="section">
                        <div class="topline">
                            <div>
                                <div class="section-label">Conversation</div>
                                <h2 style="margin-top:8px;">Thread with the requester</h2>
                            </div>
                            <?php if ($conversationOpen): ?>
                                <span class="badge accepted">Open</span>
                            <?php else: ?>
                                <span class="badge pending">Locked</span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($messages)): ?>
                            <div class="empty">No messages yet.</div>
                        <?php else: ?>
                            <div class="thread">
                                <?php foreach ($messages as $message): ?>
                                    <?php $isMine = (int) ($message['senderId'] ?? 0) === $adminId; ?>
                                    <div class="bubble<?= $isMine ? ' me' : ''; ?>">
                                        <strong><?= h((string) ($message['senderName'] ?? 'User')); ?></strong>
                                        <div style="margin-top:6px;line-height:1.6;white-space:pre-wrap;"><?= h((string) ($message['message'] ?? '')); ?></div>
                                        <div class="meta-line"><?= h((string) ($message['createdAt'] ?? '')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($conversationOpen): ?>
                            <form method="POST" class="section">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="requestId" value="<?= (int) $selectedRequest['requestId']; ?>">
                                <label class="section-label">Add a message</label>
                                <textarea name="message" placeholder="Send a follow-up, link, or next step."></textarea>
                                <div class="btn-row">
                                    <button class="btn btn-primary" type="submit">Send message</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="section">
                                <div class="empty">Accept or schedule the request first, then the conversation thread opens for both sides.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
</body>
</html>
