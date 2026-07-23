<?php
/**
 * Unicité numéro rayon : par zone (parent), plus par étage entier.
 * Usage : php migrations/run_migrate_entrepot_rayon_unique_par_zone.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

function migrate_index_existe(PDO $db, $table, $index) {
    $st = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
    );
    $st->execute([':t' => $table, ':i' => $index]);

    return (int) $st->fetchColumn() > 0;
}

try {
    // Dédupliquer d’éventuels conflits avant de créer l’index zone+numero
    // (même zone_id + même numero) — garder le plus petit id
    $dups = $db->query(
        'SELECT zone_id, numero, COUNT(*) AS c, MIN(id) AS keep_id
         FROM entrepot_rayon
         WHERE zone_id IS NOT NULL AND zone_id > 0
         GROUP BY zone_id, numero
         HAVING c > 1'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dups as $d) {
        $zone_id = (int) $d['zone_id'];
        $numero = (int) $d['numero'];
        $keep = (int) $d['keep_id'];
        $others = $db->prepare(
            'SELECT id FROM entrepot_rayon WHERE zone_id = :z AND numero = :n AND id != :k ORDER BY id ASC'
        );
        $others->execute([':z' => $zone_id, ':n' => $numero, ':k' => $keep]);
        $max = (int) $db->query(
            'SELECT COALESCE(MAX(numero), 0) FROM entrepot_rayon WHERE zone_id = ' . $zone_id
        )->fetchColumn();
        foreach ($others->fetchAll(PDO::FETCH_COLUMN) as $oid) {
            $max++;
            $db->prepare('UPDATE entrepot_rayon SET numero = :n WHERE id = :id')
                ->execute([':n' => $max, ':id' => (int) $oid]);
            echo "— rayon #$oid renuméroté en $max (conflit zone $zone_id).\n";
        }
    }

    if (migrate_index_existe($db, 'entrepot_rayon', 'uniq_entrepot_rayon_etage_num')) {
        $db->exec('ALTER TABLE `entrepot_rayon` DROP INDEX `uniq_entrepot_rayon_etage_num`');
        echo "OK: index uniq_entrepot_rayon_etage_num supprimé.\n";
    } else {
        echo "— uniq_entrepot_rayon_etage_num déjà absent.\n";
    }

    if (!migrate_index_existe($db, 'entrepot_rayon', 'uniq_entrepot_rayon_zone_num')) {
        $db->exec(
            'ALTER TABLE `entrepot_rayon`
             ADD UNIQUE KEY `uniq_entrepot_rayon_zone_num` (`zone_id`, `numero`)'
        );
        echo "OK: contrainte uniq_entrepot_rayon_zone_num ajoutée.\n";
    } else {
        echo "— uniq_entrepot_rayon_zone_num déjà présente.\n";
    }

    echo "Migration rayon unique par zone terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR: ' . $e->getMessage() . "\n");
    exit(1);
}
