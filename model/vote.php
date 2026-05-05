<?php
declare(strict_types=1);

class Vote
{
    private ?int $id;
    private int $userId;
    private int $ideeId;
    private string $type; // 'like' or 'dislike'
    private string $dateCreation;

    public function __construct(
        int $userId,
        int $ideeId,
        string $type,
        string $dateCreation,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->ideeId = $ideeId;
        $this->type = strtolower($type);
        $this->dateCreation = $dateCreation;
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            (int) ($row['user_id'] ?? 0),
            (int) ($row['idee_id'] ?? 0),
            (string) ($row['type'] ?? 'like'),
            (string) ($row['date_creation'] ?? ''),
            isset($row['id']) ? (int) $row['id'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getIdeeId(): int
    {
        return $this->ideeId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDateCreation(): string
    {
        return $this->dateCreation;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setIdeeId(int $ideeId): void
    {
        $this->ideeId = $ideeId;
    }

    public function setType(string $type): void
    {
        $this->type = strtolower($type);
    }
}
