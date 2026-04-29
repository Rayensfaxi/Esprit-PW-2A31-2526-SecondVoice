CREATE TABLE IF NOT EXISTS resource_modification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    requested_by INT NOT NULL,
    resources_title VARCHAR(255) DEFAULT NULL,
    resources_description TEXT DEFAULT NULL,
    resources_data LONGTEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    processed_by INT NULL DEFAULT NULL,
    KEY fk_rmr_event (event_id),
    KEY fk_rmr_requested_by (requested_by),
    KEY fk_rmr_processed_by (processed_by),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
