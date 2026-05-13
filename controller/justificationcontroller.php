<?php
include_once(__DIR__ . '/../config.php');
include(__DIR__ . '/../model/justification.php');

class JustificationController 
{
    public function addJustification(Justification $justification)
    {
        $sql = "INSERT INTO justification (contenu, date_justification, id_reclamation) 
                VALUES (:contenu, :date_justification, :id_reclamation)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'contenu' => $justification->getContenu(),
                'date_justification' => $justification->getDate_justification(),
                'id_reclamation' => $justification->getId_reclamation(),
            ]);
            return $db->lastInsertId();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return false;
        }
    }

    public function listJustifications()
    {
        $sql = "SELECT * FROM justification";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            $justificationsData = $query->fetchAll();

            $justifications = [];
            foreach ($justificationsData as $row) { 
                $justification = new Justification(
                    $row['contenu'], 
                    $row['date_justification'], 
                    $row['id_reclamation']
                );
                $justification->setId_justification($row['id_justification']);
                $justifications[] = $justification;
            }
            return $justifications;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getJustificationsByReclamation($id_reclamation)
    {
        $sql = "SELECT * FROM justification WHERE id_reclamation = :id_reclamation ORDER BY date_justification DESC";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id_reclamation' => $id_reclamation]);
            $justificationsData = $query->fetchAll();

            $justifications = [];
            foreach ($justificationsData as $row) { 
                $justification = new Justification(
                    $row['contenu'], 
                    $row['date_justification'], 
                    $row['id_reclamation']
                );
                $justification->setId_justification($row['id_justification']);
                $justifications[] = $justification;
            }
            return $justifications;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getJustificationById($id)
    {
        $sql = "SELECT * FROM justification WHERE id_justification = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            
            if ($row) {
                $justification = new Justification(
                    $row['contenu'], 
                    $row['date_justification'], 
                    $row['id_reclamation']
                );
                $justification->setId_justification($row['id_justification']);
                return $justification;
            }
            return null;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }

    public function deleteJustification($id)
    {
        $sql = "DELETE FROM justification WHERE id_justification = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateJustification($id, $contenu)
    {
        $sql = "UPDATE justification 
                SET contenu = :contenu
                WHERE id_justification = :id";
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