console.log("--- CHARGEMENT DE SERVICE.JS ---");

// Fonction pour filtrer les services via AJAX
let debounceTimer;
window.filterServices = function() {
    console.log("--- Début filterServices() (AJAX) ---");
    const searchInput = document.getElementById('serviceSearch');
    const tableBody = document.getElementById('serviceTableBody');

    if (!tableBody) {
        console.error("ERREUR: Conteneur #serviceTableBody non trouvé dans le DOM");
        return;
    }

    const search = searchInput ? searchInput.value.trim() : '';
    console.log("Recherche en cours pour:", search);
    
    tableBody.style.opacity = '0.5';
    tableBody.style.transition = 'opacity 0.2s ease-in-out';

    const url = `get_service_list.php?search=${encodeURIComponent(search)}`;
    console.log("Appel fetch vers URL:", url);

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Erreur réseau lors de la récupération des données');
            return response.text();
        })
        .then(html => {
            tableBody.innerHTML = html;
            tableBody.style.opacity = '1';
            console.log("Mise à jour du tableau réussie");
        })
        .catch(error => {
            console.error("ERREUR lors de la recherche AJAX:", error);
            tableBody.style.opacity = '1';
        });
}

// Fonctions de gestion de la modale et des actions
window.confirmDeleteService = function(id) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui, supprimer !',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'deleteService.php?id=' + id;
        }
    });
}

window.openAddModal = function() {
    if (window.resetServiceFormErrors) window.resetServiceFormErrors();
    document.getElementById('serviceForm').action = 'addService.php';
    document.getElementById('service_id').value = '';
    document.getElementById('nom').value = '';
    document.getElementById('description').value = '';
    document.getElementById('modalTitle').innerText = 'Ajouter un service';
    document.getElementById('serviceModal').classList.add('active');
}

window.editService = function(service) {
    if (window.resetServiceFormErrors) window.resetServiceFormErrors();
    document.getElementById('serviceForm').action = 'updateService.php';
    document.getElementById('service_id').value = service.id;
    document.getElementById('nom').value = service.nom;
    document.getElementById('description').value = service.description;
    document.getElementById('modalTitle').innerText = 'Modifier le service';
    document.getElementById('serviceModal').classList.add('active');
}

window.closeServiceModal = function() {
    document.getElementById('serviceModal').classList.remove('active');
}

// Initialisation globale
function initServiceFeatures() {
    console.log("Exécution de initServiceFeatures()");
    
    const searchInput = document.getElementById('serviceSearch');
    const serviceForm = document.getElementById('serviceForm');
    
    // Recherche dynamique
    if (searchInput) {
        console.log("Écouteur attaché à #serviceSearch");
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                window.filterServices();
            }, 500);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                window.filterServices();
            }
        });
    }

    // Validation du formulaire (migré de service_validation.js)
    function validateField(id) {
        const field = document.getElementById(id);
        const errorElement = document.getElementById(id + '-error');
        if (!field || !errorElement) return true;

        let isValid = true;
        let message = '';
        const value = field.value.trim();

        if (id === 'nom') {
            if (value === '') {
                isValid = false;
                message = 'Le nom du service est obligatoire.';
            } else if (value.length < 3) {
                isValid = false;
                message = 'Le nom doit contenir au moins 3 caractères.';
            }
        } else if (id === 'description') {
            if (value === '') {
                isValid = false;
                message = 'La description est obligatoire.';
            } else if (value.length < 10) {
                isValid = false;
                message = 'La description doit contenir au moins 10 caractères.';
            }
        }

        if (isValid) {
            errorElement.style.display = 'none';
            field.classList.remove('invalid');
        } else {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            field.classList.add('invalid');
        }
        return isValid;
    }

    if (serviceForm) {
        ['nom', 'description'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', () => validateField(id));
                el.addEventListener('blur', () => validateField(id));
            }
        });

        serviceForm.addEventListener('submit', function(e) {
            let formIsValid = true;
            ['nom', 'description'].forEach(id => {
                if (!validateField(id)) formIsValid = false;
            });
            if (!formIsValid) e.preventDefault();
        });
    }

    window.resetServiceFormErrors = function() {
        ['nom', 'description'].forEach(id => {
            const field = document.getElementById(id);
            const errorElement = document.getElementById(id + '-error');
            if (field) field.classList.remove('invalid');
            if (errorElement) errorElement.style.display = 'none';
        });
    };
}

// Lancement
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initServiceFeatures();
} else {
    document.addEventListener('DOMContentLoaded', initServiceFeatures);
}

console.log("--- SERVICE.JS PRÊT ---");
