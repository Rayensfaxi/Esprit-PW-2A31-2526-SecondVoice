<?php
require_once __DIR__ . '/../../../controller/RendezvousHttpController.php';

$rendezvousViewData = (new RendezvousHttpController())->getListViewDataFromRequest();
$search = $rendezvousViewData['search'];
$filterStatus = $rendezvousViewData['filterStatus'];
$liste = $rendezvousViewData['liste'];
?>

<section class="table-card rendezvous-table-card" style="grid-column: 1 / -1;">
  <div class="card-header rendezvous-table-header">
    <h3 class="panel-title">Liste des rendez-vous</h3>
    <div class="users-actions rendezvous-toolbar">
      <label class="rdv-search-wrap" for="appointmentSearch">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input
          type="text"
          id="appointmentSearch"
          placeholder="Rechercher par citoyen, assistant..."
          onkeyup="filterAppointments()"
          value="<?php echo htmlspecialchars($search); ?>"
        />
      </label>

      <select id="statusFilter" onchange="filterAppointments()" class="rdv-select-filter">
        <option value="">Tous les statuts</option>
        <option value="En attente" <?php echo $filterStatus === 'En attente' ? 'selected' : ''; ?>>En attente</option>
        <option value="Confirmé" <?php echo $filterStatus === 'Confirmé' ? 'selected' : ''; ?>>Confirmé</option>
        <option value="Annulé" <?php echo $filterStatus === 'Annulé' ? 'selected' : ''; ?>>Annulé</option>
      </select>

      <button class="action-button rdv-export-button" type="button" onclick="window.print()">Exporter PDF</button>
    </div>
  </div>

  <table class="table users-table">
    <thead>
      <tr>
        <th>Citoyen <span class="rdv-sort-mark">↕</span></th>
        <th>Service / Assistant <span class="rdv-sort-mark">↕</span></th>
        <th>Date & Heure <span class="rdv-sort-mark">↕</span></th>
        <th>Mode</th>
        <th>Statut <span class="rdv-sort-mark">↕</span></th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($liste as $rdv): ?>
        <?php
          $statusRaw = (string) $rdv->getStatut();
          $statusLower = strtolower($statusRaw);
          $isConfirmed = str_contains($statusLower, 'confirm');
          $isCancelled = str_contains($statusLower, 'annul');
        ?>
        <tr>
          <td>
            <div class="user-cell">
              <span class="user-avatar"><?php echo strtoupper(substr((string) $rdv->getService(), 0, 2)); ?></span>
              <div>
                <strong>Citoyen #<?php echo (int) $rdv->getIdCitoyen(); ?></strong>
                <span>ID: #<?php echo (int) $rdv->getId(); ?></span>
              </div>
            </div>
          </td>
          <td>
            <div>
              <strong><?php echo htmlspecialchars((string) $rdv->getService()); ?></strong>
              <span class="small-label rdv-assistant-label">AVEC <?php echo strtoupper(htmlspecialchars((string) $rdv->getAssistant())); ?></span>
            </div>
          </td>
          <td>
            <div>
              <strong><?php echo $rdv->getDateRdv()->format('d/m/Y'); ?></strong>
              <span class="small-label rdv-time-label"><?php echo htmlspecialchars((string) $rdv->getHeureRdv()); ?></span>
            </div>
          </td>
          <td>
            <span class="badge rdv-mode-pill"><?php echo htmlspecialchars((string) $rdv->getMode()); ?></span>
          </td>
          <td onclick="event.stopPropagation()">
            <span
              class="status-pill <?php echo $isConfirmed ? 'active' : ($isCancelled ? 'disabled' : 'pending'); ?>"
              style="cursor: <?php echo $isCancelled ? 'default' : 'pointer'; ?>;"
              onclick="if(typeof window.toggleStatus === 'function'){ window.toggleStatus(<?php echo (int) $rdv->getId(); ?>, '<?php echo htmlspecialchars($statusRaw, ENT_QUOTES); ?>'); }"
            >
              <?php echo htmlspecialchars($statusRaw); ?>
            </span>
          </td>
          <td>
            <div class="table-actions rdv-action-wrap">
              <button
                type="button"
                class="view-btn rdv-view-btn"
                onclick='event.stopPropagation(); openDetails(<?php echo json_encode([
                  "id" => $rdv->getId(),
                  "id_citoyen" => $rdv->getIdCitoyen(),
                  "service" => $rdv->getService(),
                  "assistant" => $rdv->getAssistant(),
                  "date_rdv" => $rdv->getDateRdv()->format("Y-m-d"),
                  "heure_rdv" => $rdv->getHeureRdv(),
                  "mode" => $rdv->getMode(),
                  "remarques" => $rdv->getRemarques(),
                  "statut" => $rdv->getStatut()
                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                Voir
              </button>

              <button
                type="button"
                class="view-btn rdv-icon-btn rdv-edit-btn"
                onclick='event.stopPropagation(); editRdv(<?php echo json_encode([
                  "id" => $rdv->getId(),
                  "id_citoyen" => $rdv->getIdCitoyen(),
                  "service_id" => $rdv->getServiceId(),
                  "assistant" => $rdv->getAssistant(),
                  "date_rdv" => $rdv->getDateRdv()->format("Y-m-d"),
                  "heure_rdv" => $rdv->getHeureRdv(),
                  "mode" => $rdv->getMode(),
                  "remarques" => $rdv->getRemarques(),
                  "statut" => $rdv->getStatut()
                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                <?php echo $isCancelled ? 'disabled title="Modification impossible pour un rendez-vous annulé"' : ''; ?>
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>

              <button
                type="button"
                class="view-btn rdv-icon-btn rdv-delete-btn"
                onclick="event.stopPropagation(); if(typeof window.confirmDelete === 'function'){ window.confirmDelete(<?php echo (int) $rdv->getId(); ?>); }"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
