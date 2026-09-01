<?php
/**
 * LE RATTRAPAGE DE SCHÉMA — production et serveur d'entreprise (01/09/2026).
 *
 * Découvert en déployant sur foutasvr : la page « Toutes les étiquettes » y
 * rendait une liste vide. Cause : sa requête lit produits.reference_oem, la
 * base de production ne l'a jamais reçue, et le catch rendait l'erreur
 * muette — la leçon connue du nom d'objet inexistant avalé par un catch.
 *
 * La comparaison complète des schémas (base de référence jomas_fouta3
 * contre la copie de production) a donné l'écart exact : 5 tables et 10
 * colonnes de nos fonctionnalités passées, posées jadis à la main sur la
 * base locale, jamais écrites en migration. Ce fichier les pose toutes,
 * dans le style maison : on vérifie, on ne crée que ce qui manque, il se
 * rejoue sans risque.
 *
 *   php migrations/run_rattrapage_schema_prod.php
 *
 * À jouer aussi sur la PRODUCTION lors de la mise en ligne du code.
 * (brouillons, nom_wolof et prix_entreprise ont déjà leur fichier :
 * migrations/run_wizard_piece.php — à jouer avec.)
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$table_existe = function ($t) use ($db) {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
    $s->execute([':t' => $t]);

    return (int) $s->fetchColumn() > 0;
};
$colonne_existe = function ($t, $c) use ($db) {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
    $s->execute([':t' => $t, ':c' => $c]);

    return (int) $s->fetchColumn() > 0;
};

/* ------------------------------------------------------------------ LES TABLES
   Définitions relevées sur la base de référence, à l'octet près. L'ordre
   respecte les clés étrangères : marques → modèles → générations… */

$tables = [];

