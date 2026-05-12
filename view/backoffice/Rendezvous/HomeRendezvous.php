<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des rendez-vous</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo (string) @filemtime(__DIR__ . '/../assets/style.css'); ?>" />
  </head>
  <body data-page="subscription">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="../index.php"><img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="../index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="../gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="../gestion-services.php" data-nav="services"><span class="nav-icon icon-card"></span><span>Gestion des services</span></a>
              <a class="nav-link" href="HomeRendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="../gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="../gestion-documents.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des documents</span></a>
              <a class="nav-link" href="../gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="../settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des rendez-vous</h1>
            <div class="page-subtitle">Liste des rendez-vous citoyens, filtres rapides et actions.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../index.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="page-grid">
          <div style="grid-column: 1 / -1;">
            <?php include 'rendezvousList.php'; ?>
          </div>
        </div>
      </main>
    </div>

    <style>
      .modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(7, 10, 26, 0.68);
        backdrop-filter: blur(8px);
      }

      .modal-overlay.active {
        display: flex;
      }

      .modal-container {
        width: min(100%, 560px);
        max-height: calc(100vh - 48px);
        overflow: auto;
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        background: var(--panel);
        color: var(--text);
        box-shadow: 0 24px 80px rgba(0, 0, 0, 0.32);
      }

      .modal-header,
      .modal-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--line);
      }

      .modal-footer {
        justify-content: flex-end;
        border-top: 1px solid var(--line);
        border-bottom: 0;
      }

      .modal-body {
        padding: 20px;
      }

      .modal-title {
        margin: 0;
      }

      .modal-close {
        width: 36px;
        height: 36px;
        display: inline-grid;
        place-items: center;
        border: 1px solid var(--line);
        border-radius: 50%;
        background: var(--panel-2);
        color: var(--text);
        cursor: pointer;
        font-size: 1.35rem;
        line-height: 1;
      }

      .detail-row {
        display: flex;
        gap: 12px;
        border-bottom: 1px solid var(--line);
      }

      .detail-label {
        color: var(--muted);
        font-weight: 700;
      }

      .detail-value {
        color: var(--text);
        font-weight: 700;
      }

      @media print {
        body * {
          visibility: hidden;
        }
        .table-card,
        .table-card * {
          visibility: visible;
        }
        .table-card {
          position: absolute;
          left: 0;
          top: 0;
          width: 100%;
        }
        .users-actions,
        th:last-child,
        td:last-child {
          display: none !important;
        }
      }
    </style>

    <?php include 'detailsRendezvous.php'; ?>
    <?php include 'updateRendezvous.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/app.js"></script>
    <script src="rendezvous.js?v=<?php echo (string) @filemtime(__DIR__ . '/rendezvous.js'); ?>"></script>
    <script>
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('error')) {
        Swal.fire('Erreur', urlParams.get('error'), 'error');
      }
    </script>
  </body>
</html>
