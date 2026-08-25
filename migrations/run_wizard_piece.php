<?php
/**
 * Applique 2026_08_23_wizard_piece_brouillons.sql en VÉRIFIANT d'abord :
 * la table et les colonnes ne sont créées que si elles manquent, donc le
 * script se rejoue sans risque (MySQL n'a pas de ADD COLUMN IF NOT EXISTS).
 *
 *   php migrations/run_wizard_piece.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$base = $db->query('SELECT DATABASE()')->fetchColumn();
echo "Base : $base\n";

$colonne = function ($table, $colonne) use ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $s->execute(['t' => $table, 'c' => $colonne]);
    return (int) $s->fetchColumn() > 0;
};
$table = function ($table) use ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $s->execute(['t' => $table]);
    return (int) $s->fetchColumn() > 0;
};

if (!$table('brouillons')) {
    $db->exec("CREATE TABLE `brouillons` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `admin_id` INT NOT NULL,
        `cle` VARCHAR(120) NOT NULL COMMENT 'Formulaire + cible, ex. produit.nouveau.27',
        `contenu` JSON NULL DEFAULT NULL,
        `date_creation` DATETIME NULL DEFAULT NULL,
        `date_modification` DATETIME NULL DEFAULT NULL,
        `sync_uuid` CHAR(36) NULL DEFAULT NULL,
        `sync_updated_at` DATETIME NULL DEFAULT NULL,
        `sync_deleted_at` DATETIME NULL DEFAULT NULL,
        `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_brouillons` (`admin_id`, `cle`),
        UNIQUE KEY `sync_uuid` (`sync_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "brouillons : table créée\n";
} else {
    echo "brouillons : déjà là\n";
}

if (!$colonne('produits', 'nom_wolof')) {
    $db->exec("ALTER TABLE `produits` ADD COLUMN `nom_wolof` VARCHAR(190) NULL DEFAULT NULL
               COMMENT 'Le nom demandé au comptoir — titre de l''étiquette' AFTER `nom`");
    echo "produits.nom_wolof : colonne ajoutée\n";
} else {
    echo "produits.nom_wolof : déjà là\n";
}

if (!$colonne('produits', 'prix_entreprise')) {
    $db->exec("ALTER TABLE `produits` ADD COLUMN `prix_entreprise` DECIMAL(12,2) NULL DEFAULT NULL
               COMMENT 'Tarif des clients professionnels, sous le prix public' AFTER `prix_promotion`");
    echo "produits.prix_entreprise : colonne ajoutée\n";
} else {
    echo "produits.prix_entreprise : déjà là\n";
}

echo "Produits : " . (int) $db->query('SELECT COUNT(*) FROM produits')->fetchColumn() . " (inchangé)\n";
