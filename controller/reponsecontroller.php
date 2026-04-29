<?php
include_once(__DIR__ . '/../config.php');
include(__DIR__ . '/../model/reponse.php');

class ReponseController 
{
    public function addReponse(Reponse $reponse)
    {
        $sql = "INSERT INTO reponse (contenu, date_reponse, id_reclamation, id_user) 
                VALUES (:contenu, :date_reponse, :id_reclamation, :id_user)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'contenu' => $reponse->getContenu(),
                'date_reponse' => $reponse->getDate_reponse(),
                'id_reclamation' => $reponse->getId_reclamation(),
                'id_user' => $reponse->getId_user(),
            ]);
            return $db->lastInsertId();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return false;
        }
    }

    public function listReponses()
    {
        $sql = "SELECT * FROM reponse";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            $reponsesData = $query->fetchAll();

            $reponses = [];
            foreach ($reponsesData as $row) { 
                $reponse = new Reponse(
                    $row['contenu'], 
                    $row['date_reponse'], 
                    $row['id_reclamation'], 
                    $row['id_user']
                );
                $reponse->setId_reponse($row['id_reponse']);
                $reponses[] = $reponse;
            }
            return $reponses;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getReponsesByReclamation($id_reclamation)
    {
        $sql = "SELECT * FROM reponse WHERE id_reclamation = :id_reclamation ORDER BY date_reponse ASC";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id_reclamation' => $id_reclamation]);
            $reponsesData = $query->fetchAll();

            $reponses = [];
            foreach ($reponsesData as $row) { 
                $reponse = new Reponse(
                    $row['contenu'], 
                    $row['date_reponse'], 
                    $row['id_reclamation'], 
                    $row['id_user']
                );
                $reponse->setId_reponse($row['id_reponse']);
                $reponses[] = $reponse;
            }
            return $reponses;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getReponseById($id)
    {
        $sql = "SELECT * FROM reponse WHERE id_reponse = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            
            if ($row) {
                $reponse = new Reponse(
                    $row['contenu'], 
                    $row['date_reponse'], 
                    $row['id_reclamation'], 
                    $row['id_user']
                );
                $reponse->setId_reponse($row['id_reponse']);
                return $reponse;
            }
            return null;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }

    public function deleteReponse($id)
    {
        $sql = "DELETE FROM reponse WHERE id_reponse = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateReponse($id, $contenu)
    {
        $sql = "UPDATE reponse 
                SET contenu = :contenu
                WHERE id_reponse = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $id,
                'contenu' => $contenu
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>