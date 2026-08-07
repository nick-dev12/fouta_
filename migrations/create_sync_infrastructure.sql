-- Infrastructure de synchronisation bidirectionnelle Fouta
-- Usage : mysql -u USER -p NOM_BASE < migrations/create_sync_infrastructure.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sync_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `direction` ENUM('push','pull','files') NOT NULL,
  `table_name` VARCHAR(64) NULL DEFAULT NULL,
  `records_count` INT(11) NOT NULL DEFAULT 0,
  `conflicts_count` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('success','partial','error') NOT NULL DEFAULT 'success',
  `message` TEXT NULL,
  `node_id` VARCHAR(64) NULL DEFAULT NULL,
  `remote_node_id` VARCHAR(64) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sync_log_created` (`created_at`),
  KEY `idx_sync_log_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_state` (
  `state_key` VARCHAR(128) NOT NULL,
  `state_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_id_map` (
  `table_name` VARCHAR(64) NOT NULL,
  `sync_uuid` CHAR(36) NOT NULL,
  `local_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`table_name`, `sync_uuid`),
  UNIQUE KEY `idx_sync_map_local` (`table_name`, `local_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_file_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `relative_path` VARCHAR(512) NOT NULL,
  `file_md5` CHAR(32) NOT NULL,
  `file_size` BIGINT(20) NOT NULL DEFAULT 0,
  `direction` ENUM('push','pull') NOT NULL DEFAULT 'push',
  `status` ENUM('pending','done','error') NOT NULL DEFAULT 'pending',
  `last_error` TEXT NULL,
  `sync_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sync_file_path` (`relative_path`(191)),
  KEY `idx_sync_file_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
