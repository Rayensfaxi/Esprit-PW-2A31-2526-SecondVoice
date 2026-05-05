<section class="section">
  <div class="container service-layout">
    <div class="post-content fade-up">
      <div class="post-hero">
        <div class="post-hero-content">
          <div class="tag">Formulaire de réservation</div>
          <h2><?php echo $rdvToEdit ? 'Modifier mon rendez-vous' : 'Détails du rendez-vous'; ?></h2>
          <p><?php echo $rdvToEdit ? 'Modifiez les informations ci-dessous.' : 'Merci de remplir les informations ci-dessous pour confirmer votre demande.'; ?></p>
        </div>
      </div>
      
      <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
      <?php endif; ?>

        <form id="rdvForm" method="POST" action="addRendezvous.php" class="auth-form" style="max-width: 100%; margin-top: 2.5rem;" novalidate>
          <?php if ($rdvToEdit): ?>
            <input type="hidden" name="id" value="<?php echo $rdvToEdit->getId(); ?>">
            <input type="hidden" name="statut" value="<?php echo $rdvToEdit->getStatut(); ?>">
          <?php endif; ?>
          <div class="input-row" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem;">
            <div style="flex: 1; min-width: 250px;">
              <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Service souhaité</label>
              <select id="service" name="service" class="field" style="width: 100%;">
                <option value="" disabled <?php echo !$rdvToEdit ? 'selected' : ''; ?>>Choisir un service...</option>
                <?php 
                $services = ["Accompagnement administratif", "Suivi de dossier", "Gestion de réclamation", "Support technique"];
                foreach($services as $s) {
                    $selected = ($rdvToEdit && $rdvToEdit->getService() == $s) ? 'selected' : '';
                    echo "<option value=\"$s\" $selected>$s</option>";
                }
                ?>
              </select>
          <div id="service-error" class="js-error">Veuillez choisir un service.</div>
        </div>
        <div style="flex: 1; min-width: 250px;">
          <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Assistant préféré</label>
          <select id="assistant" name="assistant" class="field" style="width: 100%;">
                <option value="" disabled <?php echo !$rdvToEdit ? 'selected' : ''; ?>>Choisir un assistant...</option>
                <?php 
                $assistants = [
                    "Amira Selmi" => "Amira Selmi (Administration)",
                    "Nour Kammoun" => "Nour Kammoun (Social)",
                    "Hichem Ben Ali" => "Hichem Ben Ali (Technique)"
                ];
                foreach($assistants as $val => $label) {
                    $selected = ($rdvToEdit && $rdvToEdit->getAssistant() == $val) ? 'selected' : '';
                    echo "<option value=\"$val\" $selected>$label</option>";
                }
                ?>
              </select>
              <div id="assistant-error" class="js-error">Veuillez choisir un assistant.</div>
            </div>
          </div>

          <div class="input-row" style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem;">
            <div style="flex: 1; min-width: 250px;">
              <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Date du rendez-vous</label>
              <input id="date_rdv" name="date_rdv" class="field" type="date" style="width: 100%;" value="<?php echo $rdvToEdit ? $rdvToEdit->getDateRdv()->format('Y-m-d') : ''; ?>" />
              <div id="date-error" class="js-error">Veuillez choisir une date valide.</div>
            </div>
            <div style="flex: 1; min-width: 250px;">
              <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Heure du rendez-vous</label>
              <input id="heure_rdv" name="heure_rdv" class="field" type="time" style="width: 100%;" value="<?php echo $rdvToEdit ? $rdvToEdit->getHeureRdv() : ''; ?>" />
              <div id="heure-error" class="js-error">Veuillez choisir une heure.</div>
            </div>
          </div>

        <div style="margin-bottom: 2rem;">
          <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Mode du rendez-vous</label>
          <div style="display: flex; gap: 3rem; flex-wrap: wrap;">
            <label class="check-row"><input type="radio" name="mode" value="Présentiel" <?php echo (!$rdvToEdit || $rdvToEdit->getMode() == 'Présentiel') ? 'checked' : ''; ?> /> Présentiel</label>
            <label class="check-row"><input type="radio" name="mode" value="En ligne" <?php echo ($rdvToEdit && $rdvToEdit->getMode() == 'En ligne') ? 'checked' : ''; ?> /> En ligne (Visioconférence)</label>
          </div>
        </div>

        <div style="margin-bottom: 2.5rem;">
          <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text); opacity: 0.9;">Remarques ou précisions (Obligatoire)</label>
          <textarea name="remarques" id="remarques" class="field" placeholder="Expliquez brièvement l'objet de votre rendez-vous..." style="width: 100%; min-height: 140px; resize: vertical;"><?php echo $rdvToEdit ? $rdvToEdit->getRemarques() : ''; ?></textarea>
          <div id="remarques-error" class="js-error">Ce champ est obligatoire et ne doit pas dépasser 50 mots.</div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
          <?php if ($rdvToEdit): ?>
            <a href="HomeRendezvous.php" class="btn btn-secondary">Annuler l'édition</a>
          <?php else: ?>
          <?php endif; ?>
          <button class="btn btn-primary" type="submit" name="save_rdv"><?php echo $rdvToEdit ? 'Mettre à jour' : 'Confirmer le rendez-vous'; ?></button>
        </div>
      </form>
