<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include '../../../controller/justificationcontroller.php';
require_once __DIR__ . '/../../../model/justification.php';

$justificationController = new JustificationController();

$id_justification = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$justification = $justificationController->getJustificationById($id_justification);

if (!$justification) {
    header('Location: gestion-justifications.php');
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['contenu']);
    
    if (!empty($contenu)) {
        $justificationController->updateJustification($id_justification, $contenu);
        $success = "Justification modifiée avec succès !";
        // Rafraîchir les données
        $justification = $justificationController->getJustificationById($id_justification);
    } else {
        $error = "Le contenu ne peut pas être vide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Modifier Justification | SecondVoice</title>
    <link rel="stylesheet" href="../../assets/style.css" />
    <style>
        .form-page { padding: 24px; }
        .form-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 32px;
            max-width: 700px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text);
            min-height: 150px;
            resize: vertical;
        }
        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-cancel {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            margin-left: 12px;
        }
        .alert-success { background: #d1fae5; color: #059669; padding: 12px; border-radius: 8px; }
        .alert-error { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; }
    </style>
</head>
<body data-page="voice">
    <div class="shell">
        <!-- Sidebar (identique) -->
        <aside class="sidebar">...</aside>

        <main class="page with-sidebar">
            <div class="topbar">
                <h1 class="page-title">Modifier la justification</h1>
                <a class="update-button" href="gestion-justifications.php">Retour</a>
            </div>

            <div class="form-page">
                <div class="form-card">
                    <h2>✏️ Modifier Justification #<?= $id_justification ?></h2>

                    <?php if (!empty($success)): ?>
                        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Contenu</label>
                            <textarea class="form-control" name="contenu" required><?= htmlspecialchars($justification->getContenu()) ?></textarea>
                        </div>
                        <button type="submit" class="btn-save">💾 Enregistrer</button>
                        <a href="gestion-justifications.php" class="btn-cancel">❌ Annuler</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>