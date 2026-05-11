<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/Sponsor.php';

class SponsorController {

    // ── READ ALL ──────────────────────────────────────
    public function listerSponsors(): array {
        $sql = "SELECT * FROM Sponsor ORDER BY sponsorId DESC";
        $db  = config::getConnexion();
        try {
            $stmt = $db->query($sql);
            $list = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToSponsor($row);
            }
            return $list;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── READ BY USER ──────────────────────────────────
    public function getByUser(int $userId): array {
        $sql = "SELECT * FROM Sponsor WHERE userId = :uid";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':uid' => $userId]);
            $list = [];
            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $this->rowToSponsor($row);
            }
            return $list;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── READ ONE ──────────────────────────────────────
    public function getById(int $id): ?Sponsor {
        $sql = "SELECT * FROM Sponsor WHERE sponsorId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            $row = $req->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->rowToSponsor($row) : null;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── CREATE ────────────────────────────────────────
    public function addSponsor(Sponsor $s): bool {
        $sql = "INSERT INTO Sponsor (userId, name, company, email, contribution, amount)
                VALUES (:userId, :name, :company, :email, :contribution, :amount)";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'userId'       => $s->getUserId(),
                'name'         => $s->getName(),
                'company'      => $s->getCompany(),
                'email'        => $s->getEmail(),
                'contribution' => $s->getContribution(),
                'amount'       => $s->getAmount(),
            ]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── UPDATE ────────────────────────────────────────
    public function updateSponsor(int $id, Sponsor $s): bool {
        $sql = "UPDATE Sponsor SET
                    userId       = :userId,
                    name         = :name,
                    company      = :company,
                    email        = :email,
                    contribution = :contribution,
                    amount       = :amount
                WHERE sponsorId = :id";
        $db = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id'           => $id,
                'userId'       => $s->getUserId(),
                'name'         => $s->getName(),
                'company'      => $s->getCompany(),
                'email'        => $s->getEmail(),
                'contribution' => $s->getContribution(),
                'amount'       => $s->getAmount(),
            ]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── DELETE ────────────────────────────────────────
    public function deleteSponsor(int $id): bool {
        $sql = "DELETE FROM Sponsor WHERE sponsorId = :id";
        $db  = config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([':id' => $id]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ── HELPER ────────────────────────────────────────
    private function rowToSponsor(array $row): Sponsor {
        $s = new Sponsor(
            $row['name'],
            $row['company'],
            $row['email'],
            $row['contribution'],
            (float)$row['amount'],
            isset($row['userId']) ? (int)$row['userId'] : null
        );
        $s->setSponsorId((int)$row['sponsorId']);
        return $s;
    }
}
?>
