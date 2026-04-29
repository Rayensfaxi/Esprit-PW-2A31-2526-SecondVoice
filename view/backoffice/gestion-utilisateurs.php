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
        'bloque' => 'risk',
        'en_pause' => 'review',
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

function validateUserInput(array $data, bool $isAdd): array
{
    $errors = [
        'nom' => '',
        'prenom' => '',
        'email' => '',
        'telephone' => '',
        'role' => '',
        'mot_de_passe' => '',
        'statut_compte' => ''
    ];

    $nom = trim((string) ($data['nom'] ?? ''));
    $prenom = trim((string) ($data['prenom'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $telephone = preg_replace('/\s+/', '', trim((string) ($data['telephone'] ?? ''))) ?? '';
    $role = strtolower(trim((string) ($data['role'] ?? '')));
    $status = strtolower(trim((string) ($data['statut_compte'] ?? '')));
    $password = (string) ($data['mot_de_passe'] ?? '');

    if (!preg_match("/^[\\p{L}\\s'\\-]{2,60}$/u", $nom)) {
        $errors['nom'] = 'Le nom doit contenir 2 a 60 caracteres alphabetiques.';
    }

    if (!preg_match("/^[\\p{L}\\s'\\-]{2,60}$/u", $prenom)) {
        $errors['prenom'] = 'Le prenom doit contenir 2 a 60 caracteres alphabetiques.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Adresse e-mail invalide.';
    } elseif (strlen($email) > 100) {
        $errors['email'] = 'Adresse e-mail trop longue (max 100 caracteres).';
    }

    if (!preg_match('/^\+?[0-9]{8,15}$/', $telephone)) {
        $errors['telephone'] = 'Le telephone doit contenir entre 8 et 15 chiffres.';
    }

    $allowedRoles = $isAdd ? ['admin', 'agent'] : ['admin', 'agent', 'client'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors['role'] = $isAdd
            ? 'En creation, le role doit etre admin ou agent.'
            : 'Le role doit etre admin, agent ou client.';
    }

    if (($isAdd || $password !== '') && strlen($password) < 6) {
        $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 6 caracteres.';
    }

    if (!in_array($status, ['actif', 'bloque', 'en_pause'], true)) {
        $errors['statut_compte'] = 'Le statut du compte doit etre Actif, Bloque ou En pause.';
    }

    return $errors;
}

$controller = new UtilisateurController();
$feedbackType = '';
$feedbackMessage = '';

$formValues = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'role' => 'agent',
    'statut_compte' => 'actif'
];
$fieldErrors = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'role' => '',
    'mot_de_passe' => '',
    'statut_compte' => ''
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
    'updated' => 'Utilisateur modifie avec succes.'
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
            'role' => (string) ($editUser['role'] ?? 'agent'),
            'statut_compte' => (string) ($editUser['statut_compte'] ?? 'actif')
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));
    $formValues['nom'] = trim((string) ($_POST['nom'] ?? $formValues['nom']));
    $formValues['prenom'] = trim((string) ($_POST['prenom'] ?? $formValues['prenom']));
    $formValues['email'] = trim((string) ($_POST['email'] ?? $formValues['email']));
    $formValues['telephone'] = trim((string) ($_POST['telephone'] ?? $formValues['telephone']));
    $formValues['role'] = trim((string) ($_POST['role'] ?? $formValues['role']));
    $formValues['statut_compte'] = trim((string) ($_POST['statut_compte'] ?? $formValues['statut_compte']));

    if ($action !== 'add' && $action !== 'update') {
        $feedbackType = 'error';
        $feedbackMessage = 'Action invalide.';
    } else {
        $validationErrors = validateUserInput($_POST, $action === 'add');
        $hasFieldErrors = false;
        foreach ($validationErrors as $key => $message) {
            $fieldErrors[$key] = $message;
            if ($message !== '') {
                $hasFieldErrors = true;
            }
        }

        if ($hasFieldErrors) {
            $feedbackType = 'error';
            $feedbackMessage = 'Veuillez corriger les erreurs sous les champs.';
        } else {
            try {
                if ($action === 'add') {
                    $controller->addUser(
                        $formValues['nom'],
                        $formValues['prenom'],
                        $formValues['email'],
                        (string) ($_POST['mot_de_passe'] ?? ''),
                        $formValues['telephone'],
                        $formValues['role'],
                        $formValues['statut_compte']
                    );
                    header('Location: gestion-utilisateurs.php?status=added');
                    exit;
                }

                $id = (int) ($_POST['id'] ?? 0);
                $password = trim((string) ($_POST['mot_de_passe'] ?? ''));
                $controller->updateUser(
                    $id,
                    $formValues['nom'],
                    $formValues['prenom'],
                    $formValues['email'],
                    $formValues['telephone'],
                    $formValues['role'],
                    $password !== '' ? $password : null,
                    $formValues['statut_compte']
                );
                header('Location: gestion-utilisateurs.php?status=updated');
                exit;
            } catch (Throwable $exception) {
                $feedbackType = 'error';
                $feedbackMessage = $exception->getMessage();
                $message = strtolower($feedbackMessage);

                if (str_contains($message, 'e-mail') || str_contains($message, 'email')) {
                    $fieldErrors['email'] = $feedbackMessage;
                } elseif (str_contains($message, 'telephone')) {
                    $fieldErrors['telephone'] = $feedbackMessage;
                } elseif (str_contains($message, 'prenom')) {
                    $fieldErrors['prenom'] = $feedbackMessage;
                } elseif (str_contains($message, 'nom')) {
                    $fieldErrors['nom'] = $feedbackMessage;
                } elseif (str_contains($message, 'role')) {
                    $fieldErrors['role'] = $feedbackMessage;
                } elseif (str_contains($message, 'statut')) {
                    $fieldErrors['statut_compte'] = $feedbackMessage;
                } elseif (str_contains($message, 'mot de passe')) {
                    $fieldErrors['mot_de_passe'] = $feedbackMessage;
                }
            }
        }
    }

    if (isset($_POST['id']) && ctype_digit((string) $_POST['id'])) {
        $editUser = $controller->getUserById((int) $_POST['id']);
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
    <style>
      .field-error-inline {
        margin: 6px 0 0;
        min-height: 18px;
        font-size: 0.84rem;
        color: #dc4b67;
      }
    </style>
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
            <h1 class="page-title">Gestion des utilisateurs</h1>
            
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
                <h2 class="section-title">Liste des utilisateurs</h2>
                <p class="helper">Filtrez, modifiez et gerez le statut des comptes en un seul endroit.</p>
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
                  <p class="field-error-inline" data-error-for="nom"><?= h($fieldErrors['nom']) ?></p>
                </div>
                <div class="filter-field">
                  <label for="prenom">Prenom</label>
                  <input id="prenom" name="prenom" type="text" value="<?= h($formValues['prenom']) ?>" placeholder="Prenom" />
                  <p class="field-error-inline" data-error-for="prenom"><?= h($fieldErrors['prenom']) ?></p>
                </div>
                <div class="filter-field">
                  <label for="email">E-mail</label>
                  <input id="email" name="email" type="text" value="<?= h($formValues['email']) ?>" placeholder="utilisateur@mail.com" />
                  <p class="field-error-inline" data-error-for="email"><?= h($fieldErrors['email']) ?></p>
                </div>
                <div class="filter-field">
                  <label for="telephone">Telephone</label>
                  <input id="telephone" name="telephone" type="text" value="<?= h($formValues['telephone']) ?>" placeholder="+21612345678" />
                  <p class="field-error-inline" data-error-for="telephone"><?= h($fieldErrors['telephone']) ?></p>
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
                  <p class="field-error-inline" data-error-for="role"><?= h($fieldErrors['role']) ?></p>
                </div>
                <div class="filter-field">
                  <label for="mot_de_passe">Mot de passe <?= $editUser ? '(laisser vide pour conserver)' : '' ?></label>
                  <input id="mot_de_passe" name="mot_de_passe" type="password" placeholder="Minimum 6 caracteres" />
                  <p class="field-error-inline" data-error-for="mot_de_passe"><?= h($fieldErrors['mot_de_passe']) ?></p>
                </div>
                <div class="filter-field">
                  <label for="statut_compte">Statut du compte</label>
                  <select id="statut_compte" name="statut_compte">
                    <option value="actif" <?= strtolower((string) $formValues['statut_compte']) === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="bloque" <?= strtolower((string) $formValues['statut_compte']) === 'bloque' ? 'selected' : '' ?>>Bloque</option>
                    <option value="en_pause" <?= strtolower((string) $formValues['statut_compte']) === 'en_pause' ? 'selected' : '' ?>>En pause</option>
                  </select>
                  <p class="field-error-inline" data-error-for="statut_compte"><?= h($fieldErrors['statut_compte']) ?></p>
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
                      <td>
                        <span class="status-pill <?= h(getStatusClass((string) ($user['statut_compte'] ?? 'actif'))) ?>">
                          <?= h(ucfirst(str_replace('_', ' ', (string) ($user['statut_compte'] ?? 'actif')))) ?>
                        </span>
                      </td>
                      <td><?= h(formatCreationDate((string) ($user['date_creation'] ?? ''))) ?></td>
                      <td>
                        <div class="table-actions">
                          <a class="ghost-button" href="gestion-utilisateurs.php?edit=<?= (int) $user['id'] ?>#user-form">Modifier</a>
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
        const fieldErrors = form
          ? {
              nom: form.querySelector('[data-error-for="nom"]'),
              prenom: form.querySelector('[data-error-for="prenom"]'),
              email: form.querySelector('[data-error-for="email"]'),
              telephone: form.querySelector('[data-error-for="telephone"]'),
              role: form.querySelector('[data-error-for="role"]'),
              mot_de_passe: form.querySelector('[data-error-for="mot_de_passe"]'),
              statut_compte: form.querySelector('[data-error-for="statut_compte"]')
            }
          : {};

        if (!form) return;

        function clearFeedback() {
          if (!feedback) return;
          feedback.textContent = "";
          feedback.classList.remove("error");
          feedback.classList.remove("success");
        }

        function clearFieldErrors() {
          Object.values(fieldErrors).forEach(function (node) {
            if (node) node.textContent = "";
          });
        }

        function setFieldError(fieldName, message) {
          const node = fieldErrors[fieldName];
          if (node) node.textContent = message;
        }

        function validateFields() {
          clearFieldErrors();
          const action = (form.querySelector('input[name="action"]')?.value || "").toLowerCase();
          const nom = (form.nom.value || "").trim();
          const prenom = (form.prenom.value || "").trim();
          const email = (form.email.value || "").trim();
          const telephone = (form.telephone.value || "").trim().replace(/\s+/g, "");
          const role = (form.role.value || "").trim().toLowerCase();
          const status = (form.statut_compte.value || "").trim().toLowerCase();
          const password = form.mot_de_passe.value || "";

          const namePattern = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]{2,60}$/;
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          const phonePattern = /^\+?[0-9]{8,15}$/;
          let hasError = false;

          if (!namePattern.test(nom)) {
            setFieldError("nom", "Le nom doit contenir 2 a 60 caracteres alphabetiques.");
            hasError = true;
          }

          if (!namePattern.test(prenom)) {
            setFieldError("prenom", "Le prenom doit contenir 2 a 60 caracteres alphabetiques.");
            hasError = true;
          }

          if (!emailPattern.test(email)) {
            setFieldError("email", "Adresse e-mail invalide.");
            hasError = true;
          } else if (email.length > 100) {
            setFieldError("email", "Adresse e-mail trop longue (max 100 caracteres).");
            hasError = true;
          }

          if (!phonePattern.test(telephone)) {
            setFieldError("telephone", "Le telephone doit contenir entre 8 et 15 chiffres.");
            hasError = true;
          }

          const validRoles = action === "add" ? ["admin", "agent"] : ["admin", "agent", "client"];
          if (!validRoles.includes(role)) {
            setFieldError(
              "role",
              action === "add"
                ? "En creation, le role doit etre admin ou agent."
                : "Le role doit etre admin, agent ou client."
            );
            hasError = true;
          }

          if ((action === "add" || password.length > 0) && password.length < 6) {
            setFieldError("mot_de_passe", "Le mot de passe doit contenir au moins 6 caracteres.");
            hasError = true;
          }

          if (!["actif", "bloque", "en_pause"].includes(status)) {
            setFieldError("statut_compte", "Le statut du compte doit etre Actif, Bloque ou En pause.");
            hasError = true;
          }

          return !hasError;
        }

        form.addEventListener("submit", function (event) {
          clearFeedback();
          if (!validateFields()) {
            event.preventDefault();
            if (feedback) {
              feedback.textContent = "Veuillez corriger les erreurs sous les champs.";
              feedback.classList.add("error");
              feedback.classList.remove("success");
            }
          }
        });

        form.nom.addEventListener("input", function () {
          const namePattern = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]{2,60}$/;
          setFieldError("nom", namePattern.test((form.nom.value || "").trim()) ? "" : "Le nom doit contenir 2 a 60 caracteres alphabetiques.");
        });
        form.prenom.addEventListener("input", function () {
          const namePattern = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]{2,60}$/;
          setFieldError("prenom", namePattern.test((form.prenom.value || "").trim()) ? "" : "Le prenom doit contenir 2 a 60 caracteres alphabetiques.");
        });
        form.email.addEventListener("input", function () {
          const email = (form.email.value || "").trim();
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailPattern.test(email)) {
            setFieldError("email", "Adresse e-mail invalide.");
          } else if (email.length > 100) {
            setFieldError("email", "Adresse e-mail trop longue (max 100 caracteres).");
          } else {
            setFieldError("email", "");
          }
        });
        form.telephone.addEventListener("input", function () {
          const phonePattern = /^\+?[0-9]{8,15}$/;
          const phone = (form.telephone.value || "").trim().replace(/\s+/g, "");
          setFieldError("telephone", phonePattern.test(phone) ? "" : "Le telephone doit contenir entre 8 et 15 chiffres.");
        });
        form.role.addEventListener("change", function () {
          setFieldError("role", "");
        });
        form.statut_compte.addEventListener("change", function () {
          setFieldError("statut_compte", "");
        });
        form.mot_de_passe.addEventListener("input", function () {
          const action = (form.querySelector('input[name="action"]')?.value || "").toLowerCase();
          const password = form.mot_de_passe.value || "";
          if ((action === "add" || password.length > 0) && password.length < 6) {
            setFieldError("mot_de_passe", "Le mot de passe doit contenir au moins 6 caracteres.");
          } else {
            setFieldError("mot_de_passe", "");
          }
        });
      })();
    </script>
    <script src="assets/app.js"></script>
  </body>
</html>
