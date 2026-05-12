<?php
// Shared front-office site header (top nav).
// Callers may set BEFORE including:
//   $activeNavItem   string|null  One of: 'index','about','services','blog','contact'
//                                  Determines which nav-link gets the .is-active class.
//                                  If unset, no link is highlighted.
//   $headerCtaButton string|null  Optional override for the right-side CTA button HTML.
//                                  If unset, the default Tableau-de-bord button is used
//                                  (only shown when user has admin/agent access).

if (!isset($_SESSION)) {
    session_start();
}

$siteHeaderRole = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
$siteHeaderCanDashboard = isset($_SESSION['user_id']) && in_array($siteHeaderRole, ['admin', 'agent'], true);
$siteHeaderDashboardUrl = $siteHeaderRole === 'agent'
    ? 'assistant-accompagnements.php'
    : '../backoffice/index.php';

$siteHeaderActive = $activeNavItem ?? '';
$siteHeaderCustomCta = $headerCtaButton ?? null;

$siteHeaderEscape = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$siteHeaderActiveCls = static fn (string $item): string => $siteHeaderActive === $item ? ' class="is-active"' : '';
?>
<header class="site-header">
  <div class="container nav-inner">
    <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
    <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
      <span class="icon-lines"></span>
    </button>
    <div class="nav" data-nav>
      <nav>
        <ul class="nav-links">
          <li><a<?= $siteHeaderActiveCls('index') ?> href="index.php">Accueil</a></li>
          <li><a<?= $siteHeaderActiveCls('about') ?> href="about.php">A propos</a></li>
          <li><a<?= $siteHeaderActiveCls('services') ?> href="services.php">Services</a></li>
          <li><a<?= $siteHeaderActiveCls('blog') ?> href="blog.php">Blog</a></li>
          <li><a<?= $siteHeaderActiveCls('contact') ?> href="contact.php">Contact</a></li>
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
        <?php if ($siteHeaderCustomCta !== null): ?>
          <?= $siteHeaderCustomCta ?>
        <?php elseif ($siteHeaderCanDashboard): ?>
          <a class="btn btn-primary" data-dashboard-link="true" href="<?= $siteHeaderEscape($siteHeaderDashboardUrl) ?>">Tableau de bord</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>
