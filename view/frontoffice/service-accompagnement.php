<?php require_once __DIR__ . '/../../controller/frontoffice/ServiceAccompagnementPageController.php'; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $isEdit ? 'Modifier ma demande' : 'Nouvelle demande' ?> | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
  <style>
    :root {
      --bg: #07011b;
      --surface: #12092f;
      --surface2: rgba(255, 255, 255, 0.05);
      --border: rgba(255, 255, 255, 0.08);
      --text: #f4f2ff;
      --muted: #b7afd9;
      --accent: #7f3dff;
      --accent-hover: #6f2df1;
      --accent-soft: rgba(127, 61, 255, 0.15);
      --danger: #ef4444;
      --danger-soft: rgba(239, 68, 68, 0.12);
      --success: #10b981;
      --success-soft: rgba(16, 185, 129, 0.14);
      --warning: #f59e0b;
      --warning-soft: rgba(245, 158, 11, 0.14);
      --radius: 14px;
      --shadow: 0 10px 30px rgba(4, 0, 20, 0.35);
      --shadow-lg: 0 22px 56px rgba(4, 0, 20, 0.45);
    }
    :root[data-theme="light"] {
      --bg: #f0f4ff;
      --surface: #ffffff;
      --surface2: #f7f9ff;
      --border: #e2e8f8;
      --text: #0f1629;
      --muted: #6b7a9f;
      --accent: #5046e5;
      --accent-hover: #3d34d1;
      --accent-soft: #eeeeff;
      --danger: #ef4444;
      --danger-soft: #fef2f2;
      --success: #10b981;
      --success-soft: #ecfdf5;
      --warning: #f59e0b;
      --warning-soft: #fffbeb;
      --shadow: 0 4px 24px rgba(80, 70, 229, 0.08);
      --shadow-lg: 0 16px 48px rgba(80, 70, 229, 0.14);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* HEADER */
    .site-header {
      background: rgba(7, 1, 27, 0.88);
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      backdrop-filter: blur(20px);
      padding: 0 32px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 8px 30px rgba(4, 0, 20, 0.25);
    }
    :root[data-theme="light"] .site-header {
      background: rgba(248, 246, 255, 0.9);
      border-bottom: 1px solid rgba(83, 61, 158, 0.08);
      box-shadow: 0 2px 12px rgba(80, 70, 229, 0.06);
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

    .header-nav {
      display: flex;
      align-items: center;
      gap: 8px;
    }

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

    /* MAIN LAYOUT */
    .page-wrapper {
      max-width: 820px;
      margin: 0 auto;
      padding: 40px 24px 80px;
    }

    /* BREADCRUMB */
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.825rem;
      color: var(--muted);
      margin-bottom: 28px;
    }
    .breadcrumb a { color: var(--accent); text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }
    .breadcrumb-sep { opacity: 0.4; }

    /* PAGE HEADER */
    .page-header {
      margin-bottom: 36px;
    }
    .page-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      padding: 5px 12px;
      border-radius: 100px;
      margin-bottom: 14px;
    }
    .page-title {
      font-size: 2rem;
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: 10px;
    }
    .page-subtitle {
      font-size: 1rem;
      color: var(--muted);
      line-height: 1.6;
    }

    /* ALERTS */
    .alert {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      border-radius: var(--radius);
      font-size: 0.9rem;
      margin-bottom: 24px;
      animation: slideIn .3s ease;
    }
    .alert-success { background: var(--success-soft); color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error   { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; }
    .alert-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* FORM CARD */
    .form-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 36px;
      box-shadow: var(--shadow);
    }

    .form-section {
      margin-bottom: 28px;
    }
    .form-section-title {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 16px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border);
    }

    .form-grid {
      display: grid;
      gap: 20px;
    }
    .form-grid-2 {
      grid-template-columns: 1fr 1fr;
    }
    @media (max-width: 600px) {
      .form-grid-2 { grid-template-columns: 1fr; }
      .form-card { padding: 24px; }
    }

    .form-group { display: flex; flex-direction: column; gap: 6px; }

    label {
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--text);
    }

    label .required {
      color: var(--danger);
      margin-left: 3px;
    }

    label .hint {
      font-weight: 400;
      color: var(--muted);
      font-size: 0.8rem;
      margin-left: 6px;
    }

    .label-with-action {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .label-with-action label { flex: 1; min-width: 0; }

    input[type="text"],
    select,
    textarea {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-size: 0.925rem;
      color: var(--text);
      background: var(--surface);
      transition: border-color .2s, box-shadow .2s;
      outline: none;
    }

    input[type="text"]:focus,
    select:focus,
    textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(80,70,229,0.1);
    }

    input.is-invalid,
    select.is-invalid,
    textarea.is-invalid {
      border-color: var(--danger) !important;
      box-shadow: 0 0 0 3px rgba(239,68,68,0.1) !important;
    }

    .field-error {
      font-size: 0.8rem;
      color: var(--danger);
      display: none;
      align-items: center;
      gap: 4px;
    }
    .field-error.visible { display: flex; }

    textarea { resize: vertical; min-height: 110px; }

    select option { padding: 6px; }

    /* TYPE SELECTOR (visual cards) */
    .type-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
    }
    @media (max-width: 600px) {
      .type-grid { grid-template-columns: 1fr 1fr; }
    }

    .type-card {
      position: relative;
      cursor: pointer;
    }
    .type-card input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .type-card-inner {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 14px 8px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--muted);
      text-align: center;
      transition: all .2s;
      background: var(--surface2);
    }
    .type-card-inner .type-emoji { font-size: 1.5rem; }
    .type-card:hover .type-card-inner {
      border-color: var(--accent);
      color: var(--accent);
      background: var(--accent-soft);
    }
    .type-card input:checked + .type-card-inner {
      border-color: var(--accent);
      background: var(--accent-soft);
      color: var(--accent);
      box-shadow: 0 0 0 3px rgba(80,70,229,0.12);
    }

    /* ASSISTANT CARD SELECT */
    .assistant-select-wrapper {
      position: relative;
    }
    .assistant-select-wrapper select {
      appearance: none;
      padding-right: 40px;
    }
    .assistant-select-wrapper::after {
      content: 'â–¾';
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      pointer-events: none;
      font-size: 0.9rem;
    }

    /* CHAR COUNTER */
    .char-counter {
      font-size: 0.78rem;
      color: var(--muted);
      text-align: right;
    }
    .char-counter.warn { color: var(--warning); }
    .char-counter.over { color: var(--danger); }

    /* FORM ACTIONS */
    .form-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid var(--border);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 24px;
      border-radius: 10px;
      font-family: inherit;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: all .2s;
      text-decoration: none;
    }
    .btn-ghost {
      background: transparent;
      color: var(--muted);
      border: 1.5px solid var(--border);
    }
    .btn-ghost:hover { background: var(--surface2); color: var(--text); }
    .btn-primary {
      background: var(--accent);
      color: #fff;
      box-shadow: 0 4px 14px rgba(80,70,229,0.35);
    }
    .btn-primary:hover {
      background: var(--accent-hover);
      box-shadow: 0 6px 20px rgba(80,70,229,0.4);
      transform: translateY(-1px);
    }
    .btn-primary:active { transform: translateY(0); }
    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    /* SUBMIT LOADING STATE */
    .btn-spinner {
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin .6s linear infinite;
      display: none;
    }
    .loading .btn-spinner { display: block; }
    .loading .btn-text { display: none; }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* EDIT MODE NOTICE */
    .edit-notice {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      background: var(--warning-soft);
      border: 1px solid #fde68a;
      border-radius: var(--radius);
      padding: 14px 18px;
      margin-bottom: 24px;
      font-size: 0.875rem;
      color: #92400e;
    }
    .edit-notice-icon { font-size: 1.1rem; flex-shrink: 0; }

    .echo-import-notice {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      background: linear-gradient(135deg, #f5f3ff 0%, #eeeeff 100%);
      border: 1px solid #d6d2ff;
      border-radius: var(--radius);
      padding: 14px 18px;
      margin-bottom: 24px;
      font-size: 0.9rem;
      color: var(--accent);
      animation: slideIn .35s cubic-bezier(.2,.9,.3,1.05);
    }
    .echo-import-icon { font-size: 1.2rem; flex-shrink: 0; line-height: 1; }
    .echo-import-notice strong { color: var(--accent-hover); }
  </style>
</head>
<body>
<?php
  $activeNavItem = 'services';
  include __DIR__ . '/../partials/site-header.php';
?>

<div class="page-wrapper">

  <nav class="breadcrumb">
    <a href="index.php">Accueil</a>
    <span class="breadcrumb-sep">â€º</span>
    <a href="mes-accompagnements.php">Mes accompagnements</a>
    <span class="breadcrumb-sep">â€º</span>
    <span><?= $isEdit ? 'Modifier ma demande' : 'Nouvelle demande' ?></span>
  </nav>

  <div class="page-header">
    <div class="page-tag">âœ¦ Accompagnement professionnel</div>
    <h1 class="page-title">
      <?= $isEdit ? 'âœï¸ Modifier ma demande' : 'ðŸš€ Nouvelle demande d\'accompagnement' ?>
    </h1>
    <p class="page-subtitle">
      <?= $isEdit
        ? 'Mettez Ã  jour les informations de votre demande tant qu\'elle n\'est pas encore traitÃ©e.'
        : 'Soumettez votre demande d\'accompagnement et choisissez l\'assistant qui vous guidera.' ?>
    </p>
  </div>

  <?php require __DIR__ . '/../partials/flash.php'; ?>

  <?php if ($isEdit): ?>
    <div class="edit-notice">
      <span class="edit-notice-icon">âœï¸</span>
      <div><strong>Mode Ã©dition</strong> â€” Vous modifiez la demande <strong>#<?= $existingGoal['id'] ?></strong>. La modification n'est possible que tant que la demande est en attente de validation.</div>
    </div>
  <?php endif; ?>

  <?php if ($cameFromEchoMe): ?>
    <div class="echo-import-notice">
      <span class="echo-import-icon">ðŸŽ™ï¸âœ¨</span>
      <div>
        <strong>Texte importÃ© depuis Echo Me</strong> â€” Votre phrase clarifiÃ©e a Ã©tÃ© insÃ©rÃ©e dans la description ci-dessous.
        VÃ©rifiez et complÃ©tez avant de soumettre.
      </div>
    </div>
  <?php endif; ?>

  <?php if ($cameFromSmartGuide): ?>
    <div class="echo-import-notice">
      <span class="echo-import-icon">ðŸ¤–âœ¨</span>
      <div>
        <strong>Recommandation SmartGuide</strong> â€” J'ai prÃ©-rempli le <strong>type</strong>,
        l'<strong>assistant suggÃ©rÃ©</strong><?= $prefillDesc ? ' et la <strong>description</strong>' : '' ?>
        d'aprÃ¨s vos rÃ©ponses. Vous pouvez tout modifier avant de soumettre.
      </div>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <form id="goalForm" method="POST" novalidate>
      <input type="hidden" name="action" value="<?= $isEdit ? 'update_goal' : 'create_goal' ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="goal_id" value="<?= $existingGoal['id'] ?>">
      <?php endif; ?>

      <!-- TYPE DE SERVICE -->
      <div class="form-section">
        <div class="form-section-title">01 â€” Type de service</div>
        <div class="form-group">
          <label>Choisissez un service <span class="required">*</span></label>
          <div class="type-grid" id="typeGrid">
            <?php
            $types = [
              'cv'           => ['ðŸ“„', 'CV'],
              'cover_letter' => ['âœ‰ï¸', 'Lettre de motivation'],
              'linkedin'     => ['ðŸ”—', 'LinkedIn'],
              'interview'    => ['ðŸŽ¤', 'Entretien'],
              'other'        => ['ðŸ“Œ', 'Autre'],
            ];
            $defaultType = $isEdit
                ? ($existingGoal['type'] ?? '')
                : ($prefillType !== '' ? $prefillType : 'cv');
            foreach ($types as $val => $info):
              $checked = ($val === $defaultType) ? 'checked' : '';
            ?>
            <label class="type-card">
              <input type="radio" name="type" value="<?= $val ?>" <?= $checked ?> required />
              <div class="type-card-inner">
                <span class="type-emoji"><?= $info[0] ?></span>
                <span><?= $info[1] ?></span>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="field-error" id="typeError">âš  Veuillez sÃ©lectionner un type de service.</div>
        </div>
      </div>

      <!-- INFORMATIONS -->
      <div class="form-section">
        <div class="form-section-title">02 â€” Informations sur la demande</div>
        <div class="form-grid">
          <div class="form-group">
            <div class="label-with-action">
              <label for="title">Titre de votre demande <span class="required">*</span> <span class="hint">(ex: RÃ©vision de mon CV pour un poste marketing)</span></label>
              <button type="button" class="vd-btn" id="vd-title" title="Dicter le titre Ã  voix haute">ðŸŽ™ï¸ Dicter</button>
            </div>
            <input
              type="text"
              id="title"
              name="title"
              placeholder="DÃ©crivez briÃ¨vement votre besoin..."
              value="<?= htmlspecialchars($existingGoal['title'] ?? '') ?>"
              maxlength="200"
              autocomplete="off"
            />
            <div class="char-counter" id="titleCounter">0 / 200</div>
            <div class="field-error" id="titleError">âš  Le titre est obligatoire (min. 5 caractÃ¨res).</div>
          </div>

          <div class="form-group">
            <div class="label-with-action">
              <label for="description">Description dÃ©taillÃ©e <span class="required">*</span></label>
              <button type="button" class="vd-btn" id="vd-description" title="Dicter la description Ã  voix haute">ðŸŽ™ï¸ Dicter</button>
            </div>
            <textarea
              id="description"
              name="description"
              placeholder="DÃ©crivez votre situation, votre objectif professionnel, les spÃ©cificitÃ©s de votre demande..."
              maxlength="1000"
            ><?= htmlspecialchars($existingGoal['description'] ?? $prefillDesc) ?></textarea>
            <div class="char-counter" id="descCounter">0 / 1000</div>
            <div class="field-error" id="descError">âš  La description est obligatoire (min. 20 caractÃ¨res).</div>
          </div>
        </div>
      </div>

      <!-- CHOIX ASSISTANT -->
      <div class="form-section">
        <div class="form-section-title">03 â€” Choix de l'assistant</div>
        <div class="form-group">
          <label for="assistant_id">SÃ©lectionner un assistant <span class="required">*</span></label>
          <div class="assistant-select-wrapper">
            <select id="assistant_id" name="assistant_id" required>
              <option value="">â€” Choisissez votre assistant â€”</option>
              <?php
                $defaultAssistant = $isEdit
                    ? (int)($existingGoal['selected_assistant_id'] ?? 0)
                    : $prefillAssistant;
              ?>
              <?php foreach ($assistants as $a): ?>
                <option value="<?= $a['id'] ?>"
                  <?= ((int)$a['id'] === $defaultAssistant ? 'selected' : '') ?>>
                  <?= htmlspecialchars($a['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field-error" id="assistantError">âš  Veuillez sÃ©lectionner un assistant.</div>
        </div>
      </div>

      <!-- ACTIONS -->
      <div class="form-actions">
        <a href="smart-guide.php" class="btn btn-ghost">ðŸ§­ Smart Guide</a>
        <button type="button" class="btn btn-ghost" id="echoMeBtn">ðŸ¤– Echo Me</button>
        <a href="mes-accompagnements.php" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <span class="btn-spinner"></span>
          <span class="btn-text"><?= $isEdit ? 'ðŸ’¾ Enregistrer les modifications' : 'ðŸš€ Soumettre ma demande' ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  // â”€â”€â”€ HELPERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  function showError(id, inputEl) {
    document.getElementById(id).classList.add('visible');
    if (inputEl) inputEl.classList.add('is-invalid');
  }
  function clearError(id, inputEl) {
    document.getElementById(id).classList.remove('visible');
    if (inputEl) inputEl.classList.remove('is-invalid');
  }

  // â”€â”€â”€ CHAR COUNTERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  function initCounter(inputId, counterId, max) {
    const el = document.getElementById(inputId);
    const counter = document.getElementById(counterId);
    if (!el || !counter) return;

    function update() {
      const len = el.value.length;
      counter.textContent = len + ' / ' + max;
      counter.className = 'char-counter' + (len > max * 0.9 ? (len >= max ? ' over' : ' warn') : '');
    }
    el.addEventListener('input', update);
    update();
  }

  initCounter('title', 'titleCounter', 200);
  initCounter('description', 'descCounter', 1000);

  // â”€â”€â”€ LIVE VALIDATION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const titleEl = document.getElementById('title');
  const descEl  = document.getElementById('description');
  const assistEl = document.getElementById('assistant_id');

  titleEl.addEventListener('blur', function () {
    if (this.value.trim().length < 5) showError('titleError', this);
    else clearError('titleError', this);
  });
  titleEl.addEventListener('input', function () {
    if (this.value.trim().length >= 5) clearError('titleError', this);
  });

  descEl.addEventListener('blur', function () {
    if (this.value.trim().length < 20) showError('descError', this);
    else clearError('descError', this);
  });
  descEl.addEventListener('input', function () {
    if (this.value.trim().length >= 20) clearError('descError', this);
  });

  assistEl.addEventListener('change', function () {
    if (this.value) clearError('assistantError', this);
    else showError('assistantError', this);
  });

  // â”€â”€â”€ FULL FORM VALIDATION ON SUBMIT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const form = document.getElementById('goalForm');
  const submitBtn = document.getElementById('submitBtn');

  form.addEventListener('submit', function (e) {
    let valid = true;

    // Type check (radio)
    const typeSelected = form.querySelector('input[name="type"]:checked');
    if (!typeSelected) {
      showError('typeError', null);
      valid = false;
    } else {
      clearError('typeError', null);
    }

    // Title
    if (titleEl.value.trim().length < 5) {
      showError('titleError', titleEl);
      valid = false;
    } else {
      clearError('titleError', titleEl);
    }

    // Description
    if (descEl.value.trim().length < 20) {
      showError('descError', descEl);
      valid = false;
    } else {
      clearError('descError', descEl);
    }

    // Assistant
    if (!assistEl.value) {
      showError('assistantError', assistEl);
      valid = false;
    } else {
      clearError('assistantError', assistEl);
    }

    if (!valid) {
      e.preventDefault();
      // Scroll to first error
      const firstInvalid = form.querySelector('.is-invalid, .field-error.visible');
      if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
  });

  // â”€â”€â”€ TYPE CARD KEYBOARD SUPPORT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  document.querySelectorAll('.type-card input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function () {
      clearError('typeError', null);
    });
  });

  // Echo Me: voice flow (title first, then description)
  const echoMeBtn = document.getElementById('echoMeBtn');
  if (echoMeBtn) {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let listening = false;

    function notify(msg) {
      const toast = document.getElementById('echoToast');
      if (!toast) return;
      toast.textContent = msg;
      toast.style.display = 'block';
      clearTimeout(notify._timer);
      notify._timer = setTimeout(function () {
        toast.style.display = 'none';
      }, 2400);
    }

    function startRecognition(targetInput, done) {
      const rec = new SpeechRecognition();
      rec.lang = 'fr-FR';
      rec.interimResults = false;
      rec.maxAlternatives = 1;

      rec.onresult = function (event) {
        const text = (event.results && event.results[0] && event.results[0][0])
          ? event.results[0][0].transcript
          : '';
        if (text && targetInput) {
          const previous = (targetInput.value || '').trim();
          targetInput.value = previous ? (previous + ' ' + text) : text;
          targetInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
      };

      rec.onerror = function () {
        done(false);
      };
      rec.onend = function () {
        done(true);
      };
      rec.start();
    }

    echoMeBtn.addEventListener('click', function () {
      if (listening) return;
      if (!SpeechRecognition) {
        notify("Le micro n'est pas disponible sur ce navigateur.");
        return;
      }

      const titleEl = document.getElementById('title');
      const descEl = document.getElementById('description');
      if (!titleEl || !descEl) return;

      listening = true;
      echoMeBtn.disabled = true;
      notify("Echo Me: dictez d'abord le titre, puis validez l'Ã©coute.");

      startRecognition(titleEl, function () {
        notify("TrÃ¨s bien. Dictez maintenant la description.");
        startRecognition(descEl, function () {
          listening = false;
          echoMeBtn.disabled = false;
          notify("Echo Me terminÃ©: titre et description ont Ã©tÃ© remplis.");
        });
      });
    });
  }
})();
</script>

<?php require __DIR__ . '/../partials/voice-dictate.php'; ?>
<div id="echoToast" style="display:none;position:fixed;right:20px;bottom:20px;z-index:9999;background:rgba(18,9,47,.96);color:#f4f2ff;border:1px solid rgba(127,61,255,.5);padding:10px 14px;border-radius:10px;box-shadow:0 10px 28px rgba(4,0,20,.4);font-weight:600;max-width:360px;"></div>
<script src="assets/js/main.js"></script>
<script>
(function () {
  if (!window.VoiceDictate) return;
  VoiceDictate.attachButton(document.getElementById('vd-title'),       'title',       { polishUrl: 'echome.php' });
  VoiceDictate.attachButton(document.getElementById('vd-description'), 'description', { polishUrl: 'echome.php' });
})();
</script>

<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>






