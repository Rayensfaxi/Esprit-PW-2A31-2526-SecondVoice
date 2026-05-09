<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../controller/UtilisateurController.php';
require_once __DIR__ . '/../../controller/ActivityLogger.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getInitials(array $user): string
{
    $nom = (string) ($user['nom'] ?? '');
    $prenom = (string) ($user['prenom'] ?? '');
    $initials = strtoupper(substr(trim($nom), 0, 1) . substr(trim($prenom), 0, 1));
    return $initials !== '' ? $initials : 'SV';
}

function findUserPhotoFile(int $userId, string $photoDir): ?string
{
    $pattern = $photoDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '.*';
    $files = glob($pattern) ?: [];
    return $files[0] ?? null;
}

function deleteUserPhotoFiles(int $userId, string $photoDir): void
{
    $pattern = $photoDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '.*';
    $files = glob($pattern) ?: [];
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function getAdminFaceTemplatePaths(int $userId): array
{
    $safeId = max(1, $userId);
    return [
        __DIR__ . '/../../storage/security/admin_face_' . $safeId . '.json',
        __DIR__ . '/assets/media/security/admin_face_' . $safeId . '.json'
    ];
}

function resetAdminFaceTemplates(int $userId): int
{
    $deleted = 0;
    foreach (getAdminFaceTemplatePaths($userId) as $path) {
        if (is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }
    return $deleted;
}

function formatEventDate(string $isoDate): string
{
    try {
        return (new DateTime($isoDate))->format('d/m/Y');
    } catch (Throwable $exception) {
        return '-';
    }
}

function formatEventTime(string $isoDate): string
{
    try {
        return (new DateTime($isoDate))->format('H:i');
    } catch (Throwable $exception) {
        return '-';
    }
}

function formatTimeAgo(string $isoDate): string
{
    try {
        $date = new DateTime($isoDate);
        $now = new DateTime();
        $diff = max(0, $now->getTimestamp() - $date->getTimestamp());

        if ($diff < 60) {
            return "a l'instant";
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return 'il y a ' . $minutes . ' min';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return 'il y a ' . $hours . ' h';
        }
        if ($diff < 172800) {
            return "hier";
        }
        $days = (int) floor($diff / 86400);
        return 'il y a ' . $days . ' jours';
    } catch (Throwable $exception) {
        return '-';
    }
}

function isLocalWebHost(string $host): bool
{
    $host = strtolower(trim($host, "[] \t\n\r\0\x0B"));
    return $host === 'localhost' || $host === '::1' || preg_match('/^127(?:\.\d{1,3}){3}$/', $host) === 1;
}

function isUsableIpv4(string $ip): bool
{
    $ip = trim($ip);
    if (strpos($ip, ':') !== false && preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches) === 1) {
        $ip = $matches[1];
    }

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
        && preg_match('/^(?:0|127)\./', $ip) !== 1
        && $ip !== '255.255.255.255';
}

function isPrivateIpv4(string $ip): bool
{
    return preg_match('/^(?:10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[0-1])\.)/', $ip) === 1;
}

function detectLanIpv4(): string
{
    $candidates = [];
    $addCandidate = static function ($value) use (&$candidates): void {
        $ip = trim((string) $value);
        if (strpos($ip, ':') !== false && preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches) === 1) {
            $ip = $matches[1];
        }

        if (isUsableIpv4($ip) && !in_array($ip, $candidates, true)) {
            $candidates[] = $ip;
        }
    };

    // On Windows with VirtualBox/VMware adapters, hostname resolution often
    // returns a virtual network IP. The UDP socket exposes the default route IP.
    $socket = @stream_socket_client('udp://8.8.8.8:80', $errno, $error, 0.2);
    if (is_resource($socket)) {
        $localSocketName = stream_socket_get_name($socket, false);
        fclose($socket);
        if (is_string($localSocketName)) {
            $addCandidate($localSocketName);
        }
    }

    foreach (['LOCAL_ADDR', 'SERVER_ADDR'] as $serverKey) {
        $addCandidate($_SERVER[$serverKey] ?? '');
    }

    if (function_exists('gethostname')) {
        $hostname = (string) gethostname();
        if ($hostname !== '') {
            $addCandidate(gethostbyname($hostname));
        }
    }

    foreach ($candidates as $candidate) {
        if (isPrivateIpv4($candidate)) {
            return $candidate;
        }
    }

    return $candidates[0] ?? '';
}

