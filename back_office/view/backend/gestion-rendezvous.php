<?php
require_once '../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();

// Recherche et Filtres
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['filterStatus'] ?? '';

// Actions
if (isset($_GET['action'])) {
    $rdvId = $_GET['id'] ?? null;
    $action = $_GET['action'];

    if ($rdvId) {
        // Sécurité : Récupérer le rendez-vous actuel pour vérifier son statut
        $currentRdv = $rendezvousC->getRendezvousById($rdvId);

        if ($currentRdv && $currentRdv['statut'] == 'Annulé' && ($action == 'confirm' || $action == 'wait')) {
            // Interdire de passer d'Annulé à Confirmé ou En attente
            header('Location: gestion-rendezvous.php?error=Impossible de modifier un rendez-vous annulé');
            exit;
        }

        if ($action == 'confirm') {
            $rendezvousC->updateStatut($rdvId, 'Confirmé');
        } elseif ($action == 'wait') {
            $rendezvousC->updateStatut($rdvId, 'En attente');
        } elseif ($action == 'delete') {
            $rendezvousC->deleteRendezvous($rdvId);
        }
    }
    header('Location: gestion-rendezvous.php');
    exit;
}

// Mise à jour (POST)
if (isset($_POST['update_rdv'])) {
    $id = $_POST['id'] ?? null;
    $id_citoyen = $_POST['id_citoyen'] ?? null;
    $service = $_POST['service'] ?? '';
    $assistant = $_POST['assistant'] ?? '';
    $date_rdv = $_POST['date_rdv'] ?? '';
    $heure_rdv = $_POST['heure_rdv'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $remarques = $_POST['remarques'] ?? '';
    $statut = $_POST['statut'] ?? 'En attente';

    if ($id && $id_citoyen && !empty($service) && !empty($assistant) && !empty($date_rdv) && !empty($heure_rdv)) {
        // Sécurité : Vérifier si le rendez-vous actuel est annulé
        $currentRdv = $rendezvousC->getRendezvousById($id);
        if ($currentRdv && $currentRdv['statut'] == 'Annulé') {
            header('Location: gestion-rendezvous.php?error=Impossible de modifier un rendez-vous annulé');
            exit;
        }

        // Validation PHP
        $heure_time = strtotime($heure_rdv);
        $start_time = strtotime('08:10');
        $end_time = strtotime('17:30');
        $word_count = !empty(trim($remarques)) ? preg_match_all('/\S+/', $remarques) : 0;

        if ($heure_time < $start_time || $heure_time > $end_time) {
            header('Location: gestion-rendezvous.php?error=Heure invalide (08:10-17:30)');
            exit;
        }
        if (empty(trim($remarques))) {
            header('Location: gestion-rendezvous.php?error=Remarques obligatoires');
            exit;
        }
        if ($word_count > 50) {
            header('Location: gestion-rendezvous.php?error=Max 50 mots pour les remarques');
            exit;
        }

        $rdv = new Rendezvous(
            $id,
            $id_citoyen,
            htmlspecialchars($service),
            htmlspecialchars($assistant),
            new DateTime($date_rdv),
            htmlspecialchars($heure_rdv),
            htmlspecialchars($mode),
            htmlspecialchars($remarques),
            htmlspecialchars($statut)
        );
        $rendezvousC->updateRendezvous($rdv, $id);
        header('Location: gestion-rendezvous.php?status=updated');
        exit;
    }
}

// Récupération de la liste filtrée
$allRdv = $rendezvousC->listRendezvous();
$liste = [];
$stats = ['total' => 0, 'confirme' => 0, 'annule' => 0, 'attente' => 0];

foreach ($allRdv as $r) {
    $stats['total']++;
    if ($r['statut'] == 'Confirmé') $stats['confirme']++;
    elseif ($r['statut'] == 'Annulé') $stats['annule']++;
    elseif ($r['statut'] == 'En attente') $stats['attente']++;

    $matchSearch = empty($search) || 
                   stripos($r['service'], $search) !== false || 
                   stripos($r['assistant'], $search) !== false ||
                   stripos((string)$r['id_citoyen'], $search) !== false;
    
    $matchStatus = empty($filterStatus) || $r['statut'] == $filterStatus;

    if ($matchSearch && $matchStatus) {
        $liste[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Gestion des rendez-vous</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
  </head>
  <body data-page="subscription">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.html"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.html" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.html" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-demandes.html" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des demandes</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.html" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-documents.html" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des documents</span></a>
              <a class="nav-link" href="gestion-reclamations.html" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Gestion des rendez-vous</h1>
            <div class="page-subtitle">Suivez, confirmez et gérez les rendez-vous de la plateforme.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../../../front_office/view/frontend/index.html">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
            <div class="profile-menu-wrap" data-profile-wrap>
              <button class="profile-trigger" data-profile-toggle aria-label="Open profile menu">
                <img class="topbar-avatar" src="assets/media/profile-avatar.svg" alt="Profile" />
              </button>
              <div class="profile-dropdown" data-profile-menu>
                <div class="profile-dropdown-card">
                  <div class="profile-thumb"><img src="assets/media/profile-avatar.svg" alt="Profile avatar" /></div>
                  <div>
                    <strong>Admin SecondVoice</strong>
                    <span>Administrateur</span>
                  </div>
                </div>
                <div class="profile-menu-list">
                  <a class="menu-link" href="settings.html"><span class="menu-icon icon-settings"></span><span>Parametres</span></a>
                </div>
                <button class="logout-button" type="button">Déconnexion <span class="logout-arrow">-></span></button>
              </div>
            </div>
          </div>
        </div>

        <div class="page-grid">
          <section class="table-card" style="grid-column: 1 / -1;">
            <div class="card-header" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 1rem;">
                <h3 class="panel-title">Liste des rendez-vous</h3>
                <div class="users-actions" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                  <div style="position: relative; flex: 1; min-width: 250px;">
                    <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); opacity: 0.5;">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" id="appointmentSearch" placeholder="Rechercher par citoyen, assistant ou service..." 
                           style="width: 100%; height: 42px; padding: 0 14px 0 40px; border-radius: var(--radius-sm); border: 1px solid var(--input-border); background: var(--input-bg); color: var(--text); outline: none;"
                           onkeyup="filterAppointments()" value="<?php echo htmlspecialchars($search); ?>">
                  </div>
                  <select id="statusFilter" onchange="filterAppointments()" style="height: 42px; padding: 0 10px; border-radius: var(--radius-sm); border: 1px solid var(--input-border); background: var(--input-bg); color: var(--text);">
                    <option value="">Tous les statuts</option>
                    <option value="En attente" <?php echo $filterStatus == 'En attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="Confirmé" <?php echo $filterStatus == 'Confirmé' ? 'selected' : ''; ?>>Confirmé</option>
                    <option value="Annulé" <?php echo $filterStatus == 'Annulé' ? 'selected' : ''; ?>>Annulé</option>
                  </select>
                  <button class="action-button" style="background: #1fb47a; border-color: #1fb47a; height: 42px;" type="button" onclick="window.print()">Exporter PDF</button>
                </div>
              </div>
            </div>
            <table class="table users-table">
              <thead>
                <tr>
                  <th style="cursor: pointer;">Citoyen <span style="font-size: 0.7rem; margin-left: 4px;">↕</span></th>
                  <th style="cursor: pointer;">Service / Assistant <span style="font-size: 0.7rem; margin-left: 4px;">↕</span></th>
                  <th style="cursor: pointer;">Date & Heure <span style="font-size: 0.7rem; margin-left: 4px;">↕</span></th>
                  <th>Mode</th>
                  <th style="cursor: pointer;">Statut <span style="font-size: 0.7rem; margin-left: 4px;">↕</span></th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($liste as $rdv): ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <span class="user-avatar"><?php echo strtoupper(substr($rdv['service'], 0, 2)); ?></span>
                      <div>
                        <strong>Citoyen #<?php echo $rdv['id_citoyen']; ?></strong>
                        <span>ID: #<?php echo $rdv['id']; ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div>
                      <strong><?php echo $rdv['service']; ?></strong>
                      <span class="small-label" style="display: block;">avec <?php echo $rdv['assistant']; ?></span>
                    </div>
                  </td>
                  <td>
                    <div>
                      <strong><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></strong>
                      <span class="small-label" style="display: block;"><?php echo $rdv['heure_rdv']; ?></span>
                    </div>
                  </td>
                  <td><span class="badge"><?php echo $rdv['mode']; ?></span></td>
                  <td>
                    <span class="status-pill <?php echo $rdv['statut'] == 'Confirmé' ? 'active' : ($rdv['statut'] == 'Annulé' ? 'disabled' : 'pending'); ?>" 
                          style="cursor: <?php echo $rdv['statut'] == 'Annulé' ? 'default' : 'pointer'; ?>; <?php echo $rdv['statut'] == 'Annulé' ? 'opacity: 0.7;' : ''; ?>"
                          onclick="toggleStatus(<?php echo $rdv['id']; ?>, '<?php echo $rdv['statut']; ?>')">
                      <?php echo $rdv['statut']; ?>
                    </span>
                  </td>
                  <td>
                    <div style="display: flex; gap: 8px;">
                      <button class="view-btn" onclick='openDetails(<?php echo json_encode($rdv, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        Voir
                      </button>
                      <button class="view-btn" onclick='editRdv(<?php echo json_encode($rdv, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                              style="background: var(--panel-2); border-color: var(--line); <?php echo $rdv['statut'] == 'Annulé' ? 'opacity: 0.5; pointer-events: none; cursor: not-allowed;' : ''; ?>"
                              <?php echo $rdv['statut'] == 'Annulé' ? 'title="Modification impossible pour un rendez-vous annulé" disabled' : ''; ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                      </button>
                      <button class="view-btn" onclick="if(confirm('Supprimer ce rendez-vous ?')) window.location.href='gestion-rendezvous.php?action=delete&id=<?php echo $rdv['id']; ?>'" style="background: #ef444415; border-color: #ef4444; color: #ef4444;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </section>

        </div>
      </main>
    </div>

    <style>
      @media print {
        /* Masquer tout sauf la table */
        body * {
          visibility: hidden;
        }
        .table-card, .table-card * {
          visibility: visible;
        }
        .table-card {
          position: absolute;
          left: 0;
          top: 0;
          width: 100%;
        }
        /* Masquer les actions et la barre de recherche dans l'impression */
        .users-actions, .actions-column, th:last-child, td:last-child {
          display: none !important;
        }
        .card-header h3 {
          visibility: visible;
          margin-bottom: 20px;
        }
        .table {
          width: 100%;
          border-collapse: collapse;
        }
        .table th, .table td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: left;
        }
        .status-pill {
          border: none !important;
          padding: 0 !important;
        }
      }
    </style>
    <script src="assets/app.js"></script>
    <script>
      function filterAppointments() {
        const searchInput = document.getElementById('appointmentSearch');
        const filterSearch = searchInput.value.toLowerCase();
        const statusSelect = document.getElementById('statusFilter');
        const filterStatus = statusSelect.value.toLowerCase();
        
        const table = document.querySelector('.users-table');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
          const citizenCell = tr[i].getElementsByTagName('td')[0];
          const serviceCell = tr[i].getElementsByTagName('td')[1];
          const statusCell = tr[i].getElementsByTagName('td')[4];
          
          let matchSearch = false;
          let matchStatus = false;

          if (citizenCell || serviceCell) {
            const citizenText = (citizenCell ? (citizenCell.textContent || citizenCell.innerText) : "").toLowerCase();
            const serviceText = (serviceCell ? (serviceCell.textContent || serviceCell.innerText) : "").toLowerCase();
            if (citizenText.indexOf(filterSearch) > -1 || serviceText.indexOf(filterSearch) > -1) {
              matchSearch = true;
            }
          }

          if (statusCell) {
            const statusText = (statusCell.textContent || statusCell.innerText).trim().toLowerCase();
            if (filterStatus === "" || statusText === filterStatus) {
              matchStatus = true;
            }
          }

          if (matchSearch && matchStatus) {
            tr[i].style.display = "";
          } else {
            tr[i].style.display = "none";
          }
        }
      }

      function toggleStatus(id, currentStatus) {
          if (currentStatus === 'Annulé') {
              alert("Impossible de modifier le statut d'un rendez-vous annulé.");
              return;
          }
          let nextStatus = currentStatus === 'Confirmé' ? 'wait' : 'confirm';
          window.location.href = 'gestion-rendezvous.php?action=' + nextStatus + '&id=' + id;
      }
    </script>

    <!-- Modal Détails Rendez-vous -->
    <div class="modal-overlay" id="detailsModal">
      <div class="modal-container" style="max-width: 450px;">
        <div class="modal-header" style="padding: 16px 20px;">
          <h3 class="modal-title" style="font-size: 1.1rem;">Détails du rendez-vous</h3>
          <button class="modal-close" onclick="closeDetails()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 16px 20px;">
          <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
            <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Citoyen</div>
            <div class="detail-value" id="det-citizen" style="font-size: 0.9rem;">-</div>
          </div>
          <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
            <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Service</div>
            <div class="detail-value" id="det-service" style="font-size: 0.9rem;">-</div>
          </div>
          <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
            <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Assistant</div>
            <div class="detail-value" id="det-assistant" style="font-size: 0.9rem;">-</div>
          </div>
          <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
            <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Date & Heure</div>
            <div class="detail-value" id="det-datetime" style="font-size: 0.9rem;">-</div>
          </div>
          <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
            <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Mode</div>
            <div class="detail-value" id="det-mode" style="font-size: 0.9rem;">-</div>
          </div>
          <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
            <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Statut</div>
            <div class="detail-value" id="det-status" style="font-size: 0.9rem;">-</div>
          </div>
          <div class="detail-row" style="flex-direction: column; border-bottom: none; margin-top: 0px; padding-top: 0px;">
            <div class="detail-label" style="margin-bottom: 2px; font-size: 0.85rem;">Remarques</div>
            <p id="det-notes" style="font-weight: 400; line-height: 1.4; background: var(--panel-2); padding: 10px; border-radius: 8px; border: 1px solid var(--line); font-size: 0.85rem; max-height: 120px; overflow-y: auto; margin: 0;">
              -
            </p>
          </div>
        </div>
        <div class="modal-footer" style="padding: 12px 20px;">
          <button class="action-button" onclick="closeDetails()" style="padding: 6px 16px; font-size: 0.9rem;">Fermer</button>
        </div>
      </div>
    </div>

    <!-- Edit Modal (Adapté du design original pour rester cohérent) -->
    <div id="editModal" class="modal-overlay">
      <div class="modal-container" style="width: 500px; max-width: 90%;">
        <div class="modal-header">
          <h3 class="modal-title">Modifier le rendez-vous</h3>
          <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editForm" method="POST" action="gestion-rendezvous.php" novalidate>
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="id_citoyen" id="edit-id-citoyen">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Service</label>
                    <select name="service" id="edit-service" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                      <option value="Accompagnement administratif">Accompagnement administratif</option>
                      <option value="Suivi de dossier">Suivi de dossier</option>
                      <option value="Gestion de réclamation">Gestion de réclamation</option>
                      <option value="Support technique">Support technique</option>
                    </select>
                    <div id="edit-service-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Veuillez choisir un service.</div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Assistant</label>
                    <select name="assistant" id="edit-assistant" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                      <option value="Amira Selmi">Amira Selmi (Administration)</option>
                      <option value="Nour Kammoun">Nour Kammoun (Social)</option>
                      <option value="Hichem Ben Ali">Hichem Ben Ali (Technique)</option>
                    </select>
                    <div id="edit-assistant-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Veuillez choisir un assistant.</div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Date</label>
                        <input type="date" name="date_rdv" id="edit-date" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                        <div id="edit-date-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Date invalide.</div>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Heure</label>
                        <input type="time" name="heure_rdv" id="edit-heure" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                        <div id="edit-heure-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Veuillez choisir une heure.</div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Mode</label>
                        <select name="mode" id="edit-mode" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                            <option value="Présentiel">Présentiel</option>
                            <option value="En ligne">En ligne</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Statut</label>
                        <select name="statut" id="edit-statut" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                            <option value="En attente">En attente</option>
                            <option value="Confirmé">Confirmé</option>
                            <option value="Annulé">Annulé</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Remarques (Obligatoire, max 50 mots)</label>
                    <textarea name="remarques" id="edit-remarques" class="form-control" style="width: 100%; min-height: 80px; padding: 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);"></textarea>
                    <div id="edit-remarques-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Ce champ est obligatoire et ne doit pas dépasser 50 mots.</div>
                </div>
                <div class="modal-footer" style="padding: 0; border-top: none;">
                    <button type="button" onclick="closeEditModal()" class="action-button" style="background: #94a3b8; border-color: #94a3b8;">Annuler</button>
                    <button type="submit" name="update_rdv" class="action-button">Enregistrer</button>
                </div>
            </form>
        </div>
      </div>
    </div>

    <script>
      let currentRdv = null;

      function openDetails(rdv) {
        currentRdv = rdv;
        document.getElementById('det-citizen').textContent = 'Citoyen #' + rdv.id_citoyen;
        document.getElementById('det-service').textContent = rdv.service;
        document.getElementById('det-assistant').textContent = rdv.assistant;
        document.getElementById('det-datetime').textContent = rdv.date_rdv + ' à ' + rdv.heure_rdv;
        document.getElementById('det-mode').textContent = rdv.mode;
        document.getElementById('det-status').textContent = rdv.statut;
        document.getElementById('det-notes').textContent = rdv.remarques;
        
        document.getElementById('detailsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
      }


      function closeDetails() {
        document.getElementById('detailsModal').classList.remove('active');
        document.body.style.overflow = '';
      }

      function editRdv(rdv) {
          document.getElementById('edit-id').value = rdv.id;
          document.getElementById('edit-id-citoyen').value = rdv.id_citoyen;
          document.getElementById('edit-service').value = rdv.service;
          document.getElementById('edit-assistant').value = rdv.assistant;
          document.getElementById('edit-date').value = rdv.date_rdv;
          document.getElementById('edit-heure').value = rdv.heure_rdv;
          document.getElementById('edit-mode').value = rdv.mode;
          document.getElementById('edit-statut').value = rdv.statut;
          document.getElementById('edit-remarques').value = rdv.remarques;
          
          document.querySelectorAll('.js-error').forEach(el => el.style.display = 'none');
          document.getElementById('editModal').classList.add('active');
          document.body.style.overflow = 'hidden';
      }

      function closeEditModal() {
          document.getElementById('editModal').classList.remove('active');
          document.body.style.overflow = '';
      }

      function validateEditField(fieldId) {
          const field = document.getElementById(fieldId);
          const errorElement = document.getElementById(fieldId + '-error');
          if (!field || !errorElement) return;

          let isValid = true;
          let errorMsg = "";

          if (fieldId === 'edit-service' || fieldId === 'edit-assistant') {
              if (!field.value) { isValid = false; errorMsg = "Veuillez choisir un choix."; }
          } else if (fieldId === 'edit-date') {
              if (!field.value) { isValid = false; errorMsg = "Date invalide."; }
          } else if (fieldId === 'edit-heure') {
              if (!field.value) {
                  isValid = false; errorMsg = "Veuillez choisir une heure.";
              } else {
                  const hParts = field.value.split(':');
                  const timeInMinutes = parseInt(hParts[0]) * 60 + parseInt(hParts[1]);
                  if (timeInMinutes < (8 * 60 + 10) || timeInMinutes > (17 * 60 + 30)) {
                      isValid = false; errorMsg = "L'heure doit être entre 08:10 et 17:30.";
                  }
              }
          } else if (fieldId === 'edit-remarques') {
              const words = field.value.trim().match(/\S+/g) || [];
              const wordCount = words.length;
              if (field.value.trim() === "") {
                  isValid = false; errorMsg = "Ce champ est obligatoire.";
              } else if (wordCount > 50) {
                  isValid = false; errorMsg = "Max 50 mots (actuellement : " + wordCount + " mots).";
              }
          }

          if (isValid) {
              errorElement.style.display = 'none';
          } else {
              errorElement.textContent = errorMsg;
              errorElement.style.display = 'block';
          }
          return isValid;
      }

      ['edit-service', 'edit-assistant', 'edit-date', 'edit-heure', 'edit-remarques'].forEach(id => {
          const el = document.getElementById(id);
          if (el) {
              el.addEventListener('input', () => validateEditField(id));
              el.addEventListener('change', () => validateEditField(id));
          }
      });

      document.getElementById('editForm').addEventListener('submit', function(e) {
          let isValidForm = true;
          ['edit-service', 'edit-assistant', 'edit-date', 'edit-heure', 'edit-remarques'].forEach(id => {
              if (!validateEditField(id)) isValidForm = false;
          });

          if (!isValidForm) e.preventDefault();
      });

      window.onclick = function(e) {
        if (e.target.classList.contains('modal-overlay')) {
          closeDetails();
          closeEditModal();
        }
      };
    </script>
  </body>
</html>
