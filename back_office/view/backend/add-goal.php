<?php
require_once __DIR__ . '/../../controller/GoalController.php';

$goalController = new GoalController();
$goalController->addGoal(); // This will process the POST request if submitted

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Goal - SecondVoice Admin</title>
    <script>
        (function () {
            var keys = ['theme', 'intellectai-theme'];
            var initialTheme = 'light';

            try {
                for (var i = 0; i < keys.length; i++) {
                    var value = localStorage.getItem(keys[i]);
                    if (value === 'light' || value === 'dark') {
                        initialTheme = value;
                        break;
                    }
                }
            } catch (error) {
                initialTheme = 'light';
            }

            document.documentElement.setAttribute('data-theme', initialTheme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/crud-style.css">
</head>
<body>
    <nav class="sidebar d-flex flex-column">
        <div class="brand">SecondVoice Admin</div>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li class="nav-item"><a href="index-goals.php" class="nav-link active"><i class="fas fa-bullseye"></i> Gestion des Goals</a></li>
            <li class="nav-item"><a href="index-guides.php" class="nav-link"><i class="fas fa-book"></i> Gestion des Guides</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="top-navbar d-flex justify-content-between align-items-center mb-4">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=2c3e50&color=fff" width="32" height="32" class="rounded-circle me-2">
                    <strong>Admin</strong>
                </a>
            </div>
            <button type="button" id="theme-toggle" class="btn fw-semibold border-0 rounded-pill px-3 py-2 text-secondary theme-toggle-btn" style="background: #f1f5f9; transition: all 0.2s;" aria-label="Basculer le theme">
                <i class="fas fa-moon me-2" aria-hidden="true"></i><span id="theme-toggle-label">Sombre</span>
            </button>
        </header>

        <div class="page-hero-card mb-4">
            <div>
                <p class="page-kicker mb-2">Gestion des Goals</p>
                <h2 class="fw-bold mb-2">Ajouter un nouvel Objectif</h2>
                <p class="text-muted mb-0">Créez un objectif clair, planifié et attribué pour un meilleur suivi.</p>
            </div>
            <a href="index-goals.php" class="btn btn-outline-secondary shadow-sm page-hero-action">
                <i class="fas fa-arrow-left me-1"></i> Retour à la liste
            </a>
        </div>

        <div class="card card-custom">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Informations du Goal</h5>
                    <p class="mb-0 text-muted small">Les champs marques d'un asterisque sont obligatoires.</p>
                </div>
                <span class="chip chip-primary"><i class="fas fa-bullseye me-2"></i>Creation</span>
            </div>
            <div class="card-body p-4">
                <form id="addGoalForm" method="POST" action="" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Titre de l'objectif <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" maxlength="20" required placeholder="Ex: Renouvellement carte (max 20 car.)">
                            <div class="invalid-feedback">Le titre ne doit pas dépasser 20 caractères.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="4" minlength="5" maxlength="100" required placeholder="Détaillez l'objectif (entre 5 et 100 caractères)..."></textarea>
                            <div class="invalid-feedback">La description doit contenir entre 5 et 100 caractères.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-divider"><span>Planification</span></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Statut <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <option value="en_attente">En attente</option>
                                <option value="en_cours">En cours</option>
                                <option value="termine">Terminé</option>
                                <option value="annule">Annulé</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Priorité <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <option value="faible">Faible</option>
                                <option value="moyenne" selected>Moyenne</option>
                                <option value="haute">Haute</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="startDate" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date de fin prévue <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="endDate" required>
                        </div>

                        <div class="col-12">
                            <div class="form-divider"><span>Affectation</span></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Citoyen Assigné (ID) <span class="text-danger">*</span></label>
                            <select class="form-select" name="citoyen_id" required>
                                <option value="" disabled selected>Choisir un citoyen...</option>
                                <option value="1">Citoyen #1 (Ahmed Ben Ali)</option>
                                <option value="2">Citoyen #2 (Sami Trabelsi)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Assistant Responsable (ID) <span class="text-danger">*</span></label>
                            <select class="form-select" name="assistant_id" required>
                                <option value="" disabled selected>Choisir un assistant...</option>
                                <option value="101">Assistant #101 (Maha Gharbi)</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-end form-actions">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="window.history.back();">Annuler</button>
                            <button type="submit" class="btn btn-primary-custom shadow-sm"><i class="fas fa-save me-1"></i> Enregistrer le goal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
