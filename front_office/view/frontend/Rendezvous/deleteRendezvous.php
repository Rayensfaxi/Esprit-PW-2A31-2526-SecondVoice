<?php
require_once '../../../controller/RendezvousC.php';
$rendezvousC = new RendezvousC();

if (isset($_GET['delete'])) {
    $rendezvousC->deleteRendezvous($_GET['delete']);
    header('Location: HomeRendezvous.php?success=Rendez-vous supprimé');
    exit;
} else {
    header('Location: HomeRendezvous.php');
    exit;
}
?>
