-- Mise à jour du schéma pour ajouter les champs users supplémentaires
-- Ajout des nouveaux champs dans la table users
ALTER TABLE
    `users`
ADD
    COLUMN `phone` VARCHAR(50) NULL DEFAULT NULL
AFTER
    `name`,
ADD
    COLUMN `facebook` VARCHAR(255) NULL DEFAULT NULL
AFTER
    `phone`,
ADD
    COLUMN `whatsapp` VARCHAR(50) NULL DEFAULT NULL
AFTER
    `facebook`;

-- Optionnel: Créer des index pour améliorer les performances de recherche
-- ALTER TABLE `users` ADD INDEX `idx_phone` (`phone`);
-- ALTER TABLE `users` ADD INDEX `idx_name` (`name`);
-- Vérification du schéma
DESCRIBE `users`;

-- Schéma complet de la table users après mise à jour:
/*
 CREATE TABLE `users` (
 `user_id` int NOT NULL,
 `user_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `facebook` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `whatsapp` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `commentaire` text COLLATE utf8mb4_unicode_ci,
 `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`user_id`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */