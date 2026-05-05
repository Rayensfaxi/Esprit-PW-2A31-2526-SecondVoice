<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class VoteController
{
    private PDO $conn;

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            return;
        }

        if (class_exists('Config') && method_exists('Config', 'getConnexion')) {
            $configConnection = Config::getConnexion();
            if ($configConnection instanceof PDO) {
                $this->conn = $configConnection;
                return;
            }
        }

        throw new RuntimeException('Connexion base de donnees indisponible.');
    }

    public function addVote(int $userId, int $ideeId, string $type): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO vote (user_id, idee_id, type, date_vote) VALUES (?, ?, ?, NOW())"
            );
            return $stmt->execute([$userId, $ideeId, $type]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateVote(int $userId, int $ideeId, string $type): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE vote SET type = ?, date_vote = NOW() WHERE user_id = ? AND idee_id = ?"
            );
            return $stmt->execute([$type, $userId, $ideeId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUserVote(int $userId, int $ideeId): ?array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, user_id, idee_id, type, date_vote FROM vote WHERE user_id = ? AND idee_id = ?"
            );
            $stmt->execute([$userId, $ideeId]);
            $vote = $stmt->fetch(PDO::FETCH_ASSOC);

            return $vote ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getVotesByIdee(int $ideeId): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, user_id, idee_id, type, date_vote FROM vote WHERE idee_id = ?"
            );
            $stmt->execute([$ideeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function calculateWinner(int $brainstormingId): array
    {
        try {
            // Get the idea with most likes
            $stmt = $this->conn->prepare(
                "SELECT id, contenu, likes FROM ideas WHERE brainstorming_id = ? ORDER BY likes DESC LIMIT 1"
            );
            $stmt->execute([$brainstormingId]);
            $winner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$winner) {
                return ['success' => false, 'message' => 'Aucune idee trouvee pour ce brainstorming.'];
            }

            // Reset all winners in this brainstorming
            $stmt = $this->conn->prepare(
                "UPDATE ideas SET is_winner = 0 WHERE brainstorming_id = ?"
            );
            $stmt->execute([$brainstormingId]);

            // Mark the winner
            $stmt = $this->conn->prepare(
                "UPDATE ideas SET is_winner = 1 WHERE id = ?"
            );
            $success = $stmt->execute([$winner['id']]);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Gagnant calcule: "' . substr($winner['contenu'], 0, 50) . '..." avec ' . $winner['likes'] . ' likes.'
                ];
            }

            return ['success' => false, 'message' => 'Erreur lors du marquage du gagnant.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    public function isVotePeriodOpen(int $brainstormingId): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT vote_start, vote_end FROM brainstorming WHERE id = ?"
            );
            $stmt->execute([$brainstormingId]);
            $brainstorming = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$brainstorming || !$brainstorming['vote_start'] || !$brainstorming['vote_end']) {
                return false;
            }

            $now = new DateTime();
            $start = new DateTime($brainstorming['vote_start']);
            $end = new DateTime($brainstorming['vote_end']);

            return $now >= $start && $now <= $end;
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateVoteCounts(int $ideeId): void
    {
        try {
            // Count likes
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) as count FROM vote WHERE idee_id = ? AND type = 'like'"
            );
            $stmt->execute([$ideeId]);
            $likes = (int) $stmt->fetchColumn();

            // Count dislikes
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) as count FROM vote WHERE idee_id = ? AND type = 'dislike'"
            );
            $stmt->execute([$ideeId]);
            $dislikes = (int) $stmt->fetchColumn();

            // Update idea counts
            $stmt = $this->conn->prepare(
                "UPDATE ideas SET likes = ?, dislikes = ? WHERE id = ?"
            );
            $stmt->execute([$likes, $dislikes, $ideeId]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }

    public function getBrainstormingVoteStatus(int $brainstormingId): ?array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT vote_start, vote_end FROM brainstorming WHERE id = ?"
            );
            $stmt->execute([$brainstormingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            $isOpen = false;
            if ($result['vote_start'] && $result['vote_end']) {
                try {
                    $now = new DateTime();
                    $start = new DateTime($result['vote_start']);
                    $end = new DateTime($result['vote_end']);
                    $isOpen = $now >= $start && $now <= $end;
                } catch (Exception $e) {
                    $isOpen = false;
                }
            }

            return [
                'status' => ($result['vote_start'] && $result['vote_end']) ? 'open' : 'closed',
                'start' => $result['vote_start'],
                'end' => $result['vote_end'],
                'isOpen' => $isOpen
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    public function openVotePeriod(int $brainstormingId, string $startDate, string $endDate): array
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE brainstorming SET vote_start = ?, vote_end = ? WHERE id = ?"
            );
            $stmt->execute([$startDate, $endDate, $brainstormingId]);

            return ['success' => true, 'message' => 'Période de vote ouverte.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de l\'ouverture de la période de vote.'];
        }
    }

    public function closeVotePeriod(int $brainstormingId): array
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE brainstorming SET vote_start = NULL, vote_end = NULL WHERE id = ?"
            );
            $stmt->execute([$brainstormingId]);

            // Auto-mark winner
            $this->calculateWinner($brainstormingId);

            return ['success' => true, 'message' => 'Période de vote fermée.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la fermeture de la période de vote.'];
        }
    }
}
