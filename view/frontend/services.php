<?php
require_once '../../controller/ServiceC.php';
$serviceC = new ServiceC();
$listeServices = $serviceC->listServices();
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Services | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="assets/media/favicon.png" />
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
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.html"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="index.html">Accueil</a></li>
                <li><a href="about.html">A propos</a></li>
                <li><a class="is-active" href="services.php">Services</a></li>
                <li><a href="Rendezvous/mes_rendezvous.php">Mes RDV</a></li>
                <li><a href="blog.html">Blog</a></li>
                <li><a href="contact.html">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a class="icon-btn user-trigger" href="login.html" aria-label="Ouvrir la page de connexion"><span>Profil</span></a>
              </div>
              <a class="btn btn-primary" href="service-prise-rendezvous.php">Prendre RDV</a>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Services</span></div>
              <h1>Nos services pour simplifier vos demarches</h1>
              <p>
                Decouvrez les services que nous mettons a votre disposition pour faciliter vos demarches administratives
                et vous accompagner efficacement.
              </p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            <h2 style="margin-bottom: 2rem;">Liste de nos services disponibles</h2>
            <div class="grid-3">
              <?php foreach ($listeServices as $service): ?>
              <article class="service-card fade-up" style="cursor: pointer;" onclick="window.location.href='Rendezvous/HomeRendezvous.php?service_id=<?php echo $service->getId(); ?>'">
                <div class="card-icon"></div>
                <h3><?php echo htmlspecialchars($service->getNom()); ?></h3>
                <p><?php echo htmlspecialchars($service->getDescription()); ?></p>
                <a class="service-link" href="Rendezvous/HomeRendezvous.php?service_id=<?php echo $service->getId(); ?>">Prendre rendez-vous</a>
              </article>
              <?php endforeach; ?>
              
              <?php if (empty($listeServices)): ?>
                <p>Aucun service n'est disponible pour le moment.</p>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container service-layout">
            <div class="post-content fade-up">
              <h2>Comment ca fonctionne ?</h2>
              <p>
                Un parcours simple pour demarrer vos demarches en ligne.
              </p>
              <h3>Les etapes</h3>
              <ul class="feature-list">
                <li>Creer un compte</li>
                <li>Choisir un service</li>
                <li>Envoyer une demande</li>
                <li>Suivre le traitement</li>
              </ul>
            </div>
            <aside class="sidebar">
              <div class="sidebar-card fade-up">
                <h3>Besoin d'aide pour vos demarches ?</h3>
                <p>Notre equipe est la pour vous accompagner.</p>
                <a class="btn btn-primary" href="contact.html">Demander de l'aide</a>
              </div>
            </aside>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
          </div>
        </div>
      </footer>
    </div>
    <script src="assets/js/main.js"></script>
  </body>
</html>
