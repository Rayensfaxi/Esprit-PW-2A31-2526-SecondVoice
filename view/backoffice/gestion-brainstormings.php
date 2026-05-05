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

require_once __DIR__ . '/../../config.php';
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

$controller = new BrainstormingController();
$feedbackType = '';
$feedbackMessage = '';

$formValues = [
    'titre' => '',
    'description' => '',
    'categorie' => ''
];

$search = trim((string) ($_GET['q'] ?? ''));
$selectedCategorie = trim((string) ($_GET['categorie'] ?? 'toutes'));
$selectedStatus = trim((string) ($_GET['status'] ?? 'toutes'));
$allowedCategorieFilters = ['toutes', 'innovation', 'amelioration', 'nouveau produit', 'autre'];
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
    // Override with custom message if provided
    if ($status === 'winner_calculated' && isset($_GET['message'])) {
        $feedbackMessage = urldecode($_GET['message']);
    }
    $showSweetAlert = true;
} else {
    $showSweetAlert = false;
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
        if ($action === 'export_excel') {
            $controller->exportToExcel();
        }

        if ($action === 'add') {
            $titre = trim((string) ($_POST['titre'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $categorie = trim((string) ($_POST['categorie'] ?? ''));

            if ($titre === '') {
                throw new InvalidArgumentException('Le titre est obligatoire.');
            }
            if (strlen($titre) < 3) {
                throw new InvalidArgumentException('Le titre doit contenir au moins 3 caracteres.');
            }
            if ($description === '') {
                throw new InvalidArgumentException('La description est obligatoire.');
            }
            if ($categorie === '') {
                throw new InvalidArgumentException('La categorie est obligatoire.');
            }

            $controller->addBrainstorming($titre, $description, $categorie, true);
            header('Location: gestion-brainstormings.php?status=added');
            exit;
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $titre = trim((string) ($_POST['titre'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $categorie = trim((string) ($_POST['categorie'] ?? ''));
            $voteStart = trim((string) ($_POST['vote_start'] ?? ''));
            $voteEnd = trim((string) ($_POST['vote_end'] ?? ''));

            if ($titre === '') {
                throw new InvalidArgumentException('Le titre est obligatoire.');
            }
            if (strlen($titre) < 3) {
                throw new InvalidArgumentException('Le titre doit contenir au moins 3 caracteres.');
            }
            if ($description === '') {
                throw new InvalidArgumentException('La description est obligatoire.');
            }
            if ($categorie === '') {
                throw new InvalidArgumentException('La categorie est obligatoire.');
            }

            $controller->updateBrainstorming($id, $titre, $description, $categorie);

            if ($voteStart !== '' || $voteEnd !== '') {
                $conn = Config::getConnexion();
                $stmt = $conn->prepare('UPDATE brainstorming SET vote_start = :vote_start, vote_end = :vote_end WHERE id = :id');
                $stmt->execute([
                    ':vote_start' => $voteStart !== '' ? $voteStart : null,
                    ':vote_end' => $voteEnd !== '' ? $voteEnd : null,
                    ':id' => $id
                ]);
            }

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
            $voteController = new VoteController();
            $id = (int) ($_POST['id'] ?? 0);
            $startDate = trim((string) ($_POST['vote_start'] ?? ''));
            $endDate = trim((string) ($_POST['vote_end'] ?? ''));

            if ($startDate === '' || $endDate === '') {
                throw new InvalidArgumentException('Les dates de debut et de fin sont obligatoires.');
            }

            $result = $voteController->openVotePeriod($id, $startDate, $endDate);
            if ($result['success']) {
                header('Location: gestion-brainstormings.php?status=vote_opened');
            } else {
                throw new InvalidArgumentException($result['message']);
            }
            exit;
        }

        if ($action === 'close_vote') {
            $voteController = new VoteController();
            $id = (int) ($_POST['id'] ?? 0);
            $result = $voteController->closeVotePeriod($id);
            if ($result['success']) {
                header('Location: gestion-brainstormings.php?status=vote_closed');
            } else {
                throw new InvalidArgumentException($result['message']);
            }
            exit;
        }

        if ($action === 'calculate_winner') {
            $voteController = new VoteController();
            $id = (int) ($_POST['id'] ?? 0);
            $result = $voteController->calculateWinner($id);
            if ($result['success']) {
                header('Location: gestion-brainstormings.php?status=winner_calculated&message=' . urlencode($result['message']));
            } else {
                throw new InvalidArgumentException($result['message']);
            }
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

$voteController = new VoteController();
$brainstormings = $controller->getBrainstormings([
    'q' => $search,
    'categorie' => $selectedCategorie,
    'statut' => $selectedStatus
]);

// Get vote status for each brainstorming
$brainstormingVoteStatus = [];
foreach ($brainstormings as $brainstorming) {
    $voteStatus = $voteController->getBrainstormingVoteStatus((int) $brainstorming['id']);
    $brainstormingVoteStatus[$brainstorming['id']] = $voteStatus;
}
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
    <link rel="stylesheet" href="assets/style.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .table-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      max-width: 300px;
    }
    .table-actions form {
      display: inline;
    }
    .users-table th:last-child,
    .users-table td:last-child {
      min-width: 280px;
    }
  </style>
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
              <a class="nav-link" href="gestion-brainstorming-stats.php" data-nav="stats"><span class="nav-icon icon-activity"></span><span>Statistiques</span></a>
              <a class="nav-link" href="gestion-idees.php" data-nav="ideas"><span class="nav-icon icon-community"></span><span>Gestion des idees</span></a>
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
            <h1 class="page-title">Gestion des brainstormings</h1>
            <div class="page-subtitle">Gerez les sessions de brainstorming.</div>
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
                <h2 class="section-title">Liste des brainstormings</h2>
                <p class="helper">Filtrez, approuvez et desapprouvez les brainstormings.</p>
              </div>
              <div class="users-actions">
                <a class="ghost-button" href="gestion-brainstormings.php">Reinitialiser</a>
                <form method="post" action="gestion-brainstormings.php" style="display: inline;">
                  <input type="hidden" name="action" value="export_excel" />
                  <button class="action-button" type="submit" style="background: linear-gradient(135deg, #9c51ff, #7b2ff7);">Exporter Excel</button>
                </form>
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
                  <option value="nouveau produit" <?= $selectedCategorie === 'nouveau produit' ? 'selected' : '' ?>>Nouveau produit</option>
                  <option value="autre" <?= $selectedCategorie === 'autre' ? 'selected' : '' ?>>Autre</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="brainstorming-status">Statut</label>
                <select id="brainstorming-status" name="status">
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
              <h2 class="section-title">Ajouter un brainstorming</h2>
            </div>

            <?php if ($feedbackMessage !== ''): ?>
              <div class="form-feedback <?= h($feedbackType) ?>"><?= h($feedbackMessage) ?></div>
            <?php endif; ?>

            <form id="crud-brainstorming-form" class="users-crud-form" method="post" action="gestion-brainstormings.php" novalidate>
              <input type="hidden" name="action" value="add" />

              <div class="user-form-grid">
                <div class="filter-field">
                  <label for="titre">Titre *</label>
                  <input id="titre" name="titre" type="text" value="<?= h($formValues['titre']) ?>" placeholder="Titre du brainstorming" />
                </div>
                <div class="filter-field">
                  <label for="categorie">Categorie</label>
                  <select id="categorie" name="categorie">
                    <option value="innovation" <?= strtolower((string) $formValues['categorie']) === 'innovation' ? 'selected' : '' ?>>Innovation</option>
                    <option value="amelioration" <?= strtolower((string) $formValues['categorie']) === 'amelioration' ? 'selected' : '' ?>>Amelioration</option>
                    <option value="nouveau produit" <?= strtolower((string) $formValues['categorie']) === 'nouveau produit' ? 'selected' : '' ?>>Nouveau produit</option>
                    <option value="autre" <?= strtolower((string) $formValues['categorie']) === 'autre' ? 'selected' : '' ?>>Autre</option>
                  </select>
                </div>
                <div class="filter-field full-width">
                  <label for="description">Description</label>
                  <textarea id="description" name="description" placeholder="Description du brainstorming"><?= h($formValues['description']) ?></textarea>
                </div>
                <?php if ($editBrainstorming): ?>
                <div class="filter-field">
                  <label for="vote_start">Date debut vote (YYYY-MM-DD HH:MM)</label>
                  <input id="vote_start" name="vote_start" type="text" value="<?= h($editBrainstorming['vote_start'] ?? '') ?>" placeholder="YYYY-MM-DD HH:MM" />
                </div>
                <div class="filter-field">
                  <label for="vote_end">Date fin vote (YYYY-MM-DD HH:MM)</label>
                  <input id="vote_end" name="vote_end" type="text" value="<?= h($editBrainstorming['vote_end'] ?? '') ?>" placeholder="YYYY-MM-DD HH:MM" />
                </div>
                <div class="filter-field">
                  <label>Statut vote</label>
                  <?php
                  $voteStatus = $voteController->getBrainstormingVoteStatus((int) $editBrainstorming['id']);
                  if ($voteStatus && $voteStatus['isOpen']): ?>
                    <span class="status-pill active">Ouvert</span>
                  <?php elseif ($voteStatus && $voteStatus['status'] === 'open'): ?>
                    <span class="status-pill pending">Fermé (temps)</span>
                  <?php else: ?>
                    <span class="status-pill risk">Fermé</span>
                  <?php endif; ?>
                </div>
                <div class="filter-field">
                  <label>Actions vote</label>
                  <?php if ($voteStatus && $voteStatus['status'] === 'open' && !$voteStatus['isOpen']): ?>
                    <form method="post" action="gestion-brainstormings.php" style="display: inline;">
                      <input type="hidden" name="action" value="close_vote" />
                      <input type="hidden" name="id" value="<?= (int) $editBrainstorming['id'] ?>" />
                      <button class="ghost-button danger" type="submit">Fermer vote</button>
                    </form>
                  <?php elseif ($voteStatus && $voteStatus['status'] !== 'open'): ?>
                    <form method="post" action="gestion-brainstormings.php" style="display: inline;">
                      <input type="hidden" name="action" value="open_vote" />
                      <input type="hidden" name="id" value="<?= (int) $editBrainstorming['id'] ?>" />
                      <input type="hidden" name="vote_start" value="<?= h($editBrainstorming['vote_start'] ?? date('Y-m-d H:i')) ?>" />
                      <input type="hidden" name="vote_end" value="<?= h($editBrainstorming['vote_end'] ?? date('Y-m-d H:i', strtotime('+7 days'))) ?>" />
                      <button class="ghost-button" type="submit">Ouvrir vote</button>
                    </form>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>

              <p id="crud-feedback" class="form-feedback"></p>

              <div class="users-actions">
                <button class="action-button" type="submit"><?= $editBrainstorming ? 'Modifier' : 'Ajouter' ?></button>
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
                    <?php $currentStatus = strtolower(trim((string) $brainstorming['statut'])); ?>
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
                      <td><span class="status-pill <?= h(getStatusClass((string) $brainstorming['statut'])) ?>"><?= ucfirst(h((string) $brainstorming['statut'])) ?></span></td>
                      <td>
                        <?php
                        $voteStatus = $brainstormingVoteStatus[$brainstorming['id']] ?? null;
                        if ($voteStatus && $voteStatus['isOpen']): ?>
                          <span class="status-pill active">Ouvert</span>
                        <?php elseif ($voteStatus && $voteStatus['status'] === 'open'): ?>
                          <span class="status-pill pending">Fermé (temps)</span>
                        <?php else: ?>
                          <span class="status-pill risk">Fermé</span>
                        <?php endif; ?>
                      </td>
                      <td><?= h(formatCreationDate((string) $brainstorming['dateCreation'])) ?></td>
                      <td>
                        <div class="table-actions">
                          <?php if ($currentStatus !== 'approuve' && $currentStatus !== 'desapprouve'): ?>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="approuver">
                            <input type="hidden" name="action" value="approve" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <button class="ghost-button" type="submit">Approuver</button>
                          </form>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php" data-status-form data-status-label="desapprouver">
                            <input type="hidden" name="action" value="disapprove" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <button class="ghost-button danger" type="submit">Desapprouver</button>
                          </form>
                          <?php endif; ?>
                          <?php
                          $voteStatus = $brainstormingVoteStatus[$brainstorming['id']] ?? null;
                          if ($voteStatus && $voteStatus['status'] === 'open'): ?>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php">
                            <input type="hidden" name="action" value="close_vote" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <button class="ghost-button danger" type="submit">Fermer vote</button>
                          </form>
                          <?php elseif ($voteStatus && $voteStatus['status'] !== 'open'): ?>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php">
                            <input type="hidden" name="action" value="open_vote" />
                            <input type="hidden" name="id" value="<?= (int) $brainstorming['id'] ?>" />
                            <input type="hidden" name="vote_start" value="<?= date('Y-m-d H:i') ?>" />
                            <input type="hidden" name="vote_end" value="<?= date('Y-m-d H:i', strtotime('+7 days')) ?>" />
                            <button class="ghost-button" type="submit">Ouvrir vote</button>
                          </form>
                          <?php endif; ?>
                          <form class="inline-delete-form" method="post" action="gestion-brainstormings.php">
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

        // Show SweetAlert on success
        <?php if ($showSweetAlert): ?>
        Swal.fire({
          title: "Succès!",
          text: "<?= h($feedbackMessage) ?>",
          icon: "success",
          draggable: true
        });
        <?php endif; ?>
      })();
    </script>
      <script src="assets/app.js"></script>
  </body>
</html>


