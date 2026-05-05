<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

try {
    $conn = Config::getConnexion();
    
    echo "Setting up voting system...\n\n";
    
    // Add vote_start and vote_end columns to brainstorming table
    $checkColumns = $conn->query("DESCRIBE brainstorming");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!in_array('vote_start', $columns)) {
        $conn->exec("ALTER TABLE brainstorming ADD COLUMN vote_start DATE NULL");
        echo "✓ Added vote_start column to brainstorming table\n";
    } else {
        echo "✓ vote_start column already exists\n";
    }
    
    if (!in_array('vote_end', $columns)) {
        $conn->exec("ALTER TABLE brainstorming ADD COLUMN vote_end DATE NULL");
        echo "✓ Added vote_end column to brainstorming table\n";
    } else {
        echo "✓ vote_end column already exists\n";
    }
    
    // Add likes, dislikes, is_winner columns to ideas table
    $checkIdeasColumns = $conn->query("DESCRIBE ideas");
    $ideasColumns = $checkIdeasColumns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!in_array('likes', $ideasColumns)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN likes INT DEFAULT 0");
        echo "✓ Added likes column to ideas table\n";
    } else {
        echo "✓ likes column already exists\n";
    }
    
    if (!in_array('dislikes', $ideasColumns)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN dislikes INT DEFAULT 0");
        echo "✓ Added dislikes column to ideas table\n";
    } else {
        echo "✓ dislikes column already exists\n";
    }
    
    if (!in_array('is_winner', $ideasColumns)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN is_winner TINYINT(1) DEFAULT 0");
        echo "✓ Added is_winner column to ideas table\n";
    } else {
        echo "✓ is_winner column already exists\n";
    }
    
    // Create vote table
    $sql = "CREATE TABLE IF NOT EXISTS vote (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        idee_id INT NOT NULL,
        type VARCHAR(10) NOT NULL,
        date_vote DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (user_id, idee_id),
        FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
        FOREIGN KEY (idee_id) REFERENCES ideas(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);
    echo "✓ Vote table created successfully\n";
    
    echo "\n✅ Voting system database setup completed!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
