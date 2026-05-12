<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controller/EventController.php';

$controller = new EventController();
$eventId = (int) ($_GET['e'] ?? 0);
$token = trim((string) ($_GET['t'] ?? ''));

if ($eventId <= 0 || $token === '' || !hash_equals($controller->getEventQrToken($eventId), $token)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR invalide.';
    exit;
}

$event = $controller->getEventById($eventId);
if (!$event) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Evenement introuvable.';
    exit;
}

header('Location: event-detail.php?id=' . $eventId, true, 302);
exit;

