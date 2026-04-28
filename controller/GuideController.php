<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/guide.php';

class GuideController
{
    private function isGoalManagedByAssistant(int $goalId, int $assistantId): bool
    {
        $db = Config::getConnexion();
        $sql = "SELECT COUNT(*) FROM goals WHERE id = :goal_id AND selected_assistant_id = :assistant_id AND assistant_validation_status = 'accepte'";
        $req = $db->prepare($sql);
        $req->execute([
            'goal_id' => $goalId,
            'assistant_id' => $assistantId,
        ]);
        return (int) $req->fetchColumn() > 0;
    }

    // === CREATE ===
    // Assistant only, must verify if Goal is accepted and en_cours
    public function createGuide(Guide $guide)
    {
        $db = Config::getConnexion();
        
        // Verificaion that Goal is accepted by assistant and en_cours happens ideally in the Route or before calling this,
        // but here is a simple verification assuming it's valid.
        $sql = "INSERT INTO guides (goal_id, title, content) VALUES (:goal_id, :title, :content)";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'goal_id' => $guide->getGoalId(),
                'title' => $guide->getTitle(),
                'content' => $guide->getContent()
            ]);
            return true;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === READ / LIST BY GOAL ===
    public function getGuidesByGoal($goal_id)
    {
        $sql = "SELECT * FROM guides WHERE goal_id = :goal_id ORDER BY created_at ASC";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['goal_id' => $goal_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === READ SINGLE GUIDE ===
    public function getGuideById($id)
    {
        $sql = "SELECT * FROM guides WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return $req->fetch();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === UPDATE ===
    public function updateGuide(Guide $guide, $id)
    {
        $sql = "UPDATE guides SET title = :title, content = :content WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'title' => $guide->getTitle(),
                'content' => $guide->getContent(),
                'id' => $id
            ]);
            return true;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === DELETE ===
    public function deleteGuide($id)
    {
        $sql = "DELETE FROM guides WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    public function createGuideByAssistant(Guide $guide, int $assistantId): bool
    {
        if (!$this->isGoalManagedByAssistant($guide->getGoalId(), $assistantId)) {
            return false;
        }
        return $this->createGuide($guide);
    }

    public function getGuideByIdForAssistant(int $guideId, int $assistantId)
    {
        $db = Config::getConnexion();
        $sql = "SELECT g.*, go.user_id FROM guides g JOIN goals go ON g.goal_id = go.id WHERE g.id = :guide_id AND go.selected_assistant_id = :assistant_id";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'guide_id' => $guideId,
                'assistant_id' => $assistantId,
            ]);
            return $req->fetch();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    public function updateGuideByAssistant(Guide $guide, int $guideId, int $assistantId): bool
    {
        $ownedGuide = $this->getGuideByIdForAssistant($guideId, $assistantId);
        if (!$ownedGuide) {
            return false;
        }
        if (!$this->isGoalManagedByAssistant($guide->getGoalId(), $assistantId)) {
            return false;
        }
        return $this->updateGuide($guide, $guideId);
    }

    public function deleteGuideByAssistant(int $guideId, int $assistantId): bool
    {
        $ownedGuide = $this->getGuideByIdForAssistant($guideId, $assistantId);
        if (!$ownedGuide) {
            return false;
        }

        $db = Config::getConnexion();
        $sql = "DELETE g FROM guides g JOIN goals go ON g.goal_id = go.id WHERE g.id = :guide_id AND go.selected_assistant_id = :assistant_id";
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'guide_id' => $guideId,
                'assistant_id' => $assistantId,
            ]);
            return $req->rowCount() > 0;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    public function getAllGuides() {
        $db = Config::getConnexion();
        $sql = "SELECT g.*, go.description as goal_desc, u.nom FROM guides g JOIN goals go ON g.goal_id = go.id JOIN utilisateurs u ON go.user_id = u.id ORDER BY g.created_at DESC";
        try {
            $req = $db->prepare($sql);
            $req->execute();
            return $req->fetchAll();
        } catch (Exception $e) {
            die("Error:" . $e->getMessage());
        }
    }

    public function getGuidesByAssistant($assistant_id) {
        $db = Config::getConnexion();
        $sql = "SELECT g.*, go.description as goal_desc, u.nom FROM guides g JOIN goals go ON g.goal_id = go.id JOIN utilisateurs u ON go.user_id = u.id WHERE go.selected_assistant_id = :aid AND go.admin_validation_status = 'valide' AND go.assistant_validation_status = 'accepte' AND go.status IN ('en_cours', 'termine') ORDER BY g.created_at DESC";
        try {
            $req = $db->prepare($sql);
            $req->execute(["aid" => $assistant_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die("Error:" . $e->getMessage());
        }
    }

    public function getGuidesByUser($user_id) {
        $db = Config::getConnexion();
        $sql = "SELECT g.*, go.description as goal_desc, u.nom as assistant_nom FROM guides g JOIN goals go ON g.goal_id = go.id LEFT JOIN utilisateurs u ON go.selected_assistant_id = u.id WHERE go.user_id = :uid ORDER BY g.created_at DESC";
        try {
            $req = $db->prepare($sql);
            $req->execute(["uid" => $user_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die("Error:" . $e->getMessage());
        }
    }
}