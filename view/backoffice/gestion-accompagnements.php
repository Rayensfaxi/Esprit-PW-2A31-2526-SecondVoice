<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';

// Admin check
$_role = strtolower((string)($_SESSION['role'] ?? ''));
$_userRole = strtolower((string)($_SESSION['user_role'] ?? ''));
$isAdmin = ($_role === 'admin') || ($_userRole === 'admin');
if (!$isAdmin) {
  header('Location: /test-login.php?role=admin&next=view/backoffice/gestion-accompagnements.php');
  exit;
}

$controller = new GoalController();
$goals = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $comment  = trim($_POST['comment'] ?? '');

    if ($action === 'moderate' && $id > 0 && in_array($decision, ['valide', 'refuse'])) {
        try {
            $controller->moderateGoalByAdmin($id, $decision, $comment);
            $msg = $decision === 'valide' ? 'validée' : 'refusée';
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Demande $msg avec succès."];
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        }
        header('Location: gestion-accompagnements.php');
        exit;
    }
}

// === ADVANCED SEARCH FILTERS ===
$adminFilters = [
    'keyword'          => trim((string)($_GET['keyword'] ?? '')),
    'type'             => $_GET['type'] ?? '',
    'status'           => $_GET['status'] ?? '',
    'admin_status'     => $_GET['admin_status'] ?? 'en_attente', // default: pending
    'assistant_status' => $_GET['assistant_status'] ?? '',
    'priority'         => $_GET['priority'] ?? '',
    'sort'             => $_GET['sort'] ?? 'created_desc',
];
$hasActiveFilter = !empty($adminFilters['keyword']) || !empty($adminFilters['type']) || !empty($adminFilters['status'])
    || ($adminFilters['admin_status'] !== 'en_attente') || !empty($adminFilters['assistant_status'])
    || !empty($adminFilters['priority'])
    || ($adminFilters['sort'] !== 'created_desc');

try {
    $goals = $controller->searchGoals($adminFilters);
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => "Impossible de récupérer les demandes."];
}

// Global stats for the pie (independent of search filters)
$adminPieCounts = ['en_attente' => 0, 'valide' => 0, 'refuse' => 0];
try {
    $stmt = Config::getConnexion()->query("SELECT admin_validation_status, COUNT(*) AS n FROM goals GROUP BY admin_validation_status");
    if ($stmt) {
        foreach ($stmt->fetchAll() as $row) {
            $k = $row['admin_validation_status'];
            if (isset($adminPieCounts[$k])) { $adminPieCounts[$k] = (int)$row['n']; }
        }
    }
} catch (Throwable $e) {}

