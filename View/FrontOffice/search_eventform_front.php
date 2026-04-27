<?php
require_once __DIR__ . '/../../Controller/EventFormController.php';

header('Content-Type: application/json');

$formC = new EventFormController();
$list = $formC->listerForms();

// For frontoffice, we may filter only open forms, but keep all for now
$data = array_map(function($f) {
    return [
        'formId'      => $f->getFormId(),
        'eventId'     => $f->getEventId(),
        'title'       => $f->getTitle(),
        'description' => $f->getDescription(),
        'formLink'    => $f->getFormLink(),
        'status'      => $f->getStatus(),
    ];
}, $list);

echo json_encode($data);