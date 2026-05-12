-- Structure de la base de données pour le module Accompagnement (Goals et Guides)
--
-- Ce fichier installe UNIQUEMENT les tables du module accompagnement sur la
-- base partagée SecondVoice. Il suppose que la base et la table `utilisateur`
-- (singulier, convention du projet intégré) existent déjà — créées par
-- database/install_secondvoice.sql.
--
-- Pour une installation complète (toutes les tables du projet + ce module),
-- exécuter directement database/install_secondvoice.sql à la place.

-- Table Goals (Objectifs/Demandes d'accompagnement)
CREATE TABLE IF NOT EXISTS goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- L'utilisateur qui fait la demande
    selected_assistant_id INT NOT NULL, -- L'assistant choisi par l'utilisateur
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('cv', 'cover_letter', 'linkedin', 'interview', 'other') NOT NULL,
    
    -- Système de statuts
    admin_validation_status ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    assistant_validation_status ENUM('en_attente', 'accepte', 'refuse') DEFAULT 'en_attente',
    status ENUM('soumis', 'en_cours', 'termine', 'annule') DEFAULT 'soumis',
    priority ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne',
    
    admin_comment TEXT NULL,
    assistant_comment TEXT NULL,

    -- Public share token used by the QR code feature on mes-accompagnements.php
    share_token VARCHAR(64) NULL UNIQUE,

    -- Citizen-driven urgency signal selected from the QR-coded mobile page
    urgency_level ENUM('simple', 'assistance', 'urgent') NULL DEFAULT NULL,
    urgency_updated_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_assistant_id) REFERENCES utilisateur(id) ON DELETE RESTRICT
);

-- Table Guides (Étapes créées par l'assistant pour un Goal)
CREATE TABLE IF NOT EXISTS guides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);

-- Migration for existing databases (run once if goals table already exists)
-- ALTER TABLE goals ADD COLUMN IF NOT EXISTS priority ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne';
-- ALTER TABLE goals ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) NULL UNIQUE;
-- ALTER TABLE goals ADD COLUMN IF NOT EXISTS urgency_level ENUM('simple','assistance','urgent') NULL DEFAULT NULL;
-- ALTER TABLE goals ADD COLUMN IF NOT EXISTS urgency_updated_at TIMESTAMP NULL DEFAULT NULL;