$typeLabels = [
    'cv'           => ['📄', 'CV'],
    'cover_letter' => ['✉️', 'Lettre de motivation'],
    'linkedin'     => ['🔗', 'LinkedIn'],
    'interview'    => ['🎤', 'Entretien'],
    'other'        => ['📌', 'Autre'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gestion des accompagnements | Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
  <style>
    /* ── PAGE-SPECIFIC OVERRIDES ── */
    .sv-alert {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 20px;
      border-radius: 12px;
      font-size: 0.9rem;
      margin-bottom: 24px;
      animation: svFade .3s ease;
    }
    @keyframes svFade { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
    .sv-alert-success { background: rgba(49,208,170,.12); color: #1a9c7c; border: 1px solid rgba(49,208,170,.3); }
    .sv-alert-error   { background: rgba(255,107,107,.12); color: #cc3333; border: 1px solid rgba(255,107,107,.3); }

    .sv-cards { display: flex; flex-direction: column; gap: 16px; }

    .sv-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 18px;
      overflow: hidden;
      transition: box-shadow .2s;
    }
    .sv-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.2); }

    .sv-card-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      padding: 20px 24px 0;
      flex-wrap: wrap;
    }

    .sv-goal-id {
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 4px;
    }

    .sv-goal-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 5px;
    }

    .sv-goal-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      font-size: .8rem;
      color: var(--muted);
    }
    .sv-meta-sep { opacity: .3; }

    .sv-type-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 100px;
      font-size: .77rem;
      font-weight: 700;
      background: var(--purple-soft);
      color: var(--purple);
    }

    .sv-card-body {
      padding: 16px 24px;
      font-size: .875rem;
      color: var(--muted);
      line-height: 1.6;
      border-top: 1px solid var(--line);
      margin-top: 14px;
    }

    .sv-card-actions {
      padding: 16px 24px 20px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 600px) {
      .sv-card-actions { grid-template-columns: 1fr; }
    }

    .sv-action-block { display: flex; flex-direction: column; gap: 8px; }
    .sv-action-label {
      font-size: .73rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--muted);
    }

    .sv-comment-input {
      width: 100%;
      padding: 9px 13px;
      background: var(--input-bg);
      border: 1.5px solid var(--input-border);
      border-radius: 10px;
      color: var(--text);
      font-family: inherit;
      font-size: .875rem;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
      resize: none;
    }
    .sv-comment-input:focus {
      border-color: var(--purple);
      box-shadow: 0 0 0 3px var(--purple-soft);
    }
    .sv-comment-input.is-invalid {
      border-color: #ff6b6b !important;
      box-shadow: 0 0 0 3px rgba(255,107,107,.15) !important;
    }

    .sv-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 10px 20px;
      border-radius: 10px;
      font-family: inherit;
      font-size: .875rem;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: all .2s;
    }
    .sv-btn-approve {
      background: rgba(49,208,170,.18);
      color: #1fa87b;
      border: 1px solid rgba(49,208,170,.3);
    }
    .sv-btn-approve:hover {
      background: rgba(49,208,170,.28);
      transform: translateY(-1px);
    }
    .sv-btn-refuse {
      background: rgba(255,107,107,.14);
      color: #cc3333;
      border: 1px solid rgba(255,107,107,.25);
    }
    .sv-btn-refuse:hover {
      background: rgba(255,107,107,.24);
      transform: translateY(-1px);
    }
    .sv-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .sv-field-error {
      font-size: .78rem;
      color: #cc3333;
      display: none;
      align-items: center;
      gap: 4px;
      margin-top: 6px;
      padding: 6px 10px;
      background: rgba(255, 107, 107, .1);
      border: 1px solid rgba(255, 107, 107, .3);
      border-radius: 8px;
      font-weight: 600;
    }
    .sv-field-error.visible {
      display: flex;
      animation: sv-shake .42s cubic-bezier(.36, .07, .19, .97);
    }
    @keyframes sv-shake {
      0%, 100% { transform: translateX(0); }
      15%, 45%, 75% { transform: translateX(-6px); }
      30%, 60%, 90% { transform: translateX(6px); }
    }

    .sv-empty {
      text-align: center;
      padding: 72px 24px;
    }
    .sv-empty-icon { font-size: 3.5rem; margin-bottom: 14px; }
    .sv-empty-title { font-size: 1.2rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
    .sv-empty-sub { color: var(--muted); font-size: .9rem; }

    /* Badges */
    .sv-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: 100px;
      font-size: .75rem;
      font-weight: 700;
    }
    .sv-badge-pending { background: var(--warning-soft); color: #c68a00; }
    .sv-badge-info    { background: var(--blue-soft); color: #4cc9f0; }

    /* ─── 3-SECTION TOOLBAR (Search / Filters / Sort) ─── */
    .sv-tool-form { display: flex; flex-direction: column; gap: 12px; margin-bottom: 22px; }
    .sv-tool-panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 16px 20px;
    }
    .sv-tool-heading {
      display: flex; align-items: center; gap: 8px;
      font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
      color: var(--muted); margin-bottom: 10px;
    }

    .sv-search-pill {
      font-size: .73rem;
      background: var(--purple-soft);
      color: var(--purple);
      padding: 3px 10px;
      border-radius: 100px;
      font-weight: 700;
      margin-left: 8px;
    }

    /* SEARCH */
    .sv-search-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .sv-search-row input[type="text"] {
      flex: 1; min-width: 240px;
      padding: 10px 14px;
      background: var(--input-bg); color: var(--text);
      border: 1.5px solid var(--input-border); border-radius: 10px;
      font-family: inherit; font-size: .9rem; outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .sv-search-row input[type="text"]:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }

    /* FILTERS (collapsible) */
    .sv-tool-filters summary {
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      cursor: pointer; list-style: none; user-select: none;
      font-weight: 700; color: var(--text);
    }
    .sv-tool-filters summary::-webkit-details-marker { display: none; }
    .sv-tool-filters[open] summary { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px dashed var(--line); }
    .sv-filters-grid {
      display: grid; gap: 12px;
      grid-template-columns: repeat(4, 1fr);
    }
    @media (max-width: 900px) { .sv-filters-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 540px) { .sv-filters-grid { grid-template-columns: 1fr; } }
    .sv-filters-grid .span-2 { grid-column: span 2; }
    .sv-tool-field { display: flex; flex-direction: column; gap: 4px; }
    .sv-tool-field label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
    .sv-tool-field input, .sv-tool-field select {
      padding: 9px 12px;
      background: var(--input-bg); border: 1.5px solid var(--input-border);
      border-radius: 9px;
      font-family: inherit; font-size: .875rem; color: var(--text);
      outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .sv-tool-field input:focus, .sv-tool-field select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }
    .sv-filters-actions {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--line);
    }
    .sv-result-count { margin-left: auto; font-size: .82rem; color: var(--muted); font-weight: 600; }

    /* SORT */
    .sv-sort-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .sv-sort-row label { font-weight: 700; color: var(--text); font-size: .9rem; }
    .sv-sort-row select {
      flex: 1; min-width: 240px;
      padding: 9px 12px;
      background: var(--input-bg); color: var(--text);
      border: 1.5px solid var(--input-border); border-radius: 9px;
      font-family: inherit; font-size: .9rem; outline: none;
    }
    .sv-sort-row select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }

    .sv-btn-search {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 9px 18px;
      background: var(--purple, #635bff); color: #fff;
      border: none; border-radius: 10px;
      font-family: inherit; font-size: .85rem; font-weight: 700; cursor: pointer;
    }
    .sv-btn-search:hover { filter: brightness(1.08); }
    .sv-btn-reset {
      padding: 9px 16px;
      background: transparent; color: var(--muted);
      border: 1.5px solid var(--line); border-radius: 10px;
      font-family: inherit; font-size: .85rem; font-weight: 700; text-decoration: none;
    }
    .sv-btn-reset:hover { background: var(--soft-surface); color: var(--text); }
  </style>
</head>
<body data-page="chatbot">
<div class="overlay" data-overlay></div>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-panel">
      <div class="brand-row">
        <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice" /></a>
      </div>
      <div class="sidebar-scroll">
        <div class="nav-section">
          <div class="nav-title">Gestion</div>
          <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
          <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Utilisateurs</span></a>
          <a class="nav-link" href="gestion-brainstormings.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Brainstormings</span></a>
          <a class="nav-link" href="gestion-rendezvous.html" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Rendez-vous</span></a>
          <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Accompagnements</span></a>
          <a class="nav-link" href="gestion-guides.php" data-nav="images"><span class="nav-icon icon-document"></span><span>Guides</span></a>
          <a class="nav-link" href="../frontoffice/copilote.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>💬 ChatBot</span></a>
          <a class="nav-link" href="gestion-documents.html" data-nav="images"><span class="nav-icon icon-image"></span><span>Documents</span></a>
          <a class="nav-link" href="gestion-reclamations.html" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Réclamations</span></a>
          <a class="nav-link" href="settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Paramètres</span></a>
        </div>
      </div>
    </div>
  </aside>

  <main class="page">
    <div class="topbar">
      <div>
        <button class="mobile-toggle" data-nav-toggle aria-label="Menu">☰</button>
        <h1 class="page-title">Gestion des accompagnements</h1>
      </div>
      <div class="toolbar-actions">
        <a class="update-button" href="export-gestion-accompagnements.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" rel="noopener">📄 Export PDF</a>
        <a class="update-button" href="gestion-guides.php">📖 Voir les guides</a>
        <button class="icon-button icon-moon" data-theme-toggle aria-label="Thème"></button>
      </div>
    </div>

    <div class="page-grid">
      <section class="content-section">

        <div style="margin-bottom:20px;">
          <h2 class="section-title">📋 Demandes d'accompagnement</h2>
          <p style="color:var(--muted); font-size:.9rem;">Filtrez les demandes pour examiner chaque dossier et le valider, le refuser ou suivre son évolution.</p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php
        $pieTitle = '📊 Demandes par état de validation (global)';
        $pieTone  = 'dark';
        $pieData  = [
            ['label' => '🕐 En attente', 'value' => $adminPieCounts['en_attente'], 'color' => '#f59e0b'],
            ['label' => '✅ Validées',   'value' => $adminPieCounts['valide'],     'color' => '#10b981'],
            ['label' => '❌ Refusées',   'value' => $adminPieCounts['refuse'],     'color' => '#ef4444'],
        ];
        require __DIR__ . '/../partials/pie_chart.php';
        ?>

        <!-- TOOLBAR — 3 sections: Recherche / Filtres / Tri -->
        <form method="GET" class="sv-tool-form">

          <!-- 1) RECHERCHE -->
          <div class="sv-tool-panel">
            <div class="sv-tool-heading">🔍 Recherche</div>
            <div class="sv-search-row">
              <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($adminFilters['keyword']) ?>" placeholder="Mot-clé (titre, description)…">
              <button type="submit" class="sv-btn-search">Rechercher</button>
            </div>
          </div>

          <!-- 2) FILTRES -->
          <details class="sv-tool-panel sv-tool-filters" <?= $hasActiveFilter ? 'open' : '' ?>>
            <summary>
              <span>🎛 Filtres avancés
                <?php if ($hasActiveFilter): ?>
                  <span class="sv-search-pill">actifs</span>
                <?php endif; ?>
              </span>
              <span class="sv-search-pill">Afficher / masquer</span>
            </summary>

            <div class="sv-filters-grid">
              <div class="sv-tool-field">
                <label for="type">Type de service</label>
                <select id="type" name="type">
                  <option value="">— Tous —</option>
                  <?php foreach ($typeLabels as $val => $info): ?>
                    <option value="<?= $val ?>" <?= $adminFilters['type'] === $val ? 'selected' : '' ?>><?= $info[0] ?> <?= $info[1] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="sv-tool-field">
                <label for="admin_status">Validation Admin</label>
                <select id="admin_status" name="admin_status">
                  <option value="" <?= $adminFilters['admin_status'] === '' ? 'selected' : '' ?>>— Toutes —</option>
                  <option value="en_attente" <?= $adminFilters['admin_status'] === 'en_attente' ? 'selected' : '' ?>>🕐 En attente</option>
                  <option value="valide"     <?= $adminFilters['admin_status'] === 'valide'     ? 'selected' : '' ?>>✅ Validée</option>
                  <option value="refuse"     <?= $adminFilters['admin_status'] === 'refuse'     ? 'selected' : '' ?>>❌ Refusée</option>
                </select>
              </div>

              <div class="sv-tool-field">
                <label for="assistant_status">Validation Assistant</label>
                <select id="assistant_status" name="assistant_status">
                  <option value="">— Toutes —</option>
                  <option value="en_attente" <?= $adminFilters['assistant_status'] === 'en_attente' ? 'selected' : '' ?>>🕐 En attente</option>
                  <option value="accepte"    <?= $adminFilters['assistant_status'] === 'accepte'    ? 'selected' : '' ?>>✅ Acceptée</option>
                  <option value="refuse"     <?= $adminFilters['assistant_status'] === 'refuse'     ? 'selected' : '' ?>>❌ Refusée</option>
                </select>
              </div>

              <div class="sv-tool-field">
                <label for="status">Statut</label>
                <select id="status" name="status">
                  <option value="">— Tous —</option>
                  <option value="soumis"   <?= $adminFilters['status'] === 'soumis'   ? 'selected' : '' ?>>🕐 Soumis</option>
                  <option value="en_cours" <?= $adminFilters['status'] === 'en_cours' ? 'selected' : '' ?>>⚡ En cours</option>
                  <option value="termine"  <?= $adminFilters['status'] === 'termine'  ? 'selected' : '' ?>>✅ Terminé</option>
                  <option value="annule"   <?= $adminFilters['status'] === 'annule'   ? 'selected' : '' ?>>❌ Annulé</option>
                </select>
              </div>

              <div class="sv-tool-field">
                <label for="priority">Priorité</label>
                <select id="priority" name="priority">
                  <option value="">— Toutes —</option>
                  <option value="haute"   <?= $adminFilters['priority'] === 'haute'   ? 'selected' : '' ?>>🔴 Haute</option>
                  <option value="moyenne" <?= $adminFilters['priority'] === 'moyenne' ? 'selected' : '' ?>>🟡 Moyenne</option>
                  <option value="basse"   <?= $adminFilters['priority'] === 'basse'   ? 'selected' : '' ?>>🟢 Basse</option>
                </select>
              </div>

            </div>

            <div class="sv-filters-actions">
              <button type="submit" class="sv-btn-search">Appliquer</button>
              <a href="gestion-accompagnements.php" class="sv-btn-reset">↺ Réinitialiser</a>
              <span class="sv-result-count"><?= count($goals) ?> résultat<?= count($goals) > 1 ? 's' : '' ?></span>
            </div>
          </details>

          <!-- 3) TRI -->
          <div class="sv-tool-panel">
            <div class="sv-tool-heading">↕ Tri</div>
            <div class="sv-sort-row">
              <label for="sort">Trier par :</label>
              <select id="sort" name="sort" onchange="this.form.submit()">
                <option value="created_desc"  <?= $adminFilters['sort'] === 'created_desc'  ? 'selected' : '' ?>>📅 Plus récents</option>
                <option value="created_asc"   <?= $adminFilters['sort'] === 'created_asc'   ? 'selected' : '' ?>>📅 Plus anciens</option>
                <option value="title_asc"     <?= $adminFilters['sort'] === 'title_asc'     ? 'selected' : '' ?>>🔤 Titre (A→Z)</option>
                <option value="title_desc"    <?= $adminFilters['sort'] === 'title_desc'    ? 'selected' : '' ?>>🔤 Titre (Z→A)</option>
                <option value="priority_high" <?= $adminFilters['sort'] === 'priority_high' ? 'selected' : '' ?>>🔥 Priorité (haute → basse)</option>
              </select>
            </div>
          </div>
        </form>

        <?php if (empty($goals)): ?>
          <div class="sv-empty">
            <div class="sv-empty-icon">🎉</div>
            <div class="sv-empty-title"><?= $hasActiveFilter ? 'Aucun résultat' : 'Aucune demande en attente' ?></div>
            <div class="sv-empty-sub"><?= $hasActiveFilter ? 'Aucune demande ne correspond à vos critères.' : 'Toutes les demandes ont été traitées. Revenez plus tard.' ?></div>
          </div>
        <?php else: ?>
          <div class="sv-cards" id="goalsContainer">
            <?php foreach ($goals as $g):
              $typeInfo = $typeLabels[$g['type']] ?? ['📌', $g['type']];
            ?>
            <div class="sv-card" id="card-<?= $g['id'] ?>">
              <div class="sv-card-header">
                <div>
                  <div class="sv-goal-id">#<?= $g['id'] ?> · <?= date('d/m/Y H:i', strtotime($g['created_at'])) ?></div>
                  <div class="sv-goal-title"><?= htmlspecialchars($g['title']) ?></div>
                  <div class="sv-goal-meta">
                    <span>👤 <strong style="color:var(--text)"><?= htmlspecialchars($g['user_name'] ?? '—') ?></strong></span>
                    <span class="sv-meta-sep">|</span>
                    <span>🤝 <?= htmlspecialchars($g['assistant_name'] ?? '—') ?></span>
                  </div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                  <span class="sv-type-chip"><?= $typeInfo[0] ?> <?= $typeInfo[1] ?></span>
                  <?php
                    $adminBadge = [
                      'en_attente' => ['🕐 En attente', 'sv-badge-pending'],
                      'valide'     => ['✅ Validée',    'sv-badge-info'],
                      'refuse'     => ['❌ Refusée',    'sv-badge-pending'],
                    ];
                    $aB = $adminBadge[$g['admin_validation_status']] ?? ['•', 'sv-badge-info'];
                  ?>
                  <span class="sv-badge <?= $aB[1] ?>"><?= $aB[0] ?></span>
                </div>
              </div>

              <div class="sv-card-body">
                <?= nl2br(htmlspecialchars(mb_substr($g['description'], 0, 300) . (mb_strlen($g['description']) > 300 ? '…' : ''))) ?>
              </div>

              <?php if ($g['admin_validation_status'] === 'en_attente'): ?>
              <div class="sv-card-actions">
                <!-- VALIDATE -->
                <div class="sv-action-block">
                  <div class="sv-action-label">✅ Valider la demande</div>
                  <form method="POST" class="action-form" data-decision="valide" data-id="<?= $g['id'] ?>">
                    <input type="hidden" name="action" value="moderate">
                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="decision" value="valide">
                    <textarea name="comment" class="sv-comment-input" rows="2" placeholder="Commentaire optionnel..." maxlength="500"></textarea>
                    <button type="submit" class="sv-btn sv-btn-approve" style="width:100%; margin-top:6px;">✅ Valider et transmettre</button>
                  </form>
                </div>

                <!-- REFUSE -->
                <div class="sv-action-block">
                  <div class="sv-action-label">❌ Refuser la demande</div>
                  <form method="POST" class="action-form refuse-form" data-decision="refuse" data-id="<?= $g['id'] ?>" novalidate>
                    <input type="hidden" name="action" value="moderate">
                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="decision" value="refuse">
                    <textarea name="comment" class="sv-comment-input refuse-reason" rows="2" placeholder="Motif du refus (obligatoire, min. 5 caractères)..." maxlength="500" data-required></textarea>
                    <div class="sv-field-error" id="refuseError-<?= $g['id'] ?>">⚠ Un motif de refus est obligatoire (min. 5 caractères).</div>
                    <button type="submit" class="sv-btn sv-btn-refuse" style="width:100%; margin-top:6px;">❌ Refuser</button>
                  </form>
                </div>
              </div>
              <?php else: ?>
                <div style="padding:14px 24px 20px; font-size:.85rem; color:var(--muted);">
                  <?php if (!empty($g['admin_comment'])): ?>
                    💬 <strong style="color:var(--text)">Commentaire admin :</strong> <?= htmlspecialchars($g['admin_comment']) ?>
                  <?php else: ?>
                    🔒 Cette demande a déjà été traitée. Aucune action de modération disponible.
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </section>
    </div>
  </main>
