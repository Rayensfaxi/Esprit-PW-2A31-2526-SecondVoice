<?php
declare(strict_types=1);

function rdv_status_class(string $status): string
{
    $s = mb_strtolower(trim($status));
    $s = str_replace(['é', 'è', 'ê'], 'e', $s);
    $s = str_replace(' ', '-', $s);
    return $s;
}
?>
<div id="searchRdvForm" class="rdv-filters">
  <div class="rdv-search-wrap">
    <input type="text" id="searchRdv" class="rdv-input" placeholder="Rechercher un service, assistant..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off" />
    <span class="rdv-search-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </span>
  </div>

  <select id="filterStatus" class="rdv-input" style="width: 220px;">
    <option value="">Tous les statuts</option>
    <option value="En attente" <?php echo $filterStatus === 'En attente' ? 'selected' : ''; ?>>En attente</option>
    <option value="Confirmé" <?php echo $filterStatus === 'Confirmé' ? 'selected' : ''; ?>>Confirmé</option>
    <option value="Annulé" <?php echo $filterStatus === 'Annulé' ? 'selected' : ''; ?>>Annulé</option>
  </select>

  <select id="sortRdv" class="rdv-input" style="width: 300px;">
    <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Plus récent au plus ancien</option>
    <option value="date_asc" <?php echo $sortBy === 'date_asc' ? 'selected' : ''; ?>>Plus ancien au plus récent</option>
    <option value="service_asc" <?php echo $sortBy === 'service_asc' ? 'selected' : ''; ?>>Service (A-Z)</option>
    <option value="service_desc" <?php echo $sortBy === 'service_desc' ? 'selected' : ''; ?>>Service (Z-A)</option>
  </select>
</div>

<div id="rendezvousList" class="rdv-list">
  <?php if ($liste === []): ?>
    <div id="emptyInitial" class="appointment-card" style="text-align:center;">
      <p style="font-size: 1.1rem; color: var(--muted);">Aucun rendez-vous ne correspond à vos critères.</p>
      <a href="javascript:void(0)" onclick="resetFilters()" class="text-link">Réinitialiser les filtres</a>
    </div>
  <?php else: ?>
    <?php foreach ($liste as $rdv): ?>
      <?php
        $status = $rdv->getStatut();
        $statusClass = rdv_status_class($status);
      ?>
      <div class="appointment-card"
           data-service="<?php echo htmlspecialchars(mb_strtolower($rdv->getService())); ?>"
           data-assistant="<?php echo htmlspecialchars(mb_strtolower($rdv->getAssistant())); ?>"
           data-status="<?php echo htmlspecialchars($status); ?>"
           data-date="<?php echo $rdv->getDateRdv()->format('Y-m-d'); ?>">
        <div class="appointment-header">
          <div class="appointment-info">
            <span class="status-badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($status); ?></span>
            <h4><?php echo htmlspecialchars($rdv->getService()); ?></h4>
            <p>Assistant : <strong><?php echo htmlspecialchars($rdv->getAssistant()); ?></strong></p>
          </div>
          <div class="appointment-meta">
            <p class="rdv-date"><?php echo htmlspecialchars($rdv->getDateRdv()->format('d M Y')); ?></p>
            <p class="rdv-time"><?php echo htmlspecialchars($rdv->getHeureRdv()); ?></p>
            <p class="rdv-mode">Mode : <?php echo htmlspecialchars($rdv->getMode()); ?></p>
          </div>
        </div>
        <div class="appointment-footer">
          <p>
            <?php
              if ($status === 'Confirmé') {
                  echo 'Ce rendez-vous est déjà confirmé.';
              } elseif ($status === 'Annulé') {
                  echo 'Ce rendez-vous a été annulé.';
              } else {
                  echo 'Rendez-vous modifiable.';
              }
            ?>
          </p>
          <div class="appointment-actions">
            <?php if ($status === 'En attente'): ?>
              <a href="HomeRendezvous.php?edit=<?php echo (int) $rdv->getId(); ?>#rdvForm" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color:black;">Reprogrammer</a>
              <a href="javascript:void(0)" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c;" onclick="event.stopPropagation(); if(typeof window.confirmCancel === 'function') { window.confirmCancel(<?php echo (int) $rdv->getId(); ?>); }">Annuler</a>
            <?php elseif ($status === 'Annulé'): ?>
              <a href="javascript:void(0)" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c;" onclick="event.stopPropagation(); if(typeof window.confirmDelete === 'function') { window.confirmDelete(<?php echo (int) $rdv->getId(); ?>); }">Supprimer</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

