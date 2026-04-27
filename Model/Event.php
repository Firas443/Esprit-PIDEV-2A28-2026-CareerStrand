<?php
class Event {
    private ?int $eventId = null;
    private ?int $managerId = null;
    private string $name;
    private string $description;
    private string $type;
    private string $location;
    private int $capacity;
    private string $date;
    private string $status;
    private string $createdAt = '';  // automatique, non modifiable par l'utilisateur
    private string $tags;
    private string $organiser;
    private string $time;
    private string $eventMode = 'Online';   // 'Online' or 'In-person'
    private ?int $sponsorId = null;
    private int $duration   = 0;

    public function __construct(
        string $name,
        string $description,
        string $type,
        string $location,
        int    $capacity,
        string $date,
        string $status,
        ?int   $managerId = null,
        string $tags      = '',
        string $organiser = '',
        string $time      = '',
        string $eventMode = 'Online',
        ?int   $sponsorId = null,
        int    $duration  = 0
    ) {
        $this->name        = $name;
        $this->description = $description;
        $this->type        = $type;
        $this->location    = $location;
        $this->capacity    = $capacity;
        $this->date        = $date;
        $this->status      = $status;
        $this->createdAt   = date('Y-m-d'); // toujours la date du jour
        $this->managerId   = $managerId;
        $this->tags        = $tags;
        $this->organiser   = $organiser;
        $this->time        = $time;
        $this->eventMode   = $eventMode;
        $this->sponsorId   = $sponsorId;
        $this->duration    = $duration;
    }

    // ── Getters ──────────────────────────────────────
    public function getEventId(): ?int       { return $this->eventId; }
    public function getManagerId(): ?int     { return $this->managerId; }
    public function getName(): string        { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getType(): string        { return $this->type; }
    public function getLocation(): string    { return $this->location; }
    public function getCapacity(): int       { return $this->capacity; }
    public function getDate(): string        { return $this->date; }
    public function getStatus(): string      { return $this->status; }
    public function getCreatedAt(): string   { return $this->createdAt; }
    public function getTags(): string        { return $this->tags; }
    public function getOrganiser(): string   { return $this->organiser; }
    public function getTime(): string        { return $this->time; }
    public function getEventMode(): string { return $this->eventMode; }
    public function getSponsorId(): ?int      { return $this->sponsorId; }
    public function getDuration(): int        { return $this->duration; }

    // ── Setters ──────────────────────────────────────
    public function setEventId(int $id): void        { $this->eventId = $id; }
    public function setManagerId(?int $id): void     { $this->managerId = $id; }
    public function setName(string $v): void         { $this->name = $v; }
    public function setDescription(string $v): void  { $this->description = $v; }
    public function setType(string $v): void         { $this->type = $v; }
    public function setLocation(string $v): void     { $this->location = $v; }
    public function setCapacity(int $v): void        { $this->capacity = $v; }
    public function setDate(string $v): void         { $this->date = $v; }
    public function setStatus(string $v): void       { $this->status = $v; }
    public function setCreatedAt(string $v): void    { $this->createdAt = $v; }
    public function setTags(string $v): void         { $this->tags = $v; }
    public function setOrganiser(string $v): void    { $this->organiser = $v; }
    public function setTime(string $v): void         { $this->time = $v; }
    public function setEventMode(string $v): void { $this->eventMode = $v; }
    public function setSponsorId(?int $v): void        { $this->sponsorId = $v; }
    public function setDuration(int $v): void          { $this->duration = $v; }
}
?>
