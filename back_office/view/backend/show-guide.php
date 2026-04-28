<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecture d'un Guide - SecondVoice</title>
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
                <a href="#" class="text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin" width="32" class="rounded-circle me-2"> <strong>Admin</strong>
                </a>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Détails du Guide</h2>
            <div>
                <a href="edit-guide.php" class="btn btn-outline-primary shadow-sm me-2"><i class="fas fa-edit"></i> Modifier</a>
                <a href="index-guides.php" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Liste des guides</a>
            </div>
        </div>

        <div class="card card-custom mb-4 border-top border-4 border-info">
            <div class="card-header-custom d-flex justify-content-between align-items-center pb-2 bg-light">
                <span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="fas fa-book-open me-2"></i>Tutoriel</span>
                <span class="text-muted"><i class="fas fa-link me-1"></i> Associé au Goal: <a href="show-goal.php" class="fw-bold text-decoration-none">#1 - Obtenir carte d'invalidité</a></span>
            </div>
            
            <div class="card-body p-5">
                <h2 class="fw-bolder mb-4 text-dark">Procédure de prise de RDV en ligne</h2>
                
                <div class="p-4 bg-white border border-1 rounded my-3" style="font-size: 1.1rem; line-height: 1.8; color: #444;">
                    <p><strong>Étape 1 :</strong> Se rendre sur le portail <a href="#" class="text-primary text-decoration-none">www.social.gov.tn</a></p>
                    <p><strong>Étape 2 :</strong> Cliquer sur "Espace Citoyen" en haut à droite.</p>
                    <p><strong>Étape 3 :</strong> Saisir les informations demandées issues du dossier n°125 (votre identifiant national et mot de passe).</p>
                    <hr class="my-4">
                    <p class="text-muted"><i class="fas fa-info-circle me-2"></i> Rappel : Ne partagez jamais vos codes personnels par téléphone.</p>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-2"></i>Imprimer le contenu</button>
                </div>
            </div>
        </div>

    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
