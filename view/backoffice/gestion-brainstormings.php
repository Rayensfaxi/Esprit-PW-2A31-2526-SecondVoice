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
    header('Location: ../frontoffice/assistant-accompagnements.php');
    exit;
}

require_once __DIR__ . '/../../controller/BrainstormingController.php';
require_once __DIR__ . '/../../controller/VoteController.php';

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

function getStatusLabel(string $status): string
{
    return match (strtolower(trim($status))) {
        'approuve' => 'Approuve',
        'desapprouve' => 'Desapprouve',
        default => 'En attente'
    };
}

function formatCreationDate(string $date): string
{
    if ($date === '') {
        return '-';
    }

    try {
        return (new DateTime($date))->format('d/m/Y');
    } catch (Throwable $exception) {
        return $date;
    }
}

function formatDateTimeLabel(string $date): string
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

function isVoteOpen(?string $startDate, ?string $endDate): bool
{
    $startDate = trim((string) $startDate);
    $endDate = trim((string) $endDate);

    if ($startDate === '' || $endDate === '') {
        return true;
    }

    try {
        $now = new DateTime();
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        return $now >= $start && $now <= $end;
    } catch (Throwable $exception) {
        return false;
    }
}

$controller = new BrainstormingController();
$voteController = new VoteController();
$feedbackType = '';
$feedbackMessage = '';

$formValues = [
    'titre' => '',
    'description' => '',
    'categorie' => ''
];

$search = trim((string) ($_GET['q'] ?? ''));
$selectedCategorie = trim((string) ($_GET['categorie'] ?? 'toutes'));
$selectedStatus = trim((string) ($_GET['statut'] ?? 'toutes'));
$allowedCategorieFilters = ['toutes', 'innovation', 'amelioration', 'processus', 'client', 'nouveau produit', 'autre'];
$allowedStatusFilters = ['toutes', 'en attente', 'approuve', 'desapprouve'];

if (!in_array($selectedCategorie, $allowedCategorieFilters, true)) {
    $selectedCategorie = 'toutes';
}

if (!in_array($selectedStatus, $allowedStatusFilters, true)) {
    $selectedStatus = 'toutes';
}

$status = (string) ($_GET['status'] ?? '');
$statusMessages = [
    'added' => 'Brainstorming ajoute avec succes.',
    'updated' => 'Brainstorming modifie avec succes.',
    'deleted' => 'Brainstorming supprime avec succes.',
    'approved' => 'Brainstorming approuve avec succes.',
    'disapproved' => 'Brainstorming desapprouve avec succes.',
    'vote_opened' => 'Periode de vote ouverte avec succes.',
    'vote_closed' => 'Periode de vote fermee avec succes.',
    'winner_calculated' => 'Gagnant calcule avec succes.'
];
if (isset($statusMessages[$status])) {
    $feedbackType = 'success';
    $feedbackMessage = $statusMessages[$status];
}

