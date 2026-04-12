<?php
require_once '../../../controller/RendezvousC.php';
$rendezvousC = new RendezvousC();

// Recherche et Filtres
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['filterStatus'] ?? '';

// Récupération de la liste filtrée
$allRdv = $rendezvousC->listRendezvous();
$liste = [];

foreach ($allRdv as $r) {
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
            <button class="view-btn" onclick="if(confirm('Supprimer ce rendez-vous ?')) window.location.href='deleteRendezvous.php?id=<?php echo $rdv['id']; ?>'" style="background: #ef444415; border-color: #ef4444; color: #ef4444;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
