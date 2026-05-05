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

// Handle POST actions — sets a session flash and redirects (PRG pattern) so
// the animated toast plays once and a refresh won't re-trigger anything.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errorGoalId = (int)($_POST['goal_id'] ?? 0);

    try {
        if ($action === 'add_guide') {
            $goal_id = (int)$_POST['goal_id'];
            $title   = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if (empty($title) || empty($content) || $goal_id <= 0) throw new RuntimeException("Tous les champs sont requis.");
            $guide = new Guide($goal_id, $title, $content);
            if (!$guideCtrl->createGuideByAssistant($guide, $assistant_id)) throw new RuntimeException("Vous n'êtes pas autorisé à créer un guide pour cet accompagnement.");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Guide ajouté avec succès."];
        }
        if ($action === 'edit_guide') {
            $guide_id = (int)$_POST['guide_id'];
            $goal_id  = (int)$_POST['goal_id'];
            $title    = trim($_POST['title'] ?? '');
            $content  = trim($_POST['content'] ?? '');
            if (empty($title) || empty($content)) throw new RuntimeException("Tous les champs sont requis.");
            $guide = new Guide($goal_id, $title, $content);
            if (!$guideCtrl->updateGuideByAssistant($guide, $guide_id, $assistant_id)) throw new RuntimeException("Modification non autorisée.");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Guide modifié avec succès."];
        }
        if ($action === 'delete_guide') {
            $guide_id = (int)$_POST['guide_id'];
            if (!$guideCtrl->deleteGuideByAssistant($guide_id, $assistant_id)) throw new RuntimeException("Suppression non autorisée.");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Guide supprimé."];
        }
    } catch (Exception $e) {
        $_SESSION['flash']     = ['type' => 'error', 'message' => $e->getMessage()];
        $_SESSION['flash_ctx'] = ['error_goal_id' => $errorGoalId];
    }

    header('Location: assistant-guides.php');
    exit;
}

// === ADVANCED SEARCH FILTERS ===
$agFilters = [
    'assistant_id' => $assistant_id,
    'keyword'      => trim((string)($_GET['keyword'] ?? '')),
    'goal_id'      => $_GET['goal_id'] ?? '',
    'goal_type'    => $_GET['goal_type'] ?? '',
    'goal_status'  => $_GET['goal_status'] ?? '',
    'sort'         => $_GET['sort'] ?? 'created_desc',
];
$hasActiveFilter = !empty($agFilters['keyword']) || !empty($agFilters['goal_id']) || !empty($agFilters['goal_type'])
    || !empty($agFilters['goal_status'])
    || ($agFilters['sort'] !== 'created_desc');

$guides       = $hasActiveFilter
    ? $guideCtrl->searchGuides($agFilters)
    : $guideCtrl->getGuidesByAssistant($assistant_id);
$activeGoals  = $goalCtrl->getAcceptedGoalsForAssistant($assistant_id);

// Group guides by goal_id
$groupedGuides = [];
foreach ($guides as $g) {
    $groupedGuides[$g['goal_id']][] = $g;
}

// When a filter is active, only show goal groups that have matching guides
if ($hasActiveFilter) {
    $matchingGoalIds = array_keys($groupedGuides);
    $activeGoals = array_values(array_filter($activeGoals, function ($goal) use ($matchingGoalIds) {
        return in_array((int)$goal['id'], array_map('intval', $matchingGoalIds), true);
    }));
}

$agTypeLabels = [
    'cv'           => '📄 CV',
    'cover_letter' => '✉️ Lettre',
    'linkedin'     => '🔗 LinkedIn',
    'interview'    => '🎤 Entretien',
    'other'        => '📌 Autre',
];

// === STATISTICS (computed from unfiltered data so they always reflect the global picture) ===
$statsAllGuides = $hasActiveFilter
    ? $guideCtrl->searchGuides(['assistant_id' => $assistant_id])
    : $guides;
$statsAllMissions = $goalCtrl->getAcceptedGoalsForAssistant($assistant_id);

$statTotalGuides     = count($statsAllGuides);
$statTotalMissions   = count($statsAllMissions);
$statCompletedMissions = 0;
$statActiveMissions = 0;
foreach ($statsAllMissions as $__m) {
    $st = $__m['status'] ?? '';
    if ($st === 'termine')  { $statCompletedMissions++; }
    if ($st === 'en_cours') { $statActiveMissions++; }
}
$statAvgSteps = $statTotalMissions > 0 ? round($statTotalGuides / $statTotalMissions, 1) : 0;

