<?php
require_once dirname(__DIR__, 3) . '/controller/RendezvousC.php';

if (isset($_GET['id'])) {
    $rendezvousC = new RendezvousC();
    $qrPath = $rendezvousC->generateQRCode($_GET['id']);
    
    if ($qrPath) {
        echo json_encode(['success' => true, 'qrPath' => $qrPath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Impossible de générer le QR Code (bibliothèque absente ou erreur serveur).']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID manquant.']);
}
?>
