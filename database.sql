-- Création de la base de données
CREATE DATABASE IF NOT EXISTS secondvoice_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE secondvoice_db;

-- --------------------------------------------------------
-- Structure de la table `goals`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `status` enum('en_attente','en_cours','termine','annule') NOT NULL DEFAULT 'en_attente',
  `priority` enum('faible','moyenne','haute') NOT NULL DEFAULT 'moyenne',
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  `citoyen_id` int(11) NOT NULL,
  `assistant_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Structure de la table `guides`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `guides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `type` varchar(100) NOT NULL,
  `goal_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jeu de données de test (Optionnel)
INSERT INTO `goals` (`title`, `description`, `status`, `priority`, `startDate`, `endDate`, `citoyen_id`, `assistant_id`) VALUES
('Obtenir carte d''invalidité', 'Démarche administrative pour citoyen', 'en_cours', 'haute', '2026-04-10', '2026-05-30', 1, 101),
('Demande de fauteuil roulant', 'Aide technique CNAM', 'en_attente', 'moyenne', '2026-04-15', '2026-06-15', 2, 101);

INSERT INTO `guides` (`title`, `content`, `type`, `goal_id`) VALUES
('Procédure de prise de RDV en ligne', 'Étape 1 : Se rendre sur le portail...', 'Tutoriel', 1);
