console.log("rendezvous.js injecté avec succès");

window.filterAppointments = function() {
  const searchInput = document.getElementById('appointmentSearch');
  const filterSearch = searchInput.value.toLowerCase();
  const statusSelect = document.getElementById('statusFilter');
  const filterStatus = statusSelect.value.toLowerCase();
  
  const table = document.querySelector('.users-table');
  const tr = table.getElementsByTagName('tr');

  for (let i = 1; i < tr.length; i++) {
    const citizenCell = tr[i].getElementsByTagName('td')[0];
    const serviceCell = tr[i].getElementsByTagName('td')[1];
    const statusCell = tr[i].getElementsByTagName('td')[4];
    
    let matchSearch = false;
    let matchStatus = false;

    if (citizenCell || serviceCell) {
      const citizenText = (citizenCell ? (citizenCell.textContent || citizenCell.innerText) : "").toLowerCase();
      const serviceText = (serviceCell ? (serviceCell.textContent || serviceCell.innerText) : "").toLowerCase();
      if (citizenText.indexOf(filterSearch) > -1 || serviceText.indexOf(filterSearch) > -1) {
        matchSearch = true;
      }
    }

    if (statusCell) {
      const statusText = (statusCell.textContent || statusCell.innerText).trim().toLowerCase();
      if (filterStatus === "" || statusText === filterStatus) {
        matchStatus = true;
      }
    }

    if (matchSearch && matchStatus) {
      tr[i].style.display = "";
    } else {
      tr[i].style.display = "none";
    }
  }
}

window.toggleStatus = function(id, currentStatus) {
    console.log("toggleStatus appelé - ID:", id, "Statut actuel:", currentStatus);
    
    if (currentStatus === 'Annulé') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Action impossible',
                text: "Impossible de modifier le statut d'un rendez-vous annulé.",
                confirmButtonColor: '#1fb47a'
            });
        } else {
            alert("Impossible de modifier le statut d'un rendez-vous annulé.");
        }
        return;
    }

    let nextStatus = currentStatus === 'Confirmé' ? 'wait' : 'confirm';
    let statusLabel = currentStatus === 'Confirmé' ? 'mettre en attente' : 'confirmer';
    
    if (typeof Swal === 'undefined') {
        console.error("SweetAlert2 n'est pas chargé !");
        if(confirm("Voulez-vous " + statusLabel + " ce rendez-vous ?")) {
            window.location.href = 'toggleStatutRendezvous.php?action=' + nextStatus + '&id=' + id;
        }
        return;
    }

    Swal.fire({
        title: 'Changer le statut ?',
        text: "Voulez-vous " + statusLabel + " ce rendez-vous ?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1fb47a',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, changer',
        cancelButtonText: 'Annuler',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Confirmation reçue, redirection vers toggleStatutRendezvous.php");
            window.location.href = 'toggleStatutRendezvous.php?action=' + nextStatus + '&id=' + id;
        }
    });
}

let currentRdv = null;

