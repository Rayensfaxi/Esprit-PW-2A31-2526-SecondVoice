<!-- Section Mes rendez-vous -->
<div style="margin-top: 5rem;">
  <div class="post-hero">
    <div class="post-hero-content">
      <div class="tag">Mon Suivi</div>
      <h2>Mes rendez-vous</h2>
      <p>Consultez, modifiez ou annulez vos rendez-vous en cours.</p>
    </div>
  </div>

  <!-- Barre de recherche et filtres -->
  <div id="searchRdvForm" class="filters-bar" style="margin-top: 3rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; background: var(--panel); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--line);">
    <div style="flex: 1; min-width: 250px; position: relative;">
      <input type="text" id="searchRdv" placeholder="Rechercher un service, assistant..." 
             value="<?php echo htmlspecialchars($search); ?>"
             autocomplete="off"
             style="width: 100%; padding: 0.8rem 1rem 0.8rem 2.5rem; border-radius: var(--radius-md); border: 1px solid var(--line); background: var(--input-bg); color: var(--text);">
      <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); opacity: 0.5;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      </span>
    </div>
    
    <select id="filterStatus" style="padding: 0.8rem; border-radius: var(--radius-md); border: 1px solid var(--line); background: var(--input-bg); color: var(--text); min-width: 150px;">
      <option value="">Tous les statuts</option>
      <option value="En attente" <?php echo $filterStatus == 'En attente' ? 'selected' : ''; ?>>En attente</option>
      <option value="Confirmé" <?php echo $filterStatus == 'Confirmé' ? 'selected' : ''; ?>>Confirmé</option>
      <option value="Annulé" <?php echo $filterStatus == 'Annulé' ? 'selected' : ''; ?>>Annulé</option>
    </select>

    <select id="sortRdv" style="padding: 0.8rem; border-radius: var(--radius-md); border: 1px solid var(--line); background: var(--input-bg); color: var(--text); min-width: 180px;">
      <option value="date_desc" <?php echo $sortBy == 'date_desc' ? 'selected' : ''; ?>>Plus récent au plus ancien</option>
      <option value="date_asc" <?php echo $sortBy == 'date_asc' ? 'selected' : ''; ?>>Plus ancien au plus récent</option>
      <option value="service_asc" <?php echo $sortBy == 'service_asc' ? 'selected' : ''; ?>>Service (A-Z)</option>
      <option value="service_desc" <?php echo $sortBy == 'service_desc' ? 'selected' : ''; ?>>Service (Z-A)</option>
    </select>
  </div>

  <div id="rendezvousList" style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 2rem;">
    <!-- Message "Aucun résultat" pour le filtrage JS -->
    <div id="noResults" style="display: none; text-align: center; padding: 3rem; background: var(--panel); border-radius: var(--radius-lg); border: 1px solid var(--line); width: 100%;">
      <p style="font-size: 1.1rem; color: var(--muted);">Aucun rendez-vous ne correspond à vos critères.</p>
      <a href="javascript:void(0)" onclick="resetFilters()" class="text-link" style="margin-top: 1rem;">Réinitialiser les filtres</a>
    </div>

    <?php if (empty($liste)): ?>
      <div id="emptyInitial" style="text-align: center; padding: 3rem; background: var(--panel); border-radius: var(--radius-lg); border: 1px solid var(--line);">
        <p style="font-size: 1.1rem; color: var(--muted);">Aucun rendez-vous ne correspond à vos critères.</p>
        <a href="javascript:void(0)" onclick="resetFilters()" class="text-link" style="margin-top: 1rem;">Réinitialiser les filtres</a>
      </div>
    <?php else: ?>
      <?php foreach ($liste as $rdv): ?>
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
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
