<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/model/Rendezvous.php';

class RendezvousC {
    private function tableExists(PDO $db, string $tableName): bool
    {
        $stmt = $db->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function ensureSchema(PDO $db): void
    {
        if (!$this->tableExists($db, 'service')) {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS service (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nom VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            if ($this->tableExists($db, 'services')) {
                $db->exec(
                    "INSERT INTO service (id, nom, description)
                     SELECT s.id, s.nom, s.description
                     FROM services s
                     LEFT JOIN service t ON t.id = s.id
                     WHERE t.id IS NULL"
                );
            }
        }

        if (!$this->tableExists($db, 'rendezvous')) {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS rendezvous (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_citoyen INT NOT NULL,
                    service_id INT NULL,
                    assistant VARCHAR(255) NOT NULL,
                    date_rdv DATE NOT NULL,
                    heure_rdv TIME NOT NULL,
                    mode VARCHAR(100) NOT NULL,
                    remarques TEXT NULL,
                    statut VARCHAR(50) NOT NULL DEFAULT 'En attente',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_rdv_citoyen (id_citoyen),
                    INDEX idx_rdv_service (service_id),
                    INDEX idx_rdv_date (date_rdv)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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
            $this->ensureSchema($db);
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

    private function isLocalWebHost(string $host): bool
    {
        $host = strtolower(trim($host, "[] \t\n\r\0\x0B"));
        return $host === 'localhost' || $host === '::1' || preg_match('/^127(?:\.\d{1,3}){3}$/', $host) === 1;
    }

    private function isUsableIpv4(string $ip): bool
    {
        $ip = trim($ip);
        if (strpos($ip, ':') !== false && preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches) === 1) {
            $ip = $matches[1];
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && preg_match('/^(?:0|127)\./', $ip) !== 1
            && $ip !== '255.255.255.255';
    }

    private function isPrivateIpv4(string $ip): bool
    {
        return preg_match('/^(?:10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[0-1])\.)/', $ip) === 1;
    }

    private function detectLanIpv4(): string
    {
        $candidates = [];
        $addCandidate = function ($value) use (&$candidates): void {
            $ip = trim((string) $value);
            if (strpos($ip, ':') !== false && preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches) === 1) {
                $ip = $matches[1];
            }

            if ($this->isUsableIpv4($ip) && !in_array($ip, $candidates, true)) {
                $candidates[] = $ip;
            }
        };

        $socket = @stream_socket_client('udp://8.8.8.8:80', $errno, $error, 0.2);
        if (is_resource($socket)) {
            $localSocketName = stream_socket_get_name($socket, false);
            fclose($socket);
            if (is_string($localSocketName)) {
                $addCandidate($localSocketName);
            }
        }

        foreach (['LOCAL_ADDR', 'SERVER_ADDR'] as $serverKey) {
            $addCandidate($_SERVER[$serverKey] ?? '');
        }

        if (function_exists('gethostname')) {
            $hostname = (string) gethostname();
            if ($hostname !== '') {
                $addCandidate(gethostbyname($hostname));
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->isPrivateIpv4($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? '';
    }

    private function requestHostName(string $hostHeader): string
    {
        return (string) (parse_url('http://' . $hostHeader, PHP_URL_HOST) ?: $hostHeader);
    }

    private function requestHostPort(string $hostHeader, string $scheme): string
    {
        $port = parse_url('http://' . $hostHeader, PHP_URL_PORT);
        if ($port === null) {
            $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
            if ($serverPort > 0 && !(($scheme === 'http' && $serverPort === 80) || ($scheme === 'https' && $serverPort === 443))) {
                $port = $serverPort;
            }
        }

        return $port !== null ? ':' . (string) $port : '';
    }

    private function encodeUrlPath(string $path): string
    {
        $segments = explode('/', $path);
        $segments = array_map(static function (string $segment): string {
            return rawurlencode(rawurldecode($segment));
        }, $segments);

        return implode('/', $segments);
    }

    private function rendezvousPublicBaseUrl(): string
    {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $hostName = $this->requestHostName($host);

        if ($this->isLocalWebHost($hostName)) {
            $lanIp = $this->detectLanIpv4();
            if ($lanIp !== '') {
                $host = $lanIp . $this->requestHostPort($host, $scheme);
            }
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = '';
        $viewPos = strpos($scriptName, '/view/');
        if ($viewPos !== false) {
            $basePath = substr($scriptName, 0, $viewPos);
        }

        return $scheme . '://' . $host . $this->encodeUrlPath($basePath);
    }

    public function getRendezvousPublicUrl(int $id): string
    {
        return $this->rendezvousPublicBaseUrl() . '/view/backoffice/Rendezvous/viewQRCode.php?id=' . rawurlencode((string) $id);
    }

    public function generateQRCode($id) {
        $rdv = $this->getRendezvousById($id);
        if (!$rdv) return null;

        $libPath = dirname(__DIR__) . '/lib/phpqrcode/qrlib.php';
        if (!file_exists($libPath)) {
            return null;
        }

        require_once $libPath;

        $qrDir = dirname(__DIR__) . '/view/backoffice/assets/qrcodes/';
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0777, true);
        }

        $fileName = 'rdv_' . $id . '.svg';
        $filePath = $qrDir . $fileName;

        $url = $this->getRendezvousPublicUrl((int) $id);

        try {
            // Utilisation de SVG qui ne nécessite pas l'extension GD
            QRcode::svg($url, $filePath, QR_ECLEVEL_L, 4, 2);
        } catch (Throwable $e) {
            return null;
        }

        return '../assets/qrcodes/' . $fileName;
    }
}
?>
