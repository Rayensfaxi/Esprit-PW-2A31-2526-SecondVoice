<?php
session_start();
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../controller/GuideController.php";

function redirect_with_notice(string $path, string $type, string $message): void
{
    $separator = (strpos($path, '?') === false) ? '?' : '&';
    $query = http_build_query([
        'type' => $type,
        'msg' => $message,
    ]);
    header('Location: ' . $path . $separator . $query);
    exit;
}

// Ensure admin access
$_role = strtolower((string) ($_SESSION['role'] ?? ''));
$_userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));
$isAdmin = ($_role === 'admin') || ($_userRole === 'admin');
if (!$isAdmin) {
    $next = isset($_GET['id']) ? ('view/backoffice/gestion-guides-details.php?id=' . urlencode((string) $_GET['id'])) : 'view/backoffice/gestion-guides.php';
    $msg = urlencode('Accès réservé aux administrateurs.');
    header('Location: /test-login.php?role=admin&next=' . $next . '&type=error&msg=' . $msg);
    exit;
}

if (!isset($_GET['id'])) {
    redirect_with_notice('gestion-guides.php', 'error', 'ID du guide manquant.');
}

$guideController = new GuideController();
$guide = $guideController->getGuideById((int)$_GET['id']);

if (!$guide) {
    redirect_with_notice('gestion-guides.php', 'error', 'Guide introuvable.');
}

$noticeMessage = trim((string) ($_GET['msg'] ?? ''));
$noticeTypeRaw = strtolower((string) ($_GET['type'] ?? 'info'));
$noticeType = in_array($noticeTypeRaw, ['success', 'error', 'info'], true) ? $noticeTypeRaw : 'info';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails du Guide</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .container { max-width: 800px; margin: auto; padding: 30px; font-family: sans-serif; background:#fff; min-height: 100vh;}
        .btn { padding: 8px 15px; background: #007bff; color: #fff; border: none; border-radius:4px; cursor: pointer; text-decoration: none; display: inline-block; font-weight:bold;}
        .card { border:1px solid #ccc; padding:20px; border-radius:8px; background:#f9f9f9;}
        h1,h2,h3 {color:#222;}
        p {color:#444;}
        .notice {
            border-radius: 10px;
            padding: 11px 14px;
            margin-bottom: 14px;
            border: 1px solid transparent;
            font-weight: 600;
        }
        .notice-success { background: #e9f8ee; border-color: #b9e6c7; color: #1e6a39; }
        .notice-error { background: #fdecee; border-color: #f5c1c7; color: #8b1f2b; }
        .notice-info { background: #edf4ff; border-color: #cfe0ff; color: #1d4e9a; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($noticeMessage !== ''): ?>
            <div class="notice notice-<?= htmlspecialchars($noticeType) ?>"><?= htmlspecialchars($noticeMessage) ?></div>
        <?php endif; ?>
        <a href="gestion-guides.php" class="btn" style="margin-bottom:20px; background:#6c757d;">← Retour à la liste</a>
        <div class="card">
            <h1 style="margin-top:0;">Détails du Guide (lecture seule)</h1>
            <h2>Titre : <?= htmlspecialchars($guide['title']) ?></h2>
            <hr>
            <h3 style="margin-top:20px;">Contenu / Instructions :</h3>
            <div style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:4px; min-height:100px;">
                <?= nl2br(htmlspecialchars($guide['content'])) ?>
            </div>
            <p style="margin-top:20px; font-size:12px; color:#999;">Date de création : <?= htmlspecialchars($guide['created_at']) ?> | Dernière mise à jour : <?= htmlspecialchars($guide['updated_at']) ?></p>
            <p style="margin-top:15px; padding:10px; background:#fff3cd; border:1px solid #ffeaa7; border-radius:4px; color:#856404; font-size:13px;">
                ℹ️ En tant qu'administrateur, vous consultez ce guide en lecture seule. Seul l'assistant responsable peut le modifier.
            </p>
        </div>
    </div>
</body>
</html>
