<?php

?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des réclamations</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../../assets/style.css" />
    <style>
      .tabs-menu {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        padding: 4px;
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border);
      }
      .tab-button {
        padding: 12px 24px;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
      }
      .tab-button:hover {
        color: var(--text-primary);
      }
      .tab-button.active {
        background: var(--accent);
        color: white;
      }
    </style>
  </head>
  <body data-page="voice">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="../index.php">
              <img
                class="brand-logo"
                src="../../assets/media/secondvoice-logo.png"
                alt="SecondVoice logo"
              />
            </a>
          </div>
          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="../index.php" data-nav="home">
                <span class="nav-icon icon-home"></span>
                <span>Tableau de bord</span>
              </a>
              <a
                class="nav-link"
                href="gestion-utilisateurs.php"
                data-nav="profile"
                ><span class="nav-icon icon-profile"></span
                ><span>Gestion des utilisateurs</span></a
              >
              <a
                class="nav-link"
                href="../demandes/gestion-demandes.php"
                data-nav="community"
              >
                <span class="nav-icon icon-community"></span>
                <span>Gestion des idees</span>
              </a>
              <a
                class="nav-link"
                href="gestion-rendezvous.php"
                data-nav="subscription"
              >
                <span class="nav-icon icon-card"></span>
                <span>Gestion des rendez-vous</span>
              </a>
              <a
                class="nav-link"
                href="gestion-accompagnements.php"
                data-nav="chatbot"
              >
                <span class="nav-icon icon-chat"></span>
                <span>Gestion des guides</span>
              </a>
              <a
                class="nav-link"
                href="../documents/gestion-documents.php"
                data-nav="images"
              >
                <span class="nav-icon icon-image"></span>
                <span>Gestion des evennements</span>
              </a>
              <a
                class="nav-link active"
                href="gestion-reclamations.php"
                data-nav="voice"
              >
                <span class="nav-icon icon-mic"></span>
                <span>Gestion des réclamations</span>
              </a>
              <a class="nav-link" href="../settings.php" data-nav="settings">
                <span class="nav-icon icon-settings"></span>
                <span>Paramètres</span>
              </a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button
              class="mobile-toggle"
              data-nav-toggle
              aria-label="Open navigation"
            >
              =
            </button>
            <h1 class="page-title">Gestion des réclamations</h1>
            <div class="page-subtitle">
              Suivez et gérez toutes les réclamations clients
            </div>
          </div>
          <div class="toolbar-actions">
            <a
              class="update-button"
              href="../../../front_office/view/frontend/index.php"
              >Revenir</a
            >
            <button
              class="icon-button icon-moon"
              data-theme-toggle
              aria-label="Switch theme"
            ></button>
            <div class="profile-menu-wrap" data-profile-wrap>
              <button
                class="profile-trigger"
                data-profile-toggle
                aria-label="Open profile menu"
              >
                <img
                  class="topbar-avatar"
                  src="../../assets/media/profile-avatar.svg"
                  alt="Profile"
                />
              </button>
              <div class="profile-dropdown" data-profile-menu>
                <div class="profile-dropdown-card">
                  <div class="profile-thumb">
                    <img
                      src="../../assets/media/profile-avatar.svg"
                      alt="Profile avatar"
                    />
                  </div>
                  <div>
                    <strong>MR. Crow Kader</strong>
                    <span>CEO, Valo How Masud</span>
                  </div>
                </div>
                <div class="profile-menu-list">
                  <a
                    class="menu-link"
                    href="../utilisateurs/gestion-utilisateurs.php"
                  >
                    <span class="menu-icon icon-profile"></span>
                    <span>Gestion des utilisateurs</span>
                  </a>
                  <a class="menu-link" href="../settings.php">
                    <span class="menu-icon icon-settings"></span>
                    <span>Paramètres</span>
                  </a>
                  <a
                    class="menu-link"
                    href="../rendezvous/gestion-rendezvous.php"
                  >
                    <span class="menu-icon icon-card"></span>
                    <span>Gestion des rendez-vous</span>
                  </a>
                </div>
                <button class="logout-button" type="button">
                  Logout <span class="logout-arrow">-></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="tabs-menu">
          <a href="gestion-reclamation.php" class="tab-button active"
            >Réclamations</a
          >
          <a href="gestion-reponse.php" class="tab-button">Réponses</a>
          <a href="gestion-justification.php" class="tab-button"
            >Justifications</a
          >
        </div>

        <div class="page-grid users-page">
          <section class="users-hero">
            <div class="users-header">
              <div>
                <h2 class="section-title">Liste des réclamations</h2>
                <p class="helper">
                  Consultez, filtrez et gérez toutes les réclamations soumises
                  par les clients.
                </p>
              </div>
              <div class="users-actions">
                <button class="ghost-button" type="button">Exporter</button>
                <a href="form-reclamation.php" class="action-button"
                  >Nouvelle réclamation</a
                >
              </div>
            </div>

            <div class="users-filters">
              <div class="filter-field">
                <label for="reclamation-search">Recherche</label>
                <input
                  id="reclamation-search"
                  type="search"
                  placeholder="Rechercher par ID ou description..."
                />
              </div>
              <div class="filter-field">
                <label for="reclamation-statut">Statut</label>
                <select id="reclamation-statut">
                  <option>Tout</option>
                  <option>En attente</option>
                  <option>En cours</option>
                  <option>Résolue</option>
                  <option>Rejetée</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="reclamation-date">Date</label>
                <select id="reclamation-date">
                  <option>Ce mois</option>
                  <option>30 jours</option>
                  <option>7 jours</option>
                  <option>Aujourd'hui</option>
                </select>
              </div>
            </div>
          </section>

          <section class="table-card">
            <table class="table users-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Description</th>
                  <th>Client</th>
                  <th>Statut</th>
                  <th>Date de création</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>#REC-001</strong></td>
                  <td>Problème de facturation du mois de mars...</td>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">AS</span>
                      <div>
                        <strong>Amira Selmi</strong>
                        <span>User #124</span>
                      </div>
                    </div>
                  </td>
                  <td><span class="status-pill en-cours">En cours</span></td>
                  <td>14/04/2026 10:30</td>
                  <td>
                    <div class="table-actions">
                      <a href="form-reclamation.php?id=1" class="ghost-button"
                        >Modifier</a
                      >
                      <a
                        href="form-reponse.php?reclamation=1"
                        class="ghost-button"
                        >Répondre</a
                      >
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><strong>#REC-002</strong></td>
                  <td>Service client non disponible...</td>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">NK</span>
                      <div>
                        <strong>Nour Kammoun</strong>
                        <span>User #89</span>
                      </div>
                    </div>
                  </td>
                  <td><span class="status-pill resolue">Résolue</span></td>
                  <td>13/04/2026 09:15</td>
                  <td>
                    <div class="table-actions">
                      <a href="form-reclamation.php?id=2" class="ghost-button"
                        >Modifier</a
                      >
                      <button class="ghost-button" type="button">Voir</button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><strong>#REC-003</strong></td>
                  <td>Retard de livraison commande...</td>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">LM</span>
                      <div>
                        <strong>Leila Mansour</strong>
                        <span>User #56</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="status-pill en-attente">En attente</span>
                  </td>
                  <td>12/04/2026 14:20</td>
                  <td>
                    <div class="table-actions">
                      <a href="form-reclamation.php?id=3" class="ghost-button"
                        >Modifier</a
                      >
                      <a
                        href="form-reponse.php?reclamation=3"
                        class="ghost-button"
                        >Répondre</a
                      >
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><strong>#REC-004</strong></td>
                  <td>Produit défectueux reçu...</td>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">HB</span>
                      <div>
                        <strong>Hichem Ben Ali</strong>
                        <span>User #78</span>
                      </div>
                    </div>
                  </td>
                  <td><span class="status-pill rejetee">Rejetée</span></td>
                  <td>10/04/2026 11:00</td>
                  <td>
                    <div class="table-actions">
                      <a
                        href="form-justification.php?reclamation=4"
                        class="ghost-button"
                        >Justifier</a
                      >
                      <button class="ghost-button" type="button">Voir</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>
      </main>
    </div>

    <script src="../../assets/app.js"></script>
  </body>
</html>
