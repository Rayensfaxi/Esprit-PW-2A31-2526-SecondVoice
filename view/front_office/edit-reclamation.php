<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$id_user = $_SESSION['user_id'];

include '../../controller/reclamationcontroller.php';
require_once __DIR__ . '/../../model/reclamation.php';

$reclamationController = new ReclamationController();
$error = "";

// Vérifier que l'ID est fourni
if (!isset($_GET['id'])) {
    header('Location: client-reclamations.php');
    exit;
}

$id_reclamation = (int)$_GET['id'];
$reclamation = $reclamationController->getReclamationById($id_reclamation);

// Vérifier : existe + appartient à l'utilisateur + statut "en_attente"
if (!$reclamation || 
    $reclamation->getId_user() != $id_user || 
    $reclamation->getStatut() !== 'en_attente') {
    header('Location: client-reclamations.php');
    exit;
}

// Traitement de la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $new_description = trim($_POST['description']);
    
    if (!empty($new_description)) {
        $reclamationController->updateReclamation(
            $id_reclamation,
            $new_description,
            $reclamation->getStatut(),
            $id_user
        );
        header('Location: client-reclamations.php?msg=updated');
        exit;
    } else {
        $error = "La description ne peut pas être vide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Modifier Réclamation | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/media/favicon-16.png" />
    <script>
        const savedTheme = localStorage.getItem("theme");
        const initialTheme = savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
        document.documentElement.dataset.theme = initialTheme;
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/reclamations-list.css" />
    <style>
        .edit-page {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .edit-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .edit-header {
            margin-bottom: 25px;
        }
        .edit-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            margin-bottom: 8px;
        }
        .edit-header p {
            color: var(--muted);
            font-size: 14px;
        }
        .edit-date {
            display: block;
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 13px;
        }
        .edit-form textarea {
            width: 100%;
            min-height: 200px;
            padding: 15px;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-family: 'Manrope', sans-serif;
            font-size: 15px;
            resize: vertical;
            line-height: 1.6;
        }
        .edit-form textarea:focus {
            outline: none;
            border-color: var(--primary);
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
            font-weight: 600;
            cursor: pointer;
            font-family: 'Manrope', sans-serif;
        }
        .btn-cancel {
            background: transparent;
            color: var(--muted);
            border: 2px solid var(--border);
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            font-family: 'Manrope', sans-serif;
        }
        .btn-cancel:hover {
            border-color: var(--text);
            color: var(--text);
        }
        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <div class="container nav-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
                </a>
            </div>
        </header>

        <main class="edit-page">
            <div class="edit-card">
                <div class="edit-header">
                    <h2>✏️ Modifier la réclamation</h2>
                    <p>Modifiez le contenu de votre réclamation ci-dessous.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <span class="edit-date">
                    📅 Créée le <?= date('d/m/Y à H:i', strtotime($reclamation->getDate_creation())) ?>
                </span>

                <form method="POST" action="" class="edit-form">
                    <textarea name="description" required><?= htmlspecialchars($reclamation->getDescription()) ?></textarea>
                    <input type="hidden" name="action" value="update">
                    
                    <div class="edit-actions">
                        <button type="submit" class="btn-save">💾 Enregistrer les modifications</button>
                        <a href="client-reclamations.php" class="btn-cancel">❌ Annuler</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>