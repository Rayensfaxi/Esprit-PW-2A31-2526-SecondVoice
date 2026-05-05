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

if (isset($_GET['deleted'])) {
    if ($_GET['deleted'] === '1') {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Demande supprimée avec succès."];
    } else {
        $_SESSION['flash'] = ['type' => 'error',   'message' => "Suppression impossible (demande déjà prise en charge)."];
    }
    header('Location: mes-accompagnements.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_goal') {
    $goal_id = (int)$_POST['goal_id'];
    if ($goalCtrl->deleteGoal($goal_id, $user_id)) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Demande supprimée avec succès."];
    } else {
        $_SESSION['flash'] = ['type' => 'error',   'message' => "Impossible de supprimer (déjà prise en charge)."];
    }
    header('Location: mes-accompagnements.php');
    exit;
}

// Advanced search filters (GET) — separate prefixes for goals (g_) and guides (q_)
$goalFilters = [
    'user_id'          => $user_id,
    'keyword'          => trim((string)($_GET['g_keyword'] ?? '')),
    'type'             => $_GET['g_type'] ?? '',
    'status'           => $_GET['g_status'] ?? '',
    'admin_status'     => $_GET['g_admin'] ?? '',
    'assistant_status' => $_GET['g_assistant'] ?? '',
    'sort'             => $_GET['g_sort'] ?? 'created_desc',
];
$guideFilters = [
    'user_id'     => $user_id,
    'keyword'     => trim((string)($_GET['q_keyword'] ?? '')),
    'goal_type'   => $_GET['q_type'] ?? '',
    'goal_status' => $_GET['q_status'] ?? '',
    'sort'        => $_GET['q_sort'] ?? 'created_desc',
];
$goalsHasFilter = !empty($goalFilters['keyword']) || !empty($goalFilters['type']) || !empty($goalFilters['status'])
    || !empty($goalFilters['admin_status']) || !empty($goalFilters['assistant_status'])
    || ($goalFilters['sort'] !== 'created_desc');
$guidesHasFilter = !empty($guideFilters['keyword']) || !empty($guideFilters['goal_type']) || !empty($guideFilters['goal_status'])
    || ($guideFilters['sort'] !== 'created_desc');

try {
    $my_goals  = $goalCtrl->searchGoals($goalFilters);
    $my_guides = $guideCtrl->searchGuides($guideFilters);
} catch (Exception $e) {
    $my_goals = $my_guides = [];
}

// Absolute base URL for the QR target. signal-besoin.php sits next to this file.
$shareScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$shareHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$shareDir    = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');

// Replace loopback hosts with the laptop's LAN IP so a phone on the same WiFi
// can reach Apache.
$hostNoPort = preg_replace('/:\d+$/', '', $shareHost);
if (in_array(strtolower($hostNoPort), ['localhost', '127.0.0.1', '::1'], true)) {
    $lanIp = null;
    $sock = @stream_socket_client('udp://8.8.8.8:53', $eno, $estr, 1);
    if ($sock) {
        $name = @stream_socket_get_name($sock, false);
        @fclose($sock);
        if ($name && preg_match('/^(.+):\d+$/', $name, $m)) {
            $candidate = $m[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, ['127.0.0.1', '0.0.0.0'], true)) {
                $lanIp = $candidate;
            }
        }
    }
    if (!$lanIp) {
        $candidate = @gethostbyname(@gethostname());
        if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, ['127.0.0.1', '0.0.0.0'], true)) {
            $lanIp = $candidate;
        }
    }
    if ($lanIp) {
        $port = '';
        if (preg_match('/(:\d+)$/', $shareHost, $m)) { $port = $m[1]; }
        $shareHost = $lanIp . $port;
    }
}
$shareBase = $shareScheme . '://' . $shareHost . $shareDir . '/signal-besoin.php?token=';

// Optional override for hostile networks (phone hotspots with AP isolation,
// closed firewalls, etc.). If qr_tunnel.txt exists at the project root and
// holds an http(s) URL, the QR points through that tunnel instead.
$qrTunnelFile = __DIR__ . '/../../qr_tunnel.txt';
if (is_readable($qrTunnelFile)) {
    $override = trim((string) @file_get_contents($qrTunnelFile));
    if ($override !== '' && preg_match('#^https?://[^\s]+#i', $override)) {
        $parts = parse_url($override);
        if ($parts && !empty($parts['scheme']) && !empty($parts['host'])) {
            $rebuilt = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) { $rebuilt .= ':' . $parts['port']; }
            if (!empty($parts['path'])) {
                $segments = explode('/', $parts['path']);
                $encoded  = array_map(function ($s) {
                    if ($s === '') return '';
                    if (preg_match('/%[0-9A-Fa-f]{2}/', $s)) return $s;
                    return rawurlencode($s);
                }, $segments);
                $rebuilt .= implode('/', $encoded);
            }
            $shareBase = rtrim($rebuilt, '/') . '/view/frontoffice/signal-besoin.php?token=';
        }
    }
}

