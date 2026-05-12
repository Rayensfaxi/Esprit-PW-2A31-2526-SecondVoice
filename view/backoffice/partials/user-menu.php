<?php
// Shared back-office user menu (avatar + dropdown with profile info + logout).
// Include this inside a page's `<div class="toolbar-actions">`.
// Expects $_SESSION populated by view/frontoffice/login.php.
// $userMenuLogoutPath: relative path to logout.php from the current page.
//   Defaults to 'logout.php' (same directory). Override BEFORE including for
//   nested pages (e.g. set 'userMenuLogoutPath' => '../logout.php' from
//   view/backoffice/Rendezvous/ or view/backoffice/Service/).
// $userMenuSettingsPath: same idea for settings.php. Default 'settings.php'.

$userMenuLogoutPath   = $userMenuLogoutPath   ?? 'logout.php';
$userMenuSettingsPath = $userMenuSettingsPath ?? 'settings.php';

$userMenuNom    = (string) ($_SESSION['user_nom']    ?? '');
$userMenuPrenom = (string) ($_SESSION['user_prenom'] ?? '');
$userMenuEmail  = (string) ($_SESSION['user_email']  ?? '');
$userMenuRole   = strtolower((string) ($_SESSION['user_role'] ?? 'client'));

$userMenuFullName = trim($userMenuPrenom . ' ' . $userMenuNom);
if ($userMenuFullName === '') {
    $userMenuFullName = 'Utilisateur';
}

$userMenuRoleLabel = match ($userMenuRole) {
    'admin'  => 'Administrateur',
    'agent'  => 'Agent / Assistant',
    default  => 'Client',
};

$userMenuEscape = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="profile-menu-wrap" data-profile-wrap>
  <button class="profile-trigger" data-profile-toggle aria-label="Ouvrir le menu profil" type="button">
    <img class="topbar-avatar" src="<?= $userMenuEscape(str_starts_with($userMenuSettingsPath, '../') ? '../assets/media/profile-avatar.svg' : 'assets/media/profile-avatar.svg') ?>" alt="Profil" />
  </button>
  <div class="profile-dropdown" data-profile-menu>
    <div class="profile-dropdown-card">
      <div class="profile-thumb">
        <img src="<?= $userMenuEscape(str_starts_with($userMenuSettingsPath, '../') ? '../assets/media/profile-avatar.svg' : 'assets/media/profile-avatar.svg') ?>" alt="Avatar utilisateur" />
      </div>
      <div>
        <strong><?= $userMenuEscape($userMenuFullName) ?></strong>
        <span><?= $userMenuEscape($userMenuRoleLabel) ?></span>
      </div>
    </div>
    <?php if ($userMenuEmail !== ''): ?>
      <div class="profile-menu-list">
        <div class="menu-link" style="cursor:default;">
          <span class="menu-icon icon-profile"></span>
          <span><?= $userMenuEscape($userMenuEmail) ?></span>
        </div>
        <a class="menu-link" href="<?= $userMenuEscape($userMenuSettingsPath) ?>">
          <span class="menu-icon icon-settings"></span>
          <span>Parametres</span>
        </a>
      </div>
    <?php else: ?>
      <div class="profile-menu-list">
        <a class="menu-link" href="<?= $userMenuEscape($userMenuSettingsPath) ?>">
          <span class="menu-icon icon-settings"></span>
          <span>Parametres</span>
        </a>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= $userMenuEscape($userMenuLogoutPath) ?>" style="margin:0;">
      <button class="logout-button" type="submit">Deconnexion <span class="logout-arrow">-&gt;</span></button>
    </form>
  </div>
</div>
