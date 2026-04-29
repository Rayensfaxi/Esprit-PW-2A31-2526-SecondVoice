<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/BrainstormingController.php';
require_once __DIR__ . '/../../controller/IdeaController.php';

$brainstormingController = new BrainstormingController();
$ideaController = new IdeaController();

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentRole = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
$isAdmin = $currentUserId > 0 && $currentRole === 'admin';
$isConnected = $currentUserId > 0;

$brainstormingId = (int) ($_GET['id'] ?? 0);
if ($brainstormingId <= 0) {
    header('Location: service-brainstorming.php?error=invalid_id');
    exit;
}

$brainstorming = $brainstormingController->getBrainstormingById($brainstormingId, 0, true);
if (!$brainstorming) {
    header('Location: service-brainstorming.php?error=not_found');
    exit;
}

$submitted = isset($_GET['submitted']) && (string) $_GET['submitted'] === '1';
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$error = $_GET['error'] ?? '';
$generalError = '';

$flashErrors = $_SESSION['idea_form_errors'] ?? null;
$flashOldValues = $_SESSION['idea_form_old'] ?? null;
$flashGeneralError = $_SESSION['idea_form_general_error'] ?? null;

unset($_SESSION['idea_form_errors'], $_SESSION['idea_form_old'], $_SESSION['idea_form_general_error']);

$fieldErrors = [
    'contenu' => ''
];

if (is_array($flashErrors)) {
    $fieldErrors['contenu'] = (string) ($flashErrors['contenu'] ?? '');
}

if (is_string($flashGeneralError) && $flashGeneralError !== '') {
    $generalError = $flashGeneralError;
} elseif ($error !== '') {
    $generalError = (string) $error;
}

$ideas = $ideaController->getIdeasByBrainstormingId($brainstormingId);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDateTime(string $date): string
{
    if ($date === '') {
        return '-';
    }

    try {
        return (new DateTime($date))->format('d/m/Y à H:i');
    } catch (Throwable $exception) {
        return $date;
    }
}

