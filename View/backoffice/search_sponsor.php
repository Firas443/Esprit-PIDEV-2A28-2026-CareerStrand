<?php
require_once __DIR__ . '/../../Controller/SponsorController.php';

header('Content-Type: application/json');

$sc   = new SponsorController();
$list = $sc->listerSponsors();

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
