<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontoffice/login.php?status=auth_required');
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    header('Location: ../frontoffice/profile.php?status=forbidden');
    exit;
}

$roleSession = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
if ($roleSession === 'agent') {
    header('Location: ../frontoffice/assistant-accompagnements.php');
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/BrainstormingController.php';
require_once __DIR__ . '/../../controller/IdeaController.php';

new BrainstormingController();
new IdeaController();

$conn = Config::getConnexion();

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetchOne(PDO $conn, string $sql): int
{
    try {
        $stmt = $conn->query($sql);
        return $stmt ? (int) $stmt->fetchColumn() : 0;
    } catch (Throwable $exception) {
        return 0;
    }
}

$totalBrainstormings = fetchOne($conn, 'SELECT COUNT(*) FROM brainstorming');
$totalIdeas = fetchOne($conn, 'SELECT COUNT(*) FROM ideas');
$approvedBrainstormings = fetchOne($conn, "SELECT COUNT(*) FROM brainstorming WHERE statut = 'approuve'");
$pendingBrainstormings = fetchOne($conn, "SELECT COUNT(*) FROM brainstorming WHERE statut = 'en attente'");

$monthsStmt = $conn->query(
    "SELECT DATE_FORMAT(dateCreation, '%Y-%m') AS month_key, COUNT(*) AS total
     FROM brainstorming
     GROUP BY month_key
     ORDER BY month_key ASC"
);
$brainstormingMonths = $monthsStmt ? ($monthsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$ideasMonthsStmt = $conn->query(
    "SELECT DATE_FORMAT(date_creation, '%Y-%m') AS month_key, COUNT(*) AS total
     FROM ideas
     GROUP BY month_key
     ORDER BY month_key ASC"
);
$ideaMonths = $ideasMonthsStmt ? ($ideasMonthsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$monthKeys = array_values(array_unique(array_merge(
    array_map(static fn(array $row): string => (string) $row['month_key'], $brainstormingMonths),
    array_map(static fn(array $row): string => (string) $row['month_key'], $ideaMonths)
)));
sort($monthKeys);
if ($monthKeys === []) {
    $monthKeys = [date('Y-m')];
}

$brainstormingMonthMap = [];
foreach ($brainstormingMonths as $row) {
    $brainstormingMonthMap[(string) $row['month_key']] = (int) $row['total'];
}

$ideaMonthMap = [];
foreach ($ideaMonths as $row) {
    $ideaMonthMap[(string) $row['month_key']] = (int) $row['total'];
}

$monthlyLabels = $monthKeys;
$monthlyBrainstormings = array_map(static fn(string $month): int => $brainstormingMonthMap[$month] ?? 0, $monthKeys);
$monthlyIdeas = array_map(static fn(string $month): int => $ideaMonthMap[$month] ?? 0, $monthKeys);

$categoryStmt = $conn->query(
    "SELECT categorie, COUNT(*) AS total
     FROM brainstorming
     GROUP BY categorie
     ORDER BY total DESC, categorie ASC"
);
$categoryRows = $categoryStmt ? ($categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$topStmt = $conn->query(
    "SELECT b.id, b.titre, COUNT(i.id) AS idea_count
     FROM brainstorming b
     LEFT JOIN ideas i ON i.brainstorming_id = b.id
     GROUP BY b.id, b.titre
     ORDER BY idea_count DESC, b.id DESC
     LIMIT 3"
);
$topBrainstormings = $topStmt ? ($topStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$categoryLabels = array_map(static fn(array $row): string => (string) ($row['categorie'] ?? 'Autre'), $categoryRows);
$categoryValues = array_map(static fn(array $row): int => (int) ($row['total'] ?? 0), $categoryRows);
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Statistiques des brainstormings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css?v=brainstorming-submenu-v5" />
    <style>
      .brainstorming-stats-page .topbar {
        align-items: flex-start;
        border-bottom: 1px solid var(--line);
        margin-bottom: 24px;
        padding-bottom: 24px;
      }

      .brainstorming-stats-page .stats-overview-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-top: 0;
      }

      .brainstorming-stats-page .metric-card {
        min-height: 82px;
        border-radius: 22px;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
        box-shadow: none;
      }

      .brainstorming-stats-page .metric-card .small-label {
        color: inherit;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
      }

      .brainstorming-stats-page .metric-card strong {
        font-size: 1.7rem;
        line-height: 1;
      }

      .brainstorming-stats-page .metric-purple {
        border-color: rgba(109, 92, 255, 0.65);
        background: linear-gradient(135deg, rgba(109, 92, 255, 0.28), rgba(109, 92, 255, 0.08));
        color: #9b8cff;
      }

      .brainstorming-stats-page .metric-pink {
        border-color: rgba(255, 111, 216, 0.5);
        background: linear-gradient(135deg, rgba(255, 111, 216, 0.22), rgba(255, 111, 216, 0.08));
        color: #ff88db;
      }

      .brainstorming-stats-page .metric-green {
        border-color: rgba(45, 212, 191, 0.55);
        background: linear-gradient(135deg, rgba(45, 212, 191, 0.22), rgba(45, 212, 191, 0.08));
        color: #32e6cf;
      }

      .brainstorming-stats-page .metric-orange {
        grid-column: 1 / 2;
        border-color: rgba(245, 158, 11, 0.55);
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.22), rgba(245, 158, 11, 0.08));
        color: #ffc15e;
      }

      .brainstorming-stats-page .analytics-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 1.5fr) minmax(410px, 1fr);
        gap: 16px;
        align-items: stretch;
        margin-top: 16px;
      }

      .brainstorming-stats-page .analytics-card {
        border: 1px solid var(--line);
        border-radius: 22px;
        background: var(--panel);
        padding: 20px;
        overflow: hidden;
      }

      .brainstorming-stats-page .analytics-card .section-title {
        font-size: 1rem;
        line-height: 1.25;
        letter-spacing: 0;
        margin: 0;
      }

      .brainstorming-stats-page .analytics-card canvas {
        display: block;
        width: 100% !important;
        height: 250px !important;
        margin-top: 14px;
      }

      .brainstorming-stats-page .top-brainstormings {
        margin-top: 16px;
        min-height: 230px;
      }

      .brainstorming-stats-page .top-brainstormings .users-header {
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 14px;
      }

      .brainstorming-stats-page .top-brainstormings .helper {
        margin: 0;
      }

      .brainstorming-stats-page .ranking-list {
        display: grid;
      }

      .brainstorming-stats-page .ranking-row {
        min-height: 64px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 12px;
        border-top: 1px solid var(--line);
      }

      .brainstorming-stats-page .ranking-row:first-child {
        border-top: 0;
      }

      .brainstorming-stats-page .ranking-row div {
        display: grid;
        gap: 2px;
      }

      .brainstorming-stats-page .ranking-row div strong,
      .brainstorming-stats-page .ranking-row > strong {
        color: var(--text);
      }

      .brainstorming-stats-page .rank-badge {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-weight: 800;
      }

      .brainstorming-stats-page .rank-2 {
        background: #c4c9d2;
        color: #101828 !important;
      }

      .brainstorming-stats-page .rank-3 {
        background: #d97706;
        color: #fff !important;
      }

      @media (max-width: 1180px) {
        .brainstorming-stats-page .analytics-grid {
          grid-template-columns: 1fr;
        }
      }

      @media (max-width: 860px) {
        .brainstorming-stats-page .stats-overview-grid {
          grid-template-columns: 1fr;
        }

        .brainstorming-stats-page .metric-orange {
          grid-column: auto;
        }

        .brainstorming-stats-page .top-brainstormings .users-header {
          display: grid;
        }
      }
    </style>
  </head>
  <body class="brainstorming-stats-page" data-page="community">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-brainstormings.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des brainstormings</span></a>
              <a class="nav-link nav-sub-link" href="statistiques-brainstormings.php" data-nav="community" hidden><span class="nav-icon icon-community"></span><span>Statistiques</span></a>
              <a class="nav-link nav-sub-link" href="gestion-idees.php" data-nav="community" hidden><span class="nav-icon icon-community"></span><span>Gestion des idees</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-guides.php" data-nav="images"><span class="nav-icon icon-document"></span><span>Gestion des guides</span></a>
              <a class="nav-link" href="gestion-evenements.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des evenements</span></a>
              <a class="nav-link" href="gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Statistiques des brainstormings</h1>
            <div class="page-subtitle">Visualisez les metriques et la performance de vos brainstormings.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="gestion-brainstormings.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
            <?php include __DIR__ . '/partials/user-menu.php'; ?>
          </div>
        </div>

        <div class="stats-overview-grid">
          <article class="metric-card metric-purple">
            <div class="small-label">Total brainstormings</div>
            <strong><?= $totalBrainstormings ?></strong>
          </article>
          <article class="metric-card metric-pink">
            <div class="small-label">Total idees</div>
            <strong><?= $totalIdeas ?></strong>
          </article>
          <article class="metric-card metric-green">
            <div class="small-label">Approuves</div>
            <strong><?= $approvedBrainstormings ?></strong>
          </article>
          <article class="metric-card metric-orange">
            <div class="small-label">En attente</div>
            <strong><?= $pendingBrainstormings ?></strong>
          </article>
        </div>

        <div class="analytics-grid">
          <section class="analytics-card">
            <h2 class="section-title">Brainstormings et Idees par Mois</h2>
            <canvas id="monthly-chart" height="260"></canvas>
          </section>
          <section class="analytics-card">
            <h2 class="section-title">Brainstormings par Categorie</h2>
            <canvas id="category-chart" height="260"></canvas>
          </section>
        </div>

        <section class="analytics-card top-brainstormings">
          <div class="users-header">
            <h2 class="section-title">Top 3 Brainstormings</h2>
            <p class="helper">Les brainstormings avec le plus d'idees soumises</p>
          </div>
          <div class="ranking-list">
            <?php if ($topBrainstormings === []): ?>
              <div class="ranking-row">
                <span class="rank-badge">1</span>
                <div><strong>Aucun brainstorming</strong><span>0 idees soumises</span></div>
                <strong>0</strong>
              </div>
            <?php else: ?>
              <?php foreach ($topBrainstormings as $index => $item): ?>
                <div class="ranking-row">
                  <span class="rank-badge rank-<?= $index + 1 ?>"><?= $index + 1 ?></span>
                  <div>
                    <strong><?= h((string) $item['titre']) ?></strong>
                    <span><?= (int) $item['idea_count'] ?> idees soumises</span>
                  </div>
                  <strong><?= (int) $item['idea_count'] ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      </main>
    </div>

    <script>
      window.brainstormingStats = {
        monthlyLabels: <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE) ?>,
        monthlyBrainstormings: <?= json_encode($monthlyBrainstormings, JSON_UNESCAPED_UNICODE) ?>,
        monthlyIdeas: <?= json_encode($monthlyIdeas, JSON_UNESCAPED_UNICODE) ?>,
        categoryLabels: <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE) ?>,
        categoryValues: <?= json_encode($categoryValues, JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
    <script src="assets/brainstorming-stats.js"></script>
    <script src="assets/app.js?v=brainstorming-submenu-v5"></script>
  </body>
</html>
