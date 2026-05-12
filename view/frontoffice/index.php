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
    <title>SecondVoice | Accueil</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="assets/media/favicon.png" />
    <meta
      name="description"
      content="Modele front office multi-pages pour une plateforme IA, cybersecurite et innovation numerique."
    />
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
    <link rel="stylesheet" href="assets/css/home.css" />
  </head>
  <body class="home-page">
    <div class="logo-splash" id="logo-splash" aria-hidden="true">
      <div class="logo-splash-inner">
        <img src="assets/media/logo white.png" alt="SecondVoice" />
      </div>
    </div>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a class="is-active" href="index.php">Accueil</a></li>
                <li><a href="about.php">A propos</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a class="icon-btn user-trigger" href="profile.php" aria-label="Ouvrir le profil utilisateur"><span>Profil</span></a>
                <div class="user-backdrop"></div>
                <div class="user-panel">
                  <div class="user-panel-head">
                    <div class="user-panel-intro">
                      <div class="user-avatar">JT</div>
                      <div>
                        <p class="user-panel-title">Bon retour</p>
                        <p class="user-modal-copy">Connectez-vous pour acceder a vos projets, factures et demandes de support.</p>
                      </div>
                    </div>
                    <button class="icon-btn user-close" type="button" data-user-close aria-label="Fermer la fenetre utilisateur">X</button>
                  </div>
                  <div class="auth-tabs">
                    <a class="auth-tab is-active" href="login.php">Connexion</a>
                    <a class="auth-tab" href="register.php">Inscription</a>
                  </div>
                  <section class="auth-panel is-active" data-auth-panel="login">
                    <h3 class="auth-title">Connexion Client</h3>
                    <p class="auth-helper">Utilisez votre e-mail et mot de passe pour continuer.</p>
                    <form class="auth-form">
                      <input class="field" type="email" placeholder="Adresse e-mail" />
                      <input class="field" type="password" placeholder="Mot de passe" />
                      <div class="auth-options">
                        <label class="check-row"><input type="checkbox" /> Se souvenir de moi</label>
                        <a href="contact.php">Mot de passe oublie ?</a>
                      </div>
                      <button class="btn btn-primary" type="button">Se connecter</button>
                    </form>
                    <div class="user-panel-footer">
                      <a class="btn btn-secondary" href="contact.php">Support client</a>
                    </div>
                  </section>
                  <section class="auth-panel" data-auth-panel="register">
                    <h3 class="auth-title">Creer un compte</h3>
                    <p class="auth-helper">Creez un compte de demonstration pour le suivi, le support et la gestion de vos demandes.</p>
                    <form class="auth-form">
                      <input class="field" type="text" placeholder="Nom complet" />
                      <input class="field" type="email" placeholder="E-mail professionnel" />
                      <input class="field" type="password" placeholder="Creer un mot de passe" />
                      <button class="btn btn-primary" type="button">Creer un compte</button>
                    </form>
                    <ul class="auth-links">
                      <li><a href="services.php">Voir les offres de service</a></li>
                      <li><a href="contact.php">Demander un acces entreprise</a></li>
                    </ul>
                  </section>
                </div>
              </div>
              <?php if ($canAccessDashboard): ?>
              <a class="btn btn-primary" data-dashboard-link="true" href="<?= h($dashboardUrl) ?>">Tableau de bord</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </header>
      <main>
        <section class="sv-home-hero">
          <div class="container sv-hero-grid">
            <div class="sv-hero-copy fade-up">
              <div class="eyebrow sv-eyebrow">
                <span class="eyebrow-dot"></span>
                <span>Plateforme d&rsquo;assistance vocale et administrative</span>
              </div>
              <h1>Simplifiez vos d&eacute;marches administratives avec <span>SecondVoice.</span></h1>
              <p>Envoyez vos demandes, prenez rendez-vous, d&eacute;posez vos documents et b&eacute;n&eacute;ficiez d&rsquo;un accompagnement personnalis&eacute; en quelques clics.</p>
              <div class="sv-hero-actions">
                <a class="sv-primary-btn" href="services.php">Commencer maintenant <span>&rarr;</span></a>
                <a class="sv-secondary-btn" href="services.php">D&eacute;couvrir les services <span>&rsaquo;</span></a>
                <a class="sv-secondary-btn sv-smart-btn" href="smart-guide.php">Smart Guide <span>➜</span></a>
              </div>
              <div class="sv-metrics">
                <article><div class="sv-metric-icon metric-users"></div><strong>+100</strong><span>utilisateurs accompagnes</span></article>
                <article><div class="sv-metric-icon metric-docs"></div><strong>+50</strong><span>demandes administratives traitees</span></article>
                <article><div class="sv-metric-icon metric-clock"></div><strong>Assistance<br>24/7</strong></article>
              </div>
            </div>
            <div class="sv-dashboard-wrap fade-up" aria-label="Apercu de l'assistant SecondVoice">
              <img class="sv-dashboard-image" src="assets/media/hero-right-home.png" alt="Interface de l'assistant SecondVoice" />
            </div>
          </div>
        </section>
        <section class="sv-assistance">
          <div class="container">
            <div class="sv-section-title fade-up">
              <h2>Une assistance pensee pour vous simplifier la vie</h2>
              <p>Des services intelligents, humains et securises pour toutes vos demarches.</p>
            </div>
            <div class="sv-feature-grid">
              <article class="sv-feature-card fade-up">
                <div><span class="sv-feature-icon purple"></span><h3>Gain de temps</h3><p>Confiez vos demarches a SecondVoice et concentrez-vous sur l'essentiel.</p></div>
                <div class="sv-mini-calendar"><strong>Prise de rendez-vous</strong><small>RDV confirme</small><div class="sv-days"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><b>24</b></div></div>
              </article>
              <article class="sv-feature-card fade-up">
                <div><span class="sv-feature-icon blue"></span><h3>Accompagnement personnalise</h3><p>Un assistant dedie vous guide et repond a chacune de vos demandes.</p></div>
                <div class="sv-chat-preview"><small>Assistant SecondVoice</small><p>Comment puis-je vous aider ?</p><b>Je souhaite refaire ma carte d'identite.</b></div>
              </article>
              <article class="sv-feature-card fade-up">
                <div><span class="sv-feature-icon green"></span><h3>Suivi simplifie</h3><p>Suivez l'avancement de vos demandes en temps reel, en toute transparence.</p></div>
                <div class="sv-progress-preview"><span class="done">Demande envoyee</span><span class="current">En cours de traitement</span><span>Terminee</span></div>
              </article>
            </div>
            <div class="sv-security-bar fade-up">
              <span>SECURISE ET CONFIDENTIEL</span>
              <p>Vos donnees sont protegees et ne sont jamais partagees.</p>
              <span>HEBERGEMENT SECURISE<br><small>Certifie ISO 27001</small></span>
              <span>CONFORME RGPD<br><small>Donnees protegees</small></span>
            </div>
          </div>
        </section>
      </main>


      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
            <div class="footer-links">
              <a href="index.php">Confidentialite</a>
              <a href="index.php">Conditions</a>
            </div>
          </div>
        </div>
      </footer>
    </div>

    <script src="assets/js/main.js"></script>
  </body>
</html>











