<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontoffice/login.php?status=auth_required');
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    header('Location: ../frontoffice/profile.php?status=forbidden');
    exit;
}

$role = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
if ($role === 'agent') {
    header('Location: gestion-accompagnements.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
  </head>
  <body data-page="home">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
</div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-brainstormings.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des brainstormings</span></a>
              <a class="nav-link" href="gestion-idees.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des idees</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-evenements.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des evenements</span></a>
              <a class="nav-link" href="gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>

        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Dashboard</h1>
            <div class="page-subtitle">Suivez vos demandes, vos rendez-vous et vos documents en un seul endroit.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../frontoffice/index.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="page-grid">
          <section class="hero-banner">
            <div class="hero-inner">
              <div class="hero-copy">
                <div class="badge">Tableau de bord SecondVoice</div>
                <h1>Bienvenue sur votre espace SecondVoice</h1>
                <p>
                  Cet espace vous permet de suivre vos demandes, consulter vos documents, gerer vos rendez-vous
                  et demander un accompagnement.
                </p>
                <button class="cta-button">Voir mes demandes</button>
              </div>
              <div class="hero-visual">
                <div class="device-stack">
                  <img class="hero-art" src="assets/media/hero-collage.svg" alt="IntellectAI creator studio collage" />
                </div>
              </div>
            </div>
          </section>

          <section class="content-section">
            <div class="welcome-row">
              <div>
                <h2 class="section-title">Vue rapide</h2>
                <div class="helper">Accedez a vos informations essentielles.</div>
              </div>
            </div>

            <div class="card-grid">
              <article class="tool-card">
                <div class="tool-visual chat"><img src="assets/media/preview-chat.svg" alt="Apercu des demandes" /></div>
                <div class="mini-badge">Demandes</div>
                <h3 class="feature-title">Mes demandes</h3>
                <p class="card-copy">Consultez et suivez l'etat de vos demandes administratives en temps reel.</p>
              </article>
              <article class="tool-card">
                <div class="tool-visual image"><img src="assets/media/preview-image.svg" alt="Apercu des rendez-vous" /></div>
                <div class="mini-badge">Rendez-vous</div>
                <h3 class="feature-title">Mes rendez-vous</h3>
                <p class="card-copy">Planifiez et gerez vos rendez-vous avec les services disponibles.</p>
              </article>
              <article class="tool-card">
                <div class="tool-visual voice"><img src="assets/media/preview-voice.svg" alt="Apercu des documents" /></div>
                <div class="mini-badge">Documents</div>
                <h3 class="feature-title">Mes documents</h3>
                <p class="card-copy">Ajoutez, consultez et gerez vos documents lies a vos demandes.</p>
              </article>
            </div>
          </section>

          <section class="dual-grid">
            <article class="card">
              <div class="card-header">
                <div>
                  <div class="small-label">Statistiques</div>
                  <h3 class="panel-title">Resume d'activite</h3>
                </div>
                <span class="status-pill active">Actif</span>
              </div>
              <div class="stats-grid">
                <div class="stat-card">
                  <div class="small-label">Demandes envoyees</div>
                  <span class="metric-number">12</span>
                  <div class="status-note">Demandes enregistrees</div>
                </div>
                <div class="stat-card">
                  <div class="small-label">Rendez-vous</div>
                  <span class="metric-number">3</span>
                  <div class="status-note">Rendez-vous planifies</div>
                </div>
                <div class="stat-card">
                  <div class="small-label">Documents</div>
                  <span class="metric-number">8</span>
                  <div class="status-note">Documents disponibles</div>
                </div>
                <div class="stat-card">
                  <div class="small-label">Reclamations</div>
                  <span class="metric-number">2</span>
                  <div class="status-note">En cours de traitement</div>
                </div>
              </div>
            </article>

            <article class="panel">
              <div class="panel-header">
                <div>
                  <div class="small-label">Activite recente</div>
                  <h3 class="panel-title">Dernieres actions</h3>
                </div>
              </div>
              <div class="feed-list">
                <div class="feed-item">
                  <strong>Nouvelle demande envoyee</strong>
                  <div class="feed-meta"><span>Gestion des brainstormings</span><span>il y a 5 minutes</span></div>
                </div>
                <div class="feed-item">
                  <strong>Rendez-vous confirme</strong>
                  <div class="feed-meta"><span>Gestion des rendez-vous</span><span>il y a 20 minutes</span></div>
                </div>
                <div class="feed-item">
                  <strong>Document ajoute</strong>
                  <div class="feed-meta"><span>Gestion des evenements</span><span>il y a 1 heure</span></div>
                </div>
              </div>
            </article>
          </section>
        </div>
      </main>
    </div>

    <script src="assets/app.js"></script>
  </body>
</html>









