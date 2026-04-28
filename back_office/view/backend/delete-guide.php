<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Guide - SecondVoice</title>
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

    <main class="main-content d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="card card-custom border-danger" style="max-width: 500px; width: 100%;">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <i class="fas fa-trash-alt text-danger" style="font-size: 4rem;"></i>
                </div>
                <h3 class="fw-bold mb-3">Suppression du Guide</h3>
                <p class="text-muted mb-4 pb-2 border-bottom">
                    Êtes-vous sûr de vouloir retirer ce guide d'accompagnement ? Cette ressource ne sera plus disponible pour les utilisateurs rattachés au Goal parent.
                </p>
                
                <div class="bg-light p-3 rounded mb-4 text-start shadow-sm border">
                    <strong>Guide :</strong> Procédure de prise de RDV en ligne<br>
                    <strong>Type :</strong> <span class="badge bg-secondary">Tutoriel</span><br>
                    <strong>Goal relié :</strong> <a href="show-goal.php">#1 - Obtenir carte d'invalidité</a>
                </div>

                <form onsubmit="event.preventDefault(); window.location.href='index-guides.php';">
                    <div class="d-flex justify-content-center gap-3">
                        <a href="index-guides.php" class="btn btn-light px-4 py-2 fw-semibold">Annuler</a>
                        <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold"><i class="fas fa-trash me-2"></i>Confirmer la suppression</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
