<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/idea.php';
require_once __DIR__ . '/../model/utilisateur.php';

class IdeaController
{
    private PDO $conn;
    private static ?bool $schemaReady = null;

    private const ALLOWED_STATUTS = ['en attente', 'approuve', 'desapprouve'];

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

    public function addIdea(int $brainstormingId, int $userId, string $contenu, bool $isAdmin = false): int
    {
        $this->ensureSchema();

        if ($brainstormingId <= 0) {
            throw new InvalidArgumentException('Brainstorming invalide.');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('Utilisateur invalide.');
        }

        if (empty(trim($contenu))) {
            throw new InvalidArgumentException('Le contenu de l\'idee est obligatoire.');
        }

        $statut = $isAdmin ? 'approuve' : 'en attente';

        $sql = 'INSERT INTO ideas (brainstorming_id, user_id, contenu, date_creation, statut)
                VALUES (:brainstorming_id, :user_id, :contenu, NOW(), :statut)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':brainstorming_id' => $brainstormingId,
            ':user_id' => $userId,
            ':contenu' => trim($contenu),
            ':statut' => $statut
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function getIdeasByBrainstormingId(int $brainstormingId, int $viewerId = 0, bool $isAdmin = false): array
    {
        $this->ensureSchema();

        $sql = 'SELECT i.id, i.brainstorming_id, i.user_id, i.contenu, i.date_creation, i.statut,
                       i.likes, i.dislikes, i.is_winner,
                       u.nom AS auteur_nom, u.prenom AS auteur_prenom
                FROM ideas i
                LEFT JOIN utilisateur u ON i.user_id = u.id
                WHERE i.brainstorming_id = :brainstorming_id';
        $params = [':brainstorming_id' => $brainstormingId];

        if (!$isAdmin) {
            if ($viewerId > 0) {
                $sql .= ' AND (i.statut = :approved_status OR i.user_id = :viewer_id)';
                $params[':viewer_id'] = $viewerId;
                $params[':approved_status'] = 'approuve';
            } else {
                $sql .= ' AND i.statut = :approved_status';
                $params[':approved_status'] = 'approuve';
            }
        }

        $sql .= ' ORDER BY i.is_winner DESC, i.likes DESC, i.date_creation DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeIdeaRow($row), $rows);
    }

    public function getAllIdeas(array $filters = []): array
    {
        $this->ensureSchema();

        $sql = 'SELECT i.id, i.brainstorming_id, i.user_id, i.contenu, i.date_creation, i.statut,
                       i.likes, i.dislikes, i.is_winner,
                       b.titre AS brainstorming_titre,
                       u.nom AS auteur_nom, u.prenom AS auteur_prenom
                FROM ideas i
                LEFT JOIN brainstorming b ON i.brainstorming_id = b.id
                LEFT JOIN utilisateur u ON i.user_id = u.id
                WHERE 1=1';
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND i.contenu LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $statusFilter = trim((string) ($filters['statut'] ?? $filters['status'] ?? ''));
        if ($statusFilter !== '' && $statusFilter !== 'toutes') {
            $sql .= ' AND i.statut = :status_filter';
            $params[':status_filter'] = strtolower($statusFilter);
        }

        $sql .= ' ORDER BY i.is_winner DESC, i.date_creation DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeIdeaRow($row), $rows);
    }

    public function getIdeaById(int $id): ?array
    {
        $this->ensureSchema();

        $sql = 'SELECT i.id, i.brainstorming_id, i.user_id, i.contenu, i.date_creation, i.statut,
                       i.likes, i.dislikes, i.is_winner,
                       b.titre AS brainstorming_titre,
                       u.nom AS auteur_nom, u.prenom AS auteur_prenom
                FROM ideas i
                LEFT JOIN brainstorming b ON i.brainstorming_id = b.id
                LEFT JOIN utilisateur u ON i.user_id = u.id
                WHERE i.id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeIdeaRow($row) : null;
    }

    public function updateIdea(int $id, string $contenu, int $userId, bool $isAdmin = false): bool
    {
        $this->ensureSchema();

        if (empty(trim($contenu))) {
            throw new InvalidArgumentException('Le contenu de l\'idee est obligatoire.');
        }

        $sql = 'UPDATE ideas SET contenu = :contenu WHERE id = :id';
        $params = [
            ':contenu' => trim($contenu),
            ':id' => $id
        ];

        if (!$isAdmin) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteIdea(int $id, int $userId, bool $isAdmin = false): bool
    {
        $this->ensureSchema();

        if (!$isAdmin) {
            $idea = $this->getIdeaById($id);
            if (!$idea || (int) ($idea['user_id'] ?? 0) !== $userId) {
                return false;
            }
        }

        $this->deleteVotesForIdea($id);

        $sql = 'DELETE FROM ideas WHERE id = :id';
        $params = [':id' => $id];

        if (!$isAdmin) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updateIdeaStatus(int $id, string $statut): bool
    {
        $this->ensureSchema();

        $normalizedStatus = strtolower(trim($statut));
        if (!in_array($normalizedStatus, self::ALLOWED_STATUTS, true)) {
            throw new InvalidArgumentException('Statut invalide.');
        }

        $stmt = $this->conn->prepare('UPDATE ideas SET statut = :statut WHERE id = :id');
        return $stmt->execute([
            ':statut' => $normalizedStatus,
            ':id' => $id
        ]);
    }

    private function normalizeIdeaRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'brainstorming_id' => (int) ($row['brainstorming_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'contenu' => (string) ($row['contenu'] ?? ''),
            'date_creation' => (string) ($row['date_creation'] ?? ''),
            'statut' => (string) ($row['statut'] ?? 'en attente'),
            'auteur_nom' => (string) ($row['auteur_nom'] ?? $row['nom'] ?? ''),
            'auteur_prenom' => (string) ($row['auteur_prenom'] ?? $row['prenom'] ?? ''),
            'brainstorming_titre' => (string) ($row['brainstorming_titre'] ?? ''),
            'likes' => (int) ($row['likes'] ?? 0),
            'dislikes' => (int) ($row['dislikes'] ?? 0),
            'is_winner' => (bool) ($row['is_winner'] ?? false)
        ];
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

        $this->ensureColumn('likes', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('dislikes', 'INT NOT NULL DEFAULT 0');
        $this->ensureColumn('is_winner', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureColumn('statut', "VARCHAR(30) NOT NULL DEFAULT 'en attente'");

        self::$schemaReady = true;
    }

    private function ensureColumn(string $column, string $definition): void
    {
        $stmt = $this->conn->query("SHOW COLUMNS FROM ideas LIKE " . $this->conn->quote($column));
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        try {
            $this->conn->exec('ALTER TABLE ideas ADD COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable $exception) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM ideas LIKE " . $this->conn->quote($column));
            if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Impossible d\'ajouter la colonne ' . $column . ' sur ideas.');
            }
        }
    }

    private function deleteVotesForIdea(int $ideaId): void
    {
        try {
            $stmt = $this->conn->prepare('DELETE FROM vote WHERE idee_id = :id');
            $stmt->execute([':id' => $ideaId]);
        } catch (Throwable $exception) {
        }
    }
}
