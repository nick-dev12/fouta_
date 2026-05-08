<?php
/**
 * Migration : payee, date_paiement, numero_reference_fpl sur factures_devis
 * php migrations/run_add_factures_devis_paiement_fpl.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$statements = [
    "ALTER TABLE `factures_devis` ADD COLUMN `payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `montant_total`",
    "ALTER TABLE `factures_devis` ADD COLUMN `date_paiement` DATETIME NULL DEFAULT NULL AFTER `payee`",
    "ALTER TABLE `factures_devis` ADD COLUMN `numero_reference_fpl` VARCHAR(20) NULL DEFAULT NULL AFTER `date_paiement`",
    "ALTER TABLE `factures_devis` ADD KEY `idx_factures_devis_payee` (`payee`)",
    "ALTER TABLE `factures_devis` ADD UNIQUE KEY `idx_factures_devis_numero_fpl` (`numero_reference_fpl`)",
];

foreach ($statements as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 70) . "…\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'duplicate key') !== false) {
            echo "— Déjà présent: " . substr($sql, 0, 50) . "…\n";
        } else {
            echo "Erreur: $msg\n";
            exit(1);
        }
    }
}
echo "Terminé.\n";
