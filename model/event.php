<?php
declare(strict_types=1);

class Event
{
    private ?int $id;
    private string $name;
    private string $description;
    private ?string $startDate;
    private ?string $endDate;
    private ?string $deadline;
    private ?string $location;
    private int $max;
    private int $current;
    private string $status;
    private ?int $createdBy;

    public function __construct(
        string $name,
        string $description,
        ?string $startDate,
        ?string $endDate,
        ?string $deadline,
        ?string $location,
        int $max = 0,
        int $current = 0,
        string $status = 'en cours',
        ?int $id = null,
        ?int $createdBy = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->deadline = $deadline;
        $this->location = $location;
        $this->max = $max;
        $this->current = $current;
        $this->status = $status;
        $this->createdBy = $createdBy;
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            (string) ($row['name'] ?? ''),
            (string) ($row['description'] ?? ''),
            isset($row['start_date']) ? (string) $row['start_date'] : null,
            isset($row['end_date']) ? (string) $row['end_date'] : null,
            isset($row['deadline']) ? (string) $row['deadline'] : null,
            isset($row['location']) ? (string) $row['location'] : null,
            isset($row['max']) ? (int) $row['max'] : 0,
            isset($row['current']) ? (int) $row['current'] : 0,
            (string) ($row['status'] ?? 'en cours'),
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['created_by']) ? (int) $row['created_by'] : null
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getStartDate(): ?string { return $this->startDate; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function getDeadline(): ?string { return $this->deadline; }
    public function getLocation(): ?string { return $this->location; }
    public function getMax(): int { return $this->max; }
    public function getCurrent(): int { return $this->current; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
}
