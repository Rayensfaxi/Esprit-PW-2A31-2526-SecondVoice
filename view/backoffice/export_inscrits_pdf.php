<?php
declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Authentification requise.';
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    http_response_code(403);
    echo 'Acces non autorise.';
    exit;
}

require_once __DIR__ . '/../../controller/EventController.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
$dompdfAutoload = __DIR__ . '/../../vendor/dompdf/dompdf/autoload.inc.php';

if (is_file($autoload)) {
    require_once $autoload;
} elseif (is_file($dompdfAutoload)) {
    require_once $dompdfAutoload;
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    http_response_code(500);
    echo 'Dompdf est introuvable. Installez dompdf/dompdf dans vendor pour generer le PDF.';
    exit;
}

function pdf_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pdf_format_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function pdf_filename(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/i', '-', $name) ?: 'evenement';
    return trim($name, '-') ?: 'evenement';
}

$eventId = (int) ($_GET['event_id'] ?? 0);
if ($eventId <= 0) {
    http_response_code(400);
    echo 'Identifiant evenement invalide.';
    exit;
}

$controller = new EventController();
$event = $controller->getEventById($eventId);

if (!$event) {
    http_response_code(404);
    echo 'Evenement introuvable.';
    exit;
}

$registrants = $controller->getRegistrantsByEvent($eventId);
$eventName = (string) ($event['name'] ?? 'Evenement');
$exportDate = date('d/m/Y H:i');
$total = count($registrants);

$rows = '';
if ($registrants === []) {
    $rows = '<tr><td colspan="4" class="empty">Aucun inscrit pour cet evenement.</td></tr>';
} else {
    foreach ($registrants as $registrant) {
        $rows .= '<tr>'
            . '<td>' . pdf_h((string) ($registrant['nom'] ?? '')) . '</td>'
            . '<td>' . pdf_h((string) ($registrant['prenom'] ?? '')) . '</td>'
            . '<td>' . pdf_h((string) ($registrant['email'] ?? '')) . '</td>'
            . '<td>' . pdf_h((string) ($registrant['telephone'] ?? '')) . '</td>'
            . '</tr>';
    }
}

$html = '<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 28px 32px 42px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.45; }
    h1 { text-align: center; color: #4338ca; font-size: 24px; margin: 0 0 18px; letter-spacing: 0; }
    .summary { border: 1px solid #d9ddff; background: #f7f7ff; border-radius: 8px; padding: 14px 16px; margin-bottom: 18px; }
    .summary-row { margin: 4px 0; }
    .label { color: #4f46e5; font-weight: 700; display: inline-block; min-width: 130px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #4f46e5; color: #ffffff; font-weight: 700; text-align: left; padding: 10px 8px; border: 1px solid #4338ca; }
    td { padding: 9px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
    tr:nth-child(even) td { background: #f9fafb; }
    .empty { text-align: center; color: #6b7280; padding: 18px; }
    .footer { position: fixed; left: 0; right: 0; bottom: -20px; text-align: center; color: #6b7280; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
  </style>
</head>
<body>
  <h1>Liste des inscrits</h1>
  <div class="summary">
    <div class="summary-row"><span class="label">Evenement</span>' . pdf_h($eventName) . '</div>
    <div class="summary-row"><span class="label">Date debut</span>' . pdf_h(pdf_format_date((string) ($event['start_date'] ?? ''))) . '</div>
    <div class="summary-row"><span class="label">Date fin</span>' . pdf_h(pdf_format_date((string) ($event['end_date'] ?? ''))) . '</div>
    <div class="summary-row"><span class="label">Lieu</span>' . pdf_h((string) ($event['location'] ?? '-')) . '</div>
    <div class="summary-row"><span class="label">Total inscrits</span>' . $total . '</div>
  </div>
  <table>
    <thead>
      <tr><th>Nom</th><th>Prenom</th><th>Email</th><th>Telephone</th></tr>
    </thead>
    <tbody>' . $rows . '</tbody>
  </table>
  <div class="footer">Export genere le ' . pdf_h($exportDate) . ' - SecondVoice</div>
</body>
</html>';

$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'inscrits_evenement.pdf';
$pdfOutput = $dompdf->output();

if (ob_get_length() !== false) {
    ob_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfOutput));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfOutput;
exit;
