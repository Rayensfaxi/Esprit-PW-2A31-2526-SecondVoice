<?php
require_once '../../controller/GoalController.php';

$goalController = new GoalController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $goalController->updateGoal();
}

$goal = null;
if (isset($_GET['id'])) {
    $goal = $goalController->showGoal($_GET['id']);
}

if (!$goal) {
    header('Location: index-goals.php');
    exit();
}

$statusOptions = ['en_attente' => 'En attente', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé'];
$priorityOptions = ['faible' => 'Faible', 'moyenne' => 'Moyenne', 'haute' => 'Haute'];
$citoyens = [['id' => 1, 'name' => 'Ahmed Ben Ali'], ['id' => 2, 'name' => 'Sami Trabelsi']];
$assistants = [['id' => 101, 'name' => 'Maha Gharbi']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Goal - SecondVoice Admin</title>
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
        <header class="top-navbar">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=2c3e50&color=fff" width="32" height="32" class="rounded-circle me-2">
                    <strong>Admin</strong>
                </a>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Modifier l'Objectif #<?= htmlspecialchars($goal['id']) ?></h2>
            <a href="index-goals.php" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Retour à la liste
            </a>
        </div>

        <div class="card card-custom">
            <div class="card-header-custom">
                <h5 class="mb-0">Édition des informations</h5>
            </div>
            <div class="card-body p-4">
                <form id="editGoalForm" method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($goal['id']) ?>">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Titre de l'objectif <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($goal['title']) ?>" maxlength="20" required>
                            <div class="invalid-feedback">Le titre ne doit pas dépasser 20 caractères.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="4" minlength="5" maxlength="100" required><?= htmlspecialchars($goal['description']) ?></textarea>
                            <div class="invalid-feedback">La description doit contenir entre 5 et 100 caractères.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Statut <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $goal['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Priorité <span class="text-danger">*</span></label>
                            <select class="form-select" name="priority" required>
                                <?php foreach ($priorityOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $goal['priority'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="startDate" value="<?= htmlspecialchars($goal['startDate']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date de fin prévue <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="endDate" value="<?= htmlspecialchars($goal['endDate']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Citoyen Assigné <span class="text-danger">*</span></label>
                            <select class="form-select" name="citoyen_id" required>
                                <?php foreach ($citoyens as $citoyen): ?>
                                    <option value="<?= $citoyen['id'] ?>" <?= $goal['citoyen_id'] == $citoyen['id'] ? 'selected' : '' ?>>Citoyen #<?= $citoyen['id'] ?> (<?= htmlspecialchars($citoyen['name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Assistant Responsable <span class="text-danger">*</span></label>
                            <select class="form-select" name="assistant_id" required>
                                <?php foreach ($assistants as $assistant): ?>
                                    <option value="<?= $assistant['id'] ?>" <?= $goal['assistant_id'] == $assistant['id'] ? 'selected' : '' ?>>Assistant #<?= $assistant['id'] ?> (<?= htmlspecialchars($assistant['name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-end">
                            <a href="index-goals.php" class="btn btn-light me-2">Annuler</a>
                            <button type="submit" class="btn btn-primary-custom shadow-sm"><i class="fas fa-save me-1"></i> Mettre à jour</button>
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
            'use strict';
            const form = document.getElementById('editGoalForm');
            
            const titleInput = document.querySelector('input[name="title"]');
            const descInput = document.querySelector('textarea[name="description"]');
            const startDateInput = document.querySelector('input[name="startDate"]');
            const endDateInput = document.querySelector('input[name="endDate"]');

            form.addEventListener('submit', function (event) {
                // Réinitialiser les messages personnalisés
                titleInput.setCustomValidity('');
                descInput.setCustomValidity('');
                endDateInput.setCustomValidity('');

                // Contrôle : Le titre ne doit pas dépasser 20 caractères
                const titleLen = titleInput.value.trim().length;
                if (titleLen > 20) {
                    titleInput.setCustomValidity('Le titre ne doit pas dépasser 20 caractères (actuellement : ' + titleLen + ').');
                }

                // Contrôle : La description doit faire entre 5 et 100 caractères
                const descLen = descInput.value.trim().length;
                if (descLen < 5 || descLen > 100) {
                    descInput.setCustomValidity('La description doit contenir entre 5 et 100 caractères (actuellement : ' + descLen + ').');
                }

                // Contrôle : Date de fin ne peut pas être avant date de début
                if (startDateInput.value && endDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    if (endDate < startDate) {
                        endDateInput.setCustomValidity('La date de fin ne peut pas être antérieure à la date de début.');
                    }
                }

                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();

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
                form.classList.add('was-validated');
            }, false);

            // Nettoyer les erreurs pendant la frappe
            const inputs = [titleInput, descInput, endDateInput];
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    input.setCustomValidity('');
                });
            });
        })();
    </script>
</body>
</html>

