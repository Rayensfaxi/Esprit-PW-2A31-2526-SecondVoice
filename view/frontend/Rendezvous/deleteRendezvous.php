<?php
require_once '../../../controller/RendezvousC.php';
$rendezvousC = new RendezvousC();

if (isset($_GET['delete'])) {
    $rendezvousC->deleteRendezvous($_GET['delete']);
    header('Location: mes_rendezvous.php?success=Rendez-vous supprimé#rendezvousList');
    exit;
} else {
    header('Location: mes_rendezvous.php#rendezvousList');
    exit;
}
?>
