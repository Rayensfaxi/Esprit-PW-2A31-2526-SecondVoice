-- Table pour les demandes de modification d'événements
-- Structure minimale pour le système de demandes de modification

CREATE TABLE IF NOT EXISTS event_modification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    requested_by INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    
    -- Champs de modification demandés
    new_name VARCHAR(255) NULL,
    new_description TEXT NULL,
    new_start_date DATETIME NULL,
    new_end_date DATETIME NULL,
    new_deadline DATETIME NULL,
    new_location VARCHAR(255) NULL,
    new_max INT NULL,
    
    INDEX idx_event_id (event_id),
    INDEX idx_requested_by (requested_by),
    INDEX idx_status (status),
    
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
