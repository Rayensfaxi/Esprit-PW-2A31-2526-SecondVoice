<?php
class Reclamation
{
    private $id_reclamation;
    private $description;
    private $date_creation;
    private $statut; // en_attente, en_cours, resolue, rejetee
    private $id_user; // FK vers Utilisateur

    public function __construct($description = null, $date_creation = null, $statut = null, $id_user = null)
    {
        $this->description = $description;
        $this->date_creation = $date_creation;
        $this->statut = $statut;
        $this->id_user = $id_user;
    }

    // Getters et Setters
    public function getId_reclamation()
    {
        return $this->id_reclamation;
    }

    public function setId_reclamation($id_reclamation)
    {
        $this->id_reclamation = $id_reclamation;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getDate_creation()
    {
        return $this->date_creation;
    }

    public function setDate_creation($date_creation)
    {
        $this->date_creation = $date_creation;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($statut)
    {
        $this->statut = $statut;
    }

    public function getId_user()
    {
        return $this->id_user;
    }

    public function setId_user($id_user)
    {
        $this->id_user = $id_user;
    }
}
?>