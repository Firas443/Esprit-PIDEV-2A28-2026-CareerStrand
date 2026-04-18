<?php

class User
{
    public function __construct(
        private ?int    $userId    = null,
        private ?string $fullName  = null,
        private ?string $email     = null,
        private ?string $password  = null,
        private ?string $role      = null,
        private ?string $status    = null,
        private ?string $createdAt = null
    ) {
    }

    public function getUserId(): ?int    { return $this->userId;    }
    public function getFullName(): ?string { return $this->fullName; }
    public function getEmail(): ?string   { return $this->email;    }
    public function getPassword(): ?string { return $this->password; }
    public function getRole(): ?string    { return $this->role;     }
    public function getStatus(): ?string  { return $this->status;   }
    public function getCreatedAt(): ?string { return $this->createdAt; }
}