// Display map for the citizen's last urgency signal selected from the QR page.
$urgencyMap = [
    'simple'     => ['label' => '🟢 Simple',         'class' => 'urgency-simple'],
    'assistance' => ['label' => "🟡 Besoin d'aide",  'class' => 'urgency-assistance'],
    'urgent'     => ['label' => '🔴 Urgent',          'class' => 'urgency-urgent'],
];

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

    /* ─── 3-SECTION TOOLBAR (Search / Filters / Sort) ─── */
    .tool-form { display: flex; flex-direction: column; gap: 12px; margin-bottom: 22px; }

    /* Section heading */
    .tool-heading {
      display: flex; align-items: center; gap: 8px;
      font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
      color: var(--muted); margin-bottom: 10px;
    }

    /* Section panel */
    .tool-panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px 18px;
      box-shadow: var(--shadow);
    }

    /* SEARCH section: keyword + button */
    .tool-search-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .tool-search-row input[type="text"] {
      flex: 1; min-width: 220px;
      padding: 10px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit; font-size: .9rem;
      background: var(--surface); color: var(--text);
      outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .tool-search-row input[type="text"]:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(80,70,229,.1); }

    /* FILTERS section (collapsible) */
    .tool-filters summary {
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      cursor: pointer; list-style: none; user-select: none;
      font-weight: 700; color: var(--text);
    }
    .tool-filters summary::-webkit-details-marker { display: none; }
    .tool-filters[open] summary { margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px dashed var(--border); }
    .tool-filters-grid {
      display: grid; gap: 12px;
      grid-template-columns: repeat(4, 1fr);
    }
    @media (max-width: 720px) { .tool-filters-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .tool-filters-grid { grid-template-columns: 1fr; } }
    .tool-filters-grid .span-2 { grid-column: span 2; }
    @media (max-width: 480px) { .tool-filters-grid .span-2 { grid-column: span 1; } }
    .tool-field { display: flex; flex-direction: column; gap: 4px; }
    .tool-field label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
    .tool-field input, .tool-field select {
      padding: 9px 12px;
      border: 1.5px solid var(--border);
      border-radius: 9px;
      font-family: inherit; font-size: .875rem;
      background: var(--surface); color: var(--text);
      outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .tool-field input:focus, .tool-field select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(80,70,229,.1); }
    .tool-filters-actions {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border);
    }
    .tool-result-count { margin-left: auto; font-size: .82rem; color: var(--muted); font-weight: 600; }

    /* SORT section: label + select inline */
    .tool-sort-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .tool-sort-row label { font-weight: 700; color: var(--text); font-size: .9rem; }
    .tool-sort-row select {
      flex: 1; min-width: 220px;
      padding: 9px 12px;
      border: 1.5px solid var(--border);
      border-radius: 9px;
      font-family: inherit; font-size: .9rem;
      background: var(--surface); color: var(--text);
      outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .tool-sort-row select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(80,70,229,.1); }

    .active-filter-chip {
      display: inline-flex; align-items: center; gap: 4px;
      background: var(--accent-soft); color: var(--accent);
      padding: 3px 10px; border-radius: 100px;
      font-size: .73rem; font-weight: 700; margin-left: 6px;
    }

    /* ─── URGENCY SIGNAL BADGE ─── */
    .urgency-tag {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 10px;
      border-radius: 100px;
      font-size: .75rem;
      font-weight: 700;
      border: 1px solid transparent;
    }
    .urgency-simple     { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .urgency-assistance { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .urgency-urgent     { background: #fef2f2; color: #991b1b; border-color: #fecaca;
                          animation: urgentPulse 1.6s ease-in-out infinite; }
    @keyframes urgentPulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, .35); }
      50%      { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    }

    /* ─── QR BUTTON + MODAL ─── */
    .qr-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px;
      font-family: inherit;
      font-size: .8rem;
      font-weight: 700;
      background: var(--accent-soft);
      color: var(--accent);
      border: 1px solid #d6d2ff;
      border-radius: 8px;
      cursor: pointer;
      transition: background .15s, transform .15s;
    }
    .qr-btn:hover { background: #e0dcff; transform: translateY(-1px); }

    .qr-modal-backdrop {
      position: fixed; inset: 0;
      background: rgba(15, 22, 41, .55);
      backdrop-filter: blur(4px);
      z-index: 250;
      display: none;
      align-items: center; justify-content: center;
      padding: 16px;
    }
    .qr-modal-backdrop.open { display: flex; animation: qrFade .2s ease; }
    @keyframes qrFade { from { opacity: 0; } to { opacity: 1; } }
    .qr-modal {
      background: var(--surface);
      border-radius: 20px;
      padding: 28px 24px 22px;
      max-width: 380px;
      width: 100%;
      text-align: center;
      box-shadow: 0 24px 60px rgba(0,0,0,.25);
      position: relative;
      animation: qrPop .25s cubic-bezier(.2,.9,.3,1.05);
    }
    @keyframes qrPop {
      from { opacity: 0; transform: scale(.92) translateY(8px); }
      to { opacity: 1; transform: none; }
    }
    .qr-close {
      position: absolute; top: 12px; right: 14px;
      width: 32px; height: 32px;
      border-radius: 50%;
      border: none;
      background: var(--surface2);
      color: var(--muted);
      font-size: 1.3rem;
      line-height: 1;
      cursor: pointer;
      transition: background .15s, color .15s;
    }
    .qr-close:hover { background: var(--danger-soft); color: var(--danger); }
    .qr-icon { font-size: 2rem; margin-bottom: 6px; }
    .qr-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; }
    .qr-goal { font-size: .85rem; color: var(--muted); margin-bottom: 14px; font-weight: 600; }
    .qr-image-wrap {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px;
      margin: 0 auto 14px;
      width: fit-content;
    }
    .qr-image { display: block; width: 220px; height: 220px; image-rendering: pixelated; }
    .qr-text { font-size: .85rem; color: var(--muted); line-height: 1.5; margin-bottom: 14px; }
    .qr-url-row { display: flex; gap: 8px; margin-bottom: 10px; }
    .qr-url {
      flex: 1;
      padding: 9px 12px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
      font-size: .76rem;
      color: var(--muted);
      background: var(--surface2);
      outline: none;
      min-width: 0;
    }
    .qr-copy {
      padding: 9px 14px;
      border: none;
      border-radius: 10px;
      background: var(--accent);
      color: #fff;
      font-family: inherit;
      font-size: .8rem;
      font-weight: 700;
      cursor: pointer;
      flex-shrink: 0;
    }
    .qr-copy:hover { filter: brightness(1.08); }
    .qr-copy.copied { background: var(--success); }
    .qr-hint { font-size: .72rem; color: var(--muted); line-height: 1.4; }
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
    <a href="copilote.php" class="nav-link">💬 ChatBot</a>
    <a href="echome.php" class="nav-link">🎙️ Echo Me</a>
    <a href="profile.php" class="nav-link">Mon profil</a>
  </nav>
