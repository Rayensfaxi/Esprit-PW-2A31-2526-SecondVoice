<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../GoalController.php';
require_once __DIR__ . '/../GuideController.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$goalCtrl  = new GoalController();
$guideCtrl = new GuideController();

if (isset($_GET['deleted'])) {
    $_SESSION['flash'] = $_GET['deleted'] === '1'
        ? ['type' => 'success', 'message' => "Demande supprimée avec succès."]
        : ['type' => 'error',   'message' => "Suppression impossible (demande déjà prise en charge)."];
    header('Location: mes-accompagnements.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_goal') {
    $goal_id = (int) $_POST['goal_id'];
    $_SESSION['flash'] = $goalCtrl->deleteGoal($goal_id, $user_id)
        ? ['type' => 'success', 'message' => "Demande supprimée avec succès."]
        : ['type' => 'error',   'message' => "Impossible de supprimer (déjà prise en charge)."];
    header('Location: mes-accompagnements.php');
    exit;
}

$goalFilters = [
    'user_id'          => $user_id,
    'keyword'          => trim((string) ($_GET['g_keyword'] ?? '')),
    'type'             => $_GET['g_type'] ?? '',
    'status'           => $_GET['g_status'] ?? '',
    'admin_status'     => $_GET['g_admin'] ?? '',
    'assistant_status' => $_GET['g_assistant'] ?? '',
    'sort'             => $_GET['g_sort'] ?? 'created_desc',
];
$guideFilters = [
    'user_id'     => $user_id,
    'keyword'     => trim((string) ($_GET['q_keyword'] ?? '')),
    'goal_type'   => $_GET['q_type'] ?? '',
    'goal_status' => $_GET['q_status'] ?? '',
    'sort'        => $_GET['q_sort'] ?? 'created_desc',
];

$goalsHasFilter = !empty($goalFilters['keyword']) || !empty($goalFilters['type']) || !empty($goalFilters['status'])
    || !empty($goalFilters['admin_status']) || !empty($goalFilters['assistant_status'])
    || ($goalFilters['sort'] !== 'created_desc');
$guidesHasFilter = !empty($guideFilters['keyword']) || !empty($guideFilters['goal_type']) || !empty($guideFilters['goal_status'])
    || ($guideFilters['sort'] !== 'created_desc');

try {
    $my_goals  = $goalCtrl->searchGoals($goalFilters);
    $my_guides = $guideCtrl->searchGuides($guideFilters);
} catch (Exception $e) {
    $my_goals = [];
    $my_guides = [];
}

$shareScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$shareHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$shareDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');
$hostNoPort = preg_replace('/:\d+$/', '', $shareHost);
if (in_array(strtolower($hostNoPort), ['localhost', '127.0.0.1', '::1'], true)) {
    $lanIp = null;
    $sock = @stream_socket_client('udp://8.8.8.8:53', $eno, $estr, 1);
    if ($sock) {
        $name = @stream_socket_get_name($sock, false);
        @fclose($sock);
        if ($name && preg_match('/^(.+):\d+$/', $name, $m)) {
            $candidate = $m[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, ['127.0.0.1', '0.0.0.0'], true)) {
                $lanIp = $candidate;
            }
        }
    }
    if (!$lanIp) {
        $candidate = @gethostbyname(@gethostname());
        if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, ['127.0.0.1', '0.0.0.0'], true)) {
            $lanIp = $candidate;
        }
    }
    if ($lanIp) {
        $port = '';
        if (preg_match('/(:\d+)$/', $shareHost, $m)) {
            $port = $m[1];
        }
        $shareHost = $lanIp . $port;
    }
}
$shareBase = $shareScheme . '://' . $shareHost . $shareDir . '/signal-besoin.php?token=';

$qrTunnelFile = __DIR__ . '/../../qr_tunnel.txt';
if (is_readable($qrTunnelFile)) {
    $override = trim((string) @file_get_contents($qrTunnelFile));
    if ($override !== '' && preg_match('#^https?://[^\s]+#i', $override)) {
        $parts = parse_url($override);
        if ($parts && !empty($parts['scheme']) && !empty($parts['host'])) {
            $rebuilt = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $rebuilt .= ':' . $parts['port'];
            }
            if (!empty($parts['path'])) {
                $segments = explode('/', $parts['path']);
                $encoded = array_map(static function ($s) {
                    if ($s === '') return '';
                    if (preg_match('/%[0-9A-Fa-f]{2}/', $s)) return $s;
                    return rawurlencode($s);
                }, $segments);
                $rebuilt .= implode('/', $encoded);
            }
            $shareBase = rtrim($rebuilt, '/') . '/view/frontoffice/signal-besoin.php?token=';
        }
    }
}

$urgencyMap = [
    'simple'     => ['label' => '🟢 Simple',         'class' => 'urgency-simple'],
    'assistance' => ['label' => "🟡 Besoin d'aide",  'class' => 'urgency-assistance'],
    'urgent'     => ['label' => '🔴 Urgent',          'class' => 'urgency-urgent'],
];

$statusMap = [
    'soumis'    => ['label' => 'Soumis',      'class' => 'status-pending',  'icon' => '🕐'],
    'en_cours'  => ['label' => 'En cours',    'class' => 'status-active',   'icon' => '⚡'],
    'termine'   => ['label' => 'Terminé',     'class' => 'status-done',     'icon' => '✅'],
    'annule'    => ['label' => 'Annulé',      'class' => 'status-refused',  'icon' => '❌'],
];
$adminMap = [
    'en_attente' => ['label' => 'En attente admin',   'class' => 'tag-warning'],
    'valide'     => ['label' => 'Validé par admin',   'class' => 'tag-success'],
    'refuse'     => ['label' => 'Refusé par admin',   'class' => 'tag-danger'],
];
$assistantMap = [
    'en_attente' => ['label' => 'En attente assistant',   'class' => 'tag-info'],
    'accepte'    => ['label' => 'Accepté',                'class' => 'tag-success'],
    'refuse'     => ['label' => 'Refusé par assistant',   'class' => 'tag-danger'],
];
$typeLabels = [
    'cv'           => '📄 CV',
    'cover_letter' => '✉️ Lettre',
    'linkedin'     => '🔗 LinkedIn',
    'interview'    => '🎤 Entretien',
    'other'        => '📌 Autre',
];

