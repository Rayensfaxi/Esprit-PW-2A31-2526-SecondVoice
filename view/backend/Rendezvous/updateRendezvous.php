<?php
require_once '../../../controller/RendezvousC.php';
require_once '../../../model/Rendezvous.php';

$rendezvousC = new RendezvousC();

if (isset($_POST['update_rdv'])) {
    $id = $_POST['id'] ?? null;
    $id_citoyen = $_POST['id_citoyen'] ?? null;
    $service = $_POST['service'] ?? '';
    $assistant = $_POST['assistant'] ?? '';
    $date_rdv = $_POST['date_rdv'] ?? '';
    $heure_rdv = $_POST['heure_rdv'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $remarques = $_POST['remarques'] ?? '';
    $statut = $_POST['statut'] ?? 'En attente';

    if ($id && $id_citoyen && !empty($service) && !empty($assistant) && !empty($date_rdv) && !empty($heure_rdv)) {
        $currentRdv = $rendezvousC->getRendezvousById($id);
        if ($currentRdv && $currentRdv->getStatut() == 'Annulé') {
            header('Location: HomeRendezvous.php?error=Impossible de modifier un rendez-vous annulé');
            exit;
        }

        $heure_time = strtotime($heure_rdv);
        $start_time = strtotime('08:10');
        $end_time = strtotime('17:30');
        $word_count = !empty(trim($remarques)) ? preg_match_all('/\S+/', $remarques) : 0;

        if ($heure_time < $start_time || $heure_time > $end_time) {
            header('Location: HomeRendezvous.php?error=Heure invalide (08:10-17:30)');
            exit;
        }
        if (empty(trim($remarques))) {
            header('Location: HomeRendezvous.php?error=Remarques obligatoires');
            exit;
        }
        if ($word_count > 50) {
            header('Location: HomeRendezvous.php?error=Max 50 mots pour les remarques');
            exit;
        }

        $rdv = new Rendezvous(
            $id,
            $id_citoyen,
            htmlspecialchars($service),
            htmlspecialchars($assistant),
            new DateTime($date_rdv),
            htmlspecialchars($heure_rdv),
            htmlspecialchars($mode),
            htmlspecialchars($remarques),
            htmlspecialchars($statut)
        );
        $rendezvousC->updateRendezvous($rdv, $id);
        header('Location: HomeRendezvous.php?status=updated');
        exit;
    }
}
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
                <select name="service" id="edit-service" class="form-control" style="width: 100%; height: 42px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--panel-2); color: var(--text);">
                  <option value="Accompagnement administratif">Accompagnement administratif</option>
                  <option value="Suivi de dossier">Suivi de dossier</option>
                  <option value="Gestion de réclamation">Gestion de réclamation</option>
                  <option value="Support technique">Support technique</option>
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
