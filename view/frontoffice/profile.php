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
                null
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
                (string) ($oldUser['telephone'] ?? '') !== (string) ($user['telephone'] ?? '')
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
      .profile-showcase { padding: 24px 0 52px; color: #f7f8ff; }
      .profile-showcase .container { max-width: 1120px; }
      .profile-showcase h1,
      .profile-showcase h2,
      .profile-showcase h3,
      .profile-showcase strong,
      .profile-showcase label,
      .profile-showcase small { color: #f8f7ff; }
      .profile-showcase p,
      .profile-showcase span { color: #d9ddff; }
      .profile-header-box { color: #f7f8ff; border: 1px solid rgba(164,111,255,.48); border-radius: 24px; padding: 28px 30px; background: radial-gradient(circle at 82% 18%, rgba(140,76,255,.24), transparent 44%), linear-gradient(145deg, #181044, #0b0d28); margin-bottom: 18px; box-shadow: 0 18px 46px rgba(2, 4, 18, .22); }
      .profile-header-box h1 { margin: 6px 0 10px; font-size: clamp(2.25rem, 4vw, 3.8rem); line-height: 1.04; color: #fff; }
      .profile-header-box p { color: #eef0ff; }
      .profile-header-box .breadcrumbs,
      .profile-header-box .breadcrumbs span { color: #bfc5ff; }
      .profile-layout { display: grid; grid-template-columns: 320px minmax(0,1fr); gap: 18px; align-items: start; }
      .panel { border: 1px solid rgba(164,111,255,.42); border-radius: 22px; background: linear-gradient(160deg, rgba(36,29,82,.96), rgba(14,14,36,.98)); padding: 22px; color: #f7f8ff; box-shadow: 0 16px 36px rgba(3, 4, 22, .18); }
      .panel:target { border-color: rgba(237,89,185,.72); box-shadow: 0 0 0 1px rgba(237,89,185,.18); }
      .left-stack, .right-stack { display: grid; gap: 16px; }
      .profile-avatar-big { width: 142px; height: 142px; border-radius: 50%; border: 3px solid #9d63ff; margin: 2px auto 14px; background: linear-gradient(135deg, rgba(107,92,255,.38), rgba(237,89,185,.28)); }
      .profile-avatar-big.has-image { background-size: cover; background-position: center; }
      .profile-avatar-big span { display: grid; place-items: center; height: 100%; font-weight: 800; font-size: 2rem; color: #fff; }
      .name-center { text-align: center; }
      .name-center h3 { color: #fff; }
      .name-center p { color: #d8dcff; }
      .badge-role { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 7px 14px; background: linear-gradient(90deg, #6b5cff, #ed59b9); font-weight: 700; color: #fff; }
      .dot-ok { width: 9px; height: 9px; border-radius: 50%; background: #42e888; display: inline-block; margin-right: 8px; }
      .left-menu { margin-top: 20px; border-top: 1px solid rgba(255,255,255,.08); padding-top: 14px; display: grid; gap: 8px; }
      .left-menu a { border: 1px solid rgba(255,255,255,.16); border-radius: 12px; padding: 11px 12px; color: #f3f5ff; background: rgba(255,255,255,.025); font-weight: 700; }
      .left-menu a.active { background: linear-gradient(90deg, #6b5cff, #ed59b9); border-color: transparent; color: #fff; }
      #profile-info-card, #preferences-card, #security-card, #historique-card { scroll-margin-top: 96px; }
      .pref-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 0; }
      .pref-row span { color: #dde1ff; }
      .pref-row strong { color: #fff; text-align: right; }
      .panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
      .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
      .field-label { display: block; margin: 0 0 6px; color: #d8dcff; font-weight: 600; }
      .profile-input { width: 100%; height: 50px; border-radius: 12px; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.055); color: #f7f8ff; padding: 0 14px; font-weight: 650; }
      .profile-input:focus { outline: none; border-color: rgba(237,89,185,.7); box-shadow: 0 0 0 3px rgba(237,89,185,.13); }
      .profile-input.readonly { opacity: 1; background: rgba(255,255,255,.035); color: #dfe3ff; }
      .save-btn { margin-top: 14px; width: 100%; height: 52px; border-radius: 12px; border: 0; font-weight: 800; font-size: 1.12rem; color: #fff; background: linear-gradient(90deg, #6b5cff, #ed59b9); }
      .duo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
      .activity-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
      .activity-list li { display: flex; justify-content: space-between; gap: 12px; }
      .activity-list li::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #a86dff; margin-top: 8px; margin-right: 8px; flex: 0 0 auto; }
      .activity-line { display: flex; justify-content: space-between; align-items: flex-start; width: 100%; border-bottom: 1px solid rgba(255,255,255,.08); padding-bottom: 6px; gap: 14px; }
      .activity-line span:first-child { flex: 1; color: #f1f3ff; line-height: 1.35; }
      .activity-line span:last-child { flex: 0 0 auto; color: #d1d6ff; font-weight: 800; text-align: right; }
      .profile-help,
      .profile-feedback { color: #cfd4ff; }
      .profile-feedback.success { color: #57f49e; }
      .profile-feedback.error { color: #ff7f9d; }
      .profile-showcase .btn.btn-secondary { border: 1px solid rgba(255,255,255,.17); background: rgba(255,255,255,.07); color: #fff; }
      .profile-showcase .btn.btn-secondary:hover { border-color: rgba(237,89,185,.5); background: rgba(237,89,185,.16); }
      .profile-showcase .btn.btn-primary { color: #fff; }
      .stats-panel { padding: 24px; }
      .stats-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
      .stats-head h3 { margin: 0; }
      .stats-head span { color: #b9bee6; font-weight: 700; }
      .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; }
      .stat-item { position: relative; overflow: hidden; min-height: 136px; border: 1px solid rgba(255,255,255,.13); border-radius: 18px; padding: 18px; background: linear-gradient(150deg, rgba(255,255,255,.08), rgba(255,255,255,.025)); box-shadow: inset 0 1px 0 rgba(255,255,255,.08); }
      .stat-item::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--stat-accent, #7c6bff); }
      .stat-item::after { content: ""; position: absolute; top: -34px; right: -34px; width: 92px; height: 92px; border-radius: 50%; background: color-mix(in srgb, var(--stat-accent, #7c6bff) 24%, transparent); }
      .stat-icon { display: inline-grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; color: #fff; background: color-mix(in srgb, var(--stat-accent, #7c6bff) 28%, transparent); border: 1px solid color-mix(in srgb, var(--stat-accent, #7c6bff) 44%, transparent); font-weight: 900; margin-bottom: 12px; }
      .stat-item small { display: block; color: #d8dcff; font-weight: 800; letter-spacing: 0; }
      .stat-item strong { display: block; font-size: 2.35rem; line-height: 1; margin: 12px 0 5px; color: #fff; }
      .stat-item span:not(.stat-icon) { color: #c6caee; font-weight: 700; }
      :root[data-theme="light"] .profile-showcase { color: #221a3d; }
      :root[data-theme="light"] .profile-header-box {
        color: #221a3d;
        border-color: rgba(103, 74, 190, .22);
        background: linear-gradient(145deg, #ffffff, #f3f1ff);
        box-shadow: 0 18px 42px rgba(51, 38, 118, .12);
      }
      :root[data-theme="light"] .panel {
        color: #221a3d;
        border-color: rgba(103, 74, 190, .22);
        background: linear-gradient(160deg, #ffffff, #f7f5ff);
        box-shadow: 0 16px 36px rgba(50, 40, 110, .1);
      }
      :root[data-theme="light"] .profile-showcase h1,
      :root[data-theme="light"] .profile-showcase h2,
      :root[data-theme="light"] .profile-showcase h3,
      :root[data-theme="light"] .profile-showcase strong,
      :root[data-theme="light"] .profile-showcase label,
      :root[data-theme="light"] .profile-showcase small { color: #221a3d; }
      :root[data-theme="light"] .profile-showcase p,
      :root[data-theme="light"] .profile-showcase span { color: #5b5574; }
      :root[data-theme="light"] .profile-header-box h1,
      :root[data-theme="light"] .profile-header-box h3 { color: #211943; }
      :root[data-theme="light"] .profile-header-box p,
      :root[data-theme="light"] .profile-header-box .breadcrumbs,
      :root[data-theme="light"] .profile-header-box .breadcrumbs span { color: #67617c; }
      :root[data-theme="light"] .name-center h3 { color: #211943; }
      :root[data-theme="light"] .name-center p { color: #625b78; }
      :root[data-theme="light"] .badge-role,
      :root[data-theme="light"] .left-menu a.active,
      :root[data-theme="light"] .profile-avatar-big span,
      :root[data-theme="light"] .stat-icon { color: #fff; }
      :root[data-theme="light"] .left-menu { border-top-color: rgba(37, 27, 78, .1); }
      :root[data-theme="light"] .left-menu a {
        color: #312852;
        border-color: rgba(80, 62, 145, .18);
        background: #fbfaff;
      }
      :root[data-theme="light"] .pref-row span { color: #5b5574; }
      :root[data-theme="light"] .pref-row strong { color: #221a3d; }
      :root[data-theme="light"] .field-label { color: #3b315f; }
      :root[data-theme="light"] .profile-input,
      :root[data-theme="light"] #profile-form .profile-input {
        color: #211943;
        border-color: rgba(72, 56, 128, .18);
        background: #ffffff;
      }
      :root[data-theme="light"] .profile-input.readonly,
      :root[data-theme="light"] #profile-form .profile-input.readonly {
        color: #5d5675;
        background: #f4f2fb;
      }
      :root[data-theme="light"] .activity-line {
        border-bottom-color: rgba(37, 27, 78, .1);
      }
      :root[data-theme="light"] .activity-line span:first-child { color: #2b2348; }
      :root[data-theme="light"] .activity-line span:last-child { color: #5d5675; }
      :root[data-theme="light"] .profile-help,
      :root[data-theme="light"] .profile-feedback { color: #5e5875; }
      :root[data-theme="light"] .profile-feedback.success { color: #0b8f50; }
      :root[data-theme="light"] .profile-feedback.error { color: #b4234c; }
      :root[data-theme="light"] .profile-showcase .btn.btn-secondary {
        color: #2b2348;
        border-color: rgba(80, 62, 145, .18);
        background: #f4f1ff;
      }
      :root[data-theme="light"] .stat-item {
        border-color: rgba(80, 62, 145, .14);
        background: linear-gradient(150deg, #ffffff, #f5f2ff);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
      }
      :root[data-theme="light"] .stats-head span,
      :root[data-theme="light"] .stat-item span:not(.stat-icon) { color: #5d5675; }
      :root[data-theme="light"] .stat-item small { color: #3b315f; }
      :root[data-theme="light"] .stat-item strong { color: #211943; }
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
                  <a class="active" href="#profile-info-card" data-profile-nav>Mon profil</a>
                  <a href="#preferences-card" data-profile-nav>Parametres</a>
                  <a href="#historique-card" data-profile-nav>Historique</a>
                  <a href="#security-card" data-profile-nav>Securite</a>
                  <form method="post" action="profile.php" style="margin-top:4px;">
                    <input type="hidden" name="action" value="logout" />
                    <button class="btn btn-primary" type="submit" style="width:100%;">Deconnexion</button>
                  </form>
                </div>
              </div>

              <div class="panel fade-up" id="preferences-card">
                <h3>Parametres</h3>
                <div class="pref-row"><span>Theme</span><strong>Sombre</strong></div>
                <div class="pref-row"><span>Langue</span><strong>Francais</strong></div>
                <div class="pref-row"><span>Historique</span><strong>Actif</strong></div>
              </div>

              <div class="panel fade-up">
                <h3>Acces mobile</h3>
                <p class="profile-help">Scannez ce QR code pour ouvrir le site sur votre telephone.</p>
                <?php if ($mobileUrlIsLocal): ?>
                  <p class="profile-help" style="color:#d64b6a;">Configuration reseau requise pour un acces mobile depuis un autre appareil.</p>
                <?php endif; ?>
                <img src="<?= h($qrCodeUrl) ?>" alt="QR code vers le site SecondVoice" style="width: 100%; max-width: 220px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.15); margin: 10px 0;" />
                <?php if (!$mobileUrlIsLocal): ?>
                  <p class="profile-help" style="word-break: break-all;"><?= h($mobileOpenUrl) ?></p>
                <?php endif; ?>
              </div>
            </aside>

            <div class="right-stack">
              <section class="panel fade-up" id="profile-info-card">
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

                <section class="panel fade-up" id="historique-card">
                  <h3>Historique</h3>
                  <?php if (count($recentActivities) === 0): ?>
                    <p class="profile-help">Aucune entree dans l'historique.</p>
                  <?php else: ?>
                    <ul class="activity-list">
                      <?php foreach ($recentActivities as $activity): ?>
                        <li><div class="activity-line"><span><?= h((string) ($activity['detail'] ?? 'Activite')) ?></span><span><?= h(formatTimeAgo((string) ($activity['at'] ?? ''))) ?></span></div></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </section>
              </div>

              <section class="panel fade-up stats-panel">
                <div class="stats-head">
                  <h3>Statistiques</h3>
                  <span>Vue rapide</span>
                </div>
                <div class="stats-grid">
                  <div class="stat-item" style="--stat-accent:#6b8cff;"><span class="stat-icon">R</span><small>Rendez-vous</small><strong>12</strong><span>Ce mois</span></div>
                  <div class="stat-item" style="--stat-accent:#ffb454;"><span class="stat-icon">!</span><small>Reclamations</small><strong>4</strong><span>Total</span></div>
                  <div class="stat-item" style="--stat-accent:#48ec8f;"><span class="stat-icon">E</span><small>Evenements</small><strong>6</strong><span>Participes</span></div>
                  <div class="stat-item" style="--stat-accent:#ed59b9;"><span class="stat-icon">B</span><small>Brainstormings</small><strong>3</strong><span>Crees</span></div>
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

        const navLinks = Array.from(document.querySelectorAll("[data-profile-nav]"));

        function setActiveNav(hash) {
          navLinks.forEach(function (link) {
            link.classList.toggle("active", link.getAttribute("href") === hash);
          });
        }

        navLinks.forEach(function (link) {
          link.addEventListener("click", function () {
            setActiveNav(link.getAttribute("href") || "#profile-info-card");
          });
        });

        setActiveNav(window.location.hash || "#profile-info-card");
        window.addEventListener("hashchange", function () {
          setActiveNav(window.location.hash || "#profile-info-card");
        });
        <?php if ($mobileUrlIsLocal): ?>
        setTimeout(function () {
          alert("Acces mobile: pour partager le projet sur telephone, configurez une URL publique (IP LAN).");
        }, 250);
        <?php endif; ?>
      })();
    </script>
    <script src="assets/js/main.js"></script>
  </body>
</html>

