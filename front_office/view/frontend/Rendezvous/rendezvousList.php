<!-- Section Mes rendez-vous -->
<div style="margin-top: 5rem;">
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
              <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $rdv['statut'])); ?>">
                  <?php echo $rdv['statut']; ?>
              </span>
              <h4><?php echo $rdv['service']; ?></h4>
              <p style="margin: 0; color: var(--muted); font-size: 0.95rem;">Assistant : <strong><?php echo $rdv['assistant']; ?></strong></p>
            </div>
            <div class="appointment-meta">
              <p style="margin: 0; font-weight: 700; font-size: 1.1rem; color: var(--primary);"><?php echo date('d M Y', strtotime($rdv['date_rdv'])); ?></p>
              <p style="margin: 0; font-weight: 600;"><?php echo $rdv['heure_rdv']; ?></p>
              <p style="margin: 0.25rem 0 0; color: var(--muted); font-size: 0.85rem;">Mode : <?php echo $rdv['mode']; ?></p>
            </div>
          </div>
          <div class="appointment-footer">
            <p style="font-size: 0.85rem; color: var(--muted); font-style: italic; margin: 0;">
              <?php 
              if ($rdv['statut'] == 'Confirmé') echo 'Ce rendez-vous est déjà confirmé.';
              elseif ($rdv['statut'] == 'Annulé') echo 'Ce rendez-vous a été annulé.';
              else echo 'Rendez-vous modifiable.'; 
              ?>
            </p>
            <div class="appointment-actions">
               <?php if ($rdv['statut'] == 'En attente'): ?>
                  <a href="HomeRendezvous.php?edit=<?php echo $rdv['id']; ?>#rdvForm" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Reprogrammer</a>
                  <a href="cancelRendezvous.php?cancel=<?php echo $rdv['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c;" onclick="return confirm('Voulez-vous vraiment annuler ce rendez-vous ?')">Annuler</a>
               <?php endif; ?>
               <?php if ($rdv['statut'] == 'Annulé'): ?>
                  <a href="deleteRendezvous.php?delete=<?php echo $rdv['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #e74c3c;" onclick="return confirm('Supprimer définitivement cet historique ?')">Supprimer</a>
               <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
