<?php
require_once __DIR__ . '/../../utils/FrontOfficeAuth.php';
require_once __DIR__ . '/../../Controller/UserController.php';
require_once __DIR__ . '/../../Controller/MentorshipRequestController.php';

$frontUser = requireFrontUser();

$requestController = new MentorshipRequestController();
$userController = new UserController();

$currentUserId = (int) $frontUser['userId'];
$currentUserRole = strtolower(trim((string) ($frontUser['role'] ?? 'user')));
$isMentorEligible = $requestController->isEligibleMentor($currentUserId);

$mentorId = (int) ($_GET['mentorId'] ?? 0);
$sourceType = strtolower(trim((string) ($_GET['sourceType'] ?? 'direct')));
$sourceId = isset($_GET['sourceId']) ? (int) $_GET['sourceId'] : 0;
$sourceLabel = trim((string) ($_GET['sourceLabel'] ?? 'General mentorship request'));
$requestType = strtolower(trim((string) ($_GET['requestType'] ?? 'mentor_chat')));
$sessionMode = strtolower(trim((string) ($_GET['sessionMode'] ?? 'chat')));
$selectedRequestId = (int) ($_GET['requestId'] ?? 0);

if (!in_array($sourceType, ['profile', 'hub', 'event', 'direct'], true)) {
    $sourceType = 'direct';
}
if (!in_array($requestType, ['mentor_chat', 'office_hours'], true)) {
    $requestType = 'mentor_chat';
}
if (!in_array($sessionMode, ['chat', 'video', 'in_person'], true)) {
    $sessionMode = 'chat';
}

$mentorError = '';
$requestError = '';
$messageError = '';
$requestSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_mentor_request') {
        $result = $requestController->createRequest([
            'requesterId' => $currentUserId,
            'mentorId' => (int) ($_POST['mentorId'] ?? 0),
            'sourceType' => trim((string) ($_POST['sourceType'] ?? 'direct')),
            'sourceId' => (int) ($_POST['sourceId'] ?? 0),
            'requestType' => trim((string) ($_POST['requestType'] ?? 'mentor_chat')),
            'sessionMode' => trim((string) ($_POST['sessionMode'] ?? 'chat')),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'details' => trim((string) ($_POST['details'] ?? '')),
            'preferredDate' => trim((string) ($_POST['preferredDate'] ?? '')),
            'preferredTime' => trim((string) ($_POST['preferredTime'] ?? '')),
            'durationMinutes' => (int) ($_POST['durationMinutes'] ?? 30),
            'offerAmount' => trim((string) ($_POST['offerAmount'] ?? '')),
            'currency' => trim((string) ($_POST['currency'] ?? 'USD')),
        ]);

        if (!empty($result['success'])) {
            header('Location: mentor-requests.php?status=submitted');
            exit;
        }

        $mentorError = (string) ($result['errors']['mentorId'] ?? '');
        $requestError = implode(' ', array_values(array_filter($result['errors'] ?? ['db' => 'Please review the form and try again.'])));
        $mentorId = (int) ($_POST['mentorId'] ?? $mentorId);
        $sourceType = trim((string) ($_POST['sourceType'] ?? $sourceType));
        $sourceId = (int) ($_POST['sourceId'] ?? $sourceId);
        $sourceLabel = trim((string) ($_POST['sourceLabel'] ?? $sourceLabel));
        $requestType = trim((string) ($_POST['requestType'] ?? $requestType));
        $sessionMode = trim((string) ($_POST['sessionMode'] ?? $sessionMode));
    }

    if ($action === 'respond_request' && $isMentorEligible) {
        $requestId = (int) ($_POST['requestId'] ?? 0);
        $result = $requestController->respondToRequest($requestId, $currentUserId, [
            'status' => trim((string) ($_POST['status'] ?? 'accepted')),
            'responseNote' => trim((string) ($_POST['responseNote'] ?? '')),
            'scheduledDate' => trim((string) ($_POST['scheduledDate'] ?? '')),
            'scheduledTime' => trim((string) ($_POST['scheduledTime'] ?? '')),
            'meetingLink' => trim((string) ($_POST['meetingLink'] ?? '')),
        ]);

        if (!empty($result['success'])) {
            header('Location: mentor-requests.php?requestId=' . $requestId . '&status=updated');
            exit;
        }

        $requestError = implode(' ', array_values(array_filter($result['errors'] ?? ['db' => 'Could not update the request.'])));
    }

    if ($action === 'send_message') {
        $selectedRequestId = (int) ($_POST['requestId'] ?? $selectedRequestId);
        $result = $requestController->addMessage(
            $selectedRequestId,
            $currentUserId,
            trim((string) ($_POST['message'] ?? ''))
        );

        if (!empty($result['success'])) {
            header('Location: mentor-requests.php?requestId=' . $selectedRequestId . '&status=messaged');
            exit;
        }

        $messageError = implode(' ', array_values(array_filter($result['errors'] ?? ['db' => 'Could not send the message.'])));
    }
}

