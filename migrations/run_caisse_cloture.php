<?php
/**
 * CLÔTURER LA CAISSE ET GARDER CHAQUE CORRECTION DE PAIEMENT (10/09/2026).
 *
 * Mesuré sur la base : le caissier pouvait changer le mode de paiement d'un
 * ticket encaissé, à n'importe quelle date, et l'ancienne valeur était
 * écrasée ; aucune clôture de caisse n'existait. Deux tables neuves :
 *
 *   caisse_clotures             l'arrêté de caisse : période couverte, montants
 *                               encaissés par canal, espèces attendues, espèces
 *                               comptées, écart constaté et son explication ;
 *   caisse_corrections_paiement chaque correction de paiement d'un ticket
 *                               encaissé : l'ancienne valeur, la nouvelle, son
 *                               auteur, sa date et son motif.
 *
 * Les deux portent les colonnes de synchronisation et de vraies clés étrangères :
 * la synchro retraduit vente_id, admin_id et caissier_id d'un serveur à l'autre
 * grâce à elles (les identifiants diffèrent entre foutasvr et le VPS). Les
 * déclencheurs de synchro sont posés ici quand le serveur le permet, car
 * run_add_sync_columns.php passe AVANT cette migration au déploiement et ne
 * connaît pas encore ces tables.
 *
 * Aucune donnée existante touchée. Idempotente : rejouable sans effet.
 * À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS).
 *
 * Usage : php migrations/run_caisse_cloture.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/sync_functions.php';

$colonnes_sync = "
        `sync_uuid` CHAR(36) NULL DEFAULT NULL,
        `sync_updated_at` DATETIME NULL DEFAULT NULL,
        `sync_deleted_at` DATETIME NULL DEFAULT NULL,
        `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,";

$tables = [
    'caisse_clotures' => "CREATE TABLE `caisse_clotures` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `caissier_id` INT NOT NULL COMMENT 'qui a compté le tiroir et clôturé',
        `periode_debut` DATETIME NULL DEFAULT NULL COMMENT 'fin de la clôture précédente, exclue ; NULL pour la première',
        `periode_fin` DATETIME NOT NULL COMMENT 'instant de la clôture, inclus',
        `nb_tickets` INT NOT NULL DEFAULT 0,
        `total_encaisse` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `montant_especes` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `montant_carte` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `montant_orange_money` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `montant_wave` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `montant_cheque` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `montant_autre` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `especes_attendues` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `especes_comptees` DECIMAL(12,2) NOT NULL,
        `ecart` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'espèces comptées moins espèces attendues',
        `commentaire` VARCHAR(255) NULL DEFAULT NULL,
        `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,$colonnes_sync
        PRIMARY KEY (`id`),
        KEY `idx_caisse_clotures_fin` (`periode_fin`),
        KEY `idx_caisse_clotures_caissier` (`caissier_id`),
        UNIQUE KEY `sync_uuid` (`sync_uuid`),
        CONSTRAINT `fk_caisse_clotures_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `admin` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'caisse_corrections_paiement' => "CREATE TABLE `caisse_corrections_paiement` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `vente_id` INT NOT NULL,
        `admin_id` INT NOT NULL COMMENT 'auteur de la correction',
        `motif` VARCHAR(255) NOT NULL,
        `paiement_avant` TEXT NOT NULL COMMENT 'JSON : mode, montants, note',
        `paiement_apres` TEXT NOT NULL COMMENT 'JSON : mode, montants, note',
        `date_correction` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,$colonnes_sync
        PRIMARY KEY (`id`),
        KEY `idx_caisse_corr_vente` (`vente_id`, `date_correction`),
        KEY `idx_caisse_corr_admin` (`admin_id`),
        UNIQUE KEY `sync_uuid` (`sync_uuid`),
        CONSTRAINT `fk_caisse_corr_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`),
        CONSTRAINT `fk_caisse_corr_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

foreach ($tables as $nom => $ddl) {
    try {
        $existe = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $existe->execute([$nom]);
        if ((int) $existe->fetchColumn() > 0) {
            echo "$nom : déjà là.\n";
        } else {
            $db->exec($ddl);
            $cles = $db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
            $cles->execute([$nom]);
            echo "$nom : table créée (" . (int) $cles->fetchColumn() . " clé(s) étrangère(s)).\n";
        }
    } catch (PDOException $e) {
        fwrite(STDERR, "$nom : " . $e->getMessage() . "\n");
        exit(1);
    }

    try {
        $declencheurs = $db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?');
        $declencheurs->execute([$nom]);
        if ((int) $declencheurs->fetchColumn() >= 2) {
            echo "$nom : déclencheurs de synchro déjà posés.\n";
        } elseif (sync_triggers_creation_possible($db)) {
            sync_create_triggers_for_table($db, $nom);
            echo "$nom : déclencheurs de synchro posés.\n";
        } else {
            echo "$nom : déclencheurs de synchro NON posés (erreur 1419 sur ce serveur) ; les uuid seront attribués par run_assign_sync_uuids.php, voir scripts/reparer_declencheurs_sync.sh.\n";
        }
    } catch (Throwable $e) {
        echo "$nom : déclencheurs de synchro non posés (" . $e->getMessage() . ").\n";
    }
}
