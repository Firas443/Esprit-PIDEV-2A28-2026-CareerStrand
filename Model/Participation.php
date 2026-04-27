<?php
class Participation {
    private ?int $participationId = null;
    private int $userId;
    private int $eventId;
    private string $registrationDate;
    private string $attendanceStatus;  // Confirmed | Pending | Cancelled
    private string $status;            // active | inactive

    public function __construct(
        int $userId,
        int $eventId,
        string $registrationDate,
        string $attendanceStatus = 'Pending',
        string $status = 'active'
    ) {
        $this->userId           = $userId;
        $this->eventId          = $eventId;
        $this->registrationDate = $registrationDate;
        $this->attendanceStatus = $attendanceStatus;
        $this->status           = $status;
    }

    // ── Getters ──────────────────────────────────────
    public function getParticipationId(): ?int   { return $this->participationId; }
    public function getUserId(): int             { return $this->userId; }
    public function getEventId(): int            { return $this->eventId; }
    public function getRegistrationDate(): string { return $this->registrationDate; }
    public function getAttendanceStatus(): string { return $this->attendanceStatus; }
    public function getStatus(): string          { return $this->status; }

    // ── Setters ──────────────────────────────────────
    public function setParticipationId(int $id): void       { $this->participationId = $id; }
    public function setUserId(int $v): void                 { $this->userId = $v; }
    public function setEventId(int $v): void                { $this->eventId = $v; }
    public function setRegistrationDate(string $v): void    { $this->registrationDate = $v; }
    public function setAttendanceStatus(string $v): void    { $this->attendanceStatus = $v; }
    public function setStatus(string $v): void              { $this->status = $v; }
}
?>
