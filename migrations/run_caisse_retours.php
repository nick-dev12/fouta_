<?php
/**
 * RETOURS CLIENTS EN CAISSE : REMBOURSEMENT EN ESPÈCES ET ÉCHANGE (11/09/2026).
 *
 * Mesuré : la caisse ne savait ni reprendre une pièce, ni rembourser, ni
 * échanger ; aucun remboursement n'avait jamais été saisi, même à la main.
 *
 * Tables neuves, toutes synchronisées :
 *   caisse_retours        le retour : ticket d'origine, motif, solution,
 *                         explication, montants, espèces à rendre ou à
 *                         recevoir, qui l'a préparé, qui l'a validé ou annulé ;
 *   caisse_retours_lignes les pièces rendues (liées à la ligne du ticket) et
 *                         les pièces remises en échange ;
 *   caisse_parametres     les réglages de la caisse, clé et valeur. La ligne du
 *                         délai de retour porte un sync_uuid FIXE : jouée sur
 *                         foutasvr et sur le VPS, elle reste une seule ligne.
 * Colonnes ajoutées à caisse_clotures : nb_retours, retours_especes_rendues,
 * retours_especes_recues (l'arrêté garde ce que les retours ont fait au tiroir).
 *
 * Vraies clés étrangères : la synchro retraduit les identifiants entre
 * foutasvr et le VPS grâce à elles. Déclencheurs de synchro posés ici quand le
 * serveur le permet (run_add_sync_columns.php passe avant au déploiement).
 *
 * Aucune donnée existante touchée. Idempotente : rejouable sans effet.
 * À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS).
 *
 * Usage : php migrations/run_caisse_retours.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/sync_functions.php';

const RETOUR_DELAI_SYNC_UUID = 'c1a55e00-7e70-4d0e-8a1a-000000000001';

$colonnes_sync = "
    `sync_uuid` CHAR(36) NULL DEFAULT NULL,
    `sync_updated_at` DATETIME NULL DEFAULT NULL,
    `sync_deleted_at` DATETIME NULL DEFAULT NULL,
    `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,";

$tables = [
    'caisse_parametres' => "CREATE TABLE `caisse_parametres` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `cle` VARCHAR(64) NOT NULL,
    `valeur` VARCHAR(255) NULL DEFAULT NULL,
    `admin_id` INT NULL DEFAULT NULL COMMENT 'dernier à avoir modifié le réglage',
    `date_modification` DATETIME NULL DEFAULT NULL,$colonnes_sync
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_caisse_parametres_cle` (`cle`),
    KEY `idx_caisse_parametres_admin` (`admin_id`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`),
    CONSTRAINT `fk_caisse_parametres_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'caisse_retours' => "CREATE TABLE `caisse_retours` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `numero_retour` VARCHAR(32) NOT NULL,
    `vente_id` INT NOT NULL COMMENT 'ticket d''origine',
    `statut` ENUM('en_attente','valide','annule') NOT NULL DEFAULT 'en_attente',
    `motif` ENUM('mauvaise_piece','defectueuse') NOT NULL,
    `solution` ENUM('remboursement','echange') NOT NULL,
    `explication` VARCHAR(255) NOT NULL,
    `montant_rendu` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'valeur des pièces rendues, au prix payé',
    `montant_remis` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'valeur des pièces remises en échange',
    `especes_a_rendre` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `especes_a_recevoir` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `admin_id` INT NOT NULL COMMENT 'vendeur qui a préparé le retour',
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `caissier_id` INT NULL DEFAULT NULL COMMENT 'caissier qui a validé',
    `date_validation` DATETIME NULL DEFAULT NULL,
    `annule_par` INT NULL DEFAULT NULL,
    `date_annulation` DATETIME NULL DEFAULT NULL,
    `motif_annulation` VARCHAR(255) NULL DEFAULT NULL,$colonnes_sync
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_caisse_retours_numero` (`numero_retour`),
    KEY `idx_caisse_retours_vente` (`vente_id`),
    KEY `idx_caisse_retours_statut` (`statut`, `date_validation`),
    KEY `idx_caisse_retours_admin` (`admin_id`),
    KEY `idx_caisse_retours_caissier` (`caissier_id`),
    KEY `idx_caisse_retours_annule_par` (`annule_par`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`),
    CONSTRAINT `fk_caisse_retours_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`),
    CONSTRAINT `fk_caisse_retours_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`),
    CONSTRAINT `fk_caisse_retours_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `admin` (`id`),
    CONSTRAINT `fk_caisse_retours_annule_par` FOREIGN KEY (`annule_par`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'caisse_retours_lignes' => "CREATE TABLE `caisse_retours_lignes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `retour_id` INT NOT NULL,
    `sens` ENUM('rendue','remise') NOT NULL COMMENT 'rendue par le client ou remise au client',
    `vente_ligne_id` INT NULL DEFAULT NULL COMMENT 'ligne du ticket d''origine, pour une pièce rendue',
    `produit_id` INT NOT NULL,
    `designation` VARCHAR(500) NOT NULL,
    `quantite` INT NOT NULL,
    `prix_unitaire` DECIMAL(12,2) NOT NULL,
    `montant` DECIMAL(12,2) NOT NULL,$colonnes_sync
    PRIMARY KEY (`id`),
    KEY `idx_caisse_retours_lignes_retour` (`retour_id`),
    KEY `idx_caisse_retours_lignes_vente_ligne` (`vente_ligne_id`),
    KEY `idx_caisse_retours_lignes_produit` (`produit_id`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`),
    CONSTRAINT `fk_caisse_retours_lignes_retour` FOREIGN KEY (`retour_id`) REFERENCES `caisse_retours` (`id`),
    CONSTRAINT `fk_caisse_retours_lignes_vente_ligne` FOREIGN KEY (`vente_ligne_id`) REFERENCES `caisse_vente_lignes` (`id`),
    CONSTRAINT `fk_caisse_retours_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";
$existe = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
$colonne = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');

foreach ($tables as $nom => $ddl) {
    try {
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
            echo "$nom : déclencheurs de synchro NON posés (erreur 1419 sur ce serveur) ; les uuid seront attribués par run_assign_sync_uuids.php.\n";
        }
    } catch (Throwable $e) {
        echo "$nom : déclencheurs de synchro non posés (" . $e->getMessage() . ").\n";
    }
}

// Le réglage du délai de retour : une seule ligne, même uuid sur chaque serveur, valeur vide = pas de limite.
try {
    $ligne = $db->prepare('SELECT id, sync_uuid FROM caisse_parametres WHERE cle = ?');
    $ligne->execute(['retour_delai_jours']);
    $trouvee = $ligne->fetch(PDO::FETCH_ASSOC);
    if ($trouvee) {
        echo 'caisse_parametres.retour_delai_jours : déjà là (uuid ' . ($trouvee['sync_uuid'] ?: 'vide') . ").\n";
    } else {
        $db->prepare('INSERT INTO caisse_parametres (cle, valeur, date_modification, sync_uuid, sync_updated_at) VALUES (?, NULL, NOW(), ?, NOW())')
            ->execute(['retour_delai_jours', RETOUR_DELAI_SYNC_UUID]);
        echo "caisse_parametres.retour_delai_jours : semé, sans limite, uuid fixe.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'caisse_parametres : ' . $e->getMessage() . "\n");
    exit(1);
}

// Ce que les retours ont fait au tiroir, gardé sur chaque arrêté de caisse.
$ajouts_cloture = [
    'nb_retours' => 'INT NOT NULL DEFAULT 0',
    'retours_especes_rendues' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
    'retours_especes_recues' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
];
try {
    $existe->execute(['caisse_clotures']);
    if ((int) $existe->fetchColumn() === 0) {
        echo "caisse_clotures : absente, jouez d'abord run_caisse_cloture.php.\n";
        exit(1);
    }
    foreach ($ajouts_cloture as $nom => $definition) {
        $colonne->execute(['caisse_clotures', $nom]);
        if ((int) $colonne->fetchColumn() > 0) {
            echo "caisse_clotures.$nom : déjà là.\n";
            continue;
        }
        $db->exec("ALTER TABLE `caisse_clotures` ADD COLUMN `$nom` $definition");
        echo "caisse_clotures.$nom : ajoutée.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'caisse_clotures : ' . $e->getMessage() . "\n");
    exit(1);
}
