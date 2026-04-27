<?php

class PostEntity
{
    public function __construct(
        private ?int $postId = null,
        private ?int $groupId = null,
        private ?int $userId = null,
        private ?int $challengeId = null,
        private ?string $postType = null,
        private ?string $title = null,
        private ?string $content = null,
        private ?string $status = null,
        private ?string $linkedUrl = null,
        private ?string $createdAt = null
    ) {
    }

    public function getPostId(): ?int
    {
        return $this->postId;
    }

    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getChallengeId(): ?int
    {
        return $this->challengeId;
    }

    public function getPostType(): ?string
    {
        return $this->postType;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getLinkedUrl(): ?string
    {
        return $this->linkedUrl;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}

class CommentEntity
{
    public function __construct(
        private ?int $commentId = null,
        private ?int $postId = null,
        private ?int $userId = null,
        private ?int $parentCommentId = null,
        private ?string $content = null,
        private ?int $likesCount = null,
        private ?string $status = null,
        private ?string $createdAt = null
    ) {
    }

    public function getCommentId(): ?int
    {
        return $this->commentId;
    }

    public function getPostId(): ?int
    {
        return $this->postId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getParentCommentId(): ?int
    {
        return $this->parentCommentId;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getLikesCount(): ?int
    {
        return $this->likesCount;
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

class SubmissionEntity
{
    public function __construct(
        private ?int $submissionId = null,
        private ?int $groupMemberId = null,
        private ?int $challengeId = null,
        private ?string $projectLink = null,
        private ?string $description = null,
        private ?string $submittedAt = null,
        private ?int $score = null,
        private ?int $submissionRank = null,
        private ?string $status = null
    ) {
    }

    public function getSubmissionId(): ?int
    {
        return $this->submissionId;
    }

    public function getGroupMemberId(): ?int
    {
        return $this->groupMemberId;
    }

    public function getChallengeId(): ?int
    {
        return $this->challengeId;
    }

    public function getProjectLink(): ?string
    {
        return $this->projectLink;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSubmittedAt(): ?string
    {
        return $this->submittedAt;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function getSubmissionRank(): ?int
    {
        return $this->submissionRank;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
