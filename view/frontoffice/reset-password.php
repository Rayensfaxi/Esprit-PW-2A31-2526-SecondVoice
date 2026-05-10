<?php

session_start();
require_once __DIR__ . '/../../controller/UtilisateurController.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$fieldErrors = [
    'password' => '',
    'confirm_password' => ''
];
$feedback = '';
$feedbackType = '';

if ($token === '') {
    header('Location: login.php?status=reset_invalid');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 6) {
        $fieldErrors['password'] = 'Le mot de passe doit contenir au moins 6 caracteres.';
    }

    if ($confirmPassword !== $password) {
        $fieldErrors['confirm_password'] = 'La confirmation ne correspond pas au mot de passe.';
    }

    $hasErrors = false;
    foreach ($fieldErrors as $msg) {
        if ($msg !== '') {
            $hasErrors = true;
            break;
        }
    }

    if ($hasErrors) {
        $feedback = 'Veuillez corriger les erreurs de saisie.';
        $feedbackType = 'error';
    } else {
        try {
            $controller = new UtilisateurController();
            $ok = $controller->resetPasswordByToken($token, $password);
            header('Location: login.php?status=' . ($ok ? 'password_reset_done' : 'reset_invalid'));
            exit;
        } catch (Throwable $exception) {
            $feedback = $exception->getMessage();
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
    <title>Nouveau mot de passe | SecondVoice</title>
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
              <p class="user-panel-title">Nouveau mot de passe</p>
              <p class="user-modal-copy">Definissez un nouveau mot de passe securise.</p>
            </div>
          </div>
          <a class="icon-btn user-close" href="login.php" aria-label="Retour connexion">X</a>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Reinitialisation</h3>
          <p class="auth-helper">Le lien est valable pendant une duree limitee.</p>

          <form class="auth-form" method="post" action="reset-password.php" novalidate>
            <input type="hidden" name="token" value="<?= h($token) ?>" />
            <input class="field" type="password" name="password" placeholder="Nouveau mot de passe" />
            <p class="field-error"><?= h($fieldErrors['password']) ?></p>
            <input class="field" type="password" name="confirm_password" placeholder="Confirmer le mot de passe" />
            <p class="field-error"><?= h($fieldErrors['confirm_password']) ?></p>
            <p class="auth-feedback <?= $feedbackType === 'error' ? 'error' : '' ?>"><?= h($feedback) ?></p>
            <button class="btn btn-primary" type="submit">Mettre a jour</button>
          </form>

          <div class="user-panel-footer">
            <a class="btn btn-secondary" href="login.php">Retour connexion</a>
          </div>
        </section>
      </section>
    </main>
  </body>
</html>

