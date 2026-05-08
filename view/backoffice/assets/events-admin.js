document.addEventListener('DOMContentLoaded', function () {
  function $(sel, ctx = document) { return ctx.querySelector(sel); }
  function $all(sel, ctx = document) { return Array.from((ctx || document).querySelectorAll(sel)); }

  const apiBase = 'gestion-evenements.php';
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
    const icon = isConfirm ? '!' : 'OK';
    const title = isConfirm ? 'Confirmation' : 'Succes';
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
        return { resource_name: name, quantity: 0, type: type };
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
    });
  });

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
      
      
      $all('.admin-tabs .tab').forEach(function (item) { item.classList.remove('active'); });
      const addTabLink = document.querySelector('.admin-tabs .tab[href*="tab=add"]');
      if (addTabLink) {
        addTabLink.classList.add('active');
      }
      $all('.tab-panel').forEach(function (panel) { panel.classList.remove('active'); });
      const addPanel = document.querySelector('#tab-add');
      if (addPanel) {
        addPanel.classList.add('active');
      }
    });
  });

  $all('.delete').forEach(function (button) {
    button.addEventListener('click', async function () {
      const eventId = Number(this.getAttribute('data-id'));
      if (!eventId) return;

      showConfirm('Etes-vous sur de vouloir supprimer cet evenement ?', async function() {
        const result = await postJson(apiBase + '?action=delete', { id: eventId });
        if (result?.success) {
          showSuccess('Evenement supprime avec succes');
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
      { id: 'evt-start', name: 'Date debut' },
      { id: 'evt-end', name: 'Date fin' },
      { id: 'evt-deadline', name: 'Date limite' },
      { id: 'evt-location', name: 'Lieu' }
    ];

    let isValid = true;
    let firstInvalidField = null;

    // Validate each required field
    requiredFields.forEach(field => {
      const input = document.getElementById(field.id);
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
      const message = isUpdate ? 'Evenement modifie avec succes' : 'Evenement ajoute avec succes';
      showSuccess(message);
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showFeedback(result?.message || "Erreur lors de l'enregistrement.", true);
    }
  });

  // Clear error messages on input
  const requiredFieldIds = ['evt-name', 'evt-start', 'evt-end', 'evt-deadline', 'evt-location'];
  requiredFieldIds.forEach(fieldId => {
    const input = document.getElementById(fieldId);
    if (input) {
      input.addEventListener('input', function() {
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

      showConfirm('Etes-vous sur de vouloir valider cet evenement ?', async function() {
        const result = await postJson(apiBase + '?action=approve', { id: eventId });
        if (result?.success) {
          showSuccess('Evenement valide avec succes');
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

      showConfirm('Etes-vous sur de vouloir refuser cet evenement ?', async function() {
        const result = await postJson(apiBase + '?action=reject', { id: eventId });
        if (result?.success) {
          showSuccess('Evenement refuse avec succes');
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

      showConfirm('Etes-vous sur de vouloir approuver cette demande de suppression ? L evenement sera definitivement supprime.', async function() {
        const result = await postJson(apiBase + '?action=approve_deletion', { request_id: requestId });
        if (result?.success) {
          showSuccess('Demande approuvee et evenement supprime');
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

      showConfirm('Etes-vous sur de vouloir refuser cette demande de suppression ? L evenement sera conserve.', async function() {
        const result = await postJson(apiBase + '?action=reject_deletion', { request_id: requestId });
        if (result?.success) {
          showSuccess('Demande de suppression refusee');
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

      showConfirm('Etes-vous sur de vouloir approuver cette modification ? Les changements seront appliques a l evenement.', async function() {
        const result = await postJson(apiBase + '?action=approve_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess('Modification approuvee et appliquee');
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

      showConfirm('Etes-vous sur de vouloir refuser cette modification ? L evenement restera inchange.', async function() {
        const result = await postJson(apiBase + '?action=reject_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess('Modification refusee');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors du refus.', true);
        }
      });
    });
  });
});
