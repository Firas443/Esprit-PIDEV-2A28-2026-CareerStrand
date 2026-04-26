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

    // ── Getters ──────────────────────────────────────
    public function getUserSkillId(): ?int       { return $this->userSkillId;    }
    public function getUserId(): ?int            { return $this->userId;         }
    public function getSkillName(): ?string      { return $this->skillName;      }
    public function getSource(): ?string         { return $this->source;         }
    public function getCertificateUrl(): ?string { return $this->certificateUrl; }
    public function getValidatedAt(): ?string    { return $this->validatedAt;    }

    // ── Setters ──────────────────────────────────────
    public function setUserSkillId(?int $id): void       { $this->userSkillId    = $id; }
    public function setUserId(?int $id): void            { $this->userId         = $id; }
    public function setSkillName(?string $v): void       { $this->skillName      = $v;  }
    public function setSource(?string $v): void          { $this->source         = $v;  }
    public function setCertificateUrl(?string $v): void  { $this->certificateUrl = $v;  }
    public function setValidatedAt(?string $v): void     { $this->validatedAt    = $v;  }

    // ── Helpers ──────────────────────────────────────
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
        $s = new self();
        $s->userSkillId    = $data['userSkillId']    ?? null;
        $s->userId         = $data['userId']         ?? null;
        $s->skillName      = $data['skillName']      ?? null;
        $s->source         = $data['source']         ?? null;
        $s->certificateUrl = $data['certificateUrl'] ?? null;
        $s->validatedAt    = $data['validatedAt']    ?? null;
        return $s;
    }
}
?>
