<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/idea.php';
require_once __DIR__ . '/../model/utilisateur.php';

class IdeaController
{
    private PDO $conn;

    private const ALLOWED_STATUTS = ['en attente', 'approuve', 'desapprouve'];

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            return;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $this->conn = $GLOBALS['conn'];
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

    public function addIdea(int $brainstormingId, int $userId, string $contenu, bool $isAdmin = false): int
    {
        if (empty(trim($contenu))) {
            throw new InvalidArgumentException('Le contenu de l\'ideas est obligatoire.');
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

    public function getIdeasByBrainstormingId(int $brainstormingId): array
    {
        $sql = 'SELECT i.*, u.nom as auteur_nom, u.prenom as auteur_prenom
                FROM ideas i
                LEFT JOIN utilisateur u ON i.user_id = u.id
                WHERE i.brainstorming_id = :brainstorming_id
                ORDER BY i.date_creation DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':brainstorming_id' => $brainstormingId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeIdeaRow($row), $rows);
    }

    public function getAllIdeas(array $filters = []): array
    {
        $sql = 'SELECT i.id, i.brainstorming_id, i.user_id, i.contenu, i.date_creation, i.statut,
                       b.titre as brainstorming_titre,
                       u.nom, u.prenom
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

        $sql .= ' ORDER BY i.date_creation DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeIdeaRow($row), $rows);
    }

    public function getIdeaById(int $id): ?array
    {
        $sql = 'SELECT i.id, i.brainstorming_id, i.user_id, i.contenu, i.date_creation, i.statut,
                       u.nom, u.prenom
                FROM ideas i
                LEFT JOIN utilisateur u ON i.user_id = u.id
                WHERE i.id = :id';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeIdeaRow($row) : null;
    }

    public function updateIdea(int $id, string $contenu, int $userId, bool $isAdmin = false): bool
    {
        if (empty(trim($contenu))) {
            throw new InvalidArgumentException('Le contenu de l\'ideas est obligatoire.');
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
            'auteur_nom' => (string) ($row['nom'] ?? ''),
            'auteur_prenom' => (string) ($row['prenom'] ?? ''),
            'brainstorming_titre' => (string) ($row['brainstorming_titre'] ?? ''),
            'likes' => (int) ($row['likes'] ?? 0),
            'dislikes' => (int) ($row['dislikes'] ?? 0),
            'is_winner' => (bool) ($row['is_winner'] ?? false)
        ];
    }
}
