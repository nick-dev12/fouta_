<?php
/**
 * Passage date_retour → DATETIME (instant exact à l’enregistrement).
 * Usage : php migrations/run_alter_bons_retour_datetime.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $stmt = $db->query(
        'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS '
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bons_retour' AND COLUMN_NAME = 'date_retour'"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $ctype = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
    if ($ctype !== '' && strpos($ctype, 'datetime') !== false) {
        echo "bons_retour.date_retour est déjà en DATETIME — rien à faire.\n";
        exit(0);
    }
    $db->exec('ALTER TABLE `bons_retour` MODIFY COLUMN `date_retour` DATETIME NOT NULL');
    echo "+ bons_retour.date_retour → DATETIME\n";
} catch (PDOException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
