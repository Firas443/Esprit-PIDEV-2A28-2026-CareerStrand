<?php

class SkillHub
{
    public function __construct(
        private ?int $groupId = null,
        private ?string $name = null,
        private ?string $category = null,
        private ?string $description = null,
        private ?string $createdAt = null,
        private ?string $status = null
    ) {
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
