<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/goal.php';

class GoalController
{
    private function assistantExistsInUtilisateurs(int $assistantId): bool
    {
        if ($assistantId <= 0) {
            return false;
        }

        $db = Config::getConnexion();
        try {
            $sql = "SELECT id FROM utilisateurs WHERE id = :id LIMIT 1";
            $req = $db->prepare($sql);
            $req->execute(['id' => $assistantId]);
            return (bool) $req->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    // === CREATE ===
    public function createGoal($goal)
    {
        if (!$this->assistantExistsInUtilisateurs($goal->getSelectedAssistantId())) {
            throw new RuntimeException("Assistant invalide: l'identifiant selectionne n'existe pas dans la table utilisateurs.");
        }

        $sql = "INSERT INTO goals (user_id, selected_assistant_id, title, description, type, admin_validation_status, assistant_validation_status, status)
                VALUES (:user_id, :selected_assistant_id, :title, :description, :type, :admin_validation_status, :assistant_validation_status, :status)";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'user_id' => $goal->getUserId(),
                'selected_assistant_id' => $goal->getSelectedAssistantId(),
                'title' => $goal->getTitle(),
                'description' => $goal->getDescription(),
                'type' => $goal->getType(),
                'admin_validation_status' => $goal->getAdminValidationStatus(),
                'assistant_validation_status' => $goal->getAssistantValidationStatus(),
                'status' => $goal->getStatus()
            ]);
            return true;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === READ / LIST BY USER ===
    public function getGoalsByUser($user_id)
    {
        $sql = "SELECT g.*, u.nom as assistant_name 
                FROM goals g 
                LEFT JOIN utilisateurs u ON g.selected_assistant_id = u.id 
                WHERE g.user_id = :user_id";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['user_id' => $user_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === READ / LIST FOR ADMIN (Pending) ===
    public function getPendingGoalsForAdmin()
    {
        $sql = "SELECT g.*, u.nom as user_name, a.nom as assistant_name 
                FROM goals g 
                JOIN utilisateurs u ON g.user_id = u.id 
                JOIN utilisateurs a ON g.selected_assistant_id = a.id
                WHERE g.admin_validation_status = 'en_attente'";
        $db = Config::getConnexion();
        try {
            return $db->query($sql)->fetchAll();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === READ / LIST FOR ASSISTANT (Assigned and validated by admin) ===
    public function getGoalsForAssistant($assistant_id)
    {
        $sql = "SELECT g.*, u.nom as user_name 
                FROM goals g 
                JOIN utilisateurs u ON g.user_id = u.id 
                WHERE g.admin_validation_status = 'valide'
                AND (
                    g.selected_assistant_id = :assistant_id
                    OR g.assistant_validation_status = 'en_attente'
                )";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['assistant_id' => $assistant_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === READ SINGLE GOAL ===
    public function getGoalById($id)
    {
        $sql = "SELECT * FROM goals WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return $req->fetch();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === EDIT / UPDATE BY USER (Only if not validated yet) ===
    public function updateGoalByUser($goal, $id)
    {
        if (!$this->assistantExistsInUtilisateurs($goal->getSelectedAssistantId())) {
            throw new RuntimeException("Assistant invalide: l'identifiant selectionne n'existe pas dans la table utilisateurs.");
        }

        $sql = "UPDATE goals SET 
                title = :title, 
                description = :description, 
                type = :type, 
                selected_assistant_id = :selected_assistant_id 
                WHERE id = :id AND admin_validation_status = 'en_attente'";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'title' => $goal->getTitle(),
                'description' => $goal->getDescription(),
                'type' => $goal->getType(),
                'selected_assistant_id' => $goal->getSelectedAssistantId(),
                'id' => $id
            ]);
            return $req->rowCount() > 0;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === ADMIN VALIDATION ===
    public function moderateGoalByAdmin($id, $decision, $comment)
    {
        $db = Config::getConnexion();
        try {
            // If admin refuses, delete the demand entirely.
            if ($decision === 'refuse') {
                $sqlDelete = "DELETE FROM goals WHERE id = :id";
                $reqDelete = $db->prepare($sqlDelete);
                $reqDelete->execute(['id' => $id]);
                return $reqDelete->rowCount() > 0;
            }

            // decision == 'valide': transfer to assistant queue.
            $sql = "UPDATE goals SET admin_validation_status = :decision, admin_comment = :comment WHERE id = :id";
            $req = $db->prepare($sql);
            $req->execute([
                'decision' => $decision,
                'comment' => $comment,
                'id' => $id
            ]);
            return $req->rowCount() > 0;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === ASSISTANT VALIDATION ===
    public function evaluateGoalByAssistant($id, $assistant_id, $decision, $comment, $priority = 'moyenne', $status = 'en_cours')
    {
        $db = Config::getConnexion();
        try {
            $allowedStatus = ['en_cours', 'termine'];
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'en_cours';
            }

            // If assistant refuses, delete the demand.
            if ($decision === 'refuse') {
                $sqlDelete = "DELETE FROM goals WHERE id = :id AND admin_validation_status = 'valide'";
                $reqDelete = $db->prepare($sqlDelete);
                $reqDelete->execute(['id' => $id]);
                return $reqDelete->rowCount() > 0;
            }

            // Assistant accepts: keep selected assistant unless current assistant exists in utilisateurs.
            $canReassign = $this->assistantExistsInUtilisateurs($assistant_id);

            try {
                $sql = "UPDATE goals SET assistant_validation_status = :decision, assistant_comment = :comment, status = :status, " . ($canReassign ? "selected_assistant_id = :assistant_id, " : "") . "priority = :priority WHERE id = :id AND admin_validation_status = 'valide' AND assistant_validation_status = 'en_attente'";
                $req = $db->prepare($sql);
                $params = [
                    'decision' => $decision,
                    'comment' => $comment,
                    'status' => $status,
                    'priority' => $priority,
                    'id' => $id
                ];
                if ($canReassign) {
                    $params['assistant_id'] = $assistant_id;
                }
                $req->execute($params);
                return $req->rowCount() > 0;
            } catch (PDOException $e) {
                // Backward compatibility: if priority column does not exist yet.
                if ($e->getCode() !== '42S22') {
                    throw $e;
                }

                $sqlFallback = "UPDATE goals SET assistant_validation_status = :decision, assistant_comment = :comment, status = :status" . ($canReassign ? ", selected_assistant_id = :assistant_id" : "") . " WHERE id = :id AND admin_validation_status = 'valide' AND assistant_validation_status = 'en_attente'";
                $reqFallback = $db->prepare($sqlFallback);
                $paramsFallback = [
                    'decision' => $decision,
                    'comment' => $comment,
                    'status' => $status,
                    'id' => $id
                ];
                if ($canReassign) {
                    $paramsFallback['assistant_id'] = $assistant_id;
                }
                $reqFallback->execute($paramsFallback);
                return $reqFallback->rowCount() > 0;
            }
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }
    
    // === ASSISTANT FINISH GOAL ===
    public function markGoalAsFinished($id)
    {
        $sql = "UPDATE goals SET status = 'termine' WHERE id = :id AND assistant_validation_status = 'accepte'";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === DELETE BY USER ===
    public function deleteGoal($id, $user_id)
    {
        // Allowed only if not taken in charge by admin yet
        $sql = "DELETE FROM goals WHERE id = :id AND user_id = :user_id AND admin_validation_status = 'en_attente'";
        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute([
                'id' => $id,
                'user_id' => $user_id
            ]);
            return $req->rowCount() > 0;
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    public function getAcceptedGoalsForAssistant($assistant_id) {
        $db = Config::getConnexion();
        $sql = "SELECT g.*, u.nom FROM goals g JOIN utilisateurs u ON g.user_id = u.id WHERE g.selected_assistant_id = :aid AND g.admin_validation_status = 'valide' AND g.assistant_validation_status = 'accepte' AND g.status IN ('en_cours', 'termine')";
        try {
            $req = $db->prepare($sql);
            $req->execute(['aid' => $assistant_id]);
            return $req->fetchAll();
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }
}

