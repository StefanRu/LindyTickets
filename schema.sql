-- Schema pour le système de tickets Lindy
-- Compatible MariaDB 10.x+ / MySQL 8.x+

CREATE TABLE IF NOT EXISTS `events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `event_date` VARCHAR(100) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT UNSIGNED NOT NULL,
    `ticket_code` VARCHAR(12) NOT NULL,
    `nom` VARCHAR(255) NOT NULL,
    `prenom` VARCHAR(255) NOT NULL,
    `ticket_label` VARCHAR(20) DEFAULT NULL COMMENT 'ex: 1/3',
    `checked_in` TINYINT(1) NOT NULL DEFAULT 0,
    `checked_in_at` DATETIME DEFAULT NULL,
    `checked_in_by` VARCHAR(50) DEFAULT 'scanner' COMMENT 'scanner ou manual',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ticket_code` (`ticket_code`),
    KEY `idx_event` (`event_id`),
    KEY `idx_checkin` (`checked_in`),
    CONSTRAINT `fk_ticket_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Événement par défaut
INSERT IGNORE INTO `events` (`id`, `name`, `event_date`, `location`)
VALUES (1, 'Soirée Danse', '', '');
