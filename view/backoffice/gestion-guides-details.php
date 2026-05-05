<?php
session_start();
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../controller/GuideController.php";

// Backward-compat: legacy ?type=...&msg=... URL params → session flash.
$_legacyMsg = trim((string) ($_GET['msg'] ?? ''));
if ($_legacyMsg !== '') {
    $_legacyType = strtolower((string) ($_GET['type'] ?? 'info'));
    $_SESSION['flash'] = [
        'type'    => in_array($_legacyType, ['success','error','info','warning'], true) ? $_legacyType : 'info',
        'message' => $_legacyMsg,
    ];
}

function redirect_with_notice(string $path, string $type, string $message): void
{
    $allowed = ['success', 'error', 'info', 'warning'];
    $_SESSION['flash'] = [
        'type'    => in_array($type, $allowed, true) ? $type : 'info',
        'message' => $message,
    ];
    header('Location: ' . $path);
    exit;
}

// Ensure admin access
$_role     = strtolower((string) ($_SESSION['role']      ?? ''));
$_userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
$isAdmin   = ($_role === 'admin') || ($_userRole === 'admin');
if (!$isAdmin) {
    $next = isset($_GET['id']) ? ('view/backoffice/gestion-guides-details.php?id=' . urlencode((string) $_GET['id'])) : 'view/backoffice/gestion-guides.php';
    $msg = urlencode('Accès réservé aux administrateurs.');
    header('Location: /test-login.php?role=admin&next=' . $next . '&type=error&msg=' . $msg);
    exit;
}

if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
    redirect_with_notice('gestion-guides.php', 'error', 'ID du guide manquant.');
}

$guideId         = (int)$_GET['id'];
$guideController = new GuideController();
$guide           = $guideController->getGuideWithContext($guideId);

if (!$guide) {
    redirect_with_notice('gestion-guides.php', 'error', 'Guide introuvable.');
}

// Sibling steps in the same mission (for the "all steps" sidebar list)
$siblingGuides = $guideController->getGuidesByGoal((int)$guide['goal_id']);

// Display-friendly maps
$typeMap = [
    'cv'           => ['📄', 'CV'],
    'cover_letter' => ['✉️', 'Lettre de motivation'],
    'linkedin'     => ['🔗', 'LinkedIn'],
    'interview'    => ['🎤', 'Entretien'],
    'other'        => ['📌', 'Autre'],
];
$statusMap = [
    'soumis'   => ['🕐', 'Soumis'],
    'en_cours' => ['⚡', 'En cours'],
    'termine'  => ['✅', 'Terminé'],
    'annule'   => ['❌', 'Annulé'],
];
$priorityMap = [
    'basse'   => ['🟢', 'Basse'],
    'moyenne' => ['🟡', 'Moyenne'],
    'haute'   => ['🔴', 'Haute'],
];
$adminMap = [
    'en_attente' => 'En attente',
    'valide'     => 'Validée',
    'refuse'     => 'Refusée',
];
$assistantMap = [
    'en_attente' => 'En attente',
    'accepte'    => 'Acceptée',
    'refuse'     => 'Refusée',
];

$type     = $typeMap[$guide['goal_type']]      ?? ['📌', $guide['goal_type']];
$status   = $statusMap[$guide['goal_status']]  ?? ['•', $guide['goal_status']];
$priority = $priorityMap[$guide['goal_priority']] ?? ['•', $guide['goal_priority'] ?? '—'];

$citizenName   = trim(($guide['user_prenom'] ?? '') . ' ' . ($guide['user_nom'] ?? '')) ?: '—';
$assistantName = trim(($guide['assistant_prenom'] ?? '') . ' ' . ($guide['assistant_nom'] ?? '')) ?: '—';

