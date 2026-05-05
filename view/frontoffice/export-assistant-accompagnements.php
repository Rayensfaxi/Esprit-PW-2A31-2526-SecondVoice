<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/GoalController.php';

$_role = strtolower((string) ($_SESSION['role'] ?? ''));
$_userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
$isAssistant = in_array($_role, ['assistant', 'agent'], true) || in_array($_userRole, ['assistant', 'agent'], true);
if (!$isAssistant) {
    header('Location: /test-login.php?role=assistant&next=view/frontoffice/export-assistant-accompagnements.php');
    exit;
}
$assistant_id = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($assistant_id <= 0) {
    header('Location: /test-login.php?role=assistant');
    exit;
}

// Identity for header
$assistantInfo = null;
try {
    $stmt = Config::getConnexion()->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id = :id");
    $stmt->execute(['id' => $assistant_id]);
    $assistantInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$assistantName = $assistantInfo
    ? trim(($assistantInfo['prenom'] ?? '') . ' ' . ($assistantInfo['nom'] ?? ''))
    : 'Assistant';
if ($assistantName === '') { $assistantName = 'Assistant'; }
$assistantEmail = $assistantInfo['email'] ?? '';

// Honour parent page filters
$asFilters = [
    'keyword'          => trim((string)($_GET['keyword'] ?? '')),
    'type'             => $_GET['type']             ?? '',
    'status'           => $_GET['status']           ?? '',
    'assistant_status' => $_GET['assistant_status'] ?? '',
    'priority'         => $_GET['priority']         ?? '',
    'sort'             => $_GET['sort']             ?? 'created_desc',
];

$goalCtrl = new GoalController();
$goals    = $goalCtrl->searchGoalsForAssistant($assistant_id, $asFilters);

$typeMap      = ['cv' => 'CV', 'cover_letter' => 'Lettre de motivation', 'linkedin' => 'LinkedIn', 'interview' => 'Entretien', 'other' => 'Autre'];
$statusMap    = ['soumis' => 'Soumis', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé'];
$assistantMap = ['en_attente' => 'À évaluer', 'accepte' => 'Acceptée', 'refuse' => 'Refusée'];
$priorityMap  = ['basse' => 'Basse', 'moyenne' => 'Moyenne', 'haute' => 'Haute'];

$pendingCount = $activeCount = $doneCount = 0;
foreach ($goals as $g) {
    if (($g['assistant_validation_status'] ?? '') === 'en_attente') { $pendingCount++; }
    if (($g['status'] ?? '') === 'en_cours') { $activeCount++; }
    if (($g['status'] ?? '') === 'termine')  { $doneCount++; }
}

$activeFiltersText = [];
if (!empty($asFilters['keyword']))          { $activeFiltersText[] = 'Mot-clé : "' . $asFilters['keyword'] . '"'; }
if (!empty($asFilters['type']))             { $activeFiltersText[] = 'Type : ' . ($typeMap[$asFilters['type']] ?? $asFilters['type']); }
if (!empty($asFilters['status']))           { $activeFiltersText[] = 'Statut : ' . ($statusMap[$asFilters['status']] ?? $asFilters['status']); }
if (!empty($asFilters['assistant_status'])) { $activeFiltersText[] = 'Mon état : ' . ($assistantMap[$asFilters['assistant_status']] ?? $asFilters['assistant_status']); }
if (!empty($asFilters['priority']))         { $activeFiltersText[] = 'Priorité : ' . ($priorityMap[$asFilters['priority']] ?? $asFilters['priority']); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Export PDF — Mes missions | SecondVoice</title>
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
  tbody td { padding: 9px 10px; border-bottom: 1px solid #e2e8f8; vertical-align: top; }
  tbody tr:nth-child(even) td { background: #f7f9ff; }

  .badge { display: inline-block; padding: 2px 8px; border-radius: 100px; font-size: 8.5pt; font-weight: 700; background: #eeeeff; color: #5046e5; }
  .badge-yellow { background: #fffbeb; color: #92400e; }
  .badge-blue   { background: #eff6ff; color: #1d4ed8; }
  .badge-green  { background: #ecfdf5; color: #065f46; }
  .badge-red    { background: #fef2f2; color: #991b1b; }
  .badge-gray   { background: #f1f5f9; color: #475569; }

  .empty { padding: 36px; text-align: center; color: #6b7a9f; background: #f7f9ff; border: 1px dashed #c0c8e0; border-radius: 10px; }

  .doc-footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f8; font-size: 9pt; color: #6b7a9f; display: flex; justify-content: space-between; }


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
      <div class="doc-brand-tag">Espace Assistant — Suivi des missions</div>
    </div>
  </div>
  <div class="doc-meta">
    <strong>Document généré le</strong><br>
    <?= date('d/m/Y') ?> à <?= date('H:i') ?>
  </div>
</header>

<h1>🎯 Mes missions d'accompagnement</h1>
<div class="doc-subtitle">Récapitulatif des demandes que je suis ou que je dois évaluer</div>

<div class="info-card">
  <strong>🤝 Assistant :</strong> <?= htmlspecialchars($assistantName) ?>
  <?php if (!empty($assistantEmail)): ?>
    &nbsp;·&nbsp; <strong>📧</strong> <?= htmlspecialchars($assistantEmail) ?>
  <?php endif; ?>
</div>

<?php if (!empty($activeFiltersText)): ?>
<div class="info-card" style="background:#fffbeb;border-color:#fde68a;">
  <strong>🔍 Filtres appliqués :</strong> <?= htmlspecialchars(implode(' · ', $activeFiltersText)) ?>
</div>
<?php endif; ?>

<div class="stats-row">
  <div class="stat-card"><div class="stat-num"><?= count($goals) ?></div><div class="stat-label">Total missions</div></div>
  <div class="stat-card s-yellow"><div class="stat-num"><?= $pendingCount ?></div><div class="stat-label">À évaluer</div></div>
  <div class="stat-card s-blue"><div class="stat-num"><?= $activeCount ?></div><div class="stat-label">En cours</div></div>
  <div class="stat-card s-green"><div class="stat-num"><?= $doneCount ?></div><div class="stat-label">Terminées</div></div>
</div>

<h2 class="section">Détail des missions</h2>

<?php if (empty($goals)): ?>
  <div class="empty">Aucune mission à exporter avec les filtres actuels.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width: 6%;">#</th>
        <th style="width: 13%;">Type</th>
        <th style="width: 24%;">Titre</th>
        <th style="width: 14%;">Citoyen</th>
        <th style="width: 10%;">Mon état</th>
        <th style="width: 10%;">Statut</th>
        <th style="width: 9%;">Priorité</th>
        <th style="width: 10%;">Reçue le</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($goals as $g):
        $av = $g['assistant_validation_status'] ?? 'en_attente';
        $st = $g['status'] ?? 'soumis';
        $avCls = ($av === 'accepte' ? 'badge-green' : ($av === 'refuse' ? 'badge-red' : 'badge-yellow'));
        $stCls = ($st === 'termine' ? 'badge-green' : ($st === 'en_cours' ? 'badge-blue' : ($st === 'annule' ? 'badge-red' : 'badge-yellow')));
        $pr = $g['priority'] ?? '';
        $prCls = ($pr === 'haute' ? 'badge-red' : ($pr === 'moyenne' ? 'badge-yellow' : ($pr === 'basse' ? 'badge-green' : 'badge-gray')));
      ?>
      <tr>
        <td><strong>#<?= (int)$g['id'] ?></strong></td>
        <td><?= htmlspecialchars($typeMap[$g['type']] ?? $g['type']) ?></td>
        <td><?= htmlspecialchars($g['title']) ?></td>
        <td><?= htmlspecialchars($g['user_name'] ?? '—') ?></td>
        <td><span class="badge <?= $avCls ?>"><?= htmlspecialchars($assistantMap[$av] ?? $av) ?></span></td>
        <td><span class="badge <?= $stCls ?>"><?= htmlspecialchars($statusMap[$st] ?? $st) ?></span></td>
        <td><span class="badge <?= $prCls ?>"><?= htmlspecialchars($priorityMap[$pr] ?? '—') ?></span></td>
        <td><?= date('d/m/Y', strtotime($g['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<footer class="doc-footer">
  <div>SecondVoice © <?= date('Y') ?> — Espace Assistant</div>
  <div>Généré pour : <?= htmlspecialchars($assistantName) ?></div>
</footer>

</div><!-- /#export-content -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
  (function () {
    var btn   = document.getElementById('btnDownload');
    var toast = document.getElementById('pdfToast');
    var fileName = 'mes-missions_<?= date('Y-m-d_His') ?>.pdf';

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
