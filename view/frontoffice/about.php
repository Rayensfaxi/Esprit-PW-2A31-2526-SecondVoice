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
    <title>A propos | SecondVoice</title>
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
        $activeNavItem = 'about';
        include __DIR__ . '/../partials/site-header.php';
      ?>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>A propos</span></div>
              <h1>A propos de SecondVoice</h1>
              <p>
                SecondVoice est une plateforme qui aide les utilisateurs a gerer leurs demandes
                administratives et leurs interactions de service plus simplement.
              </p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container grid-2">
            <article class="image-card fade-up">
              <div class="image-card-content">
                <div class="section-kicker">Description</div>
                <h3>SecondVoice centralise les besoins administratifs dans une experience claire.</h3>
                <p>
                  L'utilisateur peut soumettre une demande, suivre son traitement, recevoir des rappels
                  et obtenir de l'aide sans multiplier les canaux.
                </p>
              </div>
            </article>
            <div class="fade-up">
              <div class="section-kicker">Objectif</div>
              <h2>Rendre les demarches plus rapides, plus accessibles et mieux suivies.</h2>
              <p class="section-copy">
                SecondVoice reduit la charge administrative, ameliore la communication avec les services
                et limite les retards lies au manque d'information.
              </p>
              <ul class="feature-list">
                <li>Suivi en temps reel des demandes et des etapes</li>
                <li>Rappels de rendez-vous et notifications utiles</li>
                <li>Accompagnement simple pour les procedures complexes</li>
                <li>Historique centralise pour eviter les pertes d'information</li>
              </ul>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            <div class="section-header fade-up">
              <div>
                <div class="section-kicker">Nos principes</div>
                <h2>Clarte, rapidite et resilience.</h2>
              </div>
            </div>
            <div class="grid-3">
              <article class="info-card fade-up">
                <div class="info-art"></div>
                <h3>Description</h3>
                <p>Une application orientee usager pour simplifier les echanges administratifs.</p>
              </article>
              <article class="info-card fade-up">
                <div class="info-art"></div>
                <h3>Objectif</h3>
                <p>Faire gagner du temps aux utilisateurs et aux equipes de support.</p>
              </article>
              <article class="info-card fade-up">
                <div class="info-art"></div>
                <h3>Probleme resolu</h3>
                <p>Moins de confusion, moins de retard, et un parcours de demande plus fluide.</p>
              </article>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            <div class="achievement fade-up">
              <div class="grid-4">
                <div>
                  <strong class="price">25+</strong>
                  <div class="meta">specialistes en strategie, livraison et conception d'experience</div>
                </div>
                <div>
                  <strong class="price">60+</strong>
                  <div class="meta">missions de modernisation realisees dans des secteurs reglementes</div>
                </div>
                <div>
                  <strong class="price">90%</strong>
                  <div class="meta">des projets lances avec des systemes de contenu reutilisables</div>
                </div>
                <div>
                  <strong class="price">4.9</strong>
                  <div class="meta">de satisfaction client sur les partenariats long terme</div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            <div class="section-header fade-up">
              <div>
                <div class="section-kicker">Leadership</div>
                <h2>L'equipe qui garantit la qualite de livraison.</h2>
              </div>
            </div>
            <div class="grid-4">
              <article class="team-card fade-up">
                <div class="team-art"></div>
                <h3>Maya Jensen</h3>
                <div class="team-meta">Directrice Generale</div>
                <div class="team-socials"><a href="#">in</a><a href="#">x</a><a href="#">be</a></div>
              </article>
              <article class="team-card fade-up">
                <div class="team-art"></div>
                <h3>Rami Trabelsi</h3>
                <div class="team-meta">Responsable Cloud et Securite</div>
                <div class="team-socials"><a href="#">in</a><a href="#">x</a><a href="#">be</a></div>
              </article>
              <article class="team-card fade-up">
                <div class="team-art"></div>
                <h3>Ella Stone</h3>
                <div class="team-meta">Directrice Design d'Experience</div>
                <div class="team-socials"><a href="#">in</a><a href="#">x</a><a href="#">be</a></div>
              </article>
              <article class="team-card fade-up">
                <div class="team-art"></div>
                <h3>Youssef Ben Ali</h3>
                <div class="team-meta">Architecte Automatisation</div>
                <div class="team-socials"><a href="#">in</a><a href="#">x</a><a href="#">be</a></div>
              </article>
            </div>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-main">
            <div>
              <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
              <p>Template HTML premium pour agences IA, cabinets technologiques et marques orientees cyber.</p>
            </div>
            <div>
              <h3 class="footer-title">Pages</h3>
              <ul class="footer-list">
                <li><a href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </div>
            <div>
              <h3 class="footer-title">Contact</h3>
              <ul class="footer-list">
                <li>lac 2, Tunis, Tunisia</li>
                <li>+216 70 000 000</li>
                <li>support@secondvoice.app</li>
              </ul>
            </div>
            <div>
              <h3 class="footer-title">Demarrer</h3>
              <a class="btn btn-primary" href="contact.php">Reserver un appel</a>
            </div>
          </div>
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
            <div class="footer-links"><a href="index.php">Confidentialite</a><a href="index.php">Conditions</a></div>
          </div>
        </div>
      </footer>
    </div>
    <script src="assets/js/main.js"></script>
  </body>
</html>








