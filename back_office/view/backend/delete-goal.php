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

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        $goalController->deleteGoal($_GET['id']);
    } else {
        // If cancelled, redirect back to goals list
        header('Location: index-goals.php');
        exit();
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Goal - SecondVoice</title>
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

    <main class="main-content d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="card card-custom border-danger" style="max-width: 500px; width: 100%;">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                </div>
                <h3 class="fw-bold mb-3">Confirmation de suppression</h3>
                <p class="text-muted mb-4 pb-2 border-bottom">
                    Êtes-vous sûr de vouloir supprimer définitivement cet objectif ? Cette action supprimera également tous les guides qui lui sont associés.
                </p>
                
                <div class="bg-light p-3 rounded mb-4 text-start">
                    <strong>Titre :</strong> <?= htmlspecialchars($goal['title']) ?><br>
                    <strong>Statut :</strong> <span class="text-primary"><?= getStatusLabel($goal['status']) ?></span><br>
                    <strong>Citoyen ID :</strong> <?= htmlspecialchars($goal['citoyen_id']) ?>
                </div>

                <form method="POST" action="">
                    <div class="d-flex justify-content-center gap-3">
                        <a href="index-goals.php" class="btn btn-light px-4 py-2 fw-semibold">Annuler</a>
                        <button type="submit" name="confirm_delete" value="yes" class="btn btn-danger px-4 py-2 fw-semibold"><i class="fas fa-trash me-2"></i>Confirmer la suppression</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