$statusNotice = (string) ($_GET['status'] ?? '');
if ($statusNotice === 'submitted') {
    $requestSuccess = 'Your mentorship request was submitted and is waiting for an admin to review it.';
} elseif ($statusNotice === 'updated') {
    $requestSuccess = 'The request was updated successfully.';
} elseif ($statusNotice === 'messaged') {
    $requestSuccess = 'Your message was sent.';
}

$eligibleMentors = $requestController->listEligibleMentors(120);
if ($mentorId <= 0 && !empty($eligibleMentors)) {
    $mentorId = (int) $eligibleMentors[0]['userId'];
}

$selectedMentor = null;
foreach ($eligibleMentors as $candidate) {
    if ((int) ($candidate['userId'] ?? 0) === $mentorId) {
        $selectedMentor = $candidate;
        break;
    }
}

if ($selectedMentor === null && $mentorId > 0) {
    $mentorRow = $userController->getById($mentorId);
    if ($mentorRow !== null) {
        $selectedMentor = [
            'userId' => $mentorId,
            'fullName' => $mentorRow->getFullName(),
            'email' => $mentorRow->getEmail(),
            'role' => $mentorRow->getRole(),
            'status' => $mentorRow->getStatus(),
        ];
    } else {
        $mentorId = 0;
    }
}

$mentorEligible = $mentorId > 0 ? $requestController->isEligibleMentor($mentorId) : false;
if ($mentorId > 0 && !$mentorEligible) {
    $mentorError = 'That admin cannot receive mentorship requests right now.';
}

$myRequests = $requestController->getRequestsByRequester($currentUserId);

$selectedRequest = null;
foreach ($myRequests as $request) {
    if ((int) ($request['requestId'] ?? 0) === $selectedRequestId) {
        $selectedRequest = $request;
        break;
    }
}

if ($selectedRequest === null && $selectedRequestId > 0) {
    $candidateRequest = $requestController->getRequestById($selectedRequestId);
    if ($candidateRequest !== null && (int) ($candidateRequest['requesterId'] ?? 0) === $currentUserId) {
        $selectedRequest = $candidateRequest;
    }
}

if ($selectedRequest === null && !empty($myRequests)) {
    $selectedRequest = $myRequests[0];
    $selectedRequestId = (int) ($selectedRequest['requestId'] ?? 0);
}

$selectedMessages = $selectedRequest !== null ? $requestController->getMessagesByRequestId((int) $selectedRequest['requestId']) : [];
$conversationOpen = $selectedRequest !== null && in_array(strtolower((string) ($selectedRequest['status'] ?? 'pending')), ['accepted', 'scheduled', 'completed'], true);
$incomingRequests = $isMentorEligible ? $requestController->getRequestsByMentor($currentUserId) : [];
$incomingSelectedRequest = null;
foreach ($incomingRequests as $request) {
    if ((int) ($request['requestId'] ?? 0) === $selectedRequestId) {
        $incomingSelectedRequest = $request;
        break;
    }
}

if ($incomingSelectedRequest === null && !empty($incomingRequests)) {
    $incomingSelectedRequest = $incomingRequests[0];
}
$incomingSelectedMessages = $incomingSelectedRequest !== null ? $requestController->getMessagesByRequestId((int) $incomingSelectedRequest['requestId']) : [];
$incomingConversationOpen = $incomingSelectedRequest !== null && in_array(strtolower((string) ($incomingSelectedRequest['status'] ?? 'pending')), ['accepted', 'scheduled', 'completed'], true);

