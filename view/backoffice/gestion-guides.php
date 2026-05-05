<?php
session_start();
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../controller/GuideController.php";

$_role = strtolower((string)($_SESSION['role'] ?? ''));
$_userRole = strtolower((string)($_SESSION['user_role'] ?? ''));
$isAdmin = ($_role === 'admin') || ($_userRole === 'admin');
if (!$isAdmin) {
  header('Location: /test-login.php?role=admin&next=view/backoffice/gestion-guides.php');
  exit;
}

// Backward-compat: legacy ?type=...&msg=... URL params → session flash.
$_legacyMsg = trim((string) ($_GET['msg'] ?? ''));
if ($_legacyMsg !== '') {
    $_legacyType = strtolower((string) ($_GET['type'] ?? 'info'));
    $_SESSION['flash'] = [
        'type'    => in_array($_legacyType, ['success','error','info','warning'], true) ? $_legacyType : 'info',
        'message' => $_legacyMsg,
    ];
}

$guideController = new GuideController();

// === ADVANCED SEARCH FILTERS ===
$gFilters = [
    'keyword'     => trim((string)($_GET['keyword'] ?? '')),
    'goal_id'     => $_GET['goal_id'] ?? '',
    'goal_type'   => $_GET['goal_type'] ?? '',
    'goal_status' => $_GET['goal_status'] ?? '',
    'sort'        => $_GET['sort'] ?? 'created_desc',
];
$hasActiveFilter = !empty($gFilters['keyword']) || !empty($gFilters['goal_id']) || !empty($gFilters['goal_type'])
    || !empty($gFilters['goal_status'])
    || ($gFilters['sort'] !== 'created_desc');

$guides = $hasActiveFilter ? $guideController->searchGuides($gFilters) : $guideController->getAllGuides();

// Group by goal
$byGoal = [];
foreach ($guides as $g) {
    $byGoal[$g['goal_id']][] = $g;
}

$total = count($guides);
$goals_count = count($byGoal);

