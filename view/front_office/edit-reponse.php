<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include '../../controller/reponsecontroller.php';
require_once __DIR__ . '/../../model/reponse.php';

$reponseController = new ReponseController();

$id_reponse = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_reclamation = isset($_GET['reclamation']) ? (int)$_GET['reclamation'] : 0;

$reponse = $reponseController->getReponseById($id_reponse);

// Vérifier que la réponse existe et appartient à l'utilisateur
if (!$reponse || $reponse->getId_user() != $_SESSION['user_id']) {
    header('Location: client-reponse.php?reclamation=' . $id_reclamation);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['contenu']);
    
    if (!empty($contenu)) {
        $reponseController->updateReponse($id_reponse, $contenu);
        header('Location: client-reponse.php?reclamation=' . $id_reclamation);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Modifier Réponse | SecondVoice</title>
    <link rel="stylesheet" href="../assets/style.css" />
    <style>
        .edit-page {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .edit-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
        }
        .edit-card h2 { margin: 0 0 20px; }
        .edit-textarea {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text);
            resize: vertical;
            font-family: inherit;
        }
        .edit-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
        }
        .btn-cancel {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="edit-page">
        <div class="edit-card">
            <h2>✏️ Modifier la réponse</h2>
            <form method="POST" action="">
                <textarea class="edit-textarea" name="contenu" required><?= htmlspecialchars($reponse->getContenu()) ?></textarea>
                <div class="edit-actions">
                    <button type="submit" class="btn-save">💾 Enregistrer</button>
                    <a href="client-reponse.php?reclamation=<?= $id_reclamation ?>" class="btn-cancel">❌ Annuler</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>