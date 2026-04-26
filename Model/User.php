<?php

class User
{
    private ?int    $userId    = null;
    private string  $fullName;
    private string  $email;
    private string  $password;
    private string  $role;
    private string  $status;
    private string  $createdAt;

    public function __construct(
        string  $fullName  = '',
        string  $email     = '',
        string  $password  = '',
        string  $role      = 'user',
        string  $status    = 'active',
        string  $createdAt = ''
    ) {
        $this->fullName  = $fullName;
        $this->email     = $email;
        $this->password  = $password;
        $this->role      = $role;
        $this->status    = $status;
        $this->createdAt = $createdAt ?: date('Y-m-d');
    }

    // ── Getters ──────────────────────────────────────
    public function getUserId(): ?int     { return $this->userId;    }
    public function getFullName(): string { return $this->fullName;  }
    public function getEmail(): string    { return $this->email;     }
    public function getPassword(): string { return $this->password;  }
    public function getRole(): string     { return $this->role;      }
    public function getStatus(): string   { return $this->status;    }
    public function getCreatedAt(): string{ return $this->createdAt; }

    // ── Setters ──────────────────────────────────────
    public function setUserId(int $id): void      { $this->userId    = $id; }
    public function setFullName(string $v): void  { $this->fullName  = $v;  }
    public function setEmail(string $v): void     { $this->email     = $v;  }
    public function setPassword(string $v): void  { $this->password  = $v;  }
    public function setRole(string $v): void      { $this->role      = $v;  }
    public function setStatus(string $v): void    { $this->status    = $v;  }
    public function setCreatedAt(string $v): void { $this->createdAt = $v;  }
}
?>
