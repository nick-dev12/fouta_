<?php
/**
 * Migration : colonnes TVA devis, bons_livraison, factures_devis
 * php migrations/run_add_devis_bl_factures_tva.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$statements = [
    "ALTER TABLE `devis` ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = total TTC'",
    "ALTER TABLE `devis` ADD COLUMN `taux_tva_pourcent` DECIMAL(5,2) NOT NULL DEFAULT 18.00",
    "ALTER TABLE `bons_livraison` ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE `bons_livraison` ADD COLUMN `taux_tva_pourcent` DECIMAL(5,2) NOT NULL DEFAULT 18.00",
    "ALTER TABLE `factures_devis` ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE `factures_devis` ADD COLUMN `montant_ht` DECIMAL(10,2) NULL DEFAULT NULL",
    "ALTER TABLE `factures_devis` ADD COLUMN `montant_tva` DECIMAL(10,2) NULL DEFAULT NULL",
    "ALTER TABLE `factures_devis` ADD COLUMN `taux_tva_pourcent` DECIMAL(5,2) NULL DEFAULT NULL",
];

foreach ($statements as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 72) . "…\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false) {
            echo "— Déjà présent: " . substr($sql, 0, 48) . "…\n";
        } else {
            echo "Erreur: $msg\n";
            exit(1);
        }
    }
}
echo "Terminé.\n";
