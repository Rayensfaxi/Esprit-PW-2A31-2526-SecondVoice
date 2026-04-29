<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/BrainstormingController.php';

$controller = new BrainstormingController();

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentRole = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
$isAdmin = $currentUserId > 0 && $currentRole === 'admin';
$isConnected = $currentUserId > 0;

$currentSearch = $_GET['q'] ?? '';
$currentCategorie = $_GET['categorie'] ?? 'toutes';
$currentStatus = $_GET['status'] ?? 'toutes';
$submitted = isset($_GET['submitted']) && (string) $_GET['submitted'] === '1';
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$error = $_GET['error'] ?? '';
$generalError = '';
$editIdea = null;
$formValues = [
    'titre' => '',
    'description' => '',
    'categorie' => ''
];
$fieldErrors = [
    'titre' => '',
    'description' => '',
    'categorie' => ''
];

$flashErrors = $_SESSION['brainstorming_form_errors'] ?? null;
$flashOldValues = $_SESSION['brainstorming_form_old'] ?? null;
$flashGeneralError = $_SESSION['brainstorming_form_general_error'] ?? null;

unset($_SESSION['brainstorming_form_errors'], $_SESSION['brainstorming_form_old'], $_SESSION['brainstorming_form_general_error'], $_SESSION['brainstorming_form_action'], $_SESSION['brainstorming_form_id']);

if (is_array($flashErrors)) {
    $fieldErrors['titre'] = (string) ($flashErrors['titre'] ?? '');
    $fieldErrors['description'] = (string) ($flashErrors['description'] ?? '');
    $fieldErrors['categorie'] = (string) ($flashErrors['categorie'] ?? '');
}

if (is_array($flashOldValues)) {
    $formValues['titre'] = (string) ($flashOldValues['titre'] ?? '');
    $formValues['description'] = (string) ($flashOldValues['description'] ?? '');
    $formValues['categorie'] = (string) ($flashOldValues['categorie'] ?? '');
}

if (is_string($flashGeneralError) && $flashGeneralError !== '') {
    $generalError = $flashGeneralError;
} elseif ($error !== '') {
    $generalError = (string) $error;
}

if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $editIdea = $controller->getBrainstormingById((int) $_GET['edit'], $currentUserId, false);
    if ($editIdea !== null) {
        if (!is_array($flashOldValues)) {
            $formValues = [
                'titre' => (string) ($editIdea['titre'] ?? ''),
                'description' => (string) ($editIdea['description'] ?? ''),
                'categorie' => (string) ($editIdea['categorie'] ?? '')
            ];
        }
    }
}

