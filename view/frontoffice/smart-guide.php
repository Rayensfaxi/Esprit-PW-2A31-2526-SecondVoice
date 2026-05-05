<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// === AJAX endpoint: pick the best assistant for the chosen type ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $body['action'] ?? '';

    if ($action === 'recommend_assistant') {
        $type = trim((string)($body['type'] ?? ''));
        $ctrl = new GoalController();
        $assistant = $ctrl->getBestAssistantForType($type);
        echo json_encode([
            'ok'        => (bool) $assistant,
            'assistant' => $assistant,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#5046e5" />
  <title>SmartGuide — Trouve ton accompagnement | SecondVoice</title>
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
      --accent-hover: #3d34d1;
      --accent-soft: #eeeeff;
      --shadow: 0 4px 24px rgba(80,70,229,.08);
      --shadow-lg: 0 16px 48px rgba(80,70,229,.18);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background:
        radial-gradient(1200px 600px at 50% -10%, rgba(80,70,229,.18), transparent 60%),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
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
      font-size: .85rem; font-weight: 600;
      color: var(--muted);
      text-decoration: none;
      transition: all .15s;
    }
    .header-nav a:hover { color: var(--text); background: var(--surface2); }
    .header-nav a.active { color: var(--accent); background: var(--accent-soft); }

    .wrap { max-width: 720px; margin: 0 auto; padding: 28px 18px 80px; }

    .hero { text-align: center; margin-bottom: 22px; }
    .tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--accent-soft);
      color: var(--accent);
      font-size: .72rem; font-weight: 800; letter-spacing: .08em;
      text-transform: uppercase;
      padding: 5px 12px;
      border-radius: 100px;
      margin-bottom: 12px;
    }
    h1 {
      font-size: 1.9rem; font-weight: 800; line-height: 1.15;
      background: linear-gradient(135deg, #0f1629, #5046e5);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 6px;
    }
    .lead { color: var(--muted); font-size: .95rem; line-height: 1.55; }

    /* CHAT SHELL */
    .chat-shell {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 22px;
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      max-height: 80vh;
    }
    .chat-header {
      display: flex; align-items: center; gap: 12px;
      padding: 16px 20px;
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
    }
    .bot-avatar {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: rgba(255,255,255,.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem;
      box-shadow: 0 4px 12px rgba(0,0,0,.18);
    }
    .bot-name { font-weight: 800; font-size: 1rem; }
    .bot-subtitle { font-size: .75rem; opacity: .85; margin-top: 2px; }
    .restart-btn {
      margin-left: auto;
      padding: 6px 12px;
      font-size: .76rem;
      font-weight: 700;
      background: rgba(255,255,255,.15);
      color: #fff;
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 8px;
      cursor: pointer;
      font-family: inherit;
    }
    .restart-btn:hover { background: rgba(255,255,255,.22); }

    .chat-body {
      flex: 1;
      overflow-y: auto;
      padding: 22px 20px 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      background: var(--surface2);
    }

    /* BUBBLES */
    .bubble {
      max-width: 82%;
      padding: 11px 16px;
      border-radius: 18px;
      font-size: .94rem;
      line-height: 1.5;
      word-wrap: break-word;
      animation: bubbleIn .35s cubic-bezier(.2,.9,.3,1.05);
    }
    @keyframes bubbleIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: none; }
    }
    .bubble.bot {
      align-self: flex-start;
      background: var(--surface);
      color: var(--text);
      border: 1px solid var(--border);
      border-bottom-left-radius: 6px;
      box-shadow: 0 2px 8px rgba(15,22,41,.04);
    }
    .bubble.user {
      align-self: flex-end;
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      border-bottom-right-radius: 6px;
      box-shadow: 0 4px 12px rgba(80,70,229,.32);
    }
    .typing {
      align-self: flex-start;
      display: inline-flex;
      gap: 4px;
      padding: 14px 18px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      border-bottom-left-radius: 6px;
    }
    .typing span {
      width: 7px; height: 7px;
      background: var(--accent);
      border-radius: 50%;
      opacity: .5;
      animation: typingDot 1.1s ease-in-out infinite;
    }
    .typing span:nth-child(2) { animation-delay: .15s; }
    .typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes typingDot {
      0%, 100% { transform: translateY(0); opacity: .35; }
      50%      { transform: translateY(-4px); opacity: 1; }
    }

    /* QUICK REPLIES */
    .chat-actions {
      padding: 12px 16px 16px;
      background: var(--surface);
      border-top: 1px solid var(--border);
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .qr-btn-quick {
      padding: 10px 16px;
      font-family: inherit;
      font-size: .88rem;
      font-weight: 700;
      background: var(--accent-soft);
      color: var(--accent);
      border: 1.5px solid #d6d2ff;
      border-radius: 12px;
      cursor: pointer;
      transition: all .15s;
      flex-shrink: 0;
    }
    .qr-btn-quick:hover {
      background: var(--accent);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(80,70,229,.32);
    }
    .qr-btn-quick:disabled { opacity: .4; cursor: not-allowed; transform: none; }

    /* FREE TEXT INPUT (for Q5) */
    .chat-input {
      padding: 14px 16px 18px;
      background: var(--surface);
      border-top: 1px solid var(--border);
    }
    .chat-input textarea {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      font-family: inherit;
      font-size: .95rem;
      color: var(--text);
      background: var(--surface2);
      resize: vertical;
      min-height: 60px;
      outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .chat-input textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(80,70,229,.12);
    }
    .input-row {
      display: flex;
      gap: 8px;
      margin-top: 10px;
      flex-wrap: wrap;
    }
    .btn-send {
      padding: 10px 22px;
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-family: inherit;
      font-size: .9rem;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 16px rgba(80,70,229,.32);
      transition: filter .15s, transform .12s;
    }
    .btn-send:hover { filter: brightness(1.08); transform: translateY(-1px); }

    /* RECOMMENDATION CARD */
    .reco-card {
      align-self: stretch;
      background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);
      border: 2px solid #d6d2ff;
      border-radius: 20px;
      padding: 22px 22px 18px;
      box-shadow: var(--shadow-lg);
      animation: bubbleIn .45s cubic-bezier(.2,.9,.3,1.05);
    }
    .reco-title {
      display: flex; align-items: center; gap: 8px;
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--accent);
      margin-bottom: 14px;
    }
    .reco-row {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 12px 0;
      border-bottom: 1px dashed var(--border);
    }
    .reco-row:last-of-type { border-bottom: none; }
    .reco-icon {
      flex-shrink: 0;
      width: 36px; height: 36px;
      border-radius: 10px;
      background: var(--accent-soft);
      color: var(--accent);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      font-weight: 800;
    }
    .reco-content { flex: 1; min-width: 0; }
    .reco-label { font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
    .reco-value { font-size: 1rem; font-weight: 700; color: var(--text); margin-top: 2px; }
    .reco-why   { font-size: .82rem; color: var(--muted); margin-top: 4px; line-height: 1.45; }
    .reco-actions {
      display: flex; gap: 10px;
      margin-top: 16px;
      flex-wrap: wrap;
    }
    .reco-btn {
      flex: 1;
      min-width: 120px;
      padding: 12px 20px;
      font-family: inherit;
      font-size: .9rem;
      font-weight: 800;
      border-radius: 12px;
      cursor: pointer;
      text-decoration: none;
      text-align: center;
      transition: filter .15s, transform .12s;
      border: none;
    }
    .reco-btn-primary {
      background: linear-gradient(135deg, #5046e5, #3d34d1);
      color: #fff;
      box-shadow: 0 8px 22px rgba(80,70,229,.32);
    }
    .reco-btn-primary:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .reco-btn-ghost {
      background: var(--surface);
      color: var(--text);
      border: 1.5px solid var(--border);
    }
    .reco-btn-ghost:hover { background: var(--surface2); }

    @media (max-width: 540px) {
      .header-nav { display: none; }
      h1 { font-size: 1.55rem; }
      .bubble { max-width: 92%; font-size: .9rem; }
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
    <a href="smart-guide.php" class="active">🤖 SmartGuide</a>
    <a href="echome.php">🎙️ Echo Me</a>
    <a href="profile.php">Mon profil</a>
  </nav>
</header>

<main class="wrap">
  <section class="hero">
    <div class="tag">🤖 Recommandation intelligente</div>
    <h1>SmartGuide</h1>
    <p class="lead">
      Quelques questions simples pour vous proposer le <strong>type d'accompagnement</strong>,
      <strong>l'assistant</strong> et la <strong>priorité</strong> les plus adaptés à votre situation.
    </p>
  </section>

  <div class="chat-shell">
    <div class="chat-header">
      <div class="bot-avatar">🤖</div>
      <div>
        <div class="bot-name">SmartGuide</div>
        <div class="bot-subtitle">5 questions · Réponses personnalisées</div>
      </div>
      <button type="button" class="restart-btn" id="restartBtn" style="display:none;">↺ Recommencer</button>
    </div>

    <div class="chat-body" id="chatBody"></div>

    <div class="chat-actions" id="chatActions"></div>

    <div class="chat-input" id="chatInput" style="display:none;">
      <textarea id="freeText" maxlength="1000" placeholder="Décrivez en une phrase votre objectif (vous pouvez parler 🎙️)…"></textarea>
      <div class="input-row">
        <button type="button" class="vd-btn" id="vd-freeText">🎙️ Dicter</button>
        <button type="button" class="btn-send" id="sendBtn">Envoyer →</button>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../partials/voice-dictate.php'; ?>

<script>
(function () {
  // ─── Decision tree ───
  // Each node: { bot: string|string[], choices?: [{label, value, next}], input?: 'text', next? }
  const NODES = {
    start: {
      bot: ["Bonjour ! 👋 Je suis SmartGuide.",
            "En quelques questions, je vais vous recommander l'accompagnement parfait pour votre situation."],
      choices: [{ label: "✨ C'est parti !", value: 'start', next: 'q1' }]
    },
    q1: {
      bot: "Quel est votre **objectif principal** en ce moment ?",
      choices: [
        { label: "🔍 Trouver un nouvel emploi",      value: 'job_search',     next: 'q2' },
        { label: "📈 Améliorer ma présence pro",     value: 'improve_profile', next: 'q2' },
        { label: "🎤 Réussir un entretien à venir",  value: 'interview_prep', next: 'q2' },
        { label: "🧭 Je ne sais pas encore",         value: 'unsure',         next: 'q2' },
      ]
    },
    q2: {
      bot: "Où en êtes-vous dans votre démarche ?",
      choices: [
        { label: "🚀 Je commence",                  value: 'starting',  next: 'q3' },
        { label: "📤 J'ai postulé, j'attends",       value: 'applied',   next: 'q3' },
        { label: "💼 Je suis en poste, je cherche", value: 'employed',  next: 'q3' },
        { label: "🔄 Je change de domaine",          value: 'changing',  next: 'q3' },
      ]
    },
    q3: {
      bot: "Sur **quel élément** voulez-vous concentrer l'aide en priorité ?",
      choices: [
        { label: "📄 Mon CV",                          value: 'cv',           next: 'q4' },
        { label: "✉️ Ma lettre de motivation",         value: 'cover_letter', next: 'q4' },
        { label: "🔗 Mon profil LinkedIn",             value: 'linkedin',     next: 'q4' },
        { label: "🎤 Mes compétences en entretien",    value: 'interview',    next: 'q4' },
        { label: "📌 Plusieurs choses ou autre",       value: 'other',        next: 'q4' },
      ]
    },
    q4: {
      bot: "Quand souhaitez-vous être aidé(e) ?",
      choices: [
        { label: "🔥 Très bientôt — c'est urgent",    value: 'haute',   next: 'q5' },
        { label: "📅 Dans 2-3 semaines",               value: 'moyenne', next: 'q5' },
        { label: "🌱 Pas urgent, je prépare",          value: 'basse',   next: 'q5' },
      ]
    },
    q5: {
      bot: "Dernière question ! 🎯 **Décrivez en une phrase votre objectif.** Vous pouvez parler 🎙️ ou écrire.",
      input: 'text',
      next: 'recommend'
    }
  };

  // Type label map (display only)
  const TYPE_LABELS = {
    cv:           { icon: '📄', label: 'Rédaction de CV' },
    cover_letter: { icon: '✉️', label: 'Lettre de motivation' },
    linkedin:     { icon: '🔗', label: 'Optimisation LinkedIn' },
    interview:    { icon: '🎤', label: 'Préparation entretien' },
    other:        { icon: '📌', label: 'Autre besoin' }
  };
  const PRIORITY_LABELS = {
    haute:   { icon: '🔴', label: 'Haute', color: '#dc2626' },
    moyenne: { icon: '🟡', label: 'Moyenne', color: '#f59e0b' },
    basse:   { icon: '🟢', label: 'Basse', color: '#10b981' }
  };

  // ─── State ───
  const state = { answers: {}, current: 'start' };

  const chatBody = document.getElementById('chatBody');
  const chatActions = document.getElementById('chatActions');
  const chatInput = document.getElementById('chatInput');
  const freeText = document.getElementById('freeText');
  const sendBtn = document.getElementById('sendBtn');
  const restartBtn = document.getElementById('restartBtn');
  const vdBtn = document.getElementById('vd-freeText');

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  // Light markdown: **bold**
  function md(s) {
    return escapeHtml(s).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }

  function scrollToBottom() {
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  function addBubble(text, who) {
    const div = document.createElement('div');
    div.className = 'bubble ' + who;
    div.innerHTML = md(text);
    chatBody.appendChild(div);
    scrollToBottom();
    return div;
  }

  function showTyping() {
    const t = document.createElement('div');
    t.className = 'typing';
    t.innerHTML = '<span></span><span></span><span></span>';
    t.id = 'typingIndicator';
    chatBody.appendChild(t);
    scrollToBottom();
    return t;
  }
  function hideTyping() {
    const t = document.getElementById('typingIndicator');
    if (t) t.remove();
  }

  function clearActions() { chatActions.innerHTML = ''; }
  function showActions(buttons) {
    clearActions();
    buttons.forEach(b => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'qr-btn-quick';
      btn.textContent = b.label;
      btn.addEventListener('click', () => onChoose(b));
      chatActions.appendChild(btn);
    });
  }
  function showFreeInput() {
    clearActions();
    chatInput.style.display = 'block';
    freeText.focus();
  }
  function hideFreeInput() {
    chatInput.style.display = 'none';
  }

  async function ask(nodeKey) {
    const node = NODES[nodeKey];
    if (!node) return;
    state.current = nodeKey;

    const lines = Array.isArray(node.bot) ? node.bot : [node.bot];
    for (let i = 0; i < lines.length; i++) {
      const t = showTyping();
      await new Promise(r => setTimeout(r, 600));
      hideTyping();
      addBubble(lines[i], 'bot');
      if (i < lines.length - 1) await new Promise(r => setTimeout(r, 250));
    }

    if (node.choices) {
      hideFreeInput();
      showActions(node.choices);
    } else if (node.input === 'text') {
      showFreeInput();
    }
  }

  function onChoose(choice) {
    addBubble(choice.label, 'user');
    state.answers[state.current] = choice.value;
    clearActions();
    if (choice.next === 'recommend') {
      finalize();
    } else {
      setTimeout(() => ask(choice.next), 300);
    }
  }

  sendBtn.addEventListener('click', () => {
    const v = (freeText.value || '').trim();
    if (!v) { freeText.focus(); return; }
    addBubble(v, 'user');
    state.answers.q5 = v;
    freeText.value = '';
    hideFreeInput();
    finalize();
  });

  // Voice dictation on Q5: append to textarea via shared widget
  if (window.VoiceDictate && vdBtn) {
    VoiceDictate.attachButton(vdBtn, 'freeText', { polishUrl: 'echome.php' });
  }

  // ─── Recommendation logic ───
  function buildWhy() {
    const a = state.answers;
    const reasons = [];
    if (a.q1 === 'job_search')      reasons.push('Vous cherchez un nouvel emploi');
    else if (a.q1 === 'improve_profile') reasons.push('Vous renforcez votre présence professionnelle');
    else if (a.q1 === 'interview_prep')  reasons.push('Vous préparez un entretien');
    else if (a.q1 === 'unsure')          reasons.push("Vous explorez différentes pistes");

    if (a.q2 === 'starting')      reasons.push('vous démarrez votre démarche');
    else if (a.q2 === 'applied')   reasons.push('vous avez déjà postulé');
    else if (a.q2 === 'employed')  reasons.push('vous évoluez en parallèle de votre poste actuel');
    else if (a.q2 === 'changing')  reasons.push('vous changez de domaine');

    return reasons.join(' et ') + '.';
  }

  async function finalize() {
    const t = showTyping();
    await new Promise(r => setTimeout(r, 700));
    hideTyping();

    const type     = state.answers.q3 || 'other';
    const priority = state.answers.q4 || 'moyenne';
    const desc     = state.answers.q5 || '';

    addBubble("Parfait, j'ai tout ce qu'il me faut. Voici ma recommandation personnalisée 🎯 :", 'bot');

    // Lookup the best assistant for this type
    let assistant = null;
    try {
      const r = await fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'recommend_assistant', type })
      });
      const j = await r.json();
      if (j.ok) assistant = j.assistant;
    } catch (e) { /* fall back */ }

    const typeInfo = TYPE_LABELS[type]     || TYPE_LABELS.other;
    const priInfo  = PRIORITY_LABELS[priority] || PRIORITY_LABELS.moyenne;
    const why      = buildWhy();

    let assistantBlock;
    if (assistant) {
      const fullName = ((assistant.prenom || '') + ' ' + (assistant.nom || '')).trim() || 'Notre assistant';
      const handled  = parseInt(assistant.handled || 0, 10);
      const finished = parseInt(assistant.finished || 0, 10);
      const reason = handled > 0
        ? handled + ' demande' + (handled > 1 ? 's' : '') + ' déjà traitée' + (handled > 1 ? 's' : '') + ' dans cette catégorie'
            + (finished > 0 ? ' · ' + finished + ' terminée' + (finished > 1 ? 's' : '') : '')
        : "Disponible et avec la file la plus légère pour démarrer rapidement";
      assistantBlock = '<div class="reco-row">' +
        '<div class="reco-icon">🎓</div>' +
        '<div class="reco-content">' +
          '<div class="reco-label">Assistant suggéré</div>' +
          '<div class="reco-value">' + escapeHtml(fullName) + '</div>' +
          '<div class="reco-why">' + escapeHtml(reason) + '</div>' +
        '</div></div>';
    } else {
      assistantBlock = '<div class="reco-row">' +
        '<div class="reco-icon">🎓</div>' +
        '<div class="reco-content">' +
          '<div class="reco-label">Assistant</div>' +
          '<div class="reco-value">À choisir manuellement</div>' +
          '<div class="reco-why">Aucun assistant n\'est encore disponible. Vous le choisirez sur le formulaire.</div>' +
        '</div></div>';
    }

    // Build the URL to the create form (only the fields the form actually accepts)
    const params = new URLSearchParams();
    params.set('prefill_type', type);
    if (assistant && assistant.id) params.set('prefill_assistant', assistant.id);
    if (desc) params.set('prefill_description', desc);
    params.set('from', 'smartguide');
    const createUrl = 'service-accompagnement.php?' + params.toString();

    const card = document.createElement('div');
    card.className = 'reco-card';
    card.innerHTML =
      '<div class="reco-title">✨ Voici ma recommandation</div>' +

      '<div class="reco-row">' +
        '<div class="reco-icon">' + typeInfo.icon + '</div>' +
        '<div class="reco-content">' +
          '<div class="reco-label">Type d\'accompagnement</div>' +
          '<div class="reco-value">' + escapeHtml(typeInfo.label) + '</div>' +
          '<div class="reco-why">' + escapeHtml(why) + '</div>' +
        '</div>' +
      '</div>' +

      assistantBlock +

      '<div class="reco-row">' +
        '<div class="reco-icon" style="background: ' + priInfo.color + '20; color: ' + priInfo.color + ';">' + priInfo.icon + '</div>' +
        '<div class="reco-content">' +
          '<div class="reco-label">Priorité suggérée</div>' +
          '<div class="reco-value">' + escapeHtml(priInfo.label) + '</div>' +
          '<div class="reco-why">' + (priority === 'haute' ? "Vous avez besoin d'aide rapidement." : (priority === 'moyenne' ? "Un délai raisonnable, sans précipitation." : "Vous avez le temps de préparer en profondeur.")) + '</div>' +
        '</div>' +
      '</div>' +

      (desc ? '<div class="reco-row">' +
        '<div class="reco-icon">📝</div>' +
        '<div class="reco-content">' +
          '<div class="reco-label">Votre objectif</div>' +
          '<div class="reco-value" style="font-weight:600; font-size:.95rem;">' + escapeHtml(desc) + '</div>' +
        '</div>' +
      '</div>' : '') +

      '<div class="reco-actions">' +
        '<button type="button" class="reco-btn reco-btn-ghost" id="recoRestart">↺ Recommencer</button>' +
        '<a href="' + escapeHtml(createUrl) + '" class="reco-btn reco-btn-primary">🚀 Créer ma demande</a>' +
      '</div>';

    chatBody.appendChild(card);
    scrollToBottom();
    restartBtn.style.display = 'inline-flex';

    document.getElementById('recoRestart').addEventListener('click', restart);
  }

  function restart() {
    state.answers = {};
    state.current = 'start';
    chatBody.innerHTML = '';
    clearActions();
    hideFreeInput();
    restartBtn.style.display = 'none';
    setTimeout(() => ask('start'), 200);
  }

  restartBtn.addEventListener('click', restart);

  // Kick off
  setTimeout(() => ask('start'), 400);
})();
</script>

<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
