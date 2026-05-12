<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../GoalController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$goalCtrl = new GoalController();

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $goalId = (int) $_GET['id'];
    $ownsGoal = false;
    foreach ($goalCtrl->getGoalsByUser($user_id) as $g) {
        if ((int) $g['id'] === $goalId) {
            $ownsGoal = true;
            break;
        }
    }

    $deleted = $ownsGoal ? $goalCtrl->deleteGoal($goalId, $user_id) : false;
    $_SESSION['flash'] = $deleted
        ? ['type' => 'success', 'message' => "Demande supprimée avec succès."]
        : ['type' => 'error',   'message' => "Suppression impossible (demande déjà prise en charge)."];
    header('Location: mes-accompagnements.php');
    exit;
}

$db = Config::getConnexion();
$assistants = [];
try {
    $stmt = $db->query("SELECT id, nom FROM utilisateur WHERE LOWER(role) IN ('assistant', 'agent') ORDER BY nom ASC");
    $assistants = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
}

$isEdit = false;
$existingGoal = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $goalId = (int) $_GET['id'];
    foreach ($goalCtrl->getGoalsByUser($user_id) as $g) {
        if ((int) $g['id'] === $goalId) {
            $existingGoal = $g;
            $isEdit = true;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_goal' || $action === 'update_goal') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? '';
        $assistant_id = (int) ($_POST['assistant_id'] ?? 0);

        if ($title === '' || $description === '' || $type === '' || $assistant_id <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.'];
        } else {
            $newGoal = new Goal($user_id, $assistant_id, $title, $description, $type);
            if ($action === 'create_goal') {
                $goalCtrl->createGoal($newGoal);
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Votre demande d'accompagnement a été soumise avec succès."];
                header('Location: mes-accompagnements.php');
                exit;
            }

            $goal_id = (int) ($_POST['goal_id'] ?? 0);
            $canUpdate = false;
            foreach ($goalCtrl->getGoalsByUser($user_id) as $g) {
                if ((int) $g['id'] === $goal_id) {
                    $canUpdate = true;
                    break;
                }
            }

            if (!$canUpdate) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => "Action non autorisée."];
            } else {
                if ($goalCtrl->updateGoalByUser($newGoal, $goal_id)) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "Votre demande a été modifiée."];
                    header('Location: mes-accompagnements.php');
                    exit;
                }
                $_SESSION['flash'] = ['type' => 'error', 'message' => "Modification impossible (demande déjà en cours de traitement)."];
            }
        }
    }
}

$typeLabels = [
    'cv'           => '📄 Rédaction de CV',
    'cover_letter' => '✉️ Lettre de motivation',
    'linkedin'     => '🔗 Optimisation LinkedIn',
    'interview'    => '🎤 Préparation entretien',
    'other'        => '📌 Autre',
];

$prefillDesc = '';
$prefillType = '';
$prefillAssistant = 0;
$cameFromEchoMe = false;
$cameFromSmartGuide = false;

if (!$isEdit) {
    if (isset($_GET['prefill_description'])) {
        $prefillDesc = mb_substr(trim((string) $_GET['prefill_description']), 0, 1000);
    }
    if (isset($_GET['prefill_type'])) {
        $candidate = (string) $_GET['prefill_type'];
        if (in_array($candidate, ['cv', 'cover_letter', 'linkedin', 'interview', 'other'], true)) {
            $prefillType = $candidate;
        }
    }
    if (isset($_GET['prefill_assistant'])) {
        $prefillAssistant = (int) $_GET['prefill_assistant'];
    }

    $from = strtolower((string) ($_GET['from'] ?? ''));
    if ($from === 'smartguide') {
        $cameFromSmartGuide = true;
    } elseif ($prefillDesc !== '') {
        $cameFromEchoMe = true;
    }
}

