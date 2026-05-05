<?php
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// === AJAX endpoint: polish the raw transcript and return JSON ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = file_get_contents('php://input');
    $data = json_decode($body, true) ?: [];
    $raw  = trim((string)($data['text'] ?? ''));
    if ($raw === '') {
        echo json_encode(['ok' => false, 'error' => 'empty']);
        exit;
    }
    [$polished, $changes] = polishTranscript($raw);
    echo json_encode([
        'ok'        => true,
        'raw'       => $raw,
        'polished'  => $polished,
        'changes'   => $changes,
        'timestamp' => date('H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Turn an imperfectly-spoken French transcript into a clean sentence.
 * Returns [string $polished, array $changes].
 */
function polishTranscript(string $raw): array
{
    $changes = [];
    $text = $raw;

    // 1) Strip filler words
    $fillerCount = 0;
    $fillers = [
        '/\beuh+\b/iu'           => '',
        '/\bheu+\b/iu'            => '',
        '/\bhum+\b/iu'            => '',
        '/\bbah+\b/iu'            => '',
        '/\bben\b/iu'             => '',
        '/\bvoilà quoi\b/iu'      => '',
        '/\b(tu sais|tsé)\b/iu'   => '',
        '/\bquoi\s*([.!?]|$)/iu'  => '$1',
    ];
    foreach ($fillers as $p => $r) {
        $count = 0;
        $text = preg_replace($p, $r, $text, -1, $count);
        $fillerCount += $count;
    }
    if ($fillerCount > 0) {
        $changes[] = "Mots de remplissage retirés ($fillerCount)";
    }

    // 2) Common informal → polite contractions (French)
    $contractionFixes = 0;
    $replacements = [
        '/\bj\s*veu(x|t)?\b/iu'       => 'je veux',
        '/\bjveux\b/iu'                => 'je veux',
        '/\bj\s+ai\b/iu'               => "j'ai",
        '/\bjai\b/iu'                  => "j'ai",
        '/\bchais pas\b/iu'            => 'je ne sais pas',
        '/\bj\s*sais pas\b/iu'         => 'je ne sais pas',
        '/\bjsais\b/iu'                => 'je sais',
        '/\bjsuis\b/iu'                => 'je suis',
        '/\bj\s+suis\b/iu'             => 'je suis',
        '/\bjpense\b/iu'               => 'je pense',
        '/\bçui\b/iu'                  => 'celui',
        '/\bouais\b/iu'                => 'oui',
        '/\bj peux\b/iu'               => 'je peux',
        '/\bjpeux\b/iu'                => 'je peux',
        '/\bj voudrais\b/iu'           => 'je voudrais',
        '/\bj aimerais\b/iu'           => "j'aimerais",
        '/\bjaimerais\b/iu'            => "j'aimerais",
        '/\bj besoin\b/iu'             => "j'ai besoin",
        '/\bdu coup\b/iu'              => 'donc',
        '/\bgenre\b/iu'                => '',
        // Common French elisions that STT drops apostrophes on
        '/\bm aider\b/iu'              => "m'aider",
        '/\bm appelle\b/iu'            => "m'appelle",
        '/\bn est\b/iu'                => "n'est",
        '/\bc est\b/iu'                => "c'est",
        '/\bd accord\b/iu'             => "d'accord",
        '/\bl ai\b/iu'                 => "l'ai",
        '/\bs il vous plaît\b/iu'      => "s'il vous plaît",
        '/\bs il te plaît\b/iu'        => "s'il te plaît",
        '/\bpourriez vous\b/iu'        => 'pourriez-vous',
        '/\bpouvez vous\b/iu'          => 'pouvez-vous',
        '/\bpeux tu\b/iu'              => 'peux-tu',
        '/\best ce que\b/iu'           => 'est-ce que',
    ];
    foreach ($replacements as $p => $r) {
        $count = 0;
        $text = preg_replace($p, $r, $text, -1, $count);
        $contractionFixes += $count;
    }
    if ($contractionFixes > 0) {
        $changes[] = "Tournures corrigées ($contractionFixes)";
    }

    // 3) Normalize whitespace
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    // 4) Detect if it's a question (so we punctuate correctly)
    $isQuestion = (bool) preg_match(
        '/^(est-ce|qu(\'|e\s|i\s)|où|quand|comment|pourquoi|combien|peux-tu|peut-on|pourriez-vous|pouvez-vous)\b/iu',
        $text
    );

    // 5) Capitalize first letter
    if ($text !== '') {
        $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    // 6) Add ending punctuation if missing
    if ($text !== '' && !preg_match('/[.!?…]$/u', $text)) {
        if ($isQuestion) {
            $text .= ' ?';
            $changes[] = 'Point d\'interrogation ajouté';
        } else {
            $text .= '.';
            $changes[] = 'Ponctuation finale ajoutée';
        }
    }

    // 7) Fix space-before-punctuation French rules (NBSP-ish, here we use plain space)
    $text = preg_replace('/\s*([?!:;])/u', ' $1', $text);
    $text = preg_replace('/\s*([.,])/u', '$1', $text);
    // Collapse double spaces a final time
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    if (empty($changes)) {
        $changes[] = 'Texte déjà clair — aucune correction nécessaire';
    }

    return [$text, $changes];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#5046e5" />
  <title>Echo Me — Parlez librement | SecondVoice</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
  <!-- Vosk speech recognition (WASM, runs entirely in the browser, no API key, no server) -->
  <script src="https://cdn.jsdelivr.net/npm/vosk-browser@0.0.8/dist/vosk.js"></script>
  <style>
    :root {
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
      --shadow: 0 4px 24px rgba(80,70,229,.08);
      --shadow-lg: 0 16px 48px rgba(80,70,229,.18);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background:
        radial-gradient(1200px 600px at 50% -10%, rgba(80,70,229,.16), transparent 60%),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    .site-header {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 0 24px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky; top: 0; z-index: 50;
      box-shadow: 0 2px 12px rgba(80,70,229,.06);
    }
    .brand {
      display: flex; align-items: center; gap: 10px;
      font-weight: 800; font-size: 1.05rem; color: var(--accent);
      text-decoration: none;
    }
    .brand-mark {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      border-radius: 10px;
      font-size: .8rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 6px 16px rgba(80,70,229,.4);
    }
    .header-nav { display: flex; gap: 6px; }
    .header-nav a {
      padding: 8px 14px;
      border-radius: 8px;
      font-size: .85rem;
      font-weight: 600;
      color: var(--muted);
      text-decoration: none;
      transition: all .15s;
    }
    .header-nav a:hover { color: var(--text); background: var(--surface2); }
    .header-nav a.active { color: var(--accent); background: var(--accent-soft); }

    .wrap { max-width: 720px; margin: 0 auto; padding: 32px 20px 80px; }

    .hero {
      text-align: center;
      margin-bottom: 36px;
    }
    .tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: .72rem; font-weight: 800; letter-spacing: .08em;
      text-transform: uppercase;
      padding: 5px 12px;
      border-radius: 100px;
      margin-bottom: 14px;
    }
    h1 {
      font-size: 2.1rem; font-weight: 800; line-height: 1.15;
      margin-bottom: 10px;
      background: linear-gradient(135deg, #0f1629, #5046e5);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .lead {
      color: var(--muted);
      font-size: 1rem; line-height: 1.55;
      max-width: 520px; margin: 0 auto;
    }

    /* MIC */
    .mic-stage {
      display: flex; flex-direction: column; align-items: center;
      gap: 16px;
      margin: 28px 0 36px;
      position: relative;
    }
    .mic-btn {
      width: 140px; height: 140px;
      border-radius: 50%;
      border: none;
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      font-size: 3.4rem;
      cursor: pointer;
      box-shadow:
        0 18px 38px rgba(80,70,229,.35),
        inset 0 -6px 14px rgba(0,0,0,.18),
        inset 0 4px 10px rgba(255,255,255,.18);
      position: relative;
      transition: transform .15s, box-shadow .2s;
      z-index: 2;
    }
    .mic-btn:hover  { transform: translateY(-2px); box-shadow: 0 22px 46px rgba(80,70,229,.45), inset 0 -6px 14px rgba(0,0,0,.18), inset 0 4px 10px rgba(255,255,255,.18); }
    .mic-btn:active { transform: translateY(0); }
    .mic-btn.is-listening {
      background: linear-gradient(135deg, #ef4444, #b91c1c);
      box-shadow:
        0 18px 38px rgba(239,68,68,.45),
        inset 0 -6px 14px rgba(0,0,0,.18),
        inset 0 4px 10px rgba(255,255,255,.2);
    }
    .mic-btn.is-listening::before,
    .mic-btn.is-listening::after {
      content: '';
      position: absolute;
      inset: -4px;
      border-radius: 50%;
      border: 3px solid rgba(239,68,68,.55);
      animation: micPulse 1.6s ease-out infinite;
      pointer-events: none;
    }
    .mic-btn.is-listening::after { animation-delay: .8s; }
    @keyframes micPulse {
      0%   { transform: scale(1);   opacity: .9; }
      100% { transform: scale(1.7); opacity: 0; }
    }

    .mic-hint {
      font-size: .9rem;
      color: var(--muted);
      font-weight: 600;
    }
    .mic-hint .dot {
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--success);
      margin-right: 6px;
      vertical-align: middle;
      box-shadow: 0 0 0 3px rgba(16,185,129,.18);
    }
    .mic-hint.recording .dot {
      background: var(--danger);
      box-shadow: 0 0 0 3px rgba(239,68,68,.22);
      animation: dotBlink 1.1s linear infinite;
    }
    @keyframes dotBlink { 50% { opacity: .35; } }

    /* MIC DEVICE PICKER */
    .mic-picker {
      display: flex; align-items: center; gap: 8px;
      flex-wrap: wrap;
      max-width: 460px;
      margin: 8px auto 4px;
      padding: 10px 14px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: .82rem;
      color: var(--muted);
      font-weight: 600;
    }
    .mic-picker label { white-space: nowrap; }
    .mic-picker select {
      flex: 1;
      min-width: 160px;
      padding: 7px 10px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: .82rem;
      background: var(--surface2);
      color: var(--text);
      outline: none;
    }
    .mic-picker select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(80,70,229,.12); }
    .mini-btn {
      padding: 7px 12px;
      border: none;
      border-radius: 8px;
      background: var(--accent-soft);
      color: var(--accent);
      font-family: inherit;
      font-size: .8rem;
      font-weight: 700;
      cursor: pointer;
    }
    .mini-btn:hover { background: #d6d2ff; }
    .mini-btn.is-on { background: var(--danger-soft); color: var(--danger); }

    /* VOSK status banner (model load + recognition state) */
    .vosk-status {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: .88rem;
      font-weight: 700;
      margin-bottom: 16px;
      background: #eef2ff;
      color: #3730a3;
      border: 1px solid #c7d2fe;
      animation: slideIn .3s ease;
    }
    .vosk-status.ok    { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .vosk-status.err   { background: var(--danger-soft); color: #991b1b; border-color: #fecaca; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

    /* MIC LEVEL METER */
    .mic-meter {
      width: 100%;
      max-width: 360px;
      margin: 6px auto 0;
      text-align: center;
    }
    .mic-meter-label {
      font-size: .78rem;
      font-weight: 700;
      color: var(--muted);
      margin-bottom: 6px;
    }
    .mic-meter-label #meterStatus {
      color: var(--muted);
      font-weight: 600;
      letter-spacing: .03em;
    }
    .mic-meter-label.active #meterStatus { color: var(--success); }
    .mic-meter-label.silent #meterStatus { color: var(--danger); }
    .mic-meter-bar {
      height: 12px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 100px;
      overflow: hidden;
      box-shadow: inset 0 1px 3px rgba(0,0,0,.06);
    }
    .mic-meter-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #10b981 0%, #84cc16 35%, #f59e0b 70%, #ef4444 95%);
      border-radius: 100px;
      transition: width 60ms linear;
    }
    .mic-meter-hint {
      font-size: .72rem;
      color: var(--muted);
      margin-top: 6px;
      line-height: 1.4;
    }
    .speak-tips {
      max-width: 460px;
      margin: 14px auto 0;
      padding: 10px 14px;
      background: var(--accent-soft);
      color: var(--accent);
      border: 1px solid #d6d2ff;
      border-radius: 12px;
      font-size: .82rem;
      line-height: 1.5;
      text-align: center;
    }
    .speak-tips strong { color: var(--accent-hover); }

    /* TYPED INPUT (primary, always visible) */
    .text-input-card {
      background: var(--surface);
      border: 1.5px solid #d6d2ff;
      border-radius: 16px;
      padding: 22px 22px 18px;
      margin: 0 0 24px;
      box-shadow: var(--shadow);
      position: relative;
    }
    .ti-divider {
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--bg);
      padding: 0 16px;
    }
    .ti-divider span {
      display: inline-block;
      font-size: .72rem;
      font-weight: 800;
      letter-spacing: .12em;
      color: var(--muted);
      background: var(--accent-soft);
      color: var(--accent);
      padding: 4px 12px;
      border-radius: 100px;
    }
    .ti-label {
      font-size: .85rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 12px;
    }
    .text-input-card textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-size: .95rem;
      resize: vertical;
      outline: none;
      transition: border-color .15s, box-shadow .15s;
      color: var(--text);
      background: var(--surface2);
      margin-bottom: 12px;
    }
    .text-input-card textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(80,70,229,.12);
    }
    .text-input-card button { align-self: flex-start; }

    /* DIAG */
    .debug-box {
      margin-top: 28px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 16px;
      font-size: .82rem;
    }
    .debug-box summary {
      cursor: pointer;
      font-weight: 700;
      color: var(--muted);
      list-style: none;
    }
    .debug-box summary::-webkit-details-marker { display: none; }
    .debug-log {
      margin-top: 10px;
      max-height: 180px;
      overflow: auto;
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
      font-size: .76rem;
      color: var(--text);
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 12px;
      line-height: 1.5;
      white-space: pre-wrap;
    }
    .debug-log .ok    { color: #047857; }
    .debug-log .err   { color: #991b1b; }
    .debug-log .warn  { color: #92400e; }
    .debug-log .ev    { color: #1d4ed8; }

    /* TRANSCRIPT PANELS */
    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 22px;
      margin-bottom: 16px;
      box-shadow: var(--shadow);
      transition: box-shadow .2s, border-color .2s;
    }
    .panel.is-active {
      border-color: rgba(80,70,229,.35);
      box-shadow: var(--shadow-lg);
    }
    .panel-label {
      font-size: .72rem;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 10px;
      display: flex; align-items: center; gap: 6px;
    }
    .panel-text {
      font-size: 1.05rem;
      line-height: 1.6;
      color: var(--text);
      min-height: 1.8em;
      word-wrap: break-word;
    }
    .panel-text.muted { color: #b0b9d2; font-style: italic; }
    .panel.polished .panel-text {
      font-size: 1.4rem;
      font-weight: 700;
      line-height: 1.4;
      letter-spacing: -.005em;
    }
    .panel.polished {
      background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);
      border-color: #d6d2ff;
    }

    .changes {
      display: flex; flex-wrap: wrap; gap: 6px;
      margin-top: 12px;
    }
    .changes .chip {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: .72rem; font-weight: 700;
      padding: 3px 10px;
      border-radius: 100px;
      background: var(--accent-soft);
      color: var(--accent);
    }

    /* ACTIONS */
    .actions {
      display: flex; gap: 10px; flex-wrap: wrap;
      justify-content: center;
      margin: 8px 0 28px;
    }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 12px 22px;
      font-family: inherit;
      font-size: .9rem;
      font-weight: 700;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: transform .12s, filter .15s, box-shadow .2s;
    }
    .btn-primary {
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      box-shadow: 0 8px 20px rgba(80,70,229,.32);
    }
    .btn-primary:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .btn-ghost {
      background: var(--surface);
      color: var(--text);
      border: 1.5px solid var(--border);
    }
    .btn-ghost:hover { background: var(--surface2); }
    .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    /* HISTORY */
    .history {
      margin-top: 32px;
    }
    .history-title {
      font-size: .82rem;
      font-weight: 800;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 12px;
    }
    .history-empty {
      text-align: center;
      color: var(--muted);
      font-size: .88rem;
      padding: 20px;
      background: var(--surface);
      border: 1px dashed var(--border);
      border-radius: 14px;
    }
    .history-item {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 16px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: background .15s;
    }
    .history-item:hover { background: var(--surface2); }
    .history-item .text { flex: 1; font-size: .95rem; line-height: 1.4; }
    .history-item .time {
      font-size: .72rem;
      color: var(--muted);
      font-weight: 600;
      flex-shrink: 0;
    }
    .history-item .ico-btn {
      width: 32px; height: 32px;
      border: none;
      border-radius: 8px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: 1rem;
      cursor: pointer;
      transition: background .15s;
      flex-shrink: 0;
    }
    .history-item .ico-btn:hover { background: #d6d2ff; }

    /* TOAST / FATAL */
    .alert {
      padding: 14px 16px;
      border-radius: 12px;
      font-size: .9rem;
      font-weight: 600;
      margin-bottom: 16px;
      display: flex; align-items: flex-start; gap: 10px;
    }
    .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .alert-err  { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; }

    @media (max-width: 480px) {
      .header-nav { display: none; }
      .wrap { padding: 24px 14px 80px; }
      h1 { font-size: 1.7rem; }
      .panel.polished .panel-text { font-size: 1.2rem; }
      .mic-btn { width: 120px; height: 120px; font-size: 2.8rem; }
    }
  </style>
</head>
<body>

<header class="site-header">
  <a href="index.html" class="brand">
    <span class="brand-mark">SV</span>
    SecondVoice
  </a>
  <nav class="header-nav">
    <a href="index.html">Accueil</a>
    <a href="mes-accompagnements.php">Mes accompagnements</a>
    <a href="echome.php" class="active">Echo Me</a>
    <a href="profile.php">Mon profil</a>
  </nav>
</header>

<main class="wrap">

  <section class="hero">
    <div class="tag">🎙️ Assistant vocal</div>
    <h1>Echo Me</h1>
    <p class="lead">
      Parlez à voix basse, hésitez, bafouillez — ou <strong>tapez</strong> simplement.<br>
      Echo Me transforme votre intention en une <strong>phrase claire</strong>, puis la lit à voix haute.
    </p>
  </section>

  <div id="unsupported" class="alert alert-warn" style="display:none;">
    <span>⚠️</span>
    <div>
      Votre navigateur ne supporte pas la reconnaissance vocale.<br>
      Utilisez <strong>Chrome</strong>, <strong>Edge</strong> ou <strong>Safari</strong> récent (sur HTTPS ou localhost).
    </div>
  </div>

  <div id="errorBox" class="alert alert-err" style="display:none;">
    <span>⚠️</span>
    <div id="errorMsg"></div>
  </div>

  <div id="insecureBox" class="alert alert-warn" style="display:none;">
    <span>🔒</span>
    <div>
      <strong>Le micro ne peut pas démarrer sur cette URL.</strong><br>
      Chrome n'autorise l'accès au micro que sur <code>localhost</code> ou en <strong>HTTPS</strong>.<br>
      Hôte actuel&nbsp;: <code id="currentHost"></code>.<br>
      Solution&nbsp;: ouvrez cette page via <code>http://localhost/...</code> sur le PC,
      ou via votre URL tunnel <code>https://...lhr.life/...</code>.
      <br>(Vous pouvez quand même utiliser le mode « Tapez votre phrase » ci-dessous.)
    </div>
  </div>

  <div id="voskStatus" class="vosk-status" style="display:none;"></div>

  <div class="mic-stage">
    <button id="micBtn" class="mic-btn" type="button" aria-label="Activer le micro">🎤</button>
    <div id="micHint" class="mic-hint">
      <span class="dot"></span>
      Cliquez sur le micro et parlez librement
    </div>

    <div class="mic-picker">
      <label for="micDevice">🎙️ Périphérique d'entrée&nbsp;:</label>
      <select id="micDevice"><option value="">(par défaut Chrome)</option></select>
      <button type="button" id="testMicBtn" class="mini-btn">🔊 Tester</button>
    </div>

    <div class="mic-meter">
      <div class="mic-meter-label">🎚️ Niveau du micro <span id="meterStatus">en attente</span></div>
      <div class="mic-meter-bar">
        <div id="micMeterFill" class="mic-meter-fill"></div>
      </div>
      <div class="mic-meter-hint">
        Si la barre ne bouge pas quand vous parlez, sélectionnez un autre périphérique ci-dessus
        (évitez « Mixage stéréo »&nbsp;: il n'enregistre que les sons des haut-parleurs).
      </div>
    </div>

    <div class="speak-tips">
      💡 <strong>Pour de meilleurs résultats</strong>&nbsp;:
      parlez <strong>à voix posée</strong> (pas en chuchotant), <strong>près du micro</strong> (15–30&nbsp;cm),
      et faites des <strong>phrases complètes</strong> sans grande pause au milieu.
    </div>
  </div>

  <div class="text-input-card">
    <div class="ti-divider"><span>OU</span></div>
    <div class="ti-label">⌨️ Tapez votre phrase (mode toujours fiable)</div>
    <textarea id="textInput" rows="2" placeholder="Tapez ici un texte imparfait, par exemple : « euh j veu aller au docteur quoi »"></textarea>
    <button id="textBtn" class="btn btn-primary" type="button">✨ Clarifier ce texte</button>
  </div>

  <div id="rawPanel" class="panel">
    <div class="panel-label">📝 Ce que j'ai entendu</div>
    <div id="rawText" class="panel-text muted">Votre voix apparaîtra ici en direct…</div>
  </div>

  <div id="polishedPanel" class="panel polished">
    <div class="panel-label">✨ Phrase clarifiée</div>
    <div id="polishedText" class="panel-text muted">La version polie s'affichera ici.</div>
    <div id="changesRow" class="changes" style="display:none;"></div>
  </div>

  <div class="actions">
    <button id="repeatBtn" class="btn btn-primary" type="button" disabled>
      🔁 Répéter à voix haute
    </button>
    <button id="createGoalBtn" class="btn btn-primary" type="button" disabled
            title="Démarrer une demande d'accompagnement avec ce texte">
      📝 Créer une demande
    </button>
    <button id="copyBtn" class="btn btn-ghost" type="button" disabled>
      📋 Copier
    </button>
    <button id="rePolishBtn" class="btn btn-ghost" type="button" disabled>
      ✨ Reformuler
    </button>
  </div>

  <section class="history">
    <div class="history-title">📜 Historique récent (cette session)</div>
    <div id="historyList">
      <div class="history-empty" id="historyEmpty">
        Aucune phrase pour le moment. Parlez pour commencer&nbsp;!
      </div>
    </div>
  </section>

  <details class="debug-box">
    <summary>🔧 Diagnostic micro (cliquez si rien ne marche)</summary>
    <div id="debugLog" class="debug-log">En attente d'événements…</div>
  </details>

</main>

<?php require __DIR__ . '/../partials/role-switcher.php'; ?>

<script>
(function () {
  const synth = window.speechSynthesis;

  // ─── Diagnostic logger ───
  const dbgEl = document.getElementById('debugLog');
  function dbg(msg, cls) {
    if (!dbgEl) return;
    const t = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    if (cls) line.className = cls;
    line.textContent = '[' + t + '] ' + msg;
    if (dbgEl.firstChild && dbgEl.firstChild.textContent &&
        dbgEl.firstChild.textContent.startsWith('En attente')) {
      dbgEl.innerHTML = '';
    }
    dbgEl.insertBefore(line, dbgEl.firstChild);
  }

  dbg('Page chargée — UA: ' + navigator.userAgent.split(') ')[0] + ')', 'ev');
  dbg('URL: ' + location.href, 'ev');
  dbg('Vosk lib: ' + (typeof Vosk !== 'undefined' ? 'chargée' : 'NON chargée'), typeof Vosk !== 'undefined' ? 'ok' : 'err');
  dbg('isSecureContext: ' + window.isSecureContext, window.isSecureContext ? 'ok' : 'warn');

  const micBtn        = document.getElementById('micBtn');
  const micHint       = document.getElementById('micHint');
  const rawPanel      = document.getElementById('rawPanel');
  const rawText       = document.getElementById('rawText');
  const polishedPanel = document.getElementById('polishedPanel');
  const polishedText  = document.getElementById('polishedText');
  const changesRow    = document.getElementById('changesRow');
  const repeatBtn     = document.getElementById('repeatBtn');
  const copyBtn       = document.getElementById('copyBtn');
  const rePolishBtn   = document.getElementById('rePolishBtn');
  const createGoalBtn = document.getElementById('createGoalBtn');
  const historyList   = document.getElementById('historyList');
  const historyEmpty  = document.getElementById('historyEmpty');
  const errorBox      = document.getElementById('errorBox');
  const errorMsg      = document.getElementById('errorMsg');

  let lastPolished = '';
  let lastRaw      = '';

  // Wire the typed-text fallback (works even when STT is unavailable)
  const textInput = document.getElementById('textInput');
  const textBtn   = document.getElementById('textBtn');
  textBtn.addEventListener('click', () => {
    const v = (textInput.value || '').trim();
    if (!v) {
      textInput.focus();
      return;
    }
    rawText.textContent = v;
    rawText.classList.remove('muted');
    lastRaw = v;
    polish(v);
  });
  textInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); textBtn.click(); }
  });

  if (typeof Vosk === 'undefined') {
    document.getElementById('unsupported').style.display = 'flex';
    document.getElementById('unsupported').innerHTML = '<span>⚠️</span><div>La bibliothèque de reconnaissance vocale n\'a pas pu être chargée. Vérifiez votre connexion Internet, ou utilisez le mode « Tapez votre phrase ».</div>';
    micBtn.disabled = true;
    return;
  }

  // getUserMedia silently fails on plain HTTP non-localhost.
  const isLocalhost = ['localhost', '127.0.0.1', '::1'].includes(location.hostname);
  const isSecure    = window.isSecureContext || isLocalhost;
  if (!isSecure) {
    document.getElementById('currentHost').textContent = location.host;
    document.getElementById('insecureBox').style.display = 'flex';
    dbg('Contexte non sécurisé — getUserMedia bloqué sur ce hôte', 'err');
  }

  // ─── Real-time microphone level meter ───
  const meterFill   = document.getElementById('micMeterFill');
  const meterLabel  = document.querySelector('.mic-meter-label');
  const meterStatus = document.getElementById('meterStatus');
  const deviceSel   = document.getElementById('micDevice');
  const testMicBtn  = document.getElementById('testMicBtn');
  let meterStop = null;
  let everHeard = false;

  // ─── Populate the device picker ───
  async function populateDevices() {
    try {
      // Need permission first to get device labels
      const probe = await navigator.mediaDevices.getUserMedia({ audio: true });
      probe.getTracks().forEach(t => t.stop());
      const all = await navigator.mediaDevices.enumerateDevices();
      const inputs = all.filter(d => d.kind === 'audioinput');
      deviceSel.innerHTML = '<option value="">(défaut)</option>';
      let bestId = '';
      for (const d of inputs) {
        const opt = document.createElement('option');
        opt.value = d.deviceId;
        opt.textContent = d.label || ('Périphérique ' + (deviceSel.options.length));
        // Auto-pick: prefer real mics over Stereo Mix / Loopback
        if (!bestId && /micro|microphone|realtek|array|usb|headset|casque/i.test(d.label) &&
            !/mixage|stereo|loopback|virtual/i.test(d.label)) {
          bestId = d.deviceId;
        }
        deviceSel.appendChild(opt);
      }
      const saved = localStorage.getItem('echome_mic') || '';
      if (saved && inputs.some(d => d.deviceId === saved)) {
        deviceSel.value = saved;
      } else if (bestId) {
        deviceSel.value = bestId;
        localStorage.setItem('echome_mic', bestId);
      }
      dbg('Périphériques détectés (' + inputs.length + ') — sélectionné: ' + (deviceSel.options[deviceSel.selectedIndex]?.textContent || 'défaut'), 'ok');
    } catch (e) {
      dbg('enumerateDevices: échec — ' + e.message, 'err');
    }
  }
  deviceSel.addEventListener('change', () => {
    localStorage.setItem('echome_mic', deviceSel.value);
    if (meterStop) { meterStop(); startMicMeter(); }
  });
  // Run after DOMContentLoaded so the SR check above doesn't block it
  populateDevices();

  // ─── "Tester" button: just visualize the mic level (no recognition) ───
  testMicBtn.addEventListener('click', () => {
    if (meterStop) {
      meterStop();
      testMicBtn.classList.remove('is-on');
      testMicBtn.textContent = '🔊 Tester';
    } else {
      startMicMeter();
      testMicBtn.classList.add('is-on');
      testMicBtn.textContent = '⏹ Arrêter';
    }
  });

  function micConstraints() {
    // Chrome's built-in DSP — these dramatically improve Vosk recognition:
    //   echoCancellation: removes loopback (TTS feedback while mic is open)
    //   noiseSuppression: filters out fan/keyboard/AC ambient noise
    //   autoGainControl:  boosts quiet/whispered voices automatically
    const audio = {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl:  true,
      channelCount: 1,
    };
    if (deviceSel.value) audio.deviceId = { exact: deviceSel.value };
    return { audio };
  }

  async function startMicMeter() {
    if (meterStop) return true; // already running
    try {
      const stream = await navigator.mediaDevices.getUserMedia(micConstraints());
      const Ctx = window.AudioContext || window.webkitAudioContext;
      const ctx = new Ctx();
      const src = ctx.createMediaStreamSource(stream);
      const an  = ctx.createAnalyser();
      an.fftSize = 256;
      src.connect(an);
      const data = new Uint8Array(an.frequencyBinCount);
      let raf;
      everHeard = false;
      function tick() {
        an.getByteFrequencyData(data);
        let sum = 0;
        for (let i = 0; i < data.length; i++) sum += data[i];
        const avg = sum / data.length;
        const pct = Math.min(100, avg * 1.6);
        meterFill.style.width = pct + '%';
        if (pct > 5)  { everHeard = true; meterLabel.classList.add('active'); meterLabel.classList.remove('silent'); meterStatus.textContent = 'capture en cours ✓'; }
        raf = requestAnimationFrame(tick);
      }
      tick();
      meterLabel.classList.remove('silent');
      meterLabel.classList.add('active');
      meterStatus.textContent = 'micro ouvert…';
      dbg('Niveau micro: capture démarrée', 'ok');
      meterStop = () => {
        cancelAnimationFrame(raf);
        try { stream.getTracks().forEach(t => t.stop()); } catch (e) {}
        try { ctx.close(); } catch (e) {}
        meterFill.style.width = '0%';
        meterLabel.classList.remove('active');
        if (!everHeard) {
          meterLabel.classList.add('silent');
          meterStatus.textContent = 'aucun son détecté ✗';
          dbg('Niveau micro: AUCUN son capté pendant la session', 'err');
        } else {
          meterStatus.textContent = 'arrêté';
        }
        meterStop = null;
      };
      return true;
    } catch (e) {
      dbg('Niveau micro: échec getUserMedia — ' + e.message, 'err');
      const help = e.name === 'NotAllowedError'
        ? "Permission micro refusée. Cliquez sur l'icône 🔒 dans la barre d'adresse → Microphone → Autoriser."
        : (e.name === 'NotFoundError' ? 'Aucun micro détecté.' : ('Erreur micro : ' + e.message));
      showError(help);
      return false;
    }
  }

  let listening = false;

  function showError(msg) {
    errorMsg.textContent = msg;
    errorBox.style.display = 'flex';
    setTimeout(() => { errorBox.style.display = 'none'; }, 5500);
  }

  function setListening(on) {
    listening = on;
    micBtn.classList.toggle('is-listening', on);
    micHint.classList.toggle('recording', on);
    micHint.innerHTML = on
      ? '<span class="dot"></span>Je vous écoute… cliquez à nouveau pour stopper'
      : '<span class="dot"></span>Cliquez sur le micro et parlez librement';
    rawPanel.classList.toggle('is-active', on);
  }

  // ─── VOSK: in-browser French speech recognition (no Chrome STT, no API key) ───
  const voskStatusEl = document.getElementById('voskStatus');
  let voskModel    = null;
  let voskLoading  = false;
  let voskFailed   = null;
  let voskRunning  = null; // { ctx, src, node, recognizer, stream }

  function setVoskStatus(text, kind) {
    if (!voskStatusEl) return;
    voskStatusEl.textContent = text;
    voskStatusEl.className = 'vosk-status' + (kind ? (' ' + kind) : '');
    voskStatusEl.style.display = text ? 'flex' : 'none';
  }

  // While the model loads, the mic button is disabled and the hint reflects status.
  function setMicReady(ready, msg) {
    micBtn.disabled = !ready;
    micBtn.style.opacity = ready ? '1' : '.55';
    micBtn.style.cursor  = ready ? 'pointer' : 'wait';
    if (msg) {
      micHint.innerHTML = '<span class="dot"></span>' + msg;
    }
  }

  async function loadVosk() {
    if (voskModel) return true;
    if (voskFailed) return false;
    if (voskLoading) {
      while (voskLoading) await new Promise(r => setTimeout(r, 200));
      return !!voskModel;
    }
    voskLoading = true;
    setMicReady(false, '⏳ Téléchargement du modèle vocal (~46 Mo)…');
    setVoskStatus('⏳ Téléchargement du modèle vocal français (~46 Mo, mis en cache ensuite)… cela peut prendre 30 à 60 secondes selon votre connexion.');
    dbg('Vosk: début chargement du modèle', 'ev');
    try {
      voskModel = await Vosk.createModel(
        'https://ccoreilly.github.io/vosk-browser/models/vosk-model-small-fr-pguyot-0.3.tar.gz'
      );
      dbg('Vosk: modèle chargé ✓', 'ok');
      setVoskStatus('✅ Modèle vocal prêt — cliquez sur le micro et parlez', 'ok');
      setMicReady(true, 'Cliquez sur le micro et parlez librement');
      setTimeout(() => setVoskStatus(''), 5000);
      return true;
    } catch (e) {
      voskFailed = e;
      dbg('Vosk: échec — ' + e.message, 'err');
      setVoskStatus('❌ Échec chargement du modèle: ' + e.message + '. Utilisez la zone "Tapez votre phrase" ci-dessous.', 'err');
      setMicReady(false, '❌ Modèle vocal indisponible — utilisez la zone de texte');
      return false;
    } finally {
      voskLoading = false;
    }
  }

  // Pre-load the model in the background so it's ready when the user clicks.
  setMicReady(false, '⏳ Initialisation du modèle vocal…');
  loadVosk();

  async function startVoskRec() {
    if (voskRunning) {
      dbg('startVoskRec ignoré: déjà en cours', 'warn');
      return true;
    }
    if (!await loadVosk()) return false;

    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia(micConstraints());
    } catch (e) {
      dbg('getUserMedia rejeté: ' + e.message, 'err');
      const help = e.name === 'NotAllowedError'
        ? "Permission micro refusée. Cliquez sur l'icône 🔒 dans la barre d'adresse → Microphone → Autoriser."
        : ('Erreur micro : ' + e.message);
      showError(help);
      return false;
    }

    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    // Browser autoplay policies sometimes start a new AudioContext as 'suspended';
    // explicit resume is needed before audio can flow through the graph.
    if (ctx.state === 'suspended') {
      try { await ctx.resume(); dbg('Vosk: AudioContext resumed', 'ok'); }
      catch (e) { dbg('Vosk: ctx.resume échec — ' + e.message, 'err'); }
    }
    const sr = ctx.sampleRate;
    dbg('Vosk: AudioContext sample rate = ' + sr + ' Hz, state=' + ctx.state, 'ev');

    const recognizer = new voskModel.KaldiRecognizer(sr);
    recognizer.setWords(true);

    recognizer.on('result', (msg) => {
      const t = ((msg && msg.result && msg.result.text) || '').trim();
      if (t) {
        dbg('Vosk result: "' + t + '"', 'ok');
        rawText.textContent = t;
        rawText.classList.remove('muted');
        lastRaw = t;
        polish(t);
      }
    });
    recognizer.on('partialresult', (msg) => {
      const p = ((msg && msg.result && msg.result.partial) || '').trim();
      if (p) {
        rawText.textContent = p + '…';
        rawText.classList.remove('muted');
      }
    });
    recognizer.on('error', (msg) => dbg('Vosk error: ' + JSON.stringify(msg), 'err'));

    const src  = ctx.createMediaStreamSource(stream);
    const node = ctx.createScriptProcessor(4096, 1, 1);
    let chunkCount = 0;
    let errorCount = 0;
    node.onaudioprocess = (event) => {
      try {
        recognizer.acceptWaveform(event.inputBuffer);
        chunkCount++;
        // Periodically log progress so we know audio is flowing
        if (chunkCount === 10 || chunkCount === 50 || chunkCount % 200 === 0) {
          dbg('Vosk: ' + chunkCount + ' chunks audio traités', 'ev');
        }
      } catch (e) {
        errorCount++;
        if (errorCount <= 3) dbg('Vosk acceptWaveform erreur: ' + e.message, 'err');
      }
    };
    src.connect(node);
    node.connect(ctx.destination);

    voskRunning = { ctx, src, node, recognizer, stream };
    dbg('Vosk: capture en route — parlez !', 'ok');
    return true;
  }

  async function stopVoskRec() {
    if (!voskRunning) return;
    const { ctx, src, node, recognizer, stream } = voskRunning;
    voskRunning = null;
    // Order matters: disconnect graph first, then release resources.
    try { node.onaudioprocess = null; } catch (e) {}
    try { src.disconnect(node); }       catch (e) {}
    try { node.disconnect(); }          catch (e) {}
    try { stream.getTracks().forEach(t => { t.stop(); }); } catch (e) {}
    try { recognizer.remove(); }        catch (e) {}
    try { await ctx.close(); }          catch (e) {}
    dbg('Vosk: arrêt complet', 'ev');
  }

  micBtn.addEventListener('click', async () => {
    if (voskRunning) {
      dbg('Bouton micro cliqué — arrêt de la session', 'ev');
      // Disable while we tear down so a second click during cleanup can't double-start.
      micBtn.disabled = true;
      try {
        await stopVoskRec();
        if (meterStop) meterStop();
        setListening(false);
      } finally {
        micBtn.disabled = false;
      }
      return;
    }
    rawText.textContent = '';
    rawText.classList.remove('muted');
    dbg('Bouton micro cliqué — démarrage', 'ev');

    micBtn.disabled = true;
    setListening(true);
    try {
      if (!await startMicMeter()) {
        setListening(false);
        return;
      }
      if (!await startVoskRec()) {
        setListening(false);
        if (meterStop) meterStop();
        return;
      }
    } finally {
      micBtn.disabled = false;
    }
  });

  async function polish(text) {
    polishedText.textContent = '✨ Clarification en cours…';
    polishedText.classList.add('muted');
    changesRow.style.display = 'none';
    polishedPanel.classList.add('is-active');

    try {
      const r = await fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text }),
      });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'unknown');
      applyPolished(j.polished, j.changes || []);
      addToHistory(j.polished, j.timestamp);
      // Speak it aloud automatically
      speak(j.polished);
    } catch (e) {
      polishedText.textContent = "Impossible de clarifier la phrase.";
      polishedText.classList.add('muted');
    }
  }

  function applyPolished(text, changes) {
    lastPolished = text;
    polishedText.textContent = text;
    polishedText.classList.remove('muted');
    repeatBtn.disabled = false;
    copyBtn.disabled = false;
    rePolishBtn.disabled = !lastRaw;
    if (createGoalBtn) createGoalBtn.disabled = false;

    if (changes.length) {
      changesRow.innerHTML = changes.map(c => '<span class="chip">✓ ' + escapeHtml(c) + '</span>').join('');
      changesRow.style.display = 'flex';
    }
  }

  function addToHistory(text, time) {
    if (historyEmpty) historyEmpty.remove();
    const item = document.createElement('div');
    item.className = 'history-item';
    item.innerHTML =
      '<span class="time">' + escapeHtml(time || '') + '</span>' +
      '<div class="text">' + escapeHtml(text) + '</div>' +
      '<button class="ico-btn" type="button" title="Réécouter">🔊</button>';
    item.querySelector('.ico-btn').addEventListener('click', () => speak(text));
    historyList.insertBefore(item, historyList.firstChild);
    // Cap at 10
    while (historyList.children.length > 10) {
      historyList.removeChild(historyList.lastChild);
    }
  }

  function speak(text) {
    if (!synth || !text) return;
    synth.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'fr-FR';
    u.rate = 0.96;
    u.pitch = 1.0;
    // Try to pick a French voice
    const voices = synth.getVoices();
    const fr = voices.find(v => /fr/i.test(v.lang));
    if (fr) u.voice = fr;
    synth.speak(u);
  }

  // Some browsers load voices asynchronously; force a refresh.
  if (synth) synth.onvoiceschanged = () => {};

  repeatBtn.addEventListener('click', () => speak(lastPolished));

  if (createGoalBtn) {
    createGoalBtn.addEventListener('click', () => {
      if (!lastPolished) return;
      const url = 'service-accompagnement.php?prefill_description=' + encodeURIComponent(lastPolished);
      // Open in a new tab so the citizen keeps Echo Me available alongside the form.
      window.open(url, '_blank', 'noopener');
    });
  }

  rePolishBtn.addEventListener('click', () => {
    if (lastRaw) polish(lastRaw);
  });

  copyBtn.addEventListener('click', async () => {
    if (!lastPolished) return;
    try {
      await navigator.clipboard.writeText(lastPolished);
      copyBtn.textContent = '✓ Copié';
      setTimeout(() => { copyBtn.textContent = '📋 Copier'; }, 1500);
    } catch (e) {
      showError("Impossible de copier (autorisez le presse-papier).");
    }
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }
})();
</script>

</body>
</html>
