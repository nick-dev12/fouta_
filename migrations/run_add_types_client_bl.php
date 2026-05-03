<?php
/**
 * Types clients (Standard / VIP) + plafonds cumul BL ; colonnes type sur contacts et clients_b2b.
 * php migrations/run_add_types_client_bl.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

function pct_try_add_enum_col(PDO $db, $table) {
    try {
        $db->exec(
            "ALTER TABLE `$table` ADD COLUMN `type_client_bl` ENUM('standard','vip') NOT NULL DEFAULT 'standard'"
        );
        echo "+ colonne `$table`.`type_client_bl`\n";
    } catch (PDOException $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'duplicate column') !== false
            || strpos($m, 'déjà utilisé') !== false
            || strpos($m, 'already exists') !== false
        ) {
            echo "— `$table`.`type_client_bl` existe déjà\n";
        } else {
            throw $e;
        }
    }
}

try {
    $db->exec('SET NAMES utf8mb4');

    $db->exec("
CREATE TABLE IF NOT EXISTS `parametres_types_client_bl` (
  `code_type` ENUM('standard','vip') NOT NULL,
  `montant_plafond_ht` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table parametres_types_client_bl\n";

    pct_try_add_enum_col($db, 'contacts');
    pct_try_add_enum_col($db, 'clients_b2b');

    echo "\nMigration types client BL terminée.\n";

    try {
        $db->exec("INSERT IGNORE INTO parametres_types_client_bl (code_type, montant_plafond_ht) VALUES ('standard', 0), ('vip', 0)");
        echo "+ lignes par défaut parametres_types_client_bl (si absentes)\n";
    } catch (PDOException $e) {
        // ignoré
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}
