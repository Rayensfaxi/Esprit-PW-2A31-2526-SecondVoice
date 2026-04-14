<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Liste des Goals (Page 2)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/crud-style.css">
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar d-flex flex-column">
        <div class="brand">SecondVoice Admin</div>
        <ul class="nav flex-column mb-auto">
            <li class="nav-item">
                <a href="#" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a href="index-goals.php" class="nav-link active"><i class="fas fa-bullseye"></i> Gestion des Goals</a>
            </li>
            <li class="nav-item">
                <a href="index-guides.php" class="nav-link"><i class="fas fa-book"></i> Gestion des Guides</a>
            </li>
            <li class="nav-item mt-4">
                <a href="../../../front_office/view/frontend/index.html" class="nav-link"><i class="fas fa-external-link-alt"></i> Voir le site</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=2c3e50&color=fff" alt="mdo" width="32" height="32" class="rounded-circle me-2">
                    <strong>Admin</strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="#">Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#">Déconnexion</a></li>
                </ul>
            </div>
        </header>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Liste des Goals</h2>
            <a href="add-goal.php" class="btn btn-primary-custom shadow-sm">
                <i class="fas fa-plus me-1"></i> Ajouter un Goal
            </a>
        </div>

        <!-- Filters & Search -->
        <div class="card card-custom mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" placeholder="Rechercher par titre...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente">En attente</option>
                            <option value="en_cours">En cours</option>
                            <option value="termine">Terminé</option>
                            <option value="annule">Annulé</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select">
                            <option value="">Toutes les priorités</option>
                            <option value="faible">Faible</option>
                            <option value="moyenne">Moyenne</option>
                            <option value="haute">Haute</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="submit" class="btn btn-outline-secondary w-100">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card card-custom">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Titre</th>
                                <th>Statut</th>
                                <th>Priorité</th>
                                <th>Date Début</th>
                                <th>Date Fin</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Item 4 -->
                            <tr>
                                <td><span class="text-muted">#4</span></td>
                                <td>
                                    <strong>Renouvellement allocation</strong><br>
                                    <small class="text-muted">Dossier annuel CNSS</small>
                                </td>
                                <td><span class="badge badge-status-attente px-2 py-1 rounded-pill">En attente</span></td>
                                <td><span class="badge badge-prio-haute px-2 py-1 rounded-pill">Haute</span></td>
                                <td>01 Mai 2026</td>
                                <td>15 Mai 2026</td>
                                <td class="text-end">
                                    <a href="show-goal.php" class="btn btn-action btn-outline-info" title="Voir les détails"><i class="fas fa-eye"></i></a>
                                    <a href="edit-goal.php" class="btn btn-action btn-outline-primary" title="Modifier"><i class="fas fa-edit"></i></a>
                                    <a href="delete-goal.php" class="btn btn-action btn-outline-danger" title="Supprimer"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Pagination -->
            <div class="card-footer bg-white border-top-0 py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item"><a class="page-link" href="index-goals.php">Précédent</a></li>
                        <li class="page-item"><a class="page-link" href="index-goals.php">1</a></li>
                        <li class="page-item active"><a class="page-link" href="#">2</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#">Suivant</a></li>
                    </ul>
                </nav>
            </div>
        </div>

    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

