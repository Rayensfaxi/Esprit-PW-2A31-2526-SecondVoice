<?php
require_once '../../../controller/RendezvousC.php';
require_once '../../../controller/ServiceC.php';
$rendezvousC = new RendezvousC();
$serviceC = new ServiceC();

// Vérification de la bibliothèque QR Code
$libQRCode = dirname(__DIR__, 3) . '/lib/phpqrcode/qrlib.php';
$isQRCodeLibMissing = !file_exists($libQRCode);

// Récupération des services pour le filtre calendrier
$servicesList = $serviceC->listServices();

// Récupération des filtres
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// On récupère les stats pour l'affichage via les nouvelles méthodes optimisées
$globalStats = $rendezvousC->getGlobalStats($startDate, $endDate);
$statsByService = $rendezvousC->getRendezvousStatsByService($startDate, $endDate);

// Préparation des données pour Chart.js
$serviceLabels = [];
$serviceData = [];
foreach ($statsByService as $stat) {
    $serviceLabels[] = $stat['service_nom'];
    $serviceData[] = (int)$stat['count'];
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
    <link rel="stylesheet" href="../assets/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- FullCalendar -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script>
      console.log("Scripts de base chargés.");
    </script>
  </head>
  <body data-page="subscription">
    <?php if ($isQRCodeLibMissing): ?>
    <div style="background: #ff6b6b20; color: #ff6b6b; padding: 10px; text-align: center; border-bottom: 1px solid #ff6b6b40; font-size: 0.9rem;">
      <strong>Attention :</strong> La bibliothèque <code>phpqrcode</code> est manquante dans <code>/lib/phpqrcode/</code>. Les QR Codes ne seront pas générés.
    </div>
    <?php endif; ?>
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="../index.html"><img class="brand-logo" src="../assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="../index.html" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="../gestion-utilisateurs.html" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="../Service/HomeService.php" data-nav="services"><span class="nav-icon icon-card"></span><span>Gestion des services</span></a>
              <a class="nav-link" href="HomeRendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="../gestion-accompagnements.html" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="../gestion-documents.html" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des documents</span></a>
              <a class="nav-link" href="../gestion-reclamations.html" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="../settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
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
            <a class="update-button" href="../../frontend/index.html">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
            <div class="profile-menu-wrap" data-profile-wrap>
              <button class="profile-trigger" data-profile-toggle aria-label="Open profile menu">
                <img class="topbar-avatar" src="../assets/media/profile-avatar.svg" alt="Profile" />
              </button>
              <div class="profile-dropdown" data-profile-menu>
                <div class="profile-dropdown-card">
                  <div class="profile-thumb"><img src="../assets/media/profile-avatar.svg" alt="Profile avatar" /></div>
                  <div>
                    <strong>Admin SecondVoice</strong>
                    <span>Administrateur</span>
                  </div>
                </div>
                <div class="profile-menu-list">
                  <a class="menu-link" href="../settings.html"><span class="menu-icon icon-settings"></span><span>Parametres</span></a>
                </div>
                <button class="logout-button" type="button">Déconnexion <span class="logout-arrow">-></span></button>
              </div>
            </div>
          </div>
        </div>

        <div class="page-grid">
           <!-- Section Statistiques -->
           <section class="stats-section" style="grid-column: 1 / -1; margin-bottom: 30px;">
              <div class="card" style="padding: 24px; margin-bottom: 24px;">
                 <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 class="panel-title">Filtres et Statistiques Globales</h3>
                    <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                       <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="search-input" style="width: auto; padding: 8px;">
                       <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="search-input" style="width: auto; padding: 8px;">
                       <button type="submit" class="update-button">Filtrer</button>
                       <a href="HomeRendezvous.php" class="update-button" style="background: var(--muted-2);">Réinitialiser</a>
                    </form>
                 </div>
                 
                 <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="stat-card">
                       <div class="small-label">Total Rendez-vous</div>
                       <span class="metric-number"><?php echo $globalStats['total']; ?></span>
                       <div class="status-note">Tous statuts confondus</div>
                    </div>
                    <div class="stat-card">
                       <div class="small-label">Confirmés</div>
                       <span class="metric-number" style="color: #31d0aa;"><?php echo $globalStats['confirmed']; ?></span>
                       <div class="status-note">Prêts pour le service</div>
                    </div>
                    <div class="stat-card">
                       <div class="small-label">En attente</div>
                       <span class="metric-number" style="color: #ffb84d;"><?php echo $globalStats['pending']; ?></span>
                       <div class="status-note">À traiter prochainement</div>
                    </div>
                    <div class="stat-card">
                       <div class="small-label">Annulés</div>
                       <span class="metric-number" style="color: #ff6b6b;"><?php echo $globalStats['cancelled']; ?></span>
                       <div class="status-note">Rendez-vous annulés</div>
                    </div>
                 </div>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                 <div class="card" style="padding: 24px;">
                    <h3 class="panel-title" style="margin-bottom: 20px;">Répartition par Service</h3>
                    <div style="max-height: 300px; display: flex; justify-content: center;">
                       <canvas id="serviceChart"></canvas>
                    </div>
                 </div>
                 <div class="card" style="padding: 24px;">
                    <h3 class="panel-title" style="margin-bottom: 20px;">Statistiques par Service (Tableau)</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                       <table class="table" style="width: 100%; border-collapse: collapse;">
                          <thead>
                             <tr>
                                <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--line);">Service</th>
                                <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--line);">Nombre</th>
                                <th style="text-align: right; padding: 12px; border-bottom: 1px solid var(--line);">%</th>
                             </tr>
                          </thead>
                          <tbody>
                             <?php foreach ($statsByService as $s): ?>
                             <tr>
                                <td style="padding: 12px; border-bottom: 1px solid var(--line);"><?php echo $s['service_nom']; ?></td>
                                <td style="text-align: right; padding: 12px; border-bottom: 1px solid var(--line);"><?php echo $s['count']; ?></td>
                                <td style="text-align: right; padding: 12px; border-bottom: 1px solid var(--line);">
                                   <?php echo $globalStats['total'] > 0 ? round(($s['count'] / $globalStats['total']) * 100, 1) : 0; ?>%
                                </td>
                             </tr>
                             <?php endforeach; ?>
                          </tbody>
                       </table>
                    </div>
                 </div>
              </div>
           </section>

           <!-- Section Calendrier Interactif -->
           <section class="calendar-section" style="grid-column: 1 / -1; margin-bottom: 30px;">
              <div class="card" style="padding: 24px;">
                 <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                       <h3 class="panel-title">Calendrier des Rendez-vous</h3>
                       <div class="helper">Visualisation mensuelle des rendez-vous.</div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                       <select id="calendarServiceFilter" class="search-input" style="width: auto; padding: 8px;">
                          <option value="">Tous les services</option>
                          <?php foreach ($servicesList as $s): ?>
                             <option value="<?php echo $s->getId(); ?>"><?php echo $s->getNom(); ?></option>
                          <?php endforeach; ?>
                       </select>
                    </div>
                 </div>
                 <div id='calendar' style="min-height: 600px; color: var(--text);"></div>
              </div>
           </section>

           <div style="grid-column: 1 / -1;">
              <?php include 'rendezvousList.php'; ?>
           </div>
        </div>
      </main>
    </div>

    <style>
      @media print {
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

      /* FullCalendar Dark Mode / Custom Styling */
      :root {
          --fc-border-color: var(--line);
          --fc-daygrid-event-dot-width: 8px;
      }
      .fc .fc-toolbar-title {
          font-family: 'Outfit', sans-serif;
          color: var(--text);
      }
      .fc .fc-button-primary {
          background-color: var(--purple);
          border-color: var(--purple);
      }
      .fc .fc-button-primary:hover {
          background-color: var(--purple-2);
          border-color: var(--purple-2);
      }
      .fc .fc-button-primary:disabled {
          background-color: var(--muted);
          border-color: var(--muted);
      }
      .fc-theme-standard td, .fc-theme-standard th {
          border-color: var(--line);
      }
      .fc-day-today {
          background: var(--soft-surface) !important;
      }
      .fc-event {
          cursor: pointer;
          border-radius: 4px;
          padding: 2px 4px;
      }
    </style>

    <?php include 'detailsRendezvous.php'; ?>
    <?php include 'updateRendezvous.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/app.js"></script>
    <script src="rendezvous.js"></script>
    <script>
        // Graphique par Service
        const serviceCtx = document.getElementById('serviceChart').getContext('2d');
        new Chart(serviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($serviceLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($serviceData); ?>,
                    backgroundColor: [
                        '#635bff', '#31d0aa', '#ffb84d', '#ff6b6b', '#4cc9f0', '#f72585'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#96a0b8' }
                    }
                }
            }
        });

        // Gestion des erreurs via SweetAlert2 (on ne l'affiche plus pour les succès selon la demande)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            Swal.fire('Erreur !', urlParams.get('error'), 'error');
        }

        // Initialisation du Calendrier
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var serviceFilter = document.getElementById('calendarServiceFilter');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    today: "Aujourd'hui",
                    month: 'Mois',
                    week: 'Semaine',
                    day: 'Jour'
                },
                events: 'getCalendarEvents.php',
                eventClick: function(info) {
                    const props = info.event.extendedProps;
                    Swal.fire({
                        title: info.event.title,
                        html: `
                            <div style="text-align: left; font-family: 'Outfit', sans-serif;">
                                <p style="margin-bottom: 8px;"><strong>Date:</strong> ${info.event.start.toLocaleString('fr-FR')}</p>
                                <p style="margin-bottom: 8px;"><strong>Statut:</strong> <span style="color: ${info.event.backgroundColor}">${props.statut}</span></p>
                                <p style="margin-bottom: 8px;"><strong>Assistant:</strong> ${props.assistant}</p>
                                <p style="margin-bottom: 8px;"><strong>Mode:</strong> ${props.mode}</p>
                                <p style="margin-bottom: 8px;"><strong>Remarques:</strong> ${props.remarques || 'Aucune'}</p>
                                <p style="margin-bottom: 0;"><strong>ID Citoyen:</strong> ${props.id_citoyen}</p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonColor: 'var(--purple)'
                    });
                },
                eventDidMount: function(info) {
                    // Optionnel: ajout d'un tooltip ou autre
                }
            });

            calendar.render();

            // Filtrage par service
            serviceFilter.addEventListener('change', function() {
                var serviceId = this.value;
                var newUrl = 'getCalendarEvents.php' + (serviceId ? '?service_id=' + serviceId : '');
                
                // On retire l'ancienne source et on ajoute la nouvelle
                calendar.getEventSources().forEach(source => source.remove());
                calendar.addEventSource(newUrl);
            });
        });
    </script>
  </body>
</html>
