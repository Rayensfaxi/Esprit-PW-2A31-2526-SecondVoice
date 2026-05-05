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

    public function __construct(
        string $titre,
        string $description,
        string $categorie,
        string $dateCreation,
        string $statut,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->titre = $titre;
        $this->description = $description;
        $this->categorie = $categorie;
        $this->dateCreation = $dateCreation;
        $this->statut = $statut;
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            (string) ($row['titre'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['categorie'] ?? ''),
            (string) ($row['dateCreation'] ?? ''),
            (string) ($row['statut'] ?? 'en attente'),
            isset($row['id']) ? (int) $row['id'] : null
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
}