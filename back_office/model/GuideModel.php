<?php
require_once __DIR__ . '/../config.php';

class GuideModel {
    
    // Ajouter un guide
    public function addGuide($guide) {
        $db = config::getConnexion();
        $sql = "INSERT INTO guides (title, content, type, goal_id) 
                VALUES (:title, :content, :type, :goal_id)";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'title' => $guide['title'],
                'content' => $guide['content'],
                'type' => $guide['type'],
                'goal_id' => $guide['goal_id']
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Récupérer tous les guides avec info du goal
    public function getGuides() {
        $db = config::getConnexion();
        $sql = "SELECT g.*, o.title as goal_title FROM guides g 
                LEFT JOIN goals o ON g.goal_id = o.id ORDER BY g.id DESC";
        try {
            $req = $db->prepare($sql);
            $req->execute();
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Récupérer les guides liés à un goal spécifique
    public function getGuidesByGoalId($goal_id) {
        $db = config::getConnexion();
        $sql = "SELECT * FROM guides WHERE goal_id = :goal_id";
        try {
            $req = $db->prepare($sql);
            $req->execute(['goal_id' => $goal_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Récupérer un guide spécifique
    public function getGuideById($id) {
        $db = config::getConnexion();
        $sql = "SELECT * FROM guides WHERE id = :id";
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return $req->fetch();
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Mettre à jour un guide
    public function updateGuide($id, $guide) {
        $db = config::getConnexion();
        $sql = "UPDATE guides SET 
                title = :title, content = :content, type = :type, goal_id = :goal_id 
                WHERE id = :id";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id' => $id,
                'title' => $guide['title'],
                'content' => $guide['content'],
                'type' => $guide['type'],
                'goal_id' => $guide['goal_id']
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Supprimer un guide
    public function deleteGuide($id) {
        $db = config::getConnexion();
        $sql = "DELETE FROM guides WHERE id = :id";
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }
}
?>