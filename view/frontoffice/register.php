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

function frontofficeBaseUrl(): string
{
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(dirname($script), '/');
    return $scheme . '://' . $host . $dir;
}

$feedback = '';
$feedbackType = '';
$values = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => ''
];
$fieldErrors = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'password' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['nom'] = trim((string) ($_POST['nom'] ?? ''));
    $values['prenom'] = trim((string) ($_POST['prenom'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $values['telephone'] = trim((string) ($_POST['telephone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $normalizedPhone = preg_replace('/\s+/', '', $values['telephone']) ?? '';

    if ($values['nom'] === '') {
        $fieldErrors['nom'] = 'Le nom est obligatoire.';
    } elseif (!preg_match("/^[\\p{L}\\s'\\-]{2,60}$/u", $values['nom'])) {
        $fieldErrors['nom'] = 'Nom invalide (2 a 60 caracteres lettres).';
    }

    if ($values['prenom'] === '') {
        $fieldErrors['prenom'] = 'Le prenom est obligatoire.';
    } elseif (!preg_match("/^[\\p{L}\\s'\\-]{2,60}$/u", $values['prenom'])) {
        $fieldErrors['prenom'] = 'Prenom invalide (2 a 60 caracteres lettres).';
    }

    if ($values['email'] === '') {
        $fieldErrors['email'] = "L'e-mail est obligatoire.";
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Adresse e-mail invalide.';
    } elseif (strlen($values['email']) > 100) {
        $fieldErrors['email'] = 'Adresse e-mail trop longue (max 100 caracteres).';
    }

    if ($normalizedPhone === '') {
        $fieldErrors['telephone'] = 'Le telephone est obligatoire.';
    } elseif (!preg_match('/^\+?[0-9]{8,15}$/', $normalizedPhone)) {
        $fieldErrors['telephone'] = 'Telephone invalide (8 a 15 chiffres).';
    }

    if ($password === '') {
        $fieldErrors['password'] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $fieldErrors['password'] = 'Mot de passe: minimum 6 caracteres.';
    } elseif (strlen($password) > 255) {
        $fieldErrors['password'] = 'Mot de passe trop long.';
    }

    $hasFieldErrors = false;
    foreach ($fieldErrors as $message) {
        if ($message !== '') {
            $hasFieldErrors = true;
            break;
        }
    }

    if ($hasFieldErrors) {
        $feedback = 'Veuillez corriger les erreurs de saisie.';
        $feedbackType = 'error';
    } else {
        try {
            $controller = new UtilisateurController();
            $newId = $controller->addUser(
                $values['nom'],
                $values['prenom'],
                $values['email'],
                $password,
                $normalizedPhone,
                'client'
            );

            $user = $controller->getUserById($newId);
            if (!$user) {
                throw new RuntimeException('Impossible de recuperer le compte cree.');
            }

            $mailSent = $controller->sendEmailVerification(
                (int) $newId,
                $values['email'],
                trim($values['prenom'] . ' ' . $values['nom']),
                frontofficeBaseUrl() . '/verify-email.php'
            );

            header('Location: login.php?status=' . ($mailSent ? 'verify_email_sent' : 'verify_email_sent_log'));
            exit;
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            if (stripos($message, 'e-mail') !== false || stripos($message, 'adresse') !== false || stripos($message, 'domaine') !== false) {
                $fieldErrors['email'] = $message;
                $feedback = 'Veuillez corriger les erreurs de saisie.';
            } else {
                $feedback = $message;
            }
            $feedbackType = 'error';
        }
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
            <p class="field-error" data-error-for="nom"><?= h($fieldErrors['nom']) ?></p>
            <input class="field" type="text" name="prenom" value="<?= h($values['prenom']) ?>" placeholder="Prenom" />
            <p class="field-error" data-error-for="prenom"><?= h($fieldErrors['prenom']) ?></p>
            <input class="field" type="text" name="email" value="<?= h($values['email']) ?>" placeholder="E-mail" />
            <p class="field-error" data-error-for="email"><?= h($fieldErrors['email']) ?></p>
            <input class="field" type="text" name="telephone" value="<?= h($values['telephone']) ?>" placeholder="Telephone (+216...)" />
            <p class="field-error" data-error-for="telephone"><?= h($fieldErrors['telephone']) ?></p>
            <input class="field" type="password" name="password" placeholder="Mot de passe" />
            <p class="field-error" data-error-for="password"><?= h($fieldErrors['password']) ?></p>

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

        function validateNom() {
          const nom = (form.nom.value || "").trim();
          const namePattern = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]{2,60}$/;
          if (!nom) return "Le nom est obligatoire.";
          if (!namePattern.test(nom)) return "Nom invalide (2 a 60 caracteres lettres).";
          return "";
        }

        function validatePrenom() {
          const prenom = (form.prenom.value || "").trim();
          const namePattern = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]{2,60}$/;
          if (!prenom) return "Le prenom est obligatoire.";
          if (!namePattern.test(prenom)) return "Prenom invalide (2 a 60 caracteres lettres).";
          return "";
        }

        function validateEmail() {
          const email = (form.email.value || "").trim();
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!email) return "L'e-mail est obligatoire.";
          if (!emailPattern.test(email)) return "Adresse e-mail invalide.";
          if (email.length > 100) return "Adresse e-mail trop longue (max 100 caracteres).";
          return "";
        }

        function validateTelephone() {
          const telephone = (form.telephone.value || "").trim().replace(/\s+/g, "");
          const phonePattern = /^\+?[0-9]{8,15}$/;
          if (!telephone) return "Le telephone est obligatoire.";
          if (!phonePattern.test(telephone)) return "Telephone invalide (8 a 15 chiffres).";
          return "";
        }

        function validatePassword() {
          const password = form.password.value || "";
          if (!password) return "Le mot de passe est obligatoire.";
          if (password.length < 6) return "Mot de passe: minimum 6 caracteres.";
          if (password.length > 255) return "Mot de passe trop long.";
          return "";
        }

        function runValidation(showFeedback) {
          clearFieldErrors();
          let hasError = false;
          const checks = {
            nom: validateNom(),
            prenom: validatePrenom(),
            email: validateEmail(),
            telephone: validateTelephone(),
            password: validatePassword()
          };

          Object.keys(checks).forEach(function (key) {
            if (checks[key]) {
              hasError = true;
              setFieldError(key, checks[key]);
            }
          });

          if (showFeedback) {
            if (hasError) {
              feedback.textContent = "Veuillez corriger les erreurs de saisie.";
              feedback.classList.add("error");
            } else {
              feedback.textContent = "";
              feedback.classList.remove("error");
            }
          }

          return !hasError;
        }

        form.addEventListener("submit", function (event) {
          if (!runValidation(true)) {
            event.preventDefault();
          }
        });

        form.nom.addEventListener("input", function () {
          setFieldError("nom", validateNom());
        });
        form.prenom.addEventListener("input", function () {
          setFieldError("prenom", validatePrenom());
        });
        form.email.addEventListener("input", function () {
          setFieldError("email", validateEmail());
        });
        form.telephone.addEventListener("input", function () {
          setFieldError("telephone", validateTelephone());
        });
        form.password.addEventListener("input", function () {
          setFieldError("password", validatePassword());
        });
      })();
    </script>
  </body>
</html>