$tables['vehicule_modeles'] = "CREATE TABLE `vehicule_modeles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marque_id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_updated_at` datetime DEFAULT NULL,
  `sync_deleted_at` datetime DEFAULT NULL,
  `sync_origin_node` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicule_modeles_marque_nom` (`marque_id`,`nom`),
  UNIQUE KEY `idx_vehicule_modeles_sync_uuid` (`sync_uuid`),
  KEY `idx_vehicule_modeles_sync_updated` (`sync_updated_at`),
  CONSTRAINT `fk_vehicule_modeles_marque` FOREIGN KEY (`marque_id`) REFERENCES `marques` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['vehicule_generations'] = "CREATE TABLE `vehicule_generations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `modele_id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `annee_debut` smallint unsigned DEFAULT NULL,
  `annee_fin` smallint unsigned DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_updated_at` datetime DEFAULT NULL,
  `sync_deleted_at` datetime DEFAULT NULL,
  `sync_origin_node` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicule_generations_modele_nom` (`modele_id`,`nom`),
  UNIQUE KEY `idx_vehicule_generations_sync_uuid` (`sync_uuid`),
  KEY `idx_vehicule_generations_sync_updated` (`sync_updated_at`),
  CONSTRAINT `fk_vehicule_generations_modele` FOREIGN KEY (`modele_id`) REFERENCES `vehicule_modeles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['produit_modeles'] = "CREATE TABLE `produit_modeles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `modele_id` int NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_updated_at` datetime DEFAULT NULL,
  `sync_deleted_at` datetime DEFAULT NULL,
  `sync_origin_node` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produit_modeles` (`produit_id`,`modele_id`),
  UNIQUE KEY `idx_produit_modeles_sync_uuid` (`sync_uuid`),
  KEY `idx_produit_modeles_modele` (`modele_id`),
  KEY `idx_produit_modeles_sync_updated` (`sync_updated_at`),
  CONSTRAINT `fk_produit_modeles_modele` FOREIGN KEY (`modele_id`) REFERENCES `vehicule_modeles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_produit_modeles_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['stock_emplacement'] = "CREATE TABLE `stock_emplacement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `noeud_id` int unsigned NOT NULL,
  `quantite` int NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_updated_at` datetime DEFAULT NULL,
  `sync_deleted_at` datetime DEFAULT NULL,
  `sync_origin_node` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_emplacement_produit_noeud` (`produit_id`,`noeud_id`),
  UNIQUE KEY `idx_stock_emplacement_sync_uuid` (`sync_uuid`),
  KEY `idx_stock_emplacement_noeud` (`noeud_id`),
  KEY `idx_stock_emplacement_sync_updated` (`sync_updated_at`),
  CONSTRAINT `fk_stock_emplacement_noeud` FOREIGN KEY (`noeud_id`) REFERENCES `entrepot_hierarchie_noeud` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_emplacement_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['stock_ajustement_demandes'] = "CREATE TABLE `stock_ajustement_demandes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `noeud_id` int unsigned DEFAULT NULL,
  `quantite_constatee` int NOT NULL,
  `quantite_theorique` int NOT NULL,
  `motif` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('en_attente','validee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `demandeur_id` int DEFAULT NULL,
  `decideur_id` int DEFAULT NULL,
  `date_demande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_decision` datetime DEFAULT NULL,
  `decision_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_updated_at` datetime DEFAULT NULL,
  `sync_deleted_at` datetime DEFAULT NULL,
  `sync_origin_node` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_stock_ajust_sync_uuid` (`sync_uuid`),
  KEY `idx_stock_ajust_statut` (`statut`),
  KEY `idx_stock_ajust_produit` (`produit_id`),
  KEY `idx_stock_ajust_sync_updated` (`sync_updated_at`),
  KEY `fk_stock_ajust_noeud` (`noeud_id`),
  KEY `fk_stock_ajust_demandeur` (`demandeur_id`),
  KEY `fk_stock_ajust_decideur` (`decideur_id`),
  CONSTRAINT `fk_stock_ajust_decideur` FOREIGN KEY (`decideur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_ajust_demandeur` FOREIGN KEY (`demandeur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_ajust_noeud` FOREIGN KEY (`noeud_id`) REFERENCES `entrepot_hierarchie_noeud` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_ajust_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

foreach ($tables as $nom => $ddl) {
    if ($table_existe($nom)) {
        echo "table $nom : déjà là\n";
        continue;
    }
    $db->exec($ddl);
    echo "table $nom : créée\n";
}

/* ------------------------------------------------------------------ LES COLONNES
   Types relevés sur la base de référence. Pas de clause AFTER : la position
   n'a aucun effet fonctionnel, et viser une ancre absente ferait échouer. */

$colonnes = [
    ['produits', 'modele_id', 'INT NULL DEFAULT NULL'],
    ['produits', 'generation_id', 'INT NULL DEFAULT NULL'],
    ['produits', 'reference_oem', 'VARCHAR(100) NULL DEFAULT NULL'],
    ['produits', 'position_montage', 'VARCHAR(30) NULL DEFAULT NULL'],
    ['sous_categories', 'image', 'VARCHAR(255) NULL DEFAULT NULL'],
    ['sous_categories', 'mots_cles', 'VARCHAR(500) NULL DEFAULT NULL'],
    ['entrepot_hierarchie_noeud', 'code_scan', 'VARCHAR(20) NULL DEFAULT NULL'],
    ['entrepot_hierarchie_noeud', 'est_defectueux', 'TINYINT(1) NOT NULL DEFAULT 0'],
    ['stock_mouvements', 'emplacement_source_id', 'INT UNSIGNED NULL DEFAULT NULL'],
    ['stock_mouvements', 'emplacement_destination_id', 'INT UNSIGNED NULL DEFAULT NULL'],
];

foreach ($colonnes as [$t, $c, $def]) {
    if (!$table_existe($t)) {
        echo "colonne $t.$c : TABLE ABSENTE — ignorée\n";
        continue;
    }
    if ($colonne_existe($t, $c)) {
        echo "colonne $t.$c : déjà là\n";
        continue;
    }
    $db->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
    echo "colonne $t.$c : ajoutée\n";
}

echo "Terminé.\n";