$stepNumber = 1;
foreach ($siblingGuides as $i => $s) {
    if ((int)$s['id'] === $guideId) { $stepNumber = $i + 1; break; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Détails du guide — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
  <style>
    .gd-back {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px;
      background: var(--soft-surface);
      color: var(--muted);
      border: 1px solid var(--line);
      border-radius: 10px;
      font-family: inherit; font-size: .82rem; font-weight: 700;
      text-decoration: none;
      margin-bottom: 18px;
      transition: all .15s;
    }
    .gd-back:hover { background: var(--purple-soft); color: var(--purple); border-color: var(--purple); }

    .gd-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 20px 24px;
      margin-bottom: 16px;
    }
    .gd-card-title {
      font-size: .73rem; font-weight: 700; letter-spacing: .07em;
      text-transform: uppercase; color: var(--muted);
      margin-bottom: 12px;
      display: flex; align-items: center; gap: 8px;
    }

    .gd-mission-id {
      font-size: .73rem; font-weight: 700; letter-spacing: .06em;
      text-transform: uppercase; color: var(--muted);
      margin-bottom: 4px;
    }
    .gd-mission-title { font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
    .gd-mission-desc {
      font-size: .9rem; color: var(--muted); line-height: 1.6;
      margin: 12px 0 16px;
      padding: 12px 16px;
      background: var(--soft-surface);
      border-left: 3px solid var(--purple);
      border-radius: 0 10px 10px 0;
    }

    .gd-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .gd-chip {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 5px 12px;
      border-radius: 100px;
      font-size: .77rem; font-weight: 700;
    }
    .gd-chip-purple  { background: var(--purple-soft); color: var(--purple); }
    .gd-chip-blue    { background: var(--blue-soft); color: #4cc9f0; }
    .gd-chip-green   { background: var(--green-soft); color: #1fa87b; }
    .gd-chip-yellow  { background: var(--warning-soft); color: #c68a00; }
    .gd-chip-red     { background: rgba(255,107,107,.14); color: #cc3333; }
    .gd-chip-gray    { background: var(--soft-surface); color: var(--muted); border: 1px solid var(--line); }

    .gd-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 16px;
    }
    @media (max-width: 720px) { .gd-grid-2 { grid-template-columns: 1fr; } }

    .gd-party-name { font-size: 1rem; font-weight: 800; color: var(--text); margin-bottom: 4px; }
    .gd-party-mail { font-size: .85rem; color: var(--muted); word-break: break-all; }

    .gd-step-card {
      background: linear-gradient(135deg, var(--purple-soft), var(--soft-surface));
      border: 2px solid var(--purple);
      border-radius: 18px;
      padding: 24px 28px;
      margin-bottom: 16px;
      box-shadow: 0 8px 28px rgba(99,91,255,.15);
    }
    .gd-step-num {
      display: inline-flex; align-items: center; justify-content: center;
      width: 38px; height: 38px;
      border-radius: 50%;
      background: var(--purple); color: #fff;
      font-weight: 800; font-size: .95rem;
      box-shadow: 0 6px 18px rgba(99,91,255,.4);
      margin-bottom: 12px;
    }
    .gd-step-title { font-size: 1.25rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
    .gd-step-meta { font-size: .8rem; color: var(--muted); margin-bottom: 16px; }
    .gd-step-content {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 18px 20px;
      font-size: .95rem;
      line-height: 1.7;
      color: var(--text);
      white-space: pre-wrap;
      word-break: break-word;
    }

    .gd-comment {
      margin-top: 12px;
      padding: 10px 14px;
      background: var(--soft-surface);
      border-radius: 10px;
      border-left: 3px solid var(--line);
      font-size: .85rem; color: var(--muted);
    }
    .gd-comment strong { color: var(--text); }

    .gd-siblings { display: flex; flex-direction: column; gap: 10px; }
    .gd-sibling {
      display: flex; align-items: center; gap: 12px;
      padding: 11px 14px;
      background: var(--soft-surface);
      border: 1px solid var(--line);
      border-radius: 12px;
      text-decoration: none;
      color: var(--text);
      transition: all .15s;
    }
    .gd-sibling:hover { border-color: var(--purple); background: var(--purple-soft); transform: translateX(2px); }
    .gd-sibling.is-current {
      background: var(--purple-soft);
      border-color: var(--purple);
      box-shadow: 0 4px 14px rgba(99,91,255,.18);
      cursor: default;
    }
    .gd-sibling.is-current:hover { transform: none; }
    .gd-sibling-num {
      width: 26px; height: 26px;
      border-radius: 50%;
      background: var(--panel);
      border: 1px solid var(--line);
      color: var(--muted);
      font-weight: 800; font-size: .78rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .gd-sibling.is-current .gd-sibling-num { background: var(--purple); color: #fff; border-color: var(--purple); }
    .gd-sibling-title { font-size: .9rem; font-weight: 700; flex: 1; }
    .gd-sibling-date  { font-size: .75rem; color: var(--muted); }

    .gd-readonly {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 14px 18px;
      background: var(--warning-soft);
      border: 1px solid #ffeaa7;
      border-radius: 12px;
      color: #856404;
      font-size: .85rem;
      line-height: 1.5;
    }
    .gd-readonly-icon { font-size: 1.1rem; flex-shrink: 0; }
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
          <a class="nav-link" href="settings.html" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Paramètres</span></a>
        </div>
      </div>
    </div>
  </aside>

  <main class="page">
    <div class="topbar">
      <div>
        <button class="mobile-toggle" data-nav-toggle aria-label="Menu">☰</button>
        <h1 class="page-title">Détails du guide</h1>
      </div>
      <div class="toolbar-actions">
        <a class="update-button" href="gestion-guides.php">← Tous les guides</a>
        <button class="icon-button icon-moon" data-theme-toggle aria-label="Thème"></button>
      </div>
    </div>

    <div class="page-grid">
      <section class="content-section">

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <a href="gestion-guides.php" class="gd-back">← Retour à la liste</a>

        <!-- ── MISSION CONTEXT ── -->
        <div class="gd-card">
          <div class="gd-mission-id">📋 Mission #<?= (int)$guide['goal_id'] ?> · ouverte le <?= htmlspecialchars(date('d/m/Y', strtotime($guide['goal_created_at']))) ?></div>
          <div class="gd-mission-title"><?= htmlspecialchars($guide['goal_title']) ?></div>

          <div class="gd-chips" style="margin-bottom: 4px;">
            <span class="gd-chip gd-chip-purple"><?= $type[0] ?> <?= htmlspecialchars($type[1]) ?></span>
            <span class="gd-chip gd-chip-blue"><?= $status[0] ?> <?= htmlspecialchars($status[1]) ?></span>
            <?php if (!empty($guide['goal_priority'])): ?>
              <span class="gd-chip gd-chip-yellow">Priorité : <?= $priority[0] ?> <?= htmlspecialchars($priority[1]) ?></span>
            <?php endif; ?>
            <span class="gd-chip <?= $guide['admin_validation_status'] === 'valide' ? 'gd-chip-green' : ($guide['admin_validation_status'] === 'refuse' ? 'gd-chip-red' : 'gd-chip-gray') ?>">
              🛡 Admin : <?= htmlspecialchars($adminMap[$guide['admin_validation_status']] ?? $guide['admin_validation_status']) ?>
            </span>
            <span class="gd-chip <?= $guide['assistant_validation_status'] === 'accepte' ? 'gd-chip-green' : ($guide['assistant_validation_status'] === 'refuse' ? 'gd-chip-red' : 'gd-chip-gray') ?>">
              🤝 Assistant : <?= htmlspecialchars($assistantMap[$guide['assistant_validation_status']] ?? $guide['assistant_validation_status']) ?>
            </span>
          </div>

          <?php if (!empty($guide['goal_description'])): ?>
            <div class="gd-mission-desc"><?= nl2br(htmlspecialchars($guide['goal_description'])) ?></div>
          <?php endif; ?>

          <?php if (!empty($guide['admin_comment'])): ?>
            <div class="gd-comment"><strong>💬 Commentaire admin :</strong> <?= htmlspecialchars($guide['admin_comment']) ?></div>
          <?php endif; ?>
          <?php if (!empty($guide['assistant_comment'])): ?>
            <div class="gd-comment"><strong>💬 Commentaire assistant :</strong> <?= htmlspecialchars($guide['assistant_comment']) ?></div>
          <?php endif; ?>
        </div>

        <!-- ── PARTIES (Citoyen + Assistant) ── -->
        <div class="gd-grid-2">
          <div class="gd-card">
            <div class="gd-card-title">👤 Citoyen (bénéficiaire)</div>
            <div class="gd-party-name"><?= htmlspecialchars($citizenName) ?></div>
            <?php if (!empty($guide['user_email'])): ?>
              <div class="gd-party-mail">📧 <?= htmlspecialchars($guide['user_email']) ?></div>
            <?php endif; ?>
          </div>

          <div class="gd-card">
            <div class="gd-card-title">🤝 Assistant en charge</div>
            <div class="gd-party-name"><?= htmlspecialchars($assistantName) ?></div>
            <?php if (!empty($guide['assistant_email'])): ?>
              <div class="gd-party-mail">📧 <?= htmlspecialchars($guide['assistant_email']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── CURRENT GUIDE STEP ── -->
        <div class="gd-step-card">
          <div class="gd-step-num"><?= $stepNumber ?></div>
          <div class="gd-step-title">📖 <?= htmlspecialchars($guide['title']) ?></div>
          <div class="gd-step-meta">
            📅 Créé le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($guide['created_at']))) ?>
            <?php if (!empty($guide['updated_at']) && $guide['updated_at'] !== $guide['created_at']): ?>
              · ✏️ Modifié le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($guide['updated_at']))) ?>
            <?php endif; ?>
          </div>
          <div class="gd-step-content"><?= htmlspecialchars($guide['content']) ?></div>
        </div>

        <!-- ── ALL STEPS IN THE SAME MISSION ── -->
        <?php if (count($siblingGuides) > 1): ?>
          <div class="gd-card">
            <div class="gd-card-title">🪜 Toutes les étapes de cette mission (<?= count($siblingGuides) ?>)</div>
            <div class="gd-siblings">
              <?php foreach ($siblingGuides as $i => $s):
                $isCurrent = ((int)$s['id'] === $guideId);
              ?>
                <?php if ($isCurrent): ?>
                  <div class="gd-sibling is-current">
                    <div class="gd-sibling-num"><?= $i + 1 ?></div>
                    <div class="gd-sibling-title"><?= htmlspecialchars($s['title']) ?></div>
                    <div class="gd-sibling-date">📍 Étape consultée</div>
                  </div>
                <?php else: ?>
                  <a class="gd-sibling" href="gestion-guides-details.php?id=<?= (int)$s['id'] ?>">
                    <div class="gd-sibling-num"><?= $i + 1 ?></div>
                    <div class="gd-sibling-title"><?= htmlspecialchars($s['title']) ?></div>
                    <div class="gd-sibling-date">📅 <?= htmlspecialchars(date('d/m/Y', strtotime($s['created_at']))) ?></div>
                  </a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- ── READ-ONLY NOTICE ── -->
        <div class="gd-readonly">
          <span class="gd-readonly-icon">ℹ️</span>
          <div>En tant qu'administrateur, vous consultez ce guide en <strong>lecture seule</strong>. Seul l'assistant responsable peut le modifier ou ajouter des étapes.</div>
        </div>

      </section>
    </div>
  </main>
</div>
<script src="assets/app.js"></script>
<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
