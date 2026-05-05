<?php
declare(strict_types=1);

class Brainstorming
{
    private ?int $id;
    private string $titre;
    private string $description;
    private string $categorie;
    private string $dateCreation;
    private string $statut;
    private ?string $voteStart;
    private ?string $voteEnd;
    private string $voteStatus;

    public function __construct(
        string $titre,
        string $description,
        string $categorie,
        string $dateCreation,
        string $statut,
        ?int $id = null,
        ?string $voteStart = null,
        ?string $voteEnd = null,
        string $voteStatus = 'closed'
    ) {
        $this->id = $id;
        $this->titre = $titre;
        $this->description = $description;
        $this->categorie = $categorie;
        $this->dateCreation = $dateCreation;
        $this->statut = $statut;
        $this->voteStart = $voteStart;
        $this->voteEnd = $voteEnd;
        $this->voteStatus = $voteStatus;
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            (string) ($row['titre'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['categorie'] ?? ''),
            (string) ($row['dateCreation'] ?? ''),
            (string) ($row['statut'] ?? 'en attente'),
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['vote_start']) ? (string) $row['vote_start'] : null,
            isset($row['vote_end']) ? (string) $row['vote_end'] : null,
            (string) ($row['vote_status'] ?? 'closed')
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
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

    public function setTitre(string $titre): void
    {
        $this->titre = $titre;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function setCategorie(string $categorie): void
    {
        $this->categorie = $categorie;
    }

    public function setDateCreation(string $dateCreation): void
    {
        $this->dateCreation = $dateCreation;
    }

    public function setStatut(string $statut): void
    {
        $this->statut = $statut;
    }

    public function getVoteStart(): ?string
    {
        return $this->voteStart;
    }

    public function getVoteEnd(): ?string
    {
        return $this->voteEnd;
    }

    public function getVoteStatus(): string
    {
        return $this->voteStatus;
    }

    public function setVoteStart(?string $voteStart): void
    {
        $this->voteStart = $voteStart;
    }

    public function setVoteEnd(?string $voteEnd): void
    {
        $this->voteEnd = $voteEnd;
    }

    public function setVoteStatus(string $voteStatus): void
    {
        $this->voteStatus = $voteStatus;
    }

    public function isVoteOpen(): bool
    {
        if ($this->voteStatus !== 'open' || !$this->voteStart || !$this->voteEnd) {
            return false;
        }

        $now = new DateTime();
        $start = new DateTime($this->voteStart);
        $end = new DateTime($this->voteEnd);

        return $now >= $start && $now <= $end;
    }
}