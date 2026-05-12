<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Service.php';

class ServiceC {
    private ?string $serviceTable = null;

    private function tableExists(PDO $db, string $tableName): bool
    {
        $stmt = $db->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function ensureServiceTable(PDO $db): string
    {
        if ($this->serviceTable !== null) {
            return $this->serviceTable;
        }

        if ($this->tableExists($db, 'service')) {
            $this->serviceTable = 'service';
            return $this->serviceTable;
        }

        if ($this->tableExists($db, 'services')) {
            $this->serviceTable = 'services';
            return $this->serviceTable;
        }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS service (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->serviceTable = 'service';
        return $this->serviceTable;
    }

    private function seedDefaultServices(PDO $db, string $serviceTable): void
    {
        $countStmt = $db->query("SELECT COUNT(*) FROM {$serviceTable}");
        $count = (int) ($countStmt ? $countStmt->fetchColumn() : 0);
        if ($count > 0) {
            return;
        }

        $defaults = [
            ['Demande administrative', 'Accompagnement pour vos dossiers et formalites administratives.'],
            ['Rendez-vous social', 'Prise de rendez-vous pour les demandes sociales et familiales.'],
            ['Support technique', 'Assistance technique et resolution des problemes numeriques.'],
            ['Reclamation citoyenne', 'Depot et suivi de vos reclamations aupres des services concernes.'],
        ];

        $insert = $db->prepare("INSERT INTO {$serviceTable} (nom, description) VALUES (:nom, :description)");
        foreach ($defaults as [$nom, $description]) {
            $insert->execute([
                'nom' => $nom,
                'description' => $description,
            ]);
        }
    }

    private function mapToService($row) {
        if (!$row) return null;
        return new Service(
            (int)$row['id'],
            $row['nom'],
            $row['description']
        );
    }

    public function listServices($search = '') {
        $db = Config::getConnexion();
        try {
            $serviceTable = $this->ensureServiceTable($db);
            $this->seedDefaultServices($db, $serviceTable);
            $sql = "SELECT * FROM {$serviceTable} WHERE 1=1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (nom LIKE :search OR description LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY nom ASC";
            $query = $db->prepare($sql);
            $query->execute($params);
            $liste = [];
            while ($row = $query->fetch()) {
                $liste[] = $this->mapToService($row);
            }
            return $liste;
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function addService($service) {
        $db = Config::getConnexion();
        try {
            $serviceTable = $this->ensureServiceTable($db);
            $sql = "INSERT INTO {$serviceTable} (nom, description) 
                    VALUES (:nom, :description)";
            $query = $db->prepare($sql);
            $query->execute([
                'nom' => $service->getNom(),
                'description' => $service->getDescription(),
            ]);
        } catch (Exception $e) {
            throw new Exception('Erreur SQL : ' . $e->getMessage());
        }
    }

    public function deleteService($id) {
        $db = Config::getConnexion();
        try {
            $serviceTable = $this->ensureServiceTable($db);
            $sql = "DELETE FROM {$serviceTable} WHERE id = :id";
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function updateService($service, $id) {
        $db = Config::getConnexion();
        try {
            $serviceTable = $this->ensureServiceTable($db);
            $sql = "UPDATE {$serviceTable} SET 
                        nom = :nom, 
                        description = :description
                    WHERE id = :id";
            $query = $db->prepare($sql);
            $query->execute([
                'nom' => $service->getNom(),
                'description' => $service->getDescription(),
                'id' => $id,
            ]);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getServiceById($id) {
        $db = Config::getConnexion();
        try {
            $serviceTable = $this->ensureServiceTable($db);
            $sql = "SELECT * FROM {$serviceTable} WHERE id = :id";
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            return $this->mapToService($row);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}
?>
