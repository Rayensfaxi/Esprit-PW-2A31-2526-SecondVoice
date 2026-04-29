<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include_once '../../../controller/justificationcontroller.php';
include_once '../../../controller/reclamationcontroller.php';
require_once __DIR__ . '/../../../model/justification.php';

$justificationController = new JustificationController();
$reclamationController = new ReclamationController();

$error = "";
$success = "";

$id_reclamation = isset($_GET['reclamation']) ? (int)$_GET['reclamation'] : 0;
$reclamation = $reclamationController->getReclamationById($id_reclamation);

if (!$reclamation) {
    header('Location: gestion-reclamations.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['contenu']);
    $nouveau_statut = $_POST['statut'] ?? $reclamation->getStatut();
    
    if (!empty($contenu)) {
        // Ajouter la justification
        $justification = new Justification();
        $justification->setContenu($contenu);
        $justification->setDate_justification(date('Y-m-d H:i:s'));
        $justification->setId_reclamation($id_reclamation);
        
        $result = $justificationController->addJustification($justification);
        
        // Changer le statut de la réclamation
        if ($nouveau_statut !== $reclamation->getStatut()) {
            $reclamationController->changeStatut($id_reclamation, $nouveau_statut);
        }
        
        if ($result) {
            $success = "Justification ajoutée et statut mis à jour !";
            // Rafraîchir la réclamation
            $reclamation = $reclamationController->getReclamationById($id_reclamation);
        } else {
            $error = "Erreur lors de l'ajout.";
        }
    } else {
        $error = "Le contenu ne peut pas être vide.";
    }
}

// Liste des statuts disponibles
$statuts = ['en_attente', 'en_cours', 'resolu', 'rejete'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ajouter Justification | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/media/favicon-16.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/style.css" />
    <style>
        .form-page {
            padding: 24px;
        }
        
        .form-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 32px;
            max-width: 700px;
        }
        
        .form-header {
            margin-bottom: 24px;
        }
        
        .form-header h2 {
            margin: 0 0 8px;
            font-size: 24px;
        }
        
        .form-header p {
            color: var(--muted);
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 150px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .info-box {
            background: var(--surface);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            border-left: 4px solid var(--primary);
        }
        
        .info-box h4 {
            margin: 0 0 8px;
            font-size: 14px;
        }
        
        .info-box p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }
        
        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            transform: scale(1.02);
        }
        
        .btn-cancel {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            margin-left: 12px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }
        .form-control {
    width: 100%;
    padding: 12px 16px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 12px;
    color: var(--text);
    font-family: inherit;
    font-size: 14px;
}

/* Pour le select spécifiquement */
select.form-control {
    height: 48px;
    min-height: auto;
    cursor: pointer;
}

select.form-control option {
    background: var(--bg-card);
    color: var(--text);
}
    </style>
</head>
<body data-page="voice">
    <div class="overlay" data-overlay></div>
    
    <div class="shell">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-panel">
                <div class="brand-row">
                    <a class="brand" href="../index.php">
                        <img class="brand-logo" src="../../assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
                    </a>
                </div>
                <div class="sidebar-scroll">
                    <div class="nav-section">
                        <div class="nav-title">Gestion</div>
                        <a class="nav-link" href="../index.php" data-nav="home">
                            <span class="nav-icon icon-home"></span>
                            <span>Tableau de bord</span>
                        </a>
                        <a class="nav-link active" href="gestion-reclamations.php" data-nav="voice">
                            <span class="nav-icon icon-mic"></span>
                            <span>Gestion des réclamations</span>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- PAGE -->
        <main class="page with-sidebar">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Ajouter une justification</h1>
                </div>
                <div class="toolbar-actions">
                    <a class="update-button" href="gestion-reclamations.php">Retour</a>
                </div>
            </div>

            <div class="form-page">
                <div class="form-card">
                    <div class="form-header">
                        <h2>📝 Nouvelle Justification</h2>
                        <p>Justifiez le traitement de cette réclamation.</p>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="info-box">
                        <h4>Réclamation #<?= $id_reclamation ?></h4>
                        <p><?= htmlspecialchars(substr($reclamation->getDescription(), 0, 150)) ?><?= strlen($reclamation->getDescription()) > 150 ? '...' : '' ?></p>
                    </div>

                    <form method="POST" action="">
    <!-- Info réclamation -->
    <div class="info-box">
        <h4>Réclamation #<?= $id_reclamation ?></h4>
        <p>Statut actuel : <strong><?= htmlspecialchars($reclamation->getStatut()) ?></strong></p>
        <p><?= htmlspecialchars(substr($reclamation->getDescription(), 0, 150)) ?>...</p>
    </div>

    <!-- ✅ LISTE DÉROULANTE STATUT -->
    <div class="form-group">
        <label for="statut">Changer le statut</label>
        <select class="form-control" name="statut" id="statut" style="min-height: auto; height: 48px;">
            <?php foreach ($statuts as $statut): ?>
                <option value="<?= $statut ?>" <?= $reclamation->getStatut() === $statut ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_', ' ', $statut)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Contenu justification -->
    <div class="form-group">
        <label for="contenu">Contenu de la justification</label>
        <textarea class="form-control" name="contenu" id="contenu" placeholder="Expliquez votre décision..." ></textarea>
    </div>

    <button type="submit" class="btn-submit">💾 Enregistrer</button>
    <a href="gestion-reclamations.php" class="btn-cancel">❌ Annuler</a>
</form>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/app.js"></script>
</body>
</html>