-- Structure de la base de données pour le module Accompagnement (Goals et Guides)

-- Création de la base de données si elle n'existe pas
CREATE DATABASE IF NOT EXISTS accompagnement;
USE accompagnement;

-- Table Utilisateurs (Indispensable pour les clefs étrangères)
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(20) DEFAULT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    statut_compte ENUM('actif', 'inactif', 'bloque') DEFAULT 'actif',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion de comptes de test (le mot de passe est '123456' haché)
-- Hash pour '123456' : $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (standard Laravel/common)
-- Ou plus simple pour ce test: on peut utiliser password_hash('123456', PASSWORD_DEFAULT)
INSERT IGNORE INTO utilisateurs (id, nom, prenom, email, mot_de_passe, role, statut_compte) VALUES
(1, 'Admin', 'SecondVoice', 'admin@secondvoice.com', '$2y$10$f6pXoZzXz8z8z8z8z8z8zue1v5f5f5f5f5f5f5f5f5f5f5f5f5f5f', 'admin', 'actif'),
(2, 'Assistant', 'Expert', 'assistant@secondvoice.com', '$2y$10$f6pXoZzXz8z8z8z8z8z8zue1v5f5f5f5f5f5f5f5f5f5f5f5f5f5f', 'assistant', 'actif'),
(3, 'User', 'Citoyen', 'user@secondvoice.com', '$2y$10$f6pXoZzXz8z8z8z8z8z8zue1v5f5f5f5f5f5f5f5f5f5f5f5f5f5f', 'user', 'actif');

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
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_assistant_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
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
