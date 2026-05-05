<?php
require_once __DIR__ . '/config.php';

try {
    $conn = Config::getConnexion();
    
    // Check if idee table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'idee'");
    $ideeExists = $stmt->fetch();
    
    // Check if ideas table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'ideas'");
    $ideasExists = $stmt->fetch();
    
    echo "Table 'idee' exists: " . ($ideeExists ? 'YES' : 'NO') . "\n";
    echo "Table 'ideas' exists: " . ($ideasExists ? 'YES' : 'NO') . "\n";
    
    if ($ideeExists) {
        echo "\nStructure of 'idee' table:\n";
        $stmt = $conn->query("DESCRIBE idee");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM idee");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nTotal idee in table: " . $count['count'] . "\n";
    }
    
    if ($ideasExists) {
        echo "\n\nStructure of 'ideas' table:\n";
        $stmt = $conn->query("DESCRIBE ideas");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM ideas");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nTotal ideas in table: " . $count['count'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
