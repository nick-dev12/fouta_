<?php
/**
 * Migration : numero_reference_fpl sur bons_livraison (référence FPL à la validation compta)
 */
require_once __DIR__ . '/../conn/conn.php';

$cols = [
    "ALTER TABLE `bons_livraison` ADD COLUMN `numero_reference_fpl` VARCHAR(20) NULL DEFAULT NULL AFTER `numero_bl`",
    "ALTER TABLE `bons_livraison` ADD UNIQUE KEY `idx_bl_numero_reference_fpl` (`numero_reference_fpl`)",
];

foreach ($cols as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), '1060') !== false) {
            echo "SKIP (déjà présent): $sql\n";
        } elseif (strpos($e->getMessage(), 'Duplicate key name') !== false || strpos($e->getMessage(), '1061') !== false) {
            echo "SKIP (index déjà présent): $sql\n";
        } else {
            echo "ERR: " . $e->getMessage() . "\n";
        }
    }
}

echo "Migration BL numero_reference_fpl terminée.\n";
