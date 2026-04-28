<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';
require_once __DIR__ . '/../../controller/GuideController.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$goalCtrl  = new GoalController();
$guideCtrl = new GuideController();

$success = null;
$error   = null;

if (isset($_GET['deleted'])) {
    $success = $_GET['deleted'] === '1' ? "Demande supprimée avec succès." : null;
    $error   = $_GET['deleted'] !== '1' ? "Suppression impossible (demande déjà prise en charge)." : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_goal') {
    $goal_id = (int)$_POST['goal_id'];
    if ($goalCtrl->deleteGoal($goal_id, $user_id)) {
        $success = "Demande supprimée avec succès.";
    } else {
        $error = "Impossible de supprimer (déjà prise en charge).";
    }
}

try {
    $my_goals  = $goalCtrl->getGoalsByUser($user_id);
    $my_guides = $guideCtrl->getGuidesByUser($user_id);
} catch (Exception $e) {
    $my_goals = $my_guides = [];
}

$statusMap = [
    'soumis'    => ['label' => 'Soumis',      'class' => 'status-pending',  'icon' => '🕐'],
    'en_cours'  => ['label' => 'En cours',    'class' => 'status-active',   'icon' => '⚡'],
    'termine'   => ['label' => 'Terminé',     'class' => 'status-done',     'icon' => '✅'],
    'annule'    => ['label' => 'Annulé',      'class' => 'status-refused',  'icon' => '❌'],
];
$adminMap = [
    'en_attente' => ['label' => 'En attente admin',   'class' => 'tag-warning'],
    'valide'     => ['label' => 'Validé par admin',   'class' => 'tag-success'],
    'refuse'     => ['label' => 'Refusé par admin',   'class' => 'tag-danger'],
];
$assistantMap = [
    'en_attente' => ['label' => 'En attente assistant',   'class' => 'tag-info'],
    'accepte'    => ['label' => 'Accepté',                'class' => 'tag-success'],
    'refuse'     => ['label' => 'Refusé par assistant',   'class' => 'tag-danger'],
];
$typeLabels = [
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
  <title>Mes accompagnements | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
  <style>
    :root {
      --bg: #f0f4ff;
      --surface: #ffffff;
      --surface2: #f7f9ff;
      --border: #e2e8f8;
      --text: #0f1629;
      --muted: #6b7a9f;
      --accent: #5046e5;
      --accent-soft: #eeeeff;
      --danger: #ef4444;
      --danger-soft: #fef2f2;
      --success: #10b981;
      --success-soft: #ecfdf5;
      --warning: #f59e0b;
      --warning-soft: #fffbeb;
      --info: #3b82f6;
      --info-soft: #eff6ff;
      --radius: 14px;
      --shadow: 0 4px 24px rgba(80,70,229,0.08);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    .site-header {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 0 32px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 12px rgba(80,70,229,0.06);
    }
    .header-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 800;
      font-size: 1.1rem;
      color: var(--accent);
      text-decoration: none;
    }
    .header-brand img { height: 32px; width: auto; }
    .header-nav { display: flex; align-items: center; gap: 8px; }
    .nav-link {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--muted);
      text-decoration: none;
      transition: all .15s;
    }
    .nav-link:hover { color: var(--text); background: var(--surface2); }
    .nav-link.active { color: var(--accent); background: var(--accent-soft); font-weight: 600; }

    .page-wrapper { max-width: 960px; margin: 0 auto; padding: 40px 24px 80px; }

    .page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 32px;
      flex-wrap: wrap;
      gap: 16px;
    }
    .page-title { font-size: 1.9rem; font-weight: 800; margin-bottom: 6px; }
    .page-subtitle { color: var(--muted); font-size: 0.95rem; }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 22px;
      border-radius: 10px;
      font-family: inherit;
      font-size: 0.875rem;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: all .2s;
      text-decoration: none;
    }
    .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 4px 14px rgba(80,70,229,.3); }
    .btn-primary:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .btn-ghost { background: var(--surface2); color: var(--text); border: 1.5px solid var(--border); }
    .btn-ghost:hover { background: var(--border); }
    .btn-danger-ghost { background: var(--danger-soft); color: var(--danger); border: 1.5px solid #fecaca; }
    .btn-danger-ghost:hover { background: #fee2e2; }
    .btn-sm { padding: 7px 14px; font-size: 0.8rem; }

    .alert {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      border-radius: var(--radius);
      font-size: 0.9rem;
      margin-bottom: 24px;
      animation: fadeIn .3s ease;
    }
    .alert-success { background: var(--success-soft); color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error   { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

    /* TABS */
    .tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--border); margin-bottom: 28px; }
    .tab-btn {
      padding: 10px 20px;
      font-family: inherit;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--muted);
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      cursor: pointer;
      transition: all .15s;
    }
    .tab-btn:hover { color: var(--text); }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
    .tab-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 100px;
      font-size: 0.72rem;
      font-weight: 700;
      background: var(--accent-soft);
      color: var(--accent);
      margin-left: 6px;
    }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }

    /* GOAL CARDS */
    .goal-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 24px 28px;
      margin-bottom: 16px;
      box-shadow: var(--shadow);
      transition: box-shadow .2s, transform .2s;
      position: relative;
      overflow: hidden;
    }
    .goal-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 4px;
      background: var(--accent);
      border-radius: 4px 0 0 4px;
    }
    .goal-card.status-done::before  { background: var(--success); }
    .goal-card.status-refused::before { background: var(--danger); }
    .goal-card.status-active::before { background: var(--info); }
    .goal-card:hover { box-shadow: 0 8px 32px rgba(80,70,229,0.13); transform: translateY(-2px); }

    .goal-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 12px;
    }
    .goal-title-row { flex: 1; }
    .goal-type-tag {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: 100px;
      font-size: 0.75rem;
      font-weight: 700;
      background: var(--accent-soft);
      color: var(--accent);
      margin-bottom: 6px;
    }
    .goal-title {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .goal-assistant {
      font-size: 0.825rem;
      color: var(--muted);
    }
    .goal-assistant strong { color: var(--text); }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 100px;
      font-size: 0.78rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .status-pending  { background: var(--warning-soft); color: #92400e; }
    .status-active   { background: var(--info-soft); color: #1d4ed8; }
    .status-done     { background: var(--success-soft); color: #065f46; }
    .status-refused  { background: var(--danger-soft); color: #991b1b; }

    .goal-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }
    .tag {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: 100px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .tag-warning  { background: var(--warning-soft); color: #92400e; }
    .tag-success  { background: var(--success-soft); color: #065f46; }
    .tag-danger   { background: var(--danger-soft);  color: #991b1b; }
    .tag-info     { background: var(--info-soft);    color: #1d4ed8; }
    .tag-neutral  { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }

    .goal-description {
      font-size: 0.875rem;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 16px;
    }

    .goal-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .goal-date {
      margin-left: auto;
      font-size: 0.775rem;
      color: var(--muted);
    }

    /* COMMENT BOXES */
    .comment-block {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 16px;
      margin-top: 12px;
      font-size: 0.85rem;
    }
    .comment-block-label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--muted);
      margin-bottom: 5px;
    }

    /* GUIDES SECTION */
    .guides-toggle {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      font-size: 0.8rem;
      font-weight: 600;
      background: var(--info-soft);
      color: var(--info);
      border: 1px solid #bfdbfe;
      border-radius: 8px;
      cursor: pointer;
      border: none;
      font-family: inherit;
      transition: background .15s;
    }
    .guides-toggle:hover { background: #dbeafe; }

    .guides-section {
      margin-top: 18px;
      border-top: 1px dashed var(--border);
      padding-top: 18px;
      display: none;
    }
    .guides-section.open { display: block; }

    .guide-step {
      display: flex;
      gap: 16px;
      margin-bottom: 14px;
    }
    .guide-step-num {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: var(--accent);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .guide-step-content { flex: 1; }
    .guide-step-title {
      font-size: 0.9rem;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .guide-step-text {
      font-size: 0.85rem;
      color: var(--muted);
      line-height: 1.55;
    }

    /* EMPTY STATE */
    .empty-state {
      text-align: center;
      padding: 60px 24px;
    }
    .empty-icon { font-size: 3.5rem; margin-bottom: 16px; }
    .empty-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; }
    .empty-sub { color: var(--muted); margin-bottom: 24px; font-size: 0.95rem; }

    /* DELETE CONFIRM MODAL */
    .modal-backdrop {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 200;
      display: none;
      align-items: center;
      justify-content: center;
    }
    .modal-backdrop.open { display: flex; }
    .modal {
      background: var(--surface);
      border-radius: 20px;
      padding: 32px;
      max-width: 420px;
      width: 90%;
      box-shadow: 0 24px 60px rgba(0,0,0,.2);
      animation: popIn .25s ease;
    }
    @keyframes popIn {
      from { opacity: 0; transform: scale(.92); }
      to { opacity: 1; transform: scale(1); }
    }
    .modal-icon { font-size: 2.5rem; margin-bottom: 12px; }
    .modal-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; }
    .modal-text { color: var(--muted); font-size: 0.9rem; margin-bottom: 24px; line-height: 1.5; }
    .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }

    @media (max-width: 600px) {
      .page-header { flex-direction: column; }
      .goal-header { flex-direction: column; }
      .goal-date { margin-left: 0; }
    }
  </style>
</head>
<body>

<header class="site-header">
  <a href="index.html" class="header-brand">
    <img src="assets/media/secondvoice-logo.png" alt="SecondVoice" onerror="this.style.display='none'" />
    SecondVoice
  </a>
  <nav class="header-nav">
    <a href="index.html" class="nav-link">Accueil</a>
    <a href="mes-accompagnements.php" class="nav-link active">Mes accompagnements</a>
    <a href="service-accompagnement.php" class="nav-link">Nouvelle demande</a>
    <a href="profile.php" class="nav-link">Mon profil</a>
  </nav>
</header>

<div class="page-wrapper">

  <div class="page-header">
    <div>
      <h1 class="page-title">📋 Mes accompagnements</h1>
      <p class="page-subtitle">Suivez l'état de vos demandes et consultez les guides de vos assistants.</p>
    </div>
    <a href="service-accompagnement.php" class="btn btn-primary">+ Nouvelle demande</a>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-goals" onclick="switchTab(this)">
      Mes demandes <span class="tab-count"><?= count($my_goals) ?></span>
    </button>
    <button class="tab-btn" data-tab="tab-guides" onclick="switchTab(this)">
      Mes guides <span class="tab-count"><?= count($my_guides) ?></span>
    </button>
  </div>

  <!-- TAB: GOALS -->
  <div id="tab-goals" class="tab-pane active">
    <?php if (empty($my_goals)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <div class="empty-title">Aucune demande pour le moment</div>
        <div class="empty-sub">Soumettez votre première demande d'accompagnement professionnel.</div>
        <a href="service-accompagnement.php" class="btn btn-primary">🚀 Créer une demande</a>
      </div>
    <?php else: ?>
      <?php foreach ($my_goals as $g):
        $sData   = $statusMap[$g['status']] ?? ['label' => $g['status'], 'class' => 'status-pending', 'icon' => '•'];
        $aData   = $adminMap[$g['admin_validation_status']] ?? ['label' => $g['admin_validation_status'], 'class' => 'tag-neutral'];
        $assData = $assistantMap[$g['assistant_validation_status']] ?? ['label' => $g['assistant_validation_status'], 'class' => 'tag-neutral'];
        $canEdit = $g['admin_validation_status'] === 'en_attente';
        $typeLabel = $typeLabels[$g['type']] ?? $g['type'];
        // Guides for this goal
        $goalGuides = array_filter($my_guides, fn($gg) => $gg['goal_id'] == $g['id']);
      ?>
      <div class="goal-card <?= $sData['class'] ?>">
        <div class="goal-header">
          <div class="goal-title-row">
            <span class="goal-type-tag"><?= $typeLabel ?></span>
            <div class="goal-title"><?= htmlspecialchars($g['title']) ?></div>
            <div class="goal-assistant">Assistant : <strong><?= htmlspecialchars($g['assistant_name'] ?? 'Non assigné') ?></strong></div>
          </div>
          <span class="status-badge <?= $sData['class'] ?>"><?= $sData['icon'] ?> <?= $sData['label'] ?></span>
        </div>

        <div class="goal-meta">
          <span class="tag <?= $aData['class'] ?>">🛡 <?= $aData['label'] ?></span>
          <span class="tag <?= $assData['class'] ?>">👤 <?= $assData['label'] ?></span>
          <span class="goal-date">📅 <?= date('d/m/Y', strtotime($g['created_at'])) ?></span>
        </div>

        <div class="goal-description">
          <?= nl2br(htmlspecialchars(mb_substr($g['description'], 0, 200) . (mb_strlen($g['description']) > 200 ? '…' : ''))) ?>
        </div>

        <?php if (!empty($g['admin_comment'])): ?>
          <div class="comment-block">
            <div class="comment-block-label">💬 Commentaire Admin</div>
            <?= htmlspecialchars($g['admin_comment']) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($g['assistant_comment'])): ?>
          <div class="comment-block">
            <div class="comment-block-label">💬 Commentaire Assistant</div>
            <?= htmlspecialchars($g['assistant_comment']) ?>
          </div>
        <?php endif; ?>

        <div class="goal-actions">
          <?php if ($canEdit): ?>
            <a href="service-accompagnement.php?action=edit&id=<?= $g['id'] ?>" class="btn btn-ghost btn-sm">✏️ Modifier</a>
            <button class="btn btn-danger-ghost btn-sm" onclick="confirmDelete(<?= $g['id'] ?>)">🗑 Supprimer</button>
          <?php else: ?>
            <span class="tag tag-neutral" style="font-size:.78rem;">🔒 Modification non disponible</span>
          <?php endif; ?>

          <?php if (!empty($goalGuides)): ?>
            <button class="guides-toggle" onclick="toggleGuides(<?= $g['id'] ?>, this)">
              📖 Voir les guides (<?= count($goalGuides) ?>)
            </button>
          <?php endif; ?>
        </div>

        <?php if (!empty($goalGuides)): ?>
          <div class="guides-section" id="guides-<?= $g['id'] ?>">
            <?php $step = 1; foreach ($goalGuides as $guide): ?>
              <div class="guide-step">
                <div class="guide-step-num"><?= $step++ ?></div>
                <div class="guide-step-content">
                  <div class="guide-step-title"><?= htmlspecialchars($guide['title']) ?></div>
                  <div class="guide-step-text"><?= nl2br(htmlspecialchars($guide['content'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- TAB: GUIDES -->
  <div id="tab-guides" class="tab-pane">
    <?php if (empty($my_guides)): ?>
      <div class="empty-state">
        <div class="empty-icon">📚</div>
        <div class="empty-title">Aucun guide disponible</div>
        <div class="empty-sub">Vos assistants créeront des guides une fois vos demandes acceptées.</div>
      </div>
    <?php else: ?>
      <?php
        $groupedGuides = [];
        foreach ($my_guides as $guide) {
            $groupedGuides[$guide['goal_id']][] = $guide;
        }
        foreach ($groupedGuides as $goalId => $guides):
          $g0 = $guides[0];
      ?>
      <div class="goal-card">
        <div class="goal-header" style="margin-bottom:18px">
          <div class="goal-title-row">
            <div class="goal-type-tag">📖 Guides</div>
            <div class="goal-title"><?= htmlspecialchars($g0['goal_desc'] ?? 'Accompagnement #' . $goalId) ?></div>
            <div class="goal-assistant">Assistant : <strong><?= htmlspecialchars($g0['assistant_nom'] ?? '—') ?></strong></div>
          </div>
          <span class="tag tag-info"><?= count($guides) ?> étape<?= count($guides) > 1 ? 's' : '' ?></span>
        </div>

        <?php $step = 1; foreach ($guides as $guide): ?>
          <div class="guide-step">
            <div class="guide-step-num"><?= $step++ ?></div>
            <div class="guide-step-content">
              <div class="guide-step-title"><?= htmlspecialchars($guide['title']) ?></div>
              <div class="guide-step-text"><?= nl2br(htmlspecialchars($guide['content'])) ?></div>
              <div style="font-size:.75rem; color:var(--muted); margin-top:5px;">
                📅 <?= date('d/m/Y', strtotime($guide['created_at'])) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal-backdrop" id="deleteModal">
  <div class="modal">
    <div class="modal-icon">🗑️</div>
    <div class="modal-title">Supprimer cette demande ?</div>
    <div class="modal-text">Cette action est irréversible. La demande sera définitivement supprimée. Vous ne pouvez supprimer qu'une demande encore en attente de validation.</div>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action" value="delete_goal">
      <input type="hidden" name="goal_id" id="deleteGoalId">
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn btn-primary" style="background: var(--danger); box-shadow: 0 4px 14px rgba(239,68,68,.3);">Oui, supprimer</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(btn.dataset.tab).classList.add('active');
}

function toggleGuides(goalId, btn) {
  const section = document.getElementById('guides-' + goalId);
  const isOpen = section.classList.toggle('open');
  btn.textContent = isOpen
    ? '📖 Masquer les guides'
    : '📖 Voir les guides (' + section.querySelectorAll('.guide-step').length + ')';
}

function confirmDelete(goalId) {
  document.getElementById('deleteGoalId').value = goalId;
  document.getElementById('deleteModal').classList.add('open');
}

function closeModal() {
  document.getElementById('deleteModal').classList.remove('open');
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Keyboard: Escape closes modal
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>
