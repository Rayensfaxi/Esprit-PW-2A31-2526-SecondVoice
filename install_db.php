<?php
require_once 'config.php';

try {
    // We first connect without dbname to create it if it doesn't exist
    $servername = "localhost";
    $username = "root";
    $password = "";
    
    $pdo = new PDO("mysql:host=$servername", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connexion au serveur MySQL réussie...<br>";

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS accompagnement");
    echo "Base de données 'accompagnement' vérifiée/créée.<br>";

    // Reconnect with dbname
    $db = Config::getConnexion();
    echo "Connexion à la base de données réussie.<br>";

    // We drop goals and guides to ensure a clean state (foreign key order matters)
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("DROP TABLE IF EXISTS guides");
    $db->exec("DROP TABLE IF EXISTS goals");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Nettoyage des anciennes tables d'accompagnement effectué.<br>";

    // SQL for utilisateurs (preserved)
    $sqlUtilisateurs = "CREATE TABLE IF NOT EXISTS utilisateurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        prenom VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        mot_de_passe VARCHAR(255) NOT NULL,
        telephone VARCHAR(20) DEFAULT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'user',
        statut_compte ENUM('actif', 'inactif', 'bloque') DEFAULT 'actif',
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sqlUtilisateurs);
    echo "Table 'utilisateurs' vérifiée.<br>";

    // Insert test users if table is empty
    $count = $db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    if ($count == 0) {
        $pass = password_hash("123456", PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Admin', 'SecondVoice', 'admin@secondvoice.com', $pass, 'admin']);
        $stmt->execute(['Assistant', 'Expert', 'assistant@secondvoice.com', $pass, 'assistant']);
        $stmt->execute(['User', 'Citoyen', 'user@secondvoice.com', $pass, 'user']);
        echo "Utilisateurs de test insérés (pass: 123456).<br>";
    }

    // SQL for goals
    $sqlGoals = "CREATE TABLE goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        selected_assistant_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        type ENUM('cv', 'cover_letter', 'linkedin', 'interview', 'other') NOT NULL,
        admin_validation_status ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
        assistant_validation_status ENUM('en_attente', 'accepte', 'refuse') DEFAULT 'en_attente',
        status ENUM('soumis', 'en_cours', 'termine', 'annule') DEFAULT 'soumis',
        priority ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne',
        admin_comment TEXT NULL,
        assistant_comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY (selected_assistant_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
    )";
    $db->exec($sqlGoals);
    echo "Table 'goals' créée avec succès.<br>";

    // SQL for guides
    $sqlGuides = "CREATE TABLE guides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goal_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
    )";
    $db->exec($sqlGuides);
    echo "Table 'guides' créée avec succès.<br>";

    echo "<br><strong style='color: green;'>Installation terminée avec succès !</strong><br>";
    echo "<a href='test-login.php'>Cliquez ici pour tester la connexion</a>";

} catch (PDOException $e) {
    die("<strong style='color: red;'>Erreur d'installation :</strong> " . $e->getMessage());
}
