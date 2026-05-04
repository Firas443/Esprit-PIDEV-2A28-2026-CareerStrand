<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Participation.php';

class ParticipationController
{

    // ── READ ALL ──────────────────────────────────────
    public function listerParticipations(): array
    {
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
    public function getByEvent(int $eventId): array
    {
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
    public function getByUser(int $userId): array
    {
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
    public function getById(int $id): ?Participation
    {
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
    public function alreadyRegistered(int $userId, int $eventId): bool
    {
        $sql = "SELECT COUNT(*) FROM participation
                WHERE userId = :uid AND eventId = :eid AND attendanceStatus != 'Cancelled'";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':uid' => $userId, ':eid' => $eventId]);
            return (int)$req->fetchColumn() > 0;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── CREATE FROM FRONT (real userId) ───────────────
    public function addParticipationFront(int $eventId, int $userId, string $userName, string $userEmail, string $regDate): bool
    {
        $sql = "INSERT INTO participation
                    (userId, eventId, registrationDate, attendanceStatus, status, rating, feedback)
                VALUES (:userId,:eventId,:regDate,'Pending','Pending',NULL,:feedback)";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                ':userId'   => $userId,
                ':eventId'  => $eventId,
                ':regDate'  => $regDate,
                ':feedback' => null,
            ]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── UPDATE ────────────────────────────────────────
    public function updateParticipation(int $id, Participation $p): bool
    {
        $sql = "UPDATE participation SET
                    attendanceStatus = :attStatus,
                    status           = :status,
                    rating           = :rating,
                    feedback         = :feedback
                WHERE participationId = :id";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                ':id'        => $id,
                ':attStatus' => $p->getAttendanceStatus(),
                ':status'    => $p->getStatus(),
                ':rating'    => $p->getRating(),
                ':feedback'  => $p->getFeedback(),
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── UPDATE FEEDBACK ONLY (from front) ─────────────
    public function updateFeedback(int $participationId, int $rating, string $comment): bool
    {
        $sql = "UPDATE participation SET rating = :rating, feedback = :feedback
                WHERE participationId = :id";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $participationId, ':rating' => $rating, ':feedback' => $comment]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── CANCEL ────────────────────────────────────────
    public function cancelParticipation(int $id): bool
    {
        $sql = "UPDATE participation SET attendanceStatus='Cancelled', status='Cancelled'
                WHERE participationId = :id";
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
    public function deleteParticipation(int $id): bool
    {
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

    // ── COUNT CONFIRMED BY EVENT ───────────────────────
    public function countByEvent(int $eventId): int
    {
        $sql = "SELECT COUNT(*) FROM participation
                WHERE eventId = :eid AND attendanceStatus = 'Confirmed'";
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
    private function rowToParticipation(array $row): Participation
    {
        $p = new Participation(
            isset($row['userId']) && $row['userId'] !== null ? (int)$row['userId'] : null,
            (int)$row['eventId'],
            $row['registrationDate'],
            $row['attendanceStatus'],
            $row['status']
        );
        $p->setParticipationId((int)$row['participationId']);
        $p->setRating(isset($row['rating']) && $row['rating'] !== null ? (int)$row['rating'] : null);
        $p->setFeedback($row['feedback'] ?? null);
        return $p;
    }
}
