<?php

class UserSkill
{
    public function __construct(
        private ?int    $userSkillId    = null,
        private ?int    $userId         = null,
        private ?string $skillName      = null,
        private ?string $source         = null,
        private ?string $certificateUrl = null,
        private ?string $validatedAt    = null
    ) {
    }

    // Getters
    public function getUserSkillId(): ?int    { return $this->userSkillId;    }
    public function getUserId(): ?int         { return $this->userId;         }
    public function getSkillName(): ?string   { return $this->skillName;      }
    public function getSource(): ?string      { return $this->source;         }
    public function getCertificateUrl(): ?string { return $this->certificateUrl; }
    public function getValidatedAt(): ?string { return $this->validatedAt;    }

    // Helper methods
    public function toArray(): array
    {
        return [
            'userSkillId'    => $this->userSkillId,
            'userId'         => $this->userId,
            'skillName'      => $this->skillName,
            'source'         => $this->source,
            'certificateUrl' => $this->certificateUrl,
            'validatedAt'    => $this->validatedAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        $userSkill = new self();
        $userSkill->userSkillId    = $data['userSkillId'] ?? null;
        $userSkill->userId         = $data['userId'] ?? null;
        $userSkill->skillName      = $data['skillName'] ?? null;
        $userSkill->source         = $data['source'] ?? null;
        $userSkill->certificateUrl = $data['certificateUrl'] ?? null;
        $userSkill->validatedAt    = $data['validatedAt'] ?? null;
        return $userSkill;
    }
}