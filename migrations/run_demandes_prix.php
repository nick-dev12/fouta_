<?php
/**
 * DEMANDES DE PRIX (11/09/2026).
 *
 * Décision de la direction : le vendeur ne tape plus aucun prix. Une pièce sans
 * prix au catalogue ne se vend pas ; le vendeur demande son prix, et seul le
 * responsable de stock le fixe au catalogue.
 *
 * Table neuve, synchronisée :
 *   demandes_prix  une ligne par pièce et par colonne de prix en attente : le
 *                  prix de vente, ou le Prix Entreprise choisi pour le total
 *                  d'un devis. Le premier vendeur qui l'a demandé, le nombre de
 *                  demandes et le dernier demandeur, puis qui a fixé le prix,
 *                  quand, et à combien. Une pièce n'a jamais qu'une demande en
 *                  attente par colonne : une seconde demande s'y ajoute au lieu
 *                  d'en créer une autre.
 *
 * Vraies clés étrangères : la synchro retraduit les identifiants entre foutasvr
 * et le VPS grâce à elles. Déclencheurs de synchro posés quand le serveur le permet.
 *
 * Aucune donnée existante touchée. Idempotente : rejouable sans effet ; une
 * table créée avant la colonne « champ » la reçoit.
 * À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS).
 *
 * Usage : php migrations/run_demandes_prix.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/sync_functions.php';

$commentaire_champ = "colonne de prix demandée : prix (prix de vente), prix_entreprise, prix_achat";
$ddl = "CREATE TABLE `demandes_prix` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `produit_id` INT NOT NULL,
    `champ` VARCHAR(40) NOT NULL DEFAULT 'prix' COMMENT '$commentaire_champ',
    `admin_id` INT NOT NULL COMMENT 'premier vendeur à avoir demandé le prix',
    `statut` ENUM('en_attente','traitee','annulee') NOT NULL DEFAULT 'en_attente',
    `nb_demandes` INT NOT NULL DEFAULT 1,
    `date_demande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_derniere_demande` DATETIME NULL DEFAULT NULL,
    `dernier_demandeur_id` INT NULL DEFAULT NULL,
    `traite_par` INT NULL DEFAULT NULL COMMENT 'qui a fixé le prix, quand on le sait',
    `date_traitement` DATETIME NULL DEFAULT NULL,
    `prix_fixe` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'prix fixé au catalogue',
    `sync_uuid` CHAR(36) NULL DEFAULT NULL,
    `sync_updated_at` DATETIME NULL DEFAULT NULL,
    `sync_deleted_at` DATETIME NULL DEFAULT NULL,
    `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_demandes_prix_piece` (`produit_id`, `champ`, `statut`),
    KEY `idx_demandes_prix_statut` (`statut`),
    KEY `idx_demandes_prix_admin` (`admin_id`),
    KEY `idx_demandes_prix_dernier` (`dernier_demandeur_id`),
    KEY `idx_demandes_prix_traite_par` (`traite_par`),
    UNIQUE KEY `sync_uuid` (`sync_uuid`),
    CONSTRAINT `fk_demandes_prix_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`),
    CONSTRAINT `fk_demandes_prix_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`),
    CONSTRAINT `fk_demandes_prix_dernier` FOREIGN KEY (`dernier_demandeur_id`) REFERENCES `admin` (`id`),
    CONSTRAINT `fk_demandes_prix_traite_par` FOREIGN KEY (`traite_par`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";
$nom = 'demandes_prix';
try {
    $existe = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $existe->execute([$nom]);
    if ((int) $existe->fetchColumn() === 0) {
        $db->exec($ddl);
        $cles = $db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
        $cles->execute([$nom]);
        echo "$nom : table créée (" . (int) $cles->fetchColumn() . " clé(s) étrangère(s)).\n";
    } else {
        $colonne = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $colonne->execute([$nom, 'champ']);
        if ((int) $colonne->fetchColumn() === 0) {
            $db->exec("ALTER TABLE `demandes_prix`
                ADD COLUMN `champ` VARCHAR(40) NOT NULL DEFAULT 'prix' COMMENT '$commentaire_champ' AFTER `produit_id`,
                ADD KEY `idx_demandes_prix_piece` (`produit_id`, `champ`, `statut`),
                ADD KEY `idx_demandes_prix_statut` (`statut`)");
            try {
                $db->exec('ALTER TABLE `demandes_prix` DROP KEY `idx_demandes_prix_produit_statut`');
            } catch (PDOException $e) {
                // L'ancienne clé reste si le serveur la garde pour la clé étrangère : sans effet sur les règles.
            }
            echo "$nom : colonne « champ » ajoutée à la table existante.\n";
        } else {
            echo "$nom : déjà là.\n";
        }
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
