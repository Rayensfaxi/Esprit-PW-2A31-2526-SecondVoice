<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';

class EventController
{
    private PDO $conn;

    private const ALLOWED_STATUSES = ['en cours', 'validé', 'refusé', 'annulé'];
    private const ALLOWED_RESOURCE_TYPES = ['materiel', 'regle'];

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

        $this->conn = Config::getConnexion();
    }

    public function showEvents(): void
    {
        $events = $this->getValidatedEvents();
        include __DIR__ . '/../view/frontoffice/events.php';
    }

    public function getAllEvents(): array
    {
        $stmt = $this->conn->prepare("SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by FROM events ORDER BY start_date ASC, id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getValidatedEvents(): array
    {
        $stmt = $this->conn->prepare("SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by FROM events WHERE status = 'validé' ORDER BY start_date ASC, id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPendingEvents(): array
    {
        $stmt = $this->conn->prepare("SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by FROM events WHERE status = 'en cours' ORDER BY start_date ASC, id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getEventsByCreator(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by, created_at FROM events WHERE created_by = ? ORDER BY created_at DESC, id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getEventById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by FROM events WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getResourcesByEvent(int $eventId): array
    {
        $stmt = $this->conn->prepare('SELECT id, event_id, resources_title, resources_description, name, description, quantity, type FROM event_resources WHERE event_id = ? ORDER BY type ASC, id ASC');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getImportableResourceEventsForUser(int $userId, int $excludeEventId = 0): array
    {
        if ($userId <= 0 || !$this->userExists($userId)) {
            return [];
        }

        $sql = "
            SELECT e.id, e.name, e.start_date, e.location
            FROM events e
            WHERE e.created_by = ?
        ";
        $params = [$userId];

        if ($excludeEventId > 0) {
            $sql .= ' AND e.id <> ?';
            $params[] = $excludeEventId;
        }

        $sql .= ' ORDER BY e.created_at DESC, e.id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getImportableResourcesFromEvent(int $sourceEventId, int $userId): array
    {
        if ($sourceEventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        if (!$this->userExists($userId)) {
            return ['success' => false, 'message' => 'Utilisateur introuvable.'];
        }

        $event = $this->getEventById($sourceEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement source introuvable.'];
        }

        if ((int) ($event['created_by'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Vous ne pouvez importer que les ressources de vos propres événements.'];
        }

        $resources = $this->getResourcesByEvent($sourceEventId);
        if ($resources === []) {
            return ['success' => false, 'message' => 'Aucune ressource à importer pour cet événement'];
        }

        $meta = $resources[0] ?? [];
        return [
            'success' => true,
            'resources_title' => (string) ($meta['resources_title'] ?? ''),
            'resources_description' => (string) ($meta['resources_description'] ?? ''),
            'resources' => array_map(static function (array $resource): array {
                return [
                    'name' => (string) ($resource['name'] ?? ''),
                    'quantity' => isset($resource['quantity']) ? (string) $resource['quantity'] : '',
                    'description' => (string) ($resource['description'] ?? ''),
                    'type' => (string) ($resource['type'] ?? 'materiel'),
                ];
            }, $resources),
        ];
    }

    private function userExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare('SELECT id FROM utilisateur WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, role FROM utilisateur WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    private function getAdminEmails(): array
    {
        $stmt = $this->conn->prepare("SELECT email FROM utilisateur WHERE LOWER(role) = 'admin' AND email IS NOT NULL AND email <> ''");
        $stmt->execute();

        return array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['email'] ?? ''));
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []), static function (string $email): bool {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }));
    }

    private function formatUserName(?array $user): string
    {
        if (!$user) {
            return 'Utilisateur inconnu';
        }

        $name = trim((string) ($user['prenom'] ?? '') . ' ' . (string) ($user['nom'] ?? ''));
        return $name !== '' ? $name : 'Utilisateur #' . (string) ($user['id'] ?? '');
    }

    private function sendAdminRequestEmails(string $type, int $userId, string $eventName): void
    {
        try {
            $user = $this->getUserById($userId);
            $adminEmails = $this->getAdminEmails();
            error_log('Email admin demande: type="' . $type . '", user_id=' . $userId . ', event="' . $eventName . '", admins=' . json_encode($adminEmails));
            if ($adminEmails === []) {
                error_log('Email admin non envoye: aucun admin avec email.');
            }

            $message = "Un utilisateur a envoye une nouvelle demande.\n\n"
                . 'Type : ' . $type . "\n"
                . 'Utilisateur : ' . $this->formatUserName($user) . "\n"
                . 'E-mail : ' . (string) ($user['email'] ?? '') . "\n"
                . 'Evenement : ' . $eventName . "\n"
                . 'Date de la demande : ' . date('Y-m-d H:i:s') . "\n"
                . "Statut : en cours\n";

            foreach ($adminEmails as $email) {
                error_log('Email admin: appel sendMail vers ' . $email);
                $sent = sendMail($email, 'Nouvelle demande d evenement', $message);
                error_log('Email admin: resultat sendMail vers ' . $email . ' = ' . ($sent ? 'OK' : 'ECHEC'));
            }
        } catch (Throwable $e) {
            error_log('Erreur email admin: ' . $e->getMessage());
        }
    }

    private function sendUserRequestProcessedEmail(int $userId, string $eventName, string $type, string $decision): void
    {
        try {
            $user = $this->getUserById($userId);
            $email = trim((string) ($user['email'] ?? ''));
            $decisionLabel = $decision === 'approved' ? 'validee' : 'refusee';
            $message = 'Bonjour ' . $this->formatUserName($user) . ",\n\n"
                . 'Votre demande concernant l evenement "' . $eventName . '" a ete ' . $decisionLabel . ".\n\n"
                . 'Type de demande : ' . $type . "\n"
                . 'Decision admin : ' . ($decision === 'approved' ? 'valide' : 'refuse') . "\n"
                . 'Date de traitement : ' . date('Y-m-d H:i:s') . "\n";

            if ($email === '') {
                error_log('Email utilisateur non envoye: email introuvable pour user_id=' . $userId);
                return;
            }

            error_log('Email utilisateur: appel sendMail vers ' . $email . ', user_id=' . $userId . ', decision=' . $decision);
            $sent = sendMail($email, 'Votre demande d evenement a ete traitee', $message);
            error_log('Email utilisateur: resultat sendMail vers ' . $email . ' = ' . ($sent ? 'OK' : 'ECHEC'));
        } catch (Throwable $e) {
            error_log('Erreur email utilisateur: ' . $e->getMessage());
        }
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function normalizeResourcesPayload(array $resources, ?string $resourcesTitle = null, ?string $resourcesDescription = null, bool $requireTitleWhenResourcesExist = true): array
    {
        $normalizedResources = [];
        $title = trim((string) ($resourcesTitle ?? ''));
        $description = trim((string) ($resourcesDescription ?? ''));

        foreach ($resources as $i => $resource) {
            if (!is_array($resource)) {
                return ['success' => false, 'message' => 'Le format de la ressource #' . ($i + 1) . ' est invalide.'];
            }

            $name = trim((string) ($resource['name'] ?? ''));
            $type = trim((string) ($resource['type'] ?? 'materiel'));
            $resourceDescription = trim((string) ($resource['description'] ?? ''));
            $quantityRaw = trim((string) ($resource['quantity'] ?? ''));
            $quantity = null;

            if ($name === '') {
                return ['success' => false, 'message' => 'Le nom de la ressource #' . ($i + 1) . ' est obligatoire.'];
            }

            if ($this->textLength($name) < 6) {
                return ['success' => false, 'message' => 'Le titre doit contenir au moins 6 caractères'];
            }

            if (!in_array($type, self::ALLOWED_RESOURCE_TYPES, true)) {
                return ['success' => false, 'message' => 'Le type de la ressource #' . ($i + 1) . ' est invalide.'];
            }

            if ($type === 'materiel') {
                if ($quantityRaw === '') {
                    return ['success' => false, 'message' => 'La quantité du matériel #' . ($i + 1) . ' est obligatoire.'];
                }

                if (!ctype_digit($quantityRaw) || (int) $quantityRaw <= 0) {
                    return ['success' => false, 'message' => 'La quantité du matériel #' . ($i + 1) . ' doit être un entier positif.'];
                }

                $quantity = (int) $quantityRaw;
            }

            $normalizedResources[] = [
                'id' => (int) ($resource['id'] ?? 0),
                'name' => $name,
                'description' => $resourceDescription,
                'quantity' => $quantity,
                'type' => $type,
            ];
        }

        if ($requireTitleWhenResourcesExist && $normalizedResources !== []) {
            if ($title === '') {
                return ['success' => false, 'message' => 'Ce champ est obligatoire'];
            }

            if ($this->textLength($title) < 6) {
                return ['success' => false, 'message' => 'Le titre doit contenir au moins 6 caractères'];
            }
        }

        return [
            'success' => true,
            'resources' => $normalizedResources,
            'resources_title' => $title !== '' ? $title : null,
            'resources_description' => $description !== '' ? $description : null,
        ];
    }

    private function encodeResourcesData(array $resources): array
    {
        $json = json_encode($resources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['success' => false, 'message' => 'Impossible d\'encoder resources_data en JSON: ' . json_last_error_msg()];
        }

        return ['success' => true, 'json' => $json];
    }

    private function decodeRequestedResources(array $request): array
    {
        $rawResources = $request['resources_data'] ?? $request['new_resources'] ?? '[]';

        if (is_array($rawResources)) {
            return ['success' => true, 'resources' => $rawResources];
        }

        $decodedResources = json_decode((string) $rawResources, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Le JSON resources_data est invalide: ' . json_last_error_msg()];
        }

        if ($decodedResources === null) {
            return ['success' => true, 'resources' => []];
        }

        if (isset($decodedResources['resources']) && is_array($decodedResources['resources'])) {
            $decodedResources = $decodedResources['resources'];
        }

        if (!is_array($decodedResources)) {
            return ['success' => false, 'message' => 'Le format de resources_data est invalide.'];
        }

        return ['success' => true, 'resources' => $decodedResources];
    }

    private function buildSqlErrorMessage(Throwable $e, ?PDOStatement $stmt = null): string
    {
        $parts = [$e->getMessage()];

        if ($stmt instanceof PDOStatement) {
            $errorInfo = $stmt->errorInfo();
            if (is_array($errorInfo)) {
                $filtered = array_values(array_filter($errorInfo, static fn($value): bool => $value !== null && $value !== ''));
                if ($filtered !== []) {
                    $parts[] = 'PDO errorInfo: ' . implode(' | ', array_map('strval', $filtered));
                }
            }
        }

        return implode(' || ', $parts);
    }

    private function resourceRequestTypeColumnExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM resource_modification_requests LIKE 'request_type'");
            $stmt->execute();
            $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }

    private function mapRequestStatusToDisplay(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'pending' => 'en cours',
            'approved' => 'validé',
            'rejected' => 'refusé',
            'validé', 'en cours', 'refusé', 'annulé' => $normalized,
            default => 'en cours',
        };
    }

    private function buildEventModificationSummary(array $row): string
    {
        $changes = [];

        if (($row['new_name'] ?? null) !== null && (string) ($row['new_name'] ?? '') !== (string) ($row['current_name'] ?? '')) {
            $changes[] = 'Nom';
        }
        if (($row['new_start_date'] ?? null) !== null && (string) ($row['new_start_date'] ?? '') !== (string) ($row['current_start_date'] ?? '')) {
            $changes[] = 'Début';
        }
        if (($row['new_end_date'] ?? null) !== null && (string) ($row['new_end_date'] ?? '') !== (string) ($row['current_end_date'] ?? '')) {
            $changes[] = 'Fin';
        }
        if (($row['new_deadline'] ?? null) !== null && (string) ($row['new_deadline'] ?? '') !== (string) ($row['current_deadline'] ?? '')) {
            $changes[] = 'Date limite';
        }
        if (($row['new_location'] ?? null) !== null && (string) ($row['new_location'] ?? '') !== (string) ($row['current_location'] ?? '')) {
            $changes[] = 'Lieu';
        }
        if (($row['new_max'] ?? null) !== null && (string) ($row['new_max'] ?? '') !== (string) ($row['current_max'] ?? '')) {
            $changes[] = 'Capacité';
        }

        if ($changes === []) {
            return 'Mise à jour des informations de l\'événement.';
        }

        return 'Champs proposés: ' . implode(', ', $changes) . '.';
    }

    private function buildResourceModificationSummary(array $row): string
    {
        $decodedResources = $this->decodeRequestedResources($row);
        if (!($decodedResources['success'] ?? false)) {
            return 'Mise ÃƒÂ  jour des ressources de l\'ÃƒÂ©vÃƒÂ©nement.';
        }

        $materialsCount = 0;
        $rulesCount = 0;
        foreach ($decodedResources['resources'] as $resource) {
            if (!is_array($resource)) {
                continue;
            }

            $type = trim((string) ($resource['type'] ?? 'materiel'));
            if ($type === 'regle') {
                $rulesCount++;
            } else {
                $materialsCount++;
            }
        }

        $parts = [];
        $title = trim((string) ($row['resources_title'] ?? ''));
        if ($title !== '') {
            $parts[] = 'Titre : ' . $title;
        }
        if ($materialsCount > 0) {
            $parts[] = $materialsCount . ' materiel' . ($materialsCount > 1 ? 's' : '');
        }
        if ($rulesCount > 0) {
            $parts[] = $rulesCount . ' regle' . ($rulesCount > 1 ? 's' : '');
        }

        if ($parts === []) {
            return 'Mise ÃƒÂ  jour des ressources de l\'ÃƒÂ©vÃƒÂ©nement.';
        }

        return 'Ressources proposees : ' . implode(', ', $parts) . '.';
    }

    public function getUserEventRequests(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $requests = [];

        $creationStmt = $this->conn->prepare("
            SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_at
            FROM events
            WHERE created_by = ?
            ORDER BY created_at DESC, id DESC
        ");
        $creationStmt->execute([$userId]);
        foreach ($creationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $sourceStatus = (string) ($row['status'] ?? 'en cours');
            $requests[] = [
                'request_key' => 'add-' . (int) $row['id'],
                'request_id' => (int) $row['id'],
                'event_id' => (int) $row['id'],
                'event_name' => (string) ($row['name'] ?? 'Événement'),
                'request_type' => 'ajout',
                'status' => $this->mapRequestStatusToDisplay($sourceStatus),
                'request_date' => (string) ($row['created_at'] ?? ''),
                'summary' => trim((string) ($row['description'] ?? '')) !== ''
                    ? trim((string) ($row['description'] ?? ''))
                    : 'Demande de création d\'événement.',
                'source_status' => $sourceStatus,
                'name' => (string) ($row['name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'start_date' => (string) ($row['start_date'] ?? ''),
                'end_date' => (string) ($row['end_date'] ?? ''),
                'deadline' => (string) ($row['deadline'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'max' => (int) ($row['max'] ?? 1),
                'current' => (int) ($row['current'] ?? 0),
                'can_modify_event' => in_array($sourceStatus, ['en cours', 'refusé'], true),
                'can_manage_resources' => in_array($sourceStatus, ['en cours', 'refusé'], true),
                'can_delete_event' => $sourceStatus === 'refusé',
                'can_modify_event' => in_array((string) ($row['status'] ?? ''), ['en cours', 'validé'], true),
                'can_delete_event' => in_array((string) ($row['status'] ?? ''), ['en cours', 'validé', 'refusé'], true),
            ];
        }

        $modificationStmt = $this->conn->prepare("
            SELECT
                emr.id,
                emr.event_id,
                emr.status,
                emr.requested_at,
                emr.processed_at,
                emr.new_name,
                emr.new_description,
                emr.new_start_date,
                emr.new_end_date,
                emr.new_deadline,
                emr.new_location,
                emr.new_max,
                e.name AS current_name,
                e.description AS current_description,
                e.start_date AS current_start_date,
                e.end_date AS current_end_date,
                e.deadline AS current_deadline,
                e.location AS current_location,
                e.max AS current_max,
                e.status AS current_status
            FROM event_modification_requests emr
            LEFT JOIN events e ON e.id = emr.event_id
            WHERE emr.requested_by = ?
            ORDER BY emr.requested_at DESC, emr.id DESC
        ");
        $modificationStmt->execute([$userId]);
        foreach ($modificationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $requests[] = [
                'request_key' => 'mod-' . (int) $row['id'],
                'request_id' => (int) $row['id'],
                'event_id' => isset($row['event_id']) ? (int) $row['event_id'] : 0,
                'event_name' => (string) ($row['current_name'] ?? $row['new_name'] ?? 'Événement'),
                'request_type' => 'modification',
                'status' => $this->mapRequestStatusToDisplay((string) ($row['status'] ?? 'pending')),
                'request_date' => (string) ($row['requested_at'] ?? ''),
                'summary' => $this->buildEventModificationSummary($row),
                'source_status' => (string) ($row['status'] ?? 'pending'),
                'processed_at' => (string) ($row['processed_at'] ?? ''),
            ];
        }

        $deletionStmt = $this->conn->prepare("
            SELECT
                edr.id,
                edr.event_id,
                edr.status,
                COALESCE(edr.requested_at, edr.created_at) AS request_date,
                edr.processed_at,
                COALESCE(e.name, edr.event_name_snapshot, 'Événement supprimé') AS event_name,
                COALESCE(e.description, edr.event_description_snapshot, '') AS event_description,
                COALESCE(e.start_date, edr.event_start_date_snapshot) AS event_start_date,
                COALESCE(e.end_date, edr.event_end_date_snapshot) AS event_end_date,
                COALESCE(e.location, edr.event_location_snapshot, '') AS event_location
            FROM event_deletion_requests edr
            LEFT JOIN events e ON e.id = edr.event_id
            WHERE edr.user_id = ?
            ORDER BY COALESCE(edr.requested_at, edr.created_at) DESC, edr.id DESC
        ");
        $deletionStmt->execute([$userId]);
        foreach ($deletionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $requests[] = [
                'request_key' => 'del-' . (int) $row['id'],
                'request_id' => (int) $row['id'],
                'event_id' => isset($row['event_id']) ? (int) $row['event_id'] : 0,
                'event_name' => (string) ($row['event_name'] ?? 'Événement supprimé'),
                'request_type' => 'suppression',
                'status' => $this->mapRequestStatusToDisplay((string) ($row['status'] ?? 'pending')),
                'request_date' => (string) ($row['request_date'] ?? ''),
                'summary' => 'Demande de suppression de l\'événement.',
                'source_status' => (string) ($row['status'] ?? 'pending'),
                'processed_at' => (string) ($row['processed_at'] ?? ''),
            ];
        }

        $resourceStmt = $this->conn->prepare("
            SELECT
                rmr.id,
                rmr.event_id,
                rmr.status,
                rmr.created_at,
                rmr.processed_at,
                rmr.resources_title,
                rmr.resources_description,
                rmr.resources_data,
                e.name AS event_name
            FROM resource_modification_requests rmr
            LEFT JOIN events e ON e.id = rmr.event_id
            WHERE rmr.requested_by = ?
            ORDER BY rmr.created_at DESC, rmr.id DESC
        ");
        $resourceStmt->execute([$userId]);
        foreach ($resourceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $requests[] = [
                'request_key' => 'res-' . (int) $row['id'],
                'request_id' => (int) $row['id'],
                'event_id' => isset($row['event_id']) ? (int) $row['event_id'] : 0,
                'event_name' => (string) ($row['event_name'] ?? 'Event'),
                'request_type' => 'ressources',
                'status' => $this->mapRequestStatusToDisplay((string) ($row['status'] ?? 'pending')),
                'request_date' => (string) ($row['created_at'] ?? ''),
                'summary' => $this->buildResourceModificationSummary($row),
                'source_status' => (string) ($row['status'] ?? 'pending'),
                'processed_at' => (string) ($row['processed_at'] ?? ''),
            ];
        }

        usort($requests, static function (array $a, array $b): int {
            $aTime = strtotime((string) ($a['request_date'] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b['request_date'] ?? '')) ?: 0;
            return $bTime <=> $aTime;
        });

        return $requests;
    }

    public function getRegistrantsByEvent(int $eventId): array
    {
        $sql = 'SELECT u.id, u.nom, u.prenom, u.email, u.telephone, r.created_at
                FROM registrations r
                INNER JOIN utilisateur u ON u.id = r.user_id
                WHERE r.event_id = ?
                ORDER BY r.created_at DESC, u.id DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUserRegistrations(int $userId): array
    {
        $sql = 'SELECT r.*, e.name, e.start_date, e.location
                FROM registrations r
                INNER JOIN events e ON e.id = r.event_id
                WHERE r.user_id = ?
                ORDER BY e.start_date ASC, r.id DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function isUserRegistered(int $userId, int $eventId): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
        $stmt->execute([$userId, $eventId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registerUser(int $userId, int $eventId): array
    {
        if ($userId <= 0 || $eventId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        try {
            $this->conn->beginTransaction();

            $duplicate = $this->conn->prepare('SELECT 1 FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
            $duplicate->execute([$userId, $eventId]);
            if ($duplicate->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Vous êtes déjà inscrit à cet événement.'];
            }

            $eventStmt = $this->conn->prepare("SELECT id, status, `current`, `max` FROM events WHERE id = ? LIMIT 1");
            $eventStmt->execute([$eventId]);
            $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Événement introuvable.'];
            }

            if (($event['status'] ?? '') !== 'validé') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Seuls les événements validés acceptent des inscriptions.'];
            }

            if ((int) ($event['current'] ?? 0) >= (int) ($event['max'] ?? 0)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'L\'événement est complet.'];
            }

            $insert = $this->conn->prepare('INSERT INTO registrations (user_id, event_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
            $insert->execute([$userId, $eventId]);

            $update = $this->conn->prepare('UPDATE events SET `current` = `current` + 1 WHERE id = ?');
            $update->execute([$eventId]);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Inscription enregistrée avec succès.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur serveur lors de l\'inscription.'];
        }
    }

    public function unregisterUser(int $userId, int $eventId): array
    {
        if ($userId <= 0 || $eventId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        try {
            $this->conn->beginTransaction();

            $delete = $this->conn->prepare('DELETE FROM registrations WHERE user_id = ? AND event_id = ?');
            $delete->execute([$userId, $eventId]);

            if ($delete->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Aucune inscription trouvée pour cet utilisateur.'];
            }

            $update = $this->conn->prepare('UPDATE events SET `current` = CASE WHEN `current` > 0 THEN `current` - 1 ELSE 0 END WHERE id = ?');
            $update->execute([$eventId]);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Désinscription effectuée avec succès.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur serveur lors de la désinscription.'];
        }
    }

    private function autoRegisterEventCreator(int $userId, int $eventId): bool
    {
        if ($userId <= 0 || $eventId <= 0) {
            return false;
        }

        if (!$this->userExists($userId)) {
            return false;
        }

        $eventStmt = $this->conn->prepare('SELECT id FROM events WHERE id = ? LIMIT 1');
        $eventStmt->execute([$eventId]);
        if (!$eventStmt->fetch(PDO::FETCH_ASSOC)) {
            return false;
        }

        $duplicate = $this->conn->prepare('SELECT 1 FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
        $duplicate->execute([$userId, $eventId]);
        if ($duplicate->fetch(PDO::FETCH_ASSOC)) {
            return false;
        }

        $insert = $this->conn->prepare('INSERT INTO registrations (user_id, event_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        $insert->execute([$userId, $eventId]);

        $update = $this->conn->prepare('UPDATE events SET `current` = `current` + 1 WHERE id = ?');
        $update->execute([$eventId]);

        return true;
    }

    public function createEvent(array $data): array
    {
        try {
            error_log('CONTROLLER: Début création événement avec données: ' . json_encode($data));
            
            // Admin/agent: validation directe. Utilisateur front: demande en cours.
            $isAdminCreation = in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true);
            $data['status'] = $isAdminCreation ? 'validé' : 'en cours';
            
            $payload = $this->normalizeEventPayload($data, false);
            error_log('CONTROLLER: Payload normalisé: ' . json_encode($payload));

            $this->conn->beginTransaction();

            // Récupérer l'ID du créateur (user_id depuis la session)
            $createdBy = $data['created_by'] ?? null;

            $stmt = $this->conn->prepare("INSERT INTO events (name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
            $result = $stmt->execute([
                $payload['name'],
                $payload['description'],
                $payload['start_date'],
                $payload['end_date'],
                $payload['deadline'],
                $payload['location'],
                $payload['max'],
                $payload['status'],
                $createdBy,
            ]);
            
            error_log('CONTROLLER: Insertion résultat: ' . ($result ? 'SUCCESS' : 'FAILED'));

            $eventId = (int) $this->conn->lastInsertId();
            error_log('CONTROLLER: ID événement créé: ' . $eventId);

            if ($isAdminCreation && $eventId > 0) {
                $this->autoRegisterEventCreator((int) $createdBy, $eventId);
            }

            $this->conn->commit();
            error_log('CONTROLLER: Transaction validée');
            
            if (!$isAdminCreation && $eventId > 0) {
                $this->sendAdminRequestEmails('Ajout', (int) $createdBy, (string) $payload['name']);
            }

            return ['success' => true, 'id' => $eventId, 'message' => 'Événement créé avec succès.'];
        } catch (Throwable $e) {
            error_log('CONTROLLER: ERREUR création: ' . get_class($e) . ' - ' . $e->getMessage());
            error_log('CONTROLLER: Stack trace: ' . $e->getTraceAsString());
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
                error_log('CONTROLLER: Transaction annulée');
            }
            // Retourner le message d'erreur exact pour faciliter le diagnostic
            return ['success' => false, 'message' => 'Erreur création: ' . $e->getMessage()];
        }
    }

    public function updateEvent(int $id, array $data): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Identifiant événement invalide.'];
        }

        try {
            // Forcer automatiquement le statut 'validé' pour les événements modifiés par admin
            $data['status'] = 'validé';
            
            $payload = $this->normalizeEventPayload($data, true);
            $existing = $this->getEventById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Événement introuvable.'];
            }

            if ($payload['max'] < (int) ($existing['current'] ?? 0)) {
                throw new InvalidArgumentException('La capacité maximale ne peut pas être inférieure au nombre actuel d\'inscrits.');
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("UPDATE events SET name = ?, description = ?, start_date = ?, end_date = ?, deadline = ?, location = ?, `max` = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $payload['name'],
                $payload['description'],
                $payload['start_date'],
                $payload['end_date'],
                $payload['deadline'],
                $payload['location'],
                $payload['max'],
                $payload['status'],
                $id,
            ]);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Événement mis à jour avec succès.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Erreur lors de la mise à jour de l\'événement.'];
        }
    }

    public function updateOwnedEventForReview(int $id, int $userId, array $data): array
    {
        if ($id <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Parametres invalides.'];
        }

        try {
            $existing = $this->getEventById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'Evenement introuvable.'];
            }

            if ((int) ($existing['created_by'] ?? 0) !== $userId) {
                return ['success' => false, 'message' => 'Vous ne pouvez modifier que vos propres evenements.'];
            }

            $currentStatus = (string) ($existing['status'] ?? 'en cours');
            $isRejectedStatus = str_contains(strtolower($currentStatus), 'refus');
            if ($currentStatus !== 'en cours' && !$isRejectedStatus) {
                return ['success' => false, 'message' => 'Seuls les evenements en cours ou refuses peuvent etre modifies directement.'];
            }

            $data['status'] = 'en cours';
            $payload = $this->normalizeEventPayload($data, true);

            if ($payload['max'] < (int) ($existing['current'] ?? 0)) {
                throw new InvalidArgumentException('La capacite maximale ne peut pas etre inferieure au nombre actuel d\'inscrits.');
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("UPDATE events SET name = ?, description = ?, start_date = ?, end_date = ?, deadline = ?, location = ?, `max` = ?, status = 'en cours' WHERE id = ?");
            $stmt->execute([
                $payload['name'],
                $payload['description'],
                $payload['start_date'],
                $payload['end_date'],
                $payload['deadline'],
                $payload['location'],
                $payload['max'],
                $id,
            ]);

            $this->conn->commit();

            if ($isRejectedStatus) {
                return ['success' => true, 'message' => 'Evenement mis a jour et renvoye a l\'administrateur pour validation.'];
            }

            return ['success' => true, 'message' => 'Evenement mis a jour avec succes.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return ['success' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Erreur lors de la mise a jour de l\'evenement.'];
        }
    }

    public function deleteEvent(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Identifiant événement invalide.'];
        }

        try {
            $this->conn->beginTransaction();

            $delReg = $this->conn->prepare('DELETE FROM registrations WHERE event_id = ?');
            $delReg->execute([$id]);

            $delRes = $this->conn->prepare('DELETE FROM event_resources WHERE event_id = ?');
            $delRes->execute([$id]);

            $delEvt = $this->conn->prepare('DELETE FROM events WHERE id = ?');
            $delEvt->execute([$id]);

            if ($delEvt->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Événement introuvable.'];
            }

            $this->conn->commit();
            return ['success' => true, 'message' => 'Événement supprimé avec succès.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur lors de la suppression de l\'événement.'];
        }
    }

    public function canModifyEvent(int $eventId, int $userId, bool $isAdmin): bool
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            return false;
        }
        // Seul le créateur ou un admin peut modifier (mais admin seulement si c'est son événement)
        return $event['created_by'] === $userId;
    }

    public function canDeleteEvent(int $eventId, int $userId, bool $isAdmin): bool
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            return false;
        }
        // Seul le créateur ou un admin peut supprimer (mais admin seulement si c'est son événement)
        return $event['created_by'] === $userId;
    }

    public function isEventOwner(int $eventId, int $userId): bool
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            return false;
        }
        return $event['created_by'] === $userId;
    }

    public function updateEventStatus(int $id, string $status): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Identifiant événement invalide.'];
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return ['success' => false, 'message' => 'Statut invalide.'];
        }

        try {
            $event = $this->getEventById($id);
            if (!$event) {
                return ['success' => false, 'message' => 'Événement introuvable.'];
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("UPDATE events SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            if ($stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Événement introuvable ou statut inchangé.'];
            }

            if ($status === 'validé') {
                $this->autoRegisterEventCreator((int) ($event['created_by'] ?? 0), $id);
            }

            $this->conn->commit();

            $message = $status === 'validé'
                ? 'Événement validé avec succès.'
                : ($status === 'refusé' ? 'Événement refusé avec succès.' : 'Statut mis à jour.');

            if (in_array($status, ['validé', 'refusé'], true)) {
                $this->sendUserRequestProcessedEmail(
                    (int) ($event['created_by'] ?? 0),
                    (string) ($event['name'] ?? 'Événement'),
                    'Ajout',
                    $status === 'validé' ? 'approved' : 'rejected'
                );
            }

            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour du statut.'];
        }
    }

    /**
     * Sauvegarder les ressources d'un événement
     * - Admin : modification directe
     * - Utilisateur sans ressources existantes : création directe (première fois)
     * - Utilisateur avec ressources existantes : création d'une demande de modification
     */
    public function saveResources(int $eventId, int $userId, array $resources, ?string $resourcesTitle = null, ?string $resourcesDescription = null): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        if (!$this->userExists($userId)) {
            return ['success' => false, 'message' => 'Utilisateur introuvable pour requested_by = ' . $userId . '.'];
        }

        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement introuvable.'];
        }

        if ((int) $event['created_by'] !== $userId) {
            return ['success' => false, 'message' => 'Vous ne pouvez gérer les ressources que de vos propres événements.'];
        }

        // Valider les ressources
        foreach ($resources as $i => $resource) {
            $name = trim((string) ($resource['name'] ?? ''));
            $type = trim((string) ($resource['type'] ?? 'materiel'));
            if ($name === '') {
                return ['success' => false, 'message' => 'Le nom de la ressource #' . ($i + 1) . ' est obligatoire.'];
            }
            if ($this->textLength($name) < 6) {
                return ['success' => false, 'message' => 'Le titre doit contenir au moins 6 caractères'];
            }
            if (!in_array($type, self::ALLOWED_RESOURCE_TYPES, true)) {
                return ['success' => false, 'message' => 'Type de ressource invalide.'];
            }
        }

        $normalizedPayload = $this->normalizeResourcesPayload($resources, $resourcesTitle, $resourcesDescription);
        if (!($normalizedPayload['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($normalizedPayload['message'] ?? 'Ressources invalides.')];
        }

        $normalizedResources = $normalizedPayload['resources'];
        $normalizedTitle = $normalizedPayload['resources_title'];
        $normalizedDescription = $normalizedPayload['resources_description'];

        $isAdmin = in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true);
        if (!$isAdmin && $this->hasPendingResourceModificationRequest($eventId)) {
            return $this->upsertResourceModificationRequest(
                $eventId,
                $userId,
                $normalizedResources,
                $normalizedTitle,
                $normalizedDescription
            );
        }

        $eventStatus = (string) ($event['status'] ?? 'en cours');
        $eventStatusLower = strtolower($eventStatus);
        $isValidatedStatus = str_contains($eventStatusLower, 'valid');
        $isRejectedStatus = str_contains($eventStatusLower, 'refus');
        if (!$isAdmin && ($isValidatedStatus || $isRejectedStatus)) {
            return $this->upsertResourceModificationRequest(
                $eventId,
                $userId,
                $normalizedResources,
                $normalizedTitle,
                $normalizedDescription,
                $isRejectedStatus
            );
        }

        if (!$isAdmin && in_array($eventStatus, ['validé', 'refusé'], true)) {
            return $this->upsertResourceModificationRequest(
                $eventId,
                $userId,
                $normalizedResources,
                $normalizedTitle,
                $normalizedDescription,
                $eventStatus === 'refusé'
            );
        }

        $isAdmin = in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true);
        $existingResources = $this->getResourcesByEvent($eventId);
        $hasExistingResources = $existingResources !== [];

        // Si utilisateur (non-admin) et ressources existantes â†’ créer une demande de modification
        if (!$isAdmin && $hasExistingResources && $eventStatus !== 'en cours') {
            return $this->createResourceModificationRequest($eventId, $userId, $normalizedResources, $normalizedTitle, $normalizedDescription);
        }

        // Sinon (admin ou première fois pour utilisateur) : modification directe
        try {
            $this->conn->beginTransaction();

            // Récupérer les IDs existants
            $existingStmt = $this->conn->prepare('SELECT id FROM event_resources WHERE event_id = ?');
            $existingStmt->execute([$eventId]);
            $existingIds = array_column($existingStmt->fetchAll(PDO::FETCH_ASSOC), 'id');

            $sentIds = [];

            foreach ($normalizedResources as $resource) {
                $id = (int) ($resource['id'] ?? 0);
                $name = trim((string) ($resource['name']));
                $description = trim((string) ($resource['description'] ?? ''));
                $quantity = isset($resource['quantity']) ? (int) $resource['quantity'] : null;
                $type = trim((string) ($resource['type'] ?? 'materiel'));

                if ($id > 0 && in_array($id, $existingIds, true)) {
                    // UPDATE ressource existante
                    $stmt = $this->conn->prepare('UPDATE event_resources SET resources_title = ?, resources_description = ?, name = ?, description = ?, quantity = ?, type = ? WHERE id = ? AND event_id = ?');
                    $stmt->execute([$normalizedTitle, $normalizedDescription, $name, $description, $quantity, $type, $id, $eventId]);
                    $sentIds[] = $id;
                } else {
                    // INSERT nouvelle ressource
                    $stmt = $this->conn->prepare('INSERT INTO event_resources (event_id, resources_title, resources_description, name, description, quantity, type) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$eventId, $normalizedTitle, $normalizedDescription, $name, $description, $quantity, $type]);
                    $sentIds[] = (int) $this->conn->lastInsertId();
                }
            }

            // DELETE ressources supprimées
            $deletedIds = array_diff($existingIds, $sentIds);
            if ($deletedIds !== []) {
                $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
                $delStmt = $this->conn->prepare("DELETE FROM event_resources WHERE id IN ($placeholders) AND event_id = ?");
                $params = array_merge($deletedIds, [$eventId]);
                $delStmt->execute($params);
            }

            // Mettre à jour le statut de l'événement (uniquement si ce n'est pas déjà validé)
            $currentStatus = $event['status'] ?? 'en cours';
            if ($currentStatus !== 'validé') {
                $newStatus = $isAdmin ? 'validé' : 'en cours';
                $statusStmt = $this->conn->prepare("UPDATE events SET status = ? WHERE id = ?");
                $statusStmt->execute([$newStatus, $eventId]);
            }

            $this->conn->commit();

            $statusMessage = $isAdmin
                ? 'Ressources enregistrées.'
                : 'Ressources enregistrées. Événement en attente de validation par un administrateur.';

            return ['success' => true, 'message' => $statusMessage];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $errorMessage = $this->buildSqlErrorMessage($e);
            error_log('ERREUR saveResources: ' . $errorMessage);
            return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement des ressources: ' . $errorMessage];
        }
    }

    public function deleteResources(int $eventId, int $userId): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        if (!$this->userExists($userId)) {
            return ['success' => false, 'message' => 'Utilisateur introuvable.'];
        }

        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement introuvable.'];
        }

        $isAdmin = in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true);
        $isOwner = (int) ($event['created_by'] ?? 0) === $userId;

        if (!$isAdmin && !$isOwner) {
            return ['success' => false, 'message' => 'Vous ne pouvez supprimer que les ressources de vos propres événements.'];
        }

        if ($isAdmin) {
            try {
                $stmt = $this->conn->prepare('DELETE FROM event_resources WHERE event_id = ?');
                $stmt->execute([$eventId]);
                return ['success' => true, 'message' => 'Ressources supprimées avec succès'];
            } catch (Throwable $e) {
                $errorMessage = $this->buildSqlErrorMessage($e, $stmt ?? null);
                error_log('ERREUR deleteResources: ' . $errorMessage);
                return ['success' => false, 'message' => 'Erreur lors de la suppression des ressources: ' . $errorMessage];
            }
        }

        return $this->createResourceDeletionRequest($eventId, $userId);
    }

    public function createResourceDeletionRequest(int $eventId, int $userId): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        if (!$this->userExists($userId)) {
            return ['success' => false, 'message' => 'Utilisateur introuvable.'];
        }

        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement introuvable.'];
        }

        if ((int) ($event['created_by'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Vous ne pouvez supprimer que les ressources de vos propres événements.'];
        }

        if ($this->hasPendingResourceModificationRequest($eventId)) {
            return ['success' => false, 'message' => 'Une demande concernant les ressources est déjà en cours pour cet événement.'];
        }

        try {
            if ($this->resourceRequestTypeColumnExists()) {
                $stmt = $this->conn->prepare('INSERT INTO resource_modification_requests (event_id, requested_by, request_type, resources_title, resources_description, resources_data, status, created_at) VALUES (?, ?, ?, NULL, NULL, ?, ?, NOW())');
                $stmt->execute([$eventId, $userId, 'suppression ressources', '[]', 'pending']);
            } else {
                $stmt = $this->conn->prepare('INSERT INTO resource_modification_requests (event_id, requested_by, resources_title, resources_description, resources_data, status, created_at) VALUES (?, ?, NULL, NULL, ?, ?, NOW())');
                $stmt->execute([$eventId, $userId, '[]', 'pending']);
            }

            $this->sendAdminRequestEmails('Suppression ressources', $userId, (string) ($event['name'] ?? 'Événement'));

            return ['success' => true, 'message' => 'Votre demande de suppression des ressources a été envoyée'];
        } catch (Throwable $e) {
            $errorMessage = $this->buildSqlErrorMessage($e, $stmt ?? null);
            error_log('ERREUR createResourceDeletionRequest: ' . $errorMessage);
            return ['success' => false, 'message' => 'Erreur lors de la création de la demande de suppression des ressources: ' . $errorMessage];
        }
    }

    private function isResourceDeletionRequest(array $request): bool
    {
        if (trim((string) ($request['request_type'] ?? '')) === 'suppression ressources') {
            return true;
        }

        $decodedResources = $this->decodeRequestedResources($request);
        if (!($decodedResources['success'] ?? false)) {
            return false;
        }

        return $decodedResources['resources'] === []
            && trim((string) ($request['resources_title'] ?? '')) === ''
            && trim((string) ($request['resources_description'] ?? '')) === '';
    }

    /**
     * Créer une demande de modification des ressources (pour les utilisateurs non-admin)
     */
    public function upsertResourceModificationRequest(int $eventId, int $userId, array $newResources, ?string $resourcesTitle = null, ?string $resourcesDescription = null, bool $moveEventBackToReview = false): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Parametres invalides.'];
        }

        if (!$this->userExists($userId)) {
            return ['success' => false, 'message' => 'Utilisateur introuvable pour requested_by = ' . $userId . '.'];
        }

        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Evenement introuvable.'];
        }

        if ((int) ($event['created_by'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Vous ne pouvez modifier les ressources que de vos propres evenements.'];
        }

        $normalizedPayload = $this->normalizeResourcesPayload($newResources, $resourcesTitle, $resourcesDescription);
        if (!($normalizedPayload['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($normalizedPayload['message'] ?? 'Ressources invalides.')];
        }

        $encodedResources = $this->encodeResourcesData($normalizedPayload['resources']);
        if (!($encodedResources['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($encodedResources['message'] ?? 'Encodage JSON impossible.')];
        }

        $stmt = null;
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT id FROM resource_modification_requests WHERE event_id = ? AND requested_by = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$eventId, $userId]);
            $pendingRequest = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pendingRequest) {
                $stmt = $this->conn->prepare("
                    UPDATE resource_modification_requests
                    SET resources_title = ?, resources_description = ?, resources_data = ?, status = 'pending', created_at = NOW(), processed_at = NULL, processed_by = NULL
                    WHERE id = ?
                ");
                $stmt->execute([
                    $normalizedPayload['resources_title'],
                    $normalizedPayload['resources_description'],
                    $encodedResources['json'],
                    (int) $pendingRequest['id'],
                ]);
                $message = 'La demande de modification des ressources en cours a ete remplacee par la plus recente.';
            } else {
                $stmt = $this->conn->prepare('INSERT INTO resource_modification_requests (event_id, requested_by, resources_title, resources_description, resources_data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([
                    $eventId,
                    $userId,
                    $normalizedPayload['resources_title'],
                    $normalizedPayload['resources_description'],
                    $encodedResources['json'],
                    'pending'
                ]);
                $message = 'Demande de modification des ressources creee avec succes. L\'administrateur va examiner votre demande.';
            }

            if ($moveEventBackToReview && str_contains(strtolower((string) ($event['status'] ?? '')), 'refus')) {
                $eventStmt = $this->conn->prepare("UPDATE events SET status = 'en cours' WHERE id = ?");
                $eventStmt->execute([$eventId]);
                $message = 'Demande de modification des ressources enregistree. L\'evenement repart en validation administrateur.';
            }

            $this->conn->commit();
            $this->sendAdminRequestEmails('Modification ressources', $userId, (string) ($event['name'] ?? 'Événement'));
            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $errorMessage = $this->buildSqlErrorMessage($e, $stmt);
            error_log('ERREUR upsertResourceModificationRequest: ' . $errorMessage . ' | event_id=' . $eventId . ' | requested_by=' . $userId);
            return ['success' => false, 'message' => 'Erreur SQL lors de la creation de la demande: ' . $errorMessage];
        }
    }

    public function createResourceModificationRequest(int $eventId, int $userId, array $newResources, ?string $resourcesTitle = null, ?string $resourcesDescription = null): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        if (!$this->userExists($userId)) {
            return ['success' => false, 'message' => 'Utilisateur introuvable pour requested_by = ' . $userId . '.'];
        }

        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement introuvable.'];
        }

        if ((int) $event['created_by'] !== $userId) {
            return ['success' => false, 'message' => 'Vous ne pouvez modifier les ressources que de vos propres événements.'];
        }

        // Vérifier qu'une demande n'est pas déjà en cours pour cet événement
        $stmt = $this->conn->prepare("SELECT id FROM resource_modification_requests WHERE event_id = ? AND status = 'pending'");
        $stmt->execute([$eventId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Une demande de modification des ressources est déjà en cours pour cet événement.'];
        }

        $normalizedPayload = $this->normalizeResourcesPayload($newResources, $resourcesTitle, $resourcesDescription);
        if (!($normalizedPayload['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($normalizedPayload['message'] ?? 'Ressources invalides.')];
        }

        $encodedResources = $this->encodeResourcesData($normalizedPayload['resources']);
        if (!($encodedResources['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($encodedResources['message'] ?? 'Encodage JSON impossible.')];
        }

        $stmt = null;
        try {
            $stmt = $this->conn->prepare('INSERT INTO resource_modification_requests (event_id, requested_by, resources_title, resources_description, resources_data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $eventId,
                $userId,
                $normalizedPayload['resources_title'],
                $normalizedPayload['resources_description'],
                $encodedResources['json'],
                'pending'
            ]);

            $this->sendAdminRequestEmails('Modification ressources', $userId, (string) ($event['name'] ?? 'Événement'));

            return ['success' => true, 'message' => 'Demande de modification des ressources créée avec succès. L\'administrateur va examiner votre demande.'];
        } catch (Throwable $e) {
            $errorMessage = $this->buildSqlErrorMessage($e, $stmt);
            error_log('ERREUR createResourceModificationRequest: ' . $errorMessage . ' | event_id=' . $eventId . ' | requested_by=' . $userId);
            return ['success' => false, 'message' => 'Erreur SQL lors de la création de la demande: ' . $errorMessage];
        }
    }

    /**
     * Récupérer les demandes de modification de ressources en attente
     */
    public function getPendingResourceModificationRequests(): array
    {
        $stmt = $this->conn->prepare("
            SELECT rmr.*, e.name as event_name, u.nom as requester_name, u.prenom as requester_prenom
            FROM resource_modification_requests rmr
            INNER JOIN events e ON e.id = rmr.event_id
            INNER JOIN utilisateur u ON u.id = rmr.requested_by
            WHERE rmr.status = 'pending'
            ORDER BY rmr.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Approuver une demande de modification des ressources
     */
    public function approveResourceModificationRequest(int $requestId, int $adminId): array
    {
        if ($requestId <= 0 || $adminId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        try {
            $this->conn->beginTransaction();

            // Récupérer la demande
            $stmt = $this->conn->prepare('SELECT * FROM resource_modification_requests WHERE id = ? AND status = ?');
            $stmt->execute([$requestId, 'pending']);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Demande introuvable ou déjà traitée.'];
            }

            $isDeletionRequest = $this->isResourceDeletionRequest($request);
            $eventId = (int) $request['event_id'];
            $event = $this->getEventById($eventId);
            $decodedResources = $this->decodeRequestedResources($request);
            if (!($decodedResources['success'] ?? false)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => (string) ($decodedResources['message'] ?? 'resources_data invalide.')];
            }

            $normalizedPayload = $this->normalizeResourcesPayload(
                $decodedResources['resources'],
                (string) ($request['resources_title'] ?? ''),
                (string) ($request['resources_description'] ?? ''),
                false
            );
            if (!($normalizedPayload['success'] ?? false)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => (string) ($normalizedPayload['message'] ?? 'Ressources demandées invalides.')];
            }

            $newResources = $normalizedPayload['resources'];
            $resourcesTitle = $normalizedPayload['resources_title'];
            $resourcesDescription = $normalizedPayload['resources_description'];

            // Supprimer les anciennes ressources
            $delStmt = $this->conn->prepare('DELETE FROM event_resources WHERE event_id = ?');
            $delStmt->execute([$eventId]);

            // Insérer les nouvelles ressources
            if ($newResources !== []) {
                $insStmt = $this->conn->prepare('INSERT INTO event_resources (event_id, resources_title, resources_description, name, description, quantity, type) VALUES (?, ?, ?, ?, ?, ?, ?)');
                foreach ($newResources as $resource) {
                    $insStmt->execute([
                        $eventId,
                        $resourcesTitle,
                        $resourcesDescription,
                        trim((string) ($resource['name'] ?? '')),
                        trim((string) ($resource['description'] ?? '')),
                        isset($resource['quantity']) ? (int) $resource['quantity'] : null,
                        trim((string) ($resource['type'] ?? 'materiel'))
                    ]);
                }
            }

            // Marquer la demande comme approuvée
            $updStmt = $this->conn->prepare('UPDATE resource_modification_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?');
            $updStmt->execute(['approved', $adminId, $requestId]);

            $this->conn->commit();
            $message = $isDeletionRequest
                ? 'Demande approuvée. Les ressources ont été supprimées.'
                : 'Demande approuvée. Les ressources ont été mises à jour.';

            $this->sendUserRequestProcessedEmail(
                (int) ($request['requested_by'] ?? 0),
                (string) ($event['name'] ?? 'Événement'),
                $isDeletionRequest ? 'Suppression ressources' : 'Modification ressources',
                'approved'
            );

            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $errorMessage = $this->buildSqlErrorMessage($e);
            error_log('ERREUR approveResourceModificationRequest: ' . $errorMessage);
            return ['success' => false, 'message' => 'Erreur lors de l\'approbation de la demande: ' . $errorMessage];
        }
    }

    /**
     * Refuser une demande de modification des ressources
     */
    public function rejectResourceModificationRequest(int $requestId, int $adminId): array
    {
        if ($requestId <= 0 || $adminId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        try {
            $requestStmt = $this->conn->prepare('SELECT * FROM resource_modification_requests WHERE id = ? AND status = ? LIMIT 1');
            $requestStmt->execute([$requestId, 'pending']);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
            if (!$request) {
                return ['success' => false, 'message' => 'Demande introuvable ou déjà traitée.'];
            }

            $isDeletionRequest = $this->isResourceDeletionRequest($request);
            $event = $this->getEventById((int) ($request['event_id'] ?? 0));

            $stmt = $this->conn->prepare('UPDATE resource_modification_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ? AND status = ?');
            $stmt->execute(['rejected', $adminId, $requestId, 'pending']);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Demande introuvable ou déjà traitée.'];
            }

            $message = $isDeletionRequest
                ? 'Demande de suppression des ressources refusée.'
                : 'Demande de modification des ressources refusée.';

            $this->sendUserRequestProcessedEmail(
                (int) ($request['requested_by'] ?? 0),
                (string) ($event['name'] ?? 'Événement'),
                $isDeletionRequest ? 'Suppression ressources' : 'Modification ressources',
                'rejected'
            );

            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            $errorMessage = $this->buildSqlErrorMessage($e, $stmt ?? null);
            error_log('ERREUR rejectResourceModificationRequest: ' . $errorMessage);
            return ['success' => false, 'message' => 'Erreur lors du refus de la demande: ' . $errorMessage];
        }
    }

    /**
     * Vérifier si une demande de modification de ressources est en cours pour un événement
     */
    public function hasPendingResourceModificationRequest(int $eventId): bool
    {
        if ($eventId <= 0) return false;
        $stmt = $this->conn->prepare("SELECT id FROM resource_modification_requests WHERE event_id = ? AND status = 'pending'");
        $stmt->execute([$eventId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Créer une demande de suppression d'événement (pour les utilisateurs)
     */
    public function requestEventDeletion(int $eventId, int $userId): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        // Vérifier que l'événement existe et que l'utilisateur est le créateur
        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement non trouvé.'];
        }

        $eventCreatedBy = (int) ($event['created_by'] ?? 0);
        if ($eventCreatedBy !== $userId) {
            return ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à demander la suppression de cet événement.'];
        }

        // Vérifier qu'une demande n'est pas déjà en cours
        $stmt = $this->conn->prepare("SELECT id FROM event_deletion_requests WHERE event_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$eventId, $userId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Une demande de suppression est déjà en cours pour cet événement.'];
        }

        // Créer la demande de suppression
        $stmt = $this->conn->prepare('INSERT INTO event_deletion_requests (event_id, user_id, status, requested_at, event_name_snapshot, event_description_snapshot, event_start_date_snapshot, event_end_date_snapshot, event_location_snapshot, event_status_snapshot) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $eventId,
            $userId,
            'pending',
            $event['name'] ?? null,
            $event['description'] ?? null,
            $event['start_date'] ?? null,
            $event['end_date'] ?? null,
            $event['location'] ?? null,
            $event['status'] ?? null,
        ]);

        $this->sendAdminRequestEmails('Suppression', $userId, (string) ($event['name'] ?? 'Événement'));

        return ['success' => true, 'message' => 'Demande de suppression créée avec succès. L\'administrateur va examiner votre demande.'];
    }

    /**
     * Récupérer toutes les demandes de suppression en attente
     */
    public function getPendingDeletionRequests(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                edr.id as request_id,
                edr.event_id,
                edr.user_id,
                edr.status,
                edr.requested_at,
                COALESCE(e.name, edr.event_name_snapshot, 'Événement supprimé') as event_name,
                COALESCE(e.status, edr.event_status_snapshot, 'annulé') as event_status,
                e.created_by as event_creator_id,
                u.nom as user_nom,
                u.prenom as user_prenom,
                u.email as user_email
            FROM event_deletion_requests edr
            LEFT JOIN events e ON edr.event_id = e.id
            JOIN utilisateur u ON edr.user_id = u.id
            WHERE edr.status = 'pending'
            ORDER BY edr.requested_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Approuver une demande de suppression
     */
    public function approveDeletionRequest(int $requestId, int $adminId): array
    {
        if ($requestId <= 0 || $adminId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        // Récupérer la demande
        $stmt = $this->conn->prepare("
            SELECT edr.event_id, edr.user_id, COALESCE(e.name, edr.event_name_snapshot, 'Événement') AS event_name
            FROM event_deletion_requests edr
            LEFT JOIN events e ON e.id = edr.event_id
            WHERE edr.id = ? AND edr.status = 'pending'
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande de suppression non trouvée ou déjà traitée.'];
        }

        $eventId = (int) $request['event_id'];

        try {
            $this->conn->beginTransaction();

            // Supprimer l'événement
            $stmt = $this->conn->prepare('DELETE FROM events WHERE id = ?');
            $stmt->execute([$eventId]);

            // Mettre à jour la demande
            $stmt = $this->conn->prepare("UPDATE event_deletion_requests SET status = 'approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
            $stmt->execute([$adminId, $requestId]);

            $this->conn->commit();
            $this->sendUserRequestProcessedEmail(
                (int) ($request['user_id'] ?? 0),
                (string) ($request['event_name'] ?? 'Événement'),
                'Suppression',
                'approved'
            );
            return ['success' => true, 'message' => 'Demande approuvée et événement supprimé.'];
        } catch (Throwable $e) {
            $this->conn->rollBack();
            error_log('Erreur lors de l\'approbation de la suppression: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }

    /**
     * Refuser une demande de suppression
     */
    public function rejectDeletionRequest(int $requestId, int $adminId): array
    {
        if ($requestId <= 0 || $adminId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        $requestStmt = $this->conn->prepare("
            SELECT edr.user_id, COALESCE(e.name, edr.event_name_snapshot, 'Événement') AS event_name
            FROM event_deletion_requests edr
            LEFT JOIN events e ON e.id = edr.event_id
            WHERE edr.id = ? AND edr.status = 'pending'
            LIMIT 1
        ");
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            return ['success' => false, 'message' => 'Demande de suppression non trouvée ou déjà traitée.'];
        }

        $stmt = $this->conn->prepare("UPDATE event_deletion_requests SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$adminId, $requestId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Demande de suppression non trouvée ou déjà traitée.'];
        }

        $this->sendUserRequestProcessedEmail(
            (int) ($request['user_id'] ?? 0),
            (string) ($request['event_name'] ?? 'Événement'),
            'Suppression',
            'rejected'
        );

        return ['success' => true, 'message' => 'Demande de suppression refusée. L\'événement est conservé.'];
    }

    /**
     * Créer une demande de modification d'événement (pour les utilisateurs)
     */
    public function requestEventModification(int $eventId, int $userId, array $newData): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        // Vérifier que l'événement existe et que l'utilisateur est le créateur
        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement non trouvé.'];
        }

        $eventCreatedBy = (int) ($event['created_by'] ?? 0);
        if ($eventCreatedBy !== $userId) {
            return ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à modifier cet événement.'];
        }

        // Vérifier qu'une demande n'est pas déjà en cours pour cet événement
        $stmt = $this->conn->prepare("SELECT id FROM event_modification_requests WHERE event_id = ? AND status = 'pending'");
        $stmt->execute([$eventId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Une demande de modification est déjà en cours pour cet événement.'];
        }

        // Créer la demande de modification
        $stmt = $this->conn->prepare('
            INSERT INTO event_modification_requests 
            (event_id, requested_by, status, new_name, new_description, new_start_date, new_end_date, new_deadline, new_location, new_max, requested_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $stmt->execute([
            $eventId,
            $userId,
            'pending',
            $newData['name'] ?? null,
            $newData['description'] ?? null,
            $newData['start_date'] ?? null,
            $newData['end_date'] ?? null,
            $newData['deadline'] ?? null,
            $newData['location'] ?? null,
            $newData['max'] ?? null
        ]);

        $this->sendAdminRequestEmails('Modification', $userId, (string) ($event['name'] ?? 'Événement'));

        return ['success' => true, 'message' => 'Demande de modification créée avec succès. L\'administrateur va examiner votre demande.'];
    }

    /**
     * Récupérer toutes les demandes de modification en attente
     */
    public function getPendingModificationRequests(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                emr.id as request_id,
                emr.event_id,
                emr.requested_by,
                emr.status,
                emr.requested_at,
                emr.new_name,
                emr.new_description,
                emr.new_start_date,
                emr.new_end_date,
                emr.new_deadline,
                emr.new_location,
                emr.new_max,
                e.name as current_name,
                e.description as current_description,
                e.start_date as current_start_date,
                e.end_date as current_end_date,
                e.deadline as current_deadline,
                e.location as current_location,
                e.max as current_max,
                e.status as event_status,
                u.nom as user_nom,
                u.prenom as user_prenom,
                u.email as user_email
            FROM event_modification_requests emr
            JOIN events e ON emr.event_id = e.id
            JOIN utilisateur u ON emr.requested_by = u.id
            WHERE emr.status = 'pending'
            ORDER BY emr.requested_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertEventModificationRequest(int $eventId, int $userId, array $newData): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement non trouvé.'];
        }

        if ((int) ($event['created_by'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à modifier cet événement.'];
        }

        $eventStatus = (string) ($event['status'] ?? '');
        if (!in_array($eventStatus, ['validé', 'en cours'], true)) {
            return ['success' => false, 'message' => 'Seuls les événements validés ou en cours peuvent être modifiés.'];
        }

        $normalizedPayload = $this->normalizeEventPayload([
            'name' => $newData['name'] ?? ($event['name'] ?? ''),
            'description' => $newData['description'] ?? ($event['description'] ?? ''),
            'start_date' => $newData['start_date'] ?? ($event['start_date'] ?? null),
            'end_date' => $newData['end_date'] ?? ($event['end_date'] ?? null),
            'deadline' => $newData['deadline'] ?? ($event['deadline'] ?? null),
            'location' => $newData['location'] ?? ($event['location'] ?? ''),
            'max' => $newData['max'] ?? ($event['max'] ?? 1),
            'status' => $eventStatus,
        ], true);

        $stmt = $this->conn->prepare("SELECT id FROM event_modification_requests WHERE event_id = ? AND requested_by = ? AND status = 'pending' ORDER BY requested_at DESC LIMIT 1");
        $stmt->execute([$eventId, $userId]);
        $pendingRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pendingRequest) {
            $stmt = $this->conn->prepare("
                UPDATE event_modification_requests
                SET new_name = ?, new_description = ?, new_start_date = ?, new_end_date = ?, new_deadline = ?, new_location = ?, new_max = ?, requested_at = NOW(), processed_at = NULL, processed_by = NULL, status = 'pending'
                WHERE id = ?
            ");
            $stmt->execute([
                $normalizedPayload['name'],
                $normalizedPayload['description'],
                $normalizedPayload['start_date'],
                $normalizedPayload['end_date'],
                $normalizedPayload['deadline'],
                $normalizedPayload['location'],
                $normalizedPayload['max'],
                (int) $pendingRequest['id'],
            ]);

            $this->sendAdminRequestEmails('Modification', $userId, (string) ($event['name'] ?? 'Événement'));

            return ['success' => true, 'message' => 'La demande de modification en cours a été remplacée par la plus récente.'];
        }

        $stmt = $this->conn->prepare('
            INSERT INTO event_modification_requests
            (event_id, requested_by, status, new_name, new_description, new_start_date, new_end_date, new_deadline, new_location, new_max, requested_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $eventId,
            $userId,
            'pending',
            $normalizedPayload['name'],
            $normalizedPayload['description'],
            $normalizedPayload['start_date'],
            $normalizedPayload['end_date'],
            $normalizedPayload['deadline'],
            $normalizedPayload['location'],
            $normalizedPayload['max'],
        ]);

        $this->sendAdminRequestEmails('Modification', $userId, (string) ($event['name'] ?? 'Événement'));

        return ['success' => true, 'message' => 'Demande de modification créée avec succès. L\'administrateur va examiner votre demande.'];
    }

    /**
     * Approuver une demande de modification
     */
    public function approveModificationRequest(int $requestId, int $adminId): array
    {
        if ($requestId <= 0 || $adminId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        // Récupérer la demande
        $stmt = $this->conn->prepare("
            SELECT emr.event_id, emr.requested_by, emr.new_name, emr.new_description, emr.new_start_date, emr.new_end_date, emr.new_deadline, emr.new_location, emr.new_max, e.name AS event_name
            FROM event_modification_requests emr
            JOIN events e ON e.id = emr.event_id
            WHERE emr.id = ? AND emr.status = 'pending'
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande de modification non trouvée ou déjà traitée.'];
        }

        $eventId = (int) $request['event_id'];

        try {
            $this->conn->beginTransaction();

            // Appliquer les modifications à l'événement
            $stmt = $this->conn->prepare("
                UPDATE events 
                SET name = ?, description = ?, start_date = ?, end_date = ?, deadline = ?, location = ?, max = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $request['new_name'],
                $request['new_description'],
                $request['new_start_date'],
                $request['new_end_date'],
                $request['new_deadline'],
                $request['new_location'],
                $request['new_max'],
                $eventId
            ]);

            // Mettre à jour la demande
            $stmt = $this->conn->prepare("
                UPDATE event_modification_requests 
                SET status = 'approved', processed_at = NOW(), processed_by = ? 
                WHERE id = ?
            ");
            $stmt->execute([$adminId, $requestId]);

            $this->conn->commit();
            $this->sendUserRequestProcessedEmail(
                (int) ($request['requested_by'] ?? 0),
                (string) ($request['event_name'] ?? $request['new_name'] ?? 'Événement'),
                'Modification',
                'approved'
            );
            return ['success' => true, 'message' => 'Demande approuvée et modifications appliquées.'];
        } catch (Throwable $e) {
            $this->conn->rollBack();
            error_log('Erreur lors de l\'approbation de la modification: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de l\'application des modifications: ' . $e->getMessage()];
        }
    }

    /**
     * Refuser une demande de modification
     */
    public function rejectModificationRequest(int $requestId, int $adminId): array
    {
        if ($requestId <= 0 || $adminId <= 0) {
            return ['success' => false, 'message' => 'Paramètres invalides.'];
        }

        $requestStmt = $this->conn->prepare("
            SELECT emr.requested_by, e.name AS event_name
            FROM event_modification_requests emr
            JOIN events e ON e.id = emr.event_id
            WHERE emr.id = ? AND emr.status = 'pending'
            LIMIT 1
        ");
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            return ['success' => false, 'message' => 'Demande de modification non trouvée ou déjà traitée.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE event_modification_requests 
            SET status = 'rejected', processed_at = NOW(), processed_by = ? 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$adminId, $requestId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Demande de modification non trouvée ou déjà traitée.'];
        }

        $this->sendUserRequestProcessedEmail(
            (int) ($request['requested_by'] ?? 0),
            (string) ($request['event_name'] ?? 'Événement'),
            'Modification',
            'rejected'
        );

        return ['success' => true, 'message' => 'Demande de modification refusée. L\'événement reste inchangé.'];
    }

    private function normalizeEventPayload(array $data, bool $isUpdate): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $startDate = $this->normalizeNullableDate($data['start_date'] ?? null);
        $endDate = $this->normalizeNullableDate($data['end_date'] ?? null);
        $deadline = $this->normalizeNullableDate($data['deadline'] ?? null);
        $location = trim((string) ($data['location'] ?? ''));
        $max = max(1, (int) ($data['max'] ?? 0));
        $status = trim((string) ($data['status'] ?? 'en cours'));

        if ($name === '') {
            throw new InvalidArgumentException('Le nom de l\'événement est obligatoire.');
        }
        
        if ($this->textLength($name) < 6) {
            throw new InvalidArgumentException('Le titre doit contenir au moins 6 caractères');
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Le statut de l\'événement est invalide.');
        }

        if ($startDate !== null && $endDate !== null && strtotime($endDate) < strtotime($startDate)) {
            throw new InvalidArgumentException('La date de fin doit être postérieure à la date de début.');
        }

        if ($deadline !== null && $startDate !== null && strtotime($deadline) > strtotime($startDate)) {
            throw new InvalidArgumentException('La date limite doit être antérieure ou égale à la date de début.');
        }

        $resources = $this->normalizeResources($data['resources'] ?? []);

        return [
            'name' => $name,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'deadline' => $deadline,
            'location' => $location,
            'max' => $max,
            'status' => $status,
            'resources' => $resources,
        ];
    }

    private function normalizeResources(mixed $resources): array
    {
        if (is_string($resources) && trim($resources) !== '') {
            $decoded = json_decode($resources, true);
            $resources = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($resources)) {
            return [];
        }

        $normalized = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }

            $name = trim((string) ($resource['name'] ?? ''));
            $description = trim((string) ($resource['description'] ?? ''));
            $type = trim((string) ($resource['type'] ?? 'materiel'));

            if ($name === '') {
                continue;
            }

            if (!in_array($type, self::ALLOWED_RESOURCE_TYPES, true)) {
                $type = 'materiel';
            }

            $normalized[] = [
                'name' => $name,
                'description' => $description,
                'type' => $type,
            ];
        }

        return $normalized;
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Une date fournie est invalide.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Statistiques pour l'utilisateur connecté (front office)
     * Retourne les stats personnelles de l'utilisateur
     */
    public function getUserStatistics(int $userId): array
    {
        if ($userId <= 0) {
            return ['success' => false, 'message' => 'Utilisateur invalide.'];
        }

        try {
            // Événements créés par l'utilisateur
            $stmt = $this->conn->prepare('SELECT COUNT(*) FROM events WHERE created_by = ?');
            $stmt->execute([$userId]);
            $totalEvents = (int) $stmt->fetchColumn();

            $stmt = $this->conn->prepare('
                SELECT TRIM(status) AS status, COUNT(*) AS total
                FROM events
                WHERE created_by = ?
                GROUP BY TRIM(status)
            ');
            $stmt->execute([$userId]);

            $statusCounts = [
                'valides' => 0,
                'en_cours' => 0,
                'refuses' => 0,
            ];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $status = strtolower(trim((string) ($row['status'] ?? '')));
                $count = (int) ($row['total'] ?? 0);

                if ($status === 'validé') {
                    $statusCounts['valides'] += $count;
                } elseif ($status === 'en cours') {
                    $statusCounts['en_cours'] += $count;
                } elseif ($status === 'refusé') {
                    $statusCounts['refuses'] += $count;
                }
            }

            // Nombre d'inscriptions effectuées par l'utilisateur
            $stmt = $this->conn->prepare('SELECT COUNT(*) FROM registrations WHERE user_id = ?');
            $stmt->execute([$userId]);
            $inscriptions = (int) $stmt->fetchColumn();

            // Nombre de demandes envoyées (suppression + modification événements + modification ressources)
            $stmt = $this->conn->prepare("SELECT 
                (SELECT COUNT(*) FROM event_deletion_requests WHERE user_id = ?) +
                (SELECT COUNT(*) FROM event_modification_requests WHERE requested_by = ?) +
                (SELECT COUNT(*) FROM resource_modification_requests WHERE requested_by = ?) as total_demandes");
            $stmt->execute([$userId, $userId, $userId]);
            $demandes = $stmt->fetch(PDO::FETCH_ASSOC)['total_demandes'] ?? 0;

            return [
                'success' => true,
                'user_id' => $userId,
                'events_crees' => $totalEvents,
                'events_valides' => $statusCounts['valides'],
                'events_en_cours' => $statusCounts['en_cours'],
                'events_refuses' => $statusCounts['refuses'],
                'inscriptions' => $inscriptions,
                'demandes_envoyees' => (int) $demandes,
            ];
        } catch (PDOException $e) {
            error_log('Erreur getUserStatistics user_id=' . $userId . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la récupération des statistiques.'];
        }
    }

    /**
     * Statistiques temporelles pour l'admin (back office)
     * Retourne l'évolution des événements par mois sur les 12 derniers mois
     */
    public function getAdminMonthlyStatistics(): array
    {
        try {
            $stats = [];
            
            // Générer les 12 derniers mois
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $monthLabel = date('M Y', strtotime("-$i months"));
                $startDate = $month . '-01';
                $endDate = date('Y-m-t', strtotime($startDate));
                
                // Événements créés ce mois
                $stmt = $this->conn->prepare("SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
                    SUM(CASE WHEN status = 'en cours' THEN 1 ELSE 0 END) as en_cours,
                    SUM(CASE WHEN status = 'refusé' THEN 1 ELSE 0 END) as refuses
                    FROM events 
                    WHERE DATE(created_at) BETWEEN ? AND ?");
                $stmt->execute([$startDate, $endDate]);
                $eventStats = $stmt->fetch(PDO::FETCH_ASSOC);

                // Inscriptions ce mois
                $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE DATE(created_at) BETWEEN ? AND ?");
                $stmt->execute([$startDate, $endDate]);
                $inscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

                $stats[] = [
                    'month' => $monthLabel,
                    'mois_complet' => $month,
                    'events_crees' => (int) ($eventStats['total'] ?? 0),
                    'events_valides' => (int) ($eventStats['valides'] ?? 0),
                    'events_en_cours' => (int) ($eventStats['en_cours'] ?? 0),
                    'events_refuses' => (int) ($eventStats['refuses'] ?? 0),
                    'inscriptions' => (int) $inscriptions,
                ];
            }

            // Totaux généraux
            $stmt = $this->conn->query("SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as total_valides,
                SUM(CASE WHEN status = 'en cours' THEN 1 ELSE 0 END) as total_en_cours,
                SUM(CASE WHEN status = 'refusé' THEN 1 ELSE 0 END) as total_refuses
                FROM events");
            $totaux = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->conn->query("SELECT COUNT(*) as total_inscriptions FROM registrations");
            $totalInscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total_inscriptions'] ?? 0;

            return [
                'success' => true,
                'monthly' => $stats,
                'totaux' => [
                    'total_events' => (int) ($totaux['total_events'] ?? 0),
                    'total_valides' => (int) ($totaux['total_valides'] ?? 0),
                    'total_en_cours' => (int) ($totaux['total_en_cours'] ?? 0),
                    'total_refuses' => (int) ($totaux['total_refuses'] ?? 0),
                    'total_inscriptions' => (int) $totalInscriptions,
                ]
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la récupération des statistiques.'];
        }
    }

    /**
     * Statistiques temporelles avec filtres (jour, semaine, mois, année)
     * Pour le back office avec mise à jour AJAX
     */
    public function getTemporalStatistics(string $period = 'month'): array
    {
        $validPeriods = ['day', 'week', 'month', 'year'];
        if (!in_array($period, $validPeriods, true)) {
            $period = 'month';
        }

        try {
            $stats = [];
            $totaux = [];

            switch ($period) {
                case 'day':
                    // 30 derniers jours
                    for ($i = 29; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        $label = date('d/m', strtotime("-$i days"));
                        
                        $stmt = $this->conn->prepare("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
                            SUM(CASE WHEN status = 'en cours' THEN 1 ELSE 0 END) as en_cours,
                            SUM(CASE WHEN status = 'refusé' THEN 1 ELSE 0 END) as refuses
                            FROM events WHERE DATE(created_at) = ?");
                        $stmt->execute([$date]);
                        $eventStats = $stmt->fetch(PDO::FETCH_ASSOC);

                        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE DATE(created_at) = ?");
                        $stmt->execute([$date]);
                        $inscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

                        $stats[] = [
                            'label' => $label,
                            'events_crees' => (int) ($eventStats['total'] ?? 0),
                            'events_valides' => (int) ($eventStats['valides'] ?? 0),
                            'events_en_cours' => (int) ($eventStats['en_cours'] ?? 0),
                            'events_refuses' => (int) ($eventStats['refuses'] ?? 0),
                            'inscriptions' => (int) $inscriptions,
                        ];
                    }
                    break;

                case 'week':
                    // 12 dernières semaines
                    for ($i = 11; $i >= 0; $i--) {
                        $yearWeek = date('Y-W', strtotime("-$i weeks"));
                        $startDate = date('Y-m-d', strtotime($yearWeek . ' monday'));
                        $endDate = date('Y-m-d', strtotime($yearWeek . ' sunday'));
                        $label = 'S' . date('W', strtotime("-$i weeks"));
                        
                        $stmt = $this->conn->prepare("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
                            SUM(CASE WHEN status = 'en cours' THEN 1 ELSE 0 END) as en_cours,
                            SUM(CASE WHEN status = 'refusé' THEN 1 ELSE 0 END) as refuses
                            FROM events WHERE DATE(created_at) BETWEEN ? AND ?");
                        $stmt->execute([$startDate, $endDate]);
                        $eventStats = $stmt->fetch(PDO::FETCH_ASSOC);

                        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE DATE(created_at) BETWEEN ? AND ?");
                        $stmt->execute([$startDate, $endDate]);
                        $inscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

                        $stats[] = [
                            'label' => $label,
                            'events_crees' => (int) ($eventStats['total'] ?? 0),
                            'events_valides' => (int) ($eventStats['valides'] ?? 0),
                            'events_en_cours' => (int) ($eventStats['en_cours'] ?? 0),
                            'events_refuses' => (int) ($eventStats['refuses'] ?? 0),
                            'inscriptions' => (int) $inscriptions,
                        ];
                    }
                    break;

                case 'month':
                    // 12 derniers mois (déjà implémenté dans getAdminMonthlyStatistics)
                    return $this->getAdminMonthlyStatistics();

                case 'year':
                    // 5 dernières années
                    $currentYear = date('Y');
                    for ($i = 4; $i >= 0; $i--) {
                        $year = $currentYear - $i;
                        $startDate = $year . '-01-01';
                        $endDate = $year . '-12-31';
                        
                        $stmt = $this->conn->prepare("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'valide' THEN 1 ELSE 0 END) as valides,
                            SUM(CASE WHEN status = 'en cours' THEN 1 ELSE 0 END) as en_cours,
                            SUM(CASE WHEN status = 'refusé' THEN 1 ELSE 0 END) as refuses
                            FROM events WHERE DATE(created_at) BETWEEN ? AND ?");
                        $stmt->execute([$startDate, $endDate]);
                        $eventStats = $stmt->fetch(PDO::FETCH_ASSOC);

                        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE DATE(created_at) BETWEEN ? AND ?");
                        $stmt->execute([$startDate, $endDate]);
                        $inscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

                        $stats[] = [
                            'label' => (string) $year,
                            'events_crees' => (int) ($eventStats['total'] ?? 0),
                            'events_valides' => (int) ($eventStats['valides'] ?? 0),
                            'events_en_cours' => (int) ($eventStats['en_cours'] ?? 0),
                            'events_refuses' => (int) ($eventStats['refuses'] ?? 0),
                            'inscriptions' => (int) $inscriptions,
                        ];
                    }
                    break;
            }

            // Calculer les totaux pour la période sélectionnée
            $totaux = [
                'total_events' => array_sum(array_column($stats, 'events_crees')),
                'total_valides' => array_sum(array_column($stats, 'events_valides')),
                'total_en_cours' => array_sum(array_column($stats, 'events_en_cours')),
                'total_refuses' => array_sum(array_column($stats, 'events_refuses')),
                'total_inscriptions' => array_sum(array_column($stats, 'inscriptions')),
            ];

            // Calculer le taux de validation
            $totaux['taux_validation'] = $totaux['total_events'] > 0 
                ? round(($totaux['total_valides'] / $totaux['total_events']) * 100, 1) 
                : 0;

            return [
                'success' => true,
                'period' => $period,
                'data' => $stats,
                'totaux' => $totaux,
            ];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la récupération des statistiques temporelles.'];
        }
    }
}
