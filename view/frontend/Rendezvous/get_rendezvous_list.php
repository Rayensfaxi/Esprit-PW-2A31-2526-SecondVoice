<?php
require_once '../../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();
$id_citoyen = 1; // Simulé pour l'exemple

$search = $_GET['search'] ?? "";
$filterStatus = $_GET['status'] ?? "";
$sortBy = $_GET['sort'] ?? "date_desc";

$liste = $rendezvousC->listRendezvousByCitoyen($id_citoyen, $search, $filterStatus, $sortBy);

if (empty($liste)) {
    echo '<div id="noResults" style="text-align: center; padding: 3rem; background: var(--panel); border-radius: var(--radius-lg); border: 1px solid var(--line); width: 100%;">';
    echo '<p style="font-size: 1.1rem; color: var(--muted);">Aucun rendez-vous ne correspond à vos critères.</p>';
    echo '<a href="javascript:void(0)" onclick="resetFilters()" class="text-link" style="margin-top: 1rem;">Réinitialiser les filtres</a>';
    echo '</div>';
} else {
    foreach ($liste as $rdv) {
        ?>
        <div class="appointment-card" 
             data-service="<?php echo htmlspecialchars(strtolower($rdv->getService())); ?>" 
             data-assistant="<?php echo htmlspecialchars(strtolower($rdv->getAssistant())); ?>" 
             data-status="<?php echo htmlspecialchars($rdv->getStatut()); ?>" 
             data-date="<?php echo $rdv->getDateRdv()->format('Y-m-d'); ?>"
             data-timestamp="<?php 
                $dt = clone $rdv->getDateRdv();
                $h = $rdv->getHeureRdv();
                if ($h) {
                    $parts = explode(':', $h);
                    $dt->setTime((int)$parts[0], (int)$parts[1]);
                }
                echo $dt->getTimestamp(); 
             ?>">
          <div class="appointment-header">
            <div class="appointment-info">
              <span class="status-badge <?php 
                $statusClass = strtolower($rdv->getStatut());
                $statusClass = str_replace(' ', '-', $statusClass);
                $statusClass = str_replace(['é', 'è', 'ê'], 'e', $statusClass);
                echo $statusClass; 
              ?>">
                  <?php echo $rdv->getStatut(); ?>
              </span>
              <h4><?php echo $rdv->getService(); ?></h4>
              <p style="margin: 0; color: var(--muted); font-size: 0.95rem;">Assistant : <strong><?php echo $rdv->getAssistant(); ?></strong></p>
            </div>
            <div class="appointment-meta">
              <p style="margin: 0; font-weight: 700; font-size: 1.1rem; color: var(--primary);"><?php echo $rdv->getDateRdv()->format('d M Y'); ?></p>
              <p style="margin: 0; font-weight: 600;"><?php echo $rdv->getHeureRdv(); ?></p>
              <p style="margin: 0.25rem 0 0; color: var(--muted); font-size: 0.85rem;">Mode : <?php echo $rdv->getMode(); ?></p>
            </div>
          </div>
          <div class="appointment-footer">
            <p style="font-size: 0.85rem; color: var(--muted); font-style: italic; margin: 0;">
              <?php 
              if ($rdv->getStatut() == 'Confirmé') echo 'Ce rendez-vous est déjà confirmé.';
              elseif ($rdv->getStatut() == 'Annulé') echo 'Ce rendez-vous a été annulé.';
              else echo 'Rendez-vous modifiable.'; 
              ?>
            </p>
            <div class="appointment-actions">
               <?php if ($rdv->getStatut() == 'En attente'): ?>
                  <a href="HomeRendezvous.php?edit=<?php echo $rdv->getId(); ?>#rdvForm" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;color:black">Reprogrammer</a>
                  <a href="javascript:void(0)" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c; position: relative; z-index: 5;" onclick="event.stopPropagation(); if(typeof window.confirmCancel === 'function') { window.confirmCancel(<?php echo $rdv->getId(); ?>); } else { console.error('confirmCancel non trouvé'); }">Annuler</a>
               <?php endif; ?>
               <?php if ($rdv->getStatut() == 'Annulé'): ?>
                  <a href="javascript:void(0)" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c; position: relative; z-index: 5;" onclick="event.stopPropagation(); if(typeof window.confirmDelete === 'function') { window.confirmDelete(<?php echo $rdv->getId(); ?>); } else { console.error('confirmDelete non trouvé'); }">Supprimer</a>
               <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
    }
}
?>
