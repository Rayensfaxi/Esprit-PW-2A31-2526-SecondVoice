<?php
class Utilisateur
{
    private $id;
    private $nom;
    private $prenom;
    private $email;
    private $mot_de_passe;
    private $telephone;
    private $role;
    private $statut_compte;
    private $date_creation;

    // Constructeur
    public function __construct(
        $nom = null,
        $prenom = null,
        $email = null,
        $mot_de_passe = null,
        $telephone = null,
        $role = 'user',
        $statut_compte = 'actif',
        $date_creation = null
    ) {
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
        $this->mot_de_passe = $mot_de_passe;
        $this->telephone = $telephone;
        $this->role = $role;
        $this->statut_compte = $statut_compte;
        $this->date_creation = $date_creation;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getNom() { return $this->nom; }
    public function getPrenom() { return $this->prenom; }
    public function getEmail() { return $this->email; }
    public function getMot_de_passe() { return $this->mot_de_passe; }
    public function getTelephone() { return $this->telephone; }
    public function getRole() { return $this->role; }
    public function getStatut_compte() { return $this->statut_compte; }
    public function getDate_creation() { return $this->date_creation; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setNom($nom) { $this->nom = $nom; }
    public function setPrenom($prenom) { $this->prenom = $prenom; }
    public function setEmail($email) { $this->email = $email; }
    public function setMot_de_passe($mot_de_passe) { $this->mot_de_passe = $mot_de_passe; }
    public function setTelephone($telephone) { $this->telephone = $telephone; }
    public function setRole($role) { $this->role = $role; }
    public function setStatut_compte($statut_compte) { $this->statut_compte = $statut_compte; }
    public function setDate_creation($date_creation) { $this->date_creation = $date_creation; }
}
?>