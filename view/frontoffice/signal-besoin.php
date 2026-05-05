<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';

$goalCtrl = new GoalController();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

// Handle the color tap: PRG so a refresh doesn't replay the choice.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    $level = $_POST['level'] ?? '';
    $ok = $goalCtrl->setUrgencyLevel($token, $level);
    $redirect = 'signal-besoin.php?token=' . urlencode($token);
    if ($ok) {
        $redirect .= '&saved=' . urlencode($level);
    } else {
        $redirect .= '&error=1';
    }
    header('Location: ' . $redirect);
    exit;
}

$goal = $token !== '' ? $goalCtrl->getGoalByShareToken($token) : null;

$justSaved = $_GET['saved'] ?? null;
$saveError = isset($_GET['error']);

$levels = [
    'simple' => [
        'icon'  => '🟢',
        'label' => 'Simple',
        'desc'  => "Demande rapide, je n'ai pas besoin d'aide pour l'instant.",
        'color' => '#10b981',
        'soft'  => '#d1fae5',
        'dark'  => '#047857',
    ],
    'assistance' => [
        'icon'  => '🟡',
        'label' => "Besoin d'aide",
        'desc'  => "J'aimerais une explication ou un coup de main de mon assistant.",
        'color' => '#f59e0b',
        'soft'  => '#fef3c7',
        'dark'  => '#92400e',
    ],
    'urgent' => [
        'icon'  => '🔴',
        'label' => 'Urgent',
        'desc'  => "Je suis bloqué(e) ou confus(e), j'ai besoin qu'on me recontacte vite.",
        'color' => '#ef4444',
        'soft'  => '#fee2e2',
        'dark'  => '#991b1b',
    ],
];

$typeLabels = [
    'cv'           => '📄 CV',
    'cover_letter' => '✉️ Lettre de motivation',
    'linkedin'     => '🔗 LinkedIn',
    'interview'    => '🎤 Entretien',
    'other'        => '📌 Autre',
];

