<?php
/**
 * Colonnes facture_bl_payee / date_paiement_bl sur bons_livraison.
 * Usage : php migrations/run_add_bl_facture_paiement.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$statements = [
    "ALTER TABLE `bons_livraison` ADD COLUMN `facture_bl_payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `numero_reference_fpl`",
    "ALTER TABLE `bons_livraison` ADD COLUMN `date_paiement_bl` DATETIME NULL DEFAULT NULL AFTER `facture_bl_payee`",
    "ALTER TABLE `bons_livraison` ADD KEY `idx_bl_facture_payee` (`facture_bl_payee`)",
];

foreach ($statements as $sql) {
    try {
        $db->exec($sql);
        echo "+ OK : " . substr($sql, 0, 80) . "…\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'Duplicate key name') !== false) {
            echo "~ déjà appliqué : " . substr($sql, 0, 60) . "…\n";
            continue;
        }
        fwrite(STDERR, "Erreur : {$msg}\n");
        exit(1);
    }
}

echo "\nMigration paiement facture BL terminée.\n";
