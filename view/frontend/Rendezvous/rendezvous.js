console.log("--- CHARGEMENT DE RENDEZVOUS.JS ---");

// Fonction pour valider un champ individuellement
function validateField(fieldId) {
    console.log("Validation du champ:", fieldId);
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(fieldId + '-error');
    
    if (!field) {
        console.warn("Champ non trouvé:", fieldId);
        return true;
    }
    if (!errorElement) {
        console.warn("Élément d'erreur non trouvé pour:", fieldId);
        return true;
    }

    let isFieldValid = true;
    let errorMessage = "";

    if (fieldId === 'service_id' || fieldId === 'assistant') {
        // Si le champ est désactivé, il est considéré comme valide (car il a une valeur prédéfinie et ne peut pas être changé)
        if (field.disabled) {
            isFieldValid = true;
        } else if (!field.value || field.value === "" || field.value === "null") {
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
['service_id', 'assistant', 'date_rdv', 'heure_rdv', 'remarques'].forEach(id => {
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
        
        ['service_id', 'assistant', 'date_rdv', 'heure_rdv', 'remarques'].forEach(id => {
            if (!validateField(id)) isValid = false;
        });

        console.log("Validation terminée. isValid:", isValid);

        if (!isValid) {
            e.preventDefault();
            console.log("Formulaire invalide, arrêt de la soumission.");
            const firstInvalid = document.querySelector('.field.invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            console.log("Formulaire valide, soumission en cours...");
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

// Fonction pour filtrer et trier les rendez-vous via AJAX
let debounceTimer;
window.filterAppointments = function() {
    console.log("--- Début filterAppointments() (AJAX) ---");
    const searchInput = document.getElementById('searchRdv');
    const statusSelect = document.getElementById('filterStatus');
    const sortSelect = document.getElementById('sortRdv');
    const listContainer = document.getElementById('rendezvousList');

    if (!listContainer) {
        console.error("ERREUR: Conteneur #rendezvousList non trouvé dans le DOM");
        return;
    }

    const search = searchInput ? searchInput.value.trim() : '';
    const status = statusSelect ? statusSelect.value : '';
    const sort = sortSelect ? sortSelect.value : 'date_desc';
    
    console.log("Paramètres envoyés:", {search, status, sort});
    
    // Effet visuel de chargement
    listContainer.style.opacity = '0.5';
    listContainer.style.transition = 'opacity 0.2s ease-in-out';

    // Construction de l'URL avec les paramètres
    const url = `get_rendezvous_list.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&sort=${encodeURIComponent(sort)}`;
    console.log("Appel fetch vers URL:", url);

    fetch(url)
        .then(response => {
            console.log("Réponse reçue du serveur, statut:", response.status);
            if (!response.ok) throw new Error('Erreur réseau lors de la récupération des données');
            return response.text();
        })
        .then(html => {
            console.log("Données HTML reçues (taille: " + html.length + " caractères)");
            listContainer.innerHTML = html;
            listContainer.style.opacity = '1';
            console.log("Mise à jour du DOM réussie sans rechargement de page");
        })
        .catch(error => {
            console.error("ERREUR lors de la recherche AJAX:", error);
            listContainer.style.opacity = '1';
            listContainer.innerHTML = '<div class="error-message">Une erreur est survenue lors de la recherche. Veuillez réessayer.</div>';
        });
}

window.resetFilters = function() {
    console.log("resetFilters appelé !");
    const searchInput = document.getElementById('searchRdv');
    const statusSelect = document.getElementById('filterStatus');
    const sortSelect = document.getElementById('sortRdv');

    if (searchInput) searchInput.value = '';
    if (statusSelect) statusSelect.value = '';
    if (sortSelect) sortSelect.value = 'date_desc';

    window.filterAppointments();
}

// Initialisation globale
function initRendezvousFeatures() {
    console.log("Exécution de initRendezvousFeatures()");
    
    const searchInput = document.getElementById('searchRdv');
    const statusSelect = document.getElementById('filterStatus');
    const sortSelect = document.getElementById('sortRdv');
    
    console.log("Éléments trouvés:", {
        search: !!searchInput,
        status: !!statusSelect,
        sort: !!sortSelect
    });
    
    // Écouteurs pour la recherche et les filtres
    if (searchInput) {
        console.log("Attachement des événements à #searchRdv");
        // Empêcher tout rechargement sur Enter
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                console.log("Touche Enter détectée");
                e.preventDefault();
                clearTimeout(debounceTimer);
                window.filterAppointments();
            }
        });

        // Recherche dynamique en temps réel (input event)
        searchInput.addEventListener('input', function() {
            console.log("Saisie: " + this.value);
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                window.filterAppointments();
            }, 500);
        });
    }

    // Changement de statut ou tri
    if (statusSelect) {
        statusSelect.addEventListener('change', () => {
            console.log("Changement statut -> filtrage");
            window.filterAppointments();
        });
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            console.log("Changement tri -> filtrage");
            window.filterAppointments();
        });
    }
}

// Lancement de l'initialisation
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    console.log("DOM déjà prêt, initialisation immédiate.");
    initRendezvousFeatures();
} else {
    console.log("Attente de DOMContentLoaded pour l'initialisation.");
    document.addEventListener('DOMContentLoaded', initRendezvousFeatures);
}

console.log("--- RENDEZVOUS.JS PRÊT ET INJECTÉ ---");
