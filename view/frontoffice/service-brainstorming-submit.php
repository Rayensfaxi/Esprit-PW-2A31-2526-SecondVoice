<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/BrainstormingController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: service-brainstorming.php');
    exit;
}

function redirectWithFormErrors(array $errors, array $old, string $action, int $id): void
{
    $_SESSION['brainstorming_form_errors'] = $errors;
    $_SESSION['brainstorming_form_old'] = $old;
    $_SESSION['brainstorming_form_action'] = $action;
    $_SESSION['brainstorming_form_id'] = $id;

    $redirect = 'service-brainstorming.php';
    if ($action === 'update' && $id > 0) {
        $redirect .= '?edit=' . $id;
    }

    header('Location: ' . $redirect);
    exit;
}

try {
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $currentRole = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
    $allowAdminFrontofficeBypass = false;

    if ($currentUserId <= 0) {
        throw new RuntimeException('Vous devez etre connecte pour gerer une idee.');
    }

    $action = strtolower(trim((string) ($_POST['action'] ?? 'add')));
    $id = (int) ($_POST['id'] ?? 0);
    $titre = trim((string) ($_POST['titre'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $categorie = trim((string) ($_POST['categorie'] ?? ''));

    $controller = new BrainstormingController();

    if ($action === 'delete') {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifiant invalide pour la suppression.');
        }

        $deleted = $controller->deleteBrainstorming($id, $currentUserId, $allowAdminFrontofficeBypass);
        if (!$deleted) {
            throw new RuntimeException('Suppression impossible: idee introuvable ou non autorisee.');
        }

        header('Location: service-brainstorming.php?deleted=1');
        exit;
    }

    if (!in_array($action, ['add', 'update'], true)) {
        throw new InvalidArgumentException('Action invalide.');
    }

    $errors = [
        'titre' => '',
        'description' => '',
        'categorie' => ''
    ];

    if ($titre === '') {
        $errors['titre'] = 'Le titre est obligatoire.';
    } elseif (strlen($titre) < 3) {
        $errors['titre'] = 'Le titre doit contenir au moins 3 caracteres.';
    } elseif (preg_match('/[0-9]/', $titre)) {
        $errors['titre'] = 'Le titre ne peut pas contenir de chiffres.';
    }

    if ($description === '') {
        $errors['description'] = 'La description est obligatoire.';
    } elseif (strlen($description) < 10) {
        $errors['description'] = 'La description doit contenir au moins 10 caracteres.';
    } elseif (preg_match('/[0-9]/', $description)) {
        $errors['description'] = 'La description ne peut pas contenir de chiffres.';
    }

    if ($categorie === '') {
        $errors['categorie'] = 'La categorie est obligatoire.';
    }

    if ($errors['titre'] !== '' || $errors['description'] !== '' || $errors['categorie'] !== '') {
        redirectWithFormErrors(
            $errors,
            [
                'titre' => $titre,
                'description' => $description,
                'categorie' => $categorie
            ],
            $action,
            $id
        );
    }

    if ($action === 'update' && $id <= 0) {
        throw new InvalidArgumentException('Identifiant invalide pour la modification.');
    }

    if ($action === 'update') {
        $updated = $controller->updateBrainstorming($id, $titre, $description, $categorie, $currentUserId, $allowAdminFrontofficeBypass);
        if (!$updated) {
            throw new RuntimeException('Modification impossible: idee introuvable ou non autorisee.');
        }

        header('Location: service-brainstorming.php?updated=1');
        exit;
    }

    $controller->addBrainstormingForUser($titre, $description, $categorie, $currentUserId);
    header('Location: service-brainstorming.php?submitted=1');
    exit;
} catch (Throwable $e) {
    $_SESSION['brainstorming_form_general_error'] = 'Une erreur s\'est produite : ' . $e->getMessage();
    $fallbackAction = strtolower(trim((string) ($_POST['action'] ?? 'add')));
    $fallbackId = (int) ($_POST['id'] ?? 0);
    $redirect = 'service-brainstorming.php';
    if ($fallbackAction === 'update' && $fallbackId > 0) {
        $redirect .= '?edit=' . $fallbackId;
    }

    header('Location: ' . $redirect);
    exit;
}