</div>
<script src="assets/app.js"></script>
<script>
// Defensive: kill any leftover HTML5 native validation on refuse forms
// (in case the browser is rendering a cached copy with `required` still set).
document.querySelectorAll('.refuse-form').forEach(function(form) {
  form.setAttribute('novalidate', 'novalidate');
  form.noValidate = true;
  form.querySelectorAll('[required]').forEach(function(el) {
    el.removeAttribute('required');
  });
});

// JS Validation for refusal reason — replaces the browser's native popup with
// our styled .sv-field-error notification (form has `novalidate`).
var REFUSE_MIN = 5;

function showRefuseError(form, message) {
  var id = form.dataset.id;
  var reasonField = form.querySelector('.refuse-reason');
  var errorEl = document.getElementById('refuseError-' + id);

  if (message) { errorEl.textContent = '⚠ ' + message; }

  reasonField.classList.add('is-invalid');
  // Re-trigger the shake animation even if .visible was already set
  errorEl.classList.remove('visible');
  // Force reflow so the animation restarts on subsequent clicks
  void errorEl.offsetWidth;
  errorEl.classList.add('visible');
  reasonField.focus();
}

function clearRefuseError(form) {
  var id = form.dataset.id;
  form.querySelector('.refuse-reason').classList.remove('is-invalid');
  document.getElementById('refuseError-' + id).classList.remove('visible');
}

