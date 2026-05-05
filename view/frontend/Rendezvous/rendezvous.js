// Fonction pour valider un champ individuellement
function validateField(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(fieldId + '-error') || document.getElementById(fieldId.replace('_rdv', '') + '-error');
    
    if (!field || !errorElement) return;

    let isFieldValid = true;
    let errorMessage = "";

    if (fieldId === 'service' || fieldId === 'assistant') {
        if (!field.value || field.value === "") {
            isFieldValid = false;
            errorMessage = "Veuillez faire un choix.";
        }
    } else if (fieldId === 'date_rdv') {
        if (!field.value) {
            isFieldValid = false;
            errorMessage = "Veuillez choisir une date.";
        } else {
            const selectedDate = new Date(field.value);
            const today = new Date();
            today.setHours(0,0,0,0);
            if (selectedDate < today) {
                isFieldValid = false;
                errorMessage = "La date ne peut pas être dans le passé.";
            }
        }
    } else if (fieldId === 'heure_rdv') {
        if (!field.value) {
            isFieldValid = false;
            errorMessage = "Veuillez choisir une heure.";
        } else {
            const hParts = field.value.split(':');
            const h = parseInt(hParts[0]);
            const m = parseInt(hParts[1]);
            const timeInMinutes = h * 60 + m;
            const minTime = 8 * 60 + 10;
            const maxTime = 17 * 60 + 30;
            if (timeInMinutes < minTime || timeInMinutes > maxTime) {
                isFieldValid = false;
                errorMessage = "L'heure doit être comprise entre 08:10 et 17:30.";
            }
        }
    } else if (fieldId === 'remarques') {
        const words = field.value.trim().match(/\S+/g) || [];
        const wordCount = words.length;
        if (field.value.trim() === "") {
            isFieldValid = false;
            errorMessage = "Ce champ est obligatoire.";
        } else if (wordCount > 50) {
            isFieldValid = false;
            errorMessage = "Les remarques ne doivent pas dépasser 50 mots (actuellement : " + wordCount + " mots).";
        }
    }

    if (isFieldValid) {
        errorElement.style.display = 'none';
        field.classList.remove('invalid');
    } else {
        errorElement.textContent = errorMessage;
        errorElement.style.display = 'block';
        field.classList.add('invalid');
    }
    return isFieldValid;
}

// Ajouter des écouteurs d'événements pour la validation en temps réel
['service', 'assistant', 'date_rdv', 'heure_rdv', 'remarques'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', () => validateField(id));
        el.addEventListener('change', () => validateField(id));
    }
});

const rdvForm = document.getElementById('rdvForm');
if (rdvForm) {
    rdvForm.addEventListener('submit', function(e) {
        let isValid = true;
        
        ['service', 'assistant', 'date_rdv', 'heure_rdv', 'remarques'].forEach(id => {
            if (!validateField(id)) isValid = false;
        });

        if (!isValid) {
            e.preventDefault();
            const firstInvalid = document.querySelector('.field.invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
}

window.confirmCancel = function(id) {
    console.log("confirmCancel appelé - ID:", id);
    
    if (typeof Swal === 'undefined') {
        console.error("SweetAlert2 n'est pas chargé !");
        if (confirm("Êtes-vous sûr de vouloir annuler ce rendez-vous ?")) {
            window.location.href = 'cancelRendezvous.php?cancel=' + id;
        }
        return;
    }

    Swal.fire({
        title: 'Annuler le rendez-vous ?',
        text: "Êtes-vous sûr de vouloir annuler ce rendez-vous ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, annuler',
        cancelButtonText: 'Retour',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Confirmation d'annulation reçue, redirection vers cancelRendezvous.php");
            window.location.href = 'cancelRendezvous.php?cancel=' + id;
        }
    });
}

window.confirmDelete = function(id) {
    console.log("confirmDelete appelé - ID:", id);
    
    if (typeof Swal === 'undefined') {
        console.error("SweetAlert2 n'est pas chargé !");
        if (confirm("Êtes-vous sûr de vouloir supprimer cet historique ? Cette action est irréversible.")) {
            window.location.href = 'deleteRendezvous.php?delete=' + id;
        }
        return;
    }

    Swal.fire({
        title: 'Supprimer l\'historique ?',
        text: "Cette action est irréversible !",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Confirmation de suppression reçue, redirection vers deleteRendezvous.php");
            window.location.href = 'deleteRendezvous.php?delete=' + id;
        }
    });
}
