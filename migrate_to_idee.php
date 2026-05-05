<?php
require_once __DIR__ . '/config.php';

try {
    $conn = Config::getConnexion();
    
    echo "Starting migration from 'ideas' to 'idee' table...\n";
    
    // Get all data from ideas table
    $stmt = $conn->query("SELECT * FROM ideas");
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($ideas) . " records in 'ideas' table\n";
    
    // Check if idee table has voting columns
    $checkColumns = $conn->query("DESCRIBE idee");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!in_array('likes_count', $columns)) {
        $conn->exec("ALTER TABLE idee ADD COLUMN likes_count INT DEFAULT 0");
        echo "✓ Added likes_count column to idee table\n";
    }
    
    if (!in_array('dislikes_count', $columns)) {
        $conn->exec("ALTER TABLE idee ADD COLUMN dislikes_count INT DEFAULT 0");
        echo "✓ Added dislikes_count column to idee table\n";
    }
    
    if (!in_array('is_winner', $columns)) {
        $conn->exec("ALTER TABLE idee ADD COLUMN is_winner TINYINT(1) DEFAULT 0");
        echo "✓ Added is_winner column to idee table\n";
    }
    
    // Insert data into idee table
    $inserted = 0;
    foreach ($ideas as $idea) {
        $sql = "INSERT INTO idee (id, brainstorming_id, user_id, contenu, dateCreation, statut, likes_count, dislikes_count, is_winner)
                VALUES (:id, :brainstorming_id, :user_id, :contenu, :dateCreation, :statut, :likes_count, :dislikes_count, :is_winner)
                ON DUPLICATE KEY UPDATE
                brainstorming_id = VALUES(brainstorming_id),
                user_id = VALUES(user_id),
                contenu = VALUES(contenu),
                dateCreation = VALUES(dateCreation),
                statut = VALUES(statut),
                likes_count = VALUES(likes_count),
                dislikes_count = VALUES(dislikes_count),
                is_winner = VALUES(is_winner)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $idea['id'],
            ':brainstorming_id' => $idea['brainstorming_id'],
            ':user_id' => $idea['user_id'],
            ':contenu' => $idea['contenu'],
            ':dateCreation' => $idea['date_creation'],
            ':statut' => $idea['statut'],
            ':likes_count' => $idea['likes_count'] ?? 0,
            ':dislikes_count' => $idea['dislikes_count'] ?? 0,
            ':is_winner' => $idea['is_winner'] ?? 0
        ]);
        $inserted++;
    }
    
    echo "✓ Inserted/Updated $inserted records in 'idee' table\n";
    
    // Verify the data
    $stmt = $conn->query("SELECT COUNT(*) as count FROM idee");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal records in 'idee' table: " . $count['count'] . "\n";
    
    echo "\n✅ Migration completed!\n";
    echo "\nYou can now safely drop the 'ideas' table if desired.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
