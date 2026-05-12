<?php
declare(strict_types=1);

require_once __DIR__ . '/ServiceC.php';
require_once __DIR__ . '/../model/Service.php';

final class ServiceHttpController
{
    private ServiceC $serviceC;

    public function __construct(?ServiceC $serviceC = null)
    {
        $this->serviceC = $serviceC ?? new ServiceC();
    }

    public function handleCreate(array $post): void
    {
        [$nom, $description, $error] = $this->extractAndValidatePayload($post);
        if ($error !== null) {
            $this->redirectToServiceHome(['error' => $error]);
        }

        try {
            $service = new Service(null, $nom, $description);
            $this->serviceC->addService($service);
            $this->redirectToServiceHome(['success' => 'Service ajoute avec succes.']);
        } catch (Throwable $e) {
            $this->redirectToServiceHome(['error' => $e->getMessage()]);
        }
    }

    public function handleUpdate(array $post): void
    {
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        if ($id <= 0) {
            $this->redirectToServiceHome(['error' => 'ID de service invalide.']);
        }

        [$nom, $description, $error] = $this->extractAndValidatePayload($post);
        if ($error !== null) {
            $this->redirectToServiceHome(['error' => $error]);
        }

        try {
            $service = new Service($id, $nom, $description);
            $this->serviceC->updateService($service, $id);
            $this->redirectToServiceHome(['success' => 'Service modifie avec succes.']);
        } catch (Throwable $e) {
            $this->redirectToServiceHome(['error' => $e->getMessage()]);
        }
    }

    public function handleDelete(array $get): void
    {
        $id = isset($get['id']) ? (int) $get['id'] : 0;
        if ($id <= 0) {
            $this->redirectToServiceHome(['error' => 'ID non specifie.']);
        }

        try {
            $this->serviceC->deleteService($id);
            $this->redirectToServiceHome(['success' => 'Service supprime avec succes.']);
        } catch (Throwable $e) {
            $this->redirectToServiceHome(['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{0:string,1:string,2:?string}
     */
    private function extractAndValidatePayload(array $payload): array
    {
        $nom = trim((string) ($payload['nom'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        if ($nom === '') {
            return ['', '', 'Le nom du service est obligatoire.'];
        }
        if (mb_strlen($nom) < 3) {
            return ['', '', 'Le nom doit contenir au moins 3 caracteres.'];
        }
        if ($description === '') {
            return ['', '', 'La description est obligatoire.'];
        }
        if (mb_strlen($description) < 10) {
            return ['', '', 'La description doit contenir au moins 10 caracteres.'];
        }

        return [$nom, $description, null];
    }

    /**
     * @param array<string,string> $params
     */
    private function redirectToServiceHome(array $params = []): void
    {
        $query = $params === [] ? '' : ('?' . http_build_query($params));
        header('Location: HomeService.php' . $query);
        exit;
    }
}

