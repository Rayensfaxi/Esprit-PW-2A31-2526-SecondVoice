<?php
require_once '../../../controller/ServiceC.php';

$serviceC = new ServiceC();

if (isset($_POST['id']) && isset($_POST['nom']) && isset($_POST['description'])) {
    $id = $_POST['id'];
    $nom = $_POST['nom'];
    $description = $_POST['description'];

    // Validation PHP
    if (empty(trim($nom))) {
        header('Location: HomeService.php?error=Le nom du service est obligatoire.');
        exit;
    }
    if (strlen($nom) < 3) {
        header('Location: HomeService.php?error=Le nom doit contenir au moins 3 caractères.');
        exit;
    }
    if (empty(trim($description))) {
        header('Location: HomeService.php?error=La description est obligatoire.');
        exit;
    }
    if (strlen($description) < 10) {
        header('Location: HomeService.php?error=La description doit contenir au moins 10 caractères.');
        exit;
    }

    $service = new Service((int)$id, $nom, $description);
    
    try {
        $serviceC->updateService($service, (int)$id);
        header('Location: HomeService.php?success=Service modifié avec succès.');
    } catch (Exception $e) {
        header('Location: HomeService.php?error=' . $e->getMessage());
    }
} else {
    header('Location: HomeService.php?error=Tous les champs sont obligatoires.');
}
?>