</header>

<div class="page-wrapper">

  <div class="page-header">
    <div>
      <h1 class="page-title">📋 Mes accompagnements</h1>
      <p class="page-subtitle">Suivez l'état de vos demandes et consultez les guides de vos assistants.</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="export-mes-accompagnements.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" rel="noopener" class="btn btn-ghost">📄 Export PDF</a>
      <a href="service-accompagnement.php" class="btn btn-primary">+ Nouvelle demande</a>
    </div>
  </div>

  <?php require __DIR__ . '/../partials/flash.php'; ?>

  <?php
  // Pie chart: my goals by status
  $userPieCounts = ['soumis' => 0, 'en_cours' => 0, 'termine' => 0, 'annule' => 0];
  foreach ($my_goals as $__g) {
      $__s = $__g['status'] ?? 'soumis';
      if (isset($userPieCounts[$__s])) { $userPieCounts[$__s]++; }
  }
  $pieTitle = '📊 Mes demandes par statut';
  $pieTone  = 'light';
  $pieData  = [
      ['label' => '🕐 Soumis',   'value' => $userPieCounts['soumis'],   'color' => '#f59e0b'],
      ['label' => '⚡ En cours', 'value' => $userPieCounts['en_cours'], 'color' => '#3b82f6'],
      ['label' => '✅ Terminé',  'value' => $userPieCounts['termine'],  'color' => '#10b981'],
      ['label' => '❌ Annulé',   'value' => $userPieCounts['annule'],   'color' => '#ef4444'],
  ];
  require __DIR__ . '/../partials/pie_chart.php';
  ?>

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

    <!-- TOOLBAR (GOALS) — 3 sections: Recherche / Filtres / Tri -->
    <form method="GET" class="tool-form">
      <input type="hidden" name="tab" value="goals">

      <!-- 1) RECHERCHE -->
      <div class="tool-panel">
        <div class="tool-heading">🔍 Recherche</div>
        <div class="tool-search-row">
          <input type="text" id="g_keyword" name="g_keyword" value="<?= htmlspecialchars($goalFilters['keyword']) ?>" placeholder="Mot-clé (titre, description)…">
          <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
        </div>
      </div>

      <!-- 2) FILTRES -->
      <details class="tool-panel tool-filters" <?= $goalsHasFilter ? 'open' : '' ?>>
        <summary>
          <span>🎛 Filtres avancés
            <?php if ($goalsHasFilter): ?>
              <span class="active-filter-chip">actifs</span>
            <?php endif; ?>
          </span>
          <span class="active-filter-chip">Afficher / masquer</span>
        </summary>

        <div class="tool-filters-grid">
          <div class="tool-field">
            <label for="g_type">Type de service</label>
            <select id="g_type" name="g_type">
              <option value="">— Tous —</option>
              <?php foreach ($typeLabels as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= $goalFilters['type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="tool-field">
            <label for="g_status">Statut</label>
            <select id="g_status" name="g_status">
              <option value="">— Tous —</option>
              <?php foreach ($statusMap as $val => $info): ?>
                <option value="<?= $val ?>" <?= $goalFilters['status'] === $val ? 'selected' : '' ?>><?= $info['icon'] ?> <?= $info['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="tool-field">
            <label for="g_admin">Validation Admin</label>
            <select id="g_admin" name="g_admin">
              <option value="">— Toutes —</option>
              <?php foreach ($adminMap as $val => $info): ?>
                <option value="<?= $val ?>" <?= $goalFilters['admin_status'] === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="tool-field">
            <label for="g_assistant">Validation Assistant</label>
            <select id="g_assistant" name="g_assistant">
              <option value="">— Toutes —</option>
              <?php foreach ($assistantMap as $val => $info): ?>
                <option value="<?= $val ?>" <?= $goalFilters['assistant_status'] === $val ? 'selected' : '' ?>><?= $info['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>

        </div>

        <div class="tool-filters-actions">
          <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
          <a href="mes-accompagnements.php" class="btn btn-ghost btn-sm">↺ Réinitialiser</a>
          <span class="tool-result-count"><?= count($my_goals) ?> résultat<?= count($my_goals) > 1 ? 's' : '' ?></span>
        </div>
      </details>

      <!-- 3) TRI -->
      <div class="tool-panel">
        <div class="tool-heading">↕ Tri</div>
        <div class="tool-sort-row">
          <label for="g_sort">Trier par :</label>
          <select id="g_sort" name="g_sort" onchange="this.form.submit()">
            <option value="created_desc" <?= $goalFilters['sort'] === 'created_desc' ? 'selected' : '' ?>>📅 Plus récents</option>
            <option value="created_asc"  <?= $goalFilters['sort'] === 'created_asc'  ? 'selected' : '' ?>>📅 Plus anciens</option>
            <option value="title_asc"    <?= $goalFilters['sort'] === 'title_asc'    ? 'selected' : '' ?>>🔤 Titre (A→Z)</option>
            <option value="title_desc"   <?= $goalFilters['sort'] === 'title_desc'   ? 'selected' : '' ?>>🔤 Titre (Z→A)</option>
          </select>
        </div>
      </div>
    </form>

    <?php if (empty($my_goals)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <div class="empty-title"><?= $goalsHasFilter ? 'Aucun résultat' : 'Aucune demande pour le moment' ?></div>
        <div class="empty-sub"><?= $goalsHasFilter ? 'Aucune demande ne correspond à vos critères. Modifiez ou réinitialisez la recherche.' : 'Soumettez votre première demande d\'accompagnement professionnel.' ?></div>
        <?php if (!$goalsHasFilter): ?>
          <a href="service-accompagnement.php" class="btn btn-primary">🚀 Créer une demande</a>
        <?php endif; ?>
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
        // QR signal-besoin URL (lazy-create token; null if column missing)
        $shareToken = $goalCtrl->ensureShareToken((int)$g['id'], $user_id);
        $shareUrl   = $shareToken ? ($shareBase . $shareToken) : null;
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
          <?php if (!empty($g['urgency_level']) && isset($urgencyMap[$g['urgency_level']])):
            $uData = $urgencyMap[$g['urgency_level']];
          ?>
            <span class="urgency-tag <?= $uData['class'] ?>"
                  title="Signal envoyé via QR le <?= !empty($g['urgency_updated_at']) ? htmlspecialchars(date('d/m/Y à H:i', strtotime($g['urgency_updated_at']))) : '' ?>">
              📡 <?= $uData['label'] ?>
            </span>
          <?php endif; ?>
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
            <form method="POST" style="display:inline;"
                  data-confirm="Cette demande sera définitivement supprimée. Vous ne pouvez supprimer qu'une demande encore en attente de validation."
                  data-confirm-type="danger"
                  data-confirm-title="Supprimer cette demande ?"
                  data-confirm-action="Oui, supprimer"
                  data-confirm-icon="🗑️">
              <input type="hidden" name="action"  value="delete_goal">
              <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
              <button type="submit" class="btn btn-danger-ghost btn-sm">🗑 Supprimer</button>
            </form>
          <?php else: ?>
            <span class="tag tag-neutral" style="font-size:.78rem;">🔒 Modification non disponible</span>
          <?php endif; ?>

          <?php if (!empty($goalGuides)): ?>
            <button class="guides-toggle" onclick="toggleGuides(<?= $g['id'] ?>, this)">
              📖 Voir les guides (<?= count($goalGuides) ?>)
            </button>
          <?php endif; ?>

          <?php if ($shareUrl): ?>
            <button type="button" class="qr-btn"
                    onclick="openQR(this)"
                    data-share-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES) ?>"
                    data-share-title="<?= htmlspecialchars($g['title'], ENT_QUOTES) ?>">
              📡 QR — Signaler mon besoin
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

    <!-- TOOLBAR (GUIDES) — 3 sections: Recherche / Filtres / Tri -->
    <form method="GET" class="tool-form">
      <input type="hidden" name="tab" value="guides">

      <!-- 1) RECHERCHE -->
      <div class="tool-panel">
        <div class="tool-heading">🔍 Recherche</div>
        <div class="tool-search-row">
          <input type="text" id="q_keyword" name="q_keyword" value="<?= htmlspecialchars($guideFilters['keyword']) ?>" placeholder="Mot-clé (titre, contenu)…">
          <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
        </div>
      </div>

      <!-- 2) FILTRES -->
      <details class="tool-panel tool-filters" <?= $guidesHasFilter ? 'open' : '' ?>>
        <summary>
          <span>🎛 Filtres avancés
            <?php if ($guidesHasFilter): ?>
              <span class="active-filter-chip">actifs</span>
            <?php endif; ?>
          </span>
          <span class="active-filter-chip">Afficher / masquer</span>
        </summary>

        <div class="tool-filters-grid">
          <div class="tool-field">
            <label for="q_type">Type d'accompagnement</label>
            <select id="q_type" name="q_type">
              <option value="">— Tous —</option>
              <?php foreach ($typeLabels as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= $guideFilters['goal_type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="tool-field">
            <label for="q_status">Statut accompagnement</label>
            <select id="q_status" name="q_status">
              <option value="">— Tous —</option>
              <?php foreach ($statusMap as $val => $info): ?>
                <option value="<?= $val ?>" <?= $guideFilters['goal_status'] === $val ? 'selected' : '' ?>><?= $info['icon'] ?> <?= $info['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>

        </div>

        <div class="tool-filters-actions">
          <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
          <a href="mes-accompagnements.php?tab=guides" class="btn btn-ghost btn-sm">↺ Réinitialiser</a>
          <span class="tool-result-count"><?= count($my_guides) ?> résultat<?= count($my_guides) > 1 ? 's' : '' ?></span>
        </div>
      </details>

      <!-- 3) TRI -->
      <div class="tool-panel">
        <div class="tool-heading">↕ Tri</div>
        <div class="tool-sort-row">
          <label for="q_sort">Trier par :</label>
          <select id="q_sort" name="q_sort" onchange="this.form.submit()">
            <option value="created_desc" <?= $guideFilters['sort'] === 'created_desc' ? 'selected' : '' ?>>📅 Plus récents</option>
            <option value="created_asc"  <?= $guideFilters['sort'] === 'created_asc'  ? 'selected' : '' ?>>📅 Plus anciens</option>
            <option value="title_asc"    <?= $guideFilters['sort'] === 'title_asc'    ? 'selected' : '' ?>>🔤 Titre (A→Z)</option>
            <option value="title_desc"   <?= $guideFilters['sort'] === 'title_desc'   ? 'selected' : '' ?>>🔤 Titre (Z→A)</option>
          </select>
        </div>
      </div>
    </form>

    <?php if (empty($my_guides)): ?>
      <div class="empty-state">
        <div class="empty-icon">📚</div>
        <div class="empty-title"><?= $guidesHasFilter ? 'Aucun résultat' : 'Aucun guide disponible' ?></div>
        <div class="empty-sub"><?= $guidesHasFilter ? 'Aucun guide ne correspond à vos critères.' : 'Vos assistants créeront des guides une fois vos demandes acceptées.' ?></div>
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

// Honor ?tab=guides|goals so a filtered guide search lands on the guides tab
(function () {
  const params = new URLSearchParams(location.search);
  const want = params.get('tab');
  if (want === 'guides' || want === 'goals') {
    const targetId = 'tab-' + want;
    const btn = document.querySelector('.tab-btn[data-tab="' + targetId + '"]');
    if (btn) switchTab(btn);
  }
})();
</script>

<!-- QR signal-besoin modal -->
<div class="qr-modal-backdrop" id="qrModal" onclick="closeQR(event)">
  <div class="qr-modal" onclick="event.stopPropagation()">
    <button type="button" class="qr-close" onclick="closeQR()" aria-label="Fermer">×</button>
    <div class="qr-icon">📡</div>
    <div class="qr-title">Signaler mon besoin</div>
    <div class="qr-goal" id="qrGoalTitle"></div>
    <div class="qr-image-wrap">
      <img id="qrImage" class="qr-image" alt="QR code Signal besoin" />
    </div>
    <div class="qr-text">
      Scannez ce QR avec votre téléphone et choisissez la couleur qui décrit votre besoin&nbsp;:<br>
      🟢&nbsp;<strong>Simple</strong> &middot;
      🟡&nbsp;<strong>Besoin d'aide</strong> &middot;
      🔴&nbsp;<strong>Urgent</strong>.<br>
      Votre assistant verra le signal sur sa fiche.
    </div>
    <div class="qr-url-row">
      <input type="text" class="qr-url" id="qrUrl" readonly />
      <button type="button" class="qr-copy" id="qrCopy" onclick="copyQRUrl()">Copier</button>
    </div>
    <div class="qr-hint">🔒 Lien personnel — gardez-le pour vous.</div>
  </div>
</div>

<script>
function openQR(btn) {
  var url   = btn.getAttribute('data-share-url')   || '';
  var title = btn.getAttribute('data-share-title') || '';
  document.getElementById('qrUrl').value = url;
  document.getElementById('qrGoalTitle').textContent = title;
  document.getElementById('qrImage').src =
    'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' + encodeURIComponent(url);
  var copy = document.getElementById('qrCopy');
  copy.classList.remove('copied');
  copy.textContent = 'Copier';
  document.getElementById('qrModal').classList.add('open');
}
function closeQR(e) {
  if (!e || e.target.id === 'qrModal' || (e.target.classList && e.target.classList.contains('qr-close'))) {
    document.getElementById('qrModal').classList.remove('open');
  }
}
function copyQRUrl() {
  var inp = document.getElementById('qrUrl');
  inp.select();
  inp.setSelectionRange(0, 99999);
  var ok = false;
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(inp.value);
      ok = true;
    } else {
      ok = document.execCommand('copy');
    }
  } catch (e) { ok = false; }
  var btn = document.getElementById('qrCopy');
  btn.classList.toggle('copied', !!ok);
  btn.textContent = ok ? '✓ Copié' : 'Erreur';
  setTimeout(function () { btn.classList.remove('copied'); btn.textContent = 'Copier'; }, 2200);
}
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeQR();
});
</script>

<?php require __DIR__ . '/../partials/confirm-modal.php'; ?>
<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
