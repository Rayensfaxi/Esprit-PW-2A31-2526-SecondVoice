<?php
include_once(__DIR__ . '/../config.php');
include(__DIR__ . '/../model/utilisateur.php');

class UtilisateurController 
{
    // Récupérer un utilisateur par son email (pour le login)
    public function getUserByEmail($email)
    {
        $sql = "SELECT * FROM utilisateur WHERE email = :email";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['email' => $email]);
            $row = $query->fetch();
            
            if ($row) {
                return $this->hydrate($row);
            }
            return null;
        } catch (Exception $e) {
            error_log("Erreur getUserByEmail: " . $e->getMessage());
            return null;
        }
    }

    // Récupérer un utilisateur par son ID
    public function getUserById($id)
    {
        $sql = "SELECT * FROM utilisateur WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            
            if ($row) {
                return $this->hydrate($row);
            }
            return null;
        } catch (Exception $e) {
            error_log("Erreur getUserById: " . $e->getMessage());
            return null;
        }
    }

    // Ajouter un nouvel utilisateur (inscription)
    public function addUser(Utilisateur $user)
    {
        $sql = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role, statut_compte, date_creation) 
                VALUES (:nom, :prenom, :email, :mot_de_passe, :telephone, :role, :statut_compte, :date_creation)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $success = $query->execute([
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
                'mot_de_passe' => /*password_hash(*/$user->getMot_de_passe()/*, PASSWORD_DEFAULT)*/,
                'telephone' => $user->getTelephone(),
                'role' => $user->getRole() ?? 'user',
                'statut_compte' => $user->getStatut_compte() ?? 'actif',
                'date_creation' => $user->getDate_creation() ?? date('Y-m-d'),
            ]);
            
            if ($success && $query->rowCount() > 0) {
                return $db->lastInsertId();
            }
            return false;
        } catch (Exception $e) {
            error_log("Erreur addUser: " . $e->getMessage());
            return false;
        }
    }

    // Lister tous les utilisateurs
    public function listUsers()
    {
        $sql = "SELECT * FROM utilisateur";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            $rows = $query->fetchAll();
            
            $users = [];
            foreach ($rows as $row) {
                $users[] = $this->hydrate($row);
            }
            return $users;
        } catch (Exception $e) {
            error_log("Erreur listUsers: " . $e->getMessage());
            return [];
        }
    }

    // Supprimer un utilisateur
    public function deleteUser($id)
    {
        $sql = "DELETE FROM utilisateur WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            return $query->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erreur deleteUser: " . $e->getMessage());
            return false;
        }
    }

    // Mettre à jour un utilisateur
    public function updateUser(Utilisateur $user)
    {
        $sql = "UPDATE utilisateur 
                SET nom = :nom, 
                    prenom = :prenom, 
                    email = :email, 
                    telephone = :telephone, 
                    role = :role, 
                    statut_compte = :statut_compte
                WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
                'telephone' => $user->getTelephone(),
                'role' => $user->getRole(),
                'statut_compte' => $user->getStatut_compte(),
            ]);
            return $query->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erreur updateUser: " . $e->getMessage());
            return false;
        }
    }

    // Méthode privée pour hydrater un objet Utilisateur depuis un tableau
    private function hydrate($row)
    {
        $user = new Utilisateur();
        $user->setId($row['id']);
        $user->setNom($row['nom']);
        $user->setPrenom($row['prenom']);
        $user->setEmail($row['email']);
        $user->setMot_de_passe($row['mot_de_passe']);
        $user->setTelephone($row['telephone']);
        $user->setRole($row['role']);
        $user->setStatut_compte($row['statut_compte']);
        $user->setDate_creation($row['date_creation']);
        return $user;
    }
}
?>