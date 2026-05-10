<?php
require_once '../../../controller/RendezvousC.php';

header('Content-Type: application/json');

try {
    $rendezvousC = new RendezvousC();
    $serviceId = isset($_GET['service_id']) && !empty($_GET['service_id']) ? (int)$_GET['service_id'] : null;
    
    $events = $rendezvousC->getCalendarData($serviceId);
    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