document.querySelectorAll('.refuse-form').forEach(function(form) {
  form.addEventListener('submit', function(e) {
    var reasonField = form.querySelector('.refuse-reason');
    var value = reasonField.value.trim();

    if (!value) {
      e.preventDefault();
      showRefuseError(form, 'Un motif de refus est obligatoire (min. ' + REFUSE_MIN + ' caractères).');
      return;
    }
    if (value.length < REFUSE_MIN) {
      e.preventDefault();
      showRefuseError(form, 'Le motif est trop court (min. ' + REFUSE_MIN + ' caractères).');
      return;
    }

    clearRefuseError(form);
    // Disable submit to prevent double submit (page will redirect anyway)
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = '⏳ Envoi…';
  });
});

// Live: clear error as soon as the user types a valid-length value
document.querySelectorAll('.refuse-reason').forEach(function(field) {
  field.addEventListener('input', function() {
    if (this.value.trim().length >= REFUSE_MIN) {
      clearRefuseError(this.closest('form'));
    }
  });
});

// Validate forms with approve action just prevent double-submit
document.querySelectorAll('.sv-btn-approve').forEach(function(btn) {
  btn.addEventListener('click', function() {
    this.disabled = true;
    this.textContent = '⏳ En cours...';
    this.closest('form').submit();
  });
});
</script>
<?php require __DIR__ . '/../partials/confirm-modal.php'; ?>
<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
