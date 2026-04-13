<?php
class Utilisateur {

    private $id;
    private $nom;
    private $prenom;
    private $email;
    private $mot_de_passe;
    private $telephone;
    private $role;

    // constructeur
    public function __construct($nom, $prenom, $email, $mot_de_passe, $telephone, $role) {
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->mot_de_passe = $mot_de_passe;
        $this->telephone = $telephone;
        $this->role = $role;
    }

    // getters
    public function getNom() {
        return $this->nom;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getRole() {
        return $this->role;
    }

    // setters
    public function setNom($nom) {
        $this->nom = $nom;
    }

}
?>