function getAuthorName(array $idea): string
{
    $nom = (string) ($idea['auteur_nom'] ?? '');
    $prenom = (string) ($idea['auteur_prenom'] ?? '');
    
    if ($nom !== '' && $prenom !== '') {
        return $prenom . ' ' . $nom;
    }
    
    return 'Utilisateur inconnu';
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($brainstorming['titre']) ?> | SecondVoice</title>
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
      .detail-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
      }

      .brainstorming-header {
        background: var(--color-surface);
        border-radius: 12px;
        padding: 2rem;
        border: 1px solid var(--color-border);
        margin-bottom: 2rem;
      }

      .brainstorming-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 1.75rem;
      }

      .brainstorming-meta {
        display: flex;
        gap: 1.5rem;
        font-size: 0.875rem;
        color: var(--color-text-secondary);
        margin-top: 1rem;
        flex-wrap: wrap;
      }

      .brainstorming-meta span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .brainstorming-description {
        margin-top: 1rem;
        line-height: 1.6;
        color: var(--color-text);
      }

      .ideas-section {
        background: var(--color-surface);
        border-radius: 12px;
        padding: 2rem;
        border: 1px solid var(--color-border);
        margin-bottom: 2rem;
      }

      .ideas-section h2 {
        margin: 0 0 1.5rem 0;
        font-size: 1.5rem;
      }

      .idea-card {
        background: var(--color-bg);
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-left: 3px solid var(--color-primary);
      }

      .idea-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
      }

      .idea-author {
        font-weight: 600;
        color: var(--color-text);
      }

      .idea-date {
        font-size: 0.875rem;
        color: var(--color-text-secondary);
      }

      .idea-content {
        line-height: 1.6;
        color: var(--color-text);
        margin-bottom: 1rem;
      }

      .idea-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }

      .idea-actions .btn {
        min-height: 36px;
        padding: 0 12px;
        font-size: 0.8rem;
      }

      .no-ideas {
        text-align: center;
        padding: 2rem;
        color: var(--color-text-secondary);
        font-style: italic;
      }

      .submit-form {
        background: var(--color-surface);
        border-radius: 12px;
        padding: 2rem;
        border: 1px solid var(--color-border);
      }

      .submit-form h2 {
        margin: 0 0 1.5rem 0;
        font-size: 1.5rem;
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

      .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        background: var(--color-bg);
        color: var(--color-text);
        font-family: inherit;
        resize: vertical;
        min-height: 120px;
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

      .btn-danger {
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 10px 24px rgba(220, 38, 38, 0.28);
      }

      .btn-danger:hover {
        background: linear-gradient(135deg, #f87171, #dc2626);
      }

      .back-link {
        display: inline-block;
        margin-bottom: 1rem;
        color: var(--color-primary);
        text-decoration: none;
        font-weight: 500;
      }

      .back-link:hover {
        text-decoration: underline;
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
        <section class="section">
          <div class="container">
            <div class="detail-container">
              <a class="back-link" href="service-brainstorming.php">← Retour aux brainstormings</a>

              <div class="brainstorming-header">
                <h1><?= h($brainstorming['titre']) ?></h1>
                <div class="brainstorming-meta">
                  <span>Categorie: <?= h($brainstorming['categorie']) ?></span>
                  <span>Date: <?= h(date('d/m/Y', strtotime($brainstorming['dateCreation']))) ?></span>
                </div>
                <p class="brainstorming-description"><?= h($brainstorming['description']) ?></p>
              </div>

              <div class="ideas-section">
                <h2>Idées soumises</h2>

                <?php if ($submitted): ?>
                <div class="success-message">
                  Votre idee a ete soumise avec succes. Merci de votre contribution.
                </div>
                <?php endif; ?>

                <?php if ($updated): ?>
                <div class="success-message">
                  Votre idee a ete modifiee avec succes.
                </div>
                <?php endif; ?>

                <?php if ($deleted): ?>
                <div class="success-message">
                  L'idee a ete supprimee avec succes.
                </div>
                <?php endif; ?>

                <?php if ($generalError !== ''): ?>
                <div class="error-message">
                  <?= h($generalError) ?>
                </div>
                <?php endif; ?>

                <?php if (count($ideas) === 0): ?>
                  <div class="no-ideas">
                    <p>Soyez le premier a partager une idee !</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($ideas as $idea): ?>
                    <?php $canManageIdea = $currentUserId > 0 && (int) ($idea['user_id'] ?? 0) === $currentUserId; ?>
                    <div class="idea-card">
                      <div class="idea-header">
                        <div class="idea-author"><?= h(getAuthorName($idea)) ?></div>
                        <div class="idea-date"><?= h(formatDateTime($idea['date_creation'])) ?></div>
                      </div>
                      <p class="idea-content"><?= h($idea['contenu']) ?></p>
                      <?php if ($canManageIdea): ?>
                        <div class="idea-actions">
                          <a class="btn btn-secondary" href="brainstorming-detail.php?id=<?= $brainstormingId ?>&edit=<?= (int) ($idea['id'] ?? 0) ?>">Modifier</a>
                          <form method="POST" action="brainstorming-detail-submit.php" data-delete-idea-form>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) ($idea['id'] ?? 0) ?>" />
                            <input type="hidden" name="brainstorming_id" value="<?= $brainstormingId ?>" />
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                          </form>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <?php if ($brainstorming['statut'] === 'approuve'): ?>
                <?php if ($isConnected): ?>
                  <div class="submit-form">
                    <h2>Soumettre votre idee</h2>
                    <form action="brainstorming-detail-submit.php" method="POST" id="idea-form">
                      <input type="hidden" name="action" value="add" />
                      <input type="hidden" name="brainstorming_id" value="<?= $brainstormingId ?>" />

                      <div class="form-group">
                        <label for="contenu">Votre idee *</label>
                        <textarea id="contenu" name="contenu" placeholder="Partagez votre idee..."><?= h($flashOldValues['contenu'] ?? '') ?></textarea>
                        <?php if ($fieldErrors['contenu'] !== ''): ?>
                          <small class="field-error"><?= h($fieldErrors['contenu']) ?></small>
                        <?php endif; ?>
                      </div>

                      <button type="submit" class="btn btn-primary">Soumettre l'idee</button>
                    </form>
                  </div>
                <?php else: ?>
                  <div class="submit-form">
                    <h2>Soumettre votre idee</h2>
                    <p style="color: var(--color-text-secondary); margin-bottom: 1rem;">
                      Vous devez etre connecte pour soumettre une idee.
                    </p>
                    <a class="btn btn-primary" href="login.php">Se connecter</a>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="submit-form">
                  <h2>Soumettre votre idee</h2>
                  <p style="color: var(--color-text-secondary); margin-bottom: 1rem;">
                    Ce brainstorming n'est pas encore approuve. Vous ne pouvez pas soumettre d'idees pour le moment.
                  </p>
                </div>
              <?php endif; ?>
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
            const confirmed = confirm('Etes-vous sur de vouloir supprimer cette idee ?');
            if (!confirmed) {
              e.preventDefault();
            }
          });
        });
      })();
    </script>
  </body>
</html>
