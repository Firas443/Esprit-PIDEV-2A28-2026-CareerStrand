<?php
require_once __DIR__ . '/../../Controller/SponsorController.php';

// ── Toujours répondre en JSON (appelé via fetch() depuis le JS) ──
header('Content-Type: application/json');

$sc   = new SponsorController();
$list = $sc->listerSponsors();

// FrontOffice : on affiche uniquement les sponsors actifs
$data = array_map(function($s) {
    return [
        'sponsorId'    => $s->getSponsorId(),
        'name'         => $s->getName(),
        'company'      => $s->getCompany(),
        'email'        => $s->getEmail(),
        'contribution' => $s->getContribution(),
        'amount'       => $s->getAmount(),
    ];
}, $list);

echo json_encode($data);
