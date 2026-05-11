<?php
class Sponsor {
    private ?int $sponsorId = null;
    private ?int $userId = null;
    private string $name;
    private string $company;
    private string $email;
    private string $contribution;
    private float $amount;

    public function __construct(
        string $name,
        string $company,
        string $email,
        string $contribution,
        float $amount = 0.0,
        ?int $userId = null
    ) {
        $this->userId = $userId;
        $this->name = $name;
        $this->company = $company;
        $this->email = $email;
        $this->contribution = $contribution;
        $this->amount = $amount;
    }

    public function getSponsorId(): ?int { return $this->sponsorId; }
    public function getUserId(): ?int { return $this->userId; }
    public function getName(): string { return $this->name; }
    public function getCompany(): string { return $this->company; }
    public function getEmail(): string { return $this->email; }
    public function getContribution(): string { return $this->contribution; }
    public function getAmount(): float { return $this->amount; }

    public function setSponsorId(int $id): void { $this->sponsorId = $id; }
    public function setUserId(?int $value): void { $this->userId = $value; }
    public function setName(string $value): void { $this->name = $value; }
    public function setCompany(string $value): void { $this->company = $value; }
    public function setEmail(string $value): void { $this->email = $value; }
    public function setContribution(string $value): void { $this->contribution = $value; }
    public function setAmount(float $value): void { $this->amount = $value; }
}
?>
