    <?php
class EventForm {
    private ?int $formId = null;
    private int $eventId;
    private string $title;
    private string $description;
    private string $formLink;
    private string $status;    // open | closed | draft

    public function __construct(
        int $eventId,
        string $title,
        string $description,
        string $formLink,
        string $status = 'open'
    ) {
        $this->eventId     = $eventId;
        $this->title       = $title;
        $this->description = $description;
        $this->formLink    = $formLink;
        $this->status      = $status;
    }

    // ── Getters ──────────────────────────────────────
    public function getFormId(): ?int        { return $this->formId; }
    public function getEventId(): int        { return $this->eventId; }
    public function getTitle(): string       { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getFormLink(): string    { return $this->formLink; }
    public function getStatus(): string      { return $this->status; }

    // ── Setters ──────────────────────────────────────
    public function setFormId(int $id): void        { $this->formId = $id; }
    public function setEventId(int $v): void        { $this->eventId = $v; }
    public function setTitle(string $v): void       { $this->title = $v; }
    public function setDescription(string $v): void { $this->description = $v; }
    public function setFormLink(string $v): void    { $this->formLink = $v; }
    public function setStatus(string $v): void      { $this->status = $v; }
}
?>
