<?php

class Challenge
{
    public function __construct(
        private ?int $challengeId = null,
        private ?int $groupId = null,
        private ?int $managerId = null,
        private ?string $type = null,
        private ?string $title = null,
        private ?string $description = null,
        private ?string $difficulty = null,
        private ?string $deadline = null,
        private ?string $status = null,
        private ?string $createdAt = null
    ) {
    }

    public function getChallengeId(): ?int
    {
        return $this->challengeId;
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function getManagerId(): ?int
    {
        return $this->managerId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function getDeadline(): ?string
    {
        return $this->deadline;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
