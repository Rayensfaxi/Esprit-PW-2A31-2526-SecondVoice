document.addEventListener('DOMContentLoaded', function () {

  function showPopup(message, type = 'success', onConfirm = null, onCancel = null) {
    const existingPopup = document.getElementById('custom-popup');
    if (existingPopup) existingPopup.remove();

    const popup = document.createElement('div');
    popup.id = 'custom-popup';
    popup.style.cssText = `
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5); display: flex; align-items: center;
      justify-content: center; z-index: 10000; font-family: 'Outfit', sans-serif;
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
    const confirmButton = isConfirm
      ? '<button type="button" class="btn" id="popup-confirm-btn" style="margin-left: 10px;">Confirmer</button>'
      : '';

    popup.innerHTML = `
      <div style="background: white; padding: 30px 40px; border-radius: 12px;
        max-width: 400px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.2); ${borderLeft}">
        <div style="font-size: 48px; margin-bottom: 15px;">${icon}</div>
        <h3 style="margin: 0 0 15px 0; font-size: 20px; color: ${titleColor};">${title}</h3>
        <p style="margin: 0 0 25px 0; color: #6b7280; line-height: 1.5;">${message}</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <button type="button" class="${buttonClass}" id="popup-close-btn" style="${buttonStyle}">${buttonText}</button>
          ${confirmButton}
        </div>
      </div>`;

    document.body.appendChild(popup);

    document.getElementById('popup-close-btn').addEventListener('click', function () {
      popup.remove();
      if (onCancel) onCancel();
    });

    const confirmBtn = document.getElementById('popup-confirm-btn');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        popup.remove();
        if (onConfirm) onConfirm();
      });
    }

    popup.addEventListener('click', function (e) {
      if (e.target === popup) {
        popup.remove();
        if (onCancel) onCancel();
      }
    });
  }

  function showSuccess(message) { showPopup(message, 'success'); }
  function showError(message) { showPopup(message, 'error'); }

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {})
    });
    return response.json();
  }

  function showInlineError(input, message) {
    const errorEl = input?.closest('.mb-3')?.querySelector('.error-message');
    if (!errorEl) return;

    errorEl.textContent = message;
    errorEl.classList.add('visible');
    errorEl.style.display = 'block';
  }

  function clearInlineError(input) {
    const errorEl = input?.closest('.mb-3')?.querySelector('.error-message');
    if (!errorEl) return;

    errorEl.textContent = '';
    errorEl.classList.remove('visible');
    errorEl.style.display = 'none';
  }

  function hasAnyResourceBlock() {
    return document.querySelectorAll('.resource-block').length > 0;
  }

  function syncGeneralTitleRequiredState() {
    const titleInput = document.getElementById('general-resource-title');
    const requiredMarker = document.getElementById('general-resource-title-required');
    const isRequired = hasAnyResourceBlock();

    if (!titleInput || !requiredMarker) return isRequired;

    titleInput.required = isRequired;
    requiredMarker.style.display = isRequired ? 'inline' : 'none';

    if (!isRequired) {
      clearInlineError(titleInput);
    }

    return isRequired;
  }

  function validateGeneralTitle(input = document.getElementById('general-resource-title')) {
    if (!input) return true;

    const isRequired = syncGeneralTitleRequiredState();
    const value = input.value.trim();

    if (!isRequired) {
      clearInlineError(input);
      return true;
    }

    if (value.length === 0) {
      showInlineError(input, 'Ce champ est obligatoire');
      return false;
    }

    if (value.length < 6) {
      showInlineError(input, 'Le titre doit contenir au moins 6 caractères');
      return false;
    }

    clearInlineError(input);
    return true;
  }

  function validateResourceName(input) {
    if (!input) return true;

    const name = input.value.trim();
    if (name.length === 0) {
      showInlineError(input, 'Ce champ est obligatoire');
      return false;
    }

    if (name.length < 6) {
      showInlineError(input, 'Le titre doit contenir au moins 6 caractères');
      return false;
    }

    clearInlineError(input);
    return true;
  }

  function validateQuantityField(input) {
    if (!input) return true;

    const value = input.value.trim();
    if (value === '') {
      showInlineError(input, 'Ce champ est obligatoire');
      return false;
    }

    if (!/^\d+$/.test(value)) {
      showInlineError(input, 'La quantité doit être un nombre');
      return false;
    }

    clearInlineError(input);
    return true;
  }

  function createResourceBlock(type) {
    const block = document.createElement('div');
    block.className = 'resource-block';
    block.dataset.id = '0';

    const quantityField = type === 'materiel'
      ? `
        <div class="mb-3">
          <label class="form-label">Quantité <span class="required">*</span></label>
          <input type="text" class="field resource-quantity" value="" inputmode="numeric" required />
          <div class="error-message"></div>
        </div>`
      : '';

    block.innerHTML = `
      <div class="resource-block-header">
        <button type="button" class="btn outline remove-resource">−</button>
      </div>
      <div class="resource-fields">
        <div class="mb-3">
          <label class="form-label">Nom <span class="required">*</span></label>
          <input type="text" class="field resource-name" value="" />
          <div class="error-message"></div>
        </div>
        ${quantityField}
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="field resource-description" rows="2"></textarea>
        </div>
      </div>`;

    return block;
  }

  document.addEventListener('click', function (e) {
    const addBtn = e.target.closest('.add-resource');
    if (!addBtn) return;

    const type = addBtn.dataset.type;
    const listId = type === 'materiel' ? 'materiels-list' : 'regles-list';
    const list = document.getElementById(listId);
    if (!list) return;

    const block = createResourceBlock(type);
    list.appendChild(block);
    syncGeneralTitleRequiredState();

    const nameInput = block.querySelector('.resource-name');
    if (nameInput) nameInput.focus();
  });

  document.addEventListener('click', function (e) {
    const removeBtn = e.target.closest('.remove-resource');
    if (!removeBtn) return;

    const block = removeBtn.closest('.resource-block');
    if (block) {
      block.remove();
      validateGeneralTitle();
    }
  });

  document.addEventListener('input', function (e) {
    if (e.target.id === 'general-resource-title') {
      validateGeneralTitle(e.target);
    }

    if (e.target.classList.contains('resource-name')) {
      validateResourceName(e.target);
    }

    if (e.target.classList.contains('resource-quantity')) {
      validateQuantityField(e.target);
    }
  });

  document.getElementById('resources-form')?.addEventListener('submit', function (event) {
    event.preventDefault();
    document.getElementById('btn-save-resources')?.click();
  });

  function applyBackendResourceFieldErrors(generalTitleInput) {
    if (hasAnyResourceBlock() && !validateGeneralTitle(generalTitleInput)) {
      generalTitleInput?.focus();
      return true;
    }

    const resourceBlocks = Array.from(document.querySelectorAll('.resource-block'));
    for (const block of resourceBlocks) {
      const nameInput = block.querySelector('.resource-name');
      const quantityInput = block.querySelector('.resource-quantity');
      const isNameValid = validateResourceName(nameInput);
      const isQuantityValid = !quantityInput || validateQuantityField(quantityInput);

      if (!isNameValid) {
        nameInput?.focus();
        return true;
      }

      if (!isQuantityValid) {
        quantityInput?.focus();
        return true;
      }
    }

    return false;
  }

  document.getElementById('btn-save-resources')?.addEventListener('click', async function () {
    const eventId = Number(document.getElementById('event-id')?.value || 0);
    const generalTitleInput = document.getElementById('general-resource-title');
    const generalDescriptionInput = document.getElementById('general-resource-description');
    if (eventId <= 0) {
      showError('Identifiant événement invalide.');
      return;
    }

    const resources = [];
    let isValid = true;
    let firstInvalid = null;
    const materielsList = document.getElementById('materiels-list');
    const hasResourceBlocks = document.querySelectorAll('.resource-block').length > 0;

    // Valider le titre général seulement s'il y a des blocs de ressources
    if (hasResourceBlocks) {
      const isGeneralTitleValid = validateGeneralTitle(generalTitleInput);
      if (!isGeneralTitleValid) {
        isValid = false;
        firstInvalid = generalTitleInput;
      }
    }

    // Valider uniquement les blocs de ressources ouverts
    document.querySelectorAll('.resource-block').forEach(block => {
      const id = Number(block.dataset.id || 0);
      const nameInput = block.querySelector('.resource-name');
      const quantityInput = block.querySelector('.resource-quantity');
      const descInput = block.querySelector('.resource-description');

      const name = (nameInput?.value || '').trim();
      const quantity = (quantityInput?.value || '').trim();
      const description = (descInput?.value || '').trim();
      const type = materielsList?.contains(block) ? 'materiel' : 'regle';

      const isNameValid = validateResourceName(nameInput);
      const isQuantityValid = type !== 'materiel' || validateQuantityField(quantityInput);

      if (!isNameValid || !isQuantityValid) {
        isValid = false;
        if (!firstInvalid) {
          firstInvalid = !isNameValid ? nameInput : quantityInput;
        }
        return;
      }

      resources.push({
        id: id > 0 ? id : null,
        name: name,
        quantity: quantity,
        description: description,
        type: type
      });
    });

    if (!isValid) {
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    // Les ressources sont maintenant facultatives - on peut sauvegarder sans ressources
    // Si aucune ressource, on sauvegarde quand même (éventuellement avec titre/description vide)

    const result = await postJson('resources.php?action=save_resources', {
      event_id: eventId,
      resources_title: (generalTitleInput?.value || '').trim(),
      resources_description: (generalDescriptionInput?.value || '').trim(),
      resources: resources
    });

    if (result?.success) {
      showSuccess(result.message || 'Ressources enregistrées avec succès.');
      setTimeout(() => { window.location.href = 'events.php'; }, 2000);
    } else {
      if (applyBackendResourceFieldErrors(generalTitleInput)) {
        return;
      }
      showError(result?.message || 'Erreur lors de l\'enregistrement des ressources.');
    }
  });

  syncGeneralTitleRequiredState();
});
