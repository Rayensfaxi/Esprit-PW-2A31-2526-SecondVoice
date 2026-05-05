<?php
require_once __DIR__ . '/config.php';

try {
    $conn = Config::getConnexion();
    
    // Add columns to brainstorming table if they don't exist
    $checkColumns = $conn->query("DESCRIBE brainstorming");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!in_array('vote_start', $columns)) {
        $conn->exec("ALTER TABLE brainstorming ADD COLUMN vote_start DATETIME NULL");
        echo "✓ Added vote_start column to brainstorming table\n";
    }
    
    if (!in_array('vote_end', $columns)) {
        $conn->exec("ALTER TABLE brainstorming ADD COLUMN vote_end DATETIME NULL");
        echo "✓ Added vote_end column to brainstorming table\n";
    }
    
    if (!in_array('vote_status', $columns)) {
        $conn->exec("ALTER TABLE brainstorming ADD COLUMN vote_status VARCHAR(20) DEFAULT 'closed'");
        echo "✓ Added vote_status column to brainstorming table\n";
    }
    
    // Add columns to ideas table if they don't exist
    $checkColumns = $conn->query("DESCRIBE ideas");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!in_array('likes_count', $columns)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN likes_count INT DEFAULT 0");
        echo "✓ Added likes_count column to ideas table\n";
    }

    if (!in_array('dislikes_count', $columns)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN dislikes_count INT DEFAULT 0");
        echo "✓ Added dislikes_count column to ideas table\n";
    }

    if (!in_array('is_winner', $columns)) {
        $conn->exec("ALTER TABLE ideas ADD COLUMN is_winner TINYINT(1) DEFAULT 0");
        echo "✓ Added is_winner column to ideas table\n";
    }

    // Create votes table
    $sql = "CREATE TABLE IF NOT EXISTS votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ideas_id INT NOT NULL,
        type VARCHAR(10) NOT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (user_id, ideas_id),
        FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
        FOREIGN KEY (ideas_id) REFERENCES ideas(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);
    echo "✓ Votes table created successfully\n";
    
    echo "\n✅ Voting system database setup completed!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
