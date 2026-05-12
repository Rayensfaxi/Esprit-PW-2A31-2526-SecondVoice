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
    <title>Contact | SecondVoice</title>
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
        $activeNavItem = 'contact';
        include __DIR__ . '/../partials/site-header.php';
      ?>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Contact</span></div>
              <h1>Contact SecondVoice</h1>
              <p>Envoyez-nous votre message pour assistance, information ou accompagnement.</p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container contact-grid">
            <article class="contact-card fade-up">
              <h3>Parlons de votre besoin.</h3>
              <p>
                Remplissez le formulaire ci-dessous. Notre equipe support vous repond rapidement.
              </p>
              <form>
                <div class="input-row">
                  <input class="field" type="text" placeholder="Nom" />
                  <input class="field" type="email" placeholder="Email" />
                </div>
                <textarea class="field" placeholder="Message"></textarea>
                <button class="btn btn-primary" type="submit">Envoyer</button>
              </form>
            </article>
            <div class="map-card fade-up">
              <div class="map-grid">
                <span class="map-pin one"></span>
                <span class="map-pin two"></span>
                <span class="map-pin three"></span>
              </div>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container grid-3">
            <article class="detail-card fade-up">
              <h3>Siege</h3>
              <p>Les Berges du Lac 2, Tunis, Tunisia</p>
            </article>
            <article class="detail-card fade-up">
              <h3>E-mail support</h3>
              <p>support@secondvoice.app</p>
            </article>
            <article class="detail-card fade-up">
              <h3>Telephone</h3>
              <p>+216 70 000 000</p>
            </article>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
            <div class="footer-links"><a href="index.php">Accueil</a><a href="services.php">Services</a></div>
          </div>
        </div>
      </footer>
    </div>
    <script src="assets/js/main.js"></script>
  </body>
</html>






