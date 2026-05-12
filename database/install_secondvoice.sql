-- =============================================================================
-- SecondVoice — shared database install script
-- =============================================================================
-- Builds the full `secondvoice` database for the SecondVoice-Integration branch,
-- including Chaima's accompagnement module (goals + guides).
--
-- Tables this script creates are the ones the app does NOT auto-create.
-- Tables that controllers create at runtime via ensureSchema() are listed at
-- the bottom of this file for reference only — they appear on first access.
--
-- Usage (from MySQL CLI or phpMyAdmin SQL tab):
--   mysql -u root < database/install_secondvoice.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS secondvoice
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE secondvoice;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. utilisateur  (users — base table, referenced by almost everything)
-- -----------------------------------------------------------------------------
-- Columns derived from controller/UtilisateurController.php INSERT/SELECT.
-- email_verifie is added by ensureAuthSchema() at runtime if missing.
CREATE TABLE IF NOT EXISTS utilisateur (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(255) NOT NULL,
    prenom          VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255) NOT NULL,
    telephone       VARCHAR(50)  DEFAULT NULL,
    role            VARCHAR(50)  NOT NULL DEFAULT 'client',  -- admin | agent | client
    statut_compte   VARCHAR(50)  NOT NULL DEFAULT 'actif',   -- actif | bloque | en_pause
    date_creation   DATE         NULL,
    email_verifie   TINYINT(1)   NOT NULL DEFAULT 1,
    INDEX idx_utilisateur_email (email),
    INDEX idx_utilisateur_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. brainstorming  (community ideation campaigns)
