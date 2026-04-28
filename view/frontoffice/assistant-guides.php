<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GuideController.php';
require_once __DIR__ . '/../../controller/GoalController.php';

$_role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$isAssistant = in_array($_role, ['assistant', 'agent']);
if (!$isAssistant) {
  header("Location: /test-login.php?role=assistant&next=view/frontoffice/assistant-guides.php");
    exit;
}

$assistant_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($assistant_id <= 0) {
  header("Location: /test-login.php?role=assistant&next=view/frontoffice/assistant-guides.php");
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

// Group guides by goal_id
$groupedGuides = [];
foreach ($guides as $g) {
    $groupedGuides[$g['goal_id']][] = $g;
}

// Find editable guide id
$editGuideId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editGuide   = null;
if ($editGuideId) {
    $editGuide = $guideCtrl->getGuideByIdForAssistant($editGuideId, $assistant_id);
}

// Error state helper
$errorGoalId = ($error && isset($_POST['goal_id'])) ? (int)$_POST['goal_id'] : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mes Guides | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../backoffice/assets/style.css" />
  <style>
    .sv-alert {
      display:flex; align-items:flex-start; gap:12px;
      padding:14px 20px; border-radius:12px; font-size:.9rem;
      margin-bottom:22px; animation:svFd .3s ease;
    }
    @keyframes svFd { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
    .sv-alert-success { background:rgba(49,208,170,.12); color:#1fa87b; border:1px solid rgba(49,208,170,.3); }
    .sv-alert-error   { background:rgba(255,107,107,.12); color:#cc3333; border:1px solid rgba(255,107,107,.3); }

    .sv-layout { max-width: 820px; margin: 0 auto; }

    /* GOAL GROUP */
    .sv-goal-group {
      margin-bottom: 48px;
      padding-bottom: 24px;
      border-bottom: 1px solid var(--line);
    }
    .sv-goal-group:last-child { border-bottom: none; }
    .sv-goal-header {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 20px;
    }
    .sv-goal-title {
      font-size: 1.15rem; font-weight: 800; color: var(--text);
      display: flex; align-items: center; gap: 8px;
    }
    .sv-goal-badge {
      font-size: .7rem; font-weight: 700; background: var(--purple-soft);
      color: var(--purple); padding: 4px 10px; border-radius: 20px;
      text-transform: uppercase; letter-spacing: .05em;
    }

    /* GUIDE LIST */
    .sv-guide-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 18px 20px;
      margin-bottom: 16px;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      transition: all .2s ease;
      position: relative;
    }
    .sv-guide-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.1); border-color: var(--purple-soft); }
    
    .sv-guide-num {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--purple); color: #fff;
      font-size: .8rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    .sv-guide-body { flex: 1; }
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

    /* ADD STEP BUTTON */
    .sv-add-btn-wrapper { margin-top: 16px; }
    .sv-btn-add-step {
      width: 100%; padding: 18px;
      background: transparent; border: 2px dashed var(--purple);
      border-radius: 14px; display: flex; align-items: center; justify-content: center;
      gap: 12px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      color: var(--purple); font-family: inherit; font-weight: 700; font-size: .95rem;
    }
    .sv-btn-add-step:hover { background: var(--purple-soft); border-style: solid; }
    .sv-btn-add-step.active { background: var(--purple); color: #fff; border-style: solid; }
    
    .sv-add-icon-circle {
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--purple); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; transition: transform 0.3s ease; line-height: 1;
    }
    .sv-btn-add-step.active .sv-add-icon-circle {
      background: #fff; color: var(--purple); transform: rotate(45deg);
    }

    /* FORM CONTAINERS */
    .sv-form-panel {
      background: var(--panel); border: 1px solid var(--line);
      border-radius: 18px; padding: 22px 22px 24px;
    }
    .sv-add-form-container {
      max-height: 0; overflow: hidden; opacity: 0;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); margin-top: 0;
    }
    .sv-add-form-container.open {
      max-height: 1000px; opacity: 1; margin-top: 20px;
    }

    /* INLINE EDIT FORM */
    .sv-edit-inline-card {
      background: var(--panel); border: 2px solid var(--purple);
      border-radius: 18px; padding: 24px; margin-bottom: 16px;
      box-shadow: 0 8px 32px var(--purple-soft); animation: svFd .4s ease;
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
    .sv-input, .sv-textarea {
      width: 100%; padding: 10px 13px;
      background: var(--input-bg); border: 1.5px solid var(--input-border);
      border-radius: 10px; color: var(--text); font-family: inherit; font-size: .875rem;
      transition: border-color .2s, box-shadow .2s; outline: none;
    }
    .sv-input:focus, .sv-textarea:focus {
      border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft);
    }
    .sv-input.is-invalid, .sv-textarea.is-invalid {
      border-color: #ff6b6b !important;
      box-shadow: 0 0 0 3px rgba(255,107,107,.15) !important;
    }
    .sv-textarea { resize: vertical; min-height: 120px; }

    .sv-field-error { font-size: .77rem; color: #ff6b6b; display: none; margin-top: 4px; }
    .sv-field-error.visible { display: block; }
    .sv-char-count { font-size: .75rem; color: var(--muted); text-align: right; margin-top: 3px; }

    .sv-form-actions { display:flex; gap:8px; margin-top:20px; flex-wrap:wrap; }

    .sv-empty { text-align: center; padding: 40px 20px; background: var(--panel); border: 1px dashed var(--line); border-radius: 14px; margin-bottom: 16px; }
    .sv-empty-icon  { font-size: 2rem; margin-bottom: 10px; }
    .sv-empty-title { font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
    .sv-empty-sub   { color: var(--muted); font-size: .82rem; }
  </style>
</head>
<body data-page="document">
<div class="overlay" data-overlay></div>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-panel">
      <div class="brand-row">
        <a class="brand" href="index.php"><img class="brand-logo" src="../backoffice/assets/media/secondvoice-logo.png" alt="SecondVoice" /></a>
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

          <div style="margin-bottom: 32px;">
            <h2 class="section-title" style="margin-bottom:2px;">📖 Mes guides par accompagnement</h2>
            <p style="font-size:.85rem; color:var(--muted);">Retrouvez les étapes de guide pour chacune de vos missions.</p>
          </div>

          <?php if (empty($activeGoals)): ?>
            <div class="sv-empty">
                <div class="sv-empty-icon">🤝</div>
                <div class="sv-empty-title">Aucune mission active</div>
                <div class="sv-empty-sub">Vous devez accepter des missions pour pouvoir créer des guides.</div>
            </div>
          <?php else: ?>
            <?php foreach ($activeGoals as $goal): 
                $goalId = $goal['id'];
                $goalGuides = $groupedGuides[$goalId] ?? [];
                $isOpenOnErr = ($errorGoalId === $goalId);
            ?>
              <div class="sv-goal-group" id="goal-section-<?= $goalId ?>">
                <div class="sv-goal-header">
                   <h3 class="sv-goal-title">🎯 <?= htmlspecialchars($goal['title']) ?></h3>
                   <span class="sv-goal-badge">#<?= $goalId ?></span>
                </div>

                <div class="sv-guides-stack">
                  <?php if (empty($goalGuides)): ?>
                    <div class="sv-empty">
                      <div class="sv-empty-icon">📝</div>
                      <div class="sv-empty-title">Aucune étape créée</div>
                      <div class="sv-empty-sub">Commencez à structurer ce guide.</div>
                    </div>
                  <?php else: ?>
                    <?php $i = 1; foreach ($goalGuides as $guide):
                      $isEditing = ($editGuideId == $guide['id']);
                      if ($isEditing):
                    ?>
                      <!-- INLINE EDIT FORM -->
                      <div class="sv-edit-inline-card" id="edit-form-<?= $guide['id'] ?>">
                        <div class="sv-panel-title">✏️ Modifier l'étape #<?= $i++ ?></div>
                        <form class="js-edit-form" method="POST" novalidate>
                          <input type="hidden" name="action" value="edit_guide">
                          <input type="hidden" name="guide_id" value="<?= $guide['id'] ?>">
                          <input type="hidden" name="goal_id" value="<?= $goalId ?>">

                          <div class="sv-field">
                            <label class="sv-field-label">Titre de l'étape <span class="sv-field-required">*</span></label>
                            <input type="text" name="title" class="sv-input" value="<?= htmlspecialchars($guide['title']) ?>" maxlength="255" required autocomplete="off" />
                            <div class="sv-field-error">⚠ Titre obligatoire (min. 3 caractères).</div>
                          </div>

                          <div class="sv-field">
                            <label class="sv-field-label">Contenu / Instructions <span class="sv-field-required">*</span></label>
                            <textarea name="content" class="sv-textarea" maxlength="2000" required><?= htmlspecialchars($guide['content']) ?></textarea>
                            <div class="sv-char-count">0 / 2000</div>
                            <div class="sv-field-error">⚠ Contenu obligatoire (min. 10 caractères).</div>
                          </div>

                          <div class="sv-form-actions">
                            <a href="assistant-guides.php" class="sv-btn sv-btn-cancel">Annuler</a>
                            <button type="submit" class="sv-btn sv-btn-save">💾 Enregistrer</button>
                          </div>
                        </form>
                      </div>
                    <?php else: ?>
                      <!-- STANDARD CARD -->
                      <div class="sv-guide-card" id="guide-<?= $guide['id'] ?>">
                        <div class="sv-guide-num"><?= $i++ ?></div>
                        <div class="sv-guide-body">
                          <div class="sv-guide-title"><?= htmlspecialchars($guide['title']) ?></div>
                          <div class="sv-guide-text"><?= nl2br(htmlspecialchars(mb_strlen($guide['content']) > 250 ? mb_substr($guide['content'], 0, 250) . '...' : $guide['content'])) ?></div>
                          <div class="sv-guide-date">📅 <?= date('d/m/Y H:i', strtotime($guide['created_at'])) ?></div>
                        </div>
                        <div class="sv-guide-actions">
                          <a href="?edit=<?= $guide['id'] ?>" class="sv-btn sv-btn-edit">✏️</a>
                          <form method="POST" onsubmit="return confirm('Supprimer ce guide ?')">
                            <input type="hidden" name="action" value="delete_guide">
                            <input type="hidden" name="guide_id" value="<?= $guide['id'] ?>">
                            <button type="submit" class="sv-btn sv-btn-del">🗑</button>
                          </form>
                        </div>
                      </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>

                <!-- ADD BUTTON & FORM (Hidden if editing anywhere in the page for now to keep focus) -->
                <?php if (!$editGuide): ?>
                  <div class="sv-add-btn-wrapper">
                    <button type="button" class="sv-btn-add-step js-toggle-add <?= $isOpenOnErr ? 'active' : '' ?>" data-goal="<?= $goalId ?>">
                      <span class="sv-add-icon-circle">+</span>
                      <span><?= empty($goalGuides) ? 'Ajouter la première étape' : 'Ajouter l\'étape suivante' ?></span>
                    </button>

                    <div class="sv-add-form-container js-add-container <?= $isOpenOnErr ? 'open' : '' ?>" id="add-container-<?= $goalId ?>">
                      <div class="sv-form-panel">
                        <div class="sv-panel-title">+ Nouveau guide pour cette mission</div>
                        <form class="js-add-form" method="POST" novalidate>
                          <input type="hidden" name="action" value="add_guide">
                          <input type="hidden" name="goal_id" value="<?= $goalId ?>">

                          <div class="sv-field">
                            <label class="sv-field-label">Titre de l'étape <span class="sv-field-required">*</span></label>
                            <input type="text" name="title" class="sv-input" placeholder="Ex: Analyse du profil LinkedIn" maxlength="255" autocomplete="off" />
                            <div class="sv-field-error">⚠ Titre obligatoire (min. 3 caractères).</div>
                          </div>

                          <div class="sv-field">
                            <label class="sv-field-label">Contenu / Instructions <span class="sv-field-required">*</span></label>
                            <textarea name="content" class="sv-textarea" placeholder="Décrivez les étapes, actions et conseils..." maxlength="2000"></textarea>
                            <div class="sv-char-count">0 / 2000</div>
                            <div class="sv-field-error">⚠ Contenu obligatoire (min. 10 caractères).</div>
                          </div>

                          <div class="sv-form-actions">
                            <button type="button" class="sv-btn sv-btn-cancel js-cancel-add">Annuler</button>
                            <button type="submit" class="sv-btn sv-btn-save">+ Ajouter le guide</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </section>
    </div>
  </main>
</div>
<script src="../backoffice/assets/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // ─── TOGGLE ADD FORMS ────────────────────────────────────────
  document.querySelectorAll('.js-toggle-add').forEach(btn => {
    btn.addEventListener('click', function() {
      const container = this.nextElementSibling;
      const isOpen = container.classList.toggle('open');
      this.classList.toggle('active');
      
      if (isOpen) {
        setTimeout(() => {
          container.scrollIntoView({ behavior: 'smooth', block: 'center' });
          const first = container.querySelector('input, textarea');
          if (first) first.focus();
        }, 300);
      }
    });
  });
  
  document.querySelectorAll('.js-cancel-add').forEach(btn => {
    btn.addEventListener('click', function() {
      const container = this.closest('.js-add-container');
      const toggle = container.previousElementSibling;
      container.classList.remove('open');
      toggle.classList.remove('active');
    });
  });

  // ─── CHAR COUNTERS ───────────────────────────────────────────
  function initCounter(textarea) {
    if (!textarea) return;
    const counter = textarea.parentElement.querySelector('.sv-char-count');
    if (!counter) return;
    function update() {
      const len = textarea.value.length;
      counter.textContent = len + ' / 2000';
      counter.style.color = len > 1800 ? (len >= 2000 ? '#ff6b6b' : '#ffb84d') : '';
    }
    textarea.addEventListener('input', update);
    update();
  }

  document.querySelectorAll('textarea').forEach(initCounter);

  // ─── FORM VALIDATION ─────────────────────────────────────────
  function validateForm(form) {
    let valid = true;
    
    // Title
    const title = form.querySelector('[name="title"]');
    if (title) {
      if (title.value.trim().length < 3) {
        title.classList.add('is-invalid');
        const err = title.nextElementSibling;
        if (err && err.classList.contains('sv-field-error')) err.classList.add('visible');
        valid = false;
      } else {
        title.classList.remove('is-invalid');
        const err = title.nextElementSibling;
        if (err && err.classList.contains('sv-field-error')) err.classList.remove('visible');
      }
    }

    // Content
    const content = form.querySelector('[name="content"]');
    if (content) {
      if (content.value.trim().length < 10) {
        content.classList.add('is-invalid');
        const err = content.parentElement.querySelector('.sv-field-error');
        if (err && err.classList.contains('sv-field-error')) err.classList.add('visible');
        valid = false;
      } else {
        content.classList.remove('is-invalid');
        const err = content.parentElement.querySelector('.sv-field-error');
        if (err && err.classList.contains('sv-field-error')) err.classList.remove('visible');
      }
    }

    return valid;
  }

  document.querySelectorAll('.js-add-form, .js-edit-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      if (!validateForm(this)) {
        e.preventDefault();
        return;
      }
      const btn = this.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = '⏳ ...';
    });
  });

  // Live validation removal
  document.querySelectorAll('.sv-input, .sv-textarea').forEach(el => {
    el.addEventListener('input', function() {
      const min = (this.name === 'title') ? 3 : (this.name === 'content' ? 10 : 0);
      if (this.value.trim().length >= min) {
        this.classList.remove('is-invalid');
        const err = this.parentElement.querySelector('.sv-field-error');
        if (err) err.classList.remove('visible');
      }
    });
  });

  // Scroll to edit form if active
  const activeEdit = document.querySelector('.sv-edit-inline-card');
  if (activeEdit) {
    setTimeout(() => activeEdit.scrollIntoView({ behavior: 'smooth', block: 'center' }), 400);
  }

  // Auto-scroll to error form
  <?php if ($isOpenOnErr): ?>
  setTimeout(() => {
      const errForm = document.getElementById('add-container-<?= $errorGoalId ?>');
      if (errForm) errForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, 500);
  <?php endif; ?>

});
</script>
</body>
</html>
