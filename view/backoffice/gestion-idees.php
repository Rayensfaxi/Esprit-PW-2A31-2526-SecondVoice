<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontoffice/login.php?status=auth_required');
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    header('Location: ../frontoffice/profile.php?status=forbidden');
    exit;
}

$roleSession = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
if ($roleSession === 'agent') {
    header('Location: gestion-accompagnements.php');
    exit;
}

require_once __DIR__ . '/../../controller/IdeaController.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getStatusClass(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'approuve' => 'active',
        'en attente' => 'pending',
        'desapprouve' => 'risk',
        default => 'pending'
    };
}

function formatDateTime(string $date): string
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

function getAuthorName(array $idea): string
{
    $nom = (string) ($idea['auteur_nom'] ?? '');
    $prenom = (string) ($idea['auteur_prenom'] ?? '');
    
    if ($nom !== '' && $prenom !== '') {
        return $prenom . ' ' . $nom;
    }
    
    return 'Utilisateur inconnu';
}

$controller = new IdeaController();
$feedbackType = '';
$feedbackMessage = '';

$search = trim((string) ($_GET['q'] ?? ''));

$status = (string) ($_GET['status'] ?? '');
$statusMessages = [
    'updated' => 'Idee modifiee avec succes.',
    'deleted' => 'Idee supprimee avec succes.',
    'approved' => 'Idee approuvee avec succes.',
    'disapproved' => 'Idee desapprouvee avec succes.'
];
if (isset($statusMessages[$status])) {
    $feedbackType = 'success';
    $feedbackMessage = $statusMessages[$status];
}

$editIdea = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $editIdea = $controller->getIdeaById((int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    try {
        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $contenu = trim((string) ($_POST['contenu'] ?? ''));

            if ($contenu === '') {
                throw new InvalidArgumentException('Le contenu est obligatoire.');
            }
            if (strlen($contenu) < 10) {
                throw new InvalidArgumentException('Le contenu doit contenir au moins 10 caracteres.');
            }

            $controller->updateIdea($id, $contenu, 0, true);
            header('Location: gestion-idees.php?status=updated');
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->deleteIdea($id, 0, true);
            header('Location: gestion-idees.php?status=deleted');
            exit;
        }

        if ($action === 'approve') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->updateIdeaStatus($id, 'approuve');
            header('Location: gestion-idees.php?status=approved');
            exit;
        }

        if ($action === 'disapprove') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->updateIdeaStatus($id, 'desapprouve');
            header('Location: gestion-idees.php?status=disapproved');
            exit;
        }

        throw new InvalidArgumentException('Action invalide.');
    } catch (Throwable $exception) {
        $feedbackType = 'error';
        $feedbackMessage = $exception->getMessage();

        if (isset($_POST['id']) && ctype_digit((string) $_POST['id'])) {
            $editIdea = $controller->getIdeaById((int) $_POST['id']);
        }
    }
}

$ideas = $controller->getAllIdeas([
    'q' => $search
]);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des idees</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
  </head>
  <body data-page="community">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
</div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-brainstormings.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des brainstormings</span></a>
              <a class="nav-link" href="gestion-idees.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des idees</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-evenements.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des evenements</span></a>
              <a class="nav-link" href="gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>

        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des idees</h1>
            <div class="page-subtitle">Gerez les idees soumises par les utilisateurs.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../frontoffice/index.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="page-grid users-page">
          <section class="users-hero">
            <div class="users-header">
              <div>
                <h2 class="section-title">Liste des idees</h2>
                <p class="helper">Consultez, modifiez et approuvez les idees.</p>
              </div>
              <div class="users-actions">
                <a class="ghost-button" href="gestion-idees.php">Reinitialiser</a>
              </div>
            </div>

            <form class="users-filters" method="get" action="gestion-idees.php">
              <div class="filter-field">
                <label for="idea-search">Recherche</label>
                <input id="idea-search" type="text" name="q" value="<?= h($search) ?>" placeholder="Contenu de l'idee" />
              </div>
              <div class="filter-field">
                <label for="idea-filter-submit">Action</label>
                <button id="idea-filter-submit" class="ghost-button" type="submit">Filtrer</button>
              </div>
            </form>
          </section>


          <section class="table-card">
            <table class="table users-table">
              <thead>
                <tr>
                  <th>Brainstorming</th>
                  <th>Auteur</th>
                  <th>Contenu</th>
                  <th>Statut</th>
                  <th>Date creation</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($ideas) === 0): ?>
                  <tr>
                    <td colspan="6">Aucune idee trouvee.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($ideas as $idea): ?>
                    <?php $currentStatus = strtolower(trim((string) $idea['statut'])); ?>
                    <tr>
                      <td>
                        <div class="user-cell">
                          <div>
                            <strong><?= h($idea['brainstorming_titre']) ?></strong>
                            <span>ID #<?= (int) $idea['brainstorming_id'] ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?= h(getAuthorName($idea)) ?></td>
                      <td><?= h(substr($idea['contenu'], 0, 80)) ?><?= strlen($idea['contenu']) > 80 ? '...' : '' ?></td>
                      <td><span class="status-pill <?= h(getStatusClass((string) $idea['statut'])) ?>"><?= ucfirst(h((string) $idea['statut'])) ?></span></td>
                      <td><?= h(formatDateTime((string) $idea['date_creation'])) ?></td>
                      <td>
                        <div class="table-actions">
                          <form class="inline-delete-form" method="post" action="gestion-idees.php" data-delete-form>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $idea['id'] ?>" />
                            <button class="ghost-button danger" type="submit">Supprimer</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </section>
        </div>
      </main>
    </div>

    <script>
      (function () {
        const form = document.getElementById("crud-idea-form");
        const feedback = document.getElementById("crud-feedback");

        function showError(message) {
          if (!feedback) return;
          feedback.textContent = message;
          feedback.classList.add("error");
          feedback.classList.remove("success");
        }

        function clearFeedback() {
          if (!feedback) return;
          feedback.textContent = "";
          feedback.classList.remove("error");
          feedback.classList.remove("success");
        }

        if (form) {
          form.addEventListener("submit", function (event) {
            clearFeedback();

            const contenu = (form.contenu.value || "").trim();

            if (contenu === "") {
              event.preventDefault();
              showError("Le contenu est obligatoire.");
              return;
            }

            if (contenu.length < 10) {
              event.preventDefault();
              showError("Le contenu doit contenir au moins 10 caracteres.");
              return;
            }
          });
        }

        const statusForms = document.querySelectorAll('[data-status-form]');
        statusForms.forEach(form => {
          form.addEventListener('submit', function (event) {
            const actionLabel = form.getAttribute('data-status-label') || 'changer le statut de';
            const confirmed = confirm(`Etes-vous sur de vouloir ${actionLabel} cette idee ?`);
            if (!confirmed) {
              event.preventDefault();
            }
          });
        });

        const deleteForms = document.querySelectorAll('[data-delete-form]');
        deleteForms.forEach(form => {
          form.addEventListener('submit', function (event) {
            const confirmed = confirm('Etes-vous sur de vouloir supprimer cette idee ?');
            if (!confirmed) {
              event.preventDefault();
            }
          });
        });
      })();
    </script>
      <script src="assets/app.js"></script>
  </body>
</html>
