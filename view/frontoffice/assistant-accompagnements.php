<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';
require_once __DIR__ . '/../../controller/GuideController.php';

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

// Check if user is agent/assistant
$_role = strtolower((string) ($_SESSION['role'] ?? ''));
$_userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
$isAssistant = in_array($_role, ['assistant', 'agent'], true) || in_array($_userRole, ['assistant', 'agent'], true);
if (!$isAssistant) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/view/frontoffice/assistant-accompagnements.php');
  $msg = urlencode('Acces reserve aux assistants.');
    header("Location: /test-login.php?next={$next}&type=error&msg={$msg}");
    exit;
}

$assistant_id = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($assistant_id <= 0) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '/view/frontoffice/assistant-accompagnements.php');
    $msg = urlencode('Session invalide. Veuillez vous reconnecter.');
    header("Location: /test-login.php?next={$next}&type=error&msg={$msg}");
    exit;
}

$goalCtrl  = new GoalController();
$guideCtrl = new GuideController();

// Fetch assistant identity for the sidebar profile card
$assistantInfo = null;
try {
    $stmt = Config::getConnexion()->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id = :id");
    $stmt->execute(['id' => $assistant_id]);
    $assistantInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$assistantFullName = $assistantInfo
    ? trim(($assistantInfo['prenom'] ?? '') . ' ' . ($assistantInfo['nom'] ?? ''))
    : 'Assistant';
if ($assistantFullName === '') { $assistantFullName = 'Assistant'; }
$assistantEmail = $assistantInfo['email'] ?? '';
$assistantInitials = strtoupper(mb_substr($assistantFullName, 0, 2));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $goal_id = (int)($_POST['goal_id'] ?? 0);

    try {
        if ($action === 'evaluate' && $goal_id) {
            $decision = $_POST['decision'] ?? '';        // 'accepte' | 'refuse'
            $comment  = trim($_POST['comment'] ?? '');
            $priority = $_POST['priority'] ?? 'moyenne';
            $status   = $_POST['status']   ?? 'en_cours';
            $ok = $goalCtrl->evaluateGoalByAssistant($goal_id, $assistant_id, $decision, $comment, $priority, $status);
            if (!$ok) {
              redirect_with_notice('assistant-accompagnements.php', 'error', "Action impossible : la demande a d�j� �t� trait�e.");
            } else {
                if ($decision === 'accepte') {
                redirect_with_notice('assistant-accompagnements.php', 'success', "Accompagnement accept�. Vous pouvez maintenant cr�er les �tapes (guides).");
                } else {
                redirect_with_notice('assistant-accompagnements.php', 'success', "Accompagnement refus� et supprim�.");
                }
            }
        }

        if ($action === 'finish_goal' && $goal_id) {
            $goalCtrl->markGoalAsFinished($goal_id);
            redirect_with_notice('assistant-accompagnements.php', 'success', "Accompagnement marqu� comme termin�.");
        }

        if ($action === 'delete_guide') {
            $guide_id = (int)($_POST['guide_id'] ?? 0);
            if ($guide_id > 0 && $guideCtrl->deleteGuideByAssistant($guide_id, $assistant_id)) {
            redirect_with_notice('assistant-accompagnements.php', 'success', "�tape supprim�e avec succ�s.");
            } else {
            redirect_with_notice('assistant-accompagnements.php', 'error', "Suppression impossible : action non autoris�e.");
            }
        }
    } catch (Exception $e) {
        redirect_with_notice('assistant-accompagnements.php', 'error', "Erreur : " . $e->getMessage());
    }
}

// === ADVANCED SEARCH FILTERS ===
$asFilters = [
    'keyword'          => trim((string)($_GET['keyword'] ?? '')),
    'type'             => $_GET['type'] ?? '',
    'status'           => $_GET['status'] ?? '',
    'assistant_status' => $_GET['assistant_status'] ?? '',
    'priority'         => $_GET['priority'] ?? '',
    'sort'             => $_GET['sort'] ?? 'created_desc',
];
$hasActiveFilter = !empty($asFilters['keyword']) || !empty($asFilters['type']) || !empty($asFilters['status'])
    || !empty($asFilters['assistant_status']) || !empty($asFilters['priority'])
    || ($asFilters['sort'] !== 'created_desc');

// Get the goals assigned to this assistant that are validated by Admin (with optional filters)
$goals = $goalCtrl->searchGoalsForAssistant($assistant_id, $asFilters);

$pendingCount = 0;
$activeCount = 0;
$doneCount = 0;
foreach ($goals as $goalItem) {
  if (($goalItem['assistant_validation_status'] ?? '') === 'en_attente') {
    $pendingCount++;
  }
  if (($goalItem['status'] ?? '') === 'en_cours') {
    $activeCount++;
  }
  if (($goalItem['status'] ?? '') === 'termine') {
    $doneCount++;
  }
}

$asTypeLabels = [
    'cv'           => '📄 CV',
    'cover_letter' => '✉️ Lettre',
    'linkedin'     => '🔗 LinkedIn',
    'interview'    => '🎤 Entretien',
    'other'        => '📌 Autre',
];

