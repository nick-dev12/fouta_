-- Migration: Table pour stocker les tokens FCM (notifications push)

CREATE TABLE IF NOT EXISTS `fcm_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `token` VARCHAR(500) NOT NULL,
  `type` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `user_agent` VARCHAR(500) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`(191)),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