function requestHostName(string $hostHeader): string
{
    return (string) (parse_url('http://' . $hostHeader, PHP_URL_HOST) ?: $hostHeader);
}

function requestHostPort(string $hostHeader, string $scheme): string
{
    $port = parse_url('http://' . $hostHeader, PHP_URL_PORT);
    if ($port === null) {
        $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        if ($serverPort > 0 && !(($scheme === 'http' && $serverPort === 80) || ($scheme === 'https' && $serverPort === 443))) {
            $port = $serverPort;
        }
    }

    return $port !== null ? ':' . (string) $port : '';
}

function encodeUrlPath(string $path): string
{
    $segments = explode('/', $path);
    $segments = array_map(static function (string $segment): string {
        return rawurlencode(rawurldecode($segment));
    }, $segments);

    return implode('/', $segments);
}

function frontofficeBaseUrl(?string &$source = null): string
{
    $forcedPublicBase = '';
    if (class_exists('Config') && method_exists('Config', 'getPublicBaseUrl')) {
        $forcedPublicBase = trim((string) Config::getPublicBaseUrl());
    } else {
        $forcedPublicBase = trim((string) (getenv('SECONDVOICE_PUBLIC_BASE_URL') ?: ''));
    }
    if ($forcedPublicBase !== '') {
        $source = 'configured';
        return rtrim($forcedPublicBase, '/');
    }

    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $hostName = requestHostName($host);
    if (isLocalWebHost($hostName)) {
        $lanIp = detectLanIpv4();
        if ($lanIp !== '') {
            $host = $lanIp . requestHostPort($host, $scheme);
            $source = 'detected_lan';
        } else {
            $source = 'local';
        }
    } else {
        $source = 'request';
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(dirname($script), '/');
    return $scheme . '://' . $host . encodeUrlPath($dir);
}

$controller = new UtilisateurController();
$userId = (int) $_SESSION['user_id'];
$user = $controller->getUserById($userId);

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$photoDir = __DIR__ . '/assets/media/profile-users';
$photoWebDir = 'assets/media/profile-users';
if (!is_dir($photoDir)) {
    mkdir($photoDir, 0775, true);
}

$feedback = '';
$feedbackType = '';

if (isset($_GET['status']) && $_GET['status'] === 'registered') {
    $feedback = 'Compte cree avec succes.';
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'logged_in') {
    $feedback = 'Connexion reussie.';
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'forbidden') {
    $feedback = 'Acces refuse: seuls un administrateur ou un agent peuvent acceder au dashboard.';
    $feedbackType = 'error';
}
if (isset($_GET['status']) && $_GET['status'] === 'face_reset_done') {
    $feedback = "Empreinte faciale admin reinitialisee. Un nouvel enrolement sera demande a la prochaine connexion.";
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'face_reset_forbidden') {
    $feedback = "Action reservee a l'administrateur.";
    $feedbackType = 'error';
}
if (isset($_GET['status']) && $_GET['status'] === 'reset_mail_sent') {
    $feedback = "E-mail de reinitialisation envoye. Verifiez votre boite mail.";
    $feedbackType = 'success';
}
if (isset($_GET['status']) && $_GET['status'] === 'reset_mail_error') {
    $feedback = "Impossible d'envoyer l'e-mail de reinitialisation. Verifiez la configuration mail.";
    $feedbackType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string) ($_POST['action'] ?? 'update')));
    $currentRole = strtolower((string) ($user['role'] ?? 'client'));

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        header('Location: login.php?status=logged_out');
        exit;
    }

    if ($action === 'reset_face_template') {
        try {
            if ($currentRole !== 'admin') {
                header('Location: profile.php?status=face_reset_forbidden');
                exit;
            }

            resetAdminFaceTemplates($userId);
            unset($_SESSION['admin_face_verified_at']);

            // Force immediate re-enrollment flow by converting active admin session
            // into a pending admin verification session.
            $pendingAdmin = [
                'id' => $userId,
                'role' => (string) ($user['role'] ?? 'admin'),
                'nom' => (string) ($user['nom'] ?? ''),
                'prenom' => (string) ($user['prenom'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'allow_face_enroll' => true
            ];

            unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_nom'], $_SESSION['user_prenom'], $_SESSION['user_email']);
            $_SESSION['pending_admin_user'] = $pendingAdmin;

            ActivityLogger::log($userId, 'Securite', "Reinitialisation de l'empreinte faciale admin.");
            header('Location: admin-face-verify.php');
            exit;
        } catch (Throwable $exception) {
            $feedback = $exception->getMessage();
            $feedbackType = 'error';
        }
    } else {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));
        $password = trim((string) ($_POST['mot_de_passe'] ?? ''));
        $removePhoto = ((string) ($_POST['remove_photo'] ?? '0')) === '1';

        try {
            $oldUser = $user;
            $newPhotoTmp = null;
            $newPhotoExtension = null;

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Erreur pendant l\'upload de la photo.');
                }

                $maxSize = 4 * 1024 * 1024;
                if ((int) $_FILES['photo']['size'] > $maxSize) {
                    throw new RuntimeException('Image trop volumineuse (max 4 Mo).');
                }

                $tmpPath = (string) ($_FILES['photo']['tmp_name'] ?? '');
                if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                    throw new RuntimeException('Fichier upload invalide.');
                }

                $mime = (string) (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpPath) ?: '');
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed[$mime])) {
                    throw new RuntimeException('Format image non supporte. Utilisez JPG, PNG ou WEBP.');
                }

                $newPhotoTmp = $tmpPath;
                $newPhotoExtension = $allowed[$mime];
            }

            $controller->updateUser(
                $userId,
                $nom,
                $prenom,
                $email,
                $telephone,
                (string) ($user['role'] ?? 'client'),
                $password !== '' ? $password : null
            );

            if ($removePhoto) {
                deleteUserPhotoFiles($userId, $photoDir);
            }

            if ($newPhotoTmp !== null && $newPhotoExtension !== null) {
                deleteUserPhotoFiles($userId, $photoDir);
                $targetPath = $photoDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '.' . $newPhotoExtension;
                if (!move_uploaded_file($newPhotoTmp, $targetPath)) {
                    throw new RuntimeException('Impossible d\'enregistrer la photo de profil.');
                }
            }

            $user = $controller->getUserById($userId);
            if (!$user) {
                throw new RuntimeException('Utilisateur introuvable apres mise a jour.');
            }

            $_SESSION['user_role'] = (string) ($user['role'] ?? 'client');
            $_SESSION['user_nom'] = (string) ($user['nom'] ?? '');
            $_SESSION['user_prenom'] = (string) ($user['prenom'] ?? '');
            $_SESSION['user_email'] = (string) ($user['email'] ?? '');

            $profileChanged = (
                (string) ($oldUser['nom'] ?? '') !== (string) ($user['nom'] ?? '') ||
                (string) ($oldUser['prenom'] ?? '') !== (string) ($user['prenom'] ?? '') ||
                (string) ($oldUser['email'] ?? '') !== (string) ($user['email'] ?? '') ||
                (string) ($oldUser['telephone'] ?? '') !== (string) ($user['telephone'] ?? '') ||
                $password !== ''
            );

            if ($profileChanged) {
                ActivityLogger::log($userId, 'Profil', 'Mise a jour du profil utilisateur.');
            }

            if ($newPhotoTmp !== null && $newPhotoExtension !== null) {
                ActivityLogger::log($userId, 'Photo', "Ajout d'une photo de profil.");
            } elseif ($removePhoto) {
                ActivityLogger::log($userId, 'Photo', "Suppression de la photo de profil.");
            }

            $feedback = 'Profil mis a jour avec succes.';
            $feedbackType = 'success';
        } catch (Throwable $exception) {
            $feedback = $exception->getMessage();
            $feedbackType = 'error';

            $user['nom'] = $nom;
            $user['prenom'] = $prenom;
            $user['email'] = $email;
            $user['telephone'] = $telephone;
        }
    }
}

