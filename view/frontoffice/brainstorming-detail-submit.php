<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/IdeaController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: service-brainstorming.php');
    exit;
}

function redirectWithFormErrors(array $errors, array $old, int $brainstormingId): void
{
    $_SESSION['idea_form_errors'] = $errors;
    $_SESSION['idea_form_old'] = $old;

    header('Location: brainstorming-detail.php?id=' . $brainstormingId);
    exit;
}

try {
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $currentRole = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
    $isAdmin = $currentUserId > 0 && $currentRole === 'admin';

    if ($currentUserId <= 0) {
        throw new RuntimeException('Vous devez etre connecte pour gerer une idee.');
    }

    $action = strtolower(trim((string) ($_POST['action'] ?? 'add')));
    $id = (int) ($_POST['id'] ?? 0);
    $brainstormingId = (int) ($_POST['brainstorming_id'] ?? 0);
    $contenu = trim((string) ($_POST['contenu'] ?? ''));

    $controller = new IdeaController();

    if ($action === 'delete') {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifiant invalide pour la suppression.');
        }

        $deleted = $controller->deleteIdea($id, $currentUserId, $isAdmin);
        if (!$deleted) {
            throw new RuntimeException('Suppression impossible: idee introuvable ou non autorisee.');
        }

        header('Location: brainstorming-detail.php?id=' . $brainstormingId . '&deleted=1');
        exit;
    }

    if (!in_array($action, ['add', 'update'], true)) {
        throw new InvalidArgumentException('Action invalide.');
    }

    $errors = [
        'contenu' => ''
    ];

    if ($contenu === '') {
        $errors['contenu'] = 'Le contenu de l\'idee est obligatoire.';
    } elseif (strlen($contenu) < 10) {
        $errors['contenu'] = 'Le contenu doit contenir au moins 10 caracteres.';
    }

    if ($errors['contenu'] !== '') {
        redirectWithFormErrors(
            $errors,
            ['contenu' => $contenu],
            $brainstormingId
        );
    }

    if ($brainstormingId <= 0) {
        throw new InvalidArgumentException('Identifiant de brainstorming invalide.');
    }

    if ($action === 'update' && $id <= 0) {
        throw new InvalidArgumentException('Identifiant invalide pour la modification.');
    }

    if ($action === 'update') {
        $updated = $controller->updateIdea($id, $contenu, $currentUserId, $isAdmin);
        if (!$updated) {
            throw new RuntimeException('Modification impossible: idee introuvable ou non autorisee.');
        }

        header('Location: brainstorming-detail.php?id=' . $brainstormingId . '&updated=1');
        exit;
    }

    $controller->addIdea($brainstormingId, $currentUserId, $contenu, $isAdmin);
    header('Location: brainstorming-detail.php?id=' . $brainstormingId . '&submitted=1');
    exit;
} catch (Throwable $e) {
    $_SESSION['idea_form_general_error'] = 'Une erreur s\'est produite : ' . $e->getMessage();
    $fallbackBrainstormingId = (int) ($_POST['brainstorming_id'] ?? 0);

    header('Location: brainstorming-detail.php?id=' . $fallbackBrainstormingId);
    exit;
}
