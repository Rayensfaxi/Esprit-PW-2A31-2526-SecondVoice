document.addEventListener('DOMContentLoaded', function () {
  function $(sel, ctx = document) { return ctx.querySelector(sel); }
  function $all(sel, ctx = document) { return Array.from((ctx || document).querySelectorAll(sel)); }

  const feedback = document.getElementById('events-feedback');
  const eventModalEl = document.getElementById('eventModal');
  const btnAdd = document.getElementById('btn-add-event');
  const deleteBtn = document.getElementById('btn-delete-event');
  const btnCancelAdd = document.getElementById('btn-cancel-add');
  let modal = null;

  if (eventModalEl && window.bootstrap) {
    modal = new bootstrap.Modal(eventModalEl);
  }

  // Popup system for confirmation and success messages
  function showPopup(message, type = 'success', onConfirm = null, onCancel = null) {
    const existingPopup = document.getElementById('custom-popup');
    if (existingPopup) existingPopup.remove();

    const popup = document.createElement('div');
    popup.id = 'custom-popup';
    popup.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      font-family: 'Outfit', sans-serif;
    `;

    const isConfirm = type === 'confirm';
    const isError = type === 'error';
    const icon = isConfirm ? '⚠️' : isError ? '❌' : '✅';
    const title = isConfirm ? 'Confirmation' : isError ? 'Erreur' : 'Succès';
    const titleColor = isError ? '#dc3545' : '#1f2937';
    const borderLeft = isError ? 'border-left: 5px solid #dc3545;' : '';
    const buttonClass = isConfirm ? 'btn outline' : 'btn';
    const buttonStyle = isError ? 'background: #dc3545; color: white;' : '';
    const buttonText = isConfirm ? 'Annuler' : 'OK';
    const confirmButton = isConfirm ? `<button type="button" class="btn" id="popup-confirm-btn" style="margin-left: 10px;">Confirmer</button>` : '';

    popup.innerHTML = `
      <div style="
        background: white;
        padding: 30px 40px;
        border-radius: 12px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        ${borderLeft}
      ">
        <div style="font-size: 48px; margin-bottom: 15px;">${icon}</div>
        <h3 style="margin: 0 0 15px 0; font-size: 20px; color: ${titleColor};">${title}</h3>
        <p style="margin: 0 0 25px 0; color: #6b7280; line-height: 1.5;">${message}</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <button type="button" class="${buttonClass}" id="popup-close-btn" style="${buttonStyle}">${buttonText}</button>
          ${confirmButton}
        </div>
      </div>
    `;

    document.body.appendChild(popup);

    const closeBtn = document.getElementById('popup-close-btn');
    const confirmBtn = document.getElementById('popup-confirm-btn');

    closeBtn.addEventListener('click', function() {
      popup.remove();
      if (onCancel) onCancel();
    });

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function() {
        popup.remove();
        if (onConfirm) onConfirm();
      });
    }

    popup.addEventListener('click', function(e) {
      if (e.target === popup) {
        popup.remove();
        if (onCancel) onCancel();
      }
    });
  }

  function showSuccess(message) {
    showPopup(message, 'success');
  }

  function showError(message) {
    showPopup(message, 'error');
  }

  function showConfirm(message, onConfirm, onCancel) {
    showPopup(message, 'confirm', onConfirm, onCancel);
  }

  function showFeedback(message, type = 'success') {
    if (!feedback) {
      window.alert(message);
      return;
    }
    feedback.className = 'alert alert-' + type;
    feedback.textContent = message;
    feedback.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {})
    });
    return response.json();
  }

  function toLocalInputValue(value) {
    if (!value) return '';
    return String(value).replace(' ', 'T').slice(0, 16);
  }

  function validateDateIsAfterToday(dateString) {
    if (!dateString) return true; // Champ vide sera validé par la validation des champs obligatoires

    const selectedDate = new Date(dateString);
    const today = new Date();

    // Remettre à 0 les heures pour comparer uniquement les dates
    today.setHours(0, 0, 0, 0);
    selectedDate.setHours(0, 0, 0, 0);

    // La date doit être strictement supérieure à aujourd'hui
    return selectedDate > today;
  }

  function validateCapacity(value) {
    if (!value || value.trim() === '') return true; // Champ vide autorisé
    const capacity = Number(value);
    return !isNaN(capacity) && capacity >= 1;
  }

  function showFieldValidationError(fieldId, message) {
    const errorElement = document.getElementById(fieldId + '-error');
    if (!errorElement) return;

    errorElement.textContent = message;
    errorElement.classList.add('visible');
    errorElement.style.display = 'block';
    errorElement.style.color = '#dc3545';
    errorElement.style.visibility = 'visible';
    errorElement.style.opacity = '1';
  }

  function clearFieldValidationError(fieldId) {
    const errorElement = document.getElementById(fieldId + '-error');
    if (!errorElement) return;

    errorElement.textContent = '';
    errorElement.classList.remove('visible');
    errorElement.style.display = 'none';
  }

  function validateEventTitleField() {
    const titleInput = document.getElementById('evt-name');
    const value = titleInput?.value.trim() || '';

    if (!titleInput) return true;

    if (value === '') {
      showFieldValidationError('evt-name', 'Ce champ est obligatoire');
      return false;
    }

    if (value.length < 6) {
      showFieldValidationError('evt-name', 'Le titre doit contenir au moins 6 caractères');
      return false;
    }

    clearFieldValidationError('evt-name');
    return true;
  }

  function applyBackendEventFieldErrors(message) {
    const normalizedMessage = String(message || '').toLowerCase();

    if (
      normalizedMessage.includes('titre doit contenir au moins 6') ||
      (normalizedMessage.includes('obligatoire') && normalizedMessage.includes('nom'))
    ) {
      if (!validateEventTitleField()) {
        document.getElementById('evt-name')?.focus();
        return true;
      }
    }

    return false;
  }

  // Ouvrir le formulaire d'événement (mode création ou modification)
  function openEventModal(mode, eventData) {
    console.log('[FRONT] openEventModal called with mode:', mode, 'data:', eventData);

    // Masquer TOUTES les sections d'onglets
    $all('.tab-panel').forEach(function (panel) {
      panel.classList.remove('active');
      panel.setAttribute('aria-hidden', 'true');
      panel.style.display = 'none';
    });

    // Afficher uniquement le tab "Ajouter"
    const tabAddForm = document.getElementById('tab-add-form');
    if (tabAddForm) {
      tabAddForm.classList.add('active');
      tabAddForm.removeAttribute('aria-hidden');
      tabAddForm.style.display = 'block';
    }

    // Mettre à jour les boutons d'onglet - activer "Ajouter un événement"
    $all('.tab-toggle').forEach(function (b) {
      b.classList.remove('active');
    });
    const btnAddTab = document.querySelector('.tab-toggle[data-target="#tab-add-form"]');
    if (btnAddTab) btnAddTab.classList.add('active');

    const form = document.getElementById('event-form');
    if (!form) {
      console.error('[FRONT] Formulaire event-form non trouvé');
      return;
    }

    if (mode === 'edit' && eventData) {
      console.log('[FRONT] Mode modification - pré-remplissage des champs');
      document.getElementById('evt-id').value = eventData.id || '';
      document.getElementById('evt-name').value = eventData.name || '';
      // ✅ Correction : utiliser evt-description au lieu de evt-desc
      document.getElementById('evt-description').value = eventData.desc || '';
      document.getElementById('evt-start').value = eventData.start || '';
      document.getElementById('evt-end').value = eventData.end || '';
      document.getElementById('evt-deadline').value = eventData.deadline || '';
      document.getElementById('evt-location').value = eventData.location || '';
      document.getElementById('evt-max').value = eventData.max || '';
      document.getElementById('evt-status').value = eventData.status || 'en cours';

      // Changer le titre du formulaire
      const titleEl = document.querySelector('#tab-add-form h2');
      if (titleEl) titleEl.textContent = 'Modifier l\'événement';

      // Changer le texte du bouton
      const saveBtn = document.getElementById('btn-save-event');
      if (saveBtn) saveBtn.textContent = 'Enregistrer les modifications';
    } else {
      console.log('[FRONT] Mode création - formulaire vide');
      form.reset();
      document.getElementById('evt-id').value = '';
      document.getElementById('evt-status').value = 'en cours';

      const titleEl = document.querySelector('#tab-add-form h2');
      if (titleEl) titleEl.textContent = 'Créer un nouvel événement';

      const saveBtn = document.getElementById('btn-save-event');
      if (saveBtn) saveBtn.textContent = 'Enregistrer l\'événement';
    }

    console.log('[FRONT] Formulaire prêt - mode:', mode);
  }

  btnAdd && btnAdd.addEventListener('click', function () {
    openEventModal('add', {});
  });

  // Handler pour le bouton "Annuler" - retour à la section Consulter
  if (btnCancelAdd) {
    btnCancelAdd.addEventListener('click', function () {
      console.log('[FRONT] Bouton Annuler cliqué - retour à Consulter');

      // Reset form
      const form = document.getElementById('event-form');
      if (form) form.reset();

      const evtId = document.getElementById('evt-id');
      if (evtId) evtId.value = '';

      // Retourner à la section "Consulter"
      const tabConsult = document.getElementById('tab-consult');
      const tabAddForm = document.getElementById('tab-add-form');

      if (tabAddForm) {
        tabAddForm.classList.remove('active');
        tabAddForm.style.display = 'none';
      }

      if (tabConsult) {
        tabConsult.classList.add('active');
        tabConsult.style.display = 'block';
      }

      // Mettre à jour les onglets de navigation
      document.querySelectorAll('.nav-tabs .btn').forEach(btn => {
        btn.classList.remove('active');
      });

      // Activer le bouton Consulter
      const btnConsult = document.querySelector('.nav-tabs .btn[data-target="tab-consult"]') ||
                         document.querySelector('.nav-tabs .btn:first-child');
      if (btnConsult) btnConsult.classList.add('active');

      // Réactiver le bouton Ajouter
      const btnShowAdd = document.getElementById('btn-show-add-form');
      if (btnShowAdd) btnShowAdd.disabled = false;

      console.log('[FRONT] Retour à la section Consulter effectué');
    });
  }

  document.addEventListener('click', async function (event) {
    const registerButton = event.target.closest('.register');
    if (registerButton) {
      console.log('[FRONT] Bouton S\'inscrire cliqué');
      registerButton.disabled = true;
      const eventId = Number(registerButton.getAttribute('data-id'));
      console.log('[FRONT] Event ID:', eventId);
      console.log('[FRONT] Envoi requête register...');
      const result = await postJson('events.php?action=register', { event_id: eventId });
      console.log('[FRONT] Réponse register:', result);
      if (result?.success) {
        showSuccess('Inscription effectuée avec succès');
        setTimeout(() => window.location.reload(), 1500);
      } else {
        registerButton.disabled = false;
        showFeedback(result?.message || 'Impossible de vous inscrire.', 'danger');
      }
      return;
    }

    const unregisterButton = event.target.closest('.unregister');
    if (unregisterButton) {
      const eventId = Number(unregisterButton.getAttribute('data-id'));
      showConfirm('Êtes-vous sûr de vouloir vous désinscrire de cet événement ?', async function() {
        unregisterButton.disabled = true;
        const result = await postJson('events.php?action=unregister', { event_id: eventId });
        if (result?.success) {
          showSuccess('Désinscription effectuée avec succès');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          unregisterButton.disabled = false;
          showFeedback(result?.message || 'Impossible de vous désinscrire.', 'danger');
        }
      }, function() {
        // User cancelled - do nothing
      });
      return;
    }

    // Handler pour les boutons "Modifier" - Utilisation de la délégation d'événements
    const modifyButton = event.target.closest('.modify');
    if (modifyButton) {
      event.preventDefault();
      event.stopPropagation();
      console.log('[FRONT] Bouton Modifier cliqué (délégué)');
      const card = modifyButton.closest('.event-card');
      if (!card) {
        console.error('[FRONT] Carte événement non trouvée');
        return;
      }

      console.log('[FRONT] Données de la carte:', card.dataset);

      const eventData = {
        id: card.dataset.id,
        name: card.dataset.name,
        desc: card.dataset.desc,
        start: card.dataset.start,
        end: card.dataset.end,
        deadline: card.dataset.deadline,
        location: card.dataset.location,
        max: card.dataset.max,
        status: card.dataset.status,
        rules: JSON.parse(card.getAttribute('data-rules') || '[]')
      };
      openEventModal('edit', eventData);
      return;
    }

    // Handler pour le bouton Supprimer
    const deleteButton = event.target.closest('.delete');
    if (deleteButton) {
      const eventId = Number(deleteButton.getAttribute('data-id'));
      showConfirm('Êtes-vous sûr de vouloir supprimer cet événement ?', async function() {
        deleteButton.disabled = true;
        const result = await postJson('events.php?action=delete', { id: eventId });
        if (result?.success) {
          showSuccess('Événement supprimé avec succès');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          deleteButton.disabled = false;
          showFeedback(result?.message || 'Impossible de supprimer cet événement.', 'danger');
        }
      }, function() {
        // User cancelled - do nothing
      });
      return;
    }

    // Handler pour le bouton Retirer (suppression définitive d'un événement refusé)
    const retirerButton = event.target.closest('.retirer');
    if (retirerButton) {
      const eventId = Number(retirerButton.getAttribute('data-id'));
      showConfirm('Êtes-vous sûr de vouloir retirer cet événement refusé ? Cette action est définitive.', async function() {
        retirerButton.disabled = true;
        const result = await postJson('events.php?action=delete', { id: eventId });
        if (result?.success) {
          showSuccess('Événement retiré avec succès');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          retirerButton.disabled = false;
          showFeedback(result?.message || 'Impossible de retirer cet événement.', 'danger');
        }
      }, function() {
        // User cancelled - do nothing
      });
      return;
    }

    const card = event.target.closest('.event-card');
    if (card && btnAdd && (event.ctrlKey || event.metaKey)) {
      const eventId = encodeURIComponent(card.getAttribute('data-id'));
      const response = await fetch('events.php?action=get&id=' + eventId);
      const result = await response.json();
      if (result?.success) {
        openEventModal('edit', result.event);
      } else {
        showFeedback(result?.message || 'Impossible de charger cet événement.', 'danger');
      }
    }
  });

  document.getElementById('event-form')?.addEventListener('submit', function (event) {
    event.preventDefault();
    document.getElementById('btn-save-event')?.click();
  });

  document.getElementById('btn-save-event')?.addEventListener('click', async function (event) {
    event?.preventDefault();
    console.log('=== DÉBUT SOUMISSION FORMULAIRE ===');
    console.log('[DEBUG] Bouton Enregistrer cliqué - début validation');

    // Validation des champs obligatoires - FAITE EN DEHORS du try/catch pour bloquer correctement
    const requiredFields = [
      { id: 'evt-name', name: 'Titre' },
      { id: 'evt-start', name: 'Date début' },
      { id: 'evt-end', name: 'Date fin' },
      { id: 'evt-deadline', name: 'Date limite' },
      { id: 'evt-location', name: 'Lieu' }
    ];

    console.log('Champs obligatoires à vérifier:', requiredFields);

    let isValid = true;
    let firstInvalidField = null;

    // Réinitialiser tous les messages d'erreur
    requiredFields.forEach(field => {
      clearFieldValidationError(field.id);
    });

    // Valider chaque champ obligatoire
    requiredFields.forEach(field => {
      const input = document.getElementById(field.id);
      console.log(`[DEBUG] Validation champ ${field.id}:`, input ? `"${input.value}"` : 'INPUT NON TROUVÉ');

      clearFieldValidationError(field.id);

      let errorMessage = '';
      if (!input) {
        errorMessage = 'Ce champ est obligatoire';
      } else {
        const trimmedValue = input.value.trim();
        console.log(`[DEBUG] Valeur trimmée pour ${field.id}: "${trimmedValue}" (longueur: ${trimmedValue.length})`);

        if (field.id === 'evt-name') {
          errorMessage = validateEventTitleField() ? '' : (trimmedValue.length === 0 ? 'Ce champ est obligatoire' : 'Le titre doit contenir au moins 6 caractères');
        } else {
          // Pour les autres champs, garder la logique normale
          if (!trimmedValue) {
            errorMessage = 'Ce champ est obligatoire';
          }
        }
      }

      if (errorMessage) {
        isValid = false;
        console.log(`[DEBUG] ERREUR: Champ ${field.id} - ${errorMessage}`);
        showFieldValidationError(field.id, errorMessage);
        if (!firstInvalidField) {
          firstInvalidField = input;
        }
      }
    });

    // Validation des dates (doivent être postérieures à aujourd'hui)
    const dateFields = ['evt-start', 'evt-end', 'evt-deadline'];
    dateFields.forEach(fieldId => {
      const input = document.getElementById(fieldId);

      if (input && input.value.trim()) {
        console.log(`Validation date ${fieldId}:`, input.value, '->', validateDateIsAfterToday(input.value));
        if (!validateDateIsAfterToday(input.value)) {
          isValid = false;
          console.log(`ERREUR: Date ${fieldId} invalide (pas postérieure à aujourd'hui)`);
          showFieldValidationError(fieldId, 'La date doit être postérieure à la date actuelle');
          if (!firstInvalidField) {
            firstInvalidField = input;
          }
        }
      }
    });

    // Validation du champ "Capacité max" (doit être >= 1 si rempli)
    const capacityInput = document.getElementById('evt-max');
    clearFieldValidationError('evt-max');

    if (capacityInput && capacityInput.value.trim()) {
      console.log(`Validation capacité:`, capacityInput.value, '->', validateCapacity(capacityInput.value));
      if (!validateCapacity(capacityInput.value)) {
        isValid = false;
        console.log(`ERREUR: Capacité invalide (< 1)`);
        showFieldValidationError('evt-max', 'La capacité doit être au moins 1');
        if (!firstInvalidField) {
          firstInvalidField = capacityInput;
        }
      }
    }

    console.log('Résultat validation:', isValid);

    // BLOCAGE CRITIQUE : Si validation invalide, ARRÊTER ICI de manière explicite
    if (!isValid) {
      console.log('🚫 FORMULAIRE INVALIDE - BLOCAGE TOTAL DE L\'ENVOI BACKEND');
      console.log('🚫 PREMIER CHAMP INVALIDE:', firstInvalidField);
      if (firstInvalidField) {
        firstInvalidField.focus();
        console.log('🚫 FOCUS APPLIQUÉ SUR LE CHAMP INVALIDE');
      }
      console.log('🚫 FIN DE LA FONCTION - REQUÊTE BACKEND NON ENVOYÉE - PAS DE POPUP');
      // BLOCAGE TOTAL - PLUS AUCUN CODE NE SERA EXÉCUTÉ APRÈS CETTE LIGNE
      return;
    }

    console.log('✅ FORMULAIRE VALIDE - CONTINUER VERS L\'ENVOI BACKEND');

    console.log('FORMULAIRE VALIDE - CRÉATION DU PAYLOAD');

    // Récupérer l'ID et s'assurer qu'il est correctement interprété
    const eventIdRaw = document.getElementById('evt-id').value;
    const eventId = eventIdRaw && eventIdRaw.trim() !== '' ? parseInt(eventIdRaw, 10) : 0;

    console.log('[DEBUG] ID brut du champ evt-id:', eventIdRaw);
    console.log('[DEBUG] ID parsé:', eventId);
    console.log('[DEBUG] ID > 0 ?', eventId > 0);

    const payload = {
      id: eventId > 0 ? eventId : null,
      name: document.getElementById('evt-name').value.trim(),
      description: document.getElementById('evt-description').value.trim(),
      start_date: document.getElementById('evt-start').value || null,
      end_date: document.getElementById('evt-end').value || null,
      deadline: document.getElementById('evt-deadline').value || null,
      location: document.getElementById('evt-location').value.trim(),
      max: Number(document.getElementById('evt-max').value) || 1,
      status: document.getElementById('evt-status').value || 'en cours',
      resources: []
    };

    console.log('PAYLOAD À ENVOYER:', JSON.stringify(payload, null, 2));

    // Utiliser update si on a un ID valide, sinon create
    const url = eventId > 0 ? 'events.php?action=update' : 'events.php?action=create';
    console.log('[DEBUG] URL choisie:', url, '(eventId=' + eventId + ')');
    console.log('URL APPELÉE:', url);

    console.log('🌐 ENVOI DE LA REQUÊTE BACKEND...');

    let result;
    try {
      result = await postJson(url, payload);
      console.log('✅ REQUÊTE BACKEND TERMINÉE AVEC SUCCÈS');
    } catch (fetchError) {
      console.error('❌ ERREUR RÉSEAU:', fetchError);
      showError('Erreur réseau: ' + fetchError.message);
      return;
    }

    console.log('📥 RÉPONSE DU BACKEND REÇUE:', result);

    if (result?.success) {
      console.log('✅ BACKEND A RETOURNÉ SUCCÈS');
      modal && modal.hide();
      const eventId = result?.id;
      // Si création (pas d'ID dans le payload initial), rediriger vers gestion des ressources
      if (!payload.id && eventId) {
        showSuccess('Événement créé. Redirection vers la gestion des ressources...');
        setTimeout(() => {
          window.location.href = 'resources.php?event_id=' + eventId;
        }, 1500);
      } else {
        showSuccess(result?.message || 'Événement enregistré avec succès');
        setTimeout(() => window.location.reload(), 1500);
      }
    } else {
      console.log('❌ BACKEND A RETOURNÉ UNE ERREUR:', result?.message);
      if (applyBackendEventFieldErrors(result?.message)) {
        return;
      }
      showError(result?.message || 'Erreur lors de l\'enregistrement de l\'événement');
    }
  }); // Fin de l'event listener

  // Validation en temps réel pour le champ "Titre"
  const titleInput = document.getElementById('evt-name');
  if (titleInput) {
    console.log('[DEBUG] Validation en temps réel activée pour evt-name');
    titleInput.addEventListener('input', function() {
      const trimmedValue = this.value.trim();

      console.log(`[DEBUG] Input实时 - Valeur: "${trimmedValue}" (longueur: ${trimmedValue.length})`);

      if (trimmedValue.length === 0) {
        console.log('[DEBUG] Input实时 - Champ vide, afficher "obligatoire"');
        showFieldValidationError('evt-name', 'Ce champ est obligatoire');
      } else if (trimmedValue.length < 6) {
        console.log('[DEBUG] Input实时 - Champ trop court (< 6 caractères), afficher message');
        showFieldValidationError('evt-name', 'Le titre doit contenir au moins 6 caractères');
      } else {
        console.log('[DEBUG] Input实时 - Champ valide (>= 6 caractères), masquer l\'erreur');
        clearFieldValidationError('evt-name');
      }
    });
  } else {
    console.log('[DEBUG] ERREUR: Champ evt-name non trouvé pour la validation en temps réel');
  }

  const searchInput = document.getElementById('events-search');

  function normalizeSearchValue(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function getActiveSearchScope() {
    const activePanel = document.querySelector('.tab-panel.active');
    if (!activePanel) return null;

    if (activePanel.id === 'tab-requests') {
      return activePanel.querySelector('.request-subpanel.active') || activePanel;
    }

    return activePanel;
  }

  function getCardSearchText(card) {
    const explicitSearch = card.getAttribute('data-search');
    if (explicitSearch) return explicitSearch;

    return [
      card.dataset.name,
      card.dataset.desc,
      card.dataset.start,
      card.dataset.end,
      card.dataset.deadline,
      card.dataset.location,
      card.dataset.status,
      card.dataset.materials,
      card.dataset.rules,
      card.textContent
    ].filter(Boolean).join(' ');
  }

  function getSearchEmptyMessage(scope) {
    let message = scope.querySelector(':scope > .search-empty');
    if (!message) {
      message = document.createElement('p');
      message.className = 'muted fade-up search-empty';
      message.textContent = 'Aucun résultat trouvé';
      message.hidden = true;
      scope.appendChild(message);
    }
    return message;
  }

  function applyEventSearch() {
    if (!searchInput) return;

    const query = normalizeSearchValue(searchInput.value);
    const scope = getActiveSearchScope();
    if (!scope) return;

    const cards = Array.from(scope.querySelectorAll('.event-card'));
    let visibleCount = 0;

    cards.forEach(function(card) {
      const matches = query === '' || normalizeSearchValue(getCardSearchText(card)).includes(query);
      card.style.display = matches ? '' : 'none';
      if (matches) visibleCount += 1;
    });

    const emptyMessage = getSearchEmptyMessage(scope);
    emptyMessage.hidden = query === '' || visibleCount > 0 || cards.length === 0;
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyEventSearch);

    document.addEventListener('click', function(event) {
      if (event.target.closest('.tab-toggle') || event.target.closest('.request-filter')) {
        window.setTimeout(applyEventSearch, 0);
      }
    });

    applyEventSearch();
  }

}); // Fin du DOMContentLoaded handler
