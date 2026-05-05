<!-- Modal Détails Rendez-vous -->
<div class="modal-overlay" id="detailsModal">
  <div class="modal-container" style="max-width: 450px;">
    <div class="modal-header" style="padding: 16px 20px;">
      <h3 class="modal-title" style="font-size: 1.1rem;">Détails du rendez-vous</h3>
      <button class="modal-close" onclick="closeDetails()">&times;</button>
    </div>
    <div class="modal-body" style="padding: 16px 20px;">
      <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
        <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Citoyen</div>
        <div class="detail-value" id="det-citizen" style="font-size: 0.9rem;">-</div>
      </div>
      <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
        <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Service</div>
        <div class="detail-value" id="det-service" style="font-size: 0.9rem;">-</div>
      </div>
      <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
        <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Assistant</div>
        <div class="detail-value" id="det-assistant" style="font-size: 0.9rem;">-</div>
      </div>
      <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
        <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Date & Heure</div>
        <div class="detail-value" id="det-datetime" style="font-size: 0.9rem;">-</div>
      </div>
      <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
        <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Mode</div>
        <div class="detail-value" id="det-mode" style="font-size: 0.9rem;">-</div>
      </div>
      <div class="detail-row" style="margin-bottom: 5px; padding-bottom: 5px;">
        <div class="detail-label" style="flex: 0 0 110px; font-size: 0.85rem;">Statut</div>
        <div class="detail-value" id="det-status" style="font-size: 0.9rem;">-</div>
      </div>
      <div class="detail-row" style="flex-direction: column; border-bottom: none; margin-top: 0px; padding-top: 0px;">
        <div class="detail-label" style="margin-bottom: 2px; font-size: 0.85rem;">Remarques</div>
        <p id="det-notes" style="font-weight: 400; line-height: 1.4; background: var(--panel-2); padding: 10px; border-radius: 8px; border: 1px solid var(--line); font-size: 0.85rem; max-height: 120px; overflow-y: auto; margin: 0;">
          -
        </p>
      </div>
    </div>
    <div class="modal-footer" style="padding: 12px 20px;">
      <button class="action-button" onclick="closeDetails()" style="padding: 6px 16px; font-size: 0.9rem;">Fermer</button>
    </div>
  </div>
</div>