$requestTypeLabel = static function (string $value): string {
    return match ($value) {
        'mentor_chat' => 'Mentor chat',
        'office_hours' => 'Office hours',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$sessionModeLabel = static function (string $value): string {
    return match ($value) {
        'chat' => 'Chat',
        'video' => 'Video call',
        'in_person' => 'In person',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
};

$statusLabel = static function (string $value): string {
    return match ($value) {
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst($value),
    };
};

$sourceLabelText = static function (array $row): string {
    $type = strtolower(trim((string) ($row['sourceType'] ?? 'direct')));
    $id = (int) ($row['sourceId'] ?? 0);

    return match ($type) {
        'profile' => $id > 0 ? 'Profile #' . $id : 'Profile context',
        'hub' => $id > 0 ? 'Hub #' . $id : 'Hub context',
        'event' => $id > 0 ? 'Event #' . $id : 'Event context',
        default => 'Direct request',
    };
};

function mr_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mr_badge_class(string $status): string
{
    return match (strtolower(trim($status))) {
        'pending' => 'pending',
        'accepted', 'scheduled' => 'accepted',
        'declined', 'cancelled' => 'declined',
        'completed' => 'completed',
        default => 'pending',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorship Requests | CareerStrand</title>
    <link rel="stylesheet" href="assets/css/front-nav.css">
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
            --shadow:0 24px 70px rgba(2,8,20,.42);
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            font-family:Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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
        .page-shell{min-height:100vh}
        .main-area{padding:0 0 72px}
        .container{width:min(1180px, calc(100% - 32px));margin:0 auto}
        .hero{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(290px,.75fr);gap:20px;margin-bottom:22px}
        .panel{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:24px;
            box-shadow:var(--shadow),inset 0 1px 0 rgba(255,255,255,.05);
            backdrop-filter:blur(18px);
        }
        .hero-copy{padding:30px}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:var(--muted);text-transform:uppercase;font-size:11px;letter-spacing:.18em}
        h1{margin:18px 0 12px;font-size:clamp(2.2rem, 4.7vw, 4.2rem);line-height:.95;letter-spacing:-.05em}
        .hero-copy p{margin:0;color:var(--muted);font-size:15px;line-height:1.7;max-width:68ch}
        .hero-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:24px}
        .stat{padding:16px;border-radius:18px;background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.035));border:1px solid rgba(126,159,228,.16)}
        .stat span{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
        .stat strong{font-size:18px}
        .hero-side{padding:24px}
        .mentor-card{padding:20px;border-radius:22px;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.16)}
        .mentor-card .label{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
        .mentor-card h2{margin:0 0 8px;font-size:24px;letter-spacing:-.03em}
        .mentor-card p{margin:0;color:var(--muted);line-height:1.6}
        .mentor-meta{display:grid;gap:10px;margin-top:16px}
        .mentor-meta div{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(126,159,228,.14)}
        .mentor-meta span{color:var(--muted)}
        .mentor-meta strong{text-align:right}
        .grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:20px;align-items:start}
        .card{padding:24px}
        .section-head{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:16px}
        .section-head h3{margin:0;font-size:22px;letter-spacing:-.03em}
        .section-head p{margin:0;color:var(--muted);font-size:14px;line-height:1.6;max-width:60ch}
        .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .field{display:flex;flex-direction:column;gap:8px}
        .field.full{grid-column:span 2}
        label,.field strong{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted)}
        input,select,textarea{width:100%;border-radius:16px;border:1px solid rgba(126,159,228,.18);background:rgba(255,255,255,.045);color:var(--text);padding:13px 14px;font:inherit;outline:none}
        textarea{min-height:130px;resize:vertical}
        input:focus,select:focus,textarea:focus{border-color:rgba(111,143,216,.5);box-shadow:0 0 0 4px rgba(111,143,216,.14)}
        .row-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 16px;border-radius:999px;border:1px solid rgba(255,255,255,.1);text-decoration:none;font-weight:700;cursor:pointer;background:linear-gradient(180deg,rgba(255,255,255,.09),rgba(255,255,255,.035));color:var(--text);box-shadow:inset 0 1px 0 rgba(255,255,255,.08)}
        .btn-primary{background:linear-gradient(100deg,var(--blue),var(--orange) 72%,var(--yellow));border-color:transparent;color:#fff;box-shadow:0 18px 44px rgba(255,110,69,.24),0 0 30px rgba(111,143,216,.14)}
        .btn-soft{background:linear-gradient(180deg,rgba(255,255,255,.09),rgba(255,255,255,.035));border-color:rgba(126,159,228,.2)}
        .alert{padding:14px 16px;border-radius:16px;margin-bottom:16px;border:1px solid rgba(126,159,228,.16);background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.025))}
        .alert.success{background:rgba(74,222,128,.1);color:var(--green)}
        .alert.error{background:rgba(255,107,107,.08);color:#ff9a9a}
        .list{display:grid;gap:14px}
        .request-card{padding:18px;border-radius:20px;background:linear-gradient(180deg,rgba(12,22,46,.88),rgba(7,13,29,.93));border:1px solid rgba(126,159,228,.16);box-shadow:inset 0 1px 0 rgba(255,255,255,.05)}
        .request-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        .request-title{font-size:18px;font-weight:800;letter-spacing:-.02em;margin:0}
        .request-sub{color:var(--muted);margin-top:6px;line-height:1.6}
        .badge{display:inline-flex;align-items:center;gap:8px;min-height:30px;padding:0 12px;border-radius:999px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;border:1px solid transparent}
        .badge.pending{background:rgba(245,191,101,.14);border-color:rgba(245,191,101,.2);color:#f5bf65}
        .badge.accepted{background:rgba(74,222,128,.14);border-color:rgba(74,222,128,.2);color:var(--green)}
        .badge.declined{background:rgba(255,107,107,.14);border-color:rgba(255,107,107,.2);color:#ff9a9a}
        .badge.completed{background:rgba(111,143,216,.14);border-color:rgba(111,143,216,.2);color:#b8c7ff}
        .meta-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}
        .meta-item{padding:12px;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.14)}
        .meta-item span{display:block;color:var(--muted);font-size:11px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:6px}
        .meta-item strong{display:block;font-size:14px;line-height:1.5}
        .helper-list{display:grid;gap:10px}
        .helper-list div{padding:12px 14px;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.14);color:var(--muted);line-height:1.5}
        .empty{padding:24px;border-radius:18px;border:1px dashed rgba(126,159,228,.18);color:var(--muted);text-align:center;background:rgba(255,255,255,.02)}
        .thread{display:grid;gap:10px;max-height:360px;overflow:auto;padding-right:6px}
        .bubble{max-width:86%;padding:12px 14px;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.12)}
        .bubble.me{margin-left:auto;background:linear-gradient(180deg,rgba(111,143,216,.2),rgba(255,110,69,.12));border-color:rgba(111,143,216,.22)}
        .bubble .meta-line{font-size:11px;color:rgba(245,243,238,.55);margin-top:6px}
        .chip-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        .chip{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;background:rgba(255,255,255,.055);border:1px solid rgba(126,159,228,.12);font-size:12px;color:var(--muted)}
        .summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}
        .summary{padding:14px;border-radius:16px;background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.025));border:1px solid rgba(126,159,228,.14)}
        .summary span{display:block;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:rgba(245,243,238,.55);margin-bottom:6px}
        .summary strong{font-size:16px}
        @media (max-width: 960px){.hero,.grid{grid-template-columns:1fr}.hero-stats,.meta-grid,.field-grid,.summary-grid{grid-template-columns:1fr}.field.full{grid-column:span 1}}
    </style>
