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

    public function __construct(
        string  $bio             = '',
        string  $photoUrl        = '',
        string  $location        = '',
        string  $preferences     = '',
        int     $completionScore = 0,
        string  $level           = 'Starter',
        ?int    $userId          = null
    ) {
        $this->bio             = $bio;
        $this->photoUrl        = $photoUrl;
        $this->location        = $location;
        $this->preferences     = $preferences;
        $this->completionScore = $completionScore;
        $this->level           = $level;
        $this->userId          = $userId;
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

    // ── Setters ──────────────────────────────────────
    public function setProfileId(int $id): void       { $this->profileId       = $id; }
    public function setUserId(?int $id): void          { $this->userId          = $id; }
    public function setBio(string $v): void            { $this->bio             = $v;  }
    public function setPhotoUrl(string $v): void       { $this->photoUrl        = $v;  }
    public function setLocation(string $v): void       { $this->location        = $v;  }
    public function setPreferences(string $v): void    { $this->preferences     = $v;  }
    public function setCompletionScore(int $v): void   { $this->completionScore = $v;  }
    public function setLevel(string $v): void          { $this->level           = $v;  }
}
?>
