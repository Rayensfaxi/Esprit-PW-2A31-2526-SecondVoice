CREATE TABLE IF NOT EXISTS event_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    resources_title VARCHAR(255) DEFAULT NULL,
    resources_description TEXT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    quantity INT DEFAULT NULL,
    type ENUM('materiel', 'regle') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY fk_event_resources_event (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
