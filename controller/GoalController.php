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

    // === ADVANCED SEARCH (ASSISTANT SCOPE) ===
    // Mirrors getGoalsForAssistant() — admin-validated goals where the
    // assistant is the assignee OR the goal is still pending an assistant.
    // Then applies the same filters as searchGoals() on top.
    public function searchGoalsForAssistant(int $assistant_id, array $filters = []): array
    {
        $filters['admin_status'] = 'valide';
        $filters['__assistant_pool_id'] = $assistant_id;
        return $this->searchGoals($filters);
    }

    // === ADVANCED SEARCH ===
    // Accepted filter keys:
    //   keyword, type, status, admin_status, assistant_status, priority,
    //   user_id, assistant_id, date_from (Y-m-d), date_to (Y-m-d),
    //   sort = created_desc|created_asc|title_asc|title_desc|priority_high
    // Internal keys (used by helpers, not exposed in views):
    //   __assistant_pool_id  → "(selected = X OR assistant_status='en_attente')"
    public function searchGoals(array $filters = []): array
    {
        $sql = "SELECT g.*, u.nom AS user_name, a.nom AS assistant_name
                FROM goals g
                LEFT JOIN utilisateurs u ON g.user_id = u.id
                LEFT JOIN utilisateurs a ON g.selected_assistant_id = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['keyword'])) {
            $sql .= " AND (g.title LIKE :kw OR g.description LIKE :kw)";
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }
        $allowedTypes = ['cv', 'cover_letter', 'linkedin', 'interview', 'other'];
        if (!empty($filters['type']) && in_array($filters['type'], $allowedTypes, true)) {
            $sql .= " AND g.type = :type";
            $params['type'] = $filters['type'];
        }
        $allowedStatus = ['soumis', 'en_cours', 'termine', 'annule'];
        if (!empty($filters['status']) && in_array($filters['status'], $allowedStatus, true)) {
            $sql .= " AND g.status = :status";
            $params['status'] = $filters['status'];
        }
        $allowedAdmin = ['en_attente', 'valide', 'refuse'];
        if (!empty($filters['admin_status']) && in_array($filters['admin_status'], $allowedAdmin, true)) {
            $sql .= " AND g.admin_validation_status = :admin_status";
            $params['admin_status'] = $filters['admin_status'];
        }
        $allowedAssistant = ['en_attente', 'accepte', 'refuse'];
        if (!empty($filters['assistant_status']) && in_array($filters['assistant_status'], $allowedAssistant, true)) {
            $sql .= " AND g.assistant_validation_status = :assistant_status";
            $params['assistant_status'] = $filters['assistant_status'];
        }
        $allowedPriority = ['basse', 'moyenne', 'haute'];
        if (!empty($filters['priority']) && in_array($filters['priority'], $allowedPriority, true)) {
            $sql .= " AND g.priority = :priority";
            $params['priority'] = $filters['priority'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= " AND g.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['assistant_id'])) {
            $sql .= " AND g.selected_assistant_id = :assistant_id";
            $params['assistant_id'] = (int) $filters['assistant_id'];
        }
        if (!empty($filters['__assistant_pool_id'])) {
            $sql .= " AND (g.selected_assistant_id = :pool_id OR g.assistant_validation_status = 'en_attente')";
            $params['pool_id'] = (int) $filters['__assistant_pool_id'];
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
            'created_desc'  => 'g.created_at DESC',
            'created_asc'   => 'g.created_at ASC',
            'title_asc'     => 'g.title ASC',
            'title_desc'    => 'g.title DESC',
            'priority_high' => "FIELD(g.priority, 'haute','moyenne','basse')",
        ];
        $sortKey = $filters['sort'] ?? 'created_desc';
        $sql .= ' ORDER BY ' . ($sortMap[$sortKey] ?? $sortMap['created_desc']);

        $db = Config::getConnexion();
        try {
            $req = $db->prepare($sql);
            $req->execute($params);
            return $req->fetchAll();
        } catch (PDOException $e) {
            // Backward-compat: priority column missing
            if ($e->getCode() === '42S22' && (!empty($filters['priority']) || $sortKey === 'priority_high')) {
                $fallback = $filters;
                unset($fallback['priority']);
                if ($sortKey === 'priority_high') {
                    $fallback['sort'] = 'created_desc';
                }
                return $this->searchGoals($fallback);
            }
            die('Error:' . $e->getMessage());
        } catch (Exception $e) {
            die('Error:' . $e->getMessage());
        }
    }

    // === SHARE TOKEN (used by the QR-code feature on mes-accompagnements.php) ===
    // Returns the goal's share_token, generating one on first call. Returns null if
    // the goal does not belong to $userId or if the share_token column is missing.
    public function ensureShareToken(int $goalId, int $userId): ?string
    {
        $db = Config::getConnexion();
        try {
            $stmt = $db->prepare("SELECT share_token FROM goals WHERE id = :id AND user_id = :uid LIMIT 1");
            $stmt->execute(['id' => $goalId, 'uid' => $userId]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            if (!empty($row['share_token'])) {
                return $row['share_token'];
            }
            $token = bin2hex(random_bytes(16));
            $upd = $db->prepare("UPDATE goals SET share_token = :tok WHERE id = :id AND user_id = :uid");
            $upd->execute(['tok' => $token, 'id' => $goalId, 'uid' => $userId]);
            return $token;
        } catch (PDOException $e) {
            // 42S22 = column not found → schema not migrated yet
            if ($e->getCode() === '42S22') {
                return null;
            }
            throw $e;
        }
    }

    // === SMARTGUIDE: pick the best assistant for a given goal type ===
    // Returns the assistant who has accepted the most goals of that type
    // (proxy for "experience"), with completion count as a tiebreaker.
    // If no assistant has handled this type yet, falls back to the assistant
    // with the lightest active queue so the citizen still gets a useful suggestion.
    public function getBestAssistantForType(string $type): ?array
    {
        $allowed = ['cv', 'cover_letter', 'linkedin', 'interview', 'other'];
        if (!in_array($type, $allowed, true)) {
            return null;
        }
        $db = Config::getConnexion();
        try {
            $sql = "SELECT u.id, u.nom, u.prenom, u.email,
                           COUNT(g.id) AS handled,
                           SUM(CASE WHEN g.status = 'termine' THEN 1 ELSE 0 END) AS finished
                    FROM utilisateurs u
                    LEFT JOIN goals g ON g.selected_assistant_id = u.id
                                      AND g.type = :type
                                      AND g.assistant_validation_status = 'accepte'
                    WHERE LOWER(u.role) IN ('assistant', 'agent')
                    GROUP BY u.id
                    ORDER BY handled DESC, finished DESC, u.nom ASC
                    LIMIT 1";
            $req = $db->prepare($sql);
            $req->execute(['type' => $type]);
            $row = $req->fetch();
            if ($row && (int) $row['handled'] > 0) {
                return $row;
            }
            // Fallback: assistant with the lightest active queue
            $fallbackSql = "SELECT u.id, u.nom, u.prenom, u.email,
                                   0 AS handled, 0 AS finished,
                                   COUNT(g.id) AS active
                            FROM utilisateurs u
                            LEFT JOIN goals g ON g.selected_assistant_id = u.id
                                              AND g.status IN ('soumis', 'en_cours')
                            WHERE LOWER(u.role) IN ('assistant', 'agent')
                            GROUP BY u.id
                            ORDER BY active ASC, u.nom ASC
                            LIMIT 1";
            $row = $db->query($fallbackSql)->fetch();
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    // Same as ensureShareToken but checks ownership via selected_assistant_id
    // — used by the QR generator on the assistant's mission cards.
    public function ensureShareTokenForAssistant(int $goalId, int $assistantId): ?string
    {
        $db = Config::getConnexion();
        try {
            $stmt = $db->prepare("SELECT share_token FROM goals WHERE id = :id AND selected_assistant_id = :aid LIMIT 1");
            $stmt->execute(['id' => $goalId, 'aid' => $assistantId]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            if (!empty($row['share_token'])) {
                return $row['share_token'];
            }
            $token = bin2hex(random_bytes(16));
            $upd = $db->prepare("UPDATE goals SET share_token = :tok WHERE id = :id AND selected_assistant_id = :aid");
            $upd->execute(['tok' => $token, 'id' => $goalId, 'aid' => $assistantId]);
            return $token;
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                return null;
            }
            throw $e;
        }
    }

    // Public lookup by share_token (no auth) — returns a denormalized row with
    // the citizen and assistant names so the share page can render context.
    public function getGoalByShareToken(string $token)
    {
        if ($token === '') {
            return null;
        }
        $db = Config::getConnexion();
        try {
            $sql = "SELECT g.*,
                           u.nom    AS user_nom,    u.prenom AS user_prenom,
                           a.nom    AS assistant_nom, a.prenom AS assistant_prenom
                    FROM goals g
                    LEFT JOIN utilisateurs u ON g.user_id = u.id
                    LEFT JOIN utilisateurs a ON g.selected_assistant_id = a.id
                    WHERE g.share_token = :tok
                    LIMIT 1";
            $req = $db->prepare($sql);
            $req->execute(['tok' => $token]);
            $row = $req->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                return null;
            }
            throw $e;
        }
    }

    // Public write by share_token: records the citizen's urgency signal
    // (simple | assistance | urgent) selected from the QR-coded mobile page.
    public function setUrgencyLevel(string $token, string $level): bool
    {
        $allowed = ['simple', 'assistance', 'urgent'];
        if ($token === '' || !in_array($level, $allowed, true)) {
            return false;
        }
        $db = Config::getConnexion();
        try {
            $sql = "UPDATE goals
                    SET urgency_level = :lvl,
                        urgency_updated_at = CURRENT_TIMESTAMP
                    WHERE share_token = :tok";
            $req = $db->prepare($sql);
            $req->execute(['lvl' => $level, 'tok' => $token]);
            return $req->rowCount() > 0;
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                return false;
            }
            throw $e;
        }
    }
}

