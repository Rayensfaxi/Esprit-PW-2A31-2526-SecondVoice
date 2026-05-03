<?php
require_once '../../../controller/ServiceC.php';

$serviceC = new ServiceC();

if (isset($_GET['id'])) {
    try {
        $serviceC->deleteService($_GET['id']);
        header('Location: HomeService.php?success=Service supprimé avec succès.');
    } catch (Exception $e) {
        header('Location: HomeService.php?error=' . $e->getMessage());
    }
} else {
    header('Location: HomeService.php?error=ID non spécifié.');
}
?>
