<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/event.php';

class EventController
{
    private PDO $conn;
    private EventModel $eventModel;

    private const ALLOWED_STATUSES = ['en cours', 'validé', 'refusé', 'annulé'];
    private const ALLOWED_RESOURCE_TYPES = ['material', 'rule'];

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            $this->eventModel = new EventModel($this->conn);
            return;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $this->conn = $GLOBALS['conn'];
            $this->eventModel = new EventModel($this->conn);
            return;
        }

        $this->conn = Config::getConnexion();
        $this->eventModel = new EventModel($this->conn);
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

    public function getEventById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT id, name, description, start_date, end_date, deadline, location, `max`, `current`, status, created_by FROM events WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getResourcesByEvent(int $eventId): array
    {
        $stmt = $this->conn->prepare('SELECT id, event_id, resource_name, quantity, `type` FROM resources WHERE event_id = ? ORDER BY id ASC');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
                return ['success' => false, 'message' => 'L’événement est complet.'];
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
            return ['success' => false, 'message' => 'Erreur serveur lors de l’inscription.'];
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

    public function createEvent(array $data): array
    {
        try {
            error_log('CONTROLLER: Début création événement avec données: ' . json_encode($data));
            
            // Le statut est déjà défini par l'appelant (front office ou back office)
            // On s'assure juste qu'il a une valeur valide, sinon on met 'en cours' par défaut
            $status = $data['status'] ?? 'en cours';
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                $status = 'en cours';
            }
            $data['status'] = $status;
            
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
            
            $this->insertResources($eventId, $payload['resources']);
            error_log('CONTROLLER: Ressources insérées');

            $this->conn->commit();
            error_log('CONTROLLER: Transaction validée');
            
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
                throw new InvalidArgumentException('La capacité maximale ne peut pas être inférieure au nombre actuel d’inscrits.');
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

            $deleteResources = $this->conn->prepare('DELETE FROM resources WHERE event_id = ?');
            $deleteResources->execute([$id]);
            $this->insertResources($id, $payload['resources']);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Événement mis à jour avec succès.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Erreur lors de la mise à jour de l’événement.'];
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

            $delRes = $this->conn->prepare('DELETE FROM resources WHERE event_id = ?');
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
            return ['success' => false, 'message' => 'Erreur lors de la suppression de l’événement.'];
        }
    }

    public function canModifyEvent(int $eventId, int $userId, bool $isAdmin): bool
    {
        $event = $this->eventModel->getEventById($eventId);
        if (!$event) {
            return false;
        }
        // Seul le créateur ou un admin peut modifier (mais admin seulement si c'est son événement)
        return $event['created_by'] === $userId;
    }

    public function canDeleteEvent(int $eventId, int $userId, bool $isAdmin): bool
    {
        $event = $this->eventModel->getEventById($eventId);
        if (!$event) {
            return false;
        }
        // Seul le créateur ou un admin peut supprimer (mais admin seulement si c'est son événement)
        return $event['created_by'] === $userId;
    }

    public function isEventOwner(int $eventId, int $userId): bool
    {
        $event = $this->eventModel->getEventById($eventId);
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
            $stmt = $this->conn->prepare("UPDATE events SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Événement introuvable ou statut inchangé.'];
            }

            $message = $status === 'validé' ? 'Événement validé avec succès.' : 
                      ($status === 'refusé' ? 'Événement refusé avec succès.' : 'Statut mis à jour.');
            
            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour du statut.'];
        }
    }

    private function insertResources(int $eventId, array $resources): void
    {
        if ($resources === []) {
            return;
        }

        $stmt = $this->conn->prepare('INSERT INTO resources (event_id, resource_name, quantity, `type`) VALUES (?, ?, ?, ?)');
        foreach ($resources as $resource) {
            $stmt->execute([
                $eventId,
                $resource['resource_name'],
                (int) $resource['quantity'],
                $resource['type'],
            ]);
        }
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
        $event = $this->eventModel->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Événement non trouvé.'];
        }

        $eventCreatedBy = (int) ($event['created_by'] ?? 0);
        if ($eventCreatedBy !== $userId) {
            return ['success' => false, 'message' => 'Vous n\'êtes pas autorisé à demander la suppression de cet événement.'];
        }

        // Vérifier qu'une demande n'est pas déjà en cours
        $stmt = $this->conn->prepare("SELECT id FROM event_deletion_requests WHERE event_id = ? AND status = 'pending'");
        $stmt->execute([$eventId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return ['success' => false, 'message' => 'Une demande de suppression est déjà en cours pour cet événement.'];
        }

        // Créer la demande de suppression
        $stmt = $this->conn->prepare('INSERT INTO event_deletion_requests (event_id, user_id, status, requested_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$eventId, $userId, 'pending']);

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
                e.name as event_name,
                e.status as event_status,
                e.created_by as event_creator_id,
                u.nom as user_nom,
                u.prenom as user_prenom,
                u.email as user_email
            FROM event_deletion_requests edr
            JOIN events e ON edr.event_id = e.id
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
        $stmt = $this->conn->prepare("SELECT event_id FROM event_deletion_requests WHERE id = ? AND status = 'pending'");
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

        $stmt = $this->conn->prepare("UPDATE event_deletion_requests SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$adminId, $requestId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Demande de suppression non trouvée ou déjà traitée.'];
        }

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
        $event = $this->eventModel->getEventById($eventId);
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
            SELECT event_id, new_name, new_description, new_start_date, new_end_date, new_deadline, new_location, new_max 
            FROM event_modification_requests 
            WHERE id = ? AND status = 'pending'
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

        $stmt = $this->conn->prepare("
            UPDATE event_modification_requests 
            SET status = 'rejected', processed_at = NOW(), processed_by = ? 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$adminId, $requestId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Demande de modification non trouvée ou déjà traitée.'];
        }

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
            throw new InvalidArgumentException('Le nom de l’événement est obligatoire.');
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Le statut de l’événement est invalide.');
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

            $name = trim((string) ($resource['resource_name'] ?? ''));
            $type = trim((string) ($resource['type'] ?? 'material'));
            $quantity = max(0, (int) ($resource['quantity'] ?? 0));

            if ($name === '') {
                continue;
            }

            if (!in_array($type, self::ALLOWED_RESOURCE_TYPES, true)) {
                $type = 'material';
            }

            $normalized[] = [
                'resource_name' => $name,
                'quantity' => $quantity,
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
}
