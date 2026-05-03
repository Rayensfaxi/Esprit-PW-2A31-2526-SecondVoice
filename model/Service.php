<?php
class Service {
    private ?int $id = null;
    private ?string $nom = null;
    private ?string $description = null;

    public function __construct(
        ?int $id = null,
        ?string $nom = null,
        ?string $description = null
    ) {
        $this->id = $id;
        $this->nom = $nom;
        $this->description = $description;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): self { $this->nom = $nom; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
}
?>
