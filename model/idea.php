<?php
declare(strict_types=1);

class Idea
{
    private ?int $id;
    private int $brainstormingId;
    private int $userId;
    private string $contenu;
    private string $dateCreation;
    private string $statut;
    private int $likesCount;
    private int $dislikesCount;
    private bool $isWinner;

    public function __construct(
        int $brainstormingId,
        int $userId,
        string $contenu,
        string $dateCreation,
        string $statut,
        ?int $id = null,
        int $likesCount = 0,
        int $dislikesCount = 0,
        bool $isWinner = false
    ) {
        $this->id = $id;
        $this->brainstormingId = $brainstormingId;
        $this->userId = $userId;
        $this->contenu = $contenu;
        $this->dateCreation = $dateCreation;
        $this->statut = $statut;
        $this->likesCount = $likesCount;
        $this->dislikesCount = $dislikesCount;
        $this->isWinner = $isWinner;
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            (int) ($row['brainstorming_id'] ?? 0),
            (int) ($row['user_id'] ?? 0),
            (string) ($row['contenu'] ?? ''),
            (string) ($row['date_creation'] ?? ''),
            (string) ($row['statut'] ?? 'en attente'),
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['likes_count'] ?? 0),
            (int) ($row['dislikes_count'] ?? 0),
            (bool) ($row['is_winner'] ?? false)
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrainstormingId(): int
    {
        return $this->brainstormingId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function getDateCreation(): string
    {
        return $this->dateCreation;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setBrainstormingId(int $brainstormingId): void
    {
        $this->brainstormingId = $brainstormingId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setContenu(string $contenu): void
    {
        $this->contenu = $contenu;
    }

    public function setDateCreation(string $dateCreation): void
    {
        $this->dateCreation = $dateCreation;
    }

    public function setStatut(string $statut): void
    {
        $this->statut = $statut;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function getDislikesCount(): int
    {
        return $this->dislikesCount;
    }

    public function getIsWinner(): bool
    {
        return $this->isWinner;
    }

    public function setLikesCount(int $likesCount): void
    {
        $this->likesCount = $likesCount;
    }

    public function setDislikesCount(int $dislikesCount): void
    {
        $this->dislikesCount = $dislikesCount;
    }

    public function setIsWinner(bool $isWinner): void
    {
        $this->isWinner = $isWinner;
    }
}
