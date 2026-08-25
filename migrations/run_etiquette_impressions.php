<?php
/**
 * LA TRACE DES IMPRESSIONS D'ÉTIQUETTES — le socle de la page « Toutes les
 * étiquettes » (24/08/2026). Qui a imprimé quoi, quand, et si c'était un
 * marquage à la main : le filtre « à imprimer » de la page liste ce qui
 * reste à faire. Même table que chez FPL natif (etiquette_impressions).
 *
 * Table NEUVE, aucune donnée touchée. Se rejoue sans risque :
 *   php migrations/run_etiquette_impressions.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$existe = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etiquette_impressions'");
$existe->execute();
if ((int) $existe->fetchColumn() > 0) {
    echo "etiquette_impressions : déjà là\n";
    exit;
}

$db->exec("CREATE TABLE `etiquette_impressions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `imprimable_type` VARCHAR(20) NOT NULL COMMENT 'produit ou noeud',
    `imprimable_id` INT NOT NULL,
    `format_id` INT NULL DEFAULT NULL,
    `admin_id` INT NOT NULL,
    `manuel` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = marquée sans réimprimer',
    `date_impression` DATETIME NULL DEFAULT NULL,
    `date_creation` DATETIME NULL DEFAULT NULL,
    `date_modification` DATETIME NULL DEFAULT NULL,
    `sync_uuid` CHAR(36) NULL DEFAULT NULL,
    `sync_updated_at` DATETIME NULL DEFAULT NULL,
    `sync_deleted_at` DATETIME NULL DEFAULT NULL,
    `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_etiq_impr_cible` (`imprimable_type`, `imprimable_id`, `date_impression`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "etiquette_impressions : table créée\n";
