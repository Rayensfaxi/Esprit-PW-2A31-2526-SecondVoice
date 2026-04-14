<?php

?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ajouter une Réponse | SecondVoice</title>
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
        max-width: 900px;
        margin: 0 auto;
        padding: 48px 0;
      }
      .form-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 40px;
      }
      .reclamation-info {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 32px;
      }
      .reclamation-info h3 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--text-primary);
      }
      .reclamation-info p {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 8px;
      }
      .reclamation-info strong {
        color: var(--text-primary);
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
        min-height: 180px;
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
      .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
      }
      .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
      }
      .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
      }
      .status-en-attente {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
      }
      .status-en-cours {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
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
              <div class="breadcrumbs">
                <span>Accueil</span><span>/</span
                ><span><a href="gestion-reclamation.php">Réclamations</a></span
                ><span>/</span><span>Répondre</span>
              </div>
              <h1>Ajouter une réponse</h1>
              <p>Répondez à une réclamation existante.</p>
            </div>
          </div>
        </section>

        <section class="form-section">
          <div class="container">
            <div class="form-card fade-up">
              <!-- Info de la réclamation liée -->
              <div class="reclamation-info">
                <h3>📋 Réclamation liée</h3>
                <p><strong>ID :</strong> <span id="rec-id">#REC-001</span></p>
                <p>
                  <strong>Description :</strong>
                  <span id="rec-desc"
                    >Problème de facturation du mois de mars...</span
                  >
                </p>
                <p>
                  <strong>Statut :</strong>
                  <span class="status-badge status-en-attente" id="rec-statut"
                    >En attente</span
                  >
                </p>
              </div>

              <form id="form-reponse">
                <!-- ID Réclamation (caché ou visible selon besoin) -->
                <input
                  type="hidden"
                  id="id_reclamation"
                  name="id_reclamation"
                  value="1"
                />

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">Votre ID Utilisateur *</label>
                    <input
                      type="number"
                      class="form-input"
                      id="id_user"
                      name="id_user"
                      required
                      placeholder="Entrez votre ID"
                    />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Date de réponse</label>
                    <input
                      type="datetime-local"
                      class="form-input"
                      id="date_reponse"
                      name="date_reponse"
                      disabled
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Votre réponse *</label>
                  <textarea
                    class="form-input form-textarea"
                    id="contenu"
                    name="contenu"
                    required
                    placeholder="Rédigez votre réponse à cette réclamation..."
                  ></textarea>
                </div>

                <div class="form-actions">
                  <a href="gestion-reclamation.php" class="btn btn-secondary"
                    >Annuler</a
                  >
                  <button type="submit" class="btn btn-primary">
                    Envoyer la réponse
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
      // Récupérer l'ID de la réclamation depuis l'URL (ex: ?reclamation=1)
      const urlParams = new URLSearchParams(window.location.search);
      const recId = urlParams.get("reclamation") || "1";

      // Simuler les données de la réclamation (en vrai, viendront du serveur)
      const reclamations = {
        1: {
          id: "#REC-001",
          desc: "Problème de facturation du mois de mars...",
          statut: "en_attente",
        },
        2: {
          id: "#REC-002",
          desc: "Service client non disponible...",
          statut: "en_cours",
        },
        3: {
          id: "#REC-003",
          desc: "Retard de livraison commande #4582...",
          statut: "en_attente",
        },
      };

      const rec = reclamations[recId] || reclamations["1"];

      // Afficher les infos
      document.getElementById("rec-id").textContent = rec.id;
      document.getElementById("rec-desc").textContent = rec.desc;
      document.getElementById("id_reclamation").value = recId;

      // Mettre à jour le statut visuel
      const statutBadge = document.getElementById("rec-statut");
      if (rec.statut === "en_cours") {
        statutBadge.className = "status-badge status-en-cours";
        statutBadge.textContent = "En cours";
      }

      // Date auto
      document.getElementById("date_reponse").value = new Date()
        .toISOString()
        .slice(0, 16);

      // Submit
      document
        .getElementById("form-reponse")
        .addEventListener("submit", function (e) {
          e.preventDefault();
          alert("Réponse envoyée avec succès !");
          window.location.href = "gestion-reclamation.php";
        });
    </script>
  </body>
</html>
