<?php

session_start();
require_once __DIR__ . '/../../controller/UtilisateurController.php';

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

$email = '';
$fieldError = '';
$feedback = '';
$feedbackType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldError = 'Adresse e-mail invalide.';
        $feedback = 'Veuillez corriger le champ e-mail.';
        $feedbackType = 'error';
    } else {
        try {
            $controller = new UtilisateurController();
            $controller->requestPasswordReset($email, frontofficeBaseUrl() . '/reset-password.php');
            // Message volontairement générique pour ne pas divulguer l'état du compte.
            header('Location: login.php?status=reset_link_sent');
            exit;
        } catch (Throwable $exception) {
            $feedback = 'Une erreur est survenue. Reessayez plus tard.';
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
    <title>Mot de passe oublie | SecondVoice</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="stylesheet" href="assets/css/auth.css" />
  </head>
  <body class="auth-screen">
    <main class="auth-stage">
      <a class="auth-brand" href="index.php">
        <img src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" />
      </a>

      <section class="user-panel">
        <div class="user-panel-head">
          <div class="user-panel-intro">
            <div class="user-avatar">SV</div>
            <div>
              <p class="user-panel-title">Mot de passe oublie</p>
              <p class="user-modal-copy">Recevez un lien de reinitialisation par e-mail.</p>
            </div>
          </div>
          <a class="icon-btn user-close" href="login.php" aria-label="Retour connexion">X</a>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Reinitialiser le mot de passe</h3>
          <p class="auth-helper">Saisissez votre e-mail pour recevoir un lien.</p>

          <form class="auth-form" method="post" action="forgot-password.php" novalidate>
            <input class="field" type="text" name="email" value="<?= h($email) ?>" placeholder="Adresse e-mail" />
            <p class="field-error"><?= h($fieldError) ?></p>
            <p class="auth-feedback <?= $feedbackType === 'error' ? 'error' : '' ?>"><?= h($feedback) ?></p>
            <button class="btn btn-primary" type="submit">Envoyer le lien</button>
          </form>

          <div class="user-panel-footer">
            <a class="btn btn-secondary" href="login.php">Retour connexion</a>
          </div>
        </section>
      </section>
    </main>
  </body>
</html>

