<?php
require_once __DIR__ . '/config.php';

try {
    $conn = Config::getConnexion();
    
    // Create ideas table
    $sql = "CREATE TABLE IF NOT EXISTS ideas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        brainstorming_id INT NOT NULL,
        user_id INT NOT NULL,
        contenu TEXT NOT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut VARCHAR(20) DEFAULT 'en attente',
        FOREIGN KEY (brainstorming_id) REFERENCES brainstorming(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);
    echo "Table 'ideas' created successfully.\n";
    
    // Check if the table was created
    $stmt = $conn->query("DESCRIBE ideas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTable structure:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
