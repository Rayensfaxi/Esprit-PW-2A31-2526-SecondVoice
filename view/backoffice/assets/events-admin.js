document.addEventListener('DOMContentLoaded', function () {
  function $(sel, ctx = document) { return ctx.querySelector(sel); }
  function $all(sel, ctx = document) { return Array.from((ctx || document).querySelectorAll(sel)); }

  const apiBase = '../frontoffice/events.php';
  const feedback = $('#admin-feedback');
  const modal = $('#registrants-modal');
  const registrantsList = $('#registrants-list');
  const exportRegistrantsPdf = $('#export-registrants-pdf');
  const exportPdfButtons = $all('.export-pdf-btn');
  const requestDetailsModal = $('#request-details-modal');
  const requestDetailsContent = $('#request-details-content');
  const qrModal = $('#qr-modal');
  const qrImage = $('#qr-code-image');
  const qrEventName = $('#qr-event-name');
  const qrEventMeta = $('#qr-event-meta');
  const qrDownloadLink = $('#qr-download-link');
  const qrEncodedText = $('#qr-encoded-text');

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

  function openRequestDetailsModal() {
    requestDetailsModal?.setAttribute('aria-hidden', 'false');
    requestDetailsModal?.classList.add('open');
  }

  function closeRequestDetailsModal() {
    requestDetailsModal?.setAttribute('aria-hidden', 'true');
    requestDetailsModal?.classList.remove('open');
  }

  async function openQrModal(card, eventId) {
    if (!qrModal || !qrImage || !eventId) return;

    const name = card?.dataset.name || card?.querySelector('.evt-name')?.textContent || 'Evenement';
    const start = card?.dataset.start || '';
    const location = card?.dataset.location || '';

    if (qrEventName) qrEventName.textContent = name;
    if (qrEventMeta) qrEventMeta.textContent = [start, location].filter(Boolean).join(' - ');

    const qrUrl = apiBase + '?action=qr&event_id=' + encodeURIComponent(String(eventId));
    qrImage.src = qrUrl;
    if (qrDownloadLink) {
      qrDownloadLink.href = apiBase + '?action=qr_download&event_id=' + encodeURIComponent(String(eventId));
      qrDownloadLink.setAttribute('download', 'event-' + eventId + '-qr.svg');
    }
    if (qrEncodedText) {
      qrEncodedText.textContent = 'Chargement de l URL...';
      try {
        const response = await fetch(apiBase + '?action=qr_payload&event_id=' + encodeURIComponent(String(eventId)));
        const result = await response.json();
        qrEncodedText.textContent = result?.payload || '';
      } catch (error) {
        qrEncodedText.textContent = '';
      }
    }

    qrModal.setAttribute('aria-hidden', 'false');
    qrModal.classList.add('open');
  }

  function closeQrModal() {
    qrModal?.setAttribute('aria-hidden', 'true');
    qrModal?.classList.remove('open');
    if (qrImage) qrImage.src = '';
    if (qrEncodedText) qrEncodedText.textContent = '';
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char];
    });
  }

  function detailValue(value) {
    const text = String(value ?? '').trim();
    return text ? escapeHtml(text) : '<span class="request-detail-empty">Non renseigné</span>';
  }

  function renderDetailField(label, value) {
    return '<div class="request-detail-field"><span>' + escapeHtml(label) + '</span><strong>' + detailValue(value) + '</strong></div>';
  }

  function renderResources(resources) {
    const items = Array.isArray(resources) ? resources : [];
    const materials = items.filter(function (item) { return String(item.type || '').toLowerCase() === 'materiel'; });
    const rules = items.filter(function (item) { return String(item.type || '').toLowerCase() === 'regle'; });

    if (!materials.length && !rules.length) {
      return '<div class="request-detail-empty-state">Aucune ressource</div>';
    }

    function renderResourceList(title, list, isRule) {
      if (!list.length) return '';
      return '<div class="request-resource-group"><h5>' + escapeHtml(title) + '</h5>' + list.map(function (resource) {
        const name = resource.name || resource.title || 'Sans titre';
        const quantity = resource.quantity || resource.quantite || '';
        const description = resource.description || '';
        return '<div class="request-resource-item">'
          + '<div><strong>' + detailValue(name) + '</strong>'
          + (isRule || !quantity ? '' : '<span>Quantité : ' + detailValue(quantity) + '</span>')
          + '</div>'
          + '<p>' + detailValue(description) + '</p>'
          + '</div>';
      }).join('') + '</div>';
    }

    return renderResourceList('Matériels', materials, false) + renderResourceList('Règles', rules, true);
  }

  function renderRequestDetails(details) {
    if (!requestDetailsContent) return;
    const user = details.user || {};
    requestDetailsContent.innerHTML = ''
      + '<div class="request-details-grid">'
      + '<section class="request-detail-section"><h4>Événement</h4>'
      + renderDetailField('Nom événement', details.name)
      + renderDetailField('Description', details.description)
      + renderDetailField('Date début', details.start_date)
      + renderDetailField('Date fin', details.end_date)
      + renderDetailField('Date limite', details.deadline)
      + renderDetailField('Lieu', details.location)
      + renderDetailField('Capacité max', details.max)
      + renderDetailField('Statut demande', details.status)
      + renderDetailField('Type demande', details.request_type)
      + renderDetailField('Date création demande', details.created_at)
      + '</section>'
      + '<section class="request-detail-section"><h4>Utilisateur</h4>'
      + renderDetailField('Nom utilisateur', user.nom)
      + renderDetailField('Prénom utilisateur', user.prenom)
      + renderDetailField('Email', user.email)
      + renderDetailField('Téléphone', user.telephone)
      + '</section>'
      + '</div>'
      + '<section class="request-detail-section request-detail-resources"><h4>Ressources</h4>'
      + renderResources(details.resources)
      + '</section>';
  }

    $('#admin-reset')?.addEventListener('click', resetForm);
  $all('#registrants-modal .modal-close').forEach(function (button) { button.addEventListener('click', closeModal); });
  $all('#request-details-modal .modal-close').forEach(function (button) { button.addEventListener('click', closeRequestDetailsModal); });
  $all('#qr-modal .modal-close').forEach(function (button) { button.addEventListener('click', closeQrModal); });

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
  const adminFilterDate = document.getElementById('admin-filter-date');
  const adminFilterLocation = document.getElementById('admin-filter-location');
  const adminFilterAvailability = document.getElementById('admin-filter-availability');
  const adminFilterResources = document.getElementById('admin-filter-resources');
  const adminFilterReset = document.getElementById('admin-filter-reset');

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

  function parseAdminCardJson(value) {
    try {
      const parsed = JSON.parse(value || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function getAdminDayBounds(date) {
    const start = new Date(date);
    start.setHours(0, 0, 0, 0);
    const end = new Date(start);
    end.setDate(end.getDate() + 1);
    return { start, end };
  }

  function getAdminWeekBounds(date) {
    const start = new Date(date);
    start.setHours(0, 0, 0, 0);
    const day = start.getDay() || 7;
    start.setDate(start.getDate() - day + 1);
    const end = new Date(start);
    end.setDate(end.getDate() + 7);
    return { start, end };
  }

  function getAdminMonthBounds(date) {
    const start = new Date(date.getFullYear(), date.getMonth(), 1);
    const end = new Date(date.getFullYear(), date.getMonth() + 1, 1);
    return { start, end };
  }

  function adminDateRangesOverlap(startDate, endDate, rangeStart, rangeEnd) {
    const eventStart = startDate instanceof Date && !Number.isNaN(startDate.getTime()) ? startDate : null;
    const eventEnd = endDate instanceof Date && !Number.isNaN(endDate.getTime()) ? endDate : eventStart;
    return !!eventStart && eventStart < rangeEnd && eventEnd >= rangeStart;
  }

  function matchesAdminDateFilter(card, filterValue) {
    if (!filterValue) return true;

    const now = new Date();
    const startDate = new Date(String(card.dataset.start || '').replace(' ', 'T'));
    const endDate = new Date(String(card.dataset.end || card.dataset.start || '').replace(' ', 'T'));
    const eventStart = !Number.isNaN(startDate.getTime()) ? startDate : null;
    const eventEnd = !Number.isNaN(endDate.getTime()) ? endDate : eventStart;

    if (!eventStart) return false;
    if (filterValue === 'upcoming') return eventEnd >= now;
    if (filterValue === 'past') return eventEnd < now;
    if (filterValue === 'today') {
      const range = getAdminDayBounds(now);
      return adminDateRangesOverlap(eventStart, eventEnd, range.start, range.end);
    }
    if (filterValue === 'week') {
      const range = getAdminWeekBounds(now);
      return adminDateRangesOverlap(eventStart, eventEnd, range.start, range.end);
    }
    if (filterValue === 'month') {
      const range = getAdminMonthBounds(now);
      return adminDateRangesOverlap(eventStart, eventEnd, range.start, range.end);
    }

    return true;
  }

  function matchesAdminAvailabilityFilter(card, filterValue) {
    if (!filterValue) return true;

    const max = Number(card.dataset.max || 0);
    const current = Number(card.dataset.current || 0);
    const isFull = max > 0 && current >= max;

    if (filterValue === 'available') return !isFull;
    if (filterValue === 'full') return isFull;

    return true;
  }

  function matchesAdminResourcesFilter(card, filterValue) {
    if (!filterValue) return true;

    const materials = parseAdminCardJson(card.getAttribute('data-materials'));
    const rules = parseAdminCardJson(card.getAttribute('data-rules'));

    if (filterValue === 'with-materials') return materials.length > 0;
    if (filterValue === 'without-materials') return materials.length === 0;
    if (filterValue === 'with-rules') return rules.length > 0;
    if (filterValue === 'without-rules') return rules.length === 0;

    return true;
  }

  function hasActiveAdminFilters() {
    return !!(
      adminFilterDate?.value ||
      adminFilterLocation?.value.trim() ||
      adminFilterAvailability?.value ||
      adminFilterResources?.value
    );
  }

  function matchesAdminEventFilters(card) {
    const locationQuery = normalizeSearchValue(adminFilterLocation?.value || '');
    const location = normalizeSearchValue(card.dataset.location || '');

    return matchesAdminDateFilter(card, adminFilterDate?.value || '')
      && (locationQuery === '' || location.includes(locationQuery))
      && matchesAdminAvailabilityFilter(card, adminFilterAvailability?.value || '')
      && matchesAdminResourcesFilter(card, adminFilterResources?.value || '');
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

    const filterableScope = scope.id === 'tab-events';
    const filtersActive = filterableScope && hasActiveAdminFilters();

    cards.forEach(function(card) {
      const matchesSearch = query === '' || normalizeSearchValue(getAdminCardSearchText(card)).includes(query);
      const matchesFilters = !filterableScope || matchesAdminEventFilters(card);
      const matches = matchesSearch && matchesFilters;
      card.style.display = matches ? '' : 'none';
      if (matches) visibleCount += 1;
    });

    const emptyMessage = getAdminSearchEmptyMessage(scope);
    emptyMessage.textContent = filterableScope ? 'Aucun événement trouvé' : 'Aucun résultat trouvé';
    emptyMessage.hidden = (query === '' && !filtersActive) || visibleCount > 0 || cards.length === 0;
  }

  adminSearchInput?.addEventListener('input', applyAdminSearch);
  [adminFilterDate, adminFilterLocation, adminFilterAvailability, adminFilterResources].forEach(function(control) {
    control?.addEventListener('input', applyAdminSearch);
    control?.addEventListener('change', applyAdminSearch);
  });

  adminFilterReset?.addEventListener('click', function() {
    if (adminFilterDate) adminFilterDate.value = '';
    if (adminFilterLocation) adminFilterLocation.value = '';
    if (adminFilterAvailability) adminFilterAvailability.value = '';
    if (adminFilterResources) adminFilterResources.value = '';
    applyAdminSearch();
  });

  applyAdminSearch();

  exportPdfButtons.forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();

      const eventId = this.dataset.eventId || '';
      console.log('Export PDF cliqué', eventId);

      if (!eventId || Number(eventId) <= 0) {
        showFeedback('Aucun evenement selectionne pour exporter le PDF.', true);
        return;
      }

      const url = 'export_inscrits_pdf.php?event_id=' + encodeURIComponent(String(eventId));
      console.log('URL export PDF', url);
      showSuccess('PDF exporté avec succès');
      window.location.href = url;
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
      const eventId = Number(card?.getAttribute('data-id') || this.getAttribute('data-id') || 0);
      if (exportRegistrantsPdf) {
        exportRegistrantsPdf.dataset.eventId = eventId > 0 ? String(eventId) : '';
        exportRegistrantsPdf.style.display = eventId > 0 ? '' : 'none';
      }

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

  $all('.view-qr-code').forEach(function (button) {
    button.addEventListener('click', function () {
      const card = this.closest('.event-card');
      const eventId = Number(card?.getAttribute('data-id') || this.getAttribute('data-id') || 0);
      openQrModal(card, eventId);
    });
  });

  $all('.request-details').forEach(function (button) {
    button.addEventListener('click', function () {
      const card = this.closest('.event-card');
      if (!card) return;

      let details = {};
      try {
        details = JSON.parse(card.getAttribute('data-details') || '{}');
      } catch (error) {
        details = {};
      }

      renderRequestDetails(details);
      openRequestDetailsModal();
    });
  });

  [modal, requestDetailsModal, qrModal].forEach(function (item) {
    item?.addEventListener('click', function (event) {
      if (event.target !== item) return;
      if (item === modal) closeModal();
      if (item === requestDetailsModal) closeRequestDetailsModal();
      if (item === qrModal) closeQrModal();
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

  let isSavingAdminEvent = false;

  // Validation and save handler
  async function handleAdminEventSave(event) {
    event?.preventDefault();
    console.log('clic enregistrer événement admin');

    if (isSavingAdminEvent) return;
    isSavingAdminEvent = true;
    const saveButton = $('#admin-save');
    if (saveButton) saveButton.disabled = true;

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
      isSavingAdminEvent = false;
      if (saveButton) saveButton.disabled = false;
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
    let result;
    try {
      result = await postJson(url, payload);
    } catch (error) {
      showFeedback("Erreur lors de l'enregistrement: " + error.message, true);
      isSavingAdminEvent = false;
      if (saveButton) saveButton.disabled = false;
      return;
    }

    if (result?.success) {
      if (!isUpdate) {
        const eventId = Number(result.id || 0);
        if (eventId > 0) {
          showSuccess('Événement ajouté. Redirection vers la gestion des ressources...');
          setTimeout(() => {
            window.location.href = '../frontoffice/resources.php?event_id=' + encodeURIComponent(String(eventId));
          }, 1200);
          return;
        }

        showFeedback('Événement ajouté, mais identifiant introuvable pour ajouter les ressources.', true);
        isSavingAdminEvent = false;
        if (saveButton) saveButton.disabled = false;
        return;
      }

      showSuccess('Événement modifié avec succès');
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showFeedback(result?.message || "Erreur lors de l'enregistrement.", true);
      isSavingAdminEvent = false;
      if (saveButton) saveButton.disabled = false;
    }
  }

  $('#admin-event-form')?.addEventListener('submit', function (event) {
    handleAdminEventSave(event);
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
      const requestType = this.getAttribute('data-request-type') || 'modify';
      const isDeleteRequest = requestType === 'delete';
      const confirmMessage = isDeleteRequest
        ? 'Êtes-vous sûr de vouloir approuver cette suppression des ressources ? Toutes les ressources de cet événement seront supprimées.'
        : 'Êtes-vous sûr de vouloir approuver cette modification des ressources ? Les nouvelles ressources remplaceront les anciennes.';

      showConfirm(confirmMessage, async function() {
        const result = await postJson(apiBase + '?action=approve_resource_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess(isDeleteRequest ? 'Suppression des ressources approuvée' : 'Modification des ressources approuvée');
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
      const requestType = this.getAttribute('data-request-type') || 'modify';
      const isDeleteRequest = requestType === 'delete';
      const confirmMessage = isDeleteRequest
        ? 'Êtes-vous sûr de vouloir refuser cette suppression des ressources ? Les ressources actuelles seront conservées.'
        : 'Êtes-vous sûr de vouloir refuser cette modification des ressources ? Les ressources actuelles seront conservées.';

      showConfirm(confirmMessage, async function() {
        const result = await postJson(apiBase + '?action=reject_resource_modification', { request_id: requestId });
        if (result?.success) {
          showSuccess(isDeleteRequest ? 'Suppression des ressources refusée' : 'Modification des ressources refusée');
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showFeedback(result?.message || 'Erreur lors du refus.', true);
        }
      });
    });
  });
});
