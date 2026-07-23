<?php
/**
 * Ajoute les colonnes étiquette / QR sur entrepot_hierarchie_niveau.
 * Usage : php migrations/run_migrate_entrepot_hierarchie_etiquette.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

function migrate_etiq_colonne_existe(PDO $db, $table, $colonne) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
    );
    $stmt->execute([':tbl' => $table, ':col' => $colonne]);

    return (int) $stmt->fetchColumn() > 0;
}

try {
    if (!migrate_etiq_colonne_existe($db, 'entrepot_hierarchie_niveau', 'est_etiquette_qr')) {
        $db->exec(
            'ALTER TABLE `entrepot_hierarchie_niveau`
             ADD COLUMN `est_etiquette_qr` TINYINT(1) NOT NULL DEFAULT 0 AFTER `actif`'
        );
        echo "OK: est_etiquette_qr.\n";
    } else {
        echo "— est_etiquette_qr déjà présent.\n";
    }

    if (!migrate_etiq_colonne_existe($db, 'entrepot_hierarchie_niveau', 'etiquette_lie_type')) {
        $db->exec(
            "ALTER TABLE `entrepot_hierarchie_niveau`
             ADD COLUMN `etiquette_lie_type` VARCHAR(20) NOT NULL DEFAULT 'etage' AFTER `est_etiquette_qr`"
        );
        echo "OK: etiquette_lie_type.\n";
    } else {
        echo "— etiquette_lie_type déjà présent.\n";
    }

    if (!migrate_etiq_colonne_existe($db, 'entrepot_hierarchie_niveau', 'etiquette_lie_niveau_id')) {
        $db->exec(
            'ALTER TABLE `entrepot_hierarchie_niveau`
             ADD COLUMN `etiquette_lie_niveau_id` INT UNSIGNED NULL DEFAULT NULL AFTER `etiquette_lie_type`'
        );
        echo "OK: etiquette_lie_niveau_id.\n";
    } else {
        echo "— etiquette_lie_niveau_id déjà présent.\n";
    }

    // Seed : Barres = étiquette QR liée au Niveau (étage), si aucune déjà configurée
    $has = (int) $db->query(
        'SELECT COUNT(*) FROM entrepot_hierarchie_niveau WHERE est_etiquette_qr = 1'
    )->fetchColumn();
    if ($has === 0) {
        $st = $db->prepare(
            "UPDATE entrepot_hierarchie_niveau
             SET est_etiquette_qr = 1, etiquette_lie_type = 'etage', etiquette_lie_niveau_id = NULL
             WHERE slug IN ('barre', 'barres') LIMIT 1"
        );
        $st->execute();
        if ($st->rowCount() > 0) {
            echo "OK: seed Barres = étiquette / QR (lié au Niveau).\n";
        } else {
            echo "— aucun slug barre pour seed étiquette.\n";
        }
    } else {
        echo "— étiquette QR déjà configurée.\n";
    }

    echo "Migration étiquette hiérarchie terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR: ' . $e->getMessage() . "\n");
    exit(1);
}
