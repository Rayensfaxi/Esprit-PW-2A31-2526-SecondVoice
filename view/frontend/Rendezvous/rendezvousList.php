<!-- Section Mes rendez-vous -->
<div id="rendezvousList" style="margin-top: 5rem;">
  <div class="post-hero">
    <div class="post-hero-content">
      <div class="tag">Mon Suivi</div>
      <h2>Mes rendez-vous</h2>
      <p>Consultez, modifiez ou annulez vos rendez-vous en cours.</p>
    </div>
  </div>

  <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 3rem;">
    <?php if (empty($liste)): ?>
      <p>Vous n'avez pas encore de rendez-vous.</p>
    <?php else: ?>
      <?php foreach ($liste as $rdv): ?>
        <div class="appointment-card">
          <div class="appointment-header">
            <div class="appointment-info">
              <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $rdv->getStatut())); ?>">
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
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