$brainstormings = [];
if ($isAdmin) {
    $brainstormings = $controller->getBrainstormings([
        'q' => $currentSearch,
        'categorie' => $currentCategorie,
        'statut' => $currentStatus
    ]);
} elseif ($isConnected) {
    $brainstormings = $controller->getBrainstormings([
        'q' => $currentSearch,
        'categorie' => $currentCategorie,
        'statut' => $currentStatus,
        'owner_id' => $currentUserId,
        'include_global' => true,
        'approved_only' => true
    ]);
} else {
    $brainstormings = $controller->getBrainstormings([
        'q' => $currentSearch,
        'categorie' => $currentCategorie,
        'statut' => $currentStatus,
        'global_only' => true,
        'approved_only' => true
    ]);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function statusClass(string $status): string
{
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'approuve', 'approuvee', 'approuvees', 'approuves', 'approuvé', 'approuvée', 'approuvés', 'approuvées' => 'approuve',
        'desapprouve', 'desapprouvee', 'desapprouves', 'desapprouvees', 'désapprouvé', 'désapprouvée', 'désapprouvés', 'désapprouvées' => 'desapprouve',
        default => 'en-attente',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Brainstorming | SecondVoice</title>
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
    <style>
      .brainstorming-container {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 2rem;
        margin-top: 2rem;
      }

      .brainstorming-form {
        background: var(--color-surface);
        border-radius: 12px;
        padding: 2rem;
        border: 1px solid var(--color-border);
        height: fit-content;
        position: sticky;
        top: 2rem;
      }

      .brainstorming-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
      }

      .brainstorming-card {
        background: var(--color-surface);
        border-radius: 12px;
        padding: 1.5rem;
        border-left: 4px solid var(--color-primary);
        transition: all 0.3s ease;
      }

      .brainstorming-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateX(4px);
      }

      .brainstorming-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
      }

      .brainstorming-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin: 0;
      }

      .brainstorming-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: var(--color-primary);
        color: white;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
      }

      .brainstorming-badge.approuve {
        background: #10b981;
      }

      .brainstorming-badge.en-attente {
        background: #f59e0b;
      }

      .brainstorming-badge.desapprouve {
        background: #ef4444;
      }

      .brainstorming-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        color: var(--color-text-secondary);
        margin-top: 0.75rem;
      }

      .brainstorming-description {
        color: var(--color-text);
        margin: 0.75rem 0 0 0;
        line-height: 1.6;
      }

      .search-box {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
      }

      .search-box input {
        flex: 1;
      }

      .filters {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }

      .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .filter-group label {
        font-weight: 500;
        font-size: 0.875rem;
      }

      .filter-group select {
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-bg);
        color: var(--color-text);
      }

      .form-group {
        margin-bottom: 1.5rem;
      }

      .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        font-size: 0.875rem;
      }

      .form-group input,
      .form-group textarea,
      .form-group select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-bg);
        color: var(--color-text);
        font-family: inherit;
      }

      .form-group textarea {
        resize: vertical;
        min-height: 100px;
      }

      .success-message {
        padding: 1rem;
        background: #d1fae5;
        border-left: 4px solid #10b981;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        color: #065f46;
      }

      .error-message {
        padding: 1rem;
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        color: #991b1b;
      }

      .field-error {
        display: block;
        margin-top: 0.35rem;
        color: #ef4444;
        font-size: 0.78rem;
        line-height: 1.3;
      }

      .no-results {
        text-align: center;
        padding: 2rem;
        color: var(--color-text-secondary);
      }

      .brainstorming-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        flex-wrap: wrap;
      }

      .brainstorming-actions .btn {
        min-height: 40px;
        padding: 0 16px;
        font-size: 0.82rem;
        font-weight: 700;
      }

      .btn-danger {
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 10px 24px rgba(220, 38, 38, 0.28);
      }

      .btn-danger:hover {
        background: linear-gradient(135deg, #f87171, #dc2626);
      }

      @media (max-width: 768px) {
        .brainstorming-container {
          grid-template-columns: 1fr;
        }

        .brainstorming-form {
          position: static;
        }
      }
    </style>
  </head>
  <body>
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
                <li><a href="index.php">Accueil</a></li>
                <li><a href="about.php">A propos</a></li>
                <li><a class="is-active" href="services.php">Services</a></li>
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
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Brainstorming</span></div>
              <h1>Partagez vos brainstormings pour ameliorer nos services.</h1>
              <p>
                Contribuez au developpement de SecondVoice en proposant vos brainstormings d'innovation et d'amelioration.
              </p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container">
            <div class="brainstorming-container">
              <aside class="brainstorming-form fade-up">
                <h3 style="margin-top: 0;"><?= $editIdea ? 'Modifier un brainstorming' : 'Soumettre un brainstorming' ?></h3>

                <?php if ($submitted): ?>
                <div class="success-message">
                  Votre brainstorming a ete soumis avec succes. Merci de votre contribution.
                </div>
                <?php endif; ?>

                <?php if ($updated): ?>
                <div class="success-message">
                  Votre brainstorming a ete modifie avec succes.
                </div>
                <?php endif; ?>

                <?php if ($deleted): ?>
                <div class="success-message">
                  Le brainstorming a ete supprime avec succes.
                </div>
                <?php endif; ?>

                <?php if ($generalError !== ''): ?>
                <div class="error-message">
                  <?= h($generalError) ?>
                </div>
                <?php endif; ?>

                <form action="service-brainstorming-submit.php" method="POST" id="brainstorming-form">
                  <input type="hidden" name="action" value="<?= $editIdea ? 'update' : 'add' ?>" />
                  <?php if ($editIdea): ?>
                  <input type="hidden" name="id" value="<?= (int) ($editIdea['id'] ?? 0) ?>" />
                  <?php endif; ?>

                  <div class="form-group">
                    <label for="titre">Titre du brainstorming *</label>
                    <input type="text" id="titre" name="titre" placeholder="Donnez un titre a votre brainstorming" value="<?= h($formValues['titre']) ?>" />
                    <?php if ($fieldErrors['titre'] !== ''): ?>
                      <small class="field-error"><?= h($fieldErrors['titre']) ?></small>
                    <?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" placeholder="Decrivez votre brainstorming en detail..."><?= h($formValues['description']) ?></textarea>
                    <?php if ($fieldErrors['description'] !== ''): ?>
                      <small class="field-error"><?= h($fieldErrors['description']) ?></small>
                    <?php endif; ?>
                  </div>

                  <div class="form-group">
                    <label for="categorie">Categorie *</label>
                    <select id="categorie" name="categorie">
                      <option value="">Selectionnez une categorie</option>
                      <option value="innovation" <?= strtolower($formValues['categorie']) === 'innovation' ? 'selected' : '' ?>>Innovation</option>
                      <option value="amelioration" <?= strtolower($formValues['categorie']) === 'amelioration' ? 'selected' : '' ?>>Amelioration</option>
                      <option value="processus" <?= strtolower($formValues['categorie']) === 'processus' ? 'selected' : '' ?>>Processus</option>
                      <option value="client" <?= strtolower($formValues['categorie']) === 'client' ? 'selected' : '' ?>>Experience client</option>
                      <option value="autre" <?= strtolower($formValues['categorie']) === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                    <?php if ($fieldErrors['categorie'] !== ''): ?>
                      <small class="field-error"><?= h($fieldErrors['categorie']) ?></small>
                    <?php endif; ?>
                  </div>

                  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;"><?= $editIdea ? 'Enregistrer les modifications' : 'Soumettre le brainstorming' ?></button>
                    <?php if ($editIdea): ?>
                    <a href="service-brainstorming.php" class="btn btn-secondary" style="width: 100%; text-decoration: none;">Annuler</a>
                    <?php endif; ?>
                  </div>
                </form>

                <p style="font-size: 0.75rem; color: var(--color-text-secondary); margin-top: 1rem;">
                  * Champs obligatoires. Vos brainstormings seront examines par notre equipe.
                </p>
              </aside>

              <div class="fade-up">
                <h3 style="margin-top: 0;">Brainstormings en cours</h3>

                <div class="search-box">
                  <form action="" method="GET" style="display: flex; gap: 0.75rem; width: 100%;">
                    <input type="text" name="q" placeholder="Rechercher un brainstorming..." value="<?= h($currentSearch) ?>" />
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                  </form>
                </div>

                <div class="filters">
                  <div class="filter-group">
                    <label for="categorie-filter">Filtrer par categorie</label>
                    <form action="" method="GET" id="filter-form">
                      <input type="hidden" name="q" value="<?= h($currentSearch) ?>" />
                      <input type="hidden" name="status" value="<?= h($currentStatus) ?>" />
                      <select id="categorie-filter" name="categorie" onchange="document.getElementById('filter-form').submit()">
                        <option value="toutes" <?= $currentCategorie === 'toutes' ? 'selected' : '' ?>>Toutes les categories</option>
                        <option value="innovation" <?= $currentCategorie === 'innovation' ? 'selected' : '' ?>>Innovation</option>
                        <option value="amelioration" <?= $currentCategorie === 'amelioration' ? 'selected' : '' ?>>Amelioration</option>
                        <option value="processus" <?= $currentCategorie === 'processus' ? 'selected' : '' ?>>Processus</option>
                        <option value="client" <?= $currentCategorie === 'client' ? 'selected' : '' ?>>Experience client</option>
                        <option value="autre" <?= $currentCategorie === 'autre' ? 'selected' : '' ?>>Autre</option>
                      </select>
                    </form>
                  </div>
                  <div class="filter-group">
                    <label for="status-filter">Filtrer par statut</label>
                    <form action="" method="GET" id="status-filter-form">
                      <input type="hidden" name="q" value="<?= h($currentSearch) ?>" />
                      <input type="hidden" name="categorie" value="<?= h($currentCategorie) ?>" />
                      <select id="status-filter" name="status" onchange="document.getElementById('status-filter-form').submit()">
                        <option value="toutes" <?= $currentStatus === 'toutes' ? 'selected' : '' ?>>Tous les statuts</option>
                        <option value="en attente" <?= $currentStatus === 'en attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="approuve" <?= $currentStatus === 'approuve' ? 'selected' : '' ?>>Approuve</option>
                        <option value="desapprouve" <?= $currentStatus === 'desapprouve' ? 'selected' : '' ?>>Desapprouve</option>
                      </select>
                    </form>
                  </div>
                </div>

                <div class="brainstorming-list">
                  <?php if (count($brainstormings) === 0): ?>
                    <div class="no-results">
                      <p>Aucun brainstorming trouve. Soyez le premier a soumettre un brainstorming.</p>
                    </div>
                  <?php else: ?>
                    <?php foreach ($brainstormings as $idea): ?>
                      <?php $canManageIdea = $currentUserId > 0 && (int) ($idea['user_id'] ?? 0) === $currentUserId; ?>
                      <div class="brainstorming-card">
                        <div class="brainstorming-card-header">
                          <h4 class="brainstorming-title"><?= h((string) ($idea['titre'] ?? '')) ?></h4>
                          <span class="brainstorming-badge <?= h(statusClass((string) ($idea['statut'] ?? 'en attente'))) ?>">
                            <?= h((string) ($idea['statut'] ?? 'en attente')) ?>
                          </span>
                        </div>
                        <p class="brainstorming-description"><?= h((string) ($idea['description'] ?? '')) ?></p>
                        <div class="brainstorming-meta">
                          <span>Categorie: <?= h((string) ($idea['categorie'] ?? '')) ?></span>
                          <span>Date: <?= h(date('d/m/Y', strtotime((string) ($idea['dateCreation'] ?? 'now')))) ?></span>
                          <?php if ((int) ($idea['user_id'] ?? 0) === 0): ?>
                          <span>Publication: Administration</span>
                          <?php endif; ?>
                        </div>
                        <div class="brainstorming-actions">
                          <a class="btn btn-primary" href="brainstorming-detail.php?id=<?= (int) ($idea['id'] ?? 0) ?>">Voir les idées</a>
                          <?php if ($canManageIdea): ?>
                            <a class="btn btn-secondary" href="service-brainstorming.php?edit=<?= (int) ($idea['id'] ?? 0) ?>">Modifier</a>
                            <form method="POST" action="service-brainstorming-submit.php" data-delete-idea-form>
                              <input type="hidden" name="action" value="delete" />
                              <input type="hidden" name="id" value="<?= (int) ($idea['id'] ?? 0) ?>" />
                              <button type="submit" class="btn btn-danger">Supprimer</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits reserves.</span>
            <div class="footer-links"><a href="services.php">Services</a><a href="contact.php">Contact</a></div>
          </div>
        </div>
      </footer>
    </div>
    <script src="assets/js/main.js"></script>
    <script>
      (function () {
        const deleteForms = document.querySelectorAll('[data-delete-idea-form]');
        deleteForms.forEach((deleteForm) => {
          deleteForm.addEventListener('submit', (e) => {
            const confirmed = confirm('Etes-vous sur de vouloir supprimer ce brainstorming ?');
            if (!confirmed) {
              e.preventDefault();
            }
          });
        });
      })();
    </script>
  </body>
</html>
