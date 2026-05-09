<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/BrainstormingController.php';
require_once __DIR__ . '/../../controller/IdeaController.php';
require_once __DIR__ . '/../../controller/VoteController.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectToDetail(int $brainstormingId, array $params = []): void
{
    $query = array_merge(['id' => $brainstormingId], $params);
    header('Location: brainstorming-detail.php?' . http_build_query($query));
    exit;
}

function formatDateLabel(string $date): string
{
    if ($date === '') {
        return '-';
    }

    try {
        return (new DateTime($date))->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        return $date;
    }
}

function statusClass(string $status): string
{
    return match (strtolower(trim($status))) {
        'approuve' => 'approuve',
        'desapprouve' => 'desapprouve',
        default => 'en-attente'
    };
}

$brainstormingId = (int) ($_GET['id'] ?? $_POST['brainstorming_id'] ?? 0);
if ($brainstormingId <= 0) {
    header('Location: service-brainstorming.php?error=' . urlencode('Brainstorming introuvable.'));
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentRole = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
$isAdmin = $currentUserId > 0 && $currentRole === 'admin';
$isConnected = $currentUserId > 0;

$brainstormingController = new BrainstormingController();
$ideaController = new IdeaController();
$voteController = new VoteController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    try {
        if (!$isConnected) {
            throw new RuntimeException('Vous devez etre connecte pour gerer une idee.');
        }

        if (!in_array($action, ['add_idea', 'update_idea', 'delete_idea'], true)) {
            throw new InvalidArgumentException('Action invalide.');
        }

        $ideaId = (int) ($_POST['idea_id'] ?? 0);

        if ($action === 'delete_idea') {
            if ($ideaId <= 0) {
                throw new InvalidArgumentException('Idee invalide.');
            }

            $idea = $ideaController->getIdeaById($ideaId);
            if (!$idea || (int) $idea['brainstorming_id'] !== $brainstormingId) {
                throw new RuntimeException('Idee introuvable pour ce brainstorming.');
            }

            $deleted = $ideaController->deleteIdea($ideaId, $currentUserId, $isAdmin);
            if (!$deleted) {
                throw new RuntimeException('Suppression impossible: idee introuvable ou non autorisee.');
            }

            redirectToDetail($brainstormingId, ['deleted' => '1']);
        }

        $contenu = trim((string) ($_POST['contenu'] ?? ''));
        if ($contenu === '') {
            throw new InvalidArgumentException('Le contenu de l\'idee est obligatoire.');
        }
        if (strlen($contenu) < 10) {
            throw new InvalidArgumentException('Le contenu doit contenir au moins 10 caracteres.');
        }

        if ($action === 'update_idea') {
            if ($ideaId <= 0) {
                throw new InvalidArgumentException('Idee invalide.');
            }

            $idea = $ideaController->getIdeaById($ideaId);
            if (!$idea || (int) $idea['brainstorming_id'] !== $brainstormingId) {
                throw new RuntimeException('Idee introuvable pour ce brainstorming.');
            }

            $updated = $ideaController->updateIdea($ideaId, $contenu, $currentUserId, $isAdmin);
            if (!$updated) {
                throw new RuntimeException('Modification impossible: idee introuvable ou non autorisee.');
            }

            redirectToDetail($brainstormingId, ['updated' => '1']);
        }

        $ideaController->addIdea($brainstormingId, $currentUserId, $contenu, $isAdmin);
        redirectToDetail($brainstormingId, ['added' => '1']);
    } catch (Throwable $exception) {
        $_SESSION['idea_form_error'] = $exception->getMessage();
        $_SESSION['idea_form_old'] = ['contenu' => (string) ($_POST['contenu'] ?? '')];
        $params = [];
        if (($action ?? '') === 'update_idea' && (int) ($_POST['idea_id'] ?? 0) > 0) {
            $params['edit_idea'] = (string) ((int) $_POST['idea_id']);
        }
        redirectToDetail($brainstormingId, $params);
    }
}

$brainstorming = $brainstormingController->getBrainstormingForViewing($brainstormingId, $currentUserId, $isAdmin);
if ($brainstorming === null) {
    header('Location: service-brainstorming.php?error=' . urlencode('Brainstorming introuvable ou non accessible.'));
    exit;
}

