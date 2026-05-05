<?php
/**
 * Static SVG pie chart (no JS dependency).
 *
 * Caller sets these variables BEFORE including this partial:
 *   $pieTitle  string         Heading for the chart card.
 *   $pieData   array<array{label:string,value:int,color:string}>
 *   $pieTone   'light'|'dark' (optional, default 'light') Picks the centre-disk + text colors.
 */
$pieTitle = $pieTitle ?? 'Répartition';
$pieData  = $pieData  ?? [];
$pieTone  = $pieTone  ?? 'light';

$pieTotal = 0;
foreach ($pieData as $d) { $pieTotal += (int)$d['value']; }

$pieCx = 100; $pieCy = 100; $pieR = 92;
$pieSlices = [];
$pieStart = -90.0;

if ($pieTotal > 0) {
    foreach ($pieData as $d) {
        $val = (int)$d['value'];
        if ($val <= 0) { continue; }
        $portion = $val / $pieTotal;
        $sweep   = $portion * 360.0;
        $end     = $pieStart + $sweep;
        $largeArc = ($sweep > 180) ? 1 : 0;
        if ($portion >= 0.9999) {
            $pathD = "M {$pieCx} " . ($pieCy - $pieR) . " A {$pieR} {$pieR} 0 1 1 " . ($pieCx - 0.01) . " " . ($pieCy - $pieR) . " Z";
        } else {
            $sx = $pieCx + $pieR * cos(deg2rad($pieStart));
            $sy = $pieCy + $pieR * sin(deg2rad($pieStart));
            $ex = $pieCx + $pieR * cos(deg2rad($end));
            $ey = $pieCy + $pieR * sin(deg2rad($end));
            $pathD = sprintf("M %.3f %.3f L %.3f %.3f A %d %d 0 %d 1 %.3f %.3f Z", $pieCx, $pieCy, $sx, $sy, $pieR, $pieR, $largeArc, $ex, $ey);
        }
        $pieSlices[] = [
            'path'  => $pathD,
            'color' => $d['color'],
            'label' => $d['label'],
            'value' => $val,
            'pct'   => $portion * 100,
        ];
        $pieStart = $end;
    }
}

$pieIsDark   = $pieTone === 'dark';
$pieHubFill  = $pieIsDark ? '#0f1629' : '#ffffff';
$pieTextMain = $pieIsDark ? '#ffffff' : '#0f1629';
$pieTextMute = $pieIsDark ? '#a5b4fc' : '#6b7a9f';
?>
<div class="pie-card pie-card-<?= $pieIsDark ? 'dark' : 'light' ?>">
  <div class="pie-card-title"><?= htmlspecialchars($pieTitle) ?></div>

  <?php if ($pieTotal === 0): ?>
    <div class="pie-empty">Aucune donnée à afficher pour le moment.</div>
  <?php else: ?>
    <div class="pie-wrapper">
      <div class="pie-svg">
        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?= htmlspecialchars($pieTitle) ?>">
          <?php foreach ($pieSlices as $s): ?>
            <path d="<?= $s['path'] ?>" fill="<?= htmlspecialchars($s['color']) ?>" stroke="<?= $pieHubFill ?>" stroke-width="2" />
          <?php endforeach; ?>
          <circle cx="<?= $pieCx ?>" cy="<?= $pieCy ?>" r="44" fill="<?= $pieHubFill ?>" />
          <text x="<?= $pieCx ?>" y="<?= $pieCy - 6 ?>" text-anchor="middle" font-size="11" font-weight="700" fill="<?= $pieTextMute ?>" letter-spacing="1.4">TOTAL</text>
          <text x="<?= $pieCx ?>" y="<?= $pieCy + 18 ?>" text-anchor="middle" font-size="22" font-weight="800" fill="<?= $pieTextMain ?>"><?= $pieTotal ?></text>
        </svg>
      </div>
      <div class="pie-legend">
        <?php foreach ($pieSlices as $s): ?>
          <div class="pie-row">
            <span class="pie-dot" style="background: <?= htmlspecialchars($s['color']) ?>"></span>
            <span class="pie-label"><?= htmlspecialchars($s['label']) ?></span>
            <span class="pie-val"><?= $s['value'] ?> · <?= number_format($s['pct'], 1) ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<style>
  .pie-card {
    border-radius: 16px;
    padding: 18px 22px;
    margin-bottom: 22px;
  }
  .pie-card-light {
    background: #ffffff;
    border: 1px solid #e2e8f8;
    box-shadow: 0 4px 24px rgba(80,70,229,0.08);
    color: #0f1629;
  }
  .pie-card-dark {
    background: rgba(10, 16, 34, .62);
    border: 1px solid rgba(255,255,255,.09);
    color: #eef3ff;
  }
  .pie-card-title {
    font-size: .73rem; font-weight: 700; letter-spacing: .07em;
    text-transform: uppercase;
    margin-bottom: 14px;
    opacity: .8;
  }
  .pie-wrapper {
    display: flex;
    align-items: center;
    gap: 28px;
    flex-wrap: wrap;
  }
  .pie-svg svg { width: 200px; height: 200px; display: block; }
  .pie-legend {
    flex: 1;
    min-width: 220px;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .pie-row {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 0;
    font-size: .9rem;
    border-bottom: 1px dashed currentColor;
    opacity: .95;
  }
  .pie-card .pie-row { border-bottom-color: currentColor; }
  .pie-card-light .pie-row { border-bottom-color: rgba(15,22,41,.08); }
  .pie-card-dark .pie-row  { border-bottom-color: rgba(255,255,255,.08); }
  .pie-row:last-child { border-bottom: none; }
  .pie-dot {
    width: 12px; height: 12px;
    border-radius: 3px;
    flex-shrink: 0;
    box-shadow: 0 0 0 2px rgba(255,255,255,.25);
  }
  .pie-label { flex: 1; font-weight: 600; }
  .pie-val {
    font-weight: 700;
    font-size: .82rem;
    opacity: .8;
    white-space: nowrap;
  }
  .pie-empty {
    padding: 24px;
    text-align: center;
    font-size: .9rem;
    opacity: .65;
  }
  @media (max-width: 540px) {
    .pie-wrapper { gap: 16px; flex-direction: column; align-items: stretch; }
    .pie-svg { align-self: center; }
  }
</style>
