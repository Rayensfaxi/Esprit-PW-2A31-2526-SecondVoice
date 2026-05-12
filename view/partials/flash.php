<?php
// Shared flash-message renderer.
// Reads $_SESSION['flash'] = ['type' => 'success'|'error'|'info', 'message' => '...'],
// renders a styled banner, then clears the flash so it only appears once.
// Callers set the flash before redirecting; this partial displays it on the next page load.

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
    return;
}

$flashType = (string) ($_SESSION['flash']['type'] ?? 'info');
$flashMessage = (string) ($_SESSION['flash']['message'] ?? '');

unset($_SESSION['flash']);

if ($flashMessage === '') {
    return;
}

$flashColor = match ($flashType) {
    'success' => ['bg' => 'rgba(49,208,170,.12)', 'border' => 'rgba(49,208,170,.3)', 'text' => '#1a9c7c'],
    'error'   => ['bg' => 'rgba(255,107,107,.12)', 'border' => 'rgba(255,107,107,.3)', 'text' => '#cc3333'],
    default   => ['bg' => 'rgba(99,91,255,.12)', 'border' => 'rgba(99,91,255,.3)', 'text' => '#4c39ff'],
};

$flashIcon = match ($flashType) {
    'success' => '✓',
    'error'   => '⚠',
    default   => 'ℹ',
};
?>
<div class="flash-banner flash-banner-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?>" role="status" style="
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  background: <?= $flashColor['bg'] ?>;
  border: 1px solid <?= $flashColor['border'] ?>;
  color: <?= $flashColor['text'] ?>;
  font-weight: 600;
">
  <span style="font-size: 1.1rem;"><?= $flashIcon ?></span>
  <span><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></span>
</div>
