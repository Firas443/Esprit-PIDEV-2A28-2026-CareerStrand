<?php

class Profile
{
    private ?int    $profileId       = null;
    private ?int    $userId          = null;
    private string  $bio;
    private string  $photoUrl;
    private string  $location;
    private string  $preferences;
    private int     $completionScore;
    private string  $level;
    private string  $careerGoal;
    private string  $portfolioTheme;
    private string  $portfolioPrimaryColor;
    private string  $portfolioSecondaryColor;
    private string  $cvTheme;
    private string  $cvPrimaryColor;
    private string  $cvSecondaryColor;

    public function __construct(
        string  $bio             = '',
        string  $photoUrl        = '',
        string  $location        = '',
        string  $preferences     = '',
        int     $completionScore = 0,
        string  $level           = 'Starter',
        ?int    $userId          = null,
        string  $careerGoal      = '',
        string  $portfolioTheme   = 'midnight',
        string  $portfolioPrimaryColor = '#8fb1ff',
        string  $portfolioSecondaryColor = '#ff7a50',
        string  $cvTheme         = 'graphite',
        string  $cvPrimaryColor   = '#1f1b1b',
        string  $cvSecondaryColor = '#efe7df'
    ) {
        $this->bio             = $bio;
        $this->photoUrl        = $photoUrl;
        $this->location        = $location;
        $this->preferences     = $preferences;
        $this->completionScore = $completionScore;
        $this->level           = $level;
        $this->userId          = $userId;
        $this->careerGoal      = $careerGoal;
        $this->portfolioTheme  = $portfolioTheme;
        $this->portfolioPrimaryColor = $portfolioPrimaryColor;
        $this->portfolioSecondaryColor = $portfolioSecondaryColor;
        $this->cvTheme         = $cvTheme;
        $this->cvPrimaryColor   = $cvPrimaryColor;
        $this->cvSecondaryColor = $cvSecondaryColor;
    }

    // ── Getters ──────────────────────────────────────
    public function getProfileId(): ?int       { return $this->profileId;       }
    public function getUserId(): ?int          { return $this->userId;          }
    public function getBio(): string           { return $this->bio;             }
    public function getPhotoUrl(): string      { return $this->photoUrl;        }
    public function getLocation(): string      { return $this->location;        }
    public function getPreferences(): string   { return $this->preferences;     }
    public function getCompletionScore(): int  { return $this->completionScore; }
    public function getLevel(): string         { return $this->level;           }
    public function getCareerGoal(): string    { return $this->careerGoal;      }
    public function getPortfolioTheme(): string { return $this->portfolioTheme; }
    public function getPortfolioPrimaryColor(): string { return $this->portfolioPrimaryColor; }
    public function getPortfolioSecondaryColor(): string { return $this->portfolioSecondaryColor; }
    public function getCvTheme(): string { return $this->cvTheme; }
    public function getCvPrimaryColor(): string { return $this->cvPrimaryColor; }
    public function getCvSecondaryColor(): string { return $this->cvSecondaryColor; }

    // ── Setters ──────────────────────────────────────
    public function setProfileId(int $id): void       { $this->profileId       = $id; }
    public function setUserId(?int $id): void          { $this->userId          = $id; }
    public function setBio(string $v): void            { $this->bio             = $v;  }
    public function setPhotoUrl(string $v): void       { $this->photoUrl        = $v;  }
    public function setLocation(string $v): void       { $this->location        = $v;  }
    public function setPreferences(string $v): void    { $this->preferences     = $v;  }
    public function setCompletionScore(int $v): void   { $this->completionScore = $v;  }
    public function setLevel(string $v): void          { $this->level           = $v;  }
    public function setCareerGoal(string $v): void     { $this->careerGoal      = $v;  }
    public function setPortfolioTheme(string $v): void { $this->portfolioTheme  = $v;  }
    public function setPortfolioPrimaryColor(string $v): void { $this->portfolioPrimaryColor = $v; }
    public function setPortfolioSecondaryColor(string $v): void { $this->portfolioSecondaryColor = $v; }
    public function setCvTheme(string $v): void { $this->cvTheme = $v; }
    public function setCvPrimaryColor(string $v): void { $this->cvPrimaryColor = $v; }
    public function setCvSecondaryColor(string $v): void { $this->cvSecondaryColor = $v; }
}
?>
