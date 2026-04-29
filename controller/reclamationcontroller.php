<?php
include_once(__DIR__ . '/../config.php');
include(__DIR__ . '/../model/reclamation.php');

class ReclamationController 
{
    public function addReclamation(Reclamation $reclamation)
    {
        $sql = "INSERT INTO reclamation (description, date_creation, statut, id_user) 
                VALUES (:description, :date_creation, :statut, :id_user)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'description' => $reclamation->getDescription(),
                'date_creation' => $reclamation->getDate_creation(),
                'statut' => $reclamation->getStatut(),
                'id_user' => $reclamation->getId_user(),
            ]);
            //return $db->lastInsertId();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return false;
        }
    }

    public function listReclamations()
    {
        $sql = "SELECT * FROM reclamation";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            $reclamationsData = $query->fetchAll();

            $reclamations = [];
            foreach ($reclamationsData as $row) { 
                $reclamation = new Reclamation(
                    $row['description'], 
                    $row['date_creation'], 
                    $row['statut'], 
                    $row['id_user']
                );
                $reclamation->setId_reclamation($row['id_reclamation']);
                $reclamations[] = $reclamation;
            }
            return $reclamations;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getReclamationById($id)
    {
        $sql = "SELECT * FROM reclamation WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            
            if ($row) {
                $reclamation = new Reclamation(
                    $row['description'], 
                    $row['date_creation'], 
                    $row['statut'], 
                    $row['id_user']
                );
                $reclamation->setId_reclamation($row['id_reclamation']);
                return $reclamation;
            }
            return null;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }

    public function deleteReclamation($id)
    {
        $sql = "DELETE FROM reclamation WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateReclamation($id, $description, $statut, $id_user)
    {
        $sql = "UPDATE reclamation 
                SET description = :description,
                    statut = :statut,
                    id_user = :id_user
                WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $id,
                'description' => $description,
                'statut' => $statut,
                'id_user' => $id_user
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function changeStatut($id, $nouveauStatut)
    {
        $sql = "UPDATE reclamation SET statut = :statut WHERE id_reclamation = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $id,
                'statut' => $nouveauStatut
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    public function getReclamationsByUserAndStatut($id_user, $statut)
{
    $sql = "SELECT * FROM reclamation WHERE id_user = :id_user AND statut = :statut ORDER BY date_creation DESC";
    $db = Config::getConnexion();
    try {
        $query = $db->prepare($sql);
        $query->execute([
            'id_user' => $id_user,
            'statut' => $statut
        ]);
        $reclamationsData = $query->fetchAll();

        $reclamations = [];
        foreach ($reclamationsData as $row) { 
            $reclamation = new Reclamation(
                $row['description'], 
                $row['date_creation'], 
                $row['statut'], 
                $row['id_user']
            );
            $reclamation->setId_reclamation($row['id_reclamation']);
            $reclamations[] = $reclamation;
        }
        return $reclamations;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
        return [];
    }
}
}
?>