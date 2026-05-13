<?php
class Justification
{
    private $id_justification;
    private $contenu;
    private $date_justification;
    private $id_reclamation; // FK vers Reclamation

    public function __construct($contenu = null, $date_justification = null, $id_reclamation = null)
    {
        $this->contenu = $contenu;
        $this->date_justification = $date_justification;
        $this->id_reclamation = $id_reclamation;
    }

    // Getters et Setters
    public function getId_justification()
    {
        return $this->id_justification;
    }

    public function setId_justification($id_justification)
    {
        $this->id_justification = $id_justification;
    }

    public function getContenu()
    {
        return $this->contenu;
    }

    public function setContenu($contenu)
    {
        $this->contenu = $contenu;
    }

    public function getDate_justification()
    {
        return $this->date_justification;
    }

    public function setDate_justification($date_justification)
    {
        $this->date_justification = $date_justification;
    }

    public function getId_reclamation()
    {
        return $this->id_reclamation;
    }

    public function setId_reclamation($id_reclamation)
    {
        $this->id_reclamation = $id_reclamation;
    }
}
?>