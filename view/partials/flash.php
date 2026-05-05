<?php
// PHP session-flash renderer.
// Set a flash from any controller/page with:
//   $_SESSION['flash'] = ['type' => 'success'|'error'|'info'|'warning', 'message' => '...'];
// Then include this partial. The flash is consumed (cleared) on first render.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$ph_flash = $_SESSION['flash'] ?? null;
if (!$ph_flash) {
    return;
}
unset($_SESSION['flash']);

$ph_type = isset($ph_flash['type']) && in_array($ph_flash['type'], ['success', 'error', 'info', 'warning'], true)
    ? $ph_flash['type']
    : 'info';
$ph_message = (string) ($ph_flash['message'] ?? '');
$ph_icons   = ['success' => '✅', 'error' => '⚠️', 'info' => 'ℹ️', 'warning' => '⚡'];
$ph_titles  = ['success' => 'Succès', 'error' => 'Erreur', 'info' => 'Information', 'warning' => 'Attention'];
?>
<div class="ph-flash ph-flash-<?= htmlspecialchars($ph_type) ?>" id="phFlash" role="status" aria-live="polite">
  <span class="ph-flash-icon" aria-hidden="true"><?= $ph_icons[$ph_type] ?></span>
  <div class="ph-flash-body">
    <div class="ph-flash-title"><?= htmlspecialchars($ph_titles[$ph_type]) ?></div>
    <div class="ph-flash-msg"><?= htmlspecialchars($ph_message) ?></div>
  </div>
  <button type="button" class="ph-flash-close" aria-label="Fermer" onclick="this.closest('.ph-flash').classList.add('ph-flash-dismissed')">×</button>
  <span class="ph-flash-bar" aria-hidden="true"></span>
</div>

<style>
  .ph-flash {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 99999;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px 14px 18px;
    min-width: 300px;
    max-width: 440px;
    border-radius: 14px;
    font-family: 'Plus Jakarta Sans', 'Outfit', system-ui, sans-serif;
    background: #ffffff;
    box-shadow:
      0 18px 44px rgba(15, 22, 41, .18),
      0 4px 12px rgba(15, 22, 41, .06);
    overflow: hidden;
    will-change: transform, opacity;
    animation:
      ph-flash-in  .55s cubic-bezier(.2,.9,.3,1) both,
      ph-flash-out .45s cubic-bezier(.4,0,1,1) 4.4s forwards;
  }
  .ph-flash.ph-flash-dismissed {
    animation: ph-flash-out .35s cubic-bezier(.4,0,1,1) forwards !important;
  }

  .ph-flash-success { color: #065f46; border: 1px solid #a7f3d0; background: linear-gradient(135deg, #f0fdf6 0%, #ecfdf5 100%); }
  .ph-flash-error   { color: #991b1b; border: 1px solid #fecaca; background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); }
  .ph-flash-info    { color: #1d4e9a; border: 1px solid #cfe0ff; background: linear-gradient(135deg, #f0f4ff 0%, #eff6ff 100%); }
  .ph-flash-warning { color: #92400e; border: 1px solid #fde68a; background: linear-gradient(135deg, #fffaf0 0%, #fffbeb 100%); }

  .ph-flash-icon {
    font-size: 1.35rem;
    line-height: 1;
    flex-shrink: 0;
    margin-top: 2px;
    animation: ph-flash-pop .7s cubic-bezier(.18,1.5,.4,1) both;
  }
  .ph-flash-body  { flex: 1; min-width: 0; }
  .ph-flash-title { font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; opacity: .85; margin-bottom: 2px; }
  .ph-flash-msg   { font-size: .9rem; font-weight: 600; line-height: 1.45; word-wrap: break-word; }

  .ph-flash-close {
    background: transparent;
    border: 0;
    color: inherit;
    font-size: 1.4rem;
    line-height: 1;
    padding: 0 4px;
    margin-top: -2px;
    cursor: pointer;
    opacity: .55;
    transition: opacity .2s, transform .2s;
  }
  .ph-flash-close:hover { opacity: 1; transform: rotate(90deg); }

  .ph-flash-bar {
    position: absolute;
    left: 0;
    bottom: 0;
    height: 3px;
    width: 100%;
    background: currentColor;
    opacity: .35;
    transform-origin: left center;
    animation: ph-flash-progress 4.4s linear forwards;
  }

  @keyframes ph-flash-in {
    0%   { opacity: 0; transform: translate(46px, -10px) scale(.94); }
    60%  { opacity: 1; transform: translate(-4px,  0)    scale(1.015); }
    100% { opacity: 1; transform: translate(0,     0)    scale(1); }
  }
  @keyframes ph-flash-out {
    to { opacity: 0; transform: translate(46px, -10px) scale(.94); pointer-events: none; }
  }
  @keyframes ph-flash-pop {
    0%   { transform: scale(.4) rotate(-12deg); opacity: 0; }
    100% { transform: scale(1)   rotate(0);     opacity: 1; }
  }
  @keyframes ph-flash-progress {
    from { transform: scaleX(1); }
    to   { transform: scaleX(0); }
  }

  @media (max-width: 540px) {
    .ph-flash { top: 14px; left: 14px; right: 14px; max-width: none; min-width: 0; }
  }

  @media (prefers-reduced-motion: reduce) {
    .ph-flash, .ph-flash-bar, .ph-flash-icon { animation: none !important; }
  }
</style>

<script>
  (function () {
    var flash = document.getElementById('phFlash');
    if (!flash) return;
    // Remove from DOM after the auto-dismiss animation finishes
    flash.addEventListener('animationend', function (event) {
      if (event.animationName === 'ph-flash-out' && flash.parentNode) {
        flash.parentNode.removeChild(flash);
      }
    });
  })();
</script>
