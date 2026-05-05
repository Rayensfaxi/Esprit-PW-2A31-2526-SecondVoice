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

if (isset($_SESSION['pending_admin_user']) && is_array($_SESSION['pending_admin_user'])) {
    header('Location: admin-face-verify.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ensureImageCaptchaCode(): void
{
    $current = (string) ($_SESSION['client_login_image_captcha_code'] ?? '');
    if ($current !== '' && strlen($current) === 5) {
        return;
    }

    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    $maxIndex = strlen($alphabet) - 1;
    for ($i = 0; $i < 5; $i++) {
        $code .= $alphabet[random_int(0, $maxIndex)];
    }

    $_SESSION['client_login_image_captcha_code'] = $code;
}

function normalizeAgentKeyValue(string $raw): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if ($value === null) {
        $value = $raw;
    }

    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            return $line;
        }
    }

    return '';
}

function readAgentServerKey(): string
{
    $pathFromEnv = trim((string) (getenv('SECONDVOICE_AGENT_KEY_FILE') ?: ''));
    $candidatePaths = [];

    if ($pathFromEnv !== '') {
        $candidatePaths[] = $pathFromEnv;
    }
    $candidatePaths[] = __DIR__ . '/../../Agent-key.txt';

    foreach ($candidatePaths as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            continue;
        }

        $key = normalizeAgentKeyValue($raw);
        if ($key !== '') {
            return $key;
        }
    }

    throw new RuntimeException("Fichier de cle agent introuvable ou vide.");
}

