<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controller/EventController.php';

function event_reg_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function event_reg_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

$eventId = (int) ($_GET['event_id'] ?? 0);
$controller = new EventController();
$event = $eventId > 0 ? $controller->getEventById($eventId) : null;

if (!$event) {
    http_response_code(404);
}

$registrants = $event ? $controller->getRegistrantsByEvent($eventId) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $event ? event_reg_h((string) ($event['name'] ?? 'Evenement')) : 'Evenement introuvable' ?> | Inscrits</title>
  <style>
    :root { color-scheme: light; --ink: #172033; --muted: #667085; --line: #d8def0; --brand: #3157d5; --soft: #f4f7ff; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; background: #f7f9fc; color: var(--ink); line-height: 1.5; }
    main { width: min(980px, calc(100% - 32px)); margin: 28px auto; }
    .panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 22px; box-shadow: 0 12px 28px rgba(27, 39, 75, .08); }
    h1 { margin: 0 0 14px; font-size: clamp(26px, 5vw, 38px); line-height: 1.1; letter-spacing: 0; }
    .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 22px; }
    .item { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: var(--soft); }
    .label { display: block; color: var(--brand); font-weight: 700; font-size: 13px; margin-bottom: 4px; }
    .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 8px; }
    table { width: 100%; min-width: 680px; border-collapse: collapse; background: #fff; }
    th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; overflow-wrap: anywhere; }
    th { background: #eef3ff; color: #1f3f9f; font-size: 13px; }
    tr:last-child td { border-bottom: 0; }
    .muted { color: var(--muted); }
  </style>
</head>
<body>
  <main>
    <section class="panel">
      <?php if (!$event): ?>
        <h1>Evenement introuvable</h1>
        <p class="muted">Le lien scanne ne correspond a aucun evenement disponible.</p>
      <?php else: ?>
        <h1>Inscrits - <?= event_reg_h((string) ($event['name'] ?? '')) ?></h1>

        <div class="summary" aria-label="Resume de l'evenement">
          <div class="item"><span class="label">Evenement</span><?= event_reg_h((string) ($event['name'] ?? '')) ?></div>
          <div class="item"><span class="label">Date</span><?= event_reg_h(event_reg_date((string) ($event['start_date'] ?? ''))) ?></div>
          <div class="item"><span class="label">Lieu</span><?= event_reg_h((string) ($event['location'] ?? '-')) ?></div>
          <div class="item"><span class="label">Total inscrits</span><?= count($registrants) ?></div>
        </div>

        <?php if ($registrants === []): ?>
          <p class="muted">Aucun inscrit pour cet evenement.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Nom</th>
                  <th>Prenom</th>
                  <th>Email</th>
                  <th>Telephone</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($registrants as $registrant): ?>
                  <tr>
                    <td><?= event_reg_h((string) ($registrant['nom'] ?? '')) ?></td>
                    <td><?= event_reg_h((string) ($registrant['prenom'] ?? '')) ?></td>
                    <td><?= event_reg_h((string) ($registrant['email'] ?? '')) ?></td>
                    <td><?= event_reg_h((string) ($registrant['telephone'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
