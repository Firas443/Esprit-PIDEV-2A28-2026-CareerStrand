<?php
require_once __DIR__ . '/../../Controller/SponsorController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json');

$sc      = new SponsorController();
$eventId = isset($_GET['eventId']) ? (int)$_GET['eventId'] : 0;

if ($eventId > 0) {
    $list = $sc->getByEvent($eventId);
} else {
    $list = $sc->listerSponsors();
}

$data = array_map(function($s) {
    return [
        'sponsorId'    => $s->getSponsorId(),
        'name'         => $s->getName(),
        'company'      => $s->getCompany(),
        'email'        => $s->getEmail(),
        'contribution' => $s->getContribution(),
        'userId'       => $s->getUserId(),
        'amount'       => $s->getAmount(),
    ];
}, $list);

echo json_encode($data);
