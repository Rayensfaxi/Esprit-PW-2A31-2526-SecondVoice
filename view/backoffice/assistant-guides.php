<?php
if (!headers_sent()) {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  $target = '/view/frontoffice/assistant-guides.php' . ($query !== '' ? ('?' . $query) : '');
  header('Location: ' . $target);
  exit;
}

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GuideController.php';
require_once __DIR__ . '/../../controller/GoalController.php';

$_role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$isAssistant = in_array($_role, ['assistant', 'agent']);
if (!$isAssistant) {
    header("Location: /test-login.php?role=assistant");
    exit;
}

$assistant_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($assistant_id <= 0) {
    header("Location: /test-login.php?role=assistant");
    exit;
}

$guideCtrl = new GuideController();
$goalCtrl  = new GoalController();
$success = $error = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_guide') {
            $goal_id = (int)$_POST['goal_id'];
            $title   = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if (empty($title) || empty($content) || $goal_id <= 0) throw new RuntimeException("Tous les champs sont requis.");
            $guide = new Guide($goal_id, $title, $content);
            if (!$guideCtrl->createGuideByAssistant($guide, $assistant_id)) throw new RuntimeException("Vous n'êtes pas autorisé à créer un guide pour cet accompagnement.");
            $success = "Guide ajouté avec succès.";
        }
        if ($action === 'edit_guide') {
            $guide_id = (int)$_POST['guide_id'];
            $goal_id  = (int)$_POST['goal_id'];
            $title    = trim($_POST['title'] ?? '');
            $content  = trim($_POST['content'] ?? '');
            if (empty($title) || empty($content)) throw new RuntimeException("Tous les champs sont requis.");
            $guide = new Guide($goal_id, $title, $content);
            if (!$guideCtrl->updateGuideByAssistant($guide, $guide_id, $assistant_id)) throw new RuntimeException("Modification non autorisée.");
            $success = "Guide modifié avec succès.";
        }
        if ($action === 'delete_guide') {
            $guide_id = (int)$_POST['guide_id'];
            if (!$guideCtrl->deleteGuideByAssistant($guide_id, $assistant_id)) throw new RuntimeException("Suppression non autorisée.");
            $success = "Guide supprimé.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$guides       = $guideCtrl->getGuidesByAssistant($assistant_id);
$activeGoals  = $goalCtrl->getAcceptedGoalsForAssistant($assistant_id);

// Find editable guide id
$editGuideId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editGuide   = null;
if ($editGuideId) {
    $editGuide = $guideCtrl->getGuideByIdForAssistant($editGuideId, $assistant_id);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mes Guides | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
  <style>
    .sv-alert {
      display:flex; align-items:flex-start; gap:12px;
      padding:14px 20px; border-radius:12px; font-size:.9rem;
      margin-bottom:22px; animation:svFd .3s ease;
    }
    @keyframes svFd { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
    .sv-alert-success { background:rgba(49,208,170,.12); color:#1fa87b; border:1px solid rgba(49,208,170,.3); }
    .sv-alert-error   { background:rgba(255,107,107,.12); color:#cc3333; border:1px solid rgba(255,107,107,.3); }

    .sv-layout { display:grid; grid-template-columns:1fr 360px; gap:24px; align-items:start; }
    @media(max-width:900px) { .sv-layout { grid-template-columns:1fr; } }

    /* GUIDE LIST */
    .sv-guide-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 18px 20px;
      margin-bottom: 12px;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      transition: box-shadow .2s;
      position: relative;
    }
    .sv-guide-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.18); }
    .sv-guide-card.editing { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }

    .sv-guide-num {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--purple); color: #fff;
      font-size: .8rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    .sv-guide-body { flex: 1; }
    .sv-guide-goal-tag {
      font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
      color: var(--muted); margin-bottom: 4px;
    }
    .sv-guide-title { font-size: .95rem; font-weight: 700; color: var(--text); margin-bottom: 5px; }
    .sv-guide-text  { font-size: .84rem; color: var(--muted); line-height: 1.55; }
    .sv-guide-date  { font-size: .73rem; color: var(--muted); margin-top: 5px; }

    .sv-guide-actions { display:flex; gap:7px; flex-shrink:0; flex-wrap:wrap; }

    .sv-btn {
      display:inline-flex; align-items:center; gap:6px;
      padding:7px 14px; border-radius:8px; font-family:inherit;
      font-size:.8rem; font-weight:700; cursor:pointer; border:none; transition:all .2s;
      text-decoration:none;
    }
    .sv-btn-edit   { background:var(--purple-soft); color:var(--purple); border:1px solid rgba(99,91,255,.2); }
    .sv-btn-edit:hover { background:rgba(99,91,255,.25); }
    .sv-btn-del    { background:rgba(255,107,107,.1); color:#cc3333; border:1px solid rgba(255,107,107,.2); }
    .sv-btn-del:hover { background:rgba(255,107,107,.2); }
    .sv-btn-save   { background:var(--purple); color:#fff; padding:11px 24px; font-size:.9rem; border:none; box-shadow:0 4px 14px rgba(99,91,255,.35); }
    .sv-btn-save:hover { filter:brightness(1.1); transform:translateY(-1px); }
    .sv-btn-cancel { background:var(--soft-surface); color:var(--muted); border:1px solid var(--line); padding:11px 18px; font-size:.9rem; }
    .sv-btn-cancel:hover { background:var(--soft-surface-2); color:var(--text); }
    .sv-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }

    /* FORM PANEL */
    .sv-form-panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 22px 22px 24px;
      position: sticky;
      top: 20px;
    }
    .sv-panel-title {
      font-size: .78rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .07em; color: var(--purple); margin-bottom: 16px;
      padding-bottom: 12px; border-bottom: 1px solid var(--line);
    }

    .sv-field { margin-bottom: 16px; }
    .sv-field-label {
      display: block; font-size: .75rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-bottom: 6px;
    }
    .sv-field-required { color: #ff6b6b; }
    .sv-input, .sv-select, .sv-textarea {
      width: 100%; padding: 10px 13px;
      background: var(--input-bg); border: 1.5px solid var(--input-border);
      border-radius: 10px; color: var(--text); font-family: inherit; font-size: .875rem;
      transition: border-color .2s, box-shadow .2s; outline: none;
    }
    .sv-input:focus, .sv-select:focus, .sv-textarea:focus {
      border-color: var(--purple);
      box-shadow: 0 0 0 3px var(--purple-soft);
    }
    .sv-input.is-invalid, .sv-select.is-invalid, .sv-textarea.is-invalid {
      border-color: #ff6b6b !important;
      box-shadow: 0 0 0 3px rgba(255,107,107,.15) !important;
    }
    .sv-textarea { resize: vertical; min-height: 120px; }
    .sv-select { appearance: none; }

    .sv-field-error {
      font-size: .77rem; color: #ff6b6b; display: none; margin-top: 4px;
    }
    .sv-field-error.visible { display: block; }
    .sv-char-count { font-size: .75rem; color: var(--muted); text-align: right; margin-top: 3px; }

    .sv-form-actions { display:flex; gap:8px; margin-top:20px; flex-wrap:wrap; }

    .sv-empty {
      text-align: center; padding: 60px 24px;
    }
    .sv-empty-icon  { font-size: 3rem; margin-bottom: 12px; }
    .sv-empty-title { font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
    .sv-empty-sub   { color: var(--muted); font-size: .875rem; }

    /* Edit mode label */
    .edit-mode-notice {
      display: flex; align-items: center; gap: 8px;
      padding: 8px 14px; border-radius: 8px;
      background: var(--purple-soft); color: var(--purple);
      font-size: .82rem; font-weight: 700; margin-bottom: 14px;
    }
  </style>
</head>
<body data-page="document">
<div class="overlay" data-overlay></div>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-panel">
      <div class="brand-row">
        <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice" /></a>
      </div>
      <div class="sidebar-scroll">
        <div class="nav-section">
          <div class="nav-title">Espace Assistant</div>
          <a class="nav-link" href="assistant-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Mes Accompagnements</span></a>
          <a class="nav-link" href="assistant-guides.php" data-nav="document"><span class="nav-icon icon-document"></span><span>Mes Guides</span></a>
        </div>
      </div>
    </div>
  </aside>

  <main class="page">
    <div class="topbar">
      <div>
        <button class="mobile-toggle" data-nav-toggle aria-label="Menu">☰</button>
        <h1 class="page-title">Mes guides d'accompagnement</h1>
      </div>
      <div class="toolbar-actions">
        <a class="update-button" href="assistant-accompagnements.php">← Missions</a>
        <button class="icon-button icon-moon" data-theme-toggle aria-label="Thème"></button>
      </div>
    </div>

    <div class="page-grid">
      <section class="content-section">

        <?php if ($success): ?>
          <div class="sv-alert sv-alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="sv-alert sv-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="sv-layout">

          <!-- LEFT: GUIDE LIST -->
          <div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
              <div>
                <h2 class="section-title" style="margin-bottom:2px;">📖 Mes étapes créées</h2>
                <p style="font-size:.85rem; color:var(--muted);"><?= count($guides) ?> guide<?= count($guides) !== 1 ? 's' : '' ?> au total</p>
              </div>
            </div>

            <?php if (empty($guides)): ?>
              <div class="sv-empty">
                <div class="sv-empty-icon">📝</div>
                <div class="sv-empty-title">Aucun guide créé</div>
                <div class="sv-empty-sub">Créez votre premier guide pour une de vos missions acceptées.</div>
              </div>
            <?php else: ?>
              <?php $i = 1; foreach ($guides as $guide):
                $isEditing = $editGuideId == $guide['id'];
              ?>
              <div class="sv-guide-card <?= $isEditing ? 'editing' : '' ?>" id="guide-<?= $guide['id'] ?>">
                <div class="sv-guide-num"><?= $i++ ?></div>
                <div class="sv-guide-body">
                  <div class="sv-guide-goal-tag">Accompagnement #<?= $guide['goal_id'] ?></div>
                  <div class="sv-guide-title"><?= htmlspecialchars($guide['title']) ?></div>
                  <div class="sv-guide-text"><?= nl2br(htmlspecialchars(mb_substr($guide['content'], 0, 180) . (mb_strlen($guide['content']) > 180 ? '…' : ''))) ?></div>
                  <div class="sv-guide-date">📅 <?= date('d/m/Y H:i', strtotime($guide['created_at'])) ?></div>
                </div>
                <div class="sv-guide-actions">
                  <a href="?edit=<?= $guide['id'] ?>" class="sv-btn sv-btn-edit">✏️ Modifier</a>
                  <form method="POST"
                        data-confirm="Cette étape sera définitivement supprimée du guide."
                        data-confirm-type="danger"
                        data-confirm-title="Supprimer cette étape ?"
                        data-confirm-action="Oui, supprimer"
                        data-confirm-icon="🗑️">
                    <input type="hidden" name="action" value="delete_guide">
                    <input type="hidden" name="guide_id" value="<?= $guide['id'] ?>">
                    <button type="submit" class="sv-btn sv-btn-del">🗑</button>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- RIGHT: FORM PANEL -->
          <div>
            <div class="sv-form-panel">

              <?php if ($editGuide): ?>
                <div class="edit-mode-notice">✏️ Modification du guide #<?= $editGuide['id'] ?></div>
              <?php endif; ?>

              <div class="sv-panel-title">
                <?= $editGuide ? '✏️ Modifier le guide' : '+ Nouveau guide' ?>
              </div>

              <form id="guideForm" method="POST" novalidate>
                <input type="hidden" name="action" value="<?= $editGuide ? 'edit_guide' : 'add_guide' ?>">
                <?php if ($editGuide): ?>
                  <input type="hidden" name="guide_id" value="<?= $editGuide['id'] ?>">
                  <input type="hidden" name="goal_id" value="<?= $editGuide['goal_id'] ?>">
                <?php endif; ?>

                <?php if (!$editGuide): ?>
                <div class="sv-field">
                  <label class="sv-field-label">Accompagnement lié <span class="sv-field-required">*</span></label>
                  <select name="goal_id" id="goalSelect" class="sv-select">
                    <option value="">— Choisissez une mission —</option>
                    <?php foreach ($activeGoals as $g): ?>
                      <option value="<?= $g['id'] ?>"><?= htmlspecialchars(mb_substr($g['title'], 0, 50)) ?> (#<?= $g['id'] ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <div class="sv-field-error" id="goalError">⚠ Sélectionnez un accompagnement.</div>
                  <?php if (empty($activeGoals)): ?>
                    <div style="font-size:.8rem; color:var(--muted); margin-top:6px;">ℹ️ Aucune mission acceptée en cours.</div>
                  <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="sv-field">
                  <label class="sv-field-label" for="guideTitle">Titre de l'étape <span class="sv-field-required">*</span></label>
                  <input
                    type="text"
                    id="guideTitle"
                    name="title"
                    class="sv-input"
                    placeholder="Ex: Analyse du profil LinkedIn"
                    maxlength="255"
                    value="<?= htmlspecialchars($editGuide['title'] ?? '') ?>"
                    autocomplete="off"
                  />
                  <div class="sv-field-error" id="titleError">⚠ Titre obligatoire (min. 3 caractères).</div>
                </div>

                <div class="sv-field">
                  <label class="sv-field-label" for="guideContent">Contenu / Instructions <span class="sv-field-required">*</span></label>
                  <textarea
                    id="guideContent"
                    name="content"
                    class="sv-textarea"
                    placeholder="Décrivez les étapes, actions et conseils..."
                    maxlength="2000"
                  ><?= htmlspecialchars($editGuide['content'] ?? '') ?></textarea>
                  <div class="sv-char-count" id="contentCounter">0 / 2000</div>
                  <div class="sv-field-error" id="contentError">⚠ Contenu obligatoire (min. 10 caractères).</div>
                </div>

                <div class="sv-form-actions">
                  <?php if ($editGuide): ?>
                    <a href="assistant-guides.php" class="sv-btn sv-btn-cancel">Annuler</a>
                  <?php endif; ?>
                  <button type="submit" class="sv-btn sv-btn-save" id="saveBtn">
                    <?= $editGuide ? '💾 Enregistrer' : '+ Ajouter le guide' ?>
                  </button>
                </div>
              </form>

            </div>
          </div>

        </div>
      </section>
    </div>
  </main>
</div>
<script src="assets/app.js"></script>
<script>
// ─── CHAR COUNTER ────────────────────────────────────────────
(function() {
  var el = document.getElementById('guideContent');
  var counter = document.getElementById('contentCounter');
  if (!el || !counter) return;
  function update() {
    var len = el.value.length;
    counter.textContent = len + ' / 2000';
    counter.style.color = len > 1800 ? (len >= 2000 ? '#ff6b6b' : '#ffb84d') : '';
  }
  el.addEventListener('input', update);
  update();
})();

// ─── FORM VALIDATION ─────────────────────────────────────────
var form = document.getElementById('guideForm');

form.addEventListener('submit', function(e) {
  var valid = true;

  // Goal select (only on add)
  var goalEl = document.getElementById('goalSelect');
  if (goalEl) {
    if (!goalEl.value) {
      goalEl.classList.add('is-invalid');
      document.getElementById('goalError').classList.add('visible');
      valid = false;
    } else {
      goalEl.classList.remove('is-invalid');
      document.getElementById('goalError').classList.remove('visible');
    }
  }

  // Title
  var titleEl = document.getElementById('guideTitle');
  if (!titleEl.value.trim() || titleEl.value.trim().length < 3) {
    titleEl.classList.add('is-invalid');
    document.getElementById('titleError').classList.add('visible');
    valid = false;
  } else {
    titleEl.classList.remove('is-invalid');
    document.getElementById('titleError').classList.remove('visible');
  }

  // Content
  var contentEl = document.getElementById('guideContent');
  if (!contentEl.value.trim() || contentEl.value.trim().length < 10) {
    contentEl.classList.add('is-invalid');
    document.getElementById('contentError').classList.add('visible');
    valid = false;
  } else {
    contentEl.classList.remove('is-invalid');
    document.getElementById('contentError').classList.remove('visible');
  }

  if (!valid) {
    e.preventDefault();
    // scroll to first invalid
    var first = form.querySelector('.is-invalid');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  document.getElementById('saveBtn').disabled = true;
  document.getElementById('saveBtn').textContent = '⏳ Enregistrement...';
});

// Live validation
['guideTitle', 'guideContent'].forEach(function(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('input', function() {
    var min = id === 'guideTitle' ? 3 : 10;
    var errId = id === 'guideTitle' ? 'titleError' : 'contentError';
    if (this.value.trim().length >= min) {
      this.classList.remove('is-invalid');
      document.getElementById(errId).classList.remove('visible');
    }
  });
});

var goalEl = document.getElementById('goalSelect');
if (goalEl) {
  goalEl.addEventListener('change', function() {
    if (this.value) {
      this.classList.remove('is-invalid');
      document.getElementById('goalError').classList.remove('visible');
    }
  });
}

// Scroll to editing card
<?php if ($editGuide): ?>
var card = document.getElementById('guide-<?= $editGuide['id'] ?>');
if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
<?php endif; ?>
</script>
</body>
</html>
