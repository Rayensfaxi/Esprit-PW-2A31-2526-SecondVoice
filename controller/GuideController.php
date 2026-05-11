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

    // === DETAILS (with parent goal + citizen + assistant) ===
    public function getGuideWithContext(int $id)
    {
        $db = Config::getConnexion();
        $sqlFull = "SELECT
                        g.id, g.goal_id, g.title, g.content, g.created_at, g.updated_at,
                        go.title       AS goal_title,
                        go.description AS goal_description,
                        go.type        AS goal_type,
                        go.status      AS goal_status,
                        go.priority    AS goal_priority,
                        go.admin_validation_status,
                        go.assistant_validation_status,
                        go.admin_comment,
                        go.assistant_comment,
                        go.created_at  AS goal_created_at,
                        u.nom          AS user_nom,
                        u.prenom       AS user_prenom,
                        u.email        AS user_email,
                        a.nom          AS assistant_nom,
                        a.prenom       AS assistant_prenom,
                        a.email        AS assistant_email
                    FROM guides g
                    JOIN goals go ON g.goal_id = go.id
                    LEFT JOIN utilisateurs u ON go.user_id = u.id
                    LEFT JOIN utilisateurs a ON go.selected_assistant_id = a.id
                    WHERE g.id = :id";
        try {
            $req = $db->prepare($sqlFull);
            $req->execute(['id' => $id]);
            return $req->fetch();
        } catch (PDOException $e) {
            // Backward-compat: priority column may not exist yet
            if ($e->getCode() !== '42S22') { die("Error: " . $e->getMessage()); }
            $sqlFallback = str_replace('go.priority    AS goal_priority,', "NULL AS goal_priority,", $sqlFull);
            $req = $db->prepare($sqlFallback);
            $req->execute(['id' => $id]);
            return $req->fetch();
        } catch (Exception $e) {
            die("Error: " . $e->getMessage());
        }
    }

    // === ADVANCED SEARCH ===
    // Accepted filter keys:
    //   keyword, goal_id, user_id, assistant_id, goal_type, goal_status,
    //   date_from (Y-m-d), date_to (Y-m-d),
    //   sort = created_desc|created_asc|title_asc|title_desc
    public function searchGuides(array $filters = []): array
    {
        $sql = "SELECT g.*,
                       go.title       AS goal_title,
                       go.description AS goal_desc,
                       go.type        AS goal_type,
                       go.status      AS goal_status,
                       u.nom          AS user_nom,
                       a.nom          AS assistant_nom
                FROM guides g
                JOIN goals go ON g.goal_id = go.id
                LEFT JOIN utilisateurs u ON go.user_id = u.id
                LEFT JOIN utilisateurs a ON go.selected_assistant_id = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['keyword'])) {
            $sql .= " AND (g.title LIKE :kw OR g.content LIKE :kw OR go.title LIKE :kw)";
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }
        if (!empty($filters['goal_id'])) {
            $sql .= " AND g.goal_id = :goal_id";
            $params['goal_id'] = (int) $filters['goal_id'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND go.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['assistant_id'])) {
            $sql .= " AND go.selected_assistant_id = :assistant_id";
            $params['assistant_id'] = (int) $filters['assistant_id'];
        }
        $allowedTypes = ['cv', 'cover_letter', 'linkedin', 'interview', 'other'];
        if (!empty($filters['goal_type']) && in_array($filters['goal_type'], $allowedTypes, true)) {
            $sql .= " AND go.type = :goal_type";
            $params['goal_type'] = $filters['goal_type'];
        }
        $allowedStatus = ['soumis', 'en_cours', 'termine', 'annule'];
        if (!empty($filters['goal_status']) && in_array($filters['goal_status'], $allowedStatus, true)) {
            $sql .= " AND go.status = :goal_status";
            $params['goal_status'] = $filters['goal_status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND g.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND g.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sortMap = [
            'created_desc' => 'g.created_at DESC',
            'created_asc'  => 'g.created_at ASC',
            'title_asc'    => 'g.title ASC',
            'title_desc'   => 'g.title DESC',
        ];
        $sortKey = $filters['sort'] ?? 'created_desc';
        $sql .= ' ORDER BY ' . ($sortMap[$sortKey] ?? $sortMap['created_desc']);

        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute($params);
            return $req->fetchAll();
        } catch (Exception $e) {
            die("Error:" . $e->getMessage());
        }
    }
}