-- -----------------------------------------------------------------------------
-- Base columns. user_id / vote_start / vote_end are added at runtime by
-- BrainstormingController::ensureSchema() if missing, but we include them here.
CREATE TABLE IF NOT EXISTS brainstorming (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    titre           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    categorie       VARCHAR(100) NULL,
    dateCreation    DATE NULL,
    statut          VARCHAR(30)  NOT NULL DEFAULT 'en attente',  -- en attente | approuve | desapprouve
    user_id         INT NULL,
    vote_start      DATETIME NULL,
    vote_end        DATETIME NULL,
    INDEX idx_brainstorming_user (user_id),
    INDEX idx_brainstorming_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. service  (citizen-facing service catalog, referenced by rendezvous)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(255) NOT NULL,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. rendezvous  (citizen appointments tied to services)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rendezvous (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    id_citoyen  INT NOT NULL,
    service_id  INT NULL,
    assistant   VARCHAR(255) NULL,
    date_rdv    DATE NOT NULL,
    heure_rdv   TIME NOT NULL,
    mode        VARCHAR(50)  NULL,
    remarques   TEXT NULL,
    statut      VARCHAR(50)  NOT NULL DEFAULT 'En attente',
    INDEX idx_rendezvous_citoyen (id_citoyen),
    INDEX idx_rendezvous_service (service_id),
    INDEX idx_rendezvous_date    (date_rdv),
    FOREIGN KEY (service_id) REFERENCES service(id) ON DELETE SET NULL,
    FOREIGN KEY (id_citoyen) REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. events  (event module — base table)
-- -----------------------------------------------------------------------------
-- created_by added via database_alter_created_by.sql; merged here.
CREATE TABLE IF NOT EXISTS events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_date  DATETIME NULL,
    end_date    DATETIME NULL,
    deadline    DATETIME NULL,
    location    VARCHAR(255) NULL,
    `max`       INT NOT NULL DEFAULT 0,
    `current`   INT NOT NULL DEFAULT 0,
    status      VARCHAR(30) NOT NULL DEFAULT 'en cours',   -- en cours | validé | refusé | annulé
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_status     (status),
    INDEX idx_events_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. registrations  (event registrations — referenced by EventController)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    event_id    INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_registration_user_event (user_id, event_id),
    INDEX idx_registrations_event (event_id),
    FOREIGN KEY (user_id)  REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. event_resources  (materiel / regle resources attached to events)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_resources (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    event_id                INT NOT NULL,
    resources_title         VARCHAR(255) DEFAULT NULL,
    resources_description   TEXT DEFAULT NULL,
    name                    VARCHAR(255) NOT NULL,
    description             TEXT DEFAULT NULL,
    quantity                INT DEFAULT NULL,
    type                    ENUM('materiel', 'regle') NOT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY fk_event_resources_event (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. event_modification_requests  (proposed event changes awaiting moderation)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_modification_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_id        INT NOT NULL,
    requested_by    INT NOT NULL,
    status          VARCHAR(50) NOT NULL DEFAULT 'pending',
    requested_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at    TIMESTAMP NULL,
    processed_by    INT NULL,
    new_name        VARCHAR(255) NULL,
    new_description TEXT NULL,
    new_start_date  DATETIME NULL,
    new_end_date    DATETIME NULL,
    new_deadline    DATETIME NULL,
    new_location    VARCHAR(255) NULL,
    new_max         INT NULL,
    INDEX idx_emr_event       (event_id),
    INDEX idx_emr_requested_by(requested_by),
    INDEX idx_emr_status      (status),
    FOREIGN KEY (event_id)     REFERENCES events(id)      ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. event_deletion_requests  (proposed event deletions awaiting moderation)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_deletion_requests (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    event_id                    INT NULL,
    user_id                     INT NOT NULL,
    status                      VARCHAR(20) DEFAULT 'pending',
    requested_at                DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at                DATETIME NULL,
    processed_by                INT NULL,
    event_name_snapshot         VARCHAR(255) NULL,
    event_description_snapshot  TEXT NULL,
    event_start_date_snapshot   DATETIME NULL,
    event_end_date_snapshot     DATETIME NULL,
    event_location_snapshot     VARCHAR(255) NULL,
    event_status_snapshot       VARCHAR(50) NULL,
    INDEX idx_edr_status (status),
    INDEX idx_edr_event  (event_id),
    FOREIGN KEY (event_id)     REFERENCES events(id)      ON DELETE SET NULL,
    FOREIGN KEY (user_id)      REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. resource_modification_requests
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resource_modification_requests (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    event_id                INT NOT NULL,
    requested_by            INT NOT NULL,
    request_type            VARCHAR(50) NOT NULL DEFAULT 'modification ressources',
    resources_title         VARCHAR(255) DEFAULT NULL,
    resources_description   TEXT DEFAULT NULL,
    resources_data          LONGTEXT NOT NULL,
    status                  ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at            TIMESTAMP NULL DEFAULT NULL,
    processed_by            INT NULL DEFAULT NULL,
    KEY fk_rmr_event         (event_id),
    KEY fk_rmr_requested_by  (requested_by),
    KEY fk_rmr_processed_by  (processed_by),
    FOREIGN KEY (event_id)     REFERENCES events(id)      ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ACCOMPAGNEMENT MODULE (Chaima) — adapted to use utilisateur(id) not utilisateurs(id)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 11. goals  (citizen accompagnement requests)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS goals (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    user_id                     INT NOT NULL,
    selected_assistant_id       INT NOT NULL,
    title                       VARCHAR(255) NOT NULL,
    description                 TEXT NOT NULL,
    type                        ENUM('cv', 'cover_letter', 'linkedin', 'interview', 'other') NOT NULL,
    admin_validation_status     ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    assistant_validation_status ENUM('en_attente', 'accepte', 'refuse') DEFAULT 'en_attente',
    status                      ENUM('soumis', 'en_cours', 'termine', 'annule') DEFAULT 'soumis',
    priority                    ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne',
    admin_comment               TEXT NULL,
    assistant_comment           TEXT NULL,
    share_token                 VARCHAR(64) NULL UNIQUE,
    urgency_level               ENUM('simple', 'assistance', 'urgent') NULL DEFAULT NULL,
    urgency_updated_at          TIMESTAMP NULL DEFAULT NULL,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_goals_user      (user_id),
    INDEX idx_goals_assistant (selected_assistant_id),
    INDEX idx_goals_status    (status),
    FOREIGN KEY (user_id)               REFERENCES utilisateur(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_assistant_id) REFERENCES utilisateur(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. guides  (steps written by an assistant for a goal)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guides (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    goal_id     INT NOT NULL,
    title       VARCHAR(255) NOT NULL,
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_guides_goal (goal_id),
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SEED DATA — minimum to log in and test
-- =============================================================================
-- Password for all 3 seed accounts is "Test1234!" (PASSWORD_DEFAULT bcrypt hash).
-- Generated once with: php -r "echo password_hash('Test1234!', PASSWORD_DEFAULT);"
-- Re-generate if you need a different password.
INSERT INTO utilisateur (id, nom, prenom, email, mot_de_passe, telephone, role, statut_compte, date_creation, email_verifie)
VALUES
    (1, 'Admin',     'SecondVoice', 'admin@secondvoice.local',     '$2y$12$495Pmmp.2Wbt.9sJfOjcNOgqSE1KAeNhloC.hRhZiXpLn19/zCOd.', '00000000', 'admin',  'actif', CURDATE(), 1),
    (2, 'Assistant', 'Demo',        'assistant@secondvoice.local', '$2y$12$495Pmmp.2Wbt.9sJfOjcNOgqSE1KAeNhloC.hRhZiXpLn19/zCOd.', '00000000', 'agent',  'actif', CURDATE(), 1),
    (3, 'Citoyen',   'Demo',        'citoyen@secondvoice.local',   '$2y$12$495Pmmp.2Wbt.9sJfOjcNOgqSE1KAeNhloC.hRhZiXpLn19/zCOd.', '00000000', 'client', 'actif', CURDATE(), 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO service (nom, description) VALUES
    ('Accompagnement administratif', 'Aide aux démarches administratives'),
    ('Support utilisateur',           'Assistance pour l''utilisation de la plateforme'),
    ('Suivi de dossier',              'Suivi de l''avancement de vos demandes')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);

-- =============================================================================
-- TABLES AUTO-CREATED BY THE APP (do not need to be in this script)
-- =============================================================================
-- These tables are created by controller `ensureSchema()` / `ensureAuthSchema()`
-- methods on first access. Listed for documentation only:
--   - email_verification_tokens   (UtilisateurController)
--   - password_reset_tokens       (UtilisateurController)
--   - ideas                        (IdeaController, VoteController)
--   - vote                         (VoteController)
--   - activity_logs                (ActivityLogger)