function readUploadedAgentKey(): string
{
    if (!isset($_FILES['auth_key_file']) || !is_array($_FILES['auth_key_file'])) {
        throw new InvalidArgumentException("Le fichier de cle agent est obligatoire.");
    }

    $upload = $_FILES['auth_key_file'];
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException("Le fichier de cle agent est obligatoire.");
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Echec de lecture du fichier de cle agent.");
    }

    $tmpName = (string) ($upload['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException("Fichier de cle agent invalide.");
    }

    $maxBytes = 8192;
    $size = (int) ($upload['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new InvalidArgumentException("Le fichier de cle agent est invalide.");
    }

    $content = file_get_contents($tmpName);
    if ($content === false) {
        throw new RuntimeException("Impossible de lire le fichier de cle agent.");
    }

    $key = normalizeAgentKeyValue($content);
    if ($key === '') {
        throw new InvalidArgumentException("Le fichier de cle agent est vide.");
    }

    return $key;
}

$feedback = '';
$feedbackType = '';
$emailValue = '';
$captchaInput = '';
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
if (isset($_GET['status']) && $_GET['status'] === 'verify_email_sent') {
    $feedback = 'Compte cree. Verifiez votre e-mail puis cliquez sur le lien de confirmation.';
}
if (isset($_GET['status']) && $_GET['status'] === 'verify_email_sent_log') {
    $feedback = "Compte cree. L'e-mail n'a pas pu etre envoye automatiquement. Verifiez la config e-mail et storage/mail/outbox.log.";
}
if (isset($_GET['status']) && $_GET['status'] === 'email_verified') {
    $feedback = 'E-mail verifie avec succes. Vous pouvez maintenant vous connecter.';
}
if (isset($_GET['status']) && $_GET['status'] === 'email_verify_failed') {
    $feedback = 'Lien de verification invalide ou expire.';
    $feedbackType = 'error';
}
if (isset($_GET['status']) && $_GET['status'] === 'reset_link_sent') {
    $feedback = 'Si cet e-mail existe, un lien de reinitialisation a ete envoye.';
}
if (isset($_GET['status']) && $_GET['status'] === 'reset_link_sent_log') {
    $feedback = "Si ce compte est eligible, un lien de reinitialisation est envoye. Sinon, verifiez la config e-mail.";
}
if (isset($_GET['status']) && $_GET['status'] === 'password_reset_done') {
    $feedback = 'Mot de passe mis a jour. Connectez-vous avec le nouveau mot de passe.';
}
if (isset($_GET['status']) && $_GET['status'] === 'reset_invalid') {
    $feedback = 'Lien de reinitialisation invalide ou expire.';
    $feedbackType = 'error';
}

ensureImageCaptchaCode();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $loginMode = (string) ($_POST['login_mode'] ?? 'client');
    $captchaInput = strtoupper(trim((string) ($_POST['captcha_code'] ?? '')));

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

        if ($loginMode === 'client') {
            ensureImageCaptchaCode();
            $expectedCaptcha = strtoupper(trim((string) ($_SESSION['client_login_image_captcha_code'] ?? '')));
            if ($captchaInput === '') {
                throw new InvalidArgumentException("Veuillez saisir le texte de l'image captcha.");
            }
            if ($expectedCaptcha === '' || !hash_equals($expectedCaptcha, $captchaInput)) {
                unset($_SESSION['client_login_image_captcha_code']);
                ensureImageCaptchaCode();
                throw new InvalidArgumentException('Captcha image invalide. Merci de reessayer.');
            }

            // Evite la reutilisation d'un captcha deja valide.
            unset($_SESSION['client_login_image_captcha_code']);
        }

        $controller = new UtilisateurController();
        $user = $controller->authenticateUser($emailValue, $password);

        if (!$user) {
            throw new RuntimeException('E-mail ou mot de passe incorrect.');
        }

        if ($loginMode === 'agent') {
            $agentKey = readAgentServerKey();
            $uploadedAgentKey = readUploadedAgentKey();
            $role = strtolower((string) ($user['role'] ?? ''));

            if (!hash_equals($agentKey, $uploadedAgentKey)) {
                throw new RuntimeException("Fichier de cle agent invalide.");
            }

            if ($role !== 'agent' && $role !== 'admin') {
                throw new RuntimeException("Ce compte n'a pas les droits agent.");
            }
        }

        $role = strtolower((string) ($user['role'] ?? 'client'));
        if ($role === 'admin') {
            $_SESSION['pending_admin_user'] = [
                'id' => (int) ($user['id'] ?? 0),
                'role' => (string) ($user['role'] ?? 'admin'),
                'nom' => (string) ($user['nom'] ?? ''),
                'prenom' => (string) ($user['prenom'] ?? ''),
                'email' => (string) ($user['email'] ?? '')
            ];
            header('Location: admin-face-verify.php');
            exit;
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
        if ($loginMode === 'client') {
            ensureImageCaptchaCode();
        }
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
            <?= $loginMode === 'agent' ? "Saisissez vos identifiants agent et importez le fichier de cle." : 'Saisissez vos identifiants.' ?>
          </p>

          <form class="auth-form" id="login-form" action="login.php" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="login_mode" id="login-mode" value="<?= h($loginMode) ?>" />
            <input class="field" type="text" name="email" value="<?= h($emailValue) ?>" placeholder="Adresse e-mail" />
            <p class="field-error" data-error-for="email"></p>
            <input class="field" type="password" name="password" placeholder="Mot de passe" />
            <p class="field-error" data-error-for="password"></p>
            <div id="captcha-container" style="<?= $loginMode === 'agent' ? 'display:none;' : '' ?>">
              <img
                id="captcha-image"
                src="captcha-image.php?t=<?= time() ?>"
                alt="Captcha image"
                style="width:100%; max-width:280px; height:auto; border-radius:10px; border:1px solid rgba(128,128,128,0.3);"
              />
              <button class="btn btn-secondary" type="button" id="captcha-refresh-btn" style="margin-top:10px;">
                Actualiser l'image
              </button>
              <input
                class="field"
                type="text"
                name="captcha_code"
                id="captcha-code-field"
                value="<?= h($captchaInput) ?>"
                placeholder="Saisissez le texte de l'image"
                autocomplete="off"
              />
            </div>
            <p class="field-error" id="captcha-error" data-error-for="captcha" style="<?= $loginMode === 'agent' ? 'display:none;' : '' ?>"></p>
            <input
              class="field"
              type="file"
              name="auth_key_file"
              id="auth-key-file-field"
              accept=".txt,.key,text/plain"
              style="<?= $loginMode === 'agent' ? '' : 'display:none;' ?>"
            />
            <p class="field-error" id="auth-key-error" data-error-for="auth_key_file" style="<?= $loginMode === 'agent' ? '' : 'display:none;' ?>"></p>

            <p id="login-feedback" class="auth-feedback <?= $feedbackType === 'error' ? 'error' : '' ?>"><?= h($feedback) ?></p>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <button class="btn btn-primary" id="login-client-btn" type="button">Se connecter en tant que client</button>
              <button class="btn btn-secondary" id="login-agent-btn" type="button">Se connecter en tant qu'agent</button>
            </div>
          </form>

          <div class="user-panel-footer">
            <a class="btn btn-secondary" href="register.php">Creer un compte</a>
          </div>
          <div class="auth-links" style="margin-top: 10px; text-align: center;">
            <a href="forgot-password.php">Mot de passe oublie ?</a>
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
        if (!form) return;

        const feedback = document.getElementById("login-feedback");
        const clientBtn = document.getElementById("login-client-btn");
        const agentBtn = document.getElementById("login-agent-btn");
        const loginModeInput = document.getElementById("login-mode");
        const authKeyFileField = document.getElementById("auth-key-file-field");
        const authKeyError = document.getElementById("auth-key-error");
        const captchaContainer = document.getElementById("captcha-container");
        const captchaError = document.getElementById("captcha-error");
        const captchaCodeField = document.getElementById("captcha-code-field");
        const captchaImage = document.getElementById("captcha-image");
        const captchaRefreshBtn = document.getElementById("captcha-refresh-btn");
        const title = document.getElementById("login-title");
        const helper = document.getElementById("login-helper");
        const fieldErrors = {
          email: form.querySelector('[data-error-for="email"]'),
          password: form.querySelector('[data-error-for="password"]'),
          auth_key_file: form.querySelector('[data-error-for="auth_key_file"]'),
          captcha: form.querySelector('[data-error-for="captcha"]')
        };

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
          if (authKeyFileField) authKeyFileField.style.display = active ? "" : "none";
          if (authKeyError) authKeyError.style.display = active ? "" : "none";
          if (captchaContainer) captchaContainer.style.display = active ? "none" : "";
          if (captchaError) captchaError.style.display = active ? "none" : "";
          if (!active) {
            setFieldError("auth_key_file", "");
          } else {
            setFieldError("captcha", "");
          }
          if (title) title.textContent = active ? "Connexion agent" : "Connexion utilisateur";
          if (helper) {
            helper.textContent = active
              ? "Saisissez vos identifiants agent et importez le fichier de cle."
              : "Saisissez vos identifiants.";
          }
          if (clientBtn) {
            clientBtn.textContent = active ? "Passer au mode client" : "Se connecter en tant que client";
            clientBtn.classList.toggle("btn-primary", !active);
            clientBtn.classList.toggle("btn-secondary", active);
          }
          if (agentBtn) {
            agentBtn.textContent = active ? "Se connecter en tant qu'agent" : "Passer au mode agent";
            agentBtn.classList.toggle("btn-primary", active);
            agentBtn.classList.toggle("btn-secondary", !active);
          }
        }

        function validateForm(agentMode) {
          clearFieldErrors();
          const email = (form.email.value || "").trim();
          const password = form.password.value || "";
          const hasAuthKeyFile = authKeyFileField && authKeyFileField.files && authKeyFileField.files.length > 0;
          const captchaCode = captchaCodeField ? (captchaCodeField.value || "").trim() : "";
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

          if (agentMode && !hasAuthKeyFile) {
            setFieldError("auth_key_file", "Le fichier de cle agent est obligatoire.");
            hasError = true;
          }

          if (!agentMode && !captchaCode) {
            setFieldError("captcha", "Veuillez saisir le texte de l'image captcha.");
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
            const isAgent = loginModeInput && loginModeInput.value === "agent";
            if (isAgent) {
              setAgentMode(false);
              return;
            }

            if (validateForm(false)) {
              form.submit();
            }
          });
        }

        if (agentBtn) {
          agentBtn.addEventListener("click", function () {
            const isAgent = loginModeInput && loginModeInput.value === "agent";
            if (!isAgent) {
              setAgentMode(true);
              return;
            }

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

        if (authKeyFileField) {
          authKeyFileField.addEventListener("change", function () {
            const isAgent = loginModeInput && loginModeInput.value === "agent";
            const hasFile = authKeyFileField.files && authKeyFileField.files.length > 0;
            setFieldError("auth_key_file", isAgent && !hasFile ? "Le fichier de cle agent est obligatoire." : "");
          });
        }

        if (captchaCodeField) {
          captchaCodeField.addEventListener("input", function () {
            const isClient = !loginModeInput || loginModeInput.value === "client";
            const value = (captchaCodeField.value || "").trim();
            setFieldError("captcha", isClient && !value ? "Veuillez saisir le texte de l'image captcha." : "");
          });
        }

        if (captchaRefreshBtn && captchaImage) {
          captchaRefreshBtn.addEventListener("click", function () {
            captchaImage.src = "captcha-image.php?refresh=1&t=" + Date.now();
            if (captchaCodeField) captchaCodeField.value = "";
            setFieldError("captcha", "");
          });
        }
      })();
    </script>
  </body>
</html>
