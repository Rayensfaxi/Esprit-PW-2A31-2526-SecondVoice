<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GuideController.php';

$_role     = strtolower((string) ($_SESSION['role']      ?? ''));
$_userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
$isAdmin   = ($_role === 'admin') || ($_userRole === 'admin');
if (!$isAdmin) {
    header('Location: /test-login.php?role=admin&next=view/backoffice/export-gestion-guides.php');
    exit;
}

// Honour parent page filters
$gFilters = [
    'keyword'     => trim((string)($_GET['keyword'] ?? '')),
    'goal_id'     => $_GET['goal_id']     ?? '',
    'goal_type'   => $_GET['goal_type']   ?? '',
    'goal_status' => $_GET['goal_status'] ?? '',
    'sort'        => $_GET['sort']        ?? 'created_desc',
];
$hasActiveFilter = !empty($gFilters['keyword']) || !empty($gFilters['goal_id']) || !empty($gFilters['goal_type'])
    || !empty($gFilters['goal_status'])
    || ($gFilters['sort'] !== 'created_desc');

$guideController = new GuideController();
$guides = $hasActiveFilter ? $guideController->searchGuides($gFilters) : $guideController->getAllGuides();

// Group guides by parent goal
$byGoal = [];
foreach ($guides as $g) { $byGoal[$g['goal_id']][] = $g; }

// Stats
$totalGuides   = count($guides);
$totalMissions = count($byGoal);
$avgSteps      = $totalMissions > 0 ? round($totalGuides / $totalMissions, 1) : 0;

// Global stats (independent of filters) for the cover sheet
$globalTotalGuides   = 0;
$globalTotalMissions = 0;
try {
    $row = Config::getConnexion()->query("SELECT COUNT(*) AS n FROM guides")->fetch();
    if ($row) { $globalTotalGuides = (int)$row['n']; }
    $row = Config::getConnexion()->query("SELECT COUNT(DISTINCT goal_id) AS n FROM guides")->fetch();
    if ($row) { $globalTotalMissions = (int)$row['n']; }
} catch (Throwable $e) {}

