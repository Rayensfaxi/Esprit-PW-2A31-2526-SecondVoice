<?php

?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nouvelle Réclamation | SecondVoice</title>
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
    <style>
      .form-section {
        max-width: 800px;
        margin: 0 auto;
        padding: 48px 0;
      }
      .form-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 40px;
      }
      .form-input {
        width: 100%;
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--background);
        color: var(--text-primary);
        font-size: 15px;
        font-family: inherit;
      }
      .form-input:focus {
        outline: none;
        border-color: var(--accent);
      }
      .form-textarea {
        min-height: 200px;
        resize: vertical;
      }
      .form-label {
        font-weight: 600;
        font-size: 14px;
        color: var(--text-primary);
        margin-bottom: 8px;
        display: block;
      }
      .form-group {
        margin-bottom: 24px;
      }
      .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
      }
    </style>
  </head>
  <body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.php">
            <img
              class="brand-logo"
              src="../assets/media/secondvoice-logo.png"
              alt="SecondVoice logo"
            />
          </a>
          <button
            class="menu-toggle"
            type="button"
            data-menu-toggle
            aria-label="Ouvrir le menu"
          >
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="about.php">A propos</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button
                class="icon-btn theme-toggle"
                type="button"
                data-theme-toggle
                aria-label="Changer le theme"
              >
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <div class="user-shell" data-user-shell>
                <a
                  class="icon-btn user-trigger"
                  href="login.php"
                  aria-label="Ouvrir la page de connexion"
                >
                  <span>Profil</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main>
        <section class="page-hero">
          <div class="container">
            <div class="page-hero-card fade-up">
              <!--<div class="breadcrumbs">
                <span>Accueil</span><span>/</span
                ><span><a href="gestion-reclamation.php">Réclamations</a></span
                ><span>/</span><span>Nouvelle</span>
              </div>-->
              <h1>Nouvelle réclamation</h1>
              <p>Décrivez votre problème et nous vous répondrons rapidement.</p>
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="container">
            <div class="form-card fade-up">
              <form id="form-reclamation">
                <div class="form-group">
                  <label class="form-label">Votre ID Utilisateur *</label>
                  <input
                    type="number"
                    class="form-input"
                    id="id_user"
                    name="id_user"
                    required
                    placeholder="Entrez votre ID utilisateur"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label"
                    >Description de votre réclamation *</label
                  >
                  <textarea
                    class="form-input form-textarea"
                    id="description"
                    name="description"
                    required
                    placeholder="Décrivez en détail votre problème..."
                  ></textarea>
                </div>

                <div class="form-actions">
                  <a href="gestion-reclamation.php" class="btn btn-secondary"
                    >Annuler</a
                  >
                  <button type="submit" class="btn btn-primary">
                    Soumettre
                  </button>
                </div>
              </form>
            </div>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container">
          <div class="footer-bottom">
            <span>&copy; 2026 SecondVoice. Tous droits réservés.</span>
            <div class="footer-links">
              <a href="services.php">Services</a>
              <a href="contact.php">Contact</a>
            </div>
          </div>
        </div>
      </footer>
    </div>
    <script src="../assets/js/main.js"></script>
    <script>
      document
        .getElementById("form-reclamation")
        .addEventListener("submit", function (e) {
          e.preventDefault();
          alert("Réclamation soumise avec succès !");
          window.location.href = "gestion-reclamation.php";
        });
    </script>
  </body>
</html>
