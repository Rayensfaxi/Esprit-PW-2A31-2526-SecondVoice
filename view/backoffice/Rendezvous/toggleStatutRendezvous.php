<?php
require_once '../../../controller/RendezvousC.php';

$rendezvousC = new RendezvousC();

if (isset($_GET['id']) && isset($_GET['action'])) {
    $rdvId = $_GET['id'];
    $action = $_GET['action'];

    $currentRdv = $rendezvousC->getRendezvousById($rdvId);
    if ($currentRdv && $currentRdv->getStatut() == 'Annulé' && ($action == 'confirm' || $action == 'wait')) {
        header('Location: HomeRendezvous.php?error=Impossible de modifier un rendez-vous annulé');
        exit;
    }

    if ($action == 'confirm') {
        $rendezvousC->updateStatut($rdvId, 'Confirmé');
    } elseif ($action == 'wait') {
        $rendezvousC->updateStatut($rdvId, 'En attente');
    }
}

header('Location: HomeRendezvous.php');
exit;
?>
