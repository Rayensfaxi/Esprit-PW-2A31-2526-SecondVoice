<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();
$id_citoyen = 1; // Simule pour l'exemple

$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'date_desc';

$liste = $rendezvousC->listRendezvousByCitoyen($id_citoyen, $search, $filterStatus, $sortBy);

function rdv_status_class_ajax(string $status): string
{
    $s = mb_strtolower(trim($status));
    $s = str_replace(['é', 'è', 'ê'], 'e', $s);
    $s = str_replace(' ', '-', $s);
    return $s;
}

if ($liste === []) {
    echo '<div id="noResults" class="appointment-card" style="text-align:center;">';
    echo '<p style="font-size: 1.1rem; color: var(--muted);">Aucun rendez-vous ne correspond à vos critères.</p>';
    echo '<a href="javascript:void(0)" onclick="resetFilters()" class="text-link">Réinitialiser les filtres</a>';
    echo '</div>';
    exit;
}

foreach ($liste as $rdv) {
    $status = $rdv->getStatut();
    $statusClass = rdv_status_class_ajax($status);
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
    <?php
}

