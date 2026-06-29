<?php
/**
 * Colonnes facture_bl_payee / date_paiement_bl sur bons_livraison.
 * Crée aussi numero_reference_fpl si absent (prérequis sur certaines bases prod).
 *
 * Usage CLI : php migrations/run_add_bl_facture_paiement.php
 * Usage web : …/migrations/run_add_bl_facture_paiement.php
 */
require_once __DIR__ . '/../conn/conn.php';

$is_cli = (PHP_SAPI === 'cli');
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function migration_bl_paiement_err($message)
{
    if (defined('STDERR') && PHP_SAPI === 'cli') {
        fwrite(STDERR, $message);
    } else {
        echo $message;
    }
}

function migration_bl_paiement_deja_applique($msg)
{
    return stripos($msg, 'Duplicate column') !== false
        || stripos($msg, 'Duplicate key name') !== false
        || stripos($msg, 'already exists') !== false
        || stripos($msg, 'déjà utilisé') !== false
        || stripos($msg, '1060') !== false
        || stripos($msg, '1061') !== false;
}

function migration_bl_colonne_existe(PDO $db, $table, $column)
{
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    ');
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function migration_bl_exec(PDO $db, $sql, $label)
{
    try {
        $db->exec($sql);
        echo "+ OK : {$label}\n";
        return true;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (migration_bl_paiement_deja_applique($msg)) {
            echo "~ déjà appliqué : {$label}\n";
            return true;
        }
        migration_bl_paiement_err("Erreur ({$label}) : {$msg}\n");
        return false;
    }
}

if (!$db) {
    migration_bl_paiement_err("Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->query('SELECT 1 FROM bons_livraison LIMIT 1');
} catch (PDOException $e) {
    migration_bl_paiement_err("Table bons_livraison absente.\n");
    exit(1);
}

/* Prérequis : numero_reference_fpl (numérotation facture BL validée) */
if (!migration_bl_colonne_existe($db, 'bons_livraison', 'numero_reference_fpl')) {
    echo "→ Colonne numero_reference_fpl absente, création…\n";
    $after_fpl = migration_bl_colonne_existe($db, 'bons_livraison', 'numero_bl') ? ' AFTER `numero_bl`' : '';
    if (!migration_bl_exec(
        $db,
        'ALTER TABLE `bons_livraison` ADD COLUMN `numero_reference_fpl` VARCHAR(20) NULL DEFAULT NULL' . $after_fpl,
        'numero_reference_fpl'
    )) {
        exit(1);
    }
    migration_bl_exec(
        $db,
        'ALTER TABLE `bons_livraison` ADD UNIQUE KEY `idx_bl_numero_reference_fpl` (`numero_reference_fpl`)',
        'index idx_bl_numero_reference_fpl'
    );
}

/* facture_bl_payee */
if (!migration_bl_colonne_existe($db, 'bons_livraison', 'facture_bl_payee')) {
    if (migration_bl_colonne_existe($db, 'bons_livraison', 'numero_reference_fpl')) {
        $sql_payee = 'ALTER TABLE `bons_livraison` ADD COLUMN `facture_bl_payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `numero_reference_fpl`';
    } elseif (migration_bl_colonne_existe($db, 'bons_livraison', 'numero_bl')) {
        $sql_payee = 'ALTER TABLE `bons_livraison` ADD COLUMN `facture_bl_payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `numero_bl`';
    } else {
        $sql_payee = 'ALTER TABLE `bons_livraison` ADD COLUMN `facture_bl_payee` TINYINT(1) NOT NULL DEFAULT 0';
    }
    if (!migration_bl_exec($db, $sql_payee, 'facture_bl_payee')) {
        exit(1);
    }
} else {
    echo "~ déjà appliqué : facture_bl_payee\n";
}

/* date_paiement_bl */
if (!migration_bl_colonne_existe($db, 'bons_livraison', 'date_paiement_bl')) {
    $sql_date = migration_bl_colonne_existe($db, 'bons_livraison', 'facture_bl_payee')
        ? 'ALTER TABLE `bons_livraison` ADD COLUMN `date_paiement_bl` DATETIME NULL DEFAULT NULL AFTER `facture_bl_payee`'
        : 'ALTER TABLE `bons_livraison` ADD COLUMN `date_paiement_bl` DATETIME NULL DEFAULT NULL';
    if (!migration_bl_exec($db, $sql_date, 'date_paiement_bl')) {
        exit(1);
    }
} else {
    echo "~ déjà appliqué : date_paiement_bl\n";
}

migration_bl_exec(
    $db,
    'ALTER TABLE `bons_livraison` ADD KEY `idx_bl_facture_payee` (`facture_bl_payee`)',
    'index idx_bl_facture_payee'
);

echo "\nMigration paiement facture BL terminée.\n";
