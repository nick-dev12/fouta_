<?php
/**
 * Migration : adresse_client sur devis et bons_livraison
 * php migrations/run_add_devis_bl_adresse_client.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$statements = [
    "ALTER TABLE `devis` ADD COLUMN `adresse_client` TEXT NULL DEFAULT NULL COMMENT 'Adresse postale ou siège du client (optionnel)' AFTER `client_email`",
    "ALTER TABLE `bons_livraison` ADD COLUMN `adresse_client` TEXT NULL DEFAULT NULL COMMENT 'Adresse client facture BL (optionnel)' AFTER `notes`",
];

foreach ($statements as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 76) . "…\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false) {
            echo "— Déjà présent: " . substr($sql, 0, 52) . "…\n";
        } else {
            echo "Erreur: $msg\n";
            exit(1);
        }
    }
}
echo "Terminé.\n";
