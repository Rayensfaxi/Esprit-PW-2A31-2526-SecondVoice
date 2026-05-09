<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/BrainstormingController.php';
require_once __DIR__ . '/IdeaController.php';

class ExportController
{
    private PDO $conn;

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            return;
        }

        $configConnection = Config::getConnexion();
        if ($configConnection instanceof PDO) {
            $this->conn = $configConnection;
            return;
        }

        throw new RuntimeException('Connexion base de donnees indisponible.');
    }

    public function exportBrainstormingsToExcel(): void
    {
        new BrainstormingController($this->conn);
        new IdeaController($this->conn);

        $stmt = $this->conn->query(
            "SELECT b.id, b.titre, b.description, b.categorie, b.dateCreation, b.statut,
                    b.vote_start, b.vote_end,
                    COUNT(i.id) AS idea_count,
                    COALESCE(SUM(i.likes), 0) AS like_count,
                    COALESCE(SUM(i.dislikes), 0) AS dislike_count
             FROM brainstorming b
             LEFT JOIN ideas i ON i.brainstorming_id = b.id
             GROUP BY b.id, b.titre, b.description, b.categorie, b.dateCreation, b.statut, b.vote_start, b.vote_end
             ORDER BY b.id DESC"
        );

        $brainstormings = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="brainstormings_export.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Impossible de generer le fichier export.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Titre', 'Description', 'Categorie', 'Date creation', 'Statut', 'Debut vote', 'Fin vote', 'Nombre idees', 'Likes', 'Dislikes'], ';');

        foreach ($brainstormings as $brainstorming) {
            fputcsv(
                $output,
                [
                    (int) ($brainstorming['id'] ?? 0),
                    (string) ($brainstorming['titre'] ?? ''),
                    (string) ($brainstorming['description'] ?? ''),
                    (string) ($brainstorming['categorie'] ?? ''),
                    (string) ($brainstorming['dateCreation'] ?? ''),
                    (string) ($brainstorming['statut'] ?? ''),
                    (string) ($brainstorming['vote_start'] ?? ''),
                    (string) ($brainstorming['vote_end'] ?? ''),
                    (int) ($brainstorming['idea_count'] ?? 0),
                    (int) ($brainstorming['like_count'] ?? 0),
                    (int) ($brainstorming['dislike_count'] ?? 0)
                ],
                ';'
            );
        }

        fclose($output);
        exit;
    }
}
