<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Service.php';

class ServiceC {
    private function mapToService($row) {
        if (!$row) return null;
        return new Service(
            (int)$row['id'],
            $row['nom'],
            $row['description']
        );
    }

    public function listServices($search = '') {
        $sql = "SELECT * FROM service WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (nom LIKE :search OR description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY nom ASC";

        $db = Config::getConnexion();
        try {
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
        $sql = "INSERT INTO service (nom, description) 
                VALUES (:nom, :description)";
        $db = Config::getConnexion();
        try {
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
        $sql = "DELETE FROM service WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function updateService($service, $id) {
        $sql = "UPDATE service SET 
                    nom = :nom, 
                    description = :description
                WHERE id = :id";
        $db = Config::getConnexion();
        try {
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
        $sql = "SELECT * from service where id = :id";
        $db = Config::getConnexion();
        try {
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
