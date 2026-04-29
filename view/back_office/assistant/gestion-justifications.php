<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include '../../../controller/justificationcontroller.php';
include '../../../controller/reclamationcontroller.php';

$justificationController = new JustificationController();
$reclamationController = new ReclamationController();

$justifications = $justificationController->listJustifications();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestion Justifications | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/media/favicon-16.png" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/style.css" />
    <style>
        .tabs-menu {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            padding: 4px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            width: fit-content;
        }
        
        .tab-button {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .tab-button:hover { color: var(--text-primary); }
        .tab-button.active { background: var(--accent); color: white; }
        
        .table-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            text-align: left;
            padding: 16px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .table tr:hover {
            background: var(--surface);
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            margin-right: 8px;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-delete {
            background: #dc2626;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--muted);
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .btn-edit {
            background: #f59e0b;
            color: white;
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
                        <a class="nav-link" href="gestion-reclamations.php" data-nav="voice">
                            <span class="nav-icon icon-mic"></span>
                            <span>Gestion des réclamations</span>
                        </a>
                        <a class="nav-link active" href="gestion-justifications.php" data-nav="settings">
                            <span class="nav-icon icon-settings"></span>
                            <span>Justifications</span>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- PAGE -->
        <main class="page with-sidebar">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Gestion des justifications</h1>
                    <div class="page-subtitle">Liste des justifications enregistrées</div>
                </div>
            </div>

            <div class="tabs-menu">
                <a href="gestion-reclamations.php" class="tab-button">Réclamations</a>
                <a href="gestion-justifications.php" class="tab-button active">Justifications</a>
            </div>

            <div class="page-grid users-page">
                <section class="users-hero">
                    <div class="users-header">
                        <div>
                            <h2 class="section-title">Liste des justifications</h2>
                            <p class="helper">Consultez et gérez les justifications des réclamations.</p>
                        </div>
                    </div>
                </section>

                <section class="table-card">
                    <table class="table users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Contenu</th>
                                <th>Réclamation</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($justifications) > 0): ?>
                                <?php foreach ($justifications as $justification): 
                                    $rec = $reclamationController->getReclamationById($justification->getId_reclamation());
                                ?>
                                    <tr>
                                        <td><strong>#<?= $justification->getId_justification() ?></strong></td>
                                        <td><?= htmlspecialchars(substr($justification->getContenu(), 0, 80)) ?><?= strlen($justification->getContenu()) > 80 ? '...' : '' ?></td>
                                        <td>#<?= $justification->getId_reclamation() ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($justification->getDate_justification())) ?></td>
                                        <td>
                                          <div class="table-actions">
                                              
                                              <!-- ✅ BOUTON MODIFIER -->
                                              <a href="edit-justification.php?id=<?= $justification->getId_justification() ?>" 
                                                class="btn-action btn-edit">Modifier</a>
                                              
                                              <a href="delete-justification.php?id=<?= $justification->getId_justification() ?>" 
                                                class="btn-action btn-delete" 
                                                onclick="return confirm('Supprimer cette justification ?')">Supprimer</a>
                                          </div>
                                      </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <div class="empty-icon">📝</div>
                                        <p>Aucune justification trouvée.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
    </div>

    <script src="../../assets/app.js"></script>
</body>
</html>