$editBrainstorming = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $editBrainstorming = $controller->getBrainstormingById((int) $_GET['edit']);
    if ($editBrainstorming) {
        $formValues = [
            'titre' => (string) ($editBrainstorming['titre'] ?? ''),
            'description' => (string) ($editBrainstorming['description'] ?? ''),
            'categorie' => (string) ($editBrainstorming['categorie'] ?? '')
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    try {
        if ($action === 'add') {
            $titre = trim((string) ($_POST['titre'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $categorie = trim((string) ($_POST['categorie'] ?? ''));

            $controller->addBrainstorming($titre, $description, $categorie);
            header('Location: gestion-brainstormings.php?status=added');
            exit;
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $titre = trim((string) ($_POST['titre'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $categorie = trim((string) ($_POST['categorie'] ?? ''));

            $controller->updateBrainstorming($id, $titre, $description, $categorie);
            header('Location: gestion-brainstormings.php?status=updated');
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->deleteBrainstorming($id);
            header('Location: gestion-brainstormings.php?status=deleted');
            exit;
        }

        if ($action === 'approve') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->updateBrainstormingStatus($id, 'approuve');
            header('Location: gestion-brainstormings.php?status=approved');
            exit;
        }

        if ($action === 'disapprove') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->updateBrainstormingStatus($id, 'desapprouve');
            header('Location: gestion-brainstormings.php?status=disapproved');
            exit;
        }

        if ($action === 'open_vote') {
            $id = (int) ($_POST['id'] ?? 0);
            $start = trim((string) ($_POST['vote_start'] ?? ''));
            $end = trim((string) ($_POST['vote_end'] ?? ''));
            if ($start === '') {
                $start = date('Y-m-d H:i:s');
            }
            if ($end === '') {
                $end = date('Y-m-d H:i:s', strtotime('+7 days'));
            }
            $result = $voteController->openVotePeriod($id, $start, $end);
            if (empty($result['success'])) {
                throw new RuntimeException((string) ($result['message'] ?? 'Ouverture du vote impossible.'));
            }
            header('Location: gestion-brainstormings.php?status=vote_opened');
            exit;
        }

        if ($action === 'close_vote') {
            $id = (int) ($_POST['id'] ?? 0);
            $result = $voteController->closeVotePeriod($id);
            if (empty($result['success'])) {
                throw new RuntimeException((string) ($result['message'] ?? 'Fermeture du vote impossible.'));
            }
            header('Location: gestion-brainstormings.php?status=vote_closed');
            exit;
        }

        if ($action === 'calculate_winner') {
            $id = (int) ($_POST['id'] ?? 0);
            $result = $voteController->calculateWinner($id);
            if (empty($result['success'])) {
                throw new RuntimeException((string) ($result['message'] ?? 'Calcul du gagnant impossible.'));
            }
            header('Location: gestion-brainstormings.php?status=winner_calculated');
            exit;
        }

        throw new InvalidArgumentException('Action invalide.');
    } catch (Throwable $exception) {
        $feedbackType = 'error';
        $feedbackMessage = $exception->getMessage();

        $formValues['titre'] = trim((string) ($_POST['titre'] ?? $formValues['titre']));
        $formValues['description'] = trim((string) ($_POST['description'] ?? $formValues['description']));
        $formValues['categorie'] = trim((string) ($_POST['categorie'] ?? $formValues['categorie']));

        if (isset($_POST['id']) && ctype_digit((string) $_POST['id'])) {
            $editBrainstorming = $controller->getBrainstormingById((int) $_POST['id']);
        }
    }
}

$brainstormings = $controller->getBrainstormings([
    'q' => $search,
    'categorie' => $selectedCategorie,
    'statut' => $selectedStatus
]);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des brainstormings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css?v=brainstorming-submenu-v5" />
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
              <a class="nav-link nav-sub-link" href="statistiques-brainstormings.php" data-nav="community" hidden><span class="nav-icon icon-community"></span><span>Statistiques</span></a>
              <a class="nav-link nav-sub-link" href="gestion-idees.php" data-nav="community" hidden><span class="nav-icon icon-community"></span><span>Gestion des idees</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-guides.php" data-nav="images"><span class="nav-icon icon-document"></span><span>Gestion des guides</span></a>
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
            <h1 class="page-title">Gestion des brainstormings</h1>
            <div class="page-subtitle">Gerez les sessions de brainstorming.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../frontoffice/index.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
            <?php include __DIR__ . '/partials/user-menu.php'; ?>
          </div>
        </div>

        <div class="page-grid users-page">
          <section class="users-hero">
            <div class="users-header">
              <div>
                <h2 class="section-title">Liste des brainstormings</h2>
                <p class="helper">Filtrez, approuvez et desapprouvez les brainstormings.</p>
              </div>
              <div class="users-actions">
                <a class="ghost-button" href="gestion-brainstormings.php">Reinitialiser</a>
                <a class="action-button secondary-action" href="export-brainstormings.php">Exporter Excel</a>
                <a class="action-button" href="#brainstorming-form"><?= $editBrainstorming ? 'Modifier brainstorming' : 'Ajouter' ?></a>
              </div>
            </div>

            <form class="users-filters" method="get" action="gestion-brainstormings.php">
              <div class="filter-field">
                <label for="brainstorming-search">Recherche</label>
                <input id="brainstorming-search" type="text" name="q" value="<?= h($search) ?>" placeholder="Titre" />
              </div>
              <div class="filter-field">
                <label for="brainstorming-categorie">Categorie</label>
                <select id="brainstorming-categorie" name="categorie">
                  <option value="toutes" <?= $selectedCategorie === 'toutes' ? 'selected' : '' ?>>Toutes</option>
                  <option value="innovation" <?= $selectedCategorie === 'innovation' ? 'selected' : '' ?>>Innovation</option>
                  <option value="amelioration" <?= $selectedCategorie === 'amelioration' ? 'selected' : '' ?>>Amelioration</option>
                  <option value="processus" <?= $selectedCategorie === 'processus' ? 'selected' : '' ?>>Processus</option>
                  <option value="client" <?= $selectedCategorie === 'client' ? 'selected' : '' ?>>Experience client</option>
                  <option value="nouveau produit" <?= $selectedCategorie === 'nouveau produit' ? 'selected' : '' ?>>Nouveau produit</option>
                  <option value="autre" <?= $selectedCategorie === 'autre' ? 'selected' : '' ?>>Autre</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="brainstorming-status">Statut</label>
                <select id="brainstorming-status" name="statut">
                  <option value="toutes" <?= $selectedStatus === 'toutes' ? 'selected' : '' ?>>Tous</option>
                  <option value="en attente" <?= $selectedStatus === 'en attente' ? 'selected' : '' ?>>En attente</option>
                  <option value="approuve" <?= $selectedStatus === 'approuve' ? 'selected' : '' ?>>Approuve</option>
                  <option value="desapprouve" <?= $selectedStatus === 'desapprouve' ? 'selected' : '' ?>>Desapprouve</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="brainstorming-filter-submit">Action</label>
                <button id="brainstorming-filter-submit" class="ghost-button" type="submit">Filtrer</button>
              </div>
            </form>
          </section>

          <section class="users-hero user-form-card" id="brainstorming-form">
            <div>
              <h2 class="section-title"><?= $editBrainstorming ? 'Modifier un brainstorming' : 'Ajouter un brainstorming' ?></h2>
            </div>

            <?php if ($feedbackMessage !== ''): ?>
              <div class="form-feedback <?= h($feedbackType) ?>"><?= h($feedbackMessage) ?></div>
            <?php endif; ?>

            <form id="crud-brainstorming-form" class="users-crud-form" method="post" action="gestion-brainstormings.php<?= $editBrainstorming ? '?edit=' . (int) $editBrainstorming['id'] : '' ?>" novalidate>
              <input type="hidden" name="action" value="<?= $editBrainstorming ? 'update' : 'add' ?>" />
              <?php if ($editBrainstorming): ?>
                <input type="hidden" name="id" value="<?= (int) $editBrainstorming['id'] ?>" />
              <?php endif; ?>

              <div class="user-form-grid">
                <div class="filter-field">
                  <label for="titre">Titre *</label>
                  <input id="titre" name="titre" type="text" value="<?= h($formValues['titre']) ?>" placeholder="Titre du brainstorming" required />
                </div>
                <div class="filter-field">
                  <label for="categorie">Categorie</label>
                  <select id="categorie" name="categorie">
                    <option value="innovation" <?= strtolower((string) $formValues['categorie']) === 'innovation' ? 'selected' : '' ?>>Innovation</option>
                    <option value="amelioration" <?= strtolower((string) $formValues['categorie']) === 'amelioration' ? 'selected' : '' ?>>Amelioration</option>
                    <option value="processus" <?= strtolower((string) $formValues['categorie']) === 'processus' ? 'selected' : '' ?>>Processus</option>
                    <option value="client" <?= strtolower((string) $formValues['categorie']) === 'client' ? 'selected' : '' ?>>Experience client</option>
                    <option value="nouveau produit" <?= strtolower((string) $formValues['categorie']) === 'nouveau produit' ? 'selected' : '' ?>>Nouveau produit</option>
                    <option value="autre" <?= strtolower((string) $formValues['categorie']) === 'autre' ? 'selected' : '' ?>>Autre</option>
                  </select>
                </div>
                <div class="filter-field">
                  <label for="description">Description</label>
                  <textarea id="description" name="description" placeholder="Description du brainstorming"><?= h($formValues['description']) ?></textarea>
                </div>
              </div>

              <p id="crud-feedback" class="form-feedback"></p>

              <div class="users-actions">
                <button class="action-button" type="submit"><?= $editBrainstorming ? 'Enregistrer les modifications' : 'Ajouter' ?></button>
                <?php if ($editBrainstorming): ?>
                  <a class="ghost-button" href="gestion-brainstormings.php">Annuler</a>
                <?php endif; ?>
              </div>
            </form>
          </section>

          <section class="table-card">
            <table class="table users-table">
              <thead>
                <tr>
                  <th>Titre</th>
                  <th>Categorie</th>
                  <th>Statut</th>
                  <th>Vote</th>
                  <th>Date creation</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($brainstormings) === 0): ?>
                  <tr>
                    <td colspan="6">Aucun brainstorming trouve.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($brainstormings as $brainstorming): ?>
                    <?php
                      $currentStatus = strtolower(trim((string) $brainstorming['statut']));
                      $voteOpen = isVoteOpen((string) ($brainstorming['vote_start'] ?? ''), (string) ($brainstorming['vote_end'] ?? ''));
                    ?>
                    <tr>
                      <td>
                        <div class="user-cell">
                          <div>
                            <strong><?= h($brainstorming['titre']) ?></strong>
                            <span>ID #<?= (int) $brainstorming['id'] ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?= h($brainstorming['categorie']) ?></td>
                      <td><span class="status-pill <?= h(getStatusClass((string) $brainstorming['statut'])) ?>"><?= h(getStatusLabel((string) $brainstorming['statut'])) ?></span></td>
                      <td>
                        <?php if ($voteOpen): ?>
                          <span class="status-pill active">Ouvert</span>
                        <?php else: ?>
                          <span class="status-pill risk">Ferme</span>
                        <?php endif; ?>
                      </td>
                      <td><?= h(formatCreationDate((string) $brainstorming['dateCreation'])) ?></td>
                      <td>
                        <div class="table-actions">
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="approuver">
                            <input type="hidden" name="action" value="approve" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <button class="ghost-button" type="submit" <?= $currentStatus === 'approuve' ? 'disabled' : '' ?>>Approuver</button>
                          </form>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="desapprouver">
                            <input type="hidden" name="action" value="disapprove" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <button class="ghost-button danger" type="submit" <?= $currentStatus === 'desapprouve' ? 'disabled' : '' ?>>Desapprouver</button>
                          </form>
                          <?php if (!$voteOpen): ?>
                            <form class="inline-delete-form vote-window-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="ouvrir le vote">
                              <input type="hidden" name="action" value="open_vote" />
                              <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                              <button class="ghost-button" type="submit">Ouvrir vote</button>
                            </form>
                          <?php else: ?>
                            <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="fermer le vote">
                              <input type="hidden" name="action" value="close_vote" />
                              <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                              <button class="ghost-button danger" type="submit">Fermer vote</button>
                            </form>
                          <?php endif; ?>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="calculer le gagnant de">
                            <input type="hidden" name="action" value="calculate_winner" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <button class="ghost-button" type="submit">Calculer le gagnant</button>
                          </form>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-delete-form>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
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
        const form = document.getElementById("crud-brainstorming-form");
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

            const titre = (form.titre.value || "").trim();

            if (titre === "") {
              event.preventDefault();
              showError("Le titre est obligatoire.");
              return;
            }

            if (titre.length < 3) {
              event.preventDefault();
              showError("Le titre doit contenir au moins 3 caracteres.");
              return;
            }
          });
        }

        const statusForms = document.querySelectorAll('[data-status-form]');
        statusForms.forEach(form => {
          form.addEventListener('submit', function (event) {
            const actionLabel = form.getAttribute('data-status-label') || 'changer le statut de';
            const confirmed = confirm(`Etes-vous sur de vouloir ${actionLabel} ce brainstorming ?`);
            if (!confirmed) {
              event.preventDefault();
            }
          });
        });

        const deleteForms = document.querySelectorAll('[data-delete-form]');
        deleteForms.forEach(form => {
          form.addEventListener('submit', function (event) {
            if (!confirm('Etes-vous sur de vouloir supprimer ce brainstorming ?')) {
              event.preventDefault();
            }
          });
        });
      })();
    </script>
      <script src="assets/app.js?v=brainstorming-submenu-v5"></script>
  </body>
</html>


