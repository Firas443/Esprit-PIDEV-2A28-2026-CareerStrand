<?php

class ManagerProfile
{
    private ?int    $managerProfileId = null;
    private ?int    $userId           = null;
    private string  $organization;
    private string  $categoryFocus;
    private string  $description;

    public function __construct(
        string  $organization  = '',
        string  $categoryFocus = '',
        string  $description   = '',
        ?int    $userId        = null
    ) {
        $this->organization  = $organization;
        $this->categoryFocus = $categoryFocus;
        $this->description   = $description;
        $this->userId        = $userId;
    }

    // ── Getters ──────────────────────────────────────
    public function getManagerProfileId(): ?int  { return $this->managerProfileId; }
    public function getUserId(): ?int            { return $this->userId;           }
    public function getOrganization(): string    { return $this->organization;     }
    public function getCategoryFocus(): string   { return $this->categoryFocus;    }
    public function getDescription(): string     { return $this->description;      }

    // ── Setters ──────────────────────────────────────
    public function setManagerProfileId(int $id): void  { $this->managerProfileId = $id;    }
    public function setUserId(?int $id): void            { $this->userId           = $id;    }
    public function setOrganization(string $v): void     { $this->organization     = $v;     }
    public function setCategoryFocus(string $v): void    { $this->categoryFocus    = $v;     }
    public function setDescription(string $v): void      { $this->description      = $v;     }
}
?>
