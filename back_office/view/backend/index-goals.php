<?php
require_once __DIR__ . '/../../controller/GoalController.php';

$goalController = new GoalController();
$allGoals = $goalController->listGoals();

$statusOptions = [
    'en_attente' => 'En attente',
    'en_cours' => 'En cours',
    'termine' => 'Termine',
    'annule' => 'Annule'
];

$priorityOptions = [
    'faible' => 'Faible',
    'moyenne' => 'Moyenne',
    'haute' => 'Haute'
];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');

$searchLower = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);

$goals = array_values(array_filter($allGoals, function ($goal) use ($searchLower, $statusFilter, $priorityFilter) {
    $title = (string)($goal['title'] ?? '');
    $description = (string)($goal['description'] ?? '');
    $status = (string)($goal['status'] ?? '');
    $priority = (string)($goal['priority'] ?? '');

    $haystack = $title . ' ' . $description;
    $haystackLower = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);

    $matchSearch = $searchLower === '' || strpos($haystackLower, $searchLower) !== false;
    $matchStatus = $statusFilter === '' || $status === $statusFilter;
    $matchPriority = $priorityFilter === '' || $priority === $priorityFilter;

    return $matchSearch && $matchStatus && $matchPriority;
}));

$totalGoals = count($allGoals);
$openGoals = count(array_filter($allGoals, function ($goal) {
    return ($goal['status'] ?? '') === 'en_cours';
}));
$doneGoals = count(array_filter($allGoals, function ($goal) {
    return ($goal['status'] ?? '') === 'termine';
}));

function statusBadgeClass($status)
{
    switch ($status) {
        case 'en_attente':
            return 'badge-status-attente';
        case 'en_cours':
            return 'badge-status-cours';
        case 'termine':
            return 'badge-status-termine';
        case 'annule':
            return 'badge-status-annule';
        default:
            return 'bg-secondary';
    }
}

