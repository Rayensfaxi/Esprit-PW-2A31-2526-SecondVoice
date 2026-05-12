<?php
declare(strict_types=1);

require_once __DIR__ . '/RendezvousC.php';
require_once __DIR__ . '/ServiceC.php';
require_once __DIR__ . '/../model/Rendezvous.php';

class RendezvousHttpController
{
    private RendezvousC $rendezvous;
    private ServiceC $services;

    public function __construct(?RendezvousC $rendezvous = null, ?ServiceC $services = null)
    {
        $this->rendezvous = $rendezvous ?? new RendezvousC();
        $this->services = $services ?? new ServiceC();
    }

    public function getListViewDataFromRequest(): array
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $filterStatus = trim((string) ($_GET['filterStatus'] ?? ''));

        return [
            'search' => $search,
            'filterStatus' => $filterStatus,
            'liste' => $this->rendezvous->listRendezvous($search, $filterStatus)
        ];
    }

    public function getServicesForForm(): array
    {
        return $this->services->listServices();
    }

    public function getDetailsViewDataFromRequest(): array
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            return ['rdv' => null, 'error' => 'ID de rendez-vous manquant.'];
        }

        $rdv = $this->rendezvous->getRendezvousById($id);
        if (!$rdv) {
            return ['rdv' => null, 'error' => 'Rendez-vous introuvable.'];
        }

        return ['rdv' => $rdv, 'error' => ''];
    }

    public function handleUpdateRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['update_rdv'])) {
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $idCitoyen = (int) ($_POST['id_citoyen'] ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $assistant = trim((string) ($_POST['assistant'] ?? ''));
        $dateRdv = trim((string) ($_POST['date_rdv'] ?? ''));
        $heureRdv = trim((string) ($_POST['heure_rdv'] ?? ''));
        $mode = trim((string) ($_POST['mode'] ?? ''));
        $remarques = trim((string) ($_POST['remarques'] ?? ''));
        $statut = trim((string) ($_POST['statut'] ?? 'En attente'));

        if ($id <= 0 || $idCitoyen <= 0 || $serviceId <= 0 || $assistant === '' || $dateRdv === '' || $heureRdv === '') {
            $this->redirectWithError('Parametres invalides.');
        }

        $currentRdv = $this->rendezvous->getRendezvousById($id);
        if ($currentRdv && str_contains(strtolower((string) $currentRdv->getStatut()), 'annul')) {
            $this->redirectWithError('Impossible de modifier un rendez-vous annule.');
        }

        $heureTime = strtotime($heureRdv);
        $startTime = strtotime('08:10');
        $endTime = strtotime('17:30');

        if ($heureTime === false || $heureTime < $startTime || $heureTime > $endTime) {
            $this->redirectWithError('Heure invalide (08:10-17:30).');
        }

        if ($remarques === '') {
            $this->redirectWithError('Remarques obligatoires.');
        }

        $wordCount = preg_match_all('/\S+/', $remarques);
        if ($wordCount > 50) {
            $this->redirectWithError('Max 50 mots pour les remarques.');
        }

        try {
            $rdv = new Rendezvous(
                $id,
                $idCitoyen,
                $serviceId,
                '',
                $assistant,
                new DateTime($dateRdv),
                $heureRdv,
                $mode,
                $remarques,
                $statut !== '' ? $statut : 'En attente'
            );

            $this->rendezvous->updateRendezvous($rdv, $id);
            $this->redirectToHome(['status' => 'updated']);
        } catch (Throwable $exception) {
            $this->redirectWithError('Impossible de modifier ce rendez-vous.');
        }
    }

    public function handleToggleStatusRequest(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $action = trim((string) ($_GET['action'] ?? ''));

        if ($id <= 0 || !in_array($action, ['confirm', 'wait'], true)) {
            $this->redirectWithError('Parametres invalides.');
        }

        $currentRdv = $this->rendezvous->getRendezvousById($id);
        if ($currentRdv && str_contains(strtolower((string) $currentRdv->getStatut()), 'annul')) {
            $this->redirectWithError('Impossible de modifier un rendez-vous annule.');
        }

        $this->rendezvous->updateStatut($id, $action === 'confirm' ? 'Confirmé' : 'En attente');
        $this->redirectToHome();
    }

    public function handleDeleteRequest(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->rendezvous->deleteRendezvous($id);
        }

        $this->redirectToHome();
    }

    public function handleGenerateQrRequest(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID manquant.']);
            return;
        }

        try {
            $qrPath = $this->rendezvous->generateQRCode($id);
            if (!$qrPath) {
                echo json_encode(['success' => false, 'message' => 'Impossible de generer le QR Code.']);
                return;
            }

            echo json_encode([
                'success' => true,
                'qrPath' => $qrPath,
                'viewUrl' => $this->rendezvous->getRendezvousPublicUrl($id)
            ]);
        } catch (Throwable $exception) {
            echo json_encode(['success' => false, 'message' => 'Erreur serveur pendant la generation du QR Code.']);
        }
    }

    public function handleCalendarEventsRequest(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $serviceId = isset($_GET['service_id']) && $_GET['service_id'] !== '' ? (int) $_GET['service_id'] : null;
            echo json_encode($this->rendezvous->getCalendarData($serviceId));
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(['error' => 'Impossible de charger le calendrier.']);
        }
    }

    private function redirectWithError(string $message): void
    {
        $this->redirectToHome(['error' => $message]);
    }

    private function redirectToHome(array $params = []): void
    {
        $target = 'HomeRendezvous.php';
        if ($params !== []) {
            $target .= '?' . http_build_query($params);
        }

        header('Location: ' . $target);
        exit;
    }
}
?>
