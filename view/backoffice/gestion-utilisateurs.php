<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontoffice/login.php?status=auth_required');
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    header('Location: ../frontoffice/profile.php?status=forbidden');
    exit;
}

require_once __DIR__ . '/../../controller/UtilisateurController.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function makeInitials(string $nom, string $prenom): string
{
    $first = substr(trim($nom), 0, 1);
    $second = substr(trim($prenom), 0, 1);
    $initials = strtoupper($first . $second);
    return $initials !== '' ? $initials : 'SV';
}

function getStatusClass(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'actif' => 'active',
        'inactif' => 'review',
        'bloque' => 'risk',
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

$controller = new UtilisateurController();
$feedbackType = '';
$feedbackMessage = '';

$formValues = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'role' => 'agent'
];

$search = trim((string) ($_GET['q'] ?? ''));
$selectedRole = strtolower(trim((string) ($_GET['role'] ?? 'tout')));
$allowedRoleFilters = ['tout', 'admin', 'agent', 'client'];
if (!in_array($selectedRole, $allowedRoleFilters, true)) {
    $selectedRole = 'tout';
}

$status = (string) ($_GET['status'] ?? '');
$statusMessages = [
    'added' => 'Utilisateur ajoute avec succes.',
    'updated' => 'Utilisateur modifie avec succes.',
    'deleted' => 'Utilisateur supprime avec succes.'
];
if (isset($statusMessages[$status])) {
    $feedbackType = 'success';
    $feedbackMessage = $statusMessages[$status];
}

