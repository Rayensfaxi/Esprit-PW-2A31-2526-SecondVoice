<?php
declare(strict_types=1);

class Utilisateur
{
    private ?int $id;
    private string $nom;
    private string $prenom;
    private string $email;
    private string $mot_de_passe;
    private string $telephone;
    private string $role;

    public function __construct(
        string $nom,
        string $prenom,
        string $email,
        string $mot_de_passe,
        string $telephone,
        string $role,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->mot_de_passe = $mot_de_passe;
        $this->telephone = $telephone;
        $this->role = $role;
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            (string) ($row['nom'] ?? ''),
            (string) ($row['prenom'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['mot_de_passe'] ?? ''),
            (string) ($row['telephone'] ?? ''),
            (string) ($row['role'] ?? 'client'),
            isset($row['id']) ? (int) $row['id'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getMotDePasse(): string
    {
        return $this->mot_de_passe;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    public function setPrenom(string $prenom): void
    {
        $this->prenom = $prenom;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setMotDePasse(string $motDePasse): void
    {
        $this->mot_de_passe = $motDePasse;
    }

    public function setTelephone(string $telephone): void
    {
        $this->telephone = $telephone;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
?>