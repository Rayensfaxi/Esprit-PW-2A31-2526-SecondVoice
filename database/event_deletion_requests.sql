-- Table pour les demandes de suppression d'événements
-- Créée le 18/04/2026

CREATE TABLE IF NOT EXISTS event_deletion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NULL,
    user_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    processed_by INT NULL,
    event_name_snapshot VARCHAR(255) NULL,
    event_description_snapshot TEXT NULL,
    event_start_date_snapshot DATETIME NULL,
    event_end_date_snapshot DATETIME NULL,
    event_location_snapshot VARCHAR(255) NULL,
    event_status_snapshot VARCHAR(50) NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour accélérer les recherches
CREATE INDEX idx_event_deletion_status ON event_deletion_requests(status);
CREATE INDEX idx_event_deletion_event ON event_deletion_requests(event_id);
