<?php
require_once '../../../controller/RendezvousC.php';
$rendezvousC = new RendezvousC();

if (isset($_GET['cancel'])) {
    $rendezvousC->updateStatut($_GET['cancel'], 'Annulé');
    header('Location: mes_rendezvous.php?success=Rendez-vous annulé#rendezvousList');
    exit;
} else {
    header('Location: mes_rendezvous.php#rendezvousList');
    exit;
}
?>
