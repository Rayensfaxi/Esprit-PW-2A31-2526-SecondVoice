<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/brainstorming.php';

class BrainstormingController
{
    private PDO $conn;

    private const ALLOWED_STATUTS = ['en attente', 'approuvé', 'désapprouvé'];

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

    public function getBrainstormings(array $filters = []): array
    {
        $sql = 'SELECT id, titre, description, categorie, dateCreation, statut FROM brainstorming WHERE 1=1';
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

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeBrainstormingRow($row), $rows);
    }

    public function getBrainstormingById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, titre, description, categorie, dateCreation, statut FROM brainstorming WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeBrainstormingRow($row) : null;
    }

    public function updateBrainstorming(int $id, string $titre, string $description, string $categorie): bool
    {
        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $sql = 'UPDATE brainstorming SET titre = :titre, description = :description, categorie = :categorie WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':titre' => trim($titre),
            ':description' => trim($description),
            ':categorie' => trim($categorie),
            ':id' => $id
        ]);
    }

    public function deleteBrainstorming(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM brainstorming WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function normalizeBrainstormingRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'titre' => (string) ($row['titre'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'categorie' => (string) ($row['categorie'] ?? ''),
            'dateCreation' => (string) ($row['dateCreation'] ?? ''),
            'statut' => (string) ($row['statut'] ?? 'en attente')
        ];
    }
}