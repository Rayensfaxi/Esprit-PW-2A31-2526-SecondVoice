<?php

session_start();
require_once __DIR__ . '/../../controller/UtilisateurController.php';

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
$values = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['nom'] = trim((string) ($_POST['nom'] ?? ''));
    $values['prenom'] = trim((string) ($_POST['prenom'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $values['telephone'] = trim((string) ($_POST['telephone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {
        $controller = new UtilisateurController();
        $newId = $controller->addUser(
            $values['nom'],
            $values['prenom'],
            $values['email'],
            $password,
            $values['telephone'],
            'client'
        );

        $user = $controller->getUserById($newId);
        if (!$user) {
            throw new RuntimeException('Impossible de recuperer le compte cree.');
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = (string) ($user['role'] ?? 'client');
        $_SESSION['user_nom'] = (string) ($user['nom'] ?? '');
        $_SESSION['user_prenom'] = (string) ($user['prenom'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');

        header('Location: profile.php?status=registered');
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
    <title>Inscription | SecondVoice</title>
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
              <p class="user-panel-title">Creation de compte</p>
              <p class="user-modal-copy">Inscrivez-vous pour acceder a votre espace utilisateur.</p>
            </div>
          </div>
          <a class="icon-btn user-close" href="index.php" aria-label="Retour a l'accueil">X</a>
        </div>

        <div class="auth-tabs">
          <a class="auth-tab" href="login.php">Connexion</a>
          <a class="auth-tab is-active" href="register.php">Inscription</a>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Creer un compte</h3>
          <p class="auth-helper">Tous les champs ci-dessous sont obligatoires.</p>

          <form class="auth-form" id="register-form" action="register.php" method="post" novalidate>
            <input class="field" type="text" name="nom" value="<?= h($values['nom']) ?>" placeholder="Nom" />
            <p class="field-error" data-error-for="nom"></p>
            <input class="field" type="text" name="prenom" value="<?= h($values['prenom']) ?>" placeholder="Prenom" />
            <p class="field-error" data-error-for="prenom"></p>
            <input class="field" type="text" name="email" value="<?= h($values['email']) ?>" placeholder="E-mail" />
            <p class="field-error" data-error-for="email"></p>
            <input class="field" type="text" name="telephone" value="<?= h($values['telephone']) ?>" placeholder="Telephone (+216...)" />
            <p class="field-error" data-error-for="telephone"></p>
            <input class="field" type="password" name="password" placeholder="Mot de passe" />
            <p class="field-error" data-error-for="password"></p>

            <p id="register-feedback" class="auth-feedback <?= $feedbackType === 'error' ? 'error' : '' ?>"><?= h($feedback) ?></p>
            <button class="btn btn-primary" type="submit">Creer un compte</button>
          </form>
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

        const form = document.getElementById("register-form");
        const feedback = document.getElementById("register-feedback");
        const fieldErrors = {
          nom: form ? form.querySelector('[data-error-for="nom"]') : null,
          prenom: form ? form.querySelector('[data-error-for="prenom"]') : null,
          email: form ? form.querySelector('[data-error-for="email"]') : null,
          telephone: form ? form.querySelector('[data-error-for="telephone"]') : null,
          password: form ? form.querySelector('[data-error-for="password"]') : null
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

        form.addEventListener("submit", function (event) {
          clearFieldErrors();
          const nom = (form.nom.value || "").trim();
          const prenom = (form.prenom.value || "").trim();
          const email = (form.email.value || "").trim();
          const telephone = (form.telephone.value || "").trim().replace(/\s+/g, "");
          const password = form.password.value || "";

          const namePattern = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]{2,60}$/;
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          const phonePattern = /^\+?[0-9]{8,15}$/;

          if (!namePattern.test(nom)) {
            event.preventDefault();
            setFieldError("nom", "Nom invalide (2 a 60 caracteres).");
            return;
          }

          if (!namePattern.test(prenom)) {
            event.preventDefault();
            setFieldError("prenom", "Prenom invalide (2 a 60 caracteres).");
            return;
          }

          if (!emailPattern.test(email)) {
            event.preventDefault();
            setFieldError("email", "Adresse e-mail invalide.");
            return;
          }

          if (!phonePattern.test(telephone)) {
            event.preventDefault();
            setFieldError("telephone", "Telephone invalide (8 a 15 chiffres).");
            return;
          }

          if (password.length < 6) {
            event.preventDefault();
            setFieldError("password", "Mot de passe: minimum 6 caracteres.");
            return;
          }

          feedback.textContent = "";
          feedback.classList.remove("error");
        });
      })();
    </script>
  </body>
</html>

