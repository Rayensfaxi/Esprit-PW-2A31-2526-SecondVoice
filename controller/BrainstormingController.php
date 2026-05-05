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

    public function addBrainstorming(string $titre, string $description, string $categorie, bool $isAdmin = false): int
    {
        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $statut = $isAdmin ? 'approuve' : 'en attente';

        $brainstorming = new Brainstorming(
            trim($titre),
            trim($description),
            trim($categorie),
            date('Y-m-d'),
            $statut
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

    public function addBrainstormingForUser(string $titre, string $description, string $categorie, int $userId, bool $isAdmin = false): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Utilisateur invalide.');
        }

        if (empty(trim($titre))) {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $statut = $isAdmin ? 'approuve' : 'en attente';

        $this->ensureUserIdColumn();

        $brainstorming = new Brainstorming(
            trim($titre),
            trim($description),
            trim($categorie),
            date('Y-m-d'),
            $statut
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

        $statutFilter = trim((string) ($filters['statut'] ?? ''));
        if ($statutFilter !== '' && $statutFilter !== 'toutes') {
            $sql .= ' AND statut = :statut';
            $params[':statut'] = $statutFilter;
        }

        $ownerId = (int) ($filters['owner_id'] ?? 0);
        $includeGlobal = !empty($filters['include_global']);
        $globalOnly = !empty($filters['global_only']);
        $approvedOnly = !empty($filters['approved_only']);

        if ($approvedOnly) {
            $sql .= ' AND statut = "approuve"';
        }

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

    public function getBrainstormingStatistics() {
        $stats = [];

        // Total brainstormings
        $stmt = $this->conn->query("SELECT COUNT(*) as cnt FROM brainstorming");
        $count = ($stmt) ? $stmt->fetchColumn(0) : 0;
        $stats['total_brainstormings'] = (int) $count;

        // Total ideas
        $stmt = $this->conn->query("SELECT COUNT(*) as cnt FROM ideas");
        $count = ($stmt) ? $stmt->fetchColumn(0) : 0;
        $stats['total_ideas'] = (int) $count;

        // Approved brainstormings
        $stmt = $this->conn->query("SELECT COUNT(*) as cnt FROM brainstorming WHERE statut = 'approuve'");
        $count = ($stmt) ? $stmt->fetchColumn(0) : 0;
        $stats['approved_brainstormings'] = (int) $count;

        // Pending brainstormings
        $stmt = $this->conn->query("SELECT COUNT(*) as cnt FROM brainstorming WHERE statut = 'en attente'");
        $count = ($stmt) ? $stmt->fetchColumn(0) : 0;
        $stats['pending_brainstormings'] = (int) $count;

        // Line chart data - Brainstormings per month
        $stmt = $this->conn->query("SELECT DATE_FORMAT(dateCreation, '%Y-%m') AS month, COUNT(*) AS count FROM brainstorming GROUP BY DATE_FORMAT(dateCreation, '%Y-%m') ORDER BY month DESC LIMIT 6");
        $brainstormings = ($stmt) ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        // Line chart data - Ideas per month
        $stmt = $this->conn->query("SELECT DATE_FORMAT(date_creation, '%Y-%m') AS month, COUNT(*) AS count FROM ideas GROUP BY DATE_FORMAT(date_creation, '%Y-%m') ORDER BY month DESC LIMIT 6");
        $ideas = ($stmt) ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $stats['line_chart_data'] = [
            'labels' => array_keys($brainstormings),
            'brainstormings' => array_values($brainstormings),
            'ideas' => array_values($ideas)
        ];

        // Bar chart data
        $stmt = $this->conn->query("SELECT categorie, COUNT(*) AS count FROM brainstorming GROUP BY categorie ORDER BY count DESC");
        $categories = ($stmt) ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $stats['bar_chart_data'] = [
            'labels' => array_keys($categories),
            'data' => array_values($categories)
        ];

        // Top brainstormings
        $stmt = $this->conn->query("SELECT b.titre, COUNT(i.id) AS idea_count FROM brainstorming b LEFT JOIN ideas i ON b.id = i.brainstorming_id GROUP BY b.id ORDER BY idea_count DESC LIMIT 3");
        $stats['top_brainstormings'] = ($stmt) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $stats;
    }

    public function exportToExcel(): void
    {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new RuntimeException('PhpSpreadsheet library not found.');
            }

            $stmt = $this->conn->query("SELECT b.id, b.titre, b.description, b.categorie, b.dateCreation, b.statut, COUNT(i.id) AS idea_count FROM brainstorming b LEFT JOIN ideas i ON b.id = i.brainstorming_id GROUP BY b.id ORDER BY b.id DESC");
            $brainstormings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set header row
            $sheet->setCellValue('A1', 'Titre');
            $sheet->setCellValue('B1', 'Description');
            $sheet->setCellValue('C1', 'Categorie');
            $sheet->setCellValue('D1', 'Date Creation');
            $sheet->setCellValue('E1', 'Statut');
            $sheet->setCellValue('F1', 'Nombre d\'ideass');

            // Style header row
            $headerStyle = [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '635BFF'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];

            foreach (range('A', 'F') as $columnID) {
                $sheet->getStyle($columnID . '1')->applyFromArray($headerStyle);
            }

            // Populate data rows
            $row = 2;
            foreach ($brainstormings as $brainstorming) {
                $sheet->setCellValue("A$row", $brainstorming['titre']);
                $sheet->setCellValue("B$row", substr($brainstorming['description'], 0, 100));
                $sheet->setCellValue("C$row", $brainstorming['categorie']);
                $sheet->setCellValue("D$row", $brainstorming['dateCreation']);
                $sheet->setCellValue("E$row", $brainstorming['statut']);
                $sheet->setCellValue("F$row", $brainstorming['idea_count']);
                $row++;
            }

            // Auto-adjust column widths
            foreach (range('A', 'F') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Write to file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $fileName = 'brainstormings_export_' . date('Y_m_d_H_i_s') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $writer->save('php://output');
            exit;
        } catch (Throwable $exception) {
            throw new RuntimeException('Erreur lors de l\'export Excel: ' . $exception->getMessage());
        }
    }
}
