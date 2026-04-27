<?php
require_once __DIR__ . '/../../Controller/EventFormController.php';

header('Content-Type: application/json');

$formC = new EventFormController();
$eventId = isset($_GET['eventId']) ? (int)$_GET['eventId'] : 0;

if ($eventId > 0) {
    $list = $formC->getByEvent($eventId);
} else {
    $list = $formC->listerForms();
}

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