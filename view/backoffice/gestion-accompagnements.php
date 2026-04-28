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
$error = $success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $comment  = trim($_POST['comment'] ?? '');

    if ($action === 'moderate' && $id > 0 && in_array($decision, ['valide', 'refuse'])) {
        try {
            $controller->moderateGoalByAdmin($id, $decision, $comment);
            $msg = $decision === 'valide' ? 'validée' : 'refusée';
            header("Location: gestion-accompagnements.php?status=success&msg=" . urlencode("Demande $msg avec succès."));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success = htmlspecialchars($_GET['msg'] ?? 'Action effectuée avec succès.');
}

try {
    $goals = $controller->getPendingGoalsForAdmin();
} catch (Exception $e) {
    $error = "Impossible de récupérer les demandes en attente.";
}

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
    }
    .sv-field-error.visible { display: block; }

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
        <a class="update-button" href="gestion-guides.php">📖 Voir les guides</a>
        <button class="icon-button icon-moon" data-theme-toggle aria-label="Thème"></button>
      </div>
    </div>

    <div class="page-grid">
      <section class="content-section">

        <div style="margin-bottom:20px;">
          <h2 class="section-title">📋 Demandes en attente de validation</h2>
          <p style="color:var(--muted); font-size:.9rem;">Examinez chaque demande et validez ou refusez-la avant qu'elle soit transmise à l'assistant.</p>
        </div>

        <?php if ($success): ?>
          <div class="sv-alert sv-alert-success">✅ <?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="sv-alert sv-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($goals)): ?>
          <div class="sv-empty">
            <div class="sv-empty-icon">🎉</div>
            <div class="sv-empty-title">Aucune demande en attente</div>
            <div class="sv-empty-sub">Toutes les demandes ont été traitées. Revenez plus tard.</div>
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
                  <span class="sv-badge sv-badge-pending">🕐 En attente</span>
                </div>
              </div>

              <div class="sv-card-body">
                <?= nl2br(htmlspecialchars(mb_substr($g['description'], 0, 300) . (mb_strlen($g['description']) > 300 ? '…' : ''))) ?>
              </div>

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
                  <form method="POST" class="action-form refuse-form" data-decision="refuse" data-id="<?= $g['id'] ?>">
                    <input type="hidden" name="action" value="moderate">
                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                    <input type="hidden" name="decision" value="refuse">
                    <textarea name="comment" class="sv-comment-input refuse-reason" rows="2" placeholder="Motif du refus (obligatoire)..." maxlength="500" required></textarea>
                    <div class="sv-field-error" id="refuseError-<?= $g['id'] ?>">⚠ Un motif de refus est obligatoire.</div>
                    <button type="submit" class="sv-btn sv-btn-refuse" style="width:100%; margin-top:6px;">❌ Refuser</button>
                  </form>
                </div>
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
// JS Validation for refusal reason
document.querySelectorAll('.refuse-form').forEach(function(form) {
  form.addEventListener('submit', function(e) {
    var id = form.dataset.id;
    var reasonField = form.querySelector('.refuse-reason');
    var errorEl = document.getElementById('refuseError-' + id);

    if (!reasonField.value.trim()) {
      e.preventDefault();
      reasonField.classList.add('is-invalid');
      errorEl.classList.add('visible');
      reasonField.focus();
      return;
    }
    reasonField.classList.remove('is-invalid');
    errorEl.classList.remove('visible');

    // Disable submit to prevent double submit
    form.querySelector('button[type="submit"]').disabled = true;
  });
});

// Remove invalid state on input
document.querySelectorAll('.refuse-reason').forEach(function(field) {
  field.addEventListener('input', function() {
    if (this.value.trim()) {
      this.classList.remove('is-invalid');
      var id = this.closest('form').dataset.id;
      document.getElementById('refuseError-' + id).classList.remove('visible');
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
</body>
</html>
