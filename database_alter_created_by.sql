-- Ajouter le champ created_by à la table events pour identifier le créateur
-- Cela permet de gérer les droits de modification/suppression

ALTER TABLE events ADD COLUMN created_by INT NULL;

-- Ajouter une clé étrangère vers la table users (si elle existe)
-- ALTER TABLE events ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Optionnel : Mettre à jour les événements existants avec un créateur par défaut (admin id = 1)
-- UPDATE events SET created_by = 1 WHERE created_by IS NULL;
