<?php
/**
 * Migration : tva_incluse sur factures_mensuelles
 * php migrations/run_add_factures_mensuelles_tva_incluse.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$sql = "ALTER TABLE `factures_mensuelles`
  ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = TVA en sus (TTC = HT + TVA); 0 = total TTC avec TVA incluse'
  AFTER `total_ht`";

try {
    $db->exec($sql);
    echo "OK: colonne tva_incluse ajoutée.\n";
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Duplicate column') !== false) {
        echo "— Colonne tva_incluse déjà présente.\n";
    } else {
        echo "Erreur: $msg\n";
        exit(1);
    }
}
echo "Terminé.\n";
