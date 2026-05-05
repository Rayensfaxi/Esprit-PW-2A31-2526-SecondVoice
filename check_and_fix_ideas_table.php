<?php
require_once __DIR__ . '/config.php';

try {
    $conn = Config::getConnexion();
    
    // Check ideas table structure
    $stmt = $conn->query("DESCRIBE ideas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Current ideas table structure:\n";
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    $columnNames = array_column($columns, 'Field');
    
    // Add missing columns
    if (!in_array('likes_count', $columnNames)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN likes_count INT DEFAULT 0");
        echo "\n✓ Added likes_count column to ideas table\n";
    } else {
        echo "\n✓ likes_count column already exists\n";
    }
    
    if (!in_array('dislikes_count', $columnNames)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN dislikes_count INT DEFAULT 0");
        echo "✓ Added dislikes_count column to ideas table\n";
    } else {
        echo "✓ dislikes_count column already exists\n";
    }
    
    if (!in_array('is_winner', $columnNames)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN is_winner TINYINT(1) DEFAULT 0");
        echo "✓ Added is_winner column to ideas table\n";
    } else {
        echo "✓ is_winner column already exists\n";
    }
    
    // Check if there are any ideas in the table
    $stmt = $conn->query("SELECT COUNT(*) as count FROM ideas");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal ideas in table: " . $count['count'] . "\n";
    
    // Show a sample idea if exists
    if ($count['count'] > 0) {
        $stmt = $conn->query("SELECT id, brainstorming_id, user_id, contenu, date_creation FROM ideas LIMIT 1");
        $idea = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nSample idea:\n";
        echo "ID: " . $idea['id'] . "\n";
        echo "Brainstorming ID: " . $idea['brainstorming_id'] . "\n";
        echo "User ID: " . $idea['user_id'] . "\n";
        echo "Date creation: " . ($idea['date_creation'] ?? 'NULL') . "\n";
        echo "Content: " . substr($idea['contenu'], 0, 50) . "...\n";
    }
    
    echo "\n✅ Check completed!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
