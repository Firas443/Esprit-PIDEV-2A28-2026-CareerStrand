<?php

class User
{
    private ?int    $userId         = null;
    private string  $fullName;
    private string  $email;
    private string  $password;
    private string  $role;
    private string  $status;
    private string  $createdAt;
    private ?string $faceDescriptor = null;
    private int     $faceEnabled    = 0;
    private string  $approvalStatus = 'approved';
    private ?string $rejectionReason = null;

    public function __construct(
        string  $fullName   = '',
        string  $email      = '',
        string  $password   = '',
        string  $role       = 'user',
        string  $status     = 'active',
        string  $createdAt  = '',
        ?string $faceDescriptor = null,
        int     $faceEnabled    = 0,
        string  $approvalStatus = 'approved',
        ?string $rejectionReason = null
    ) {
        $this->fullName       = $fullName;
        $this->email          = $email;
        $this->password       = $password;
        $this->role           = $role;
        $this->status         = $status;
        $this->createdAt      = $createdAt ?: date('Y-m-d');
        $this->faceDescriptor = $faceDescriptor;
        $this->faceEnabled    = $faceEnabled;
        $this->approvalStatus = $approvalStatus;
        $this->rejectionReason = $rejectionReason;
    }

    // ── Getters ──────────────────────────────────────────
    public function getUserId(): ?int          { return $this->userId;         }
    public function getFullName(): string      { return $this->fullName;       }
    public function getEmail(): string         { return $this->email;          }
    public function getPassword(): string      { return $this->password;       }
    public function getRole(): string          { return $this->role;           }
    public function getStatus(): string        { return $this->status;         }
    public function getCreatedAt(): string     { return $this->createdAt;      }
    public function getFaceDescriptor(): ?string { return $this->faceDescriptor; }
    public function getFaceEnabled(): int      { return $this->faceEnabled;    }
    public function isFaceEnabled(): bool      { return $this->faceEnabled === 1; }
    public function getApprovalStatus(): string { return $this->approvalStatus; }
    public function getRejectionReason(): ?string { return $this->rejectionReason; }

    // ── Setters ──────────────────────────────────────────
    public function setUserId(int $id): void           { $this->userId         = $id;  }
    public function setFullName(string $v): void       { $this->fullName       = $v;   }
    public function setEmail(string $v): void          { $this->email          = $v;   }
    public function setPassword(string $v): void       { $this->password       = $v;   }
    public function setRole(string $v): void           { $this->role           = $v;   }
    public function setStatus(string $v): void         { $this->status         = $v;   }
    public function setCreatedAt(string $v): void      { $this->createdAt      = $v;   }
    public function setFaceDescriptor(?string $v): void { $this->faceDescriptor = $v;  }
    public function setFaceEnabled(int $v): void       { $this->faceEnabled    = $v;   }
    public function setApprovalStatus(string $v): void { $this->approvalStatus = $v; }
    public function setRejectionReason(?string $v): void { $this->rejectionReason = $v; }
}
?>