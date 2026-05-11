<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';
require_once __DIR__ . '/../../controller/GuideController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int) $_SESSION['user_id'];

// Fetch user identity for the document header
$userInfo = null;
try {
    $stmt = Config::getConnexion()->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$userFullName = $userInfo
    ? trim(($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? ''))
    : 'Utilisateur';
if ($userFullName === '') { $userFullName = 'Utilisateur'; }
$userEmail = $userInfo['email'] ?? '';

// Honour the same GET filters as the parent page (g_* prefixes)
$goalFilters = [
    'user_id'          => $user_id,
    'keyword'          => trim((string)($_GET['g_keyword'] ?? '')),
    'type'             => $_GET['g_type']      ?? '',
    'status'           => $_GET['g_status']    ?? '',
    'admin_status'     => $_GET['g_admin']     ?? '',
    'assistant_status' => $_GET['g_assistant'] ?? '',
    'sort'             => $_GET['g_sort']      ?? 'created_desc',
];

$goalCtrl  = new GoalController();
$guideCtrl = new GuideController();
$goals     = $goalCtrl->searchGoals($goalFilters);
$guides    = $guideCtrl->searchGuides(['user_id' => $user_id]);

$guidesByGoal = [];
foreach ($guides as $g) { $guidesByGoal[$g['goal_id']][] = $g; }

$typeMap      = ['cv' => 'CV', 'cover_letter' => 'Lettre de motivation', 'linkedin' => 'LinkedIn', 'interview' => 'Entretien', 'other' => 'Autre'];
$statusMap    = ['soumis' => 'Soumis', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé'];
$adminMap     = ['en_attente' => 'En attente', 'valide' => 'Validé', 'refuse' => 'Refusé'];
$assistantMap = ['en_attente' => 'En attente', 'accepte' => 'Accepté', 'refuse' => 'Refusé'];

$counts = ['soumis' => 0, 'en_cours' => 0, 'termine' => 0, 'annule' => 0];
foreach ($goals as $g) {
    $s = $g['status'] ?? 'soumis';
    if (isset($counts[$s])) { $counts[$s]++; }
}

// Build a "filtres appliqués" line for the header (only non-empty)
$activeFiltersText = [];
if (!empty($goalFilters['keyword']))          { $activeFiltersText[] = 'Mot-clé : "' . $goalFilters['keyword'] . '"'; }
if (!empty($goalFilters['type']))             { $activeFiltersText[] = 'Type : ' . ($typeMap[$goalFilters['type']] ?? $goalFilters['type']); }
if (!empty($goalFilters['status']))           { $activeFiltersText[] = 'Statut : ' . ($statusMap[$goalFilters['status']] ?? $goalFilters['status']); }
if (!empty($goalFilters['admin_status']))     { $activeFiltersText[] = 'Validation admin : ' . ($adminMap[$goalFilters['admin_status']] ?? $goalFilters['admin_status']); }
if (!empty($goalFilters['assistant_status'])) { $activeFiltersText[] = 'Validation assistant : ' . ($assistantMap[$goalFilters['assistant_status']] ?? $goalFilters['assistant_status']); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Export PDF — Mes accompagnements | SecondVoice</title>
<style>
  @page { size: A4; margin: 16mm 14mm; }
  * { box-sizing: border-box; }
  body {
    font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
    color: #1a1a2e;
    margin: 0;
    padding: 28px 32px;
    background: #fff;
    font-size: 11pt;
    line-height: 1.45;
  }

  .export-bar {
    position: fixed; top: 16px; right: 16px;
    display: flex; gap: 10px; z-index: 100;
  }
  .export-bar button {
    padding: 10px 20px; border: none; border-radius: 9px;
    font-family: inherit; font-size: .9rem; font-weight: 700; cursor: pointer;
  }
  .btn-download { background: #5046e5; color: #fff; box-shadow: 0 4px 14px rgba(80,70,229,.35); }
  .btn-download:hover  { background: #3d34d1; }
  .btn-download:disabled { opacity: .65; cursor: progress; }
  .btn-close  { background: #e2e8f8; color: #5046e5; }
  .btn-close:hover  { background: #cfd6f0; }
  .pdf-toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    background: #1a1a2e; color: #fff;
    padding: 12px 20px; border-radius: 10px;
    font-size: .9rem; font-weight: 600;
    box-shadow: 0 12px 32px rgba(0,0,0,.25);
    z-index: 200;
    display: none;
  }
  .pdf-toast.show { display: flex; align-items: center; gap: 10px; }
  .pdf-toast .spinner {
    width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: pdf-spin .7s linear infinite;
  }
  @keyframes pdf-spin { to { transform: rotate(360deg); } }

  .doc-header {
    display: flex; align-items: center; justify-content: space-between;
    padding-bottom: 14px; border-bottom: 3px solid #5046e5; margin-bottom: 24px;
  }
  .doc-brand { display: flex; align-items: center; gap: 14px; }
  .doc-brand-mark {
    width: 50px; height: 50px;
    background: linear-gradient(135deg, #5046e5, #3d34d1);
    color: #fff; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 14pt;
  }
  .doc-brand-name { font-size: 14pt; font-weight: 800; color: #5046e5; line-height: 1; }
  .doc-brand-tag  { font-size: 8.5pt; color: #6b7a9f; letter-spacing: .05em; text-transform: uppercase; margin-top: 4px; }
  .doc-meta { text-align: right; font-size: 9.5pt; color: #6b7a9f; line-height: 1.5; }
  .doc-meta strong { color: #1a1a2e; }

  h1 { font-size: 19pt; margin: 0 0 6px; color: #1a1a2e; font-weight: 800; }
  .doc-subtitle { font-size: 10pt; color: #6b7a9f; margin-bottom: 22px; }

  .info-card {
    background: #f7f9ff; border: 1px solid #e2e8f8;
    border-radius: 10px; padding: 12px 16px;
    margin-bottom: 14px; font-size: 10pt;
  }
  .info-card strong { color: #1a1a2e; }

  .stats-row {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 10px; margin-bottom: 22px;
  }
  .stat-card {
    background: #f7f9ff; border: 1px solid #e2e8f8;
    border-radius: 10px; padding: 12px 14px; text-align: center;
    border-left: 4px solid #5046e5;
  }
  .stat-card.s-yellow { border-left-color: #f59e0b; }
  .stat-card.s-blue   { border-left-color: #3b82f6; }
  .stat-card.s-green  { border-left-color: #10b981; }
  .stat-num   { font-size: 18pt; font-weight: 800; color: #1a1a2e; line-height: 1; }
  .stat-label { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; color: #6b7a9f; font-weight: 700; margin-top: 4px; }

  h2.section { font-size: 13pt; margin: 4px 0 12px; color: #5046e5; font-weight: 800; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 9.5pt; }
  thead th {
    background: #5046e5; color: #fff;
    padding: 9px 10px; text-align: left;
    font-weight: 700; font-size: 9pt;
    letter-spacing: .04em; text-transform: uppercase;
  }
  tbody td {
    padding: 9px 10px;
    border-bottom: 1px solid #e2e8f8;
    vertical-align: top;
  }
  tbody tr:nth-child(even) td { background: #f7f9ff; }

  .badge {
    display: inline-block; padding: 2px 8px;
    border-radius: 100px; font-size: 8.5pt; font-weight: 700;
    background: #eeeeff; color: #5046e5;
  }
  .badge-yellow { background: #fffbeb; color: #92400e; }
  .badge-blue   { background: #eff6ff; color: #1d4ed8; }
  .badge-green  { background: #ecfdf5; color: #065f46; }
  .badge-red    { background: #fef2f2; color: #991b1b; }
  .badge-gray   { background: #f1f5f9; color: #475569; }

  .empty {
    padding: 36px; text-align: center; color: #6b7a9f;
    background: #f7f9ff; border: 1px dashed #c0c8e0; border-radius: 10px;
  }

  .doc-footer {
    margin-top: 28px; padding-top: 12px;
    border-top: 1px solid #e2e8f8;
    font-size: 9pt; color: #6b7a9f;
    display: flex; justify-content: space-between;
  }

  /* Hidden in PDF capture */
  .no-pdf { /* will be hidden via JS during pdf generation */ }
</style>
</head>
<body>

<div class="export-bar no-pdf">
  <button class="btn-download" id="btnDownload">📥 Télécharger le PDF</button>
  <button class="btn-close" onclick="window.close()">Fermer</button>
</div>

<div class="pdf-toast" id="pdfToast">
  <span class="spinner"></span>
  <span>Génération du PDF en cours…</span>
</div>

<div id="export-content">

<header class="doc-header">
  <div class="doc-brand">
    <div class="doc-brand-mark">SV</div>
    <div>
      <div class="doc-brand-name">SecondVoice</div>
      <div class="doc-brand-tag">Plateforme d'accompagnement citoyen</div>
    </div>
  </div>
  <div class="doc-meta">
    <strong>Document généré le</strong><br>
    <?= date('d/m/Y') ?> à <?= date('H:i') ?>
  </div>
</header>

<h1>📋 Mes accompagnements</h1>
<div class="doc-subtitle">Document récapitulatif des demandes d'accompagnement et guides associés</div>

<div class="info-card">
  <strong>👤 Bénéficiaire :</strong> <?= htmlspecialchars($userFullName) ?>
  <?php if (!empty($userEmail)): ?>
    &nbsp;·&nbsp; <strong>📧</strong> <?= htmlspecialchars($userEmail) ?>
  <?php endif; ?>
</div>

<?php if (!empty($activeFiltersText)): ?>
<div class="info-card" style="background:#fffbeb;border-color:#fde68a;">
  <strong>🔍 Filtres appliqués :</strong> <?= htmlspecialchars(implode(' · ', $activeFiltersText)) ?>
</div>
<?php endif; ?>

<div class="stats-row">
  <div class="stat-card"><div class="stat-num"><?= count($goals) ?></div><div class="stat-label">Total</div></div>
  <div class="stat-card s-yellow"><div class="stat-num"><?= $counts['soumis'] ?></div><div class="stat-label">Soumises</div></div>
  <div class="stat-card s-blue"><div class="stat-num"><?= $counts['en_cours'] ?></div><div class="stat-label">En cours</div></div>
  <div class="stat-card s-green"><div class="stat-num"><?= $counts['termine'] ?></div><div class="stat-label">Terminées</div></div>
</div>

<h2 class="section">Liste détaillée des demandes</h2>

<?php if (empty($goals)): ?>
  <div class="empty">Aucune demande à exporter avec les filtres actuels.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width: 6%;">#</th>
        <th style="width: 14%;">Type</th>
        <th style="width: 28%;">Titre</th>
        <th style="width: 11%;">Statut</th>
        <th style="width: 11%;">Admin</th>
        <th style="width: 11%;">Assistant</th>
        <th style="width: 11%;">Créée le</th>
        <th style="width: 8%;">Étapes</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($goals as $g):
        $stCls = 'badge-gray';
        if ($g['status'] === 'soumis')       { $stCls = 'badge-yellow'; }
        elseif ($g['status'] === 'en_cours') { $stCls = 'badge-blue'; }
        elseif ($g['status'] === 'termine')  { $stCls = 'badge-green'; }
        elseif ($g['status'] === 'annule')   { $stCls = 'badge-red'; }
        $stepCount = isset($guidesByGoal[$g['id']]) ? count($guidesByGoal[$g['id']]) : 0;
      ?>
      <tr>
        <td><strong>#<?= (int)$g['id'] ?></strong></td>
        <td><?= htmlspecialchars($typeMap[$g['type']] ?? $g['type']) ?></td>
        <td><?= htmlspecialchars($g['title']) ?></td>
        <td><span class="badge <?= $stCls ?>"><?= htmlspecialchars($statusMap[$g['status']] ?? $g['status']) ?></span></td>
        <td><?= htmlspecialchars($adminMap[$g['admin_validation_status']] ?? $g['admin_validation_status']) ?></td>
        <td><?= htmlspecialchars($assistantMap[$g['assistant_validation_status']] ?? $g['assistant_validation_status']) ?></td>
        <td><?= date('d/m/Y', strtotime($g['created_at'])) ?></td>
        <td style="text-align:center;"><strong><?= $stepCount ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<footer class="doc-footer">
  <div>SecondVoice © <?= date('Y') ?> — Document personnel</div>
  <div>Généré pour : <?= htmlspecialchars($userFullName) ?></div>
</footer>

</div><!-- /#export-content -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
  (function () {
    var btn   = document.getElementById('btnDownload');
    var toast = document.getElementById('pdfToast');
    var fileName = 'mes-accompagnements_<?= date('Y-m-d_His') ?>.pdf';

    function generatePdf() {
      btn.disabled = true;
      toast.classList.add('show');
      var element = document.getElementById('export-content');
      var opts = {
        margin:       [10, 10, 12, 10], // mm: top, left, bottom, right
        filename:     fileName,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
        pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
      };
      html2pdf().set(opts).from(element).save().then(function () {
        toast.classList.remove('show');
        btn.disabled = false;
      }).catch(function () {
        toast.classList.remove('show');
        btn.disabled = false;
        alert('Erreur lors de la génération du PDF.');
      });
    }

    btn.addEventListener('click', generatePdf);

    // Auto-download on load
    window.addEventListener('load', function () { setTimeout(generatePdf, 350); });
  })();
</script>

</body>
</html>
