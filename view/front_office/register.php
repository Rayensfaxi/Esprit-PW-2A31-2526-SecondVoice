<?php

?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inscription | SecondVoice</title>
    <link
      rel="icon"
      type="image/png"
      sizes="32x32"
      href="../assets/media/favicon-32.png"
    />
    <link
      rel="icon"
      type="image/png"
      sizes="16x16"
      href="../assets/media/favicon-16.png"
    />
    <link rel="apple-touch-icon" href="../assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="../assets/media/favicon.png" />
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
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/auth.css" />
  </head>
  <body class="auth-screen">
    <main class="auth-stage">
      <a class="auth-brand" href="index.php">
        <img
          src="../assets/media/secondvoice-logo.png"
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
                Creez votre compte pour configurer votre profil.
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
          <a class="auth-tab" href="login.php">Connexion</a>
          <a class="auth-tab is-active" href="register.php">Inscription</a>
        </div>

        <section class="auth-panel is-active">
          <h3 class="auth-title">Creer un compte</h3>
          <p class="auth-helper">
            Creez un compte de demonstration pour le support et le suivi.
          </p>

          <form
            class="auth-form"
            id="register-form"
            action="profile.php"
            method="get"
          >
            <input
              class="field"
              type="text"
              name="fullName"
              placeholder="Nom complet"
              required
            />
            <input
              class="field"
              type="email"
              name="email"
              placeholder="E-mail professionnel"
              required
            />
            <input
              class="field"
              type="password"
              name="password"
              placeholder="Creer un mot de passe"
              minlength="4"
              required
            />

            <p id="register-feedback" class="auth-feedback"></p>
            <button class="btn btn-primary" type="submit">
              Creer un compte
            </button>
          </form>

          <ul class="auth-links">
            <li><a href="services.php">Voir les offres de service</a></li>
            <li><a href="contact.php">Demander un acces entreprise</a></li>
          </ul>
        </section>
      </section>
    </main>

    <script>
      (function () {
        const form = document.getElementById("register-form");
        const feedback = document.getElementById("register-feedback");
        const SESSION_KEY = "intellectai-session";
        const PROFILE_KEY = "intellectai-profile";

        function save(key, value) {
          try {
            localStorage.setItem(key, JSON.stringify(value));
          } catch (error) {
            // Continue even if storage is unavailable.
          }
        }

        function resolveFrontProfileUrl() {
          return "profile.php";
        }

        form.addEventListener("submit", function (event) {
          event.preventDefault();

          const fullName = form.fullName.value.trim();
          const email = form.email.value.trim();
          const password = form.password.value;

          if (!fullName) {
            feedback.textContent = "Veuillez saisir votre nom complet.";
            feedback.classList.add("error");
            return;
          }

          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            feedback.textContent = "Veuillez saisir une adresse e-mail valide.";
            feedback.classList.add("error");
            return;
          }

          if (!password || password.length < 4) {
            feedback.textContent =
              "Veuillez saisir un mot de passe (4 caracteres minimum).";
            feedback.classList.add("error");
            return;
          }

          const parts = fullName.split(" ").filter(Boolean);
          const firstName = parts[0] || fullName;
          const lastName = parts.slice(1).join(" ");

          const profile = {
            fullName: fullName,
            firstName: firstName,
            lastName: lastName,
            email: email,
            role: "client",
          };

          save(PROFILE_KEY, profile);
          save(SESSION_KEY, {
            token: "demo-token-" + Date.now(),
            user: profile,
            issuedAt: new Date().toISOString(),
          });

          window.location.href = resolveFrontProfileUrl();
        });
      })();
    </script>
  </body>
</html>
