<?php

class MentorshipRequest
{
    private ?int $requestId = null;
    private int $requesterId;
    private int $mentorId;
    private string $sourceType;
    private ?int $sourceId;
    private string $requestType;
    private string $sessionMode;
    private string $subject;
    private string $details;
    private ?string $preferredDate;
    private ?string $preferredTime;
    private int $durationMinutes;
    private ?float $offerAmount;
    private string $currency;
    private string $status;
    private ?string $responseNote;
    private ?string $scheduledDate;
    private ?string $scheduledTime;
    private ?string $meetingLink;
    private ?string $createdAt;
    private ?string $updatedAt;
    private ?string $respondedAt;

    public function __construct(
        int $requesterId,
        int $mentorId,
        string $sourceType = 'direct',
        ?int $sourceId = null,
        string $requestType = 'mentor_chat',
        string $sessionMode = 'chat',
        string $subject = '',
        string $details = '',
        ?string $preferredDate = null,
        ?string $preferredTime = null,
        int $durationMinutes = 30,
        ?float $offerAmount = null,
        string $currency = 'USD',
        string $status = 'pending',
        ?string $responseNote = null,
        ?string $scheduledDate = null,
        ?string $scheduledTime = null,
        ?string $meetingLink = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $respondedAt = null
    ) {
        $this->requesterId = $requesterId;
        $this->mentorId = $mentorId;
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
        $this->requestType = $requestType;
        $this->sessionMode = $sessionMode;
        $this->subject = $subject;
        $this->details = $details;
        $this->preferredDate = $preferredDate;
        $this->preferredTime = $preferredTime;
        $this->durationMinutes = $durationMinutes;
        $this->offerAmount = $offerAmount;
        $this->currency = $currency;
        $this->status = $status;
        $this->responseNote = $responseNote;
        $this->scheduledDate = $scheduledDate;
        $this->scheduledTime = $scheduledTime;
        $this->meetingLink = $meetingLink;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->respondedAt = $respondedAt;
    }

    public function getRequestId(): ?int { return $this->requestId; }
    public function getRequesterId(): int { return $this->requesterId; }
    public function getMentorId(): int { return $this->mentorId; }
    public function getSourceType(): string { return $this->sourceType; }
    public function getSourceId(): ?int { return $this->sourceId; }
    public function getRequestType(): string { return $this->requestType; }
    public function getSessionMode(): string { return $this->sessionMode; }
    public function getSubject(): string { return $this->subject; }
    public function getDetails(): string { return $this->details; }
    public function getPreferredDate(): ?string { return $this->preferredDate; }
    public function getPreferredTime(): ?string { return $this->preferredTime; }
    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function getOfferAmount(): ?float { return $this->offerAmount; }
    public function getCurrency(): string { return $this->currency; }
    public function getStatus(): string { return $this->status; }
    public function getResponseNote(): ?string { return $this->responseNote; }
    public function getScheduledDate(): ?string { return $this->scheduledDate; }
    public function getScheduledTime(): ?string { return $this->scheduledTime; }
    public function getMeetingLink(): ?string { return $this->meetingLink; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function getRespondedAt(): ?string { return $this->respondedAt; }

    public function setRequestId(int $requestId): void { $this->requestId = $requestId; }
}
?>
