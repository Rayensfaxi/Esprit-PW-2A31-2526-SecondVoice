<?php require_once '../../controller/GuideController.php'; $guideController = new GuideController(); $guideController->addGuide(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Guide - SecondVoice Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/crud-style.css">
</head>
<body>
    <nav class="sidebar d-flex flex-column">
        <div class="brand">SecondVoice Admin</div>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li class="nav-item"><a href="index-goals.php" class="nav-link"><i class="fas fa-bullseye"></i> Gestion des Goals</a></li>
            <li class="nav-item"><a href="index-guides.php" class="nav-link active"><i class="fas fa-book"></i> Gestion des Guides</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="top-navbar">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin" width="32" class="rounded-circle me-2">
                    <strong>Admin</strong>
                </a>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Ajouter un Guide (Ressource)</h2>
            <a href="index-guides.php" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Retour à la liste
            </a>
        </div>

        <div class="card card-custom">
            <div class="card-header-custom">
                <h5 class="mb-0">Création d'un nouveau guide didactique</h5>
            </div>
            <div class="card-body p-4">
                <form id="addGuideForm" method="POST" action="" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Titre du guide <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" maxlength="20" required placeholder="Ex: Tutoriel création compte">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Type de guide <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" required>
                                <option value="" disabled selected>Sélectionner...</option>
                                <option value="Document">Document PDF/Word</option>
                                <option value="Tutoriel">Tutoriel étape par étape</option>
                                <option value="Lien">Lien vers plateforme externe</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">Objectif Parent (Goal ID) <span class="text-danger">*</span></label>
                            <select class="form-select" name="goal_id" required>
                                <option value="" disabled selected>Associer ce guide à un objectif existant...</option>
                                <option value="1">#1 - Obtenir carte d'invalidité (En cours)</option>
                                <option value="2">#2 - Demande de fauteuil roulant (Attente)</option>
                                <option value="3">#3 - Inscription transport adapté (Terminé)</option>
                            </select>
                            <div class="form-text">Un guide doit toujours étayer un objectif spécifique de l'utilisateur.</div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <label class="form-label fw-bold">Contenu du guide <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" rows="6" minlength="5" maxlength="100" required placeholder="Texte, lien URL ou corps du tutoriel..."></textarea>
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-end">
                            <button type="button" class="btn btn-light me-2" onclick="window.history.back();">Annuler</button>
                            <button type="submit" class="btn btn-primary-custom shadow-sm"><i class="fas fa-save me-1"></i> Sauvegarder le guide</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Contrôle de saisie avancé avec JavaScript
        (function () {
            'use strict'
            var form = document.getElementById('addGuideForm')
            
            var titleInput = document.querySelector('input[name="title"]')
            var contentInput = document.querySelector('textarea[name="content"]')
            var typeSelect = document.querySelector('select[name="type"]')
            var goalSelect = document.querySelector('select[name="goal_id"]')

            form.addEventListener('submit', function (event) {
                // Réinitialiser les messages personnalisés
                titleInput.setCustomValidity('')
                contentInput.setCustomValidity('')
                typeSelect.setCustomValidity('')
                goalSelect.setCustomValidity('')

                // Contrôle : Le titre ne doit pas dépasser 20 caractères
                const titleLen = titleInput.value.trim().length;
                if (titleLen > 20) {
                    titleInput.setCustomValidity('Le titre ne doit pas dépasser 20 caractères (actuellement : ' + titleLen + ').')
                }

                // Contrôle : Le contenu doit faire entre 5 et 100 caractères
                const contentLen = contentInput.value.trim().length;
                if (contentLen < 5 || contentLen > 100) {
                    contentInput.setCustomValidity('Le contenu doit contenir entre 5 et 100 caractères (actuellement : ' + contentLen + ').')
                }

                // Contrôle : Type doit être sélectionné
                if (!typeSelect.value) {
                    typeSelect.setCustomValidity('Veuillez sélectionner un type de guide')
                }

                // Contrôle : Goal doit être sélectionné
                if (!goalSelect.value) {
                    goalSelect.setCustomValidity('Veuillez sélectionner un objectif')
                }

                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()

                    // Afficher dynamiquement le message d'erreur exact pour chaque champ
                    const allInputs = form.querySelectorAll('.form-control, .form-select');
                    allInputs.forEach(function(input) {
                        let feedback = input.nextElementSibling;
                        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                            feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            input.parentNode.appendChild(feedback);
                        }
                        if (input.validationMessage) {
                            feedback.textContent = input.validationMessage;
                        }
                    });
                }
                form.classList.add('was-validated')
            }, false)

            // Nettoyer les erreurs pendant la frappe
            const inputs = [titleInput, contentInput, typeSelect, goalSelect];
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    input.setCustomValidity('')
                })
                input.addEventListener('change', () => {
                    input.setCustomValidity('')
                })
            })
        })()
    </script>
</body>
</html>