function priorityBadgeClass($priority)
{
    switch ($priority) {
        case 'faible':
            return 'badge-prio-faible';
        case 'moyenne':
            return 'badge-prio-moyenne';
        case 'haute':
            return 'badge-prio-haute';
        default:
            return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Goals - SecondVoice Admin</title>
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
        <header class="d-flex justify-content-between align-items-center bg-white p-4 rounded-4 shadow-sm mb-4 border" style="border-color: #eef2f7 !important;">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #4F46E5, #7C3AED); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);">
                    <i class="fas fa-bullseye text-white fs-4"></i>
                </div>
                <div>
                    <h1 class="h4 fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">SecondVoice</h1>
                    <span class="text-muted fw-medium" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">Administration Goals</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="theme-toggle" class="btn fw-semibold border-0 rounded-pill px-3 py-2 text-secondary theme-toggle-btn" style="background: #f1f5f9; transition: all 0.2s;" aria-label="Basculer le theme">
                    <i class="fas fa-moon me-2" aria-hidden="true"></i><span id="theme-toggle-label">Sombre</span>
                </button>
                <button class="btn fw-semibold border-0 rounded-pill px-3 py-2 text-secondary" style="background: #f1f5f9; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a';" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b';">
                    <i class="far fa-user-circle me-2"></i>Profil
                </button>
                <a href="index.php" class="btn fw-semibold rounded-pill px-4 py-2 text-white shadow-sm" style="background: linear-gradient(135deg, #4F46E5, #7C3AED); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">
                    <i class="fas fa-border-all me-2"></i>Tableau de bord
                </a>
            </div>
        </header>

        <div class="row g-4 mb-5 mt-2">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 py-3" style="background: #fff; border-bottom: 4px solid #4F46E5 !important; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)';" onmouseout="this.style.transform='translateY(0)';">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 54px; height: 54px; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                            <i class="fas fa-list-ol fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted fw-bold mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Goals</h6>
                            <h2 class="mb-0 fw-bolder text-dark" style="font-size: 1.8rem;"><?= $totalGoals ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 py-3" style="background: #fff; border-bottom: 4px solid #F59E0B !important; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)';" onmouseout="this.style.transform='translateY(0)';">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 54px; height: 54px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                            <i class="fas fa-spinner fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted fw-bold mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">En cours</h6>
                            <h2 class="mb-0 fw-bolder text-dark" style="font-size: 1.8rem;"><?= $openGoals ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 py-3" style="background: #fff; border-bottom: 4px solid #10B981 !important; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)';" onmouseout="this.style.transform='translateY(0)';">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 54px; height: 54px; background: rgba(16, 185, 129, 0.1); color: #10B981;">
                            <i class="fas fa-check-double fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted fw-bold mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Terminés</h6>
                            <h2 class="mb-0 fw-bolder text-dark" style="font-size: 1.8rem;"><?= $doneGoals ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 py-3" style="background: #fff; border-bottom: 4px solid #3B82F6 !important; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)';" onmouseout="this.style.transform='translateY(0)';">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 54px; height: 54px; background: rgba(59, 130, 246, 0.1); color: #3B82F6;">
                            <i class="fas fa-filter fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted fw-bold mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Affichés</h6>
                            <h2 class="mb-0 fw-bolder text-dark" style="font-size: 1.8rem;"><?= count($goals) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Liste des Goals</h2>
            <a href="add-goal.php" class="btn btn-primary-custom shadow-sm">
                <i class="fas fa-plus me-1"></i> Ajouter un Goal
            </a>
        </div>

        <div class="card card-custom mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-center" method="GET" action="">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control border-start-0 ps-0" placeholder="Rechercher par titre ou description...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">Tous les statuts</option>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="priority">
                            <option value="">Toutes les priorites</option>
                            <?php foreach ($priorityOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $priorityFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 text-end d-grid gap-2">
                        <button type="submit" class="btn btn-outline-secondary">Filtrer</button>
                        <a href="index-goals.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-custom">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Titre</th>
                                <th>Statut</th>
                                <th>Priorite</th>
                                <th>Date debut</th>
                                <th>Date fin</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($goals)): ?>
                                <tr><td colspan="7" class="text-center py-4">Aucun goal trouve avec ces filtres.</td></tr>
                            <?php else: ?>
                                <?php foreach ($goals as $goal): ?>
                                    <tr>
                                        <td><span class="text-muted">#<?= htmlspecialchars($goal['id']) ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($goal['title']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars(substr((string)$goal['description'], 0, 40)) ?>...</small>
                                        </td>
                                        <td><span class="badge <?= statusBadgeClass($goal['status']) ?> px-2 py-1 rounded-pill"><?= htmlspecialchars($statusOptions[$goal['status']] ?? $goal['status']) ?></span></td>
                                        <td><span class="badge <?= priorityBadgeClass($goal['priority']) ?> px-2 py-1 rounded-pill"><?= htmlspecialchars($priorityOptions[$goal['priority']] ?? $goal['priority']) ?></span></td>
                                        <td><?= htmlspecialchars($goal['startDate'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($goal['endDate'] ?? '-') ?></td>
                                        <td class="text-end">
                                            <a href="show-goal.php?id=<?= urlencode((string)$goal['id']) ?>" class="btn btn-action btn-outline-info"><i class="fas fa-eye"></i></a>
                                            <a href="edit-goal.php?id=<?= urlencode((string)$goal['id']) ?>" class="btn btn-action btn-outline-primary"><i class="fas fa-edit"></i></a>
                                            <a href="delete-goal.php?id=<?= urlencode((string)$goal['id']) ?>" class="btn btn-action btn-outline-danger" onclick="return confirm('Supprimer ce goal ?');"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var root = document.documentElement;
            var toggleButton = document.getElementById('theme-toggle');
            var toggleLabel = document.getElementById('theme-toggle-label');
            var storageKeys = ['theme', 'intellectai-theme'];

            function getStoredTheme() {
                for (var i = 0; i < storageKeys.length; i++) {
                    var value = localStorage.getItem(storageKeys[i]);
                    if (value === 'light' || value === 'dark') {
                        return value;
                    }
                }
                return null;
            }

            function persistTheme(theme) {
                for (var i = 0; i < storageKeys.length; i++) {
                    localStorage.setItem(storageKeys[i], theme);
                }
            }

            function applyTheme(theme) {
                root.setAttribute('data-theme', theme);
                if (toggleLabel) {
                    toggleLabel.textContent = theme === 'dark' ? 'Clair' : 'Sombre';
                }
            }

            var initialTheme = getStoredTheme() || 'light';
            applyTheme(initialTheme);

            if (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    var nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    persistTheme(nextTheme);
                    applyTheme(nextTheme);
                });
            }
        })();
    </script>
</body>
</html>
