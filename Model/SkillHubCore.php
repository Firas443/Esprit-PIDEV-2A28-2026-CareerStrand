<?php

class SkillHubEntity
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

class GroupMemberEntity
{
    public function __construct(
        private ?int $groupMemberId = null,
        private ?int $groupId = null,
        private ?int $userId = null,
        private ?string $joinedAt = null,
        private ?string $status = null
    ) {
    }

    public function getGroupMemberId(): ?int
    {
        return $this->groupMemberId;
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getJoinedAt(): ?string
    {
        return $this->joinedAt;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}

class ChallengeEntity
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
