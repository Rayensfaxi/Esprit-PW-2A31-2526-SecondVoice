<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/IdeaController.php';
require_once __DIR__ . '/../../controller/VoteController.php';

function redirectToBrainstorming(int $brainstormingId): void
{
    if ($brainstormingId > 0) {
        header('Location: brainstorming-detail.php?id=' . $brainstormingId);
        exit;
    }

    header('Location: service-brainstorming.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToBrainstorming(0);
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$ideeId = (int) ($_POST['idee_id'] ?? 0);
$brainstormingId = (int) ($_POST['brainstorming_id'] ?? 0);
$voteType = strtolower(trim((string) ($_POST['type'] ?? '')));

if ($currentUserId <= 0) {
    $_SESSION['vote_error'] = 'Vous devez etre connecte pour voter.';
    redirectToBrainstorming($brainstormingId);
}

if ($ideeId <= 0 || $brainstormingId <= 0) {
    $_SESSION['vote_error'] = 'Identifiants invalides.';
    redirectToBrainstorming($brainstormingId);
}

if (!in_array($voteType, ['like', 'dislike'], true)) {
    $_SESSION['vote_error'] = 'Type de vote invalide.';
    redirectToBrainstorming($brainstormingId);
}

try {
    $ideaController = new IdeaController();
    $voteController = new VoteController();
    $idea = $ideaController->getIdeaById($ideeId);

    if (!$idea || (int) $idea['brainstorming_id'] !== $brainstormingId || strtolower((string) $idea['statut']) !== 'approuve') {
        $_SESSION['vote_error'] = 'Cette idee ne peut pas recevoir de vote.';
        redirectToBrainstorming($brainstormingId);
    }

    if (!$voteController->isVotePeriodOpen($brainstormingId)) {
        $_SESSION['vote_error'] = 'La periode de vote est fermee.';
        redirectToBrainstorming($brainstormingId);
    }

    $existingVote = $voteController->getUserVote($currentUserId, $ideeId);

    if ($existingVote) {
        if ((string) $existingVote['type'] === $voteType) {
            $_SESSION['vote_error'] = 'Vous avez deja vote de cette maniere.';
        } else {
            $success = $voteController->updateVote($currentUserId, $ideeId, $voteType);
            if ($success) {
                $voteController->updateVoteCounts($ideeId);
                $_SESSION['vote_success'] = 'Votre vote a ete mis a jour.';
            } else {
                $_SESSION['vote_error'] = 'Erreur lors de la mise a jour du vote.';
            }
        }
    } else {
        $success = $voteController->addVote($currentUserId, $ideeId, $voteType);
        if ($success) {
            $voteController->updateVoteCounts($ideeId);
            $_SESSION['vote_success'] = 'Votre vote a ete enregistre.';
        } else {
            $_SESSION['vote_error'] = 'Erreur lors de l\'enregistrement du vote.';
        }
    }
} catch (Throwable $e) {
    $_SESSION['vote_error'] = 'Une erreur est survenue lors du vote.';
}

redirectToBrainstorming($brainstormingId);
