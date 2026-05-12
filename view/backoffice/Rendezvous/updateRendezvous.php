<?php
require_once __DIR__ . '/../../../controller/RendezvousHttpController.php';

$rendezvousHttpController = new RendezvousHttpController();
$rendezvousHttpController->handleUpdateRequest();
$listeServices = $rendezvousHttpController->getServicesForForm();
?>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
  <div class="modal-container" style="width: 500px; max-width: 90%;">
    <div class="modal-header">
      <h3 class="modal-title">Modifier le rendez-vous</h3>
      <button class="modal-close" onclick="closeEditModal()">&times;</button>
    </div>
    <div class="modal-body">
        <form id="editForm" method="POST" action="updateRendezvous.php" novalidate>
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="id_citoyen" id="edit-id-citoyen">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Service</label>
                <select name="service_id" id="edit-service" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                  <?php foreach ($listeServices as $s): ?>
                    <option value="<?php echo $s->getId(); ?>"><?php echo htmlspecialchars($s->getNom()); ?></option>
                  <?php endforeach; ?>
                </select>
                <div id="edit-service-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Veuillez choisir un service.</div>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Assistant</label>
                <select name="assistant" id="edit-assistant" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                  <option value="Amira Selmi">Amira Selmi (Administration)</option>
                  <option value="Nour Kammoun">Nour Kammoun (Social)</option>
                  <option value="Hichem Ben Ali">Hichem Ben Ali (Technique)</option>
                </select>
                <div id="edit-assistant-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Veuillez choisir un assistant.</div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Date</label>
                    <input type="date" name="date_rdv" id="edit-date" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                    <div id="edit-date-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Date invalide.</div>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Heure</label>
                    <input type="time" name="heure_rdv" id="edit-heure" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                    <div id="edit-heure-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Veuillez choisir une heure.</div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Mode</label>
                    <select name="mode" id="edit-mode" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                        <option value="Présentiel">Présentiel</option>
                        <option value="En ligne">En ligne</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Statut</label>
                    <select name="statut" id="edit-statut" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                        <option value="En attente">En attente</option>
                        <option value="Confirmé">Confirmé</option>
                        <option value="Annulé">Annulé</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Remarques (Obligatoire, max 50 mots)</label>
                <textarea name="remarques" id="edit-remarques" class="form-control" style="width: 100%; min-height: 80px; padding: 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);"></textarea>
                <div id="edit-remarques-error" class="js-error" style="display:none; color: #ef4444; font-size: 0.8rem;">Ce champ est obligatoire et ne doit pas dépasser 50 mots.</div>
            </div>
            <div class="modal-footer" style="padding: 0; border-top: none;">
                <button type="button" onclick="closeEditModal()" class="action-button" style="background: #94a3b8; border-color: #94a3b8;">Annuler</button>
                <button type="submit" name="update_rdv" class="action-button">Enregistrer</button>
            </div>
        </form>
    </div>
  </div>
</div>
