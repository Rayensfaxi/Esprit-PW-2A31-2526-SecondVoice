<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des utilisateurs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
  </head>
  <body data-page="profile">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
</div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-demandes.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des demandes</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-documents.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des documents</span></a>
              <a class="nav-link" href="gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>

        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des utilisateurs</h1>
            <div class="page-subtitle">Gerez les comptes, roles et acces des utilisateurs.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../../../../front_office/view/frontend/index.html">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
            <div class="profile-menu-wrap" data-profile-wrap>
              <button class="profile-trigger" data-profile-toggle aria-label="Open profile menu">
                <img class="topbar-avatar" src="assets/media/profile-avatar.svg" alt="Mack Gok profile" />
              </button>
              <div class="profile-dropdown" data-profile-menu>
                <div class="profile-dropdown-card">
                  <div class="profile-thumb"><img src="assets/media/profile-avatar.svg" alt="Profile avatar" /></div>
                  <div>
                    <strong>MR. Crow Kader</strong>
                    <span>CEO, Valo How Masud</span>
                  </div>
                </div>
                <div class="profile-menu-list">
                  <a class="menu-link" href="gestion-utilisateurs.php"><span class="menu-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
                  <a class="menu-link" href="settings.php"><span class="menu-icon icon-settings"></span><span>Parametres</span></a>
                  <a class="menu-link" href="gestion-rendezvous.php"><span class="menu-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
                  <a class="menu-link" href="gestion-demandes.php"><span class="menu-icon icon-activity"></span><span>Gestion des demandes</span></a>
                  <a class="menu-link" href="gestion-accompagnements.php"><span class="menu-icon icon-help"></span><span>Gestion des accompagnements</span></a>
                </div>
                <button class="logout-button" type="button">Logout <span class="logout-arrow">-></span></button>
              </div>
            </div>
          </div>
        </div>

        <div class="page-grid users-page">
          <section class="users-hero">
            <div class="users-header">
              <div>
                <h2 class="section-title">Gestion des utilisateurs</h2>
                <p class="helper">Gerez tous les comptes, roles et acces depuis un seul espace.</p>
              </div>
              <div class="users-actions">
                <button class="ghost-button" type="button">Exporter</button>
                <button class="action-button" type="button">Ajouter un utilisateur</button>
              </div>
            </div>
            <div class="users-filters">
              <div class="filter-field">
                <label for="user-search">Recherche</label>
                <input id="user-search" type="search" placeholder="Rechercher un utilisateur" />
              </div>
              <div class="filter-field">
                <label for="user-role">Role</label>
                <select id="user-role">
                  <option>Tout</option>
                  <option>Admin</option>
                  <option>Client</option>
                  <option>Agent</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="user-status">Statut</label>
                <select id="user-status">
                  <option>Tout</option>
                  <option>Actif</option>
                  <option>Inactif</option>
                  <option>En attente</option>
                  <option>Bloque</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="user-date">Date</label>
                <select id="user-date">
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
                  <th>Utilisateur</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Statut</th>
                  <th>Date d'inscription</th>
                  <th>Derniere activite</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">AS</span>
                      <div>
                        <strong>Amira Selmi</strong>
                        <span>amiraselmi</span>
                      </div>
                    </div>
                  </td>
                  <td>amira.selmi@mail.com</td>
                  <td>Admin</td>
                  <td><span class="status-pill active">Actif</span></td>
                  <td>12 mars 2026</td>
                  <td>il y a 2 min</td>
                  <td>
                    <div class="table-actions">
                      <button class="ghost-button" type="button">Voir</button>
                      <button class="ghost-button" type="button">Bloquer</button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">NK</span>
                      <div>
                        <strong>Nour Kammoun</strong>
                        <span>nour.k</span>
                      </div>
                    </div>
                  </td>
                  <td>nour.kammoun@mail.com</td>
                  <td>Agent</td>
                  <td><span class="status-pill pending">En attente</span></td>
                  <td>05 mars 2026</td>
                  <td>il y a 1 h</td>
                  <td>
                    <div class="table-actions">
                      <button class="ghost-button" type="button">Valider</button>
                      <button class="ghost-button" type="button">Refuser</button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">LM</span>
                      <div>
                        <strong>Leila Mansour</strong>
                        <span>leila.m</span>
                      </div>
                    </div>
                  </td>
                  <td>leila.mansour@mail.com</td>
                  <td>Client</td>
                  <td><span class="status-pill review">Inactif</span></td>
                  <td>20 fev 2026</td>
                  <td>il y a 3 jours</td>
                  <td>
                    <div class="table-actions">
                      <button class="ghost-button" type="button">Relancer</button>
                      <button class="ghost-button" type="button">Supprimer</button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar">HB</span>
                      <div>
                        <strong>Hichem Ben Ali</strong>
                        <span>hichem.b</span>
                      </div>
                    </div>
                  </td>
                  <td>hichem.benali@mail.com</td>
                  <td>Client</td>
                  <td><span class="status-pill risk">Bloque</span></td>
                  <td>10 fev 2026</td>
                  <td>il y a 2 semaines</td>
                  <td>
                    <div class="table-actions">
                      <button class="ghost-button" type="button">Debloquer</button>
                      <button class="ghost-button" type="button">Supprimer</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>
      </main>
    </div>

    <script src="assets/app.js"></script>
  </body>
</html>









