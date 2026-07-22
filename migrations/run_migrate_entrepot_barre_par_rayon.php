<?php
/**
 * Migration : barres numérotées par rayon (plus par étage).
 * Usage : php migrations/run_migrate_entrepot_barre_par_rayon.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

$sqlFile = __DIR__ . '/migrate_entrepot_barre_par_rayon.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Fichier SQL absent.\n");
    exit(1);
}

// Supprimer l’ancienne contrainte (etage_id, numero) si elle traîne encore
try {
    $db->exec('ALTER TABLE `entrepot_barre` DROP INDEX `uniq_entrepot_barre_etage_num`');
    echo "OK: index uniq_entrepot_barre_etage_num supprimé.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '1091') !== false || strpos($e->getMessage(), "check that column/key exists") !== false) {
        echo "Index uniq_entrepot_barre_etage_num déjà absent.\n";
    } else {
        fwrite(STDERR, 'Erreur DROP ancien index : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

try {
    $db->exec(
        'ALTER TABLE `entrepot_barre` ADD UNIQUE KEY `uniq_entrepot_barre_rayon_num` (`rayon_id`, `numero`)'
    );
    echo "OK: contrainte uniq_entrepot_barre_rayon_num ajoutée.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '1061') !== false || strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "Contrainte uniq_entrepot_barre_rayon_num déjà présente.\n";
    } else {
        fwrite(STDERR, 'Erreur ADD contrainte rayon : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

try {
    // Rattacher les barres orphelines au rayon correspondant (modulo)
    $etages = $db->query('SELECT id, numero_etage FROM entrepot_etage ORDER BY numero_etage ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($etages as $et) {
        $eid = (int) $et['id'];
        $rayons = $db->prepare('SELECT id, numero FROM entrepot_rayon WHERE etage_id = :e ORDER BY numero ASC');
        $rayons->execute([':e' => $eid]);
        $rayonRows = $rayons->fetchAll(PDO::FETCH_ASSOC);
        if ($rayonRows === []) {
            continue;
        }
        $stB = $db->prepare('SELECT id, numero, rayon_id FROM entrepot_barre WHERE etage_id = :e ORDER BY numero ASC');
        $stB->execute([':e' => $eid]);
        foreach ($stB->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $bid = (int) $b['id'];
            $num = (int) $b['numero'];
            $rid = (int) ($b['rayon_id'] ?? 0);
            if ($rid <= 0) {
                $idx = ($num - 1) % count($rayonRows);
                $rid = (int) $rayonRows[$idx]['id'];
                $db->prepare('UPDATE entrepot_barre SET rayon_id = :r WHERE id = :id')
                    ->execute([':r' => $rid, ':id' => $bid]);
            }
        }
    }

    echo "Migration barres par rayon terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur rattachement barres : ' . $e->getMessage() . "\n");
    exit(1);
}
