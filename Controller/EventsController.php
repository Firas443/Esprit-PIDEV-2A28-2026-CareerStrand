<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Event.php';

class EventsController {

    // ── READ ALL ───────────────────────────────────── 
    public function listerEvents(): array {
        $sql = "SELECT * FROM event ORDER BY date DESC";
        $db  = config::getConnexion();
        try {
            $stmt   = $db->query($sql);
            $events = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { // fetch() lit une ligne à la fois, retourne false quand c'est fini
                $events[] = $this->rowToEvent($row); // Convertit le tableau en objet Event
            }
            return $events;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── READ ONE ──────────────────────────────────────
    public function getEventById(int $id): ?Event {
        $sql = "SELECT * FROM event WHERE eventId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            $row = $req->fetch(PDO::FETCH_ASSOC);// Récupère une seule ligne
            return $row ? $this->rowToEvent($row) : null; // Si $row existe : convertit en Event, sinon : retourne null
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── READ BY TYPE ──────────────────────────────────
    public function getEventsByType(string $type): array {
        $sql = "SELECT * FROM event WHERE type = :type ORDER BY date DESC";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':type' => $type]);
            $events = [];
            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
                $events[] = $this->rowToEvent($row);
            }
            return $events;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── READ UPCOMING ─────────────────────────────────
    public function getUpcomingEvents(): array {
        $sql = "SELECT * FROM event WHERE status = 'Upcoming' ORDER BY date ASC";
        $db  = config::getConnexion();
        try {
            $stmt   = $db->query($sql);
            $events = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $events[] = $this->rowToEvent($row);
            }
            return $events;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── CREATE (eventId manuel — pas d'AUTO_INCREMENT) ─
    public function addEvent(Event $event): bool {
        // Si un eventId manuel est fourni on l'inclut, sinon on laisse la DB décider
        if ($event->getEventId() !== null) {
            $sql = "INSERT INTO event
        (eventId, managerId, sponsorId, title, description, type, location,
         capacity, tags, organiser, time, eventMode, duration, date, status, createdAt)
        VALUES
        (:eventId, :managerId, :sponsorId, :title, :description, :type, :location,
         :capacity, :tags, :organiser, :time, :eventMode, :duration, :date, :status, CURDATE())";
            $params = [
                'eventId'     => $event->getEventId(),
                'managerId'   => $event->getManagerId(),
                'sponsorId'   => $event->getSponsorId(),
                'title'       => $event->getName(),
                'description' => $event->getDescription(),
                'type'        => $event->getType(),
                'location'    => $event->getLocation(),
                'capacity'    => $event->getCapacity(),
                'tags'        => $event->getTags(),
                'organiser'   => $event->getOrganiser(),
                'time'        => $event->getTime(),
                'eventMode' => $event->getEventMode(),
                'duration'    => $event->getDuration(),
                'date'        => $event->getDate(),
                'status'      => $event->getStatus(),
            ];
        } else {
           $sql = "INSERT INTO event
        (managerId, sponsorId, title, description, type, location,
         capacity, tags, organiser, time, eventMode, duration, date, status, createdAt)
        VALUES
        (:managerId, :sponsorId, :title, :description, :type, :location,
         :capacity, :tags, :organiser, :time, :eventMode, :duration, :date, :status, CURDATE())";
            $params = [
                'managerId'   => $event->getManagerId(),
                'sponsorId'   => $event->getSponsorId(),
                'title'       => $event->getName(),
                'description' => $event->getDescription(),
                'type'        => $event->getType(),
                'location'    => $event->getLocation(),
                'capacity'    => $event->getCapacity(),
                'tags'        => $event->getTags(),
                'organiser'   => $event->getOrganiser(),
                'time'        => $event->getTime(),
                'eventMode' => $event->getEventMode(),
                'duration'    => $event->getDuration(),
                'date'        => $event->getDate(),
                'status'      => $event->getStatus(),
            ];
        }
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute($params);
            return true; // true = succès
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── UPDATE ────────────────────────────────────────
    public function updateEvent(int $id, Event $event): bool {
      $sql = "UPDATE event SET
            sponsorId   = :sponsorId,
            title       = :title,
            description = :description,
            type        = :type,
            location    = :location,
            capacity    = :capacity,
            tags        = :tags,
            organiser   = :organiser,
            time        = :time,
            eventMode   = :eventMode,
            duration    = :duration,
            date        = :date,
            status      = :status
        WHERE eventId = :id";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id'          => $id,
                'sponsorId'   => $event->getSponsorId(),
                'title'       => $event->getName(),
                'description' => $event->getDescription(),
                'type'        => $event->getType(),
                'location'    => $event->getLocation(),
                'capacity'    => $event->getCapacity(),
                'tags'        => $event->getTags(),
                'organiser'   => $event->getOrganiser(),
                'time'        => $event->getTime(),
                'eventMode' => $event->getEventMode(),
                'duration'    => $event->getDuration(),
                'date'        => $event->getDate(),
                'status'      => $event->getStatus(),
            ]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── DELETE ────────────────────────────────────────
    public function deleteEvent(int $id): bool {
        $sql = "DELETE FROM event WHERE eventId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── SEARCH ────────────────────────────────────────
    public function searchEvents(string $keyword): array {
        $sql = "SELECT * FROM event
                WHERE title LIKE :kw OR description LIKE :kw
                   OR location LIKE :kw OR tags LIKE :kw OR organiser LIKE :kw
                ORDER BY date DESC";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':kw' => '%' . $keyword . '%']); // % =  "n'importe quoi avant/après"
            $events = [];
            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
                $events[] = $this->rowToEvent($row);
            }
            return $events;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── HELPER: row → Event object ────────────────────
    private function rowToEvent(array $row): Event {
         // Convertit un tableau associatif SQL en objet Event PHP
        $title = $row['title'] ?? $row['name'] ?? '';
        $e = new Event(
            $title,
            $row['description'] ?? '',
            $row['type']        ?? '',
            $row['location']    ?? '',
            (int)($row['capacity'] ?? 0),
            $row['date']        ?? '',
            $row['status']      ?? '',
            isset($row['managerId']) ? (int)$row['managerId'] : null,
            $row['tags']        ?? '',
            $row['organiser']   ?? '',
            $row['time']        ?? '',
            $row['eventMode'] ?? 'Online',
            isset($row['sponsorId']) ? (int)$row['sponsorId'] : null,
            (int)($row['duration'] ?? 0)
        );
        $e->setEventId((int)$row['eventId']);
        return $e;
    }
}
?>