$currentLevel = $goal['urgency_level'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#5046e5" />
  <title><?= $goal ? 'Signaler mon besoin' : 'Lien invalide' ?> | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
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
      --shadow: 0 4px 24px rgba(80,70,229,.08);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
    }
    .topbar {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: 0 2px 12px rgba(80,70,229,.06);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 800;
      color: var(--accent);
      font-size: 1rem;
      text-decoration: none;
    }
    .brand-mark {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      border-radius: 9px;
      font-size: .78rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
    }
    .pill {
      font-size: .68rem;
      font-weight: 800;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--muted);
      background: var(--surface2);
      padding: 5px 10px;
      border-radius: 100px;
      border: 1px solid var(--border);
    }
    .wrap { max-width: 560px; margin: 0 auto; padding: 22px 16px 80px; }

    .header {
      text-align: center;
      margin-bottom: 22px;
    }
    .header .ico { font-size: 2.4rem; margin-bottom: 6px; }
    .header h1 {
      font-size: 1.55rem;
      font-weight: 800;
      line-height: 1.25;
      margin-bottom: 8px;
    }
    .header p {
      color: var(--muted);
      font-size: .95rem;
      line-height: 1.5;
    }

    .goal-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px 16px;
      box-shadow: var(--shadow);
      margin-bottom: 18px;
    }
    .goal-type {
      display: inline-block;
      font-size: .72rem;
      font-weight: 800;
      color: var(--accent);
      background: var(--accent-soft);
      padding: 3px 10px;
      border-radius: 100px;
      margin-bottom: 6px;
    }
    .goal-title {
      font-size: 1rem;
      font-weight: 800;
      line-height: 1.3;
    }

    .question {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 14px;
      text-align: center;
    }

    /* The 3 large color buttons */
    .level-form { margin: 0; }
    .level-btn {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 22px 20px;
      margin-bottom: 14px;
      border: 3px solid transparent;
      border-radius: 18px;
      background: var(--surface);
      cursor: pointer;
      text-align: left;
      font-family: inherit;
      transition: transform .12s, box-shadow .2s, border-color .2s;
      box-shadow: 0 6px 18px rgba(15, 22, 41, .08);
    }
    .level-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15, 22, 41, .14); }
    .level-btn:active { transform: translateY(0) scale(.99); }
    .level-btn .dot {
      width: 56px; height: 56px;
      border-radius: 50%;
      flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.7rem;
      box-shadow: 0 6px 18px rgba(0,0,0,.18);
    }
    .level-btn .label { font-size: 1.15rem; font-weight: 800; line-height: 1.2; margin-bottom: 4px; }
    .level-btn .desc  { font-size: .87rem; color: var(--muted); line-height: 1.4; }
    .level-btn.is-current {
      transform: scale(1.005);
    }
    .level-btn.is-current .label::after {
      content: '✓ Actuel';
      display: inline-block;
      margin-left: 8px;
      font-size: .68rem;
      letter-spacing: .06em;
      padding: 3px 8px;
      border-radius: 100px;
      vertical-align: 1px;
    }

    /* per-color theming */
    .level-simple .dot      { background: #10b981; color: #fff; }
    .level-simple:hover     { border-color: #10b981; }
    .level-simple.is-current { border-color: #10b981; background: #ecfdf5; }
    .level-simple.is-current .label::after { background: #d1fae5; color: #047857; }

    .level-assistance .dot      { background: #f59e0b; color: #fff; }
    .level-assistance:hover     { border-color: #f59e0b; }
    .level-assistance.is-current { border-color: #f59e0b; background: #fffbeb; }
    .level-assistance.is-current .label::after { background: #fef3c7; color: #92400e; }

    .level-urgent .dot      { background: #ef4444; color: #fff; }
    .level-urgent:hover     { border-color: #ef4444; }
    .level-urgent.is-current { border-color: #ef4444; background: #fef2f2; }
    .level-urgent.is-current .label::after { background: #fee2e2; color: #991b1b; }

    .toast {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 14px;
      font-size: .92rem;
      margin-bottom: 18px;
      animation: toastIn .35s cubic-bezier(.2,.9,.3,1.05);
    }
    @keyframes toastIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: none; }
    }
    .toast-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .toast-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .toast-icon { font-size: 1.3rem; line-height: 1; flex-shrink: 0; margin-top: 1px; }
    .toast-body strong { display: block; margin-bottom: 2px; }

    .footer {
      text-align: center;
      font-size: .76rem;
      color: var(--muted);
      margin-top: 28px;
      padding-top: 18px;
      border-top: 1px solid var(--border);
    }

    .err-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 36px 24px;
      text-align: center;
      box-shadow: var(--shadow);
      margin-top: 32px;
    }
    .err-card .ico { font-size: 3rem; margin-bottom: 12px; }
    .err-card h2 { font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; }
    .err-card p { color: var(--muted); font-size: .92rem; line-height: 1.5; }

    @media (min-width: 560px) {
      .wrap { padding: 32px 24px 80px; }
      .header h1 { font-size: 1.7rem; }
    }
  </style>
</head>
<body>

<header class="topbar">
  <a href="index.html" class="brand">
    <span class="brand-mark">SV</span>
    SecondVoice
  </a>
  <span class="pill">📡 Signal rapide</span>
</header>

<main class="wrap">
<?php if (!$goal): ?>
  <div class="err-card">
    <div class="ico">🔗</div>
    <h2>Lien invalide ou expiré</h2>
    <p>Ce QR ne correspond à aucun accompagnement actif.<br>Demandez à votre assistant de vous renvoyer un nouveau code.</p>
  </div>
<?php else:
  $type = $typeLabels[$goal['type']] ?? '📌 Autre';
?>

  <?php if ($justSaved && isset($levels[$justSaved])):
    $L = $levels[$justSaved];
  ?>
    <div class="toast toast-success">
      <span class="toast-icon">✅</span>
      <div class="toast-body">
        <strong>Signal envoyé !</strong>
        Niveau enregistré : <strong style="color: <?= $L['dark'] ?>;"><?= $L['icon'] ?> <?= htmlspecialchars($L['label']) ?></strong>. Votre assistant verra ce signal sur sa fiche.
      </div>
    </div>
  <?php endif; ?>

  <?php if ($saveError): ?>
    <div class="toast toast-error">
      <span class="toast-icon">⚠️</span>
      <div class="toast-body">
        <strong>Échec de l'enregistrement.</strong>
        Le lien est peut-être invalide. Réessayez ou demandez un nouveau QR à votre assistant.
      </div>
    </div>
  <?php endif; ?>

  <div class="header">
    <div class="ico">📡</div>
    <h1>Comment puis-je vous aider ?</h1>
    <p>Touchez la couleur qui décrit le mieux votre besoin actuel — votre assistant sera prévenu.</p>
  </div>

  <div class="goal-card">
    <div class="goal-type"><?= htmlspecialchars($type) ?></div>
    <div class="goal-title"><?= htmlspecialchars($goal['title']) ?></div>
  </div>

  <div class="question">Quel est votre niveau de besoin&nbsp;?</div>

  <?php foreach ($levels as $key => $L): ?>
    <form method="POST" class="level-form">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <input type="hidden" name="level" value="<?= $key ?>">
      <button type="submit" class="level-btn level-<?= $key ?> <?= $currentLevel === $key ? 'is-current' : '' ?>">
        <span class="dot"><?= $L['icon'] ?></span>
        <span>
          <span class="label"><?= htmlspecialchars($L['label']) ?></span>
          <span class="desc"><?= htmlspecialchars($L['desc']) ?></span>
        </span>
      </button>
    </form>
  <?php endforeach; ?>

  <?php if (!empty($goal['urgency_updated_at']) && $currentLevel): ?>
    <div class="footer">
      Dernier signal envoyé le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($goal['urgency_updated_at']))) ?>.<br>
      🔒 Lien personnel. Toute personne ayant ce QR peut signaler à votre place.
    </div>
  <?php else: ?>
    <div class="footer">
      🔒 Lien personnel. Toute personne ayant ce QR peut signaler à votre place — gardez-le pour vous.
    </div>
  <?php endif; ?>

<?php endif; ?>
</main>

</body>
</html>
