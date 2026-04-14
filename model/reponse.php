<?php
class Reponse
{
    private $id_reponse;
    private $contenu;
    private $date_reponse;
    private $id_reclamation; // FK vers Reclamation
    private $id_user; // FK vers Utilisateur

    public function __construct($contenu = null, $date_reponse = null, $id_reclamation = null, $id_user = null)
    {
        $this->contenu = $contenu;
        $this->date_reponse = $date_reponse;
        $this->id_reclamation = $id_reclamation;
        $this->id_user = $id_user;
    }

    // Getters et Setters
    public function getId_reponse()
    {
        return $this->id_reponse;
    }

    public function setId_reponse($id_reponse)
    {
        $this->id_reponse = $id_reponse;
    }

    public function getContenu()
    {
        return $this->contenu;
    }

    public function setContenu($contenu)
    {
        $this->contenu = $contenu;
    }

    public function getDate_reponse()
    {
        return $this->date_reponse;
    }

    public function setDate_reponse($date_reponse)
    {
        $this->date_reponse = $date_reponse;
    }

    public function getId_reclamation()
    {
        return $this->id_reclamation;
    }

    public function setId_reclamation($id_reclamation)
    {
        $this->id_reclamation = $id_reclamation;
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