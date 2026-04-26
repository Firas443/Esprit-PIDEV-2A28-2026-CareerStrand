<?php

class RecruiterProfile
{
    private ?int    $recruiterProfileId = null;
    private ?int    $userId             = null;
    private string  $companyName;
    private string  $jobTitle;
    private string  $industry;
    private string  $companyWebsite;
    private string  $opportunityTypes;

    public function __construct(
        string  $companyName      = '',
        string  $jobTitle         = '',
        string  $industry         = '',
        string  $companyWebsite   = '',
        string  $opportunityTypes = '',
        ?int    $userId           = null
    ) {
        $this->companyName      = $companyName;
        $this->jobTitle         = $jobTitle;
        $this->industry         = $industry;
        $this->companyWebsite   = $companyWebsite;
        $this->opportunityTypes = $opportunityTypes;
        $this->userId           = $userId;
    }

    // ── Getters ──────────────────────────────────────
    public function getRecruiterProfileId(): ?int  { return $this->recruiterProfileId; }
    public function getUserId(): ?int              { return $this->userId;             }
    public function getCompanyName(): string       { return $this->companyName;        }
    public function getJobTitle(): string          { return $this->jobTitle;           }
    public function getIndustry(): string          { return $this->industry;           }
    public function getCompanyWebsite(): string    { return $this->companyWebsite;     }
    public function getOpportunityTypes(): string  { return $this->opportunityTypes;   }

    // ── Setters ──────────────────────────────────────
    public function setRecruiterProfileId(int $id): void  { $this->recruiterProfileId = $id; }
    public function setUserId(?int $id): void              { $this->userId             = $id; }
    public function setCompanyName(string $v): void        { $this->companyName        = $v;  }
    public function setJobTitle(string $v): void           { $this->jobTitle           = $v;  }
    public function setIndustry(string $v): void           { $this->industry           = $v;  }
    public function setCompanyWebsite(string $v): void     { $this->companyWebsite     = $v;  }
    public function setOpportunityTypes(string $v): void   { $this->opportunityTypes   = $v;  }
}
?>
