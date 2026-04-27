<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Participation.php';

class ParticipationController {

    // ── READ ALL ──────────────────────────────────────
    public function listerParticipations(): array {
        $sql = "SELECT * FROM participation ORDER BY registrationDate DESC";
        $db  = config::getConnexion();
        try {
            $stmt = $db->query($sql);
            $list = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToParticipation($row);
            }
            return $list;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── READ BY EVENT ─────────────────────────────────
    public function getByEvent(int $eventId): array {
        $sql = "SELECT * FROM participation WHERE eventId = :eid ORDER BY registrationDate DESC";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':eid' => $eventId]);
            $list = [];
            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToParticipation($row);
            }
            return $list;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── READ BY USER ──────────────────────────────────
    public function getByUser(int $userId): array {
        $sql = "SELECT * FROM participation WHERE userId = :uid ORDER BY registrationDate DESC";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':uid' => $userId]);
            $list = [];
            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToParticipation($row);
            }
            return $list;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── READ ONE ──────────────────────────────────────
    public function getById(int $id): ?Participation {
        $sql = "SELECT * FROM participation WHERE participationId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            $row = $req->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->rowToParticipation($row) : null;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── CHECK DUPLICATE ───────────────────────────────
    public function alreadyRegistered(int $userId, int $eventId): bool {
        $sql = "SELECT COUNT(*) FROM participation WHERE userId = :uid AND eventId = :eid AND status != 'Cancelled'";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':uid' => $userId, ':eid' => $eventId]);
            return (int)$req->fetchColumn() > 0;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── CREATE ────────────────────────────────────────
    public function addParticipation(Participation $p): bool {
        // Prevent duplicate
        if ($this->alreadyRegistered($p->getUserId(), $p->getEventId())) {
            return false;
        }
        $sql = "INSERT INTO participation (userId, eventId, registrationDate, attendanceStatus, status)
                VALUES (:userId, :eventId, :regDate, :attStatus, :status)";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'userId'    => $p->getUserId(),
                'eventId'   => $p->getEventId(),
                'regDate'   => $p->getRegistrationDate(),
                'attStatus' => $p->getAttendanceStatus(),
                'status'    => $p->getStatus(),
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── UPDATE ────────────────────────────────────────
    public function updateParticipation(int $id, Participation $p): bool {
        $sql = "UPDATE participation SET
                    attendanceStatus = :attStatus,
                    status           = :status
                WHERE participationId = :id";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id'        => $id,
                'attStatus' => $p->getAttendanceStatus(),
                'status'    => $p->getStatus(),
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── CANCEL (soft delete) ──────────────────────────
    public function cancelParticipation(int $id): bool {
        $sql = "UPDATE participation SET attendanceStatus = 'Cancelled', status = 'inactive' WHERE participationId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── DELETE ────────────────────────────────────────
    public function deleteParticipation(int $id): bool {
        $sql = "DELETE FROM participation WHERE participationId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── COUNT BY EVENT ────────────────────────────────
    public function countByEvent(int $eventId): int {
        $sql = "SELECT COUNT(*) FROM participation WHERE eventId = :eid AND status = 'active'";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':eid' => $eventId]);
            return (int)$req->fetchColumn();
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── HELPER ────────────────────────────────────────
    private function rowToParticipation(array $row): Participation {
        $p = new Participation(
            (int)$row['userId'],
            (int)$row['eventId'],
            $row['registrationDate'],
            $row['attendanceStatus'],
            $row['status']
        );
        $p->setParticipationId((int)$row['participationId']);
        return $p;
    }
}
?>
