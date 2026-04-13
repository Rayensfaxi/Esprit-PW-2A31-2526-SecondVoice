<?php
declare(strict_types=1);

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
$emailValue = '';

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

    try {
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

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = (string) ($user['role'] ?? 'client');
        $_SESSION['user_nom'] = (string) ($user['nom'] ?? '');
        $_SESSION['user_prenom'] = (string) ($user['prenom'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');

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
      <a class="auth-brand" href="index.html">
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
          <a class="icon-btn user-close" href="index.html" aria-label="Retour a l'accueil">X</a>
        </div>

        <div class="auth-tabs">
          <a class="auth-tab is-active" href="login.php">Connexion</a>
          <a class="auth-tab" href="register.php">Inscription</a>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Connexion utilisateur</h3>
          <p class="auth-helper">Saisissez vos identifiants.</p>

          <form class="auth-form" id="login-form" action="login.php" method="post" novalidate>
            <input class="field" type="text" name="email" value="<?= h($emailValue) ?>" placeholder="Adresse e-mail" />
            <input class="field" type="password" name="password" placeholder="Mot de passe" />

            <p id="login-feedback" class="auth-feedback <?= $feedbackType === 'error' ? 'error' : '' ?>"><?= h($feedback) ?></p>
            <button class="btn btn-primary" type="submit">Se connecter</button>
          </form>

          <div class="user-panel-footer">
            <a class="btn btn-secondary" href="register.php">Creer un compte</a>
          </div>
        </section>
      </section>
    </main>

    <script>
      (function () {
        const form = document.getElementById("login-form");
        const feedback = document.getElementById("login-feedback");
        if (!form) return;

        form.addEventListener("submit", function (event) {
          const email = (form.email.value || "").trim();
          const password = form.password.value || "";
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

          if (!emailPattern.test(email)) {
            event.preventDefault();
            feedback.textContent = "Adresse e-mail invalide.";
            feedback.classList.add("error");
            return;
          }

          if (password.length < 6) {
            event.preventDefault();
            feedback.textContent = "Le mot de passe doit contenir au moins 6 caracteres.";
            feedback.classList.add("error");
            return;
          }

          feedback.textContent = "";
          feedback.classList.remove("error");
        });
      })();
    </script>
  </body>
</html>
