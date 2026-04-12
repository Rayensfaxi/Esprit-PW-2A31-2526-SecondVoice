<?php
require_once '../../../controller/RendezvousC.php';
$rendezvousC = new RendezvousC();

if (isset($_GET['cancel'])) {
    $rendezvousC->updateStatut($_GET['cancel'], 'Annulé');
    header('Location: HomeRendezvous.php?success=Rendez-vous annulé');
    exit;
} else {
    header('Location: HomeRendezvous.php');
    exit;
}
?>
