<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/brainstorming.php';

class BrainstormingController
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

    public function addBrainstorming(string $titre, string $description, string $categorie): int
    {
        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $brainstorming = new Brainstorming(
            trim($titre),
            trim($description),
            trim($categorie),
            date('Y-m-d'),
            'en attente'
        );

        $this->ensureSchema();

        $sql = 'INSERT INTO brainstorming (titre, description, categorie, dateCreation, statut)
                VALUES (:titre, :description, :categorie, :dateCreation, :statut)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':titre' => $brainstorming->getTitre(),
            ':description' => $brainstorming->getDescription(),
            ':categorie' => $brainstorming->getCategorie(),
            ':dateCreation' => $brainstorming->getDateCreation(),
            ':statut' => $brainstorming->getStatut()
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function addBrainstormingForUser(string $titre, string $description, string $categorie, int $userId): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Utilisateur invalide.');
        }

        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $this->ensureSchema();

        $brainstorming = new Brainstorming(
            trim($titre),
            trim($description),
            trim($categorie),
            date('Y-m-d'),
            'en attente'
        );

        $sql = 'INSERT INTO brainstorming (titre, description, categorie, dateCreation, statut, user_id)
                VALUES (:titre, :description, :categorie, :dateCreation, :statut, :user_id)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':titre' => $brainstorming->getTitre(),
            ':description' => $brainstorming->getDescription(),
            ':categorie' => $brainstorming->getCategorie(),
            ':dateCreation' => $brainstorming->getDateCreation(),
            ':statut' => $brainstorming->getStatut(),
            ':user_id' => $userId
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function getBrainstormings(array $filters = []): array
    {
        $this->ensureSchema();

        $sql = 'SELECT id, titre, description, categorie, dateCreation, statut, user_id, vote_start, vote_end FROM brainstorming WHERE 1=1';
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND titre LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $categorieFilter = trim((string) ($filters['categorie'] ?? ''));
        if ($categorieFilter !== '' && $categorieFilter !== 'toutes') {
            $sql .= ' AND categorie = :categorie';
            $params[':categorie'] = $categorieFilter;
        }

        $ownerId = (int) ($filters['owner_id'] ?? 0);
        $includeGlobal = !empty($filters['include_global']);
        $globalOnly = !empty($filters['global_only']);
        $approvedOnly = !empty($filters['approved_only']);

        if ($globalOnly) {
            $sql .= ' AND user_id IS NULL';
        } elseif ($ownerId > 0 && $includeGlobal) {
            if ($approvedOnly) {
                $sql .= ' AND (user_id = :owner_id OR statut = :approved_visibility_status)';
                $params[':approved_visibility_status'] = 'approuve';
            } else {
                $sql .= ' AND (user_id = :owner_id OR user_id IS NULL)';
            }
            $params[':owner_id'] = $ownerId;
        } elseif ($ownerId > 0) {
            $sql .= ' AND user_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }

        if ($approvedOnly && !($ownerId > 0 && $includeGlobal)) {
            $sql .= ' AND statut = :approved_status';
            $params[':approved_status'] = 'approuve';
        }

        $statusFilter = trim((string) ($filters['statut'] ?? $filters['status'] ?? ''));
        if ($statusFilter !== '' && $statusFilter !== 'toutes') {
            $sql .= ' AND statut = :status_filter';
            $params[':status_filter'] = strtolower($statusFilter);
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeBrainstormingRow($row), $rows);
    }

    public function getBrainstormingById(int $id, int $ownerId = 0, bool $isAdmin = false): ?array
    {
        $this->ensureSchema();

        $sql = 'SELECT id, titre, description, categorie, dateCreation, statut, user_id, vote_start, vote_end FROM brainstorming WHERE id = :id';
        $params = [':id' => $id];

        if (!$isAdmin && $ownerId > 0) {
            $sql .= ' AND user_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeBrainstormingRow($row) : null;
    }

    public function getBrainstormingForViewing(int $id, int $viewerId = 0, bool $isAdmin = false): ?array
    {
        $this->ensureSchema();

        $sql = 'SELECT id, titre, description, categorie, dateCreation, statut, user_id, vote_start, vote_end FROM brainstorming WHERE id = :id';
        $params = [':id' => $id];

        if (!$isAdmin) {
            if ($viewerId > 0) {
                $sql .= ' AND (user_id = :viewer_id OR statut = :approved_status)';
                $params[':viewer_id'] = $viewerId;
                $params[':approved_status'] = 'approuve';
            } else {
                $sql .= ' AND statut = :approved_status';
                $params[':approved_status'] = 'approuve';
            }
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeBrainstormingRow($row) : null;
    }

    public function updateBrainstorming(int $id, string $titre, string $description, string $categorie, int $ownerId = 0, bool $isAdmin = false): bool
    {
        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $this->ensureSchema();

        $sql = 'UPDATE brainstorming SET titre = :titre, description = :description, categorie = :categorie WHERE id = :id';
        $params = [
            ':titre' => trim($titre),
            ':description' => trim($description),
            ':categorie' => trim($categorie),
            ':id' => $id
        ];

        if (!$isAdmin && $ownerId > 0) {
            $sql .= ' AND user_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteBrainstorming(int $id, int $ownerId = 0, bool $isAdmin = false): bool
    {
        $this->ensureSchema();

        if (!$isAdmin && $ownerId > 0 && $this->getBrainstormingById($id, $ownerId, false) === null) {
            return false;
        }

        $this->deleteRelatedRows($id);

        $sql = 'DELETE FROM brainstorming WHERE id = :id';
        $params = [':id' => $id];

        if (!$isAdmin && $ownerId > 0) {
            $sql .= ' AND user_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updateBrainstormingStatus(int $id, string $statut): bool
    {
        $this->ensureSchema();

        $normalizedStatus = strtolower(trim($statut));
        if (!in_array($normalizedStatus, self::ALLOWED_STATUTS, true)) {
            throw new InvalidArgumentException('Statut invalide.');
        }

        $stmt = $this->conn->prepare('UPDATE brainstorming SET statut = :statut WHERE id = :id');
        return $stmt->execute([
            ':statut' => $normalizedStatus,
            ':id' => $id
        ]);
    }

    private function normalizeBrainstormingRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'titre' => (string) ($row['titre'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'categorie' => (string) ($row['categorie'] ?? ''),
            'dateCreation' => (string) ($row['dateCreation'] ?? ''),
            'statut' => (string) ($row['statut'] ?? 'en attente'),
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
            'vote_start' => (string) ($row['vote_start'] ?? ''),
            'vote_end' => (string) ($row['vote_end'] ?? '')
        ];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady === true) {
            return;
        }

        $this->ensureColumn('user_id', 'INT NULL');
        $this->ensureColumn('vote_start', 'DATETIME NULL');
        $this->ensureColumn('vote_end', 'DATETIME NULL');

        self::$schemaReady = true;
    }

    private function ensureColumn(string $column, string $definition): void
    {
        $stmt = $this->conn->query("SHOW COLUMNS FROM brainstorming LIKE " . $this->conn->quote($column));
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        try {
            $this->conn->exec('ALTER TABLE brainstorming ADD COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable $exception) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM brainstorming LIKE " . $this->conn->quote($column));
            if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Impossible d\'ajouter la colonne ' . $column . ' sur brainstorming.');
            }
        }
    }

    private function deleteRelatedRows(int $brainstormingId): void
    {
        try {
            $stmt = $this->conn->prepare('DELETE FROM vote WHERE idee_id IN (SELECT id FROM ideas WHERE brainstorming_id = :id)');
            $stmt->execute([':id' => $brainstormingId]);
        } catch (Throwable $exception) {
        }

        try {
            $stmt = $this->conn->prepare('DELETE FROM ideas WHERE brainstorming_id = :id');
            $stmt->execute([':id' => $brainstormingId]);
        } catch (Throwable $exception) {
        }
    }
}
