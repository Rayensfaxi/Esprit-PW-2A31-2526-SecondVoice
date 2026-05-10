<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controller/EventController.php';

function event_detail_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function event_detail_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

$eventId = (int) ($_GET['id'] ?? 0);
$controller = new EventController();
$event = $eventId > 0 ? $controller->getEventById($eventId) : null;

if (!$event) {
    http_response_code(404);
}

$resources = $event ? $controller->getResourcesByEvent($eventId) : [];
$materials = array_values(array_filter($resources, static fn(array $row): bool => ($row['type'] ?? '') === 'materiel'));
$rules = array_values(array_filter($resources, static fn(array $row): bool => ($row['type'] ?? '') === 'regle'));
$max = (int) ($event['max'] ?? 0);
$current = (int) ($event['current'] ?? 0);
$available = max(0, $max - $current);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $event ? event_detail_h((string) ($event['name'] ?? 'Evenement')) : 'Evenement introuvable' ?> | SecondVoice</title>
  <style>
    :root { color-scheme: light; --ink: #172033; --muted: #667085; --line: #d8def0; --brand: #3157d5; --soft: #f4f7ff; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; background: #f7f9fc; color: var(--ink); line-height: 1.5; }
    main { width: min(920px, calc(100% - 32px)); margin: 28px auto; }
    .panel { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 22px; box-shadow: 0 12px 28px rgba(27, 39, 75, .08); }
    h1 { margin: 0 0 10px; font-size: clamp(26px, 5vw, 40px); line-height: 1.1; letter-spacing: 0; }
    h2 { margin: 26px 0 12px; font-size: 20px; letter-spacing: 0; }
    .desc { color: #344054; font-size: 16px; margin: 0 0 22px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .item { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: var(--soft); }
    .label { display: block; color: var(--brand); font-weight: 700; font-size: 13px; margin-bottom: 4px; }
    .value { overflow-wrap: anywhere; }
    .resource-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .resource { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: #fff; }
    .resource strong { display: block; margin-bottom: 4px; }
    .muted { color: var(--muted); }
  </style>
</head>
<body>
  <main>
    <section class="panel">
      <?php if (!$event): ?>
        <h1>Evenement introuvable</h1>
        <p class="desc">Le lien scanne ne correspond a aucun evenement disponible.</p>
      <?php else: ?>
        <h1><?= event_detail_h((string) ($event['name'] ?? '')) ?></h1>
        <p class="desc"><?= nl2br(event_detail_h((string) ($event['description'] ?? ''))) ?></p>

        <div class="grid" aria-label="Informations de l'evenement">
          <div class="item"><span class="label">Date debut</span><span class="value"><?= event_detail_h(event_detail_date((string) ($event['start_date'] ?? ''))) ?></span></div>
          <div class="item"><span class="label">Date fin</span><span class="value"><?= event_detail_h(event_detail_date((string) ($event['end_date'] ?? ''))) ?></span></div>
          <div class="item"><span class="label">Date limite</span><span class="value"><?= event_detail_h(event_detail_date((string) ($event['deadline'] ?? ''))) ?></span></div>
          <div class="item"><span class="label">Lieu</span><span class="value"><?= event_detail_h((string) ($event['location'] ?? '-')) ?></span></div>
          <div class="item"><span class="label">Places disponibles</span><span class="value"><?= $available ?> / <?= $max ?></span></div>
        </div>

        <h2>Materiels</h2>
        <?php if ($materials === []): ?>
          <p class="muted">Aucun materiel renseigne.</p>
        <?php else: ?>
          <ul class="resource-list">
            <?php foreach ($materials as $resource): ?>
              <li class="resource">
                <strong><?= event_detail_h((string) ($resource['name'] ?? '')) ?><?= isset($resource['quantity']) && $resource['quantity'] !== null ? ' - Quantite : ' . (int) $resource['quantity'] : '' ?></strong>
                <span class="muted"><?= event_detail_h((string) ($resource['description'] ?? '')) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <h2>Regles</h2>
        <?php if ($rules === []): ?>
          <p class="muted">Aucune regle renseignee.</p>
        <?php else: ?>
          <ul class="resource-list">
            <?php foreach ($rules as $resource): ?>
              <li class="resource">
                <strong><?= event_detail_h((string) ($resource['name'] ?? '')) ?></strong>
                <span class="muted"><?= event_detail_h((string) ($resource['description'] ?? '')) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
