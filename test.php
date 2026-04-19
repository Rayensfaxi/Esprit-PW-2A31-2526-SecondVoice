<?php
// Inclure la configuration de la base de données
require_once 'config.php';

// Tester la connexion
try {
    $pdo = Config::getConnexion();
    echo "Connexion à la base de données réussie !";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>