$flashError = (string) ($_SESSION['idea_form_error'] ?? '');
$flashOld = $_SESSION['idea_form_old'] ?? null;
unset($_SESSION['idea_form_error'], $_SESSION['idea_form_old']);

$editIdea = null;
if (isset($_GET['edit_idea']) && ctype_digit((string) $_GET['edit_idea'])) {
    $candidate = $ideaController->getIdeaById((int) $_GET['edit_idea']);
    if ($candidate && (int) $candidate['brainstorming_id'] === $brainstormingId) {
        $canEditCandidate = $isAdmin || ((int) $candidate['user_id'] === $currentUserId);
        if ($canEditCandidate) {
            $editIdea = $candidate;
        }
    }
}

$formContent = is_array($flashOld)
    ? (string) ($flashOld['contenu'] ?? '')
    : (string) ($editIdea['contenu'] ?? '');

$ideas = $ideaController->getIdeasByBrainstormingId($brainstormingId, $currentUserId, $isAdmin);
$ideaIds = array_map(static fn(array $idea): int => (int) $idea['id'], $ideas);
$userVotes = $voteController->getVoteMapForUser($currentUserId, $ideaIds);
$voteStatus = $voteController->getBrainstormingVoteStatus($brainstormingId) ?? [
    'status' => 'closed',
    'start' => '',
    'end' => '',
    'isOpen' => false
];