window.openDetails = function(rdv) {
  currentRdv = rdv;
  document.getElementById('det-citizen').textContent = 'Citoyen #' + rdv.id_citoyen;
  document.getElementById('det-service').textContent = rdv.service;
  document.getElementById('det-assistant').textContent = rdv.assistant;
  document.getElementById('det-datetime').textContent = rdv.date_rdv + ' à ' + rdv.heure_rdv;
  document.getElementById('det-mode').textContent = rdv.mode;
  document.getElementById('det-status').textContent = rdv.statut;
  document.getElementById('det-notes').textContent = rdv.remarques;
  
  // Génération du QR Code via AJAX
  const qrSection = document.getElementById('qr-section');
  const qrImg = document.getElementById('det-qrcode');
  const qrDownload = document.getElementById('btn-download-qr');
  const qrError = document.getElementById('qr-error');

  if (qrSection) {
    qrSection.style.display = 'block';
    qrImg.style.display = 'none';
    if (qrError) qrError.style.display = 'none';
    
    fetch('generateQR.php?id=' + rdv.id)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          qrImg.src = data.qrPath + '?t=' + new Date().getTime(); // Anti-cache
          qrImg.style.display = 'block';
          qrDownload.href = data.qrPath;
          qrDownload.style.display = 'inline-block';
        } else {
          console.error("QR Code error:", data.message);
          qrImg.style.display = 'none';
          qrDownload.style.display = 'none';
          if (qrError) {
            qrError.textContent = data.message;
            qrError.style.display = 'block';
          }
        }
      })
      .catch(err => {
        console.error("Fetch error:", err);
        if (qrError) {
          qrError.textContent = "Erreur de connexion au serveur.";
          qrError.style.display = 'block';
        }
      });
  }

  document.getElementById('detailsModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

window.closeDetails = function() {
  document.getElementById('detailsModal').classList.remove('active');
  document.body.style.overflow = '';
}

window.editRdv = function(rdv) {
    document.getElementById('edit-id').value = rdv.id;
    document.getElementById('edit-id-citoyen').value = rdv.id_citoyen;
    document.getElementById('edit-service').value = rdv.service_id;
    document.getElementById('edit-assistant').value = rdv.assistant;
    document.getElementById('edit-date').value = rdv.date_rdv;
    document.getElementById('edit-heure').value = rdv.heure_rdv;
    document.getElementById('edit-mode').value = rdv.mode;
    document.getElementById('edit-statut').value = rdv.statut;
    document.getElementById('edit-remarques').value = rdv.remarques;
    
    document.querySelectorAll('.js-error').forEach(el => el.style.display = 'none');
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

window.closeEditModal = function() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = '';
}

function validateEditField(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(fieldId + '-error');
    if (!field || !errorElement) return;

    let isValid = true;
    let errorMsg = "";

    if (fieldId === 'edit-service' || fieldId === 'edit-assistant') {
        if (!field.value) { isValid = false; errorMsg = "Veuillez choisir un choix."; }
    } else if (fieldId === 'edit-date') {
        if (!field.value) { isValid = false; errorMsg = "Date invalide."; }
    } else if (fieldId === 'edit-heure') {
        if (!field.value) {
            isValid = false; errorMsg = "Veuillez choisir une heure.";
        } else {
            const hParts = field.value.split(':');
            const timeInMinutes = parseInt(hParts[0]) * 60 + parseInt(hParts[1]);
            if (timeInMinutes < (8 * 60 + 10) || timeInMinutes > (17 * 60 + 30)) {
                isValid = false; errorMsg = "L'heure doit être entre 08:10 et 17:30.";
            }
        }
    } else if (fieldId === 'edit-remarques') {
        const words = field.value.trim().match(/\S+/g) || [];
        const wordCount = words.length;
        if (field.value.trim() === "") {
            isValid = false; errorMsg = "Ce champ est obligatoire.";
        } else if (wordCount > 50) {
            isValid = false; errorMsg = "Max 50 mots (actuellement : " + wordCount + " mots).";
        }
    }

    if (isValid) {
        errorElement.style.display = 'none';
    } else {
        errorElement.textContent = errorMsg;
        errorElement.style.display = 'block';
    }
    return isValid;
}

['edit-service', 'edit-assistant', 'edit-date', 'edit-heure', 'edit-remarques'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', () => validateEditField(id));
        el.addEventListener('change', () => validateEditField(id));
    }
});

if (document.getElementById('editForm')) {
    document.getElementById('editForm').addEventListener('submit', function(e) {
        let isValidForm = true;
        ['edit-service', 'edit-assistant', 'edit-date', 'edit-heure', 'edit-remarques'].forEach(id => {
            if (!validateEditField(id)) isValidForm = false;
        });

        if (!isValidForm) e.preventDefault();
    });
}

window.confirmDelete = function(id) {
    console.log("confirmDelete appelé - ID:", id);
    
    if (typeof Swal === 'undefined') {
        console.error("SweetAlert2 n'est pas chargé !");
        if(confirm("Êtes-vous sûr de vouloir supprimer ce rendez-vous ? Cette action est irréversible.")) {
            window.location.href = 'deleteRendezvous.php?id=' + id;
        }
        return;
    }

    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, supprimer !',
        cancelButtonText: 'Annuler',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Confirmation de suppression reçue, redirection vers deleteRendezvous.php");
            window.location.href = 'deleteRendezvous.php?id=' + id;
        }
    });
}

window.onclick = function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    closeDetails();
    closeEditModal();
  }
};
