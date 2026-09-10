<?php
/**
 * ENREGISTRER LE PAIEMENT D'UNE FACTURE, PAS SEULEMENT LA COCHER (10/09/2026).
 *
 * Mesuré sur la base : une facture de devis se marquait payée d'un clic, sans
 * montant, sans moyen de paiement ni auteur, et le bouton s'offrait au vendeur ;
 * le bon de livraison payé seul et la facture mensuelle se cochaient de même.
 * 4 factures de devis payées sur 14, 1 bon payé seul, 2 factures mensuelles
 * payées : aucune trace de l'argent reçu.
 *
 * Table neuve paiements_factures : une ligne par paiement reçu (montant, moyen,
 * date de réception, référence, note, auteur) ; un paiement erroné ne s'efface
 * pas, il s'annule avec un motif. Chaque ligne vise UNE facture par une vraie
 * clé étrangère (facture de devis, bon de livraison ou facture mensuelle) : la
 * synchro retraduit ces identifiants entre foutasvr et le VPS grâce à elles.
 * Les déclencheurs de synchro sont posés ici quand le serveur le permet, car
 * run_add_sync_columns.php passe avant cette migration au déploiement.
 *
 * Aucune donnée existante touchée. Idempotente : rejouable sans effet.
 * À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS).
 *
 * Usage : php migrations/run_paiements_factures.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/sync_functions.php';

$table = 'paiements_factures';
$ddl = "CREATE TABLE `paiements_factures` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `facture_devis_id` INT NULL DEFAULT NULL,
    `bl_id` INT NULL DEFAULT NULL,
    `facture_mensuelle_id` INT NULL DEFAULT NULL,
    `montant` DECIMAL(12,2) NOT NULL,
    `mode_paiement` ENUM('especes','virement','cheque','orange_money','wave','carte','autre') NOT NULL,
    `date_paiement` DATE NOT NULL COMMENT 'jour où l''argent a été reçu',
    `reference` VARCHAR(100) NULL DEFAULT NULL COMMENT 'numéro de chèque, de virement ou de transaction',
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `admin_id` INT NOT NULL COMMENT 'qui a enregistré le paiement',
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `annule_par` INT NULL DEFAULT NULL,
    `date_annulation` DATETIME NULL DEFAULT NULL,
    `motif_annulation` VARCHAR(255) NULL DEFAULT NULL,
    `sync_uuid` CHAR(36) NULL DEFAULT NULL,
    `sync_updated_at` DATETIME NULL DEFAULT NULL,
    `sync_deleted_at` DATETIME NULL DEFAULT NULL,
    `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_paiements_facture_devis` (`facture_devis_id`),
    KEY `idx_paiements_bl` (`bl_id`),
    KEY `idx_paiements_facture_mensuelle` (`facture_mensuelle_id`),
    KEY `idx_paiements_date` (`date_paiement`),
    KEY `idx_paiements_admin` (`admin_id`),
    KEY `idx_paiements_annule_par` (`annule_par`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`),
    CONSTRAINT `fk_paiements_facture_devis` FOREIGN KEY (`facture_devis_id`) REFERENCES `factures_devis` (`id`),
    CONSTRAINT `fk_paiements_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`),
    CONSTRAINT `fk_paiements_facture_mensuelle` FOREIGN KEY (`facture_mensuelle_id`) REFERENCES `factures_mensuelles` (`id`),
    CONSTRAINT `fk_paiements_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`),
    CONSTRAINT `fk_paiements_annule_par` FOREIGN KEY (`annule_par`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

try {
    $existe = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $existe->execute([$table]);
    if ((int) $existe->fetchColumn() > 0) {
        echo "$table : déjà là.\n";
    } else {
        $db->exec($ddl);
        $cles = $db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
        $cles->execute([$table]);
        echo "$table : table créée (" . (int) $cles->fetchColumn() . " clé(s) étrangère(s)).\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, "$table : " . $e->getMessage() . "\n");
    exit(1);
}

try {
    $declencheurs = $db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?');
    $declencheurs->execute([$table]);
    if ((int) $declencheurs->fetchColumn() >= 2) {
        echo "$table : déclencheurs de synchro déjà posés.\n";
    } elseif (sync_triggers_creation_possible($db)) {
        sync_create_triggers_for_table($db, $table);
        echo "$table : déclencheurs de synchro posés.\n";
    } else {
        echo "$table : déclencheurs de synchro NON posés (erreur 1419 sur ce serveur) ; les uuid seront attribués par run_assign_sync_uuids.php, voir scripts/reparer_declencheurs_sync.sh.\n";
    }
} catch (Throwable $e) {
    echo "$table : déclencheurs de synchro non posés (" . $e->getMessage() . ").\n";
}
