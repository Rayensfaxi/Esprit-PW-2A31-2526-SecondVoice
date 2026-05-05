<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/VoteController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($currentUserId <= 0) {
    $_SESSION['vote_error'] = 'Vous devez être connecté pour voter.';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'index.php');
    exit;
}

$ideeId = (int) ($_POST['idee_id'] ?? 0);
$brainstormingId = (int) ($_POST['brainstorming_id'] ?? 0);
$voteType = strtolower(trim((string) ($_POST['type'] ?? '')));

if ($ideeId <= 0 || $brainstormingId <= 0) {
    $_SESSION['vote_error'] = 'Identifiants invalides.';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'index.php');
    exit;
}

if (!in_array($voteType, ['like', 'dislike'], true)) {
    $_SESSION['vote_error'] = 'Type de vote invalide.';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'index.php');
    exit;
}

try {
    $voteController = new VoteController();
    $existingVote = $voteController->getUserVote($currentUserId, $ideeId);

    if ($existingVote) {
        // Update existing vote
        if ($existingVote['type'] === $voteType) {
            $_SESSION['vote_error'] = 'Vous avez déjà voté de cette manière.';
        } else {
            $success = $voteController->updateVote($currentUserId, $ideeId, $voteType);
            if ($success) {
                $voteController->updateVoteCounts($ideeId);
                $_SESSION['vote_success'] = 'Votre vote a été mis à jour.';
            } else {
                $_SESSION['vote_error'] = 'Erreur lors de la mise à jour du vote.';
            }
        }
    } else {
        // Add new vote
        $success = $voteController->addVote($currentUserId, $ideeId, $voteType);
        if ($success) {
            $voteController->updateVoteCounts($ideeId);
            $_SESSION['vote_success'] = 'Votre vote a été enregistré.';
        } else {
            $_SESSION['vote_error'] = 'Erreur lors de l\'enregistrement du vote.';
        }
    }
} catch (Exception $e) {
    $_SESSION['vote_error'] = 'Une erreur est survenue lors du vote.';
}

header('Location: brainstorming-detail.php?id=' . $brainstormingId);
exit;