// Display map for the urgency signal the citizen has sent via the QR page.
// The QR generator lives on the citizen view; here we only read the result.
$urgencyMap = [
    'simple'     => ['label' => '🟢 Simple',         'class' => 'urgency-simple'],
    'assistance' => ['label' => "🟡 Besoin d'aide",  'class' => 'urgency-assistance'],
    'urgent'     => ['label' => '🔴 Urgent',          'class' => 'urgency-urgent'],
];
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Mes Missions d'accompagnement</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../backoffice/assets/style.css" />
    <style>
      .content-section { color: #dbe5ff; }
      .hero {
        background: linear-gradient(120deg, rgba(99,91,255,.24), rgba(76,201,240,.16));
        border: 1px solid rgba(255,255,255,.09);
        border-radius: 18px;
        padding: 20px;
        margin-bottom: 18px;
      }
      .hero h2 { margin: 0; font-size: 1.25rem; color: #fff; }
      .hero p { margin: 8px 0 0; color: #c6d2f2; }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 16px;
      }
      .stat {
        background: rgba(10, 16, 34, .62);
        border: 1px solid rgba(255,255,255,.09);
        border-radius: 12px;
        padding: 12px;
      }
      .stat-label { color: #97a8d3; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
      .stat-value { margin-top: 4px; color: #fff; font-size: 1.5rem; font-weight: 800; }

      .mission-card {
        background: #10182d;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 16px;
        margin-bottom: 16px;
        overflow: hidden;
      }
      .mission-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        padding: 16px 18px 10px;
        border-bottom: 1px solid rgba(255,255,255,.06);
      }
      .mission-title { margin: 4px 0; color: #fff; font-size: 1.05rem; }
      .mission-meta { color: #9db0dc; font-size: .84rem; }
      .mission-body { padding: 14px 18px 18px; }
      .mission-desc { color: #cfdbff; margin-bottom: 14px; }

      .chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 11px;
        font-size: 12px;
        font-weight: 700;
      }
      .chip-pending { background: rgba(255,184,77,.18); color: #ffcf7d; }
      .chip-active  { background: rgba(76,201,240,.18); color: #89def6; }
      .chip-done    { background: rgba(49,208,170,.2); color: #72e3c7; }
      .chip-cancel  { background: rgba(255,107,107,.2); color: #ff9fa7; }

      .panel {
        background: rgba(255,255,255,.03);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        padding: 12px;
        margin-top: 12px;
      }
      .panel h4, .panel h5 { color: #eef3ff; margin-top: 0; }

      .guide-list { margin-top: 8px; }
      .guide-item {
        background: rgba(8, 13, 27, .78);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 10px;
        position: relative;
      }
      .guide-item h5 { margin: 0 0 6px; color: #fff; }
      .guide-item p { margin: 0; color: #c8d5f7; }

      .btn {
        padding: 8px 14px;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        color: #fff;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
      }
      .btn-success { background: linear-gradient(135deg, #18b999, #128c74); }
      .btn-danger  { background: linear-gradient(135deg, #ef6a5b, #b63d31); }
      .btn-primary { background: linear-gradient(135deg, #2163ff, #1741aa); }
      .btn:hover { filter: brightness(1.04); }

      input.form-control, textarea.form-control, select.form-control {
        width: 100%;
        padding: 9px 10px;
        margin-bottom: 10px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 8px;
        background: rgba(255,255,255,.03);
        color: #fff;
        transition: border-color .2s, box-shadow .2s;
      }
      input.form-control::placeholder, textarea.form-control::placeholder { color: #9baed8; }
      /* Make <option> items visible inside the dark-themed select dropdown */
      select.form-control option {
        background-color: #10182d;
        color: #ffffff;
        padding: 6px;
      }
      select.form-control option:checked,
      select.form-control option:hover {
        background-color: #1f2a4d;
      }
      select.form-control:focus {
        border-color: rgba(125, 117, 255, .55);
        box-shadow: 0 0 0 3px rgba(99,91,255,.18);
        outline: none;
      }
      .form-control.is-invalid {
        border-color: #ff6b6b !important;
        box-shadow: 0 0 0 3px rgba(255,107,107,.18) !important;
      }
      .field-error-msg {
        display: none;
        align-items: center;
        gap: 4px;
        font-size: .82rem;
        color: #ffb3b8;
        background: rgba(255,107,107,.12);
        border: 1px solid rgba(255,107,107,.32);
        border-radius: 8px;
        padding: 7px 11px;
        margin: -4px 0 12px;
        font-weight: 600;
      }
      .field-error-msg.visible {
        display: flex;
        animation: as-shake .42s cubic-bezier(.36,.07,.19,.97);
      }
      @keyframes as-shake {
        0%, 100% { transform: translateX(0); }
        15%, 45%, 75% { transform: translateX(-6px); }
        30%, 60%, 90% { transform: translateX(6px); }
      }
      .char-counter {
        font-size: .76rem;
        color: #9baed8;
        text-align: right;
        margin-top: -6px;
        margin-bottom: 8px;
      }
      .char-counter.warn { color: #ffcf7d; }
      .char-counter.over { color: #ff8b94; }

      .toolbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }

      .notice {
        border-radius: 10px;
        padding: 11px 14px;
        margin-bottom: 14px;
        border: 1px solid transparent;
        font-weight: 600;
      }
      .notice-success { background: #e9f8ee; border-color: #b9e6c7; color: #1e6a39; }
      .notice-error { background: #fdecee; border-color: #f5c1c7; color: #8b1f2b; }
      .notice-info { background: #edf4ff; border-color: #cfe0ff; color: #1d4e9a; }

      .confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(5, 9, 20, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 16px;
      }
      .confirm-overlay.open { display: flex; }
      .confirm-modal {
        width: min(460px, 100%);
        background: #111a30;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 14px;
        box-shadow: 0 24px 55px rgba(0,0,0,.45);
        padding: 16px;
      }
      .confirm-title {
        margin: 0;
        color: #fff;
        font-size: 1.05rem;
      }
      .confirm-text {
        margin: 8px 0 0;
        color: #c4d2f8;
        line-height: 1.4;
      }
      .confirm-actions {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
      }
      .btn-ghost {
        background: rgba(255,255,255,.08);
        color: #e8eeff;
      }
      @media (max-width: 980px) {
        .stats-grid { grid-template-columns: 1fr 1fr; }
      }
      @media (max-width: 640px) {
        .stats-grid { grid-template-columns: 1fr; }
      }

      /* ─── ASSISTANT SIDEBAR (project palette: purple/indigo) ─── */
      .sidebar.as-aside {
        background: linear-gradient(180deg, #0f1629 0%, #1a1640 100%);
        border-right: 1px solid rgba(80, 70, 229, .12);
        padding: 0;
      }
      .as-aside-inner {
        height: 100%;
        display: flex;
        flex-direction: column;
        padding: 22px 16px 18px;
        gap: 18px;
      }

      .as-brand-link {
        display: flex; align-items: center; gap: 12px;
        text-decoration: none;
      }
      .as-brand-mark {
        width: 44px; height: 44px;
        background: linear-gradient(135deg, #5046e5, #3d34d1);
        color: #fff;
        border-radius: 12px;
        font-weight: 800; font-size: .95rem;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 8px 22px rgba(80, 70, 229, .4);
        flex-shrink: 0;
      }
      .as-brand-text { color: #fff; font-weight: 800; font-size: 1rem; line-height: 1.1; }
      .as-brand-role {
        display: inline-block;
        margin-top: 4px;
        color: #c7d2fe;
        font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        background: rgba(80, 70, 229, .18);
        padding: 2px 8px;
        border-radius: 100px;
      }

      .as-profile-card {
        background: rgba(80, 70, 229, .1);
        border: 1px solid rgba(80, 70, 229, .25);
        border-radius: 14px;
        padding: 12px;
        display: flex; align-items: center; gap: 11px;
      }
      .as-avatar {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
        font-weight: 800; font-size: .82rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .18);
      }
      .as-profile-info { min-width: 0; }
      .as-profile-name {
        color: #fff; font-weight: 700; font-size: .9rem;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .as-profile-mail {
        color: #a5b4fc; font-size: .7rem;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }

      .as-section-label {
        font-size: .7rem; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; color: #a5b4fc;
        padding: 0 4px; margin-bottom: -4px;
      }
      .as-nav { display: flex; flex-direction: column; gap: 6px; }
      .as-nav a.as-nav-link {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 13px;
        border-radius: 12px;
        text-decoration: none;
        color: #dbe5ff;
        border: 1px solid transparent;
        transition: background .2s, border-color .2s, color .2s, transform .15s;
      }
      .as-nav a.as-nav-link:hover {
        background: rgba(80, 70, 229, .15);
        color: #fff;
        border-color: rgba(80, 70, 229, .28);
        transform: translateX(2px);
      }
      .as-nav a.as-nav-link.is-active {
        background: linear-gradient(135deg, rgba(80, 70, 229, .3), rgba(61, 52, 209, .18));
        color: #fff;
        border-color: rgba(99, 102, 241, .45);
        box-shadow: 0 6px 18px rgba(80, 70, 229, .22);
      }
      .as-nav-emoji { font-size: 1.25rem; flex-shrink: 0; }
      .as-nav-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
      .as-nav-title { font-weight: 700; font-size: .9rem; }
      .as-nav-sub { font-size: .7rem; opacity: .7; }

      .as-aside-spacer { flex: 1; }

      .as-aside-footer {
        border-top: 1px dashed rgba(80, 70, 229, .2);
        padding-top: 14px;
        display: flex; flex-direction: column; gap: 8px;
      }
      .as-aside-footer a {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 10px 14px;
        border-radius: 10px;
        font-family: inherit; font-weight: 700; font-size: .82rem;
        text-decoration: none;
        transition: all .18s;
      }
      .as-link-home {
        background: rgba(80, 70, 229, .12);
        color: #c7d2fe;
        border: 1px solid rgba(80, 70, 229, .26);
      }
      .as-link-home:hover { background: rgba(80, 70, 229, .22); color: #fff; }
      .as-link-logout {
        background: rgba(239, 68, 68, .12);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, .25);
      }
      .as-link-logout:hover { background: rgba(239, 68, 68, .22); color: #fff; }

      /* ─── 3-SECTION TOOLBAR (dark theme) ─── */
      .as-tool-form { display: flex; flex-direction: column; gap: 12px; margin-bottom: 22px; }
      .as-tool-panel {
        background: rgba(10, 16, 34, .62);
        border: 1px solid rgba(255,255,255,.09);
        border-radius: 16px;
        padding: 16px 20px;
      }
      .as-tool-heading {
        display: flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: #97a8d3; margin-bottom: 10px;
      }
      .as-search-pill {
        font-size: .73rem;
        background: rgba(99,91,255,.25);
        color: #c5beff;
        padding: 3px 10px;
        border-radius: 100px;
        font-weight: 700;
        margin-left: 8px;
      }

      /* SEARCH */
      .as-search-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
      .as-search-row input[type="text"] {
        flex: 1; min-width: 240px;
        padding: 10px 14px;
        background: rgba(255,255,255,.04); color: #fff;
        border: 1.5px solid rgba(255,255,255,.16); border-radius: 10px;
        font-family: inherit; font-size: .9rem; outline: none;
        transition: border-color .15s, box-shadow .15s;
      }
      .as-search-row input[type="text"]::placeholder { color: #9baed8; }
      .as-search-row input[type="text"]:focus { border-color: #7d75ff; box-shadow: 0 0 0 3px rgba(99,91,255,.18); }

      /* FILTERS (collapsible) */
      .as-tool-filters summary {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        cursor: pointer; list-style: none; user-select: none;
        font-weight: 700; color: #eef3ff;
      }
      .as-tool-filters summary::-webkit-details-marker { display: none; }
      .as-tool-filters[open] summary { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px dashed rgba(255,255,255,.1); }
      .as-filters-grid {
        display: grid; gap: 12px;
        grid-template-columns: repeat(4, 1fr);
      }
      @media (max-width: 900px) { .as-filters-grid { grid-template-columns: repeat(2, 1fr); } }
      @media (max-width: 540px) { .as-filters-grid { grid-template-columns: 1fr; } }
      .as-field { display: flex; flex-direction: column; gap: 4px; }
      .as-field label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #97a8d3; }
      .as-field input, .as-field select {
        padding: 9px 12px;
        background: rgba(255,255,255,.04); border: 1.5px solid rgba(255,255,255,.16);
        border-radius: 9px;
        color: #fff; font-family: inherit; font-size: .875rem;
        outline: none; transition: border-color .15s, box-shadow .15s;
      }
      .as-field input::placeholder { color: #9baed8; }
      .as-field input:focus, .as-field select:focus { border-color: #7d75ff; box-shadow: 0 0 0 3px rgba(99,91,255,.18); }
      .as-field select option { background: #10182d; color: #fff; }
      .as-filters-actions {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        margin-top: 14px; padding-top: 12px; border-top: 1px dashed rgba(255,255,255,.1);
      }
      .as-result-count { margin-left: auto; font-size: .82rem; color: #9db0dc; font-weight: 600; }

      /* SORT */
      .as-sort-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
      .as-sort-row label { font-weight: 700; color: #eef3ff; font-size: .9rem; }
      .as-sort-row select {
        flex: 1; min-width: 240px;
        padding: 9px 12px;
        background: rgba(255,255,255,.04); color: #fff;
        border: 1.5px solid rgba(255,255,255,.16); border-radius: 9px;
        font-family: inherit; font-size: .9rem; outline: none;
      }
      .as-sort-row select:focus { border-color: #7d75ff; box-shadow: 0 0 0 3px rgba(99,91,255,.18); }

      .as-btn-search {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 18px;
        background: linear-gradient(135deg, #635bff, #4f46e5);
        color: #fff; border: none; border-radius: 10px;
        font-family: inherit; font-size: .85rem; font-weight: 700; cursor: pointer;
      }
      .as-btn-search:hover { filter: brightness(1.08); }
      .as-btn-reset {
        padding: 9px 16px;
        background: transparent; color: #c8d5f7;
        border: 1.5px solid rgba(255,255,255,.16); border-radius: 10px;
        font-family: inherit; font-size: .85rem; font-weight: 700; text-decoration: none;
      }
      .as-btn-reset:hover { background: rgba(255,255,255,.06); color: #fff; }

      /* ─── URGENCY SIGNAL BADGE (dark theme) ─── */
      .urgency-meta { margin-top: 6px; }
      .urgency-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: .76rem;
        font-weight: 800;
        border: 1px solid transparent;
        letter-spacing: .01em;
      }
      .urgency-simple {
        background: rgba(16, 185, 129, .15);
        color: #6ee7b7;
        border-color: rgba(16, 185, 129, .35);
      }
      .urgency-assistance {
        background: rgba(245, 158, 11, .18);
        color: #fcd34d;
        border-color: rgba(245, 158, 11, .4);
      }
      .urgency-urgent {
        background: rgba(239, 68, 68, .18);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, .45);
        animation: urgentPulseDark 1.6s ease-in-out infinite;
      }
      @keyframes urgentPulseDark {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, .55); }
        50%      { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
      }

    </style>
  </head>
  <body data-page="chatbot">
    <div class="shell">
      <aside class="sidebar as-aside">
        <div class="as-aside-inner">

          <a href="index.html" class="as-brand-link">
            <span class="as-brand-mark">SV</span>
            <div>
              <div class="as-brand-text">SecondVoice</div>
              <span class="as-brand-role">🎯 Espace Assistant</span>
            </div>
          </a>

          <div class="as-profile-card">
            <div class="as-avatar"><?= htmlspecialchars($assistantInitials) ?></div>
            <div class="as-profile-info">
              <div class="as-profile-name"><?= htmlspecialchars($assistantFullName) ?></div>
              <?php if ($assistantEmail): ?>
                <div class="as-profile-mail"><?= htmlspecialchars($assistantEmail) ?></div>
              <?php else: ?>
                <div class="as-profile-mail">Accompagnateur</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="as-section-label">Navigation</div>
          <nav class="as-nav">
            <a class="as-nav-link is-active" href="assistant-accompagnements.php">
              <span class="as-nav-emoji">📋</span>
              <span class="as-nav-text">
                <span class="as-nav-title">Mes Missions</span>
                <span class="as-nav-sub">Demandes & accompagnements</span>
              </span>
            </a>
            <a class="as-nav-link" href="assistant-guides.php">
              <span class="as-nav-emoji">📖</span>
              <span class="as-nav-text">
                <span class="as-nav-title">Mes Guides</span>
                <span class="as-nav-sub">Étapes & contenus</span>
              </span>
            </a>
            <a class="as-nav-link" href="copilote.php">
              <span class="as-nav-emoji">💬</span>
              <span class="as-nav-text">
                <span class="as-nav-title">ChatBot</span>
                <span class="as-nav-sub">Urgences, stats, modèles</span>
              </span>
            </a>
          </nav>

          <div class="as-aside-spacer"></div>

          <div class="as-aside-footer">
            <a class="as-link-home" href="index.html">🏠 Retour à l'accueil</a>
            <a class="as-link-logout" href="logout.php">🚪 Déconnexion</a>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div><h1 class="page-title">Mes Missions d'accompagnement</h1></div>
          <div class="toolbar-actions">
            <a class="update-button" href="export-assistant-accompagnements.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" rel="noopener">📄 Export PDF</a>
            <a class="update-button" href="assistant-guides.php">Voir tous mes guides</a>
            <a class="update-button" href="logout.php">Deconnexion</a>
          </div>
        </div>

        <div class="page-grid">
          <section class="content-section">
            <?php require __DIR__ . '/../partials/flash.php'; ?>

            <div class="hero">
              <h2>Interface Assistant Moderne</h2>
              <p>Validez les missions, cr�ez des �tapes concr�tes et suivez l'avancement en un seul �cran.</p>
              <div class="stats-grid">
                <div class="stat"><div class="stat-label">Total missions</div><div class="stat-value"><?= count($goals) ?></div></div>
                <div class="stat"><div class="stat-label">En attente</div><div class="stat-value"><?= $pendingCount ?></div></div>
                <div class="stat"><div class="stat-label">En cours</div><div class="stat-value"><?= $activeCount ?></div></div>
                <div class="stat"><div class="stat-label">Termin�es</div><div class="stat-value"><?= $doneCount ?></div></div>
              </div>
            </div>

            <?php
            // Pie chart: my missions by status (assistant-validation perspective)
            $asPieCounts = [
                'a_evaluer'  => 0,
                'acceptees'  => 0,
                'refusees'   => 0,
                'terminees'  => 0,
            ];
            foreach ($goals as $__g) {
                $av = $__g['assistant_validation_status'] ?? 'en_attente';
                $st = $__g['status'] ?? 'soumis';
                if ($st === 'termine')               { $asPieCounts['terminees']++; }
                elseif ($av === 'en_attente')        { $asPieCounts['a_evaluer']++; }
                elseif ($av === 'accepte')           { $asPieCounts['acceptees']++; }
                elseif ($av === 'refuse')            { $asPieCounts['refusees']++; }
            }
            $pieTitle = '📊 Mes missions — répartition';
            $pieTone  = 'dark';
            $pieData  = [
                ['label' => '🕐 À évaluer',  'value' => $asPieCounts['a_evaluer'],  'color' => '#f59e0b'],
                ['label' => '⚡ Acceptées',  'value' => $asPieCounts['acceptees'],  'color' => '#3b82f6'],
                ['label' => '✅ Terminées', 'value' => $asPieCounts['terminees'],  'color' => '#10b981'],
                ['label' => '❌ Refusées',   'value' => $asPieCounts['refusees'],   'color' => '#ef4444'],
            ];
            require __DIR__ . '/../partials/pie_chart.php';
            ?>

            <!-- TOOLBAR — 3 sections: Recherche / Filtres / Tri -->
            <form method="GET" class="as-tool-form">

              <!-- 1) RECHERCHE -->
              <div class="as-tool-panel">
                <div class="as-tool-heading">🔍 Recherche</div>
                <div class="as-search-row">
                  <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($asFilters['keyword']) ?>" placeholder="Mot-clé (titre, description, client)…">
                  <button type="submit" class="as-btn-search">Rechercher</button>
                </div>
              </div>

              <!-- 2) FILTRES -->
              <details class="as-tool-panel as-tool-filters" <?= $hasActiveFilter ? 'open' : '' ?>>
                <summary>
                  <span>🎛 Filtres avancés
                    <?php if ($hasActiveFilter): ?>
                      <span class="as-search-pill">actifs</span>
                    <?php endif; ?>
                  </span>
                  <span class="as-search-pill">Afficher / masquer</span>
                </summary>

                <div class="as-filters-grid">
                  <div class="as-field">
                    <label for="type">Type de service</label>
                    <select id="type" name="type">
                      <option value="">— Tous —</option>
                      <?php foreach ($asTypeLabels as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $asFilters['type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="as-field">
                    <label for="assistant_status">Mon état</label>
                    <select id="assistant_status" name="assistant_status">
                      <option value="">— Tous —</option>
                      <option value="en_attente" <?= $asFilters['assistant_status'] === 'en_attente' ? 'selected' : '' ?>>🕐 À évaluer</option>
                      <option value="accepte"    <?= $asFilters['assistant_status'] === 'accepte'    ? 'selected' : '' ?>>✅ Acceptée</option>
                      <option value="refuse"     <?= $asFilters['assistant_status'] === 'refuse'     ? 'selected' : '' ?>>❌ Refusée</option>
                    </select>
                  </div>

                  <div class="as-field">
                    <label for="status">Statut mission</label>
                    <select id="status" name="status">
                      <option value="">— Tous —</option>
                      <option value="soumis"   <?= $asFilters['status'] === 'soumis'   ? 'selected' : '' ?>>🕐 Soumis</option>
                      <option value="en_cours" <?= $asFilters['status'] === 'en_cours' ? 'selected' : '' ?>>⚡ En cours</option>
                      <option value="termine"  <?= $asFilters['status'] === 'termine'  ? 'selected' : '' ?>>✅ Terminé</option>
                      <option value="annule"   <?= $asFilters['status'] === 'annule'   ? 'selected' : '' ?>>❌ Annulé</option>
                    </select>
                  </div>

                  <div class="as-field">
                    <label for="priority">Priorité</label>
                    <select id="priority" name="priority">
                      <option value="">— Toutes —</option>
                      <option value="haute"   <?= $asFilters['priority'] === 'haute'   ? 'selected' : '' ?>>🔴 Haute</option>
                      <option value="moyenne" <?= $asFilters['priority'] === 'moyenne' ? 'selected' : '' ?>>🟡 Moyenne</option>
                      <option value="basse"   <?= $asFilters['priority'] === 'basse'   ? 'selected' : '' ?>>🟢 Basse</option>
                    </select>
                  </div>

                </div>

                <div class="as-filters-actions">
                  <button type="submit" class="as-btn-search">Appliquer</button>
                  <a href="assistant-accompagnements.php" class="as-btn-reset">↺ Réinitialiser</a>
                  <span class="as-result-count"><?= count($goals) ?> résultat<?= count($goals) > 1 ? 's' : '' ?></span>
                </div>
              </details>

              <!-- 3) TRI -->
              <div class="as-tool-panel">
                <div class="as-tool-heading">↕ Tri</div>
                <div class="as-sort-row">
                  <label for="sort">Trier par :</label>
                  <select id="sort" name="sort" onchange="this.form.submit()">
                    <option value="created_desc"  <?= $asFilters['sort'] === 'created_desc'  ? 'selected' : '' ?>>📅 Plus récents</option>
                    <option value="created_asc"   <?= $asFilters['sort'] === 'created_asc'   ? 'selected' : '' ?>>📅 Plus anciens</option>
                    <option value="title_asc"     <?= $asFilters['sort'] === 'title_asc'     ? 'selected' : '' ?>>🔤 Titre (A→Z)</option>
                    <option value="title_desc"    <?= $asFilters['sort'] === 'title_desc'    ? 'selected' : '' ?>>🔤 Titre (Z→A)</option>
                    <option value="priority_high" <?= $asFilters['sort'] === 'priority_high' ? 'selected' : '' ?>>🔥 Priorité (haute → basse)</option>
                  </select>
                </div>
              </div>
            </form>

            <?php if (empty($goals)): ?>
              <div class="panel">
                <p><?= $hasActiveFilter ? 'Aucune mission ne correspond à vos critères. Essayez d\'élargir votre recherche.' : 'Aucune demande transférée pour le moment. Dès qu\'un admin valide une demande, elle apparaîtra ici.' ?></p>
              </div>
            <?php else: ?>
              <?php foreach ($goals as $g):
                $statusClass = 'chip-active';
                if ($g['status'] === 'termine') $statusClass = 'chip-done';
                if ($g['status'] === 'annule')  $statusClass = 'chip-cancel';
                if (($g['assistant_validation_status'] ?? '') === 'en_attente') $statusClass = 'chip-pending';
              ?>
                <div class="mission-card">
                  <div class="mission-head">
                    <div>
                      <div class="mission-meta">Demande #<?= (int) $g['id'] ?> � <?= htmlspecialchars($g['type']) ?></div>
                      <h3 class="mission-title"><?= htmlspecialchars($g['title']) ?></h3>
                      <div class="mission-meta">Client : <?= htmlspecialchars($g['user_name'] ?? 'Inconnu') ?></div>
                      <?php if (!empty($g['urgency_level']) && isset($urgencyMap[$g['urgency_level']])): ?>
                        <div class="mission-meta urgency-meta">
                          <span class="urgency-tag <?= $urgencyMap[$g['urgency_level']]['class'] ?>"
                                title="Signal du citoyen le <?= !empty($g['urgency_updated_at']) ? htmlspecialchars(date('d/m/Y à H:i', strtotime($g['urgency_updated_at']))) : '' ?>">
                            📡 <?= $urgencyMap[$g['urgency_level']]['label'] ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                    <span class="chip <?= $statusClass ?>\"><?= strtoupper(htmlspecialchars((string) $g['status'])) ?></span>
                  </div>
                  <div class="mission-body">
                    <p class="mission-desc"><?= nl2br(htmlspecialchars($g['description'])) ?></p>

                  <!-- Action d'acceptation -->
                  <?php if ($g['assistant_validation_status'] == 'en_attente'): ?>
                    <form method="POST" class="panel js-accept-form" novalidate>
                      <input type="hidden" name="action"  value="evaluate">
                      <input type="hidden" name="goal_id" value="<?= (int)$g['id'] ?>">

                      <label for="priority-<?= (int)$g['id'] ?>"><strong>Priorité</strong></label>
                      <select id="priority-<?= (int)$g['id'] ?>" name="priority" class="form-control">
                        <option value="">— Choisir —</option>
                        <option value="basse">Basse</option>
                        <option value="moyenne" selected>Moyenne</option>
                        <option value="haute">Haute</option>
                      </select>
                      <div class="field-error-msg" data-error-for="priority">⚠ Veuillez choisir une priorité.</div>

                      <label for="status-<?= (int)$g['id'] ?>"><strong>État</strong></label>
                      <select id="status-<?= (int)$g['id'] ?>" name="status" class="form-control">
                        <option value="">— Choisir —</option>
                        <option value="en_cours" selected>En cours</option>
                        <option value="termine">Terminé</option>
                      </select>
                      <div class="field-error-msg" data-error-for="status">⚠ Veuillez choisir l'état initial.</div>

                      <textarea class="form-control" name="comment" placeholder="Un commentaire motivant pour le citoyen (min. 10 caractères)..." maxlength="500"></textarea>
                      <div class="char-counter" data-counter-for="comment">0 / 500</div>
                      <div class="field-error-msg" data-error-for="comment">⚠ Un commentaire motivant est requis (min. 10 caractères).</div>

                      <button type="submit" name="decision" value="accepte" class="btn btn-success js-accept-btn">Accepter la mission</button>
                      <button type="submit" name="decision" value="refuse" class="btn btn-danger js-skip-validate"
                              data-confirm="La demande sera refusée et définitivement supprimée. Cette action est irréversible."
                              data-confirm-type="danger"
                              data-confirm-title="Refuser cette demande ?"
                              data-confirm-action="Oui, refuser"
                              data-confirm-icon="✋">Refuser</button>
                    </form>
                  <?php endif; ?>

                  <!-- Gestion des guides (Si accept�) -->
                  <?php if ($g['assistant_validation_status'] == 'accepte'): ?>
                    <div class="guide-list panel">
                      <h4>�tapes / Guides cr��s :</h4>
                      <?php
                        $guides = $guideCtrl->getGuidesByGoal($g['id']);
                        if (!empty($guides)):
                          foreach ($guides as $guide):
                      ?>
                        <div class="guide-item">
                          <h5><?= htmlspecialchars($guide['title']) ?></h5>
                          <p><?= nl2br(htmlspecialchars($guide['content'])) ?></p>
                          <div style="position: absolute; top: 10px; right: 10px; display:flex; gap: 5px;">
                            <a href="assistant-guides.php?mode=edit&id=<?= (int)$guide['id'] ?>" class="btn btn-primary" style="padding: 4px 8px; font-size: 12px;">Modifier</a>
                            <form method="POST" style="margin: 0;"
                                  data-confirm="Cette étape sera supprimée du guide. Cette action est définitive."
                                  data-confirm-type="danger"
                                  data-confirm-title="Supprimer cette étape ?"
                                  data-confirm-action="Oui, supprimer"
                                  data-confirm-icon="🗑️">
                              <input type="hidden" name="action"   value="delete_guide">
                              <input type="hidden" name="guide_id" value="<?= (int)$guide['id'] ?>">
                              <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;">Supprimer</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; else: ?>
                        <p>Aucune �tape cr��e pour le moment.</p>
                      <?php endif; ?>

                      <?php if ($g['status'] == 'en_cours'): ?>
                        <form method="POST" style="margin-top: 15px; text-align: right;"
                              data-confirm="Cet accompagnement sera marqué comme terminé. Vous ne pourrez plus ajouter d'étapes."
                              data-confirm-type="success"
                              data-confirm-title="Clôturer l'accompagnement ?"
                              data-confirm-action="Oui, clôturer"
                              data-confirm-icon="🏁">
                          <input type="hidden" name="action"  value="finish_goal">
                          <input type="hidden" name="goal_id" value="<?= (int)$g['id'] ?>">
                          <button class="btn btn-success" type="submit">Cl�turer l'accompagnement</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>
        </div>
      </main>
    </div>
    <script src="../backoffice/assets/app.js"></script>
    <script>
      // === Contrôle de saisie : Accepter la mission ===
      (function () {
        var ACCEPT_COMMENT_MIN = 10;
        var COMMENT_MAX = 500;
        var ALLOWED_PRIORITY = ['basse', 'moyenne', 'haute'];
        var ALLOWED_STATUS   = ['en_cours', 'termine'];

        function showError(form, fieldName, message) {
          var field   = form.querySelector('[name="' + fieldName + '"]');
          var errorEl = form.querySelector('[data-error-for="' + fieldName + '"]');
          if (!field || !errorEl) return;
          field.classList.add('is-invalid');
          if (message) errorEl.textContent = '⚠ ' + message;
          // Force animation restart on repeated invalid clicks
          errorEl.classList.remove('visible');
          void errorEl.offsetWidth;
          errorEl.classList.add('visible');
        }

        function clearError(form, fieldName) {
          var field   = form.querySelector('[name="' + fieldName + '"]');
          var errorEl = form.querySelector('[data-error-for="' + fieldName + '"]');
          if (field)   field.classList.remove('is-invalid');
          if (errorEl) errorEl.classList.remove('visible');
        }

        function validate(form) {
          var firstInvalid = null;
          var ok = true;

          var priorityVal = (form.querySelector('[name="priority"]').value || '').trim();
          if (ALLOWED_PRIORITY.indexOf(priorityVal) === -1) {
            showError(form, 'priority', 'Veuillez choisir une priorité (Basse, Moyenne ou Haute).');
            firstInvalid = firstInvalid || form.querySelector('[name="priority"]');
            ok = false;
          } else { clearError(form, 'priority'); }

          var statusVal = (form.querySelector('[name="status"]').value || '').trim();
          if (ALLOWED_STATUS.indexOf(statusVal) === -1) {
            showError(form, 'status', "Veuillez choisir l'état initial (En cours ou Terminé).");
            firstInvalid = firstInvalid || form.querySelector('[name="status"]');
            ok = false;
          } else { clearError(form, 'status'); }

          var commentField = form.querySelector('[name="comment"]');
          var commentVal = (commentField.value || '').trim();
          if (commentVal.length === 0) {
            showError(form, 'comment', 'Un commentaire motivant est requis (min. ' + ACCEPT_COMMENT_MIN + ' caractères).');
            firstInvalid = firstInvalid || commentField;
            ok = false;
          } else if (commentVal.length < ACCEPT_COMMENT_MIN) {
            showError(form, 'comment', 'Commentaire trop court : ' + commentVal.length + ' / ' + ACCEPT_COMMENT_MIN + ' caractères minimum.');
            firstInvalid = firstInvalid || commentField;
            ok = false;
          } else { clearError(form, 'comment'); }

          if (firstInvalid) firstInvalid.focus();
          return ok;
        }

        document.querySelectorAll('.js-accept-form').forEach(function (form) {
          // Defensive: ensure no native HTML5 popup fires
          form.setAttribute('novalidate', 'novalidate');
          form.noValidate = true;
          form.querySelectorAll('[required]').forEach(function (el) { el.removeAttribute('required'); });

          // Validate ONLY when the "Accepter" button is clicked
          var acceptBtn = form.querySelector('.js-accept-btn');
          if (acceptBtn) {
            acceptBtn.addEventListener('click', function (e) {
              if (!validate(form)) {
                e.preventDefault();
                return;
              }
              acceptBtn.disabled = true;
              acceptBtn.textContent = '⏳ Acceptation...';
            });
          }

          // Live: clear errors as user fixes the fields
          form.querySelectorAll('[name="priority"], [name="status"]').forEach(function (el) {
            el.addEventListener('change', function () {
              if (this.value) clearError(form, this.name);
            });
          });
          var commentField = form.querySelector('[name="comment"]');
          if (commentField) {
            // Char counter
            var counter = form.querySelector('[data-counter-for="comment"]');
            function updateCounter() {
              if (!counter) return;
              var len = commentField.value.length;
              counter.textContent = len + ' / ' + COMMENT_MAX;
              counter.classList.toggle('warn', len > COMMENT_MAX * 0.85 && len < COMMENT_MAX);
              counter.classList.toggle('over', len >= COMMENT_MAX);
            }
            commentField.addEventListener('input', function () {
              updateCounter();
              if (this.value.trim().length >= ACCEPT_COMMENT_MIN) clearError(form, 'comment');
            });
            updateCounter();
          }
        });
      })();
    </script>
    <?php require __DIR__ . '/../partials/confirm-modal.php'; ?>
    <?php require __DIR__ . '/../partials/role-switcher.php'; ?>
  </body>
</html>