$typeMap   = ['cv' => 'CV', 'cover_letter' => 'Lettre de motivation', 'linkedin' => 'LinkedIn', 'interview' => 'Entretien', 'other' => 'Autre'];
$statusMap = ['soumis' => 'Soumis', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé'];

$activeFiltersText = [];
if (!empty($gFilters['keyword']))     { $activeFiltersText[] = 'Mot-clé : "' . $gFilters['keyword'] . '"'; }
if (!empty($gFilters['goal_id']))     { $activeFiltersText[] = 'Mission #' . (int)$gFilters['goal_id']; }
if (!empty($gFilters['goal_type']))   { $activeFiltersText[] = 'Type : ' . ($typeMap[$gFilters['goal_type']] ?? $gFilters['goal_type']); }
if (!empty($gFilters['goal_status'])) { $activeFiltersText[] = 'Statut : ' . ($statusMap[$gFilters['goal_status']] ?? $gFilters['goal_status']); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Export PDF — Gestion des guides | SecondVoice</title>
<style>
  @page { size: A4; margin: 16mm 14mm; }
  * { box-sizing: border-box; }
  body {
    font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
    color: #1a1a2e;
    margin: 0; padding: 28px 32px;
    background: #fff;
    font-size: 11pt; line-height: 1.45;
  }
  .export-bar { position: fixed; top: 16px; right: 16px; display: flex; gap: 10px; z-index: 100; }
  .export-bar button { padding: 10px 20px; border: none; border-radius: 9px; font-family: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
  .btn-download { background: #5046e5; color: #fff; box-shadow: 0 4px 14px rgba(80,70,229,.35); }
  .btn-download:hover { background: #3d34d1; }
  .btn-download:disabled { opacity: .65; cursor: progress; }
  .btn-close { background: #e2e8f8; color: #5046e5; }
  .btn-close:hover { background: #cfd6f0; }
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
  .doc-meta .role-pill {
    display: inline-block; margin-top: 4px;
    background: #5046e5; color: #fff;
    padding: 3px 10px; border-radius: 100px;
    font-size: 8.5pt; font-weight: 700; letter-spacing: .04em;
  }

  h1 { font-size: 19pt; margin: 0 0 6px; color: #1a1a2e; font-weight: 800; }
  .doc-subtitle { font-size: 10pt; color: #6b7a9f; margin-bottom: 22px; }

  .info-card { background: #f7f9ff; border: 1px solid #e2e8f8; border-radius: 10px; padding: 12px 16px; margin-bottom: 14px; font-size: 10pt; }
  .info-card strong { color: #1a1a2e; }

  .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 22px; }
  .stat-card {
    background: #f7f9ff; border: 1px solid #e2e8f8;
    border-radius: 10px; padding: 12px 14px; text-align: center;
    border-left: 4px solid #5046e5;
  }
  .stat-card.s-blue  { border-left-color: #3b82f6; }
  .stat-card.s-amber { border-left-color: #f59e0b; }
  .stat-card.s-green { border-left-color: #10b981; }
  .stat-num   { font-size: 18pt; font-weight: 800; color: #1a1a2e; line-height: 1; }
  .stat-label { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; color: #6b7a9f; font-weight: 700; margin-top: 4px; }

  h2.section { font-size: 13pt; margin: 16px 0 12px; color: #5046e5; font-weight: 800; }

  .mission-block {
    margin-bottom: 22px;
    border: 1px solid #e2e8f8;
    border-radius: 12px;
    overflow: hidden;
    page-break-inside: avoid;
  }
  .mission-head {
    background: linear-gradient(135deg, #eeeeff, #f7f9ff);
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f8;
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 12px;
  }
  .mission-info { flex: 1; min-width: 0; }
  .mission-num  { font-size: 8pt; color: #6b7a9f; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 3px; }
  .mission-title { font-size: 11.5pt; font-weight: 800; color: #1a1a2e; margin-bottom: 3px; }
  .mission-meta  { font-size: 9pt; color: #6b7a9f; }
  .mission-chip {
    display: inline-block; padding: 3px 10px;
    border-radius: 100px; font-size: 8pt; font-weight: 700;
    background: #5046e5; color: #fff; white-space: nowrap;
  }

  .step-list { padding: 14px 16px; }
  .step {
    display: flex; gap: 12px;
    padding: 10px 0;
    border-bottom: 1px dashed #e2e8f8;
    page-break-inside: avoid;
  }
  .step:last-child { border-bottom: none; }
  .step-num {
    flex-shrink: 0;
    width: 26px; height: 26px;
    background: #5046e5; color: #fff;
    border-radius: 50%;
    font-size: 9pt; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
  }
  .step-body { flex: 1; min-width: 0; }
  .step-title { font-size: 10.5pt; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
  .step-content {
    font-size: 9.5pt; color: #4a5578; line-height: 1.55;
    white-space: pre-wrap; word-break: break-word;
  }
  .step-date { font-size: 8.5pt; color: #94a3b8; margin-top: 4px; }

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
      <div class="doc-brand-tag">Tableau de bord — Bibliothèque de guides</div>
    </div>
  </div>
  <div class="doc-meta">
    <strong>Document généré le</strong><br>
    <?= date('d/m/Y') ?> à <?= date('H:i') ?>
    <div><span class="role-pill">🛡 ADMIN</span></div>
  </div>
</header>

<h1>📖 Bibliothèque de guides d'accompagnement</h1>
<div class="doc-subtitle">Vue administrateur — Tous les guides rédigés par les assistants, regroupés par mission</div>

<?php if (!empty($activeFiltersText)): ?>
<div class="info-card" style="background:#fffbeb;border-color:#fde68a;">
  <strong>🔍 Filtres appliqués :</strong> <?= htmlspecialchars(implode(' · ', $activeFiltersText)) ?>
</div>
<?php endif; ?>

<h2 class="section">📊 Vue d'ensemble (toutes périodes confondues)</h2>
<div class="stats-row">
  <div class="stat-card"><div class="stat-num"><?= $globalTotalGuides ?></div><div class="stat-label">Total guides (global)</div></div>
  <div class="stat-card s-blue"><div class="stat-num"><?= $globalTotalMissions ?></div><div class="stat-label">Missions couvertes (global)</div></div>
  <div class="stat-card s-amber"><div class="stat-num"><?= $totalGuides ?></div><div class="stat-label">Guides exportés</div></div>
  <div class="stat-card s-green"><div class="stat-num"><?= $avgSteps ?></div><div class="stat-label">Étapes / mission (moy.)</div></div>
</div>

<h2 class="section">📑 Détail par mission (<?= $totalMissions ?>)</h2>

<?php if (empty($byGoal)): ?>
  <div class="empty">Aucun guide à exporter avec les filtres actuels.</div>
<?php else: ?>
  <?php foreach ($byGoal as $goalId => $goalGuides):
      $first = $goalGuides[0];
      $citoyen   = $first['nom'] ?? ($first['user_nom'] ?? '—');
      $assistant = $first['assistant_nom'] ?? '—';
      $goalType  = $first['goal_type'] ?? '';
      $goalDesc  = $first['goal_desc'] ?? $first['goal_title'] ?? 'Mission sans titre';
  ?>
    <div class="mission-block">
      <div class="mission-head">
        <div class="mission-info">
          <div class="mission-num">Mission #<?= (int)$goalId ?><?= $goalType ? ' · ' . htmlspecialchars($typeMap[$goalType] ?? $goalType) : '' ?></div>
          <div class="mission-title"><?= htmlspecialchars(mb_substr($goalDesc, 0, 100)) ?></div>
          <div class="mission-meta">👤 <?= htmlspecialchars($citoyen) ?> &nbsp;·&nbsp; 🤝 <?= htmlspecialchars($assistant) ?></div>
        </div>
        <div class="mission-chip"><?= count($goalGuides) ?> étape<?= count($goalGuides) > 1 ? 's' : '' ?></div>
      </div>

      <div class="step-list">
        <?php $i = 1; foreach ($goalGuides as $guide): ?>
          <div class="step">
            <div class="step-num"><?= $i++ ?></div>
            <div class="step-body">
              <div class="step-title"><?= htmlspecialchars($guide['title']) ?></div>
              <div class="step-content"><?= htmlspecialchars($guide['content']) ?></div>
              <div class="step-date">📅 <?= date('d/m/Y H:i', strtotime($guide['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<footer class="doc-footer">
  <div>SecondVoice © <?= date('Y') ?> — Document administratif confidentiel</div>
  <div>Page exportée le <?= date('d/m/Y H:i') ?></div>
</footer>

</div><!-- /#export-content -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
  (function () {
    var btn   = document.getElementById('btnDownload');
    var toast = document.getElementById('pdfToast');
    var fileName = 'gestion-guides_<?= date('Y-m-d_His') ?>.pdf';

    function generatePdf() {
      btn.disabled = true;
      toast.classList.add('show');
      var element = document.getElementById('export-content');
      var opts = {
        margin:      [10, 10, 12, 10],
        filename:    fileName,
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
        pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] }
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
    window.addEventListener('load', function () { setTimeout(generatePdf, 350); });
  })();
</script>

</body>
</html>
