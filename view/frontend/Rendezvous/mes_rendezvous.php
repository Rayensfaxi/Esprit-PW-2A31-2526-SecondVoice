<?php
require_once '../../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();
$id_citoyen = 1; // Simulé pour l'exemple

$success = $_GET['success'] ?? "";
$liste = $rendezvousC->listRendezvousByCitoyen($id_citoyen);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mes Rendez-vous | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
    <script>
      const savedTheme = localStorage.getItem("theme");
      const initialTheme =
        savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
      document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
        .success-message { color: #27ae60; background: rgba(39, 174, 96, 0.1); padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #27ae60; }
    </style>
  </head>
  <body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="../index.html"><img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="../index.html">Accueil</a></li>
                <li><a href="../about.html">A propos</a></li>
                <li><a href="../services.html">Services</a></li>
                <li><a class="is-active" href="mes_rendezvous.php">Mes Rendez-vous</a></li>
                <li><a href="../blog.html">Blog</a></li>
                <li><a href="../contact.html">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a class="icon-btn user-trigger" href="../login.html" aria-label="Ouvrir la page de connexion"><span>Profil</span></a>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Services</span><span>/</span><span>Mes rendez-vous</span></div>
              <h1>Suivez vos rendez-vous programmés.</h1>
              <p>Retrouvez ici l'historique et le statut de vos demandes de rendez-vous.</p>
            </div>
          </div>
        </section>

        <div class="container">
           <div class="post-content fade-up">
              <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
              <?php endif; ?>
              
              <?php include 'rendezvousList.php'; ?>
              
              <div style="margin-top: 2rem; display: flex; justify-content: center;">
                <a href="HomeRendezvous.php" class="btn btn-primary">Prendre un nouveau rendez-vous</a>
              </div>
           </div>
        </div>
      </main>

      <footer class="site-footer">
        <div class="container">
          <div class="footer-bottom">
            <p>&copy; 2026 SecondVoice. Tous droits réservés.</p>
          </div>
        </div>
      </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="rendezvous.js"></script>
  </body>
</html>
