<?php
// Floating role-switcher used to jump between citoyen / admin / assistant pages.
// Detects current page to highlight the active role and resolves relative paths
// whether the including file is in /view/frontoffice or /view/backoffice.

$rs_currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$rs_userPages      = ['mes-accompagnements.php', 'service-accompagnement.php', 'edit-accompagnement.php'];
$rs_adminPages     = ['gestion-accompagnements.php', 'gestion-guides.php', 'gestion-guides-details.php'];
$rs_assistantPages = ['assistant-accompagnements.php', 'assistant-guides.php', 'edit-guide.php'];

$rs_role = '';
if (in_array($rs_currentPage, $rs_userPages, true))           $rs_role = 'user';
elseif (in_array($rs_currentPage, $rs_adminPages, true))      $rs_role = 'admin';
elseif (in_array($rs_currentPage, $rs_assistantPages, true))  $rs_role = 'assistant';

// All three buttons route through test-login.php which auto-sets the session
// role and forwards to the proper landing page. This avoids RBAC redirects
// failing when the project is served from a sub-folder under htdocs.
// Path from /view/{frontoffice|backoffice}/* to project root is ../../.
$rs_loginPath = '../../test-login.php';

$rs_links = [
    'user'      => $rs_loginPath . '?role=user&next='      . rawurlencode('view/frontoffice/mes-accompagnements.php'),
    'admin'     => $rs_loginPath . '?role=admin&next='     . rawurlencode('view/backoffice/gestion-accompagnements.php'),
    'assistant' => $rs_loginPath . '?role=assistant&next=' . rawurlencode('view/frontoffice/assistant-accompagnements.php'),
];
?>
<div class="rs-switcher" id="rsSwitcher" role="navigation" aria-label="Changer de rôle">
  <div class="rs-track">
    <a href="<?= htmlspecialchars($rs_links['user']) ?>" class="rs-pill<?= $rs_role === 'user' ? ' is-active' : '' ?>" data-role="user">
      <span class="rs-icon" aria-hidden="true">👤</span>
      <span class="rs-label">Citoyen</span>
    </a>
    <a href="<?= htmlspecialchars($rs_links['admin']) ?>" class="rs-pill<?= $rs_role === 'admin' ? ' is-active' : '' ?>" data-role="admin">
      <span class="rs-icon" aria-hidden="true">🛡️</span>
      <span class="rs-label">Admin</span>
    </a>
    <a href="<?= htmlspecialchars($rs_links['assistant']) ?>" class="rs-pill<?= $rs_role === 'assistant' ? ' is-active' : '' ?>" data-role="assistant">
      <span class="rs-icon" aria-hidden="true">🎓</span>
      <span class="rs-label">Assistant</span>
    </a>
  </div>
</div>

<div class="rs-page-veil" id="rsPageVeil" aria-hidden="true"></div>

<style>
  .rs-switcher {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99998;
    font-family: 'Plus Jakarta Sans', 'Outfit', system-ui, sans-serif;
    animation: rs-rise .55s cubic-bezier(.2,.9,.3,1) both;
  }
  @keyframes rs-rise {
    from { opacity: 0; transform: translateY(28px) scale(.92); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
  }

  .rs-track {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px;
    background: rgba(255,255,255,0.92);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(80,70,229,.14);
    border-radius: 100px;
    box-shadow:
      0 14px 40px rgba(80,70,229,.20),
      0 2px 10px rgba(0,0,0,.06),
      inset 0 1px 0 rgba(255,255,255,.7);
  }

  .rs-pill {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 100px;
    text-decoration: none;
    font-size: .85rem;
    font-weight: 600;
    color: #6b7a9f;
    letter-spacing: .015em;
    isolation: isolate;
    transition: color .35s cubic-bezier(.2,.9,.3,1),
                transform .35s cubic-bezier(.2,.9,.3,1);
  }

  .rs-pill::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 100px;
    background: linear-gradient(135deg, #5046e5 0%, #7c6ef8 60%, #4cc9f0 120%);
    opacity: 0;
    transform: scale(.85);
    transition: opacity .35s cubic-bezier(.2,.9,.3,1),
                transform .35s cubic-bezier(.2,.9,.3,1);
    z-index: -1;
  }

  .rs-pill:hover {
    color: #5046e5;
    transform: translateY(-1px);
  }

  .rs-pill.is-active {
    color: #fff;
    box-shadow: 0 8px 22px rgba(80,70,229,.42);
    transform: translateY(-1px);
  }
  .rs-pill.is-active::before {
    opacity: 1;
    transform: scale(1);
  }

  .rs-icon { font-size: 1.05rem; line-height: 1; }

  /* Subtle entrance for label on hover */
  .rs-pill .rs-label {
    transition: letter-spacing .35s cubic-bezier(.2,.9,.3,1);
  }
  .rs-pill:hover .rs-label { letter-spacing: .035em; }

  @media (max-width: 540px) {
    .rs-switcher { bottom: 14px; left: 14px; right: 14px; }
    .rs-track    { justify-content: space-between; }
    .rs-label    { display: none; }
    .rs-pill     { padding: 12px 16px; }
  }

  /* Page transition veil shown when navigating */
  .rs-page-veil {
    position: fixed;
    inset: 0;
    background: linear-gradient(135deg, #5046e5 0%, #7c6ef8 60%, #4cc9f0 120%);
    z-index: 99997;
    opacity: 0;
    pointer-events: none;
    transition: opacity .45s cubic-bezier(.2,.9,.3,1);
  }
  .rs-page-veil.is-leaving {
    opacity: 1;
    pointer-events: all;
  }

  /* Soft page-in animation on every load for continuity */
  @keyframes rs-page-in {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  body { animation: rs-page-in .45s cubic-bezier(.2,.9,.3,1) both; }

  @media (prefers-reduced-motion: reduce) {
    .rs-switcher, .rs-pill, .rs-pill::before, .rs-page-veil, body {
      animation: none !important;
      transition: none !important;
    }
  }
</style>

<script>
  (function () {
    const veil = document.getElementById('rsPageVeil');
    const links = document.querySelectorAll('.rs-switcher .rs-pill');

    links.forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (link.classList.contains('is-active')) {
          event.preventDefault();
          return;
        }
        const href = link.getAttribute('href');
        if (!href) return;

        event.preventDefault();
        veil.classList.add('is-leaving');
        // Match the CSS opacity transition (.45s) before navigating
        window.setTimeout(function () { window.location.href = href; }, 400);
      });
    });
  })();
</script>
