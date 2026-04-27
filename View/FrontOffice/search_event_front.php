<?php
require_once __DIR__ . '/../../Controller/EventsController.php';

header('Content-Type: application/json');

$eventC  = new EventsController();
$keyword = trim($_GET['q']    ?? '');
$type    = trim($_GET['type'] ?? '');

if ($keyword !== '') {
    $events = $eventC->searchEvents($keyword);
} elseif ($type !== '' && $type !== 'all') {
    $events = $eventC->getEventsByType($type);
} else {
    $events = $eventC->listerEvents();
}

$data = array_map(function($e) {
    return [
        'eventId'     => $e->getEventId(),
        'managerId'   => $e->getManagerId(),
        'name'        => $e->getName(),
        'description' => $e->getDescription(),
        'type'        => $e->getType(),
        'location'    => $e->getLocation(),
        'capacity'    => $e->getCapacity(),
        'date'        => $e->getDate(),
        'status'      => $e->getStatus(),
        'createdAt'   => $e->getCreatedAt(),
        'sponsorId'   => $e->getSponsorId(),
        'duration'    => $e->getDuration(),
        'tags'        => $e->getTags(),
        'organiser'   => $e->getOrganiser(),
        'time'        => $e->getTime(),
        'eventMode'   => $e->getEventMode(),
    ];
}, $events);

echo json_encode($data);