$editUser = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $editUser = $controller->getUserById((int) $_GET['edit']);
    if ($editUser) {
        $formValues = [
            'nom' => (string) ($editUser['nom'] ?? ''),
            'prenom' => (string) ($editUser['prenom'] ?? ''),
            'email' => (string) ($editUser['email'] ?? ''),
            'telephone' => (string) ($editUser['telephone'] ?? ''),
            'role' => (string) ($editUser['role'] ?? 'agent')
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    try {
        if ($action === 'add') {
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $prenom = trim((string) ($_POST['prenom'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $telephone = trim((string) ($_POST['telephone'] ?? ''));
            $role = trim((string) ($_POST['role'] ?? 'agent'));
            $password = (string) ($_POST['mot_de_passe'] ?? '');

            if (!in_array(strtolower($role), ['admin', 'agent'], true)) {
                throw new InvalidArgumentException('En backoffice, vous pouvez ajouter uniquement des admins ou des agents.');
            }

            $controller->addUser($nom, $prenom, $email, $password, $telephone, $role);
            header('Location: gestion-utilisateurs.php?status=added');
            exit;
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $prenom = trim((string) ($_POST['prenom'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $telephone = trim((string) ($_POST['telephone'] ?? ''));
            $role = trim((string) ($_POST['role'] ?? 'agent'));
            $password = trim((string) ($_POST['mot_de_passe'] ?? ''));

            $controller->updateUser($id, $nom, $prenom, $email, $telephone, $role, $password !== '' ? $password : null);
            header('Location: gestion-utilisateurs.php?status=updated');
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $controller->deleteUser($id);
            header('Location: gestion-utilisateurs.php?status=deleted');
            exit;
        }

        throw new InvalidArgumentException('Action invalide.');
    } catch (Throwable $exception) {
        $feedbackType = 'error';
        $feedbackMessage = $exception->getMessage();

        $formValues['nom'] = trim((string) ($_POST['nom'] ?? $formValues['nom']));
        $formValues['prenom'] = trim((string) ($_POST['prenom'] ?? $formValues['prenom']));
        $formValues['email'] = trim((string) ($_POST['email'] ?? $formValues['email']));
        $formValues['telephone'] = trim((string) ($_POST['telephone'] ?? $formValues['telephone']));
        $formValues['role'] = trim((string) ($_POST['role'] ?? $formValues['role']));

        if (isset($_POST['id']) && ctype_digit((string) $_POST['id'])) {
            $editUser = $controller->getUserById((int) $_POST['id']);
        }
    }
}

$users = $controller->getUsers([
    'q' => $search,
    'role' => $selectedRole
]);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des utilisateurs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
  </head>
  <body data-page="profile">
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
              <a class="nav-link" href="gestion-rendezvous.html" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-documents.html" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des documents</span></a>
              <a class="nav-link" href="gestion-reclamations.html" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des utilisateurs</h1>
            
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../frontoffice/index.html">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="page-grid users-page">
          <section class="users-hero">
            <div class="users-header">
              <div>
                <h2 class="section-title">Liste des utilisateurs</h2>
                <p class="helper">Filtrez, modifiez et supprimez les comptes en un seul endroit.</p>
              </div>
              <div class="users-actions">
                <a class="ghost-button" href="gestion-utilisateurs.php">Reinitialiser</a>
                <a class="action-button" href="#user-form"><?= $editUser ? 'Modifier utilisateur' : 'Ajouter' ?></a>
              </div>
            </div>

            <form class="users-filters" method="get" action="gestion-utilisateurs.php">
              <div class="filter-field">
                <label for="user-search">Recherche</label>
                <input id="user-search" type="text" name="q" value="<?= h($search) ?>" placeholder="Nom, prenom, email, telephone" />
              </div>
              <div class="filter-field">
                <label for="user-role">Role</label>
                <select id="user-role" name="role">
                  <option value="tout" <?= $selectedRole === 'tout' ? 'selected' : '' ?>>Tout</option>
                  <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                  <option value="agent" <?= $selectedRole === 'agent' ? 'selected' : '' ?>>Agent</option>
                  <option value="client" <?= $selectedRole === 'client' ? 'selected' : '' ?>>Client</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="user-filter-submit">Action</label>
                <button id="user-filter-submit" class="ghost-button" type="submit">Filtrer</button>
              </div>
            </form>
          </section>

          <section class="users-hero user-form-card" id="user-form">
            <div>
              <h2 class="section-title"><?= $editUser ? 'Modifier un utilisateur' : 'Ajouter un Admin/Agent' ?></h2>
              
            </div>

            <?php if ($feedbackMessage !== ''): ?>
              <div class="form-feedback <?= h($feedbackType) ?>"><?= h($feedbackMessage) ?></div>
            <?php endif; ?>

            <form id="crud-user-form" class="users-crud-form" method="post" action="gestion-utilisateurs.php<?= $editUser ? '?edit=' . (int) $editUser['id'] : '' ?>" novalidate>
              <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'add' ?>" />
              <?php if ($editUser): ?>
                <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>" />
              <?php endif; ?>

              <div class="user-form-grid">
                <div class="filter-field">
                  <label for="nom">Nom</label>
                  <input id="nom" name="nom" type="text" value="<?= h($formValues['nom']) ?>" placeholder="Nom" />
                </div>
                <div class="filter-field">
                  <label for="prenom">Prenom</label>
                  <input id="prenom" name="prenom" type="text" value="<?= h($formValues['prenom']) ?>" placeholder="Prenom" />
                </div>
                <div class="filter-field">
                  <label for="email">E-mail</label>
                  <input id="email" name="email" type="text" value="<?= h($formValues['email']) ?>" placeholder="utilisateur@mail.com" />
                </div>
                <div class="filter-field">
                  <label for="telephone">Telephone</label>
                  <input id="telephone" name="telephone" type="text" value="<?= h($formValues['telephone']) ?>" placeholder="+21612345678" />
                </div>
                <div class="filter-field">
                  <label for="role">Role</label>
                  <select id="role" name="role">
                    <option value="admin" <?= strtolower((string) $formValues['role']) === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="agent" <?= strtolower((string) $formValues['role']) === 'agent' ? 'selected' : '' ?>>Agent</option>
                    <?php if ($editUser): ?>
                      <option value="client" <?= strtolower((string) $formValues['role']) === 'client' ? 'selected' : '' ?>>Client</option>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="filter-field">
                  <label for="mot_de_passe">Mot de passe <?= $editUser ? '(laisser vide pour conserver)' : '' ?></label>
                  <input id="mot_de_passe" name="mot_de_passe" type="password" placeholder="Minimum 6 caracteres" />
                </div>
              </div>

              <p id="crud-feedback" class="form-feedback"></p>

              <div class="users-actions">
                <button class="action-button" type="submit"><?= $editUser ? 'Enregistrer les modifications' : 'Ajouter' ?></button>
                <?php if ($editUser): ?>
                  <a class="ghost-button" href="gestion-utilisateurs.php">Annuler</a>
                <?php endif; ?>
              </div>
            </form>
          </section>

          <section class="table-card">
            <table class="table users-table">
              <thead>
                <tr>
                  <th>Utilisateur</th>
                  <th>Email</th>
                  <th>Telephone</th>
                  <th>Role</th>
                  <th>Statut compte</th>
                  <th>Date creation</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($users) === 0): ?>
                  <tr>
                    <td colspan="7">Aucun utilisateur trouve.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($users as $user): ?>
                    <tr>
                      <td>
                        <div class="user-cell">
                          <span class="user-avatar"><?= h(makeInitials((string) $user['nom'], (string) $user['prenom'])) ?></span>
                          <div>
                            <strong><?= h($user['nom'] . ' ' . $user['prenom']) ?></strong>
                            <span>ID #<?= (int) $user['id'] ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?= h($user['email']) ?></td>
                      <td><?= h($user['telephone']) ?></td>
                      <td><span class="status-pill active"><?= ucfirst(h((string) $user['role'])) ?></span></td>
                      <td><span class="status-pill <?= h(getStatusClass((string) ($user['statut_compte'] ?? 'actif'))) ?>"><?= ucfirst(h((string) ($user['statut_compte'] ?? 'actif'))) ?></span></td>
                      <td><?= h(formatCreationDate((string) ($user['date_creation'] ?? ''))) ?></td>
                      <td>
                        <div class="table-actions">
                          <a class="ghost-button" href="gestion-utilisateurs.php?edit=<?= (int) $user['id'] ?>#user-form">Modifier</a>
                          <form class="inline-delete-form" method="post" action="gestion-utilisateurs.php" data-delete-form>
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>" />
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
        const form = document.getElementById("crud-user-form");
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

            const action = (form.querySelector('input[name="action"]')?.value || "").toLowerCase();
            const nom = (form.nom.value || "").trim();
            const prenom = (form.prenom.value || "").trim();
            const email = (form.email.value || "").trim();
            const telephone = (form.telephone.value || "").trim().replace(/\s+/g, "");
            const role = (form.role.value || "").trim().toLowerCase();
            const password = form.mot_de_passe.value || "";

            const namePattern = /^[A-Za-zÃ¯Â¿Â½-Ã¯Â¿Â½Ã¯Â¿Â½-Ã¯Â¿Â½Ã¯Â¿Â½-Ã¯Â¿Â½\s'-]{2,60}$/;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phonePattern = /^\+?[0-9]{8,15}$/;

            if (!namePattern.test(nom)) {
              event.preventDefault();
              showError("Le nom doit contenir 2 a 60 caracteres alphabetiques.");
              return;
            }

            if (!namePattern.test(prenom)) {
              event.preventDefault();
              showError("Le prenom doit contenir 2 a 60 caracteres alphabetiques.");
              return;
            }

            if (!emailPattern.test(email)) {
              event.preventDefault();
              showError("Adresse e-mail invalide.");
              return;
            }

            if (!phonePattern.test(telephone)) {
              event.preventDefault();
              showError("Le telephone doit contenir entre 8 et 15 chiffres.");
              return;
            }

            if ((action === "add" && !["admin", "agent"].includes(role)) || (action !== "add" && !["admin", "agent", "client"].includes(role))) {
              event.preventDefault();
              showError(action === "add" ? "En creation, le role doit etre admin ou agent." : "Le role doit etre admin, agent ou client.");
              return;
            }

            if ((action === "add" || password.length > 0) && password.length < 6) {
              event.preventDefault();
              showError("Le mot de passe doit contenir au moins 6 caracteres.");
            }
          });
        }

        const deleteForms = document.querySelectorAll("[data-delete-form]");
        deleteForms.forEach(function (deleteForm) {
          deleteForm.addEventListener("submit", function (event) {
            if (!window.confirm("Voulez-vous vraiment supprimer cet utilisateur ?")) {
              event.preventDefault();
            }
          });
        });
      })();
    </script>
    <script src="assets/app.js"></script>
  </body>
</html>

