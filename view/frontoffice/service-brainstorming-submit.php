<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/BrainstormingController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: service-brainstorming.php');
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new RuntimeException('Vous devez etre connecte pour soumettre une idee.');
    }

    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = trim($_POST['categorie'] ?? '');
    $userId = (int) $_SESSION['user_id'];

    // Validation en PHP - aucun HTML required utilisÃ©
    $errors = [];

    if (empty($titre)) {
        $errors[] = 'Le titre est obligatoire.';
    } elseif (strlen($titre) < 3) {
        $errors[] = 'Le titre doit contenir au moins 3 caracteres.';
    } elseif (preg_match('/[0-9]/', $titre)) {
        $errors[] = 'Le titre ne peut pas contenir de chiffres.';
    }

    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    } elseif (strlen($description) < 10) {
        $errors[] = 'La description doit contenir au moins 10 caracteres.';
    } elseif (preg_match('/[0-9]/', $description)) {
        $errors[] = 'La description ne peut pas contenir de chiffres.';
    }

    if (empty($categorie)) {
        $errors[] = 'La categorie est obligatoire.';
    }

    if (!empty($errors)) {
        $error = implode(' ', $errors);
        throw new InvalidArgumentException($error);
    }

    $controller = new BrainstormingController();
    $id = $controller->addBrainstorming($titre, $description, $categorie, $userId);

    // Redirection avec message de succÃ¨s
    header('Location: service-brainstorming.php?submitted=1');
    exit;
} catch (Exception $e) {
    $error = 'Une erreur s\'est produite : ' . $e->getMessage();
}

// En cas d'erreur, retour au formulaire
header('Location: service-brainstorming.php?error=' . urlencode($error ?? 'Une erreur s\'est produite'));
exit;
