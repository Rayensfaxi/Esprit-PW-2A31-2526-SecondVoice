<?php
require_once '../../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();

if (isset($_GET['id'])) {
    $rendezvousC->deleteRendezvous($_GET['id']);
}

header('Location: HomeRendezvous.php');
exit;
?>
