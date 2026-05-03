<?php
require_once '../../../controller/RendezvousC.php';
$rendezvousC = new RendezvousC();

// On récupère les stats pour l'affichage
$allRdvStats = $rendezvousC->listRendezvous();
$stats = ['total' => 0, 'confirme' => 0, 'annule' => 0, 'attente' => 0];
foreach ($allRdvStats as $r) {
    $stats['total']++;
    if ($r->getStatut() == 'Confirmé') $stats['confirme']++;
    elseif ($r->getStatut() == 'Annulé') $stats['annule']++;
    elseif ($r->getStatut() == 'En attente') $stats['attente']++;
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des rendez-vous</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/style.css" />
  </head>
  <body data-page="subscription">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="../index.html"><img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="../index.html" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="../gestion-utilisateurs.html" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="../Service/HomeService.php" data-nav="services"><span class="nav-icon icon-card"></span><span>Gestion des services</span></a>
              <a class="nav-link" href="HomeRendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="../gestion-accompagnements.html" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="../gestion-documents.html" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des documents</span></a>
              <a class="nav-link" href="../gestion-reclamations.html" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="../settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des rendez-vous</h1>
            <div class="page-subtitle">Suivez, confirmez et gérez les rendez-vous de la plateforme.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../../frontend/index.html">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
            <div class="profile-menu-wrap" data-profile-wrap>
              <button class="profile-trigger" data-profile-toggle aria-label="Open profile menu">
                <img class="topbar-avatar" src="../assets/media/profile-avatar.svg" alt="Profile" />
              </button>
              <div class="profile-dropdown" data-profile-menu>
                <div class="profile-dropdown-card">
                  <div class="profile-thumb"><img src="../assets/media/profile-avatar.svg" alt="Profile avatar" /></div>
                  <div>
                    <strong>Admin SecondVoice</strong>
                    <span>Administrateur</span>
                  </div>
                </div>
                <div class="profile-menu-list">
                  <a class="menu-link" href="../settings.html"><span class="menu-icon icon-settings"></span><span>Parametres</span></a>
                </div>
                <button class="logout-button" type="button">Déconnexion <span class="logout-arrow">-></span></button>
              </div>
            </div>
          </div>
        </div>

        <div class="page-grid">
           <?php include 'rendezvousList.php'; ?>
        </div>
      </main>
    </div>

    <style>
      @media print {
        body * {
          visibility: hidden;
        }
        .table-card, .table-card * {
          visibility: visible;
        }
        .table-card {
          position: absolute;
          left: 0;
          top: 0;
          width: 100%;
        }
        .users-actions, .actions-column, th:last-child, td:last-child {
          display: none !important;
        }
        .card-header h3 {
          visibility: visible;
          margin-bottom: 20px;
        }
        .table {
          width: 100%;
          border-collapse: collapse;
        }
        .table th, .table td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: left;
        }
        .status-pill {
          border: none !important;
          padding: 0 !important;
        }
      }
    </style>

    <?php include 'detailsRendezvous.php'; ?>
    <?php include 'updateRendezvous.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/app.js"></script>
    <script src="rendezvous.js"></script>
    <script>
        // Gestion des erreurs via SweetAlert2 (on ne l'affiche plus pour les succès selon la demande)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            Swal.fire('Erreur !', urlParams.get('error'), 'error');
        }
    </script>
  </body>
</html>
