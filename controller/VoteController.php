<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class VoteController
{
    private PDO $conn;
    private static ?bool $schemaReady = null;

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            $this->ensureSchema();
            return;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $this->conn = $GLOBALS['conn'];
            $this->ensureSchema();
            return;
        }

        if (class_exists('Config') && method_exists('Config', 'getConnexion')) {
            $configConnection = Config::getConnexion();
            if ($configConnection instanceof PDO) {
                $this->conn = $configConnection;
                $this->ensureSchema();
                return;
            }
        }

        throw new RuntimeException('Connexion base de donnees indisponible.');
    }

    public function addVote(int $userId, int $ideeId, string $type): bool
    {
        $normalizedType = strtolower(trim($type));
        if ($userId <= 0 || $ideeId <= 0 || !in_array($normalizedType, ['like', 'dislike'], true)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO vote (user_id, idee_id, type, date_vote) VALUES (?, ?, ?, NOW())'
            );
            return $stmt->execute([$userId, $ideeId, $normalizedType]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateVote(int $userId, int $ideeId, string $type): bool
    {
        $normalizedType = strtolower(trim($type));
        if ($userId <= 0 || $ideeId <= 0 || !in_array($normalizedType, ['like', 'dislike'], true)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare(
                'UPDATE vote SET type = ?, date_vote = NOW() WHERE user_id = ? AND idee_id = ?'
            );
            return $stmt->execute([$normalizedType, $userId, $ideeId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUserVote(int $userId, int $ideeId): ?array
    {
        try {
            $stmt = $this->conn->prepare(
                'SELECT id, user_id, idee_id, type, date_vote FROM vote WHERE user_id = ? AND idee_id = ?'
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
                'SELECT id, user_id, idee_id, type, date_vote FROM vote WHERE idee_id = ?'
            );
            $stmt->execute([$ideeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getVoteMapForUser(int $userId, array $ideaIds): array
    {
        if ($userId <= 0 || $ideaIds === []) {
            return [];
        }

        $ideaIds = array_values(array_unique(array_filter(array_map('intval', $ideaIds), fn(int $id): bool => $id > 0)));
        if ($ideaIds === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ideaIds), '?'));
            $stmt = $this->conn->prepare(
                "SELECT idee_id, type FROM vote WHERE user_id = ? AND idee_id IN ($placeholders)"
            );
            $stmt->execute(array_merge([$userId], $ideaIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row['idee_id']] = (string) $row['type'];
            }

            return $map;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function calculateWinner(int $brainstormingId): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, contenu, likes FROM ideas WHERE brainstorming_id = ? AND statut = 'approuve' ORDER BY likes DESC, dislikes ASC, id ASC LIMIT 1"
            );
            $stmt->execute([$brainstormingId]);
            $winner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$winner) {
                return ['success' => false, 'message' => 'Aucune idee approuvee trouvee pour ce brainstorming.'];
            }

            $stmt = $this->conn->prepare('UPDATE ideas SET is_winner = 0 WHERE brainstorming_id = ?');
            $stmt->execute([$brainstormingId]);

            $stmt = $this->conn->prepare('UPDATE ideas SET is_winner = 1 WHERE id = ?');
            $success = $stmt->execute([(int) $winner['id']]);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Gagnant calcule avec ' . (int) $winner['likes'] . ' likes.'
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
            $stmt = $this->conn->prepare('SELECT vote_start, vote_end FROM brainstorming WHERE id = ?');
            $stmt->execute([$brainstormingId]);
            $brainstorming = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$brainstorming) {
                return false;
            }

            if (empty($brainstorming['vote_start']) || empty($brainstorming['vote_end'])) {
                return true;
            }

            $now = new DateTime();
            $start = new DateTime((string) $brainstorming['vote_start']);
            $end = new DateTime((string) $brainstorming['vote_end']);

            return $now >= $start && $now <= $end;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function updateVoteCounts(int $ideeId): void
    {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM vote WHERE idee_id = ? AND type = 'like'");
            $stmt->execute([$ideeId]);
            $likes = (int) $stmt->fetchColumn();

            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM vote WHERE idee_id = ? AND type = 'dislike'");
            $stmt->execute([$ideeId]);
            $dislikes = (int) $stmt->fetchColumn();

            $stmt = $this->conn->prepare('UPDATE ideas SET likes = ?, dislikes = ? WHERE id = ?');
            $stmt->execute([$likes, $dislikes, $ideeId]);
        } catch (PDOException $e) {
        }
    }

    public function getBrainstormingVoteStatus(int $brainstormingId): ?array
    {
        try {
            $stmt = $this->conn->prepare('SELECT vote_start, vote_end FROM brainstorming WHERE id = ?');
            $stmt->execute([$brainstormingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            if (empty($result['vote_start']) || empty($result['vote_end'])) {
                return [
                    'status' => 'open',
                    'start' => '',
                    'end' => '',
                    'isOpen' => true
                ];
            }

            $status = 'closed';
            $isOpen = false;
            try {
                $now = new DateTime();
                $start = new DateTime((string) $result['vote_start']);
                $end = new DateTime((string) $result['vote_end']);

                if ($now < $start) {
                    $status = 'scheduled';
                } elseif ($now <= $end) {
                    $status = 'open';
                    $isOpen = true;
                }
            } catch (Throwable $e) {
                $status = 'closed';
            }

            return [
                'status' => $status,
                'start' => (string) ($result['vote_start'] ?? ''),
                'end' => (string) ($result['vote_end'] ?? ''),
                'isOpen' => $isOpen
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    public function openVotePeriod(int $brainstormingId, string $startDate, string $endDate): array
    {
        try {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            if ($end <= $start) {
                return ['success' => false, 'message' => 'La date de fin doit etre apres la date de debut.'];
            }

            $stmt = $this->conn->prepare('UPDATE brainstorming SET vote_start = ?, vote_end = ? WHERE id = ?');
            $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $brainstormingId]);

            return ['success' => true, 'message' => 'Periode de vote ouverte.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erreur lors de l\'ouverture de la periode de vote.'];
        }
    }

    public function closeVotePeriod(int $brainstormingId): array
    {
        try {
            $start = (new DateTime('-1 day'))->format('Y-m-d H:i:s');
            $end = (new DateTime('-1 second'))->format('Y-m-d H:i:s');

            $stmt = $this->conn->prepare('UPDATE brainstorming SET vote_start = ?, vote_end = ? WHERE id = ?');
            $stmt->execute([$start, $end, $brainstormingId]);

            $this->calculateWinner($brainstormingId);

            return ['success' => true, 'message' => 'Periode de vote fermee.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la fermeture de la periode de vote.'];
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady === true) {
            return;
        }

        $this->conn->exec(
            "CREATE TABLE IF NOT EXISTS ideas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                brainstorming_id INT NOT NULL,
                user_id INT NOT NULL,
                contenu TEXT NOT NULL,
                date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                statut VARCHAR(30) NOT NULL DEFAULT 'en attente',
                likes INT NOT NULL DEFAULT 0,
                dislikes INT NOT NULL DEFAULT 0,
                is_winner TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_ideas_brainstorming (brainstorming_id),
                INDEX idx_ideas_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->conn->exec(
            "CREATE TABLE IF NOT EXISTS vote (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                idee_id INT NOT NULL,
                type VARCHAR(20) NOT NULL,
                date_vote DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_vote_user_idea (user_id, idee_id),
                INDEX idx_vote_idee (idee_id),
                INDEX idx_vote_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->ensureColumn('brainstorming', 'vote_start', 'DATETIME NULL');
        $this->ensureColumn('brainstorming', 'vote_end', 'DATETIME NULL');
        $this->ensureColumn('ideas', 'likes', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('ideas', 'dislikes', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('ideas', 'is_winner', 'TINYINT(1) NOT NULL DEFAULT 0');

        self::$schemaReady = true;
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if (!in_array($table, ['brainstorming', 'ideas'], true)) {
            throw new InvalidArgumentException('Table invalide.');
        }

        $stmt = $this->conn->query("SHOW COLUMNS FROM $table LIKE " . $this->conn->quote($column));
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        try {
            $this->conn->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        } catch (Throwable $exception) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM $table LIKE " . $this->conn->quote($column));
            if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Impossible d\'ajouter la colonne ' . $column . ' sur ' . $table . '.');
            }
        }
    }
}
