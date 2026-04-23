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

// ✅ SUPPRESSION EN POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_delete = (int)$_POST['id_reclamation'];
    
    $rec = $reclamationController->getReclamationById($id_delete);
    
    if ($rec && $rec->getId_user() == $id_user && $rec->getStatut() === 'en_attente') {
        $reclamationController->deleteReclamation($id_delete);
    }
    
    header('Location: client-reclamations.php');
    exit;
}

// ✅ MODIFICATION EN POST
/*if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id_update = (int)$_POST['id_reclamation'];
    $new_description = trim($_POST['description']);
    
    $rec = $reclamationController->getReclamationById($id_update);
    if ($rec && $rec->getId_user() == $id_user && !empty($new_description)) {
        $reclamationController->updateReclamation(
            $id_update,
            $new_description,
            $rec->getStatut(),
            $id_user
        );
    }
    
    header('Location: client-reclamations.php');
    exit;
}*/

$reclamations = $reclamationController->getReclamationsByUserAndStatut($id_user, 'en_attente');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mes Réclamations | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
    <script>
        const savedTheme = localStorage.getItem("theme");
        const initialTheme = savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
        document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/reclamations-list.css" />
</head>
<body>
    <div class="page-shell">
        <!-- Header identique -->
        <header class="site-header">
            <div class="container nav-inner">
                <a class="brand" href="index.php">
                    <img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
                </a>
                <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
                    <span class="icon-lines"></span>
                </button>
                <div class="nav" data-nav>
                    <nav>
                        <ul class="nav-links">
                            <li><a href="index.php">Accueil</a></li>
                            <li><a href="about.php">A propos</a></li>
                            <li><a href="services.php">Services</a></li>
                            <li><a href="blog.php">Blog</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </nav>
                    <div class="header-actions">
                        <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                            <span class="theme-toggle-label" data-theme-label>Clair</span>
                        </button>
                        <div class="user-shell" data-user-shell>
                            <a class="icon-btn user-trigger" href="login.php" aria-label="Profil">
                                <span>Profil</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="reclamations-page">
            <!-- Watermark -->
            <div class="chat-watermark">
                <img src="../assets/media/secondvoice-logo.png" alt="SecondVoice" />
            </div>

            <div class="container">
                <!-- Tabs -->
                <div class="tabs-menu">
                    <a href="service-reclamations.php" class="tab-button">Nouvelle réclamation</a>
                    <a href="client-reclamations.php" class="tab-button active">Réclamations en cours</a>
                    <!--<a href="client-reponse.php" class="tab-button">Réponses</a>-->
                </div>

                <!-- Liste des réclamations -->
                <div class="reclamations-list">
                    <?php if (empty($reclamations)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>Aucune réclamation en attente</h3>
                            <p>Vous n'avez pas de réclamations en cours de traitement.</p>
                            <!--<a href="service-reclamations.php" class="btn btn-primary">Créer une réclamation</a>
                    --></div>
                    <?php else: ?>
                        <?php foreach ($reclamations as $reclamation): 
                            // Vérifier s'il y a des réponses pour cette réclamation
                            /*$reponses = $reponseController->getReponsesByReclamation($reclamation->getId_reclamation());
                            $hasResponses = count($reponses) > 0;*/
                            
                            // Tronquer la description (1ère ligne + ...)
                            $description = $reclamation->getDescription();
                            $firstLine = strtok($description, "\n"); // Prend la 1ère ligne
                            if (strlen($firstLine) > 60) {
                                $firstLine = substr($firstLine, 0, 60) . '...';
                            } elseif ($description !== $firstLine || strlen($description) > strlen($firstLine)) {
                                $firstLine .= '...';
                            }
                        ?>
                            <div class="reclamation-item">
                                <!-- ✅ CLIQUEZ SUR LA BULLE = MODIFICATION -->
                                <a href="edit-reclamation.php?id=<?= $reclamation->getId_reclamation() ?>" 
                                class="reclamation-bubble-link">
                                    
                                    <div class="reclamation-bubble">
                                        <div class="reclamation-content">
                                            <p class="reclamation-text"><?= htmlspecialchars($firstLine) ?></p>
                                            <span class="reclamation-date">
                                                <?= date('d/m/Y H:i', strtotime($reclamation->getDate_creation())) ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>

                                <!-- Supprimer reste séparé -->
                                <div class="reclamation-actions">
                                    <form method="POST" action="" style="display:inline;" 
                                        onsubmit="return confirm('Supprimer cette réclamation ?');">
                                        <input type="hidden" name="id_reclamation" value="<?= $reclamation->getId_reclamation() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-delete">🗑️ Supprimer</button>
                                    </form>
                                </div>
                            
                                <!-- Bouton réponse -->
                                <?php /*if ($hasResponses): ?>
                                    <a href="client-reponse.php?id_reclamation=<?= $reclamation->getId_reclamation() ?>" 
                                       class="response-btn response-btn--active">
                                        <span class="response-icon">💬</span>
                                        <span class="response-text">Voir les réponses</span>
                                        <span class="response-count"><?= count($reponses) ?></span>
                                    </a>
                                <?php else: ?>
                                    <button class="response-btn response-btn--empty" disabled>
                                        <span class="response-icon">🕐</span>
                                        <span class="response-text">Aucune réponse</span>
                                    </button>
                                <?php endif;*/ ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>  

        <footer class="footer">
            <div class="container">
                <div class="footer-bottom">
                    <span>&copy; 2026 SecondVoice. Tous droits réservés.</span>
                    <div class="footer-links">
                        <a href="services.php">Services</a>
                        <a href="contact.php">Contact</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>