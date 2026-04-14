<?php
require_once '../../controller/GoalController.php';

$goalController = new GoalController();
$goal = null;

// Fetch the goal if ID is provided
if (isset($_GET['id'])) {
    $goal = $goalController->showGoal($_GET['id']);
}

// Redirect if goal doesn't exist
if (!$goal) {
    header('Location: index-goals.php');
    exit();
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'en_attente': return 'badge-status-attente';
        case 'en_cours': return 'badge-status-cours';
        case 'termine': return 'badge-status-termine';
        case 'annule': return 'badge-status-annule';
        default: return 'badge-secondary';
    }
}

// Helper function to get status label
function getStatusLabel($status) {
    switch($status) {
        case 'en_attente': return 'En attente';
        case 'en_cours': return 'En cours';
        case 'termine': return 'Terminé';
        case 'annule': return 'Annulé';
        default: return $status;
    }
}

// Helper function to get priority badge class
function getPriorityBadgeClass($priority) {
    switch($priority) {
        case 'faible': return 'badge-prio-faible';
        case 'moyenne': return 'badge-prio-moyenne';
        case 'haute': return 'badge-prio-haute';
        default: return 'badge-secondary';
    }
}

// Helper function to get priority label
function getPriorityLabel($priority) {
    switch($priority) {
        case 'faible': return 'Faible';
        case 'moyenne': return 'Moyenne';
        case 'haute': return 'Haute';
        default: return $priority;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Goal - SecondVoice</title>
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
                <a href="#" class="text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin" width="32" class="rounded-circle me-2"> <strong>Admin</strong>
                </a>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Détails de l'Objectif #<?= htmlspecialchars($goal['id']) ?></h2>
            <div>
                <a href="edit-goal.php?id=<?= $goal['id'] ?>" class="btn btn-outline-primary shadow-sm me-2"><i class="fas fa-edit"></i> Modifier</a>
                <a href="index-goals.php" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Goal Info Card -->
                <div class="card card-custom mb-4">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Informations Principales</h5>
                        <span class="badge <?= getStatusBadgeClass($goal['status']) ?> px-3 py-2 rounded-pill"><?= getStatusLabel($goal['status']) ?></span>
                    </div>
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3"><?= htmlspecialchars($goal['title']) ?></h3>
                        <p class="text-muted mb-4"><?= htmlspecialchars($goal['description']) ?></p>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Priorité</div>
                            <div class="col-sm-8"><span class="badge <?= getPriorityBadgeClass($goal['priority']) ?>"><?= getPriorityLabel($goal['priority']) ?></span></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Période</div>
                            <div class="col-sm-8 fw-semibold"><?= htmlspecialchars($goal['startDate']) ?> <i class="fas fa-arrow-right mx-2 text-muted"></i> <?= htmlspecialchars($goal['endDate']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Citoyen rattaché</div>
                            <div class="col-sm-8">
                                <a href="#" class="text-decoration-none fw-bold"><i class="fas fa-user me-1"></i> Citoyen (ID: <?= htmlspecialchars($goal['citoyen_id']) ?>)</a>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Assistant en charge</div>
                            <div class="col-sm-8">
                                <a href="#" class="text-decoration-none fw-bold text-success"><i class="fas fa-user-tie me-1"></i> Assistant (ID: <?= htmlspecialchars($goal['assistant_id']) ?>)</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Linked Guides Column -->
            <div class="col-md-4">
                <div class="card card-custom h-100">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Guides Associés</h5>
                        <a href="add-guide.php" class="btn btn-sm btn-primary-custom"><i class="fas fa-plus"></i> Ajouter</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush border-0">
                            <li class="list-group-item d-flex justify-content-between align-items-start p-3">
                                <div>
                                    <div class="fw-bold"><a href="show-guide.php" class="text-decoration-none text-dark">Aucun guide associé</a></div>
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Ajouter des guides à cet objectif</small>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
