<?php

session_start();
require_once __DIR__ . '/../../controller/UtilisateurController.php';

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    header('Location: login.php?status=email_verify_failed');
    exit;
}

try {
    $controller = new UtilisateurController();
    $ok = $controller->verifyEmailByToken($token);
    header('Location: login.php?status=' . ($ok ? 'email_verified' : 'email_verify_failed'));
    exit;
} catch (Throwable $exception) {
    header('Location: login.php?status=email_verify_failed');
    exit;
}

