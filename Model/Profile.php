<?php

class Profile
{
    public function __construct(
        private ?int    $profileId       = null,
        private ?int    $userId          = null,
        private ?string $bio             = null,
        private ?string $photoUrl        = null,
        private ?string $location        = null,
        private ?string $preferences     = null,
        private ?int    $completionScore = null,
        private ?string $level           = null
    ) {
    }

    public function getProfileId(): ?int      { return $this->profileId;       }
    public function getUserId(): ?int         { return $this->userId;          }
    public function getBio(): ?string         { return $this->bio;             }
    public function getPhotoUrl(): ?string    { return $this->photoUrl;        }
    public function getLocation(): ?string    { return $this->location;        }
    public function getPreferences(): ?string { return $this->preferences;     }
    public function getCompletionScore(): ?int { return $this->completionScore; }
    public function getLevel(): ?string       { return $this->level;           }
}
