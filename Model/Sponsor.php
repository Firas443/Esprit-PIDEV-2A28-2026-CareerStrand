<?php
class Sponsor {
    private ?int $sponsorId = null;
    private ?int $userId = null;
    private string $name;
    private string $company;
    private string $email;
    private string $contribution;  // description of what they provide
    private float $amount;

    public function __construct(
    string $name,
    string $company,
    string $email,
    string $contribution,
    float $amount = 0.0,
    ?int $userId = null    // ← toujours EN DERNIER
) {
        $this->userId       = $userId;
        $this->name         = $name;
        $this->company      = $company;
        $this->email        = $email;
        $this->contribution = $contribution;
        $this->amount       = $amount;
    }

    // ── Getters ──────────────────────────────────────
    public function getSponsorId(): ?int      { return $this->sponsorId; }
    public function getUserId(): ?int         { return $this->userId; }
    public function getName(): string         { return $this->name; }
    public function getCompany(): string      { return $this->company; }
    public function getEmail(): string        { return $this->email; }
    public function getContribution(): string { return $this->contribution; }
    public function getAmount(): float        { return $this->amount; }

    // ── Setters ──────────────────────────────────────
    public function setSponsorId(int $id): void        { $this->sponsorId = $id; }
    public function setUserId(?int $v): void           { $this->userId = $v; }
    public function setName(string $v): void           { $this->name = $v; }
    public function setCompany(string $v): void        { $this->company = $v; }
    public function setEmail(string $v): void          { $this->email = $v; }
    public function setContribution(string $v): void   { $this->contribution = $v; }
    public function setAmount(float $v): void          { $this->amount = $v; }
}
?>