</head>
<body>
<div class="page-shell">
    <?php
        $activePage = 'mentorship';
        $brandSubtitle = 'mentorship desk';
        include __DIR__ . '/partials/front-nav.php';
    ?>

    <main class="main-area">
        <div class="container">
            <section class="hero">
                <article class="panel hero-copy">
                    <span class="eyebrow">Structured support / office hours / admin review</span>
                    <h1>Request guidance from admins, managers, and manager recruiters.</h1>
                    <p>
                        Instead of instant access, CareerStrand lets you send a structured request with a subject,
                        context, preferred timing, and even an offer if you want to make the session paid or premium.
                        An admin, manager, or manager recruiter can accept, decline, or schedule the session when they are ready.
                    </p>
                    <div class="hero-stats">
                        <div class="stat"><span>My requests</span><strong><?= count($myRequests); ?></strong></div>
                        <div class="stat"><span>Admin options</span><strong><?= count($eligibleMentors); ?></strong></div>
                        <div class="stat"><span>Conversation</span><strong><?= $conversationOpen ? 'Open' : 'Locked'; ?></strong></div>
                    </div>
                </article>

                <aside class="panel hero-side">
                    <div class="mentor-card">
                        <div class="label">Selected reviewer</div>
                        <?php if ($selectedMentor !== null): ?>
                            <h2><?= mr_h((string) ($selectedMentor['fullName'] ?? 'Reviewer')); ?></h2>
                            <p><?= mr_h((string) ($selectedMentor['role'] ?? 'admin')); ?></p>
                            <div class="mentor-meta">
                                <div><span>Email</span><strong><?= mr_h((string) ($selectedMentor['email'] ?? '')); ?></strong></div>
                                <div><span>Status</span><strong><?= mr_h((string) ($selectedMentor['status'] ?? 'active')); ?></strong></div>
                                <div><span>Availability</span><strong><?= $mentorEligible ? 'Open for requests' : 'Not eligible'; ?></strong></div>
                            </div>
                        <?php else: ?>
                            <h2>Choose a reviewer</h2>
                            <p>Select an admin, manager, or manager recruiter profile to start a structured mentorship or office hours request.</p>
                        <?php endif; ?>
                    </div>
                </aside>
            </section>

            <?php if ($requestSuccess !== ''): ?><div class="alert success"><?= mr_h($requestSuccess); ?></div><?php endif; ?>
            <?php if ($requestError !== ''): ?><div class="alert error"><?= mr_h($requestError); ?></div><?php endif; ?>
            <?php if ($messageError !== ''): ?><div class="alert error"><?= mr_h($messageError); ?></div><?php endif; ?>
            <?php if ($mentorError !== ''): ?><div class="alert error"><?= mr_h($mentorError); ?></div><?php endif; ?>

            <section class="grid">
                <article class="panel card">
                    <div class="section-head">
                        <div>
                            <h3>Send a request</h3>
                            <p>Use this form to ask an admin for a short mentor chat or a structured office hours session.</p>
                        </div>
                        <div class="badge pending">Request flow</div>
                    </div>

                    <form method="POST" class="request-form-mini" novalidate>
                        <input type="hidden" name="action" value="create_mentor_request">
                        <input type="hidden" name="sourceType" value="<?= mr_h($sourceType); ?>">
                        <input type="hidden" name="sourceId" value="<?= (int) $sourceId; ?>">
                        <input type="hidden" name="sourceLabel" value="<?= mr_h($sourceLabel); ?>">

                        <div class="field-grid">
                            <div class="field full">
                                <strong>Reviewer</strong>
                                <select name="mentorId" required>
                                    <option value="">Choose an admin, manager, or recruiter</option>
                                    <?php foreach ($eligibleMentors as $mentor): ?>
                                        <?php
                                            $mentorLabel = trim((string) ($mentor['fullName'] ?? 'Admin'));
                                            $mentorRole = trim((string) ($mentor['role'] ?? 'admin'));
                                            $mentorEmail = trim((string) ($mentor['email'] ?? ''));
                                        ?>
                                        <option value="<?= (int) $mentor['userId']; ?>" <?= (int) $mentor['userId'] === $mentorId ? 'selected' : ''; ?>>
                                            <?= mr_h($mentorLabel . ' · ' . ucfirst($mentorRole) . ($mentorEmail !== '' ? ' · ' . $mentorEmail : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <strong>Request type</strong>
                                <select name="requestType">
                                    <option value="mentor_chat" <?= $requestType === 'mentor_chat' ? 'selected' : ''; ?>>Mentor chat</option>
                                    <option value="office_hours" <?= $requestType === 'office_hours' ? 'selected' : ''; ?>>Office hours</option>
                                </select>
                            </div>

                            <div class="field">
                                <strong>Session mode</strong>
                                <select name="sessionMode">
                                    <option value="chat" <?= $sessionMode === 'chat' ? 'selected' : ''; ?>>Chat</option>
                                    <option value="video" <?= $sessionMode === 'video' ? 'selected' : ''; ?>>Video call</option>
                                    <option value="in_person" <?= $sessionMode === 'in_person' ? 'selected' : ''; ?>>In person</option>
                                </select>
                            </div>

                            <div class="field full">
                                <strong>Subject</strong>
                                <input type="text" name="subject" value="<?= mr_h($_POST['subject'] ?? ''); ?>" placeholder="What do you want help with?">
                            </div>

                            <div class="field full">
                                <strong>Details</strong>
                                <textarea name="details" placeholder="Add context, goals, and what you want the admin to review."><?= mr_h($_POST['details'] ?? ''); ?></textarea>
                            </div>

                            <div class="field">
                                <strong>Preferred date</strong>
                                <input type="date" name="preferredDate" value="<?= mr_h($_POST['preferredDate'] ?? ''); ?>">
                            </div>

                            <div class="field">
                                <strong>Preferred time</strong>
                                <input type="time" name="preferredTime" value="<?= mr_h($_POST['preferredTime'] ?? ''); ?>">
                            </div>

                            <div class="field">
                                <strong>Duration (minutes)</strong>
                                <input type="number" name="durationMinutes" min="15" max="240" step="15" value="<?= mr_h($_POST['durationMinutes'] ?? '30'); ?>">
                            </div>

                            <div class="field">
                                <strong>Offer amount</strong>
                                <input type="number" name="offerAmount" min="0" step="0.01" value="<?= mr_h($_POST['offerAmount'] ?? ''); ?>" placeholder="Optional">
                            </div>

                            <div class="field">
                                <strong>Currency</strong>
                                <select name="currency">
                                    <?php foreach (['USD', 'TND', 'EUR'] as $currency): ?>
                                        <option value="<?= mr_h($currency); ?>" <?= (($_POST['currency'] ?? 'USD') === $currency) ? 'selected' : ''; ?>><?= mr_h($currency); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row-actions">
                            <button class="btn btn-primary" type="submit">Send request</button>
                            <a class="btn btn-soft" href="home.php">Back to home</a>
                        </div>
                    </form>
                </article>

                <aside class="panel card">
                    <div class="section-head">
                        <div>
                            <h3>How it works</h3>
                            <p>Admins, managers, and manager recruiters can review requests in the back office and reply after they accept or schedule a session.</p>
                        </div>
                    </div>

                    <div class="helper-list">
                        <div>1. Pick an admin, manager, or manager recruiter from a profile, hub, or event context.</div>
                        <div>2. Add the subject, the context, and the session details.</div>
                        <div>3. Include a budget or offer if you want a paid or premium session.</div>
                        <div>4. The reviewer can accept, decline, or schedule the session with a note.</div>
                    </div>
                </aside>
            </section>

            <section class="grid" style="margin-top:20px;">
                <article class="panel card">
                    <div class="section-head">
                        <div>
                            <h3>My requests</h3>
                            <p>Track the requests you have sent and the responses you received.</p>
                        </div>
                        <div class="badge pending"><?= count($myRequests); ?> total</div>
                    </div>

                    <?php if (empty($myRequests)): ?>
                        <div class="empty">You have not sent any mentorship requests yet.</div>
                    <?php else: ?>
                        <div class="list">
                            <?php foreach ($myRequests as $request): ?>
                                <article class="request-card">
                                    <div class="request-top">
                                        <div>
                                            <div class="request-title"><?= mr_h((string) ($request['subject'] ?? 'Mentorship request')); ?></div>
                                            <div class="request-sub">
                                                <?= mr_h($requestTypeLabel((string) ($request['requestType'] ?? 'mentor_chat'))); ?>
                                                · <?= mr_h($sessionModeLabel((string) ($request['sessionMode'] ?? 'chat'))); ?>
                                            </div>
                                        </div>
                                        <span class="badge <?= mr_badge_class((string) ($request['status'] ?? 'pending')); ?>">
                                            <?= mr_h($statusLabel((string) ($request['status'] ?? 'pending'))); ?>
                                        </span>
                                    </div>
                                    <div class="meta-grid">
                                        <div class="meta-item"><span>Admin</span><strong><?= mr_h((string) ($request['mentorName'] ?? 'Admin')); ?></strong></div>
                                        <div class="meta-item"><span>Source</span><strong><?= mr_h($sourceLabelText($request)); ?></strong></div>
                                        <div class="meta-item"><span>Offer</span><strong><?= $request['offerAmount'] !== null ? mr_h((string) $request['currency']) . ' ' . mr_h((string) $request['offerAmount']) : 'Not offered'; ?></strong></div>
                                    </div>
                                    <p class="request-sub"><?= mr_h((string) ($request['details'] ?? '')); ?></p>
                                    <div class="request-actions">
                                        <p>
                                            Preferred slot:
                                            <?= mr_h((string) ($request['preferredDate'] ?? 'TBA')); ?>
                                            <?= !empty($request['preferredTime']) ? ' at ' . mr_h((string) $request['preferredTime']) : ''; ?>
                                            · Duration <?= (int) ($request['durationMinutes'] ?? 30); ?> min
                                        </p>
                                        <?php if (!empty($request['responseNote'])): ?>
                                            <p><strong>Admin note:</strong> <?= mr_h((string) $request['responseNote']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($request['meetingLink'])): ?>
                                            <p><strong>Meeting link:</strong> <a href="<?= mr_h((string) $request['meetingLink']); ?>" target="_blank" rel="noopener noreferrer"><?= mr_h((string) $request['meetingLink']); ?></a></p>
                                        <?php endif; ?>
                                        <?php if (!empty($request['scheduledDate']) || !empty($request['scheduledTime'])): ?>
                                            <p><strong>Scheduled:</strong> <?= mr_h((string) ($request['scheduledDate'] ?? '')); ?> <?= !empty($request['scheduledTime']) ? mr_h((string) $request['scheduledTime']) : ''; ?></p>
                                        <?php endif; ?>
                                        <div class="row-actions">
                                            <a class="btn btn-soft" href="mentor-requests.php?requestId=<?= (int) $request['requestId']; ?>">Open thread</a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="panel card">
                    <div class="section-head">
                        <div>
                            <h3>Conversation</h3>
                            <p>Open a request to continue the thread after an admin accepts or schedules it.</p>
                        </div>
                        <div class="badge <?= $conversationOpen ? 'accepted' : 'pending'; ?>"><?= $conversationOpen ? 'Open' : 'Locked'; ?></div>
                    </div>

                    <?php if ($selectedRequest === null): ?>
                        <div class="empty">Select one of your requests to view the conversation thread.</div>
                    <?php else: ?>
                        <div class="request-card" style="margin-bottom:14px;">
                            <div class="request-top">
                                <div>
                                    <div class="request-title"><?= mr_h((string) ($selectedRequest['subject'] ?? 'Mentorship request')); ?></div>
                                    <div class="request-sub">
                                        <?= mr_h($requestTypeLabel((string) ($selectedRequest['requestType'] ?? 'mentor_chat'))); ?>
                                        · <?= mr_h($sessionModeLabel((string) ($selectedRequest['sessionMode'] ?? 'chat'))); ?>
                                    </div>
                                </div>
                                <span class="badge <?= mr_badge_class((string) ($selectedRequest['status'] ?? 'pending')); ?>"><?= mr_h($statusLabel((string) ($selectedRequest['status'] ?? 'pending'))); ?></span>
                            </div>
                            <p class="request-sub" style="margin-bottom:0;"><?= mr_h((string) ($selectedRequest['details'] ?? '')); ?></p>
                        </div>

                        <?php if (empty($selectedMessages)): ?>
                            <div class="empty">No messages yet. Once the admin accepts, the thread opens here.</div>
                        <?php else: ?>
                            <div class="list" style="margin-bottom:14px;">
                                <?php foreach ($selectedMessages as $message): ?>
                                    <?php $isMine = (int) ($message['senderId'] ?? 0) === $currentUserId; ?>
                                    <article class="request-card" style="<?= $isMine ? 'background:rgba(111,143,216,.08);' : ''; ?>">
                                        <div class="request-top">
                                            <div>
                                                <div class="request-title" style="font-size:15px;"><?= mr_h((string) ($message['senderName'] ?? 'User')); ?></div>
                                                <div class="request-sub"><?= mr_h((string) ($message['createdAt'] ?? '')); ?></div>
                                            </div>
                                        </div>
                                        <p class="request-sub" style="margin-bottom:0;white-space:pre-wrap;"><?= mr_h((string) ($message['message'] ?? '')); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($conversationOpen): ?>
                            <form method="POST" class="request-form-mini">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="requestId" value="<?= (int) $selectedRequest['requestId']; ?>">
                                <div class="field full">
                                    <strong>Message</strong>
                                    <textarea name="message" rows="3" placeholder="Reply with details, a follow-up question, or a confirmation."></textarea>
                                </div>
                                <div class="row-actions">
                                    <button class="btn btn-primary" type="submit">Send message</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="empty">The conversation unlocks after the request is accepted or scheduled.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            </section>

            <?php if ($isMentorEligible): ?>
                <section class="grid" style="margin-top:20px;">
                    <article class="panel card">
                        <div class="section-head">
                            <div>
                                <h3>Incoming requests</h3>
                                <p>Review requests sent to your admin, manager, or recruiter account.</p>
                            </div>
                            <div class="badge pending"><?= count($incomingRequests); ?> incoming</div>
                        </div>

                        <?php if (empty($incomingRequests)): ?>
                            <div class="empty">No incoming requests yet.</div>
                        <?php else: ?>
                            <div class="list">
                                <?php foreach ($incomingRequests as $request): ?>
                                    <article class="request-card">
                                        <div class="request-top">
                                            <div>
                                                <div class="request-title"><?= mr_h((string) ($request['subject'] ?? 'Mentorship request')); ?></div>
                                                <div class="request-sub">
                                                    From <?= mr_h((string) ($request['requesterName'] ?? 'Requester')); ?>
                                                    · <?= mr_h($sourceLabelText($request)); ?>
                                                </div>
                                            </div>
                                            <span class="badge <?= mr_badge_class((string) ($request['status'] ?? 'pending')); ?>">
                                                <?= mr_h($statusLabel((string) ($request['status'] ?? 'pending'))); ?>
                                            </span>
                                        </div>
                                        <div class="meta-grid">
                                            <div class="meta-item"><span>Type</span><strong><?= mr_h($requestTypeLabel((string) ($request['requestType'] ?? 'mentor_chat'))); ?></strong></div>
                                            <div class="meta-item"><span>Mode</span><strong><?= mr_h($sessionModeLabel((string) ($request['sessionMode'] ?? 'chat'))); ?></strong></div>
                                            <div class="meta-item"><span>Offer</span><strong><?= $request['offerAmount'] !== null ? mr_h((string) $request['currency']) . ' ' . mr_h((string) $request['offerAmount']) : 'No offer'; ?></strong></div>
                                        </div>
                                        <p class="request-sub"><?= mr_h((string) ($request['details'] ?? '')); ?></p>
                                        <div class="row-actions">
                                            <a class="btn btn-soft" href="mentor-requests.php?requestId=<?= (int) $request['requestId']; ?>">Open request</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="panel card">
                        <div class="section-head">
                            <div>
                                <h3>Incoming review</h3>
                                <p>Accept, reject, or schedule the selected request.</p>
                            </div>
                            <div class="badge <?= $incomingConversationOpen ? 'accepted' : 'pending'; ?>"><?= $incomingConversationOpen ? 'Open' : 'Locked'; ?></div>
                        </div>

                        <?php if ($incomingSelectedRequest === null): ?>
                            <div class="empty">Select an incoming request to review it here.</div>
                        <?php else: ?>
                            <div class="request-card" style="margin-bottom:14px;">
                                <div class="request-top">
                                    <div>
                                        <div class="request-title"><?= mr_h((string) ($incomingSelectedRequest['subject'] ?? 'Mentorship request')); ?></div>
                                        <div class="request-sub">
                                            From <?= mr_h((string) ($incomingSelectedRequest['requesterName'] ?? 'Requester')); ?>
                                            · <?= mr_h($sourceLabelText($incomingSelectedRequest)); ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= mr_badge_class((string) ($incomingSelectedRequest['status'] ?? 'pending')); ?>"><?= mr_h($statusLabel((string) ($incomingSelectedRequest['status'] ?? 'pending'))); ?></span>
                                </div>
                                <p class="request-sub" style="margin-bottom:0;"><?= mr_h((string) ($incomingSelectedRequest['details'] ?? '')); ?></p>
                            </div>

                            <form method="POST" class="request-form-mini">
                                <input type="hidden" name="action" value="respond_request">
                                <input type="hidden" name="requestId" value="<?= (int) $incomingSelectedRequest['requestId']; ?>">
                                <div class="field-grid">
                                    <div class="field">
                                        <strong>Decision</strong>
                                        <select name="status">
                                            <option value="accepted">Accept</option>
                                            <option value="scheduled">Schedule</option>
                                            <option value="declined">Decline</option>
                                            <option value="completed">Mark completed</option>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <strong>Meeting link</strong>
                                        <input type="url" name="meetingLink" placeholder="https://...">
                                    </div>
                                    <div class="field">
                                        <strong>Scheduled date</strong>
                                        <input type="date" name="scheduledDate">
                                    </div>
                                    <div class="field">
                                        <strong>Scheduled time</strong>
                                        <input type="time" name="scheduledTime">
                                    </div>
                                    <div class="field full">
                                        <strong>Response note</strong>
                                        <textarea name="responseNote" rows="3" placeholder="Share what happens next, or why you are declining."></textarea>
                                    </div>
                                </div>
                                <div class="row-actions">
                                    <button class="btn btn-primary" type="submit">Save response</button>
                                </div>
                            </form>

                            <div class="section">
                                <div class="section-head">
                                    <div>
                                        <h3>Thread</h3>
                                        <p>Conversation opens after you accept or schedule the request.</p>
                                    </div>
                                    <div class="badge <?= $incomingConversationOpen ? 'accepted' : 'pending'; ?>"><?= $incomingConversationOpen ? 'Open' : 'Locked'; ?></div>
                                </div>

                                <?php if (empty($incomingSelectedMessages)): ?>
                                    <div class="empty">No messages yet.</div>
                                <?php else: ?>
                                    <div class="thread">
                                        <?php foreach ($incomingSelectedMessages as $message): ?>
                                            <?php $isMine = (int) ($message['senderId'] ?? 0) === $currentUserId; ?>
                                            <div class="bubble<?= $isMine ? ' me' : ''; ?>">
                                                <strong><?= mr_h((string) ($message['senderName'] ?? 'User')); ?></strong>
                                                <div style="margin-top:6px;line-height:1.6;white-space:pre-wrap;"><?= mr_h((string) ($message['message'] ?? '')); ?></div>
                                                <div class="meta-line"><?= mr_h((string) ($message['createdAt'] ?? '')); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($incomingConversationOpen): ?>
                                    <form method="POST" class="section">
                                        <input type="hidden" name="action" value="send_message">
                                        <input type="hidden" name="requestId" value="<?= (int) $incomingSelectedRequest['requestId']; ?>">
                                        <label class="section-label">Add a message</label>
                                        <textarea name="message" placeholder="Send a follow-up, link, or next step."></textarea>
                                        <div class="row-actions">
                                            <button class="btn btn-primary" type="submit">Send message</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="empty" style="margin-top:14px;">Accept or schedule the request first, then the thread opens for both sides.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
