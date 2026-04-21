<?php
declare(strict_types=1);

session_start();

if (isset($_GET['action']) && $_GET['action'] === 'session-role') {
    header('Content-Type: application/json; charset=UTF-8');

    $role = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
    $isAuthenticated = isset($_SESSION['user_id']);
    $canAccessDashboard = $isAuthenticated && in_array($role, ['admin', 'agent'], true);

    $dashboardUrl = '';
    if ($canAccessDashboard) {
        $dashboardUrl = $role === 'agent'
            ? '../backoffice/gestion-accompagnements.php'
            : '../backoffice/index.php';
    }

    echo json_encode([
        'authenticated' => $isAuthenticated,
        'role' => $role,
        'canAccessDashboard' => $canAccessDashboard,
        'dashboardUrl' => $dashboardUrl
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../controller/UtilisateurController.php';
require_once __DIR__ . '/../../controller/ActivityLogger.php';

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$feedback = '';
$feedbackType = '';
$emailValue = '';
$loginMode = 'client';

if (isset($_GET['status']) && $_GET['status'] === 'logged_out') {
    $feedback = 'Vous etes deconnecte.';
}
if (isset($_GET['status']) && $_GET['status'] === 'auth_required') {
    $feedback = 'Connectez-vous pour acceder au dashboard.';
}
if (isset($_GET['status']) && $_GET['status'] === 'forbidden') {
    $feedback = 'Acces refuse: seul un admin ou un agent peut acceder au dashboard.';
    $feedbackType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $loginMode = (string) ($_POST['login_mode'] ?? 'client');
    $authKey = trim((string) ($_POST['auth_key'] ?? ''));

    try {
        if ($loginMode !== 'client' && $loginMode !== 'agent') {
            throw new InvalidArgumentException('Mode de connexion invalide.');
        }

        if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Adresse e-mail invalide.');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 6 caracteres.');
        }

        $controller = new UtilisateurController();
        $user = $controller->authenticateUser($emailValue, $password);

        if (!$user) {
            throw new RuntimeException('E-mail ou mot de passe incorrect.');
        }

        if ($loginMode === 'agent') {
            $agentKey = getenv('SECONDVOICE_AGENT_KEY') ?: 'SV-AGENT-2026';
            $role = strtolower((string) ($user['role'] ?? ''));

            if ($authKey === '') {
                throw new InvalidArgumentException("La cle d'authentification est obligatoire pour un agent.");
            }

            if (!hash_equals($agentKey, $authKey)) {
                throw new RuntimeException("Cle d'authentification invalide.");
            }

            if ($role !== 'agent' && $role !== 'admin') {
                throw new RuntimeException("Ce compte n'a pas les droits agent.");
            }
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = (string) ($user['role'] ?? 'client');
        $_SESSION['user_nom'] = (string) ($user['nom'] ?? '');
        $_SESSION['user_prenom'] = (string) ($user['prenom'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');

        ActivityLogger::log(
            (int) $user['id'],
            'Connexion',
            $loginMode === 'agent' ? 'Connexion agent au compte.' : "Acces a l'espace utilisateur."
        );

        header('Location: profile.php?status=logged_in');
        exit;
    } catch (Throwable $exception) {
        $feedback = $exception->getMessage();
        $feedbackType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion | SecondVoice</title>
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
    <link rel="stylesheet" href="assets/css/auth.css" />
  </head>
  <body class="auth-screen">
    <main class="auth-stage">
      <div class="auth-theme-row">
        <button class="icon-btn auth-theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
          <span class="theme-glyph" data-theme-glyph aria-hidden="true">☾</span>
        </button>
      </div>

      <a class="auth-brand" href="index.php">
        <img src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
      </a>

      <section class="user-panel">
        <div class="user-panel-head">
          <div class="user-panel-intro">
            <div class="user-avatar">SV</div>
            <div>
              <p class="user-panel-title">Connexion</p>
              <p class="user-modal-copy">Connectez-vous pour acceder a votre profil utilisateur.</p>
            </div>
          </div>
          <a class="icon-btn user-close" href="index.php" aria-label="Retour a l'accueil">X</a>
        </div>

        <div class="auth-tabs">
          <a class="auth-tab is-active" href="login.php">Connexion</a>
          <a class="auth-tab" href="register.php">Inscription</a>
        </div>

        <section class="auth-panel is-active">
          <h3 id="login-title" class="auth-title"><?= $loginMode === 'agent' ? 'Connexion agent' : 'Connexion utilisateur' ?></h3>
          <p id="login-helper" class="auth-helper">
            <?= $loginMode === 'agent' ? "Saisissez vos identifiants agent et la cle d'authentification." : 'Saisissez vos identifiants.' ?>
          </p>

          <form class="auth-form" id="login-form" action="login.php" method="post" novalidate>
            <input type="hidden" name="login_mode" id="login-mode" value="<?= h($loginMode) ?>" />
            <input class="field" type="text" name="email" value="<?= h($emailValue) ?>" placeholder="Adresse e-mail" />
            <p class="field-error" data-error-for="email"></p>
            <input class="field" type="password" name="password" placeholder="Mot de passe" />
            <p class="field-error" data-error-for="password"></p>
            <input
              class="field"
              type="text"
              name="auth_key"
              id="auth-key-field"
              placeholder="Cle d'authentification"
              style="<?= $loginMode === 'agent' ? '' : 'display:none;' ?>"
            />
            <p class="field-error" id="auth-key-error" data-error-for="auth_key" style="<?= $loginMode === 'agent' ? '' : 'display:none;' ?>"></p>

            <p id="login-feedback" class="auth-feedback <?= $feedbackType === 'error' ? 'error' : '' ?>"><?= h($feedback) ?></p>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <button class="btn btn-primary" id="login-client-btn" type="button">Se connecter en tant que client</button>
              <button class="btn btn-secondary" id="login-agent-btn" type="button">Se connecter en tant qu'agent</button>
            </div>
          </form>

          <div class="user-panel-footer">
            <a class="btn btn-secondary" href="register.php">Creer un compte</a>
          </div>
        </section>
      </section>
    </main>

    <script>
      (function () {
        const root = document.documentElement;
        const themeToggle = document.querySelector("[data-theme-toggle]");
        const themeGlyph = document.querySelector("[data-theme-glyph]");

        function applyTheme(theme) {
          root.dataset.theme = theme;
          if (themeToggle) {
            themeToggle.setAttribute("aria-label", theme === "light" ? "Activer le mode sombre" : "Activer le mode clair");
          }
          if (themeGlyph) {
            themeGlyph.textContent = theme === "light" ? "☀" : "☾";
          }
        }

        applyTheme(root.dataset.theme || "dark");
        if (themeToggle) {
          themeToggle.addEventListener("click", function () {
            const nextTheme = root.dataset.theme === "light" ? "dark" : "light";
            localStorage.setItem("theme", nextTheme);
            applyTheme(nextTheme);
          });
        }

        const form = document.getElementById("login-form");
        const feedback = document.getElementById("login-feedback");
        const clientBtn = document.getElementById("login-client-btn");
        const agentBtn = document.getElementById("login-agent-btn");
        const loginModeInput = document.getElementById("login-mode");
        const authKeyField = document.getElementById("auth-key-field");
        const authKeyError = document.getElementById("auth-key-error");
        const title = document.getElementById("login-title");
        const helper = document.getElementById("login-helper");
        const fieldErrors = {
          email: form.querySelector('[data-error-for="email"]'),
          password: form.querySelector('[data-error-for="password"]'),
          auth_key: form.querySelector('[data-error-for="auth_key"]')
        };
        if (!form) return;

        function clearFieldErrors() {
          Object.values(fieldErrors).forEach(function (node) {
            if (node) node.textContent = "";
          });
        }

        function setFieldError(fieldName, message) {
          const node = fieldErrors[fieldName];
          if (node) node.textContent = message;
        }

        function setAgentMode(active) {
          if (loginModeInput) loginModeInput.value = active ? "agent" : "client";
          if (authKeyField) authKeyField.style.display = active ? "" : "none";
          if (authKeyError) authKeyError.style.display = active ? "" : "none";
          if (!active) {
            setFieldError("auth_key", "");
          }
          if (title) title.textContent = active ? "Connexion agent" : "Connexion utilisateur";
          if (helper) {
            helper.textContent = active
              ? "Saisissez vos identifiants agent et la cle d'authentification."
              : "Saisissez vos identifiants.";
          }
        }

        function validateForm(agentMode) {
          clearFieldErrors();
          const email = (form.email.value || "").trim();
          const password = form.password.value || "";
          const authKey = authKeyField ? (authKeyField.value || "").trim() : "";
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

          let hasError = false;

          if (!emailPattern.test(email)) {
            setFieldError("email", "Adresse e-mail invalide.");
            hasError = true;
          }

          if (password.length < 6) {
            setFieldError("password", "Le mot de passe doit contenir au moins 6 caracteres.");
            hasError = true;
          }

          if (agentMode && !authKey) {
            setFieldError("auth_key", "La cle d'authentification est obligatoire.");
            hasError = true;
          }

          if (hasError) {
            return false;
          }

          feedback.textContent = "";
          feedback.classList.remove("error");
          return true;
        }

        setAgentMode(loginModeInput && loginModeInput.value === "agent");

        if (clientBtn) {
          clientBtn.addEventListener("click", function () {
            setAgentMode(false);
            if (validateForm(false)) {
              form.submit();
            }
          });
        }

        if (agentBtn) {
          agentBtn.addEventListener("click", function () {
            setAgentMode(true);
            if (validateForm(true)) {
              form.submit();
            }
          });
        }

        if (form.email) {
          form.email.addEventListener("input", function () {
            const email = (form.email.value || "").trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            setFieldError("email", email && emailPattern.test(email) ? "" : "Adresse e-mail invalide.");
          });
        }

        if (form.password) {
          form.password.addEventListener("input", function () {
            const password = form.password.value || "";
            setFieldError("password", password.length >= 6 ? "" : "Le mot de passe doit contenir au moins 6 caracteres.");
          });
        }

        if (authKeyField) {
          authKeyField.addEventListener("input", function () {
            const isAgent = loginModeInput && loginModeInput.value === "agent";
            const authKey = (authKeyField.value || "").trim();
            setFieldError("auth_key", isAgent && !authKey ? "La cle d'authentification est obligatoire." : "");
          });
        }
      })();
    </script>
  </body>
</html>
