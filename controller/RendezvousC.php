<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Rendezvous.php';

class RendezvousC {
    private function mapToRendezvous($row) {
        if (!$row) return null;
        return new Rendezvous(
            (int)$row['id'],
            (int)$row['id_citoyen'],
            isset($row['service_id']) ? (int)$row['service_id'] : null,
            isset($row['service_nom']) ? $row['service_nom'] : 'Service inconnu',
            $row['assistant'],
            new DateTime($row['date_rdv']),
            $row['heure_rdv'],
            $row['mode'],
            $row['remarques'],
            $row['statut']
        );
    }

    public function listRendezvous($search = '', $filterStatus = '') {
        $sql = "SELECT r.*, s.nom as service_nom FROM rendezvous r LEFT JOIN service s ON r.service_id = s.id WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (s.nom LIKE :search OR r.assistant LIKE :search OR CAST(r.id_citoyen AS CHAR) LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filterStatus)) {
            $sql .= " AND r.statut = :status";
            $params['status'] = $filterStatus;
        }

        $sql .= " ORDER BY r.date_rdv DESC, r.heure_rdv DESC";

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

    public function listRendezvousByCitoyen($id_citoyen, $search = '', $filterStatus = '', $sortBy = 'date_desc') {
        $sql = "SELECT r.*, s.nom as service_nom 
                FROM rendezvous r 
                LEFT JOIN service s ON r.service_id = s.id 
                WHERE r.id_citoyen = :id_citoyen";
        
        $params = ['id_citoyen' => $id_citoyen];

        if (!empty($search)) {
            $sql .= " AND (s.nom LIKE :search OR r.assistant LIKE :search OR r.remarques LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filterStatus)) {
            $sql .= " AND r.statut = :status";
            $params['status'] = $filterStatus;
        }

        switch ($sortBy) {
            case 'date_asc':
                $sql .= " ORDER BY r.date_rdv ASC, r.heure_rdv ASC";
                break;
            case 'service_asc':
                $sql .= " ORDER BY service_nom ASC";
                break;
            case 'service_desc':
                $sql .= " ORDER BY service_nom DESC";
                break;
            case 'date_desc':
            default:
                $sql .= " ORDER BY r.date_rdv DESC, r.heure_rdv DESC";
                break;
        }

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
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function addRendezvous($rendezvous)
    {
        $sql = "INSERT INTO rendezvous (id_citoyen, service_id, assistant, date_rdv, heure_rdv, mode, remarques, statut) 
                VALUES (:id_citoyen, :service_id, :assistant, :date_rdv, :heure_rdv, :mode, :remarques, :statut)";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'id_citoyen' => $rendezvous->getIdCitoyen(),
                'service_id' => $rendezvous->getServiceId(),
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
                    service_id = :service_id,
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
                'service_id' => $rendezvous->getServiceId(),
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
        $sql = "SELECT r.*, s.nom as service_nom 
                FROM rendezvous r 
                LEFT JOIN service s ON r.service_id = s.id 
                WHERE r.id = :id";
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
        $sql = "SELECT r.*, s.nom as service_nom 
                FROM rendezvous r 
                LEFT JOIN service s ON r.service_id = s.id 
                ORDER BY r.id DESC LIMIT :limit";
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

    public function getRendezvousStatsByStatus($startDate = null, $endDate = null) {
        $sql = "SELECT statut, COUNT(*) as count FROM rendezvous WHERE 1=1";
        $params = [];
        if ($startDate) {
            $sql .= " AND date_rdv >= :start";
            $params['start'] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND date_rdv <= :end";
            $params['end'] = $endDate;
        }
        $sql .= " GROUP BY statut";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getRendezvousStatsByService($startDate = null, $endDate = null) {
        $sql = "SELECT s.nom as service_nom, COUNT(r.id) as count 
                FROM service s 
                LEFT JOIN rendezvous r ON s.id = r.service_id";
        $params = [];
        if ($startDate || $endDate) {
            $sql .= " AND 1=1";
            if ($startDate) {
                $sql .= " AND r.date_rdv >= :start";
                $params['start'] = $startDate;
            }
            if ($endDate) {
                $sql .= " AND r.date_rdv <= :end";
                $params['end'] = $endDate;
            }
        }
        $sql .= " GROUP BY s.id, s.nom";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getRendezvousStatsByDate($period = 'day', $startDate = null, $endDate = null) {
        $dateFormat = ($period === 'month') ? '%Y-%m' : '%Y-%m-%d';
        $sql = "SELECT DATE_FORMAT(date_rdv, '$dateFormat') as date_label, COUNT(*) as count 
                FROM rendezvous 
                WHERE 1=1";
        $params = [];
        if ($startDate) {
            $sql .= " AND date_rdv >= :start";
            $params['start'] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND date_rdv <= :end";
            $params['end'] = $endDate;
        }
        $sql .= " GROUP BY date_label ORDER BY date_label ASC";
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getGlobalStats($startDate = null, $endDate = null) {
        $sql = "SELECT COUNT(*) as total,
                SUM(CASE WHEN statut = 'Confirmé' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN statut = 'Annulé' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN statut = 'En attente' THEN 1 ELSE 0 END) as pending
                FROM rendezvous WHERE 1=1";
        $params = [];
        if ($startDate) {
            $sql .= " AND date_rdv >= :start";
            $params['start'] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND date_rdv <= :end";
            $params['end'] = $endDate;
        }
        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            return $query->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function getCalendarData($serviceId = null) {
        $sql = "SELECT r.*, s.nom as service_nom 
                FROM rendezvous r 
                LEFT JOIN service s ON r.service_id = s.id 
                WHERE 1=1";
        $params = [];

        if ($serviceId) {
            $sql .= " AND r.service_id = :service_id";
            $params['service_id'] = $serviceId;
        }

        $db = Config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            $events = [];
            while ($row = $query->fetch()) {
                $color = '#ffb84d'; // Default En attente
                if ($row['statut'] == 'Confirmé') $color = '#31d0aa';
                elseif ($row['statut'] == 'Annulé') $color = '#ff6b6b';

                $events[] = [
                    'id' => $row['id'],
                    'title' => ($row['service_nom'] ?? 'Service') . ' - ' . $row['assistant'],
                    'start' => $row['date_rdv'] . 'T' . $row['heure_rdv'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'statut' => $row['statut'],
                        'assistant' => $row['assistant'],
                        'mode' => $row['mode'],
                        'remarques' => $row['remarques'],
                        'id_citoyen' => $row['id_citoyen']
                    ]
                ];
            }
            return $events;
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    public function generateQRCode($id) {
        $rdv = $this->getRendezvousById($id);
        if (!$rdv) return null;

        $libPath = dirname(__DIR__) . '/lib/phpqrcode/qrlib.php';
        if (!file_exists($libPath)) {
            return null;
        }

        require_once $libPath;

        $qrDir = dirname(__DIR__) . '/view/backend/assets/qrcodes/';
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0777, true);
        }

        $fileName = 'rdv_' . $id . '.svg';
        $filePath = $qrDir . $fileName;

        // URL de redirection pour le scan
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        
        $url = $protocol . "://" . $host . "/Esprit-PW-2A31-2526-SecondVoice/view/backend/Rendezvous/viewQRCode.php?id=" . $id;

        $content = "RDV #" . $id . "\n";
        $content .= "Service: " . $rdv->getService() . "\n";
        $content .= "Date: " . $rdv->getDateRdv()->format('d/m/Y') . "\n";
        $content .= "Heure: " . $rdv->getHeureRdv() . "\n";
        $content .= "Lien: " . $url;

        try {
            // Utilisation de SVG qui ne nécessite pas l'extension GD
            QRcode::svg($content, $filePath, QR_ECLEVEL_L, 4, 2);
        } catch (Exception $e) {
            return null;
        }

        return '../assets/qrcodes/' . $fileName;
    }
}
?>
