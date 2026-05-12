<?php

session_start();

$role = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
$canAccessDashboard = isset($_SESSION['user_id']) && in_array($role, ['admin', 'agent'], true);
$dashboardUrl = $role === 'agent' ? 'assistant-accompagnements.php' : '../backoffice/index.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Blog | SecondVoice</title>
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
            <?php
        $activeNavItem = 'blog';
        include __DIR__ . '/../partials/site-header.php';
      ?>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Blog</span></div>
              <h1>Blog SecondVoice</h1>
              <p>
                Conseils, actualites et guides pour mieux gerer vos demarches et votre suivi.
              </p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container blog-layout">
            <div class="post-grid">
              <article class="blog-card fade-up">
                <div class="blog-art"></div>
                <div class="post-meta"><span class="tag">Conseils</span><span>23 Mar 2026</span></div>
                <h3><a href="blog-details.php">5 conseils pour accelerer vos demandes administratives</a></h3>
                <p>Des bonnes pratiques simples pour eviter les erreurs frequentes et gagner du temps.</p>
              </article>
              <article class="blog-card fade-up">
                <div class="blog-art"></div>
                <div class="post-meta"><span class="tag">Actualites</span><span>19 Mar 2026</span></div>
                <h3><a href="blog-details.php">Nouveautes SecondVoice: suivi plus clair des statuts</a></h3>
                <p>Un point rapide sur les dernieres ameliorations cote utilisateur et support.</p>
              </article>
              <article class="blog-card fade-up">
                <div class="blog-art"></div>
                <div class="post-meta"><span class="tag">Guides</span><span>14 Mar 2026</span></div>
                <h3><a href="blog-details.php">Guide pratique: prise de rendez-vous en 3 etapes</a></h3>
                <p>Un tutoriel rapide pour reserver, confirmer et suivre un rendez-vous efficacement.</p>
              </article>
            </div>
            <aside class="sidebar">
              <div class="sidebar-card fade-up">
                <h3>Recherche</h3>
                <form class="search-form">
                  <input type="search" placeholder="Recherche articles" />
                </form>
              </div>
              <div class="sidebar-card fade-up">
                <h3>Categories</h3>
                <ul class="footer-list">
                  <li><a href="#">Conseils</a></li>
                  <li><a href="#">Actualites</a></li>
                  <li><a href="#">Guides</a></li>
                  <li><a href="#">Astuces pratiques</a></li>
                </ul>
              </div>
              <div class="sidebar-card fade-up">
                <h3>Newsletter</h3>
                <form class="newsletter-form">
                  <input type="email" placeholder="Votre e-mail" />
                  <a class="btn btn-primary" href="contact.php">S'abonner</a>
                </form>
              </div>
            </aside>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
            <div class="footer-links"><a href="blog-details.php">Article a la une</a><a href="contact.php">Contact</a></div>
          </div>
        </div>
      </footer>
    </div>
    <script src="assets/js/main.js"></script>
  </body>
</html>






