<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Rendezvous.php';

class RendezvousC {
    private function mapToRendezvous($row) {
        if (!$row) return null;
        return new Rendezvous(
            (int)$row['id'],
            (int)$row['id_citoyen'],
            $row['service'],
            $row['assistant'],
            new DateTime($row['date_rdv']),
            $row['heure_rdv'],
            $row['mode'],
            $row['remarques'],
            $row['statut']
        );
    }

    public function listRendezvous($search = '', $filterStatus = '') {
        $sql = "SELECT * FROM rendezvous WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (service LIKE :search OR assistant LIKE :search OR CAST(id_citoyen AS CHAR) LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filterStatus)) {
            $sql .= " AND statut = :status";
            $params['status'] = $filterStatus;
        }

        $sql .= " ORDER BY date_rdv DESC, heure_rdv DESC";

        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            $liste = [];
            while ($row = $query->fetch()) {
                $liste[] = $this->mapToRendezvous($row);
            }
            return $liste;
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function listRendezvousByCitoyen($id_citoyen) {
        $sql = "SELECT * FROM rendezvous WHERE id_citoyen = :id_citoyen ORDER BY date_rdv DESC";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id_citoyen' => $id_citoyen]);
            $liste = [];
            while ($row = $query->fetch()) {
                $liste[] = $this->mapToRendezvous($row);
            }
            return $liste;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function addRendezvous($rendezvous)
    {
        $sql = "INSERT INTO rendezvous (id_citoyen, service, assistant, date_rdv, heure_rdv, mode, remarques, statut) 
                VALUES (:id_citoyen, :service, :assistant, :date_rdv, :heure_rdv, :mode, :remarques, :statut)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id_citoyen' => $rendezvous->getIdCitoyen(),
                'service' => $rendezvous->getService(),
                'assistant' => $rendezvous->getAssistant(),
                'date_rdv' => $rendezvous->getDateRdv()->format('Y-m-d'),
                'heure_rdv' => $rendezvous->getHeureRdv(),
                'mode' => $rendezvous->getMode(),
                'remarques' => $rendezvous->getRemarques(),
                'statut' => $rendezvous->getStatut(),
            ]);
        } catch (Exception $e) {
            throw new Exception('Erreur SQL : ' . $e->getMessage());
        }
    }

    public function deleteRendezvous($id) {
        $sql = "DELETE FROM rendezvous WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id' => $id,
            ]);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function updateRendezvous($rendezvous, $id)
    {
        $sql = "UPDATE rendezvous SET 
                    service = :service, 
                    assistant = :assistant, 
                    date_rdv = :date_rdv, 
                    heure_rdv = :heure_rdv, 
                    mode = :mode, 
                    remarques = :remarques,
                    statut = :statut
                WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'service' => $rendezvous->getService(),
                'assistant' => $rendezvous->getAssistant(),
                'date_rdv' => $rendezvous->getDateRdv()->format('Y-m-d'),
                'heure_rdv' => $rendezvous->getHeureRdv(),
                'mode' => $rendezvous->getMode(),
                'remarques' => $rendezvous->getRemarques(),
                'statut' => $rendezvous->getStatut(),
                'id' => $id,
            ]);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getRendezvousById($id) {
        $sql = "SELECT * from rendezvous where id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            return $this->mapToRendezvous($row);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function updateStatut($id, $statut)
    {
        $sql = "UPDATE rendezvous SET statut = :statut WHERE id = :id";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'statut' => $statut,
                'id' => $id,
            ]);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function countRendezvous() {
        $sql = "SELECT COUNT(*) as total FROM rendezvous";
        $db = Config::getConnexion();
        try {
            $query = $db->query($sql);
            $result = $query->fetch();
            return $result['total'];
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function getRecentActivity($limit = 5) {
        $sql = "SELECT * FROM rendezvous ORDER BY id DESC LIMIT :limit";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $query->execute();
            $liste = [];
            while ($row = $query->fetch()) {
                $liste[] = $this->mapToRendezvous($row);
            }
            return $liste;
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getRendezvousStatsByStatus() {
        $sql = "SELECT statut, COUNT(*) as count FROM rendezvous GROUP BY statut";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            return $query->fetchAll();
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }
}
?>