$recentActivities = ActivityLogger::getRecent($userId, 5);
$lastActivity = $recentActivities[0] ?? null;

$currentPhotoPath = findUserPhotoFile($userId, $photoDir);
$currentPhotoUrl = '';
if ($currentPhotoPath) {
    $currentPhotoUrl = $photoWebDir . '/' . basename($currentPhotoPath) . '?v=' . (string) @filemtime($currentPhotoPath);
}
$initials = getInitials($user);
$mobileBaseSource = '';
$mobileOpenUrl = frontofficeBaseUrl($mobileBaseSource) . '/index.php';
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($mobileOpenUrl);
$mobileHost = strtolower((string) (parse_url($mobileOpenUrl, PHP_URL_HOST) ?? ''));
$mobileUrlIsLocal = in_array($mobileHost, ['localhost', '127.0.0.1', '::1'], true);
$mobileUrlUsesDetectedLan = $mobileBaseSource === 'detected_lan';
$roleLabel = ucfirst((string) ($user['role'] ?? 'client'));
$statusLabel = ucfirst((string) ($user['statut_compte'] ?? 'actif'));
$joinedRaw = (string) ($user['date_inscription'] ?? $user['created_at'] ?? $user['date_creation'] ?? $user['dateCreation'] ?? '');
$joinedLabel = $joinedRaw !== '' ? formatEventDate($joinedRaw) : '-';
$lastSeenLabel = $lastActivity ? formatTimeAgo((string) ($lastActivity['at'] ?? '')) : '-';
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profil | SecondVoice</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/media/favicon-32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="assets/media/favicon-16.png" />
    <link rel="apple-touch-icon" href="assets/media/apple-touch-icon.png" />
    <link rel="shortcut icon" href="assets/media/favicon.png" />
    <script>
      const savedTheme = localStorage.getItem("theme");
      const initialTheme =
        savedTheme || (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
      document.documentElement.dataset.theme = initialTheme;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= h((string) @filemtime(__DIR__ . '/assets/css/style.css')) ?>" />
    <style>
      .profile-showcase { padding: 24px 0 52px; }
      .profile-header-box { border: 1px solid rgba(164,111,255,.4); border-radius: 24px; padding: 26px 30px; background: radial-gradient(circle at 80% 20%, rgba(140,76,255,.2), transparent 45%), #0b0d28; margin-bottom: 18px; }
      .profile-header-box h1 { margin: 6px 0 10px; font-size: clamp(2rem, 4vw, 3.7rem); line-height: 1.05; }
      .profile-layout { display: grid; grid-template-columns: 320px minmax(0,1fr); gap: 18px; }
      .panel { border: 1px solid rgba(164,111,255,.34); border-radius: 22px; background: linear-gradient(160deg, rgba(29,22,72,.88), rgba(13,13,33,.92)); padding: 22px; }
      .left-stack, .right-stack { display: grid; gap: 16px; }
      .profile-avatar-big { width: 142px; height: 142px; border-radius: 50%; border: 3px solid #9d63ff; margin: 2px auto 14px; }
      .profile-avatar-big.has-image { background-size: cover; background-position: center; }
      .profile-avatar-big span { display: grid; place-items: center; height: 100%; font-weight: 800; font-size: 2rem; }
      .name-center { text-align: center; }
      .badge-role { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 7px 14px; background: linear-gradient(90deg, #6b5cff, #ed59b9); font-weight: 700; }
      .dot-ok { width: 9px; height: 9px; border-radius: 50%; background: #42e888; display: inline-block; margin-right: 8px; }
      .left-menu { margin-top: 20px; border-top: 1px solid rgba(255,255,255,.08); padding-top: 14px; display: grid; gap: 8px; }
      .left-menu a { border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 11px 12px; color: #f3f5ff; }
      .left-menu a.active { background: linear-gradient(90deg, #6b5cff, #ed59b9); border-color: transparent; }
      .pref-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 0; }
      .panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
      .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
      .field-label { display: block; margin: 0 0 6px; color: #d8dcff; font-weight: 600; }
      .profile-input { width: 100%; height: 50px; border-radius: 12px; border: 1px solid rgba(255,255,255,.17); background: rgba(255,255,255,.03); color: #f7f8ff; padding: 0 14px; }
      .profile-input.readonly { opacity: .82; }
      .save-btn { margin-top: 14px; width: 100%; height: 52px; border-radius: 12px; border: 0; font-weight: 800; font-size: 1.12rem; color: #fff; background: linear-gradient(90deg, #6b5cff, #ed59b9); }
      .duo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
      .activity-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
      .activity-list li { display: flex; justify-content: space-between; gap: 12px; }
      .activity-list li::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #a86dff; margin-top: 8px; margin-right: 8px; flex: 0 0 auto; }
      .activity-line { display: flex; justify-content: space-between; width: 100%; border-bottom: 1px solid rgba(255,255,255,.08); padding-bottom: 6px; }
      .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; }
      .stat-item { border: 1px solid rgba(255,255,255,.15); border-radius: 16px; padding: 18px 10px; text-align: center; }
      .stat-item strong { display: block; font-size: 2rem; margin: 8px 0 2px; }
      @media (max-width: 1080px) { .profile-layout, .duo-grid, .form-grid, .stats-grid { grid-template-columns: 1fr; } }

      /* Legacy profile layout remap to match the requested mockup */
      main .section .profile-grid {
        display: grid;
        grid-template-columns: 320px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
      }
      main .section .profile-grid .contact-card {
        order: 2;
        border: 1px solid rgba(164,111,255,.34);
        border-radius: 22px;
        background: linear-gradient(160deg, rgba(29,22,72,.88), rgba(13,13,33,.92));
        padding: 22px;
      }
      main .section .profile-grid .sidebar {
        order: 1;
        display: grid;
        gap: 16px;
      }
      main .section .profile-grid .sidebar-card {
        border: 1px solid rgba(164,111,255,.34);
        border-radius: 22px;
        background: linear-gradient(160deg, rgba(29,22,72,.88), rgba(13,13,33,.92));
        padding: 20px;
      }
      main .section .profile-grid .sidebar-card:first-child {
        text-align: center;
      }
      main .section .profile-grid .sidebar-card:first-child .profile-photo-preview {
        margin: 0 auto 14px;
        width: 142px;
        height: 142px;
        border: 3px solid #9d63ff;
      }
      #profile-form .field,
      #profile-form .profile-input {
        width: 100%;
        height: 50px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.17);
        background: rgba(255,255,255,.03);
        color: #f7f8ff;
        padding: 0 14px;
      }
      #profile-form .input-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
      }
      #profile-form .btn.btn-primary {
        width: 100%;
        height: 52px;
        border: 0;
        border-radius: 12px;
        font-weight: 800;
        font-size: 1.05rem;
        background: linear-gradient(90deg, #6b5cff, #ed59b9);
      }
      @media (max-width: 1080px) {
        main .section .profile-grid {
          grid-template-columns: 1fr;
        }
        main .section .profile-grid .contact-card,
        main .section .profile-grid .sidebar {
          order: unset;
        }
        #profile-form .input-row {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <div class="page-shell">
      <header class="site-header">
        <div class="container nav-inner">
          <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          <button class="menu-toggle" type="button" data-menu-toggle aria-label="Ouvrir le menu">
            <span class="icon-lines"></span>
          </button>
          <div class="nav" data-nav>
            <nav>
              <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="about.php">A propos</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
              </ul>
            </nav>
            <div class="header-actions">
              <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Changer le theme">
                <span class="theme-toggle-label" data-theme-label>Clair</span>
              </button>
              <a class="btn btn-secondary" href="index.php">Accueil</a>
              <form method="post" action="profile.php" style="display:inline;">
                <input type="hidden" name="action" value="logout" />
                <button class="btn btn-primary" type="submit">Deconnexion</button>
              </form>
            </div>
          </div>
        </div>
      </header>
      <main class="profile-showcase">
        <section class="container">
          <div class="profile-header-box fade-up">
            <div class="breadcrumbs"><span>Accueil</span><span>/</span><span>Profil</span></div>
            <h1>Mon profil utilisateur</h1>
            <p>Modifiez vos informations personnelles et vos preferences.</p>
          </div>

          <div class="profile-layout">
            <aside class="left-stack">
              <div class="panel fade-up">
                <div class="profile-avatar-big<?= $currentPhotoUrl !== '' ? ' has-image' : '' ?>" id="profile-photo-preview" <?php if ($currentPhotoUrl !== ''): ?>style="background-image:url('<?= h($currentPhotoUrl) ?>');"<?php endif; ?>>
                  <span id="profile-photo-initials"><?= h($initials) ?></span>
                </div>
                <div class="name-center">
                  <h3 style="margin-bottom:4px;"><?= h(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? '')) ?></h3>
                  <p style="margin-bottom:10px;"><?= h($user['email'] ?? '') ?></p>
                  <span class="badge-role"><?= h($roleLabel) ?></span>
                  <p style="margin-top:12px;"><span class="dot-ok"></span>Compte <?= h(strtolower($statusLabel)) ?></p>
                </div>
                <div class="left-menu">
                  <a class="active" href="#">Mon profil</a>
                  <a href="settings.php">Parametres</a>
                  <a href="#activity-card">Notifications</a>
                  <a href="#security-card">Securite</a>
                  <form method="post" action="profile.php" style="margin-top:4px;">
                    <input type="hidden" name="action" value="logout" />
                    <button class="btn btn-primary" type="submit" style="width:100%;">Deconnexion</button>
                  </form>
                </div>
              </div>

              <div class="panel fade-up">
                <h3>Preferences</h3>
                <div class="pref-row"><span>Theme</span><strong>Sombre</strong></div>
                <div class="pref-row"><span>Langue</span><strong>Francais</strong></div>
                <div class="pref-row"><span>Notifications</span><strong>Activees</strong></div>
              </div>

              <div class="panel fade-up">
                <h3>Acces mobile</h3>
                <p class="profile-help">Scannez ce QR code pour ouvrir le site sur votre telephone.</p>
                <?php if ($mobileUrlIsLocal): ?>
                  <p class="profile-help" style="color:#d64b6a;">URL locale detectee (<?= h($mobileHost) ?>). Configurez SECONDVOICE_PUBLIC_BASE_URL avec l'IP LAN de votre PC.</p>
                <?php endif; ?>
                <img src="<?= h($qrCodeUrl) ?>" alt="QR code vers le site SecondVoice" style="width: 100%; max-width: 220px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.15); margin: 10px 0;" />
                <p class="profile-help" style="word-break: break-all;"><?= h($mobileOpenUrl) ?></p>
              </div>
            </aside>

            <div class="right-stack">
              <section class="panel fade-up">
                <div class="panel-head"><h3 style="margin:0;">Informations personnelles</h3></div>
                <form id="profile-form" method="post" action="profile.php" enctype="multipart/form-data" novalidate>
                  <input type="hidden" name="action" value="update" />
                  <input type="hidden" name="remove_photo" id="remove-photo-flag" value="0" />

                  <div style="margin-bottom:12px;">
                    <label class="btn btn-secondary" for="profile-photo-input">Ajouter/Changer photo</label>
                    <button class="btn btn-secondary" id="profile-photo-clear" type="button">Supprimer photo</button>
                    <input id="profile-photo-input" class="profile-photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" />
                  </div>

                  <div class="form-grid">
                    <div><label class="field-label">Nom</label><input class="profile-input" type="text" name="nom" value="<?= h($user['nom'] ?? '') ?>" /></div>
                    <div><label class="field-label">Prenom</label><input class="profile-input" type="text" name="prenom" value="<?= h($user['prenom'] ?? '') ?>" /></div>
                    <div><label class="field-label">Email</label><input class="profile-input" type="text" name="email" value="<?= h($user['email'] ?? '') ?>" /></div>
                    <div><label class="field-label">Role</label><input class="profile-input readonly" type="text" value="<?= h($roleLabel) ?>" readonly /></div>
                    <div><label class="field-label">Telephone</label><input class="profile-input" type="text" name="telephone" value="<?= h($user['telephone'] ?? '') ?>" /></div>
                    <div><label class="field-label">Date d'inscription</label><input class="profile-input readonly" type="text" value="<?= h($joinedLabel) ?>" readonly /></div>
                    <div><label class="field-label">Localisation</label><input class="profile-input readonly" type="text" value="Tunisie" readonly /></div>
                    <div><label class="field-label">Derniere connexion</label><input class="profile-input readonly" type="text" value="<?= h($lastSeenLabel) ?>" readonly /></div>
                  </div>

                  <div style="margin-top:12px;"><label class="field-label">Nouveau mot de passe (optionnel)</label><input id="new-password-input" class="profile-input" type="password" name="mot_de_passe" /></div>
                  <p id="profile-feedback" class="profile-feedback <?= $feedbackType === 'error' ? 'error' : ($feedbackType === 'success' ? 'success' : '') ?>" style="margin-top:12px;"><?= h($feedback) ?></p>
                  <button class="save-btn" type="submit">Enregistrer les modifications</button>
                </form>
              </section>

              <div class="duo-grid">
                <section class="panel fade-up" id="security-card">
                  <h3>Securite du compte</h3>
                  <div class="pref-row"><span>Reconnaissance faciale</span><strong style="color:#48ec8f;">Activee</strong></div>
                  <div class="pref-row"><span>Derniere connexion</span><strong><?= h($lastSeenLabel) ?></strong></div>
                  <div class="pref-row"><span>Appareil actuel</span><strong>Chrome / Windows</strong></div>
                  <?php if (strtolower((string) ($user['role'] ?? 'client')) === 'admin'): ?>
                    <form method="post" action="profile.php" style="margin-top: 12px;">
                      <input type="hidden" name="action" value="reset_face_template" />
                      <button class="btn btn-secondary" type="submit" onclick="return confirm('Reinitialiser l empreinte faciale admin ?');">Reinitialiser empreinte faciale</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="forgot-password.php" style="margin-top:12px;">
                    <input type="hidden" name="email" value="<?= h((string) ($user['email'] ?? '')) ?>" />
                    <input type="hidden" name="return_to" value="profile.php" />
                    <button class="btn btn-secondary" type="submit">Reinitialiser mot de passe</button>
                  </form>
                </section>

                <section class="panel fade-up" id="activity-card">
                  <h3>Activite recente</h3>
                  <?php if (count($recentActivities) === 0): ?>
                    <p class="profile-help">Aucune activite recente.</p>
                  <?php else: ?>
                    <ul class="activity-list">
                      <?php foreach ($recentActivities as $activity): ?>
                        <li><div class="activity-line"><span><?= h((string) ($activity['detail'] ?? 'Activite')) ?></span><span><?= h(formatTimeAgo((string) ($activity['at'] ?? ''))) ?></span></div></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </section>
              </div>

              <section class="panel fade-up">
                <h3>Statistiques</h3>
                <div class="stats-grid">
                  <div class="stat-item"><small>Rendez-vous</small><strong>12</strong><span>Ce mois</span></div>
                  <div class="stat-item"><small>Reclamations</small><strong>4</strong><span>Total</span></div>
                  <div class="stat-item"><small>Evenements</small><strong>6</strong><span>Participes</span></div>
                  <div class="stat-item"><small>Brainstormings</small><strong>3</strong><span>Crees</span></div>
                </div>
              </section>
            </div>
          </div>
        </section>
      </main>

    </div>

    <script>
      (function () {
        const form = document.getElementById("profile-form");
        const feedback = document.getElementById("profile-feedback");
        const photoInput = document.getElementById("profile-photo-input");
        const photoClear = document.getElementById("profile-photo-clear");
        const photoPreview = document.getElementById("profile-photo-preview");
        const photoInitials = document.getElementById("profile-photo-initials");
        const removeFlag = document.getElementById("remove-photo-flag");
        if (!form) return;

        function showError(message) {
          feedback.textContent = message;
          feedback.classList.add("error");
          feedback.classList.remove("success");
        }

        function clearError() {
          feedback.classList.remove("error");
        }

        function validateImage(file) {
          const allowed = ["image/jpeg", "image/png", "image/webp"];
          const maxSize = 4 * 1024 * 1024;

          if (!allowed.includes(file.type)) {
            return "Format image non supporte. Utilisez JPG, PNG ou WEBP.";
          }

          if (file.size > maxSize) {
            return "Image trop volumineuse (max 4 Mo).";
          }

          return "";
        }

        if (photoInput) {
          photoInput.addEventListener("change", function () {
            const file = photoInput.files && photoInput.files[0];
            if (!file) return;

            const imageError = validateImage(file);
            if (imageError) {
              photoInput.value = "";
              showError(imageError);
              return;
            }

            const reader = new FileReader();
            reader.onload = function () {
              photoPreview.style.backgroundImage = `url('${String(reader.result || "")}')`;
              photoPreview.classList.add("has-image");
              if (photoInitials) {
                photoInitials.textContent = "";
              }
              removeFlag.value = "0";
              clearError();
            };
            reader.readAsDataURL(file);
          });
        }

        if (photoClear) {
          photoClear.addEventListener("click", function () {
            if (photoInput) {
              photoInput.value = "";
            }
            photoPreview.style.backgroundImage = "";
            photoPreview.classList.remove("has-image");
            removeFlag.value = "1";
          });
        }

        form.addEventListener("submit", function (event) {
          const nom = (form.nom.value || "").trim();
          const prenom = (form.prenom.value || "").trim();
          const email = (form.email.value || "").trim();
          const telephone = (form.telephone.value || "").trim().replace(/\s+/g, "");
          const password = form.mot_de_passe.value || "";

          const namePattern = /^[A-Za-zï¿½-ï¿½ï¿½-ï¿½ï¿½-ï¿½\s'-]{2,60}$/;
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          const phonePattern = /^\+?[0-9]{8,15}$/;

          if (!namePattern.test(nom)) {
            event.preventDefault();
            showError("Le nom doit contenir 2 a 60 caracteres alphabetiques.");
            return;
          }

          if (!namePattern.test(prenom)) {
            event.preventDefault();
            showError("Le prenom doit contenir 2 a 60 caracteres alphabetiques.");
            return;
          }

          if (!emailPattern.test(email)) {
            event.preventDefault();
            showError("Adresse e-mail invalide.");
            return;
          }

          if (!phonePattern.test(telephone)) {
            event.preventDefault();
            showError("Le telephone doit contenir entre 8 et 15 chiffres.");
            return;
          }

          if (password.length > 0 && password.length < 6) {
            event.preventDefault();
            showError("Le nouveau mot de passe doit contenir au moins 6 caracteres.");
            return;
          }

          const file = photoInput && photoInput.files ? photoInput.files[0] : null;
          if (file) {
            const imageError = validateImage(file);
            if (imageError) {
              event.preventDefault();
              showError(imageError);
              return;
            }
          }

          clearError();
        });
      })();
    </script>
    <script src="assets/js/main.js"></script>
  </body>
</html>