$gTypeLabels = [
    'cv'           => '📄 CV',
    'cover_letter' => '✉️ Lettre',
    'linkedin'     => '🔗 LinkedIn',
    'interview'    => '🎤 Entretien',
    'other'        => '📌 Autre',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gestion des guides | Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
  <style>
    .sv-stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
      margin-bottom: 28px;
    }
    .sv-stat-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 18px 20px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .sv-stat-label { font-size: .77rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
    .sv-stat-val   { font-size: 1.8rem; font-weight: 800; color: var(--text); }
    .sv-stat-chip  { font-size: .73rem; }

    /* ─── 3-SECTION TOOLBAR ─── */
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

    .sv-goal-group {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 18px;
      margin-bottom: 16px;
      overflow: hidden;
      transition: box-shadow .2s;
    }
    .sv-goal-group:hover { box-shadow: 0 8px 32px rgba(0,0,0,.2); }

    .sv-goal-group-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 24px;
      cursor: pointer;
      gap: 14px;
      flex-wrap: wrap;
      border-bottom: 1px solid var(--line);
    }
    .sv-goal-group-header:hover { background: var(--soft-surface); }

    .sv-goal-info { flex: 1; }
    .sv-goal-group-id { font-size: .73rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 3px; }
    .sv-goal-group-title { font-size: 1rem; font-weight: 700; color: var(--text); }
    .sv-goal-group-meta { font-size: .82rem; color: var(--muted); margin-top: 3px; }

    .sv-goal-group-chips { display: flex; gap: 7px; flex-wrap: wrap; align-items: center; }
    .sv-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 4px 12px; border-radius: 100px; font-size: .77rem; font-weight: 700;
    }
    .sv-chip-purple { background: var(--purple-soft); color: var(--purple); }
    .sv-chip-blue   { background: var(--blue-soft); color: #4cc9f0; }
    .sv-chip-green  { background: var(--green-soft); color: #31d0aa; }
    .sv-collapse-arrow { font-size: .85rem; color: var(--muted); transition: transform .2s; }
    .sv-goal-group.open .sv-collapse-arrow { transform: rotate(90deg); }

    .sv-guides-list {
      display: none;
      padding: 16px 24px 20px;
    }
    .sv-goal-group.open .sv-guides-list { display: block; }

    .sv-guide-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 14px 0;
      border-bottom: 1px solid var(--line);
    }
    .sv-guide-row:last-child { border-bottom: none; padding-bottom: 0; }
    .sv-guide-step-num {
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--purple); color: #fff;
      font-size: .78rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .sv-guide-content { flex: 1; }
    .sv-guide-title { font-size: .9rem; font-weight: 700; color: var(--text); margin-bottom: 5px; }
    .sv-guide-text  { font-size: .835rem; color: var(--muted); line-height: 1.55; }
    .sv-guide-date  { font-size: .75rem; color: var(--muted); margin-top: 5px; }
    .sv-guide-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .sv-btn-view {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 6px 14px; border-radius: 8px;
      background: var(--purple-soft); color: var(--purple);
      font-family: inherit; font-size: .8rem; font-weight: 700;
      text-decoration: none; border: 1px solid rgba(99,91,255,.2);
      transition: background .15s;
    }
    .sv-btn-view:hover { background: rgba(99,91,255,.25); }

    .sv-empty {
      text-align: center; padding: 72px 24px;
    }
    .sv-empty-icon  { font-size: 3.5rem; margin-bottom: 14px; }
    .sv-empty-title { font-size: 1.2rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
    .sv-empty-sub   { color: var(--muted); font-size: .9rem; }

    .sv-no-results { text-align: center; padding: 40px; color: var(--muted); }

    .notice {
      border-radius: 10px;
      padding: 11px 14px;
      margin-bottom: 14px;
      border: 1px solid transparent;
      font-weight: 600;
    }
    .notice-success { background: #e9f8ee; border-color: #b9e6c7; color: #1e6a39; }
    .notice-error { background: #fdecee; border-color: #f5c1c7; color: #8b1f2b; }
    .notice-info { background: #edf4ff; border-color: #cfe0ff; color: #1d4e9a; }
  </style>
</head>
<body data-page="images">
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
          <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Accompagnements</span></a>
          <a class="nav-link" href="gestion-guides.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Guides</span></a>
          <a class="nav-link" href="../frontoffice/copilote.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>💬 ChatBot</span></a>
          <a class="nav-link" href="settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Paramètres</span></a>
        </div>
      </div>
    </div>
  </aside>

  <main class="page">
    <div class="topbar">
      <div>
        <button class="mobile-toggle" data-nav-toggle aria-label="Menu">☰</button>
        <h1 class="page-title">Guides d'accompagnement</h1>
      </div>
      <div class="toolbar-actions">
        <a class="update-button" href="export-gestion-guides.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" rel="noopener">📄 Export PDF</a>
        <a class="update-button" href="gestion-accompagnements.php">← Accompagnements</a>
        <button class="icon-button icon-moon" data-theme-toggle aria-label="Thème"></button>
      </div>
    </div>

    <div class="page-grid">
      <section class="content-section">

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <div class="sv-stats-row">
          <div class="sv-stat-card">
            <div class="sv-stat-label">Total guides</div>
            <div class="sv-stat-val"><?= $total ?></div>
          </div>
          <div class="sv-stat-card">
            <div class="sv-stat-label">Missions couvertes</div>
            <div class="sv-stat-val"><?= $goals_count ?></div>
          </div>
          <div class="sv-stat-card">
            <div class="sv-stat-label">Moy. étapes / mission</div>
            <div class="sv-stat-val"><?= $goals_count > 0 ? round($total / $goals_count, 1) : 0 ?></div>
          </div>
        </div>

        <!-- TOOLBAR — 3 sections: Recherche / Filtres / Tri -->
        <form method="GET" class="sv-tool-form">

          <!-- 1) RECHERCHE -->
          <div class="sv-tool-panel">
            <div class="sv-tool-heading">🔍 Recherche</div>
            <div class="sv-search-row">
              <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($gFilters['keyword']) ?>" placeholder="Mot-clé (titre, contenu, accompagnement)…">
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
                <label for="goal_id">N° d'accompagnement</label>
                <input type="number" id="goal_id" name="goal_id" min="1" value="<?= htmlspecialchars((string)$gFilters['goal_id']) ?>" placeholder="Ex: 12">
              </div>

              <div class="sv-tool-field">
                <label for="goal_type">Type d'accompagnement</label>
                <select id="goal_type" name="goal_type">
                  <option value="">— Tous —</option>
                  <?php foreach ($gTypeLabels as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $gFilters['goal_type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="sv-tool-field">
                <label for="goal_status">Statut accompagnement</label>
                <select id="goal_status" name="goal_status">
                  <option value="">— Tous —</option>
                  <option value="soumis"   <?= $gFilters['goal_status'] === 'soumis'   ? 'selected' : '' ?>>🕐 Soumis</option>
                  <option value="en_cours" <?= $gFilters['goal_status'] === 'en_cours' ? 'selected' : '' ?>>⚡ En cours</option>
                  <option value="termine"  <?= $gFilters['goal_status'] === 'termine'  ? 'selected' : '' ?>>✅ Terminé</option>
                  <option value="annule"   <?= $gFilters['goal_status'] === 'annule'   ? 'selected' : '' ?>>❌ Annulé</option>
                </select>
              </div>

            </div>

            <div class="sv-filters-actions">
              <button type="submit" class="sv-btn-search">Appliquer</button>
              <a href="gestion-guides.php" class="sv-btn-reset">↺ Réinitialiser</a>
              <span class="sv-result-count"><?= $total ?> résultat<?= $total > 1 ? 's' : '' ?></span>
            </div>
          </details>

          <!-- 3) TRI -->
          <div class="sv-tool-panel">
            <div class="sv-tool-heading">↕ Tri</div>
            <div class="sv-sort-row">
              <label for="sort">Trier par :</label>
              <select id="sort" name="sort" onchange="this.form.submit()">
                <option value="created_desc" <?= $gFilters['sort'] === 'created_desc' ? 'selected' : '' ?>>📅 Plus récents</option>
                <option value="created_asc"  <?= $gFilters['sort'] === 'created_asc'  ? 'selected' : '' ?>>📅 Plus anciens</option>
                <option value="title_asc"    <?= $gFilters['sort'] === 'title_asc'    ? 'selected' : '' ?>>🔤 Titre (A→Z)</option>
                <option value="title_desc"   <?= $gFilters['sort'] === 'title_desc'   ? 'selected' : '' ?>>🔤 Titre (Z→A)</option>
              </select>
            </div>
          </div>
        </form>

        <?php if (empty($guides)): ?>
          <div class="sv-empty">
            <div class="sv-empty-icon">📚</div>
            <div class="sv-empty-title"><?= $hasActiveFilter ? 'Aucun résultat' : 'Aucun guide créé' ?></div>
            <div class="sv-empty-sub"><?= $hasActiveFilter ? 'Aucun guide ne correspond à vos critères. Essayez d\'élargir votre recherche.' : 'Les guides apparaîtront ici une fois que les assistants auront démarré leurs missions.' ?></div>
          </div>
        <?php else: ?>

          <div id="guideGroups">
            <?php foreach ($byGoal as $goalId => $goalGuides):
              $first = $goalGuides[0];
            ?>
            <div class="sv-goal-group guide-group" data-search="<?= strtolower(htmlspecialchars($first['goal_desc'] . ' ' . ($first['nom'] ?? '') . ' ' . ($first['assistant_nom'] ?? ''))) ?>">
              <div class="sv-goal-group-header" onclick="toggleGroup(this)">
                <div class="sv-goal-info">
                  <div class="sv-goal-group-id">Accompagnement #<?= $goalId ?></div>
                  <div class="sv-goal-group-title"><?= htmlspecialchars(mb_substr($first['goal_desc'] ?? 'Sans titre', 0, 80)) ?></div>
                  <div class="sv-goal-group-meta">
                    👤 <?= htmlspecialchars($first['nom'] ?? '—') ?>
                    &nbsp;·&nbsp;
                    🤝 <?= htmlspecialchars($first['assistant_nom'] ?? 'Assistant') ?>
                  </div>
                </div>
                <div class="sv-goal-group-chips">
                  <span class="sv-chip sv-chip-blue"><?= count($goalGuides) ?> étape<?= count($goalGuides) > 1 ? 's' : '' ?></span>
                  <span class="sv-collapse-arrow">▶</span>
                </div>
              </div>

              <div class="sv-guides-list">
                <?php $step = 1; foreach ($goalGuides as $guide): ?>
                  <div class="sv-guide-row">
                    <div class="sv-guide-step-num"><?= $step++ ?></div>
                    <div class="sv-guide-content">
                      <div class="sv-guide-title"><?= htmlspecialchars($guide['title']) ?></div>
                      <div class="sv-guide-text"><?= nl2br(htmlspecialchars(mb_substr($guide['content'], 0, 200) . (mb_strlen($guide['content']) > 200 ? '…' : ''))) ?></div>
                      <div class="sv-guide-date">📅 <?= date('d/m/Y H:i', strtotime($guide['created_at'])) ?></div>
                    </div>
                    <div class="sv-guide-actions">
                      <a href="gestion-guides-details.php?id=<?= $guide['id'] ?>" class="sv-btn-view">👁 Détails</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
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
function toggleGroup(header) {
  var group = header.parentElement;
  group.classList.toggle('open');
}

// Open all groups when an active filter is in play (so matching guides are visible)
var hasFilter = <?= $hasActiveFilter ? 'true' : 'false' ?>;
if (hasFilter) {
  document.querySelectorAll('.sv-goal-group').forEach(function(g){ g.classList.add('open'); });
} else {
  var first = document.querySelector('.sv-goal-group');
  if (first) first.classList.add('open');
}
</script>
<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
