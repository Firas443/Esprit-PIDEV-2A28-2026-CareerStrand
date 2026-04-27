<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/EventForm.php';

class EventFormController {

    // ── READ ALL ──────────────────────────────────────
    public function listerForms(): array {
        $sql = "SELECT * FROM eventform ORDER BY formId DESC";
        $db  = config::getConnexion();
        try {
            $stmt = $db->query($sql);
            $list = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToForm($row);
            }
            return $list;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── READ BY EVENT ─────────────────────────────────
    public function getByEvent(int $eventId): array {
        $sql = "SELECT * FROM eventform WHERE eventId = :eid";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':eid' => $eventId]);
            $list = [];
            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToForm($row);
            }
            return $list;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── READ ONE ──────────────────────────────────────
    public function getById(int $id): ?EventForm {
        $sql = "SELECT * FROM eventform WHERE formId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            $row = $req->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->rowToForm($row) : null;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── CREATE ────────────────────────────────────────
    public function addForm(EventForm $f): bool {
        $sql = "INSERT INTO eventform (eventId, title, description, formLink, status)
                VALUES (:eventId, :title, :description, :formLink, :status)";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'eventId'     => $f->getEventId(),
                'title'       => $f->getTitle(),
                'description' => $f->getDescription(),
                'formLink'    => $f->getFormLink(),
                'status'      => $f->getStatus(),
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── UPDATE ────────────────────────────────────────
    public function updateForm(int $id, EventForm $f): bool {
        $sql = "UPDATE eventform SET
                    title       = :title,
                    description = :description,
                    formLink    = :formLink,
                    status      = :status
                WHERE formId = :id";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id'          => $id,
                'title'       => $f->getTitle(),
                'description' => $f->getDescription(),
                'formLink'    => $f->getFormLink(),
                'status'      => $f->getStatus(),
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── DELETE ────────────────────────────────────────
    public function deleteForm(int $id): bool {
        $sql = "DELETE FROM eventform WHERE formId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── TOGGLE STATUS ─────────────────────────────────
    public function toggleStatus(int $id, string $status): bool {
        $sql = "UPDATE eventform SET status = :status WHERE formId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id, ':status' => $status]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // ── HELPER ────────────────────────────────────────
    private function rowToForm(array $row): EventForm {
        $f = new EventForm(
            (int)$row['eventId'],
            $row['title'],
            $row['description'],
            $row['formLink'],
            $row['status']
        );
        $f->setFormId((int)$row['formId']);
        return $f;
    }
}
?>