$canSubmitIdea = $isConnected && strtolower((string) $brainstorming['statut']) !== 'desapprouve';
$canVote = $isConnected && !empty($voteStatus['isOpen']);
$added = isset($_GET['added']) && (string) $_GET['added'] === '1';
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$voteSuccess = (string) ($_SESSION['vote_success'] ?? '');
$voteError = (string) ($_SESSION['vote_error'] ?? '');
unset($_SESSION['vote_success'], $_SESSION['vote_error']);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Idees | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
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
      .detail-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 2rem;
        align-items: start;
      }

      .detail-panel,
      .idea-card,
      .idea-form-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 12px;
      }

      .detail-panel,
      .idea-form-card {
        padding: 1.5rem;
      }

      .detail-meta,
      .vote-meta,
      .idea-meta {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        color: var(--color-text-secondary);
        font-size: 0.88rem;
      }

      .status-badge {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 0.75rem;
        border-radius: 999px;
        color: #fff;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
      }

      .status-badge.approuve {
        background: #10b981;
      }

      .status-badge.en-attente {
        background: #f59e0b;
      }

      .status-badge.desapprouve {
        background: #ef4444;
      }

      .ideas-list {
        display: grid;
        gap: 1rem;
        margin-top: 1.25rem;
      }

      .idea-card {
        padding: 1.25rem;
      }

      .idea-card.is-winner {
        border-color: #f59e0b;
        box-shadow: 0 16px 36px rgba(245, 158, 11, 0.16);
      }

      .idea-content {
        margin: 0.75rem 0;
        line-height: 1.65;
      }

      .idea-actions,
      .vote-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
      }

      .vote-button {
        min-height: 38px;
        min-width: 74px;
        padding: 0 0.9rem;
        border-radius: 999px;
        gap: 0.35rem;
      }

      .vote-button.is-active {
        border-color: var(--color-primary);
        color: var(--color-primary);
      }

      .vote-button:disabled {
        opacity: 0.55;
        cursor: not-allowed;
      }

      .thumb-icon {
        font-size: 1.08rem;
        line-height: 1;
      }

      .vote-count {
        font-weight: 800;
      }

      .idea-form-card {
        position: sticky;
        top: 2rem;
      }

      .idea-form-card textarea {
        width: 100%;
        min-height: 150px;
        resize: vertical;
        padding: 0.85rem;
        border: 1px solid var(--color-border);
        border-radius: 8px;
        background: var(--color-bg);
        color: var(--color-text);
        font: inherit;
      }

      .alert {
        padding: 0.85rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
      }

      .alert.success {
        background: #d1fae5;
        color: #065f46;
      }

      .alert.error {
        background: #fee2e2;
        color: #991b1b;
      }

      .empty-state {
        padding: 2rem;
        border: 1px dashed var(--color-border);
        border-radius: 12px;
        color: var(--color-text-secondary);
        text-align: center;
      }

      @media (max-width: 900px) {
        .detail-layout {
          grid-template-columns: 1fr;
        }

        .idea-form-card {
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
              <a class="icon-btn user-trigger" href="<?= $isConnected ? 'profile.php' : 'login.php' ?>" aria-label="Ouvrir le profil utilisateur"><span>Profil</span></a>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <div class="breadcrumbs"><span>Accueil</span><span>/</span><a href="service-brainstorming.php">Brainstorming</a><span>/</span><span>Idees</span></div>
              <h1><?= h((string) $brainstorming['titre']) ?></h1>
              <p><?= h((string) $brainstorming['description']) ?></p>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="container detail-layout">
            <div>
              <section class="detail-panel">
                <div class="detail-meta">
                  <span class="status-badge <?= h(statusClass((string) $brainstorming['statut'])) ?>"><?= h((string) $brainstorming['statut']) ?></span>
                  <span>Categorie: <?= h((string) $brainstorming['categorie']) ?></span>
                  <span>Cree le: <?= h(formatDateLabel((string) $brainstorming['dateCreation'])) ?></span>
                </div>
                <div class="vote-meta" style="margin-top: 1rem;">
                  <?php if (!empty($voteStatus['isOpen']) && (string) ($voteStatus['end'] ?? '') !== ''): ?>
                    <span>Vote ouvert jusqu'au <?= h(formatDateLabel((string) $voteStatus['end'])) ?></span>
                  <?php elseif (!empty($voteStatus['isOpen'])): ?>
                    <span>Vote ouvert</span>
                  <?php elseif (($voteStatus['status'] ?? '') === 'scheduled'): ?>
                    <span>Vote programme du <?= h(formatDateLabel((string) $voteStatus['start'])) ?> au <?= h(formatDateLabel((string) $voteStatus['end'])) ?></span>
                  <?php else: ?>
                    <span>Vote ferme</span>
                  <?php endif; ?>
                </div>
              </section>

              <?php if ($added): ?><div class="alert success">Idee ajoutee avec succes.</div><?php endif; ?>
              <?php if ($updated): ?><div class="alert success">Idee modifiee avec succes.</div><?php endif; ?>
              <?php if ($deleted): ?><div class="alert success">Idee supprimee avec succes.</div><?php endif; ?>
              <?php if ($voteSuccess !== ''): ?><div class="alert success"><?= h($voteSuccess) ?></div><?php endif; ?>
              <?php if ($voteError !== ''): ?><div class="alert error"><?= h($voteError) ?></div><?php endif; ?>

              <div class="ideas-list">
                <?php if ($ideas === []): ?>
                  <div class="empty-state">Aucune idee approuvee pour le moment.</div>
                <?php else: ?>
                  <?php foreach ($ideas as $idea): ?>
                    <?php
                      $ideaId = (int) $idea['id'];
                      $canManageIdea = $isAdmin || ($currentUserId > 0 && (int) $idea['user_id'] === $currentUserId);
                      $currentVote = (string) ($userVotes[$ideaId] ?? '');
                      $isIdeaApproved = strtolower((string) $idea['statut']) === 'approuve';
                    ?>
                    <article class="idea-card <?= !empty($idea['is_winner']) ? 'is-winner' : '' ?>">
                      <div class="idea-meta">
                        <span><?= h(trim((string) $idea['auteur_prenom'] . ' ' . (string) $idea['auteur_nom']) ?: 'Utilisateur') ?></span>
                        <span><?= h(formatDateLabel((string) $idea['date_creation'])) ?></span>
                        <span class="status-badge <?= h(statusClass((string) $idea['statut'])) ?>"><?= h((string) $idea['statut']) ?></span>
                        <?php if (!empty($idea['is_winner'])): ?><span>Gagnant</span><?php endif; ?>
                      </div>
                      <p class="idea-content"><?= h((string) $idea['contenu']) ?></p>
                      <div class="idea-actions">
                        <div class="vote-actions">
                          <form method="post" action="vote-submit.php">
                            <input type="hidden" name="brainstorming_id" value="<?= $brainstormingId ?>" />
                            <input type="hidden" name="idee_id" value="<?= $ideaId ?>" />
                            <input type="hidden" name="type" value="like" />
                            <button class="btn btn-secondary vote-button <?= $currentVote === 'like' ? 'is-active' : '' ?>" type="submit" aria-label="Pouce haut" title="Pouce haut" <?= (!$canVote || !$isIdeaApproved) ? 'disabled' : '' ?>>
                              <span class="thumb-icon" aria-hidden="true">&#128077;</span>
                              <span class="vote-count"><?= (int) $idea['likes'] ?></span>
                            </button>
                          </form>
                          <form method="post" action="vote-submit.php">
                            <input type="hidden" name="brainstorming_id" value="<?= $brainstormingId ?>" />
                            <input type="hidden" name="idee_id" value="<?= $ideaId ?>" />
                            <input type="hidden" name="type" value="dislike" />
                            <button class="btn btn-secondary vote-button <?= $currentVote === 'dislike' ? 'is-active' : '' ?>" type="submit" aria-label="Pouce bas" title="Pouce bas" <?= (!$canVote || !$isIdeaApproved) ? 'disabled' : '' ?>>
                              <span class="thumb-icon" aria-hidden="true">&#128078;</span>
                              <span class="vote-count"><?= (int) $idea['dislikes'] ?></span>
                            </button>
                          </form>
                        </div>
                        <?php if ($canManageIdea): ?>
                          <a class="btn btn-secondary" href="brainstorming-detail.php?id=<?= $brainstormingId ?>&edit_idea=<?= $ideaId ?>">Modifier</a>
                          <form method="post" action="brainstorming-detail.php" data-delete-idea-form>
                            <input type="hidden" name="brainstorming_id" value="<?= $brainstormingId ?>" />
                            <input type="hidden" name="action" value="delete_idea" />
                            <input type="hidden" name="idea_id" value="<?= $ideaId ?>" />
                            <button class="btn btn-secondary" type="submit">Supprimer</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </article>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <aside class="idea-form-card">
              <h3 style="margin-top: 0;"><?= $editIdea ? 'Modifier une idee' : 'Proposer une idee' ?></h3>

              <?php if ($flashError !== ''): ?>
                <div class="alert error"><?= h($flashError) ?></div>
              <?php endif; ?>

              <?php if (!$isConnected): ?>
                <div class="alert error">Connectez-vous pour proposer une idee ou voter.</div>
                <a class="btn btn-primary" href="login.php">Se connecter</a>
              <?php elseif (!$canSubmitIdea): ?>
                <div class="alert error">Ce brainstorming n'accepte pas de nouvelles idees.</div>
              <?php else: ?>
                <form method="post" action="brainstorming-detail.php">
                  <input type="hidden" name="brainstorming_id" value="<?= $brainstormingId ?>" />
                  <input type="hidden" name="action" value="<?= $editIdea ? 'update_idea' : 'add_idea' ?>" />
                  <?php if ($editIdea): ?>
                    <input type="hidden" name="idea_id" value="<?= (int) $editIdea['id'] ?>" />
                  <?php endif; ?>
                  <textarea name="contenu" placeholder="Decrivez votre idee..."><?= h($formContent) ?></textarea>
                  <div class="idea-actions" style="margin-top: 1rem;">
                    <button class="btn btn-primary" type="submit"><?= $editIdea ? 'Enregistrer' : 'Soumettre' ?></button>
                    <?php if ($editIdea): ?>
                      <a class="btn btn-secondary" href="brainstorming-detail.php?id=<?= $brainstormingId ?>">Annuler</a>
                    <?php endif; ?>
                  </div>
                </form>
              <?php endif; ?>
            </aside>
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
      document.querySelectorAll('[data-delete-idea-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
          if (!confirm('Etes-vous sur de vouloir supprimer cette idee ?')) {
            event.preventDefault();
          }
        });
      });
    </script>
  </body>
</html>
