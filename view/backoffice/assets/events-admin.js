document.addEventListener('DOMContentLoaded', function () {
  function $(sel, ctx = document) { return ctx.querySelector(sel); }
  function $all(sel, ctx = document) { return Array.from((ctx || document).querySelectorAll(sel)); }

  const apiBase = '../frontoffice/events.php';
  const feedback = $('#admin-feedback');
  const modal = $('#registrants-modal');
  const registrantsList = $('#registrants-list');

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
    const icon = isConfirm ? '⚠️' : '✅';
    const title = isConfirm ? 'Confirmation' : 'Succès';
    const buttonClass = isConfirm ? 'btn outline' : 'btn';
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
      ">
        <div style="font-size: 48px; margin-bottom: 15px;">${icon}</div>
        <h3 style="margin: 0 0 15px 0; font-size: 20px; color: #1f2937;">${title}</h3>
        <p style="margin: 0 0 25px 0; color: #6b7280; line-height: 1.5;">${message}</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <button type="button" class="${buttonClass}" id="popup-close-btn">${buttonText}</button>
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

  function showConfirm(message, onConfirm, onCancel) {
    showPopup(message, 'confirm', onConfirm, onCancel);
  }

  function showFeedback(message, isError) {
    if (!feedback) {
      window.alert(message);
      return;
    }
    feedback.textContent = message;
    feedback.style.display = 'block';
    feedback.style.background = isError ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)';
    feedback.style.color = isError ? '#b91c1c' : '#065f46';
  }

  // Helper functions for field error messages
  function showFieldError(fieldId, message) {
    const errorElement = document.getElementById(fieldId + '-error');
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.color = '#dc3545';
      errorElement.style.fontSize = '14px';
      errorElement.style.marginTop = '5px';
      errorElement.style.display = 'block';
    }
  }

  function clearFieldError(fieldId) {
    const errorElement = document.getElementById(fieldId + '-error');
    if (errorElement) {
      errorElement.textContent = '';
      errorElement.style.display = 'none';
    }
  }

  function clearAllFieldErrors() {
    const requiredFields = ['evt-name', 'evt-start', 'evt-end', 'evt-deadline', 'evt-location'];
    requiredFields.forEach(fieldId => clearFieldError(fieldId));
  }

  function validateAdminTitleField() {
    const input = document.getElementById('evt-name');
    const value = input?.value.trim() || '';

    if (!input) return true;

    if (value === '') {
      showFieldError('evt-name', 'Ce champ est obligatoire');
      return false;
    }

    if (value.length < 6) {
      showFieldError('evt-name', 'Le titre doit contenir au moins 6 caractères');
      return false;
    }

    clearFieldError('evt-name');
    return true;
  }

  function createRow(container, value = '') {
    const row = document.createElement('div');
    row.className = 'dyn-row';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'field';
    input.value = value;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn small outline';
    remove.textContent = 'Suppr';
    remove.addEventListener('click', function () {
      row.remove();
    });

    row.appendChild(input);
    row.appendChild(remove);
    container.appendChild(row);
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

  function collectResources(container, type) {
    return $all('.dyn-row input', container)
      .map(function (input) {
        return input.value.trim();
      })
      .filter(Boolean)
      .map(function (name) {
        return { name: name, description: '', type: type };
      });
  }

  function resetForm() {
    $('#admin-event-form')?.reset();
    $('#evt-id').value = '';
    $('#evt-max').value = 1;
  }

  function openModal() {
    modal?.setAttribute('aria-hidden', 'false');
    modal?.classList.add('open');
  }

  function closeModal() {
    modal?.setAttribute('aria-hidden', 'true');
    modal?.classList.remove('open');
  }

    $('#admin-reset')?.addEventListener('click', resetForm);
  $('.modal-close')?.addEventListener('click', closeModal);

  $all('.admin-tabs .tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      $all('.admin-tabs .tab').forEach(function (item) { item.classList.remove('active'); });
      $all('.tab-panel').forEach(function (panel) { panel.classList.remove('active'); });
      this.classList.add('active');
      const target = document.querySelector(this.getAttribute('data-target'));
      target && target.classList.add('active');
      window.setTimeout(applyAdminSearch, 0);
    });
  });

  const adminSearchInput = document.getElementById('admin-events-search');

  function normalizeSearchValue(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function getActiveAdminSearchScope() {
    return document.querySelector('.tab-panel.active');
  }

  function getAdminCardSearchText(card) {
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

  function getAdminSearchEmptyMessage(scope) {
    let message = scope.querySelector(':scope > .admin-search-empty');
    if (!message) {
      message = document.createElement('div');
      message.className = 'small admin-search-empty';
      message.textContent = 'Aucun résultat trouvé';
      message.hidden = true;
      scope.appendChild(message);
    }
    return message;
  }

  function applyAdminSearch() {
    if (!adminSearchInput) return;

    const scope = getActiveAdminSearchScope();
    if (!scope) return;

    const query = normalizeSearchValue(adminSearchInput.value);
    const cards = $all('.event-card', scope);
    let visibleCount = 0;

    cards.forEach(function(card) {
      const matches = query === '' || normalizeSearchValue(getAdminCardSearchText(card)).includes(query);
      card.style.display = matches ? '' : 'none';
      if (matches) visibleCount += 1;
    });

    const emptyMessage = getAdminSearchEmptyMessage(scope);
    emptyMessage.hidden = query === '' || visibleCount > 0 || cards.length === 0;
  }

  adminSearchInput?.addEventListener('input', applyAdminSearch);
  applyAdminSearch();

  $all('.view-registrants').forEach(function (button) {
    button.addEventListener('click', function () {
      const card = this.closest('.event-card');
      let registrants = [];
      try {
        registrants = JSON.parse(card?.getAttribute('data-registrants') || '[]');
      } catch (error) {
        registrants = [];
      }

      registrantsList.innerHTML = '';
      if (!registrants.length) {
        registrantsList.innerHTML = '<div class="small">Aucun inscrit.</div>';
      } else {
        registrants.forEach(function (registrant) {
          const item = document.createElement('div');
          item.className = 'reg-item';
          const fullName = [registrant.prenom || '', registrant.nom || ''].join(' ').trim() || 'Utilisateur';
          item.innerHTML = '<strong>' + fullName + '</strong><div class="small">' + (registrant.email || '') + '</div>';
          registrantsList.appendChild(item);
        });
      }

      openModal();
    });
  });

  $all('.modify').forEach(function (button) {
    button.addEventListener('click', function () {
      const card = this.closest('.event-card');
      if (!card) return;

      $('#evt-id').value = card.getAttribute('data-id') || '';
      $('#evt-name').value = card.getAttribute('data-name') || '';
      $('#evt-desc').value = card.getAttribute('data-desc') || '';
      $('#evt-start').value = toLocalInputValue(card.getAttribute('data-start') || '');
      $('#evt-end').value = toLocalInputValue(card.getAttribute('data-end') || '');
      $('#evt-deadline').value = toLocalInputValue(card.getAttribute('data-deadline') || '');
      $('#evt-location').value = card.getAttribute('data-location') || '';
      $('#evt-max').value = card.getAttribute('data-max') || 1;
      
      
      document.querySelector('.admin-tabs .tab[data-target="#tab-add"]')?.click();
    });
  });

  $all('.delete').forEach(function (button) {
    button.addEventListener('click', async function () {
      const eventId = Number(this.getAttribute('data-id'));
      if (!eventId) return;

      showConfirm('Êtes-vous sûr de vouloir supprimer cet événement ?', async function() {
        const result = await postJson(apiBase + '?action=delete', { id: eventId });
        if (result?.success) {
          showSuccess('Événement supprimé avec succès');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors de la suppression.', true);
        }
      });
    });
  });

  // Validation and save handler
  $('#admin-save')?.addEventListener('click', async function () {
    clearAllFieldErrors();

    // Define required fields
    const requiredFields = [
      { id: 'evt-name', name: 'Nom' },
      { id: 'evt-start', name: 'Date début' },
      { id: 'evt-end', name: 'Date fin' },
      { id: 'evt-deadline', name: 'Date limite' },
      { id: 'evt-location', name: 'Lieu' }
    ];

    let isValid = true;
    let firstInvalidField = null;

    // Validate each required field
    requiredFields.forEach(field => {
      const input = document.getElementById(field.id);
      if (field.id === 'evt-name') {
        if (!validateAdminTitleField()) {
          isValid = false;
          if (!firstInvalidField) {
            firstInvalidField = input;
          }
        }
        return;
      }

      if (!input || !input.value.trim()) {
        isValid = false;
        showFieldError(field.id, 'Ce champ est obligatoire');
        if (!firstInvalidField) {
          firstInvalidField = input;
        }
      }
    });

    if (!isValid) {
      if (firstInvalidField) {
        firstInvalidField.focus();
      }
      return;
    }

    const isUpdate = $('#evt-id').value;

    const payload = {
      id: $('#evt-id').value || null,
      name: $('#evt-name').value.trim(),
      description: $('#evt-desc').value.trim(),
      start_date: $('#evt-start').value || null,
      end_date: $('#evt-end').value || null,
      deadline: $('#evt-deadline').value || null,
      location: $('#evt-location').value.trim(),
      max: Number($('#evt-max').value) || 1,
      resources: []
    };

    const url = payload.id ? apiBase + '?action=update' : apiBase + '?action=create';
    const result = await postJson(url, payload);
    if (result?.success) {
      const message = isUpdate ? 'Événement modifié avec succès' : 'Événement ajouté avec succès';
      showSuccess(message);
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showFeedback(result?.message || "Erreur lors de l'enregistrement.", true);
    }
  });

  $('#admin-event-form')?.addEventListener('submit', function (event) {
    event.preventDefault();
    $('#admin-save')?.click();
  });

  // Clear error messages on input
  const requiredFieldIds = ['evt-name', 'evt-start', 'evt-end', 'evt-deadline', 'evt-location'];
  requiredFieldIds.forEach(fieldId => {
    const input = document.getElementById(fieldId);
    if (input) {
      input.addEventListener('input', function() {
        if (fieldId === 'evt-name') {
          validateAdminTitleField();
          return;
        }

        if (this.value.trim()) {
          clearFieldError(fieldId);
        }
      });
    }
  });

  // Approve / Reject handlers for pending events
  $all('.approve').forEach(function (button) {
    button.addEventListener('click', async function () {
      const eventId = Number(this.getAttribute('data-id'));
      if (!eventId) return;

      showConfirm('Êtes-vous sûr de vouloir valider cet événement ?', async function() {
        const result = await postJson(apiBase + '?action=approve', { id: eventId });
        if (result?.success) {
          showSuccess('Événement validé avec succès');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors de la validation.', true);
        }
      });
    });
  });

  $all('.reject').forEach(function (button) {
    button.addEventListener('click', async function () {
      const eventId = Number(this.getAttribute('data-id'));
      if (!eventId) return;

      showConfirm('Êtes-vous sûr de vouloir refuser cet événement ?', async function() {
        const result = await postJson(apiBase + '?action=reject', { id: eventId });
        if (result?.success) {
          showSuccess('Événement refusé avec succès');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors du refus.', true);
        }
      });
    });
  });

  // Approve / Reject deletion request handlers
  $all('.approve-deletion').forEach(function (button) {
    button.addEventListener('click', async function () {
      const requestId = Number(this.getAttribute('data-request-id'));
      if (!requestId) return;

      showConfirm('Êtes-vous sûr de vouloir approuver cette demande de suppression ? L\'événement sera définitivement supprimé.', async function() {
        const result = await postJson(apiBase + '?action=approve_deletion', { request_id: requestId });
        if (result?.success) {
          showSuccess('Demande approuvée et événement supprimé');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors de l\'approbation.', true);
        }
      });
    });
  });

  $all('.reject-deletion').forEach(function (button) {
    button.addEventListener('click', async function () {
      const requestId = Number(this.getAttribute('data-request-id'));
      if (!requestId) return;

      showConfirm('Êtes-vous sûr de vouloir refuser cette demande de suppression ? L\'événement sera conservé.', async function() {
        const result = await postJson(apiBase + '?action=reject_deletion', { request_id: requestId });
        if (result?.success) {
          showSuccess('Demande de suppression refusée');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors du refus.', true);
        }
      });
    });
  });

  // Approve / Reject modification request handlers
  $all('.approve-modification').forEach(function (button) {
    button.addEventListener('click', async function () {
      const requestId = Number(this.getAttribute('data-request-id'));
      if (!requestId) return;

      showConfirm('Êtes-vous sûr de vouloir approuver cette modification ? Les changements seront appliqués à l\'événement.', async function() {
        const result = await postJson(apiBase + '?action=approve_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess('Modification approuvée et appliquée');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors de l\'approbation.', true);
        }
      });
    });
  });

  $all('.reject-modification').forEach(function (button) {
    button.addEventListener('click', async function () {
      const requestId = Number(this.getAttribute('data-request-id'));
      if (!requestId) return;

      showConfirm('Êtes-vous sûr de vouloir refuser cette modification ? L\'événement restera inchangé.', async function() {
        const result = await postJson(apiBase + '?action=reject_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess('Modification refusée');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors du refus.', true);
        }
      });
    });
  });

  // Approve / Reject resource modification request handlers
  $all('.approve-resource-mod').forEach(function (button) {
    button.addEventListener('click', async function () {
      const requestId = Number(this.getAttribute('data-request-id'));
      if (!requestId) return;

      showConfirm('Êtes-vous sûr de vouloir approuver cette modification des ressources ? Les nouvelles ressources remplaceront les anciennes.', async function() {
        const result = await postJson(apiBase + '?action=approve_resource_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess('Modification des ressources approuvée');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors de l\'approbation.', true);
        }
      });
    });
  });

  $all('.reject-resource-mod').forEach(function (button) {
    button.addEventListener('click', async function () {
      const requestId = Number(this.getAttribute('data-request-id'));
      if (!requestId) return;

      showConfirm('Êtes-vous sûr de vouloir refuser cette modification des ressources ? Les ressources actuelles seront conservées.', async function() {
        const result = await postJson(apiBase + '?action=reject_resource_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess('Modification des ressources refusée');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors du refus.', true);
        }
      });
    });
  });
});
