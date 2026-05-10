<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des services</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/style.css" />
  </head>
  <body data-page="services">
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
              <a class="nav-link" href="HomeService.php" data-nav="services" style="background: var(--nav-active); color: var(--nav-active-text);"><span class="nav-icon icon-card"></span><span>Gestion des services</span></a>
              <a class="nav-link" href="../Rendezvous/HomeRendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
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
            <h1 class="page-title">Gestion des services</h1>
            <div class="page-subtitle">Créez et gérez les services proposés aux citoyens.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../../frontend/index.html">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="page-grid">
           <?php include 'serviceList.php'; ?>
        </div>
      </main>
    </div>

    <!-- Modal pour Ajouter/Modifier un service -->
    <div id="serviceModal" class="modal-overlay" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
      <div class="modal-content" style="background: var(--panel); padding: 2rem; border-radius: var(--radius-lg); width: 100%; max-width: 500px; position: relative;">
        <h2 id="modalTitle" style="margin-bottom: 1.5rem;">Ajouter un service</h2>
        <form id="serviceForm" method="POST" action="addService.php" novalidate>
          <input type="hidden" id="service_id" name="id">
          <div style="margin-bottom: 1rem;">
            <label style="display: block; margin-bottom: 0.5rem;">Nom du service</label>
            <input type="text" id="nom" name="nom" class="input-field" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--line); background: var(--bg);">
            <div id="nom-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem;"></div>
          </div>
          <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem;">Description</label>
            <textarea id="description" name="description" class="input-field" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--line); background: var(--bg); min-height: 100px;"></textarea>
            <div id="description-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem;"></div>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <button type="button" onclick="closeServiceModal()" class="action-button" style="background: var(--line);">Annuler</button>
            <button type="submit" class="action-button" style="background: var(--primary); color: var(--text);">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <style>
      .modal-overlay.active { display: flex !important; }
      .input-field:focus { border-color: var(--primary); outline: none; }
      .input-field.invalid { border-color: #ef4444 !important; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/app.js"></script>
    <script src="service.js?v=<?php echo time(); ?>"></script>
    <script>
        // Gestion des erreurs via SweetAlert2 (on ne l'affiche plus pour les succès selon la demande)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            Swal.fire('Erreur !', urlParams.get('error'), 'error');
        }
    </script>
  </body>
</html>