// Pie data: guides per mission type
$statByType = ['cv' => 0, 'cover_letter' => 0, 'linkedin' => 0, 'interview' => 0, 'other' => 0];
foreach ($statsAllGuides as $__g) {
    $t = $__g['goal_type'] ?? 'other';
    if (isset($statByType[$t])) { $statByType[$t]++; }
}

// Find editable guide id
$editGuideId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editGuide   = null;
if ($editGuideId) {
    $editGuide = $guideCtrl->getGuideByIdForAssistant($editGuideId, $assistant_id);
}

// Error state helper — peek (don't clear) flash so we can also auto-open the
// form on the goal where the error occurred. The flash itself is consumed
// later by the flash partial.
$errorGoalId = 0;
if (
    isset($_SESSION['flash']['type'], $_SESSION['flash_ctx']['error_goal_id'])
    && $_SESSION['flash']['type'] === 'error'
) {
    $errorGoalId = (int) $_SESSION['flash_ctx']['error_goal_id'];
}
unset($_SESSION['flash_ctx']);
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

    /* ─── 3-SECTION TOOLBAR ─── */
    .sv-tool-form { display: flex; flex-direction: column; gap: 12px; margin-bottom: 22px; }
    .sv-tool-panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 16px 20px;
    }
    .sv-tool-heading {
      display: flex; align-items: center; gap: 8px;
      font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
      color: var(--muted); margin-bottom: 10px;
    }
    .sv-search-pill {
      font-size: .73rem;
      background: var(--purple-soft); color: var(--purple);
      padding: 3px 10px; border-radius: 100px;
      font-weight: 700; margin-left: 8px;
    }

    /* SEARCH */
    .sv-search-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .sv-search-row input[type="text"] {
      flex: 1; min-width: 240px;
      padding: 10px 14px;
      background: var(--input-bg); color: var(--text);
      border: 1.5px solid var(--input-border); border-radius: 10px;
      font-family: inherit; font-size: .9rem; outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .sv-search-row input[type="text"]:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }

    /* FILTERS (collapsible) */
    .sv-tool-filters summary {
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      cursor: pointer; list-style: none; user-select: none;
      font-weight: 700; color: var(--text);
    }
    .sv-tool-filters summary::-webkit-details-marker { display: none; }
    .sv-tool-filters[open] summary { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px dashed var(--line); }
    .sv-filters-grid {
      display: grid; gap: 12px;
      grid-template-columns: repeat(4, 1fr);
    }
    @media (max-width: 900px) { .sv-filters-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 540px) { .sv-filters-grid { grid-template-columns: 1fr; } }
    .sv-tool-field { display: flex; flex-direction: column; gap: 4px; }
    .sv-tool-field label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
    .sv-tool-field input, .sv-tool-field select {
      padding: 9px 12px;
      background: var(--input-bg); border: 1.5px solid var(--input-border);
      border-radius: 9px;
      font-family: inherit; font-size: .875rem; color: var(--text);
      outline: none; transition: border-color .15s, box-shadow .15s;
    }
    .sv-tool-field input:focus, .sv-tool-field select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }
    .sv-filters-actions {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--line);
    }
    .sv-result-count { margin-left: auto; font-size: .82rem; color: var(--muted); font-weight: 600; }

    /* SORT */
    .sv-sort-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .sv-sort-row label { font-weight: 700; color: var(--text); font-size: .9rem; }
    .sv-sort-row select {
      flex: 1; min-width: 240px;
      padding: 9px 12px;
      background: var(--input-bg); color: var(--text);
      border: 1.5px solid var(--input-border); border-radius: 9px;
      font-family: inherit; font-size: .9rem; outline: none;
    }
    .sv-sort-row select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-soft); }

    .sv-btn-search {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 9px 18px;
      background: var(--purple, #635bff); color: #fff;
      border: none; border-radius: 10px;
      font-family: inherit; font-size: .85rem; font-weight: 700; cursor: pointer;
    }
    .sv-btn-search:hover { filter: brightness(1.08); }
    .sv-btn-reset {
      padding: 9px 16px;
      background: transparent; color: var(--muted);
      border: 1.5px solid var(--line); border-radius: 10px;
      font-family: inherit; font-size: .85rem; font-weight: 700; text-decoration: none;
    }
    .sv-btn-reset:hover { background: var(--soft-surface); color: var(--text); }

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

    /* ─── STATISTICS ─── */
    .ag-stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 14px;
      margin-bottom: 18px;
    }
    .ag-stat-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px 18px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      position: relative;
      overflow: hidden;
      transition: box-shadow .2s, transform .2s;
    }
    .ag-stat-card:hover { box-shadow: 0 8px 24px rgba(99,91,255,.12); transform: translateY(-2px); }
    .ag-stat-card::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 4px;
      background: var(--purple);
    }
    .ag-stat-card.ag-stat-active::before    { background: #3b82f6; }
    .ag-stat-card.ag-stat-completed::before { background: #10b981; }
    .ag-stat-card.ag-stat-avg::before       { background: #f59e0b; }
    .ag-stat-icon { font-size: 1.4rem; opacity: .85; }
    .ag-stat-num { font-size: 1.9rem; font-weight: 800; color: var(--text); line-height: 1; }
    .ag-stat-label {
      font-size: .73rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      color: var(--muted);
    }
  </style>
</head>
<body data-page="document">
<div class="overlay" data-overlay></div>
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
        <a class="as-nav-link" href="assistant-accompagnements.php">
          <span class="as-nav-emoji">📋</span>
          <span class="as-nav-text">
            <span class="as-nav-title">Mes Missions</span>
            <span class="as-nav-sub">Demandes & accompagnements</span>
          </span>
        </a>
        <a class="as-nav-link is-active" href="assistant-guides.php">
          <span class="as-nav-emoji">📖</span>
          <span class="as-nav-text">
            <span class="as-nav-title">Mes Guides</span>
            <span class="as-nav-sub">Étapes & contenus</span>
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
      <div>
        <button class="mobile-toggle" data-nav-toggle aria-label="Menu">☰</button>
        <h1 class="page-title">Mes guides d'accompagnement</h1>
      </div>
      <div class="toolbar-actions">
        <a class="update-button" href="export-assistant-guides.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" rel="noopener">📄 Export PDF</a>
        <a class="update-button" href="assistant-accompagnements.php">← Missions</a>
        <button class="icon-button icon-moon" data-theme-toggle aria-label="Thème"></button>
      </div>
    </div>

    <div class="page-grid">
      <section class="content-section">

        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <div class="sv-layout">

          <div style="margin-bottom: 22px;">
            <h2 class="section-title" style="margin-bottom:2px;">📖 Mes guides par accompagnement</h2>
            <p style="font-size:.85rem; color:var(--muted);">Retrouvez les étapes de guide pour chacune de vos missions.</p>
          </div>

          <!-- STATS CARDS -->
          <div class="ag-stats-row">
            <div class="ag-stat-card">
              <div class="ag-stat-icon">📖</div>
              <div class="ag-stat-num"><?= $statTotalGuides ?></div>
              <div class="ag-stat-label">Guides créés</div>
            </div>
            <div class="ag-stat-card ag-stat-active">
              <div class="ag-stat-icon">🎯</div>
              <div class="ag-stat-num"><?= $statActiveMissions ?></div>
              <div class="ag-stat-label">Missions en cours</div>
            </div>
            <div class="ag-stat-card ag-stat-completed">
              <div class="ag-stat-icon">✅</div>
              <div class="ag-stat-num"><?= $statCompletedMissions ?></div>
              <div class="ag-stat-label">Missions terminées</div>
            </div>
            <div class="ag-stat-card ag-stat-avg">
              <div class="ag-stat-icon">📊</div>
              <div class="ag-stat-num"><?= $statAvgSteps ?></div>
              <div class="ag-stat-label">Étapes / mission (moy.)</div>
            </div>
          </div>

          <!-- PIE CHART : guides by mission type -->
          <?php
          $pieTitle = '📊 Répartition de mes guides par type de mission';
          $pieTone  = 'light';
          $pieData  = [
              ['label' => '📄 CV',                 'value' => $statByType['cv'],           'color' => '#5046e5'],
              ['label' => '✉️ Lettre de motivation','value' => $statByType['cover_letter'], 'color' => '#3b82f6'],
              ['label' => '🔗 LinkedIn',           'value' => $statByType['linkedin'],     'color' => '#10b981'],
              ['label' => '🎤 Entretien',          'value' => $statByType['interview'],    'color' => '#f59e0b'],
              ['label' => '📌 Autre',              'value' => $statByType['other'],        'color' => '#94a3b8'],
          ];
          require __DIR__ . '/../partials/pie_chart.php';
          ?>

          <!-- TOOLBAR — 3 sections: Recherche / Filtres / Tri -->
          <form method="GET" class="sv-tool-form">

            <!-- 1) RECHERCHE -->
            <div class="sv-tool-panel">
              <div class="sv-tool-heading">🔍 Recherche</div>
              <div class="sv-search-row">
                <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($agFilters['keyword']) ?>" placeholder="Mot-clé (titre, contenu, mission)…">
                <button type="submit" class="sv-btn-search">Rechercher</button>
              </div>
            </div>

            <!-- 2) FILTRES -->
            <details class="sv-tool-panel sv-tool-filters" <?= $hasActiveFilter ? 'open' : '' ?>>
              <summary>
                <span>🎛 Filtres avancés
                  <?php if ($hasActiveFilter): ?>
                    <span class="sv-search-pill">actifs</span>
                  <?php endif; ?>
                </span>
                <span class="sv-search-pill">Afficher / masquer</span>
              </summary>

              <div class="sv-filters-grid">
                <div class="sv-tool-field">
                  <label for="goal_id">N° de mission</label>
                  <input type="number" id="goal_id" name="goal_id" min="1" value="<?= htmlspecialchars((string)$agFilters['goal_id']) ?>" placeholder="Ex: 12">
                </div>

                <div class="sv-tool-field">
                  <label for="goal_type">Type de mission</label>
                  <select id="goal_type" name="goal_type">
                    <option value="">— Tous —</option>
                    <?php foreach ($agTypeLabels as $val => $lbl): ?>
                      <option value="<?= $val ?>" <?= $agFilters['goal_type'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="sv-tool-field">
                  <label for="goal_status">Statut mission</label>
                  <select id="goal_status" name="goal_status">
                    <option value="">— Tous —</option>
                    <option value="en_cours" <?= $agFilters['goal_status'] === 'en_cours' ? 'selected' : '' ?>>⚡ En cours</option>
                    <option value="termine"  <?= $agFilters['goal_status'] === 'termine'  ? 'selected' : '' ?>>✅ Terminé</option>
                  </select>
                </div>

              </div>

              <div class="sv-filters-actions">
                <button type="submit" class="sv-btn-search">Appliquer</button>
                <a href="assistant-guides.php" class="sv-btn-reset">↺ Réinitialiser</a>
                <span class="sv-result-count"><?= count($guides) ?> guide<?= count($guides) > 1 ? 's' : '' ?> · <?= count($activeGoals) ?> mission<?= count($activeGoals) > 1 ? 's' : '' ?></span>
              </div>
            </details>

            <!-- 3) TRI -->
            <div class="sv-tool-panel">
              <div class="sv-tool-heading">↕ Tri</div>
              <div class="sv-sort-row">
                <label for="sort">Trier par :</label>
                <select id="sort" name="sort" onchange="this.form.submit()">
                  <option value="created_desc" <?= $agFilters['sort'] === 'created_desc' ? 'selected' : '' ?>>📅 Plus récents</option>
                  <option value="created_asc"  <?= $agFilters['sort'] === 'created_asc'  ? 'selected' : '' ?>>📅 Plus anciens</option>
                  <option value="title_asc"    <?= $agFilters['sort'] === 'title_asc'    ? 'selected' : '' ?>>🔤 Titre (A→Z)</option>
                  <option value="title_desc"   <?= $agFilters['sort'] === 'title_desc'   ? 'selected' : '' ?>>🔤 Titre (Z→A)</option>
                </select>
              </div>
            </div>
          </form>

          <?php if (empty($activeGoals)): ?>
            <div class="sv-empty">
                <div class="sv-empty-icon"><?= $hasActiveFilter ? '🔍' : '🤝' ?></div>
                <div class="sv-empty-title"><?= $hasActiveFilter ? 'Aucun résultat' : 'Aucune mission active' ?></div>
                <div class="sv-empty-sub"><?= $hasActiveFilter ? 'Aucune mission ne contient de guide correspondant à vos critères.' : 'Vous devez accepter des missions pour pouvoir créer des guides.' ?></div>
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
                          <form method="POST"
                                data-confirm="Cette étape sera définitivement supprimée du guide. Cette action est irréversible."
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
<?php require __DIR__ . '/../partials/confirm-modal.php'; ?>
<?php require __DIR__ . '/../partials/role-switcher.php'; ?>
</body>
</html>
