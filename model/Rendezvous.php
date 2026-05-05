<?php
class Rendezvous {
    private ?int $id = null;
    private ?int $id_citoyen = null;
    private ?string $service = null;
    private ?string $assistant = null;
    private ?DateTime $date_rdv = null;
    private ?string $heure_rdv = null;
    private ?string $mode = null;
    private ?string $remarques = null;
    private ?string $statut = null;

    public function __construct(
        ?int $id = null,
        ?int $id_citoyen = null,
        ?string $service = null,
        ?string $assistant = null,
        ?DateTime $date_rdv = null,
        ?string $heure_rdv = null,
        ?string $mode = null,
        ?string $remarques = null,
        ?string $statut = 'En attente'
    ) {
        $this->id = $id;
        $this->id_citoyen = $id_citoyen;
        $this->service = $service;
        $this->assistant = $assistant;
        $this->date_rdv = $date_rdv;
        $this->heure_rdv = $heure_rdv;
        $this->mode = $mode;
        $this->remarques = $remarques;
        $this->statut = $statut;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }

    public function getIdCitoyen(): ?int { return $this->id_citoyen; }
    public function setIdCitoyen(?int $id_citoyen): self { $this->id_citoyen = $id_citoyen; return $this; }

    public function getService(): ?string { return $this->service; }
    public function setService(?string $service): self { $this->service = $service; return $this; }

    public function getAssistant(): ?string { return $this->assistant; }
    public function setAssistant(?string $assistant): self { $this->assistant = $assistant; return $this; }

    public function getDateRdv(): ?DateTime { return $this->date_rdv; }
    public function setDateRdv(?DateTime $date_rdv): self { $this->date_rdv = $date_rdv; return $this; }

    public function getHeureRdv(): ?string { return $this->heure_rdv; }
    public function setHeureRdv(?string $heure_rdv): self { $this->heure_rdv = $heure_rdv; return $this; }

    public function getMode(): ?string { return $this->mode; }
    public function setMode(?string $mode): self { $this->mode = $mode; return $this; }

    public function getRemarques(): ?string { return $this->remarques; }
    public function setRemarques(?string $remarques): self { $this->remarques = $remarques; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }
}
?>
