<?php
require_once __DIR__ . '/../config.php';

class GoalModel {
    
    // Ajouter un goal
    public function addGoal($goal) {
        $db = config::getConnexion();
        $sql = "INSERT INTO goals (title, description, status, priority, startDate, endDate, citoyen_id, assistant_id) 
                VALUES (:title, :description, :status, :priority, :startDate, :endDate, :citoyen_id, :assistant_id)";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'title' => $goal['title'],
                'description' => $goal['description'],
                'status' => $goal['status'],
                'priority' => $goal['priority'],
                'startDate' => $goal['startDate'],
                'endDate' => $goal['endDate'],
                'citoyen_id' => $goal['citoyen_id'],
                'assistant_id' => $goal['assistant_id']
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Récupérer tous les goals
    public function getGoals() {
        $db = config::getConnexion();
        $sql = "SELECT * FROM goals ORDER BY startDate DESC";
        try {
            $req = $db->prepare($sql);
            $req->execute();
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Récupérer un goal spécifique
    public function getGoalById($id) {
        $db = config::getConnexion();
        $sql = "SELECT * FROM goals WHERE id = :id";
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return $req->fetch();
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Mettre à jour un goal
    public function updateGoal($id, $goal) {
        $db = config::getConnexion();
        $sql = "UPDATE goals SET 
                title = :title, description = :description, status = :status, 
                priority = :priority, startDate = :startDate, endDate = :endDate, 
                citoyen_id = :citoyen_id, assistant_id = :assistant_id 
                WHERE id = :id";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id' => $id,
                'title' => $goal['title'],
                'description' => $goal['description'],
                'status' => $goal['status'],
                'priority' => $goal['priority'],
                'startDate' => $goal['startDate'],
                'endDate' => $goal['endDate'],
                'citoyen_id' => $goal['citoyen_id'],
                'assistant_id' => $goal['assistant_id']
            ]);
            return true;
        } catch (Exception $e) {
            die('Erreur: ' . $e->getMessage());
        }
    }

    // Supprimer un goal
    public function deleteGoal($id) {
        $db = config::getConnexion();
        $sql = "DELETE FROM goals WHERE id = :id";
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