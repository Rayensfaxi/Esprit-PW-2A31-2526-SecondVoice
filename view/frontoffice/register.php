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
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h((string) @filemtime(__DIR__ . '/assets/css/style.css')) ?>" />
    <link rel="stylesheet" href="assets/css/auth.css?v=<?= h((string) @filemtime(__DIR__ . '/assets/css/auth.css')) ?>" />
  </head>
  <body class="auth-screen">
    <main class="auth-stage">
      <div class="auth-theme-row">
        <button class="icon-btn auth-theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
          <span class="theme-glyph theme-icon-moon" data-theme-glyph aria-hidden="true"></span>
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

          <div class="identity-ocr-box">
            <label class="identity-ocr-label" for="identity-ocr-file">Scanner carte d'identite ou passeport</label>
            <div class="identity-ocr-row">
              <input
                class="field"
                id="identity-ocr-file"
                name="identity_image"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              />
              <button class="btn btn-secondary identity-ocr-btn" id="identity-ocr-btn" type="button">Extraire</button>
            </div>
            <p id="identity-ocr-feedback" class="auth-feedback"></p>
          </div>

          <form class="auth-form" id="register-form" action="register.php" method="post" novalidate>
            <input class="field" type="text" name="nom" value="<?= h($values['nom']) ?>" placeholder="Nom" />
            <p class="field-error" data-error-for="nom"><?= h($fieldErrors['nom']) ?></p>
            <input class="field" type="text" name="prenom" value="<?= h($values['prenom']) ?>" placeholder="Prenom" />
            <p class="field-error" data-error-for="prenom"><?= h($fieldErrors['prenom']) ?></p>
            <input class="field" type="text" name="email" value="<?= h($values['email']) ?>" placeholder="E-mail" />
            <p class="field-error" data-error-for="email"><?= h($fieldErrors['email']) ?></p>
            <input class="field" type="text" name="telephone" value="<?= h($values['telephone']) ?>" placeholder="Telephone (+216...)" />
            <p class="field-error" data-error-for="telephone"><?= h($fieldErrors['telephone']) ?></p>
            <div class="password-suggestion-row">
              <input class="field" id="register-password" type="password" name="password" placeholder="Mot de passe" autocomplete="new-password" />
              <button class="btn btn-secondary password-suggestion-btn" id="password-suggest-btn" type="button">Proposer</button>
            </div>
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
            themeGlyph.classList.toggle("theme-icon-moon", theme === "light");
            themeGlyph.classList.toggle("theme-icon-sun", theme !== "light");
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
        const suggestPasswordBtn = document.getElementById("password-suggest-btn");
        const passwordField = document.getElementById("register-password");
        const identityOcrFile = document.getElementById("identity-ocr-file");
        const identityOcrBtn = document.getElementById("identity-ocr-btn");
        const identityOcrFeedback = document.getElementById("identity-ocr-feedback");
        const fieldErrors = {
          nom: form ? form.querySelector('[data-error-for="nom"]') : null,
          prenom: form ? form.querySelector('[data-error-for="prenom"]') : null,
          email: form ? form.querySelector('[data-error-for="email"]') : null,
          telephone: form ? form.querySelector('[data-error-for="telephone"]') : null,
          password: form ? form.querySelector('[data-error-for="password"]') : null
        };
        if (!form) return;

        function setFeedback(message, isError) {
          feedback.textContent = message;
          feedback.classList.toggle("error", Boolean(isError));
        }

        function setOcrFeedback(message, isError) {
          if (!identityOcrFeedback) return;
          identityOcrFeedback.textContent = message;
          identityOcrFeedback.classList.toggle("error", Boolean(isError));
        }

        function readFileAsDataUrl(file) {
          return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () {
              if (typeof reader.result === "string") {
                resolve(reader.result);
              } else {
                reject(new Error("Image OCR invalide."));
              }
            };
            reader.onerror = function () {
              reject(new Error("Impossible de lire l'image."));
            };
            reader.readAsDataURL(file);
          });
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
              setFeedback("Veuillez corriger les erreurs de saisie.", true);
            } else {
              setFeedback("", false);
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

        if (suggestPasswordBtn && passwordField) {
          suggestPasswordBtn.addEventListener("click", async function () {
            const originalText = suggestPasswordBtn.textContent;
            suggestPasswordBtn.disabled = true;
            suggestPasswordBtn.textContent = "Generation...";

            try {
              const response = await fetch("password-suggestion-api.php", {
                method: "GET",
                headers: { Accept: "application/json" },
                cache: "no-store"
              });
              const data = await response.json();
              if (!response.ok || !data || data.ok !== true || typeof data.password !== "string") {
                throw new Error(data && data.error ? data.error : "Generation impossible.");
              }

              passwordField.type = "text";
              passwordField.value = data.password;
              setFieldError("password", "");
              setFeedback("Mot de passe propose applique.", false);
              passwordField.focus();
              passwordField.select();
            } catch (error) {
              setFeedback("Impossible de generer un mot de passe pour le moment.", true);
            } finally {
              suggestPasswordBtn.disabled = false;
              suggestPasswordBtn.textContent = originalText;
            }
          });
        }

        if (identityOcrBtn && identityOcrFile) {
          identityOcrFile.addEventListener("change", function () {
            const file = identityOcrFile.files && identityOcrFile.files[0] ? identityOcrFile.files[0] : null;
            const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
            const allowedExtensions = /\.(jpe?g|png|webp)$/i;
            if (!file) {
              setOcrFeedback("", false);
              return;
            }
            if (file.size > 4 * 1024 * 1024) {
              setOcrFeedback("Image trop volumineuse (max 4 Mo).", true);
              return;
            }
            if (!allowedTypes.includes(file.type) && !allowedExtensions.test(file.name || "")) {
              setOcrFeedback("Format refuse. Utilisez JPG, PNG ou WEBP.", true);
              return;
            }
            setOcrFeedback("Fichier pret: " + file.name + ". Cliquez sur Extraire.", false);
          });

          identityOcrBtn.addEventListener("click", async function () {
            const file = identityOcrFile.files && identityOcrFile.files[0] ? identityOcrFile.files[0] : null;
            const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
            const allowedExtensions = /\.(jpe?g|png|webp)$/i;
            if (!file) {
              setOcrFeedback("Ajoutez une image JPG, PNG ou WEBP.", true);
              return;
            }
            if (file.size > 4 * 1024 * 1024) {
              setOcrFeedback("Image trop volumineuse (max 4 Mo).", true);
              return;
            }
            if (!allowedTypes.includes(file.type) && !allowedExtensions.test(file.name || "")) {
              setOcrFeedback("Format refuse. Utilisez JPG, PNG ou WEBP.", true);
              return;
            }

            const originalText = identityOcrBtn.textContent;
            identityOcrBtn.disabled = true;
            identityOcrBtn.textContent = "Analyse...";
            setOcrFeedback("Extraction OCR en cours...", false);

            try {
              const imageData = await readFileAsDataUrl(file);
              const response = await fetch("identity-ocr-api.php", {
                method: "POST",
                headers: {
                  Accept: "application/json",
                  "Content-Type": "application/json"
                },
                body: JSON.stringify({
                  filename: file.name || "identity",
                  image_data: imageData
                })
              });
              const data = await response.json();
              if (!response.ok || !data || data.ok !== true) {
                throw new Error(data && data.error ? data.error : "OCR echoue.");
              }

              form.nom.value = data.nom || "";
              form.prenom.value = data.prenom || "";
              setFieldError("nom", validateNom());
              setFieldError("prenom", validatePrenom());
              setOcrFeedback("Nom et prenom extraits. Verifiez les champs avant de creer le compte.", false);
            } catch (error) {
              setOcrFeedback(error && error.message ? error.message : "OCR echoue. Reessayez avec une image plus nette.", true);
            } finally {
              identityOcrBtn.disabled = false;
              identityOcrBtn.textContent = originalText;
            }
          });
        }
      })();
    </script>
  </body>
</html>

