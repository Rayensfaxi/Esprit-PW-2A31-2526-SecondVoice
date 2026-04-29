<?php
session_start();
include '../../../controller/utilisateurcontroller.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $userController = new UtilisateurController();
    $user = $userController->getUserByEmail($email);
    
    if ($user && password_verify($password, $user->getMot_de_passe())) {
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_role'] = $user->getRole();
        $_SESSION['user_nom'] = $user->getNom();
        $_SESSION['user_prenom'] = $user->getPrenom();
        
        // ✅ Redirection selon le rôle
        if ($user->getRole() === 'assistant') {
            header("Location: gestion-reclamations.php");  // ← Même dossier
        } else if ($user->getRole() === 'admin') {
            header("Location: ../admin/index.php");
        } else {
            header("Location: ../../front_office/index.php");
        }
        exit;
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>



<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion | SecondVoice</title>
    <link
      rel="icon"
      type="image/png"
      sizes="32x32"
      href="../../assets/media/favicon-32.png"
    />
    <link
      rel="icon"
      type="image/png"
      sizes="16x16"
      href="../../assets/media/favicon-16.png"
    />
    <link
      rel="apple-touch-icon"
      href="../../assets/media/apple-touch-icon.png"
    />
    <link rel="shortcut icon" href="../../assets/media/favicon.png" />
    <script>
      const savedTheme = localStorage.getItem("theme");
      const initialTheme =
        savedTheme ||
        (window.matchMedia("(prefers-color-scheme: light)").matches
          ? "light"
          : "dark");
      document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../../assets/css/style.css" />
    <link rel="stylesheet" href="../../assets/css/auth.css" />
  </head>
  <body class="auth-screen">
    <main class="auth-stage">
      <a class="auth-brand" href="index.php">
        <img
          src="../../assets/media/secondvoice-logo.png"
          alt="SecondVoice logo"
        />
      </a>

      <section class="user-panel">
        <div class="user-panel-head">
          <div class="user-panel-intro">
            <div class="user-avatar">GU</div>
            <div>
              <p class="user-panel-title">Bienvenue, Utilisateur invite</p>
              <p class="user-modal-copy">
                Connectez-vous pour completer votre profil.
              </p>
            </div>
          </div>
          <a
            class="icon-btn user-close"
            href="index.php"
            aria-label="Retour a l'accueil"
            >X</a
          >
        </div>

        <div class="auth-tabs">
          <a class="auth-tab is-active" href="login.php">Connexion</a>
          <a class="auth-tab" href="register.php">Inscription</a>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Connexion Client</h3>
          <p class="auth-helper">
            Utilisez votre e-mail et mot de passe pour continuer.
          </p>

          <form class="auth-form" id="login-form" action="" method="POST">
            <input
              class="field"
              type="email"
              name="email"
              placeholder="Adresse e-mail"
              
            />
            <input
              class="field"
              type="password"
              name="password"
              placeholder="Mot de passe"
              minlength="4"
              
            />

            <div class="auth-options">
              <label class="check-row"
                ><input type="checkbox" name="remember" /> Se souvenir de
                moi</label
              >
              <a href="contact.php">Mot de passe oublie ?</a>
            </div>

            <p id="login-feedback" class="auth-feedback"></p>
            <button class="btn btn-primary" type="submit">Se connecter</button>
          </form>

          <div class="user-panel-footer">
            <a class="btn btn-secondary" href="reponse-assist.php">Aller au profil</a>
          </div>
        </section>
      </section>
    </main>

    <script>
    // Affichage simple des erreurs PHP
    <?php if (!empty($error)): ?>
        document.getElementById('login-feedback').textContent = "<?= htmlspecialchars($error) ?>";
        document.getElementById('login-feedback').classList.add('error');
    <?php endif; ?>
</script>
  </body>
</html>
