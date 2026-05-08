<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/brainstorming.php';

class BrainstormingController
{
    private PDO $conn;
    private static ?bool $hasUserIdColumn = null;

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

        $this->ensureUserIdColumn();

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

        $this->ensureUserIdColumn();

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
        $this->ensureUserIdColumn();

        $sql = 'SELECT id, titre, description, categorie, dateCreation, statut, user_id FROM brainstorming WHERE 1=1';
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

        if ($globalOnly) {
            $sql .= ' AND user_id IS NULL';
        } elseif ($ownerId > 0 && $includeGlobal) {
            $sql .= ' AND (user_id = :owner_id OR user_id IS NULL)';
            $params[':owner_id'] = $ownerId;
        } elseif ($ownerId > 0) {
            $sql .= ' AND user_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeBrainstormingRow($row), $rows);
    }

    public function getBrainstormingById(int $id, int $ownerId = 0, bool $isAdmin = false): ?array
    {
        $this->ensureUserIdColumn();

        $sql = 'SELECT id, titre, description, categorie, dateCreation, statut, user_id FROM brainstorming WHERE id = :id';
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

    public function updateBrainstorming(int $id, string $titre, string $description, string $categorie, int $ownerId = 0, bool $isAdmin = false): bool
    {
        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $this->ensureUserIdColumn();

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
        $this->ensureUserIdColumn();

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
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0
        ];
    }

    private function hasUserIdColumn(): bool
    {
        if (self::$hasUserIdColumn !== null) {
            return self::$hasUserIdColumn;
        }

        $stmt = $this->conn->query("SHOW COLUMNS FROM brainstorming LIKE 'user_id'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        self::$hasUserIdColumn = $row !== false;

        return self::$hasUserIdColumn;
    }

    private function ensureUserIdColumn(): void
    {
        if ($this->hasUserIdColumn()) {
            return;
        }

        try {
            $this->conn->exec('ALTER TABLE brainstorming ADD COLUMN user_id INT NULL');
            self::$hasUserIdColumn = true;
        } catch (Throwable $exception) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM brainstorming LIKE 'user_id'");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            self::$hasUserIdColumn = $row !== false;
            if (!self::$hasUserIdColumn) {
                throw new RuntimeException('Impossible d\'ajouter la colonne user_id sur brainstorming.');
            }
        }
    }
}
