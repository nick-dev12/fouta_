<?php
/**
 * Migration hiérarchie CRUD entrepôt + reprise données existantes.
 * Usage : php migrations/run_migrate_entrepot_hierarchie_crud.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_entrepot_structure_champs.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

function migrate_hierarchie_colonne_existe(PDO $db, $table, $colonne) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
    );
    $stmt->execute([':tbl' => $table, ':col' => $colonne]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate_hierarchie_index_existe(PDO $db, $table, $index) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND INDEX_NAME = :idx'
    );
    $stmt->execute([':tbl' => $table, ':idx' => $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate_hierarchie_fk_existe(PDO $db, $table, $constraint) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND CONSTRAINT_NAME = :c'
    );
    $stmt->execute([':tbl' => $table, ':c' => $constraint]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate_hierarchie_code_abrege($code) {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    if ($code === '') {
        return 'E';
    }
    if (strlen($code) > 10) {
        $code = substr($code, 0, 10);
    }

    return $code;
}

try {
    $sqlFile = __DIR__ . '/migrate_entrepot_hierarchie_crud.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('Fichier SQL absent.');
    }
    $sql = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }
    echo "OK: tables entrepot_etagere et entrepot_structure_champ_archive.\n";

    if (!migrate_hierarchie_colonne_existe($db, 'entrepot_etage', 'code_abrege')) {
        $db->exec('ALTER TABLE `entrepot_etage` ADD COLUMN `code_abrege` VARCHAR(10) NULL DEFAULT NULL AFTER `code`');
        echo "OK: colonne entrepot_etage.code_abrege.\n";
    }

    if (!migrate_hierarchie_colonne_existe($db, 'entrepot_rayon', 'zone_id')) {
        $db->exec('ALTER TABLE `entrepot_rayon` ADD COLUMN `zone_id` INT UNSIGNED NULL DEFAULT NULL AFTER `etage_id`');
        echo "OK: colonne entrepot_rayon.zone_id.\n";
    }

    if (!migrate_hierarchie_colonne_existe($db, 'entrepot_barre', 'etagere_id')) {
        $db->exec('ALTER TABLE `entrepot_barre` ADD COLUMN `etagere_id` INT UNSIGNED NULL DEFAULT NULL AFTER `rayon_id`');
        echo "OK: colonne entrepot_barre.etagere_id.\n";
    }

    entrepot_structure_champs_ensure_table();
    if (!migrate_hierarchie_colonne_existe($db, 'entrepot_structure_champ', 'niveau_hierarchie')) {
        $db->exec(
            "ALTER TABLE `entrepot_structure_champ`
             ADD COLUMN `niveau_hierarchie` ENUM('zone','rayon','etagere','barre','position') NULL DEFAULT NULL AFTER `lie_barre`"
        );
        echo "OK: colonne entrepot_structure_champ.niveau_hierarchie.\n";
    }
    if (!migrate_hierarchie_colonne_existe($db, 'entrepot_structure_champ', 'slug_canonique')) {
        $db->exec(
            'ALTER TABLE `entrepot_structure_champ`
             ADD COLUMN `slug_canonique` VARCHAR(80) NULL DEFAULT NULL AFTER `niveau_hierarchie`'
        );
        echo "OK: colonne entrepot_structure_champ.slug_canonique.\n";
    }

    // code_abrege depuis code existant
    $etages = $db->query('SELECT id, code, code_abrege FROM entrepot_etage')->fetchAll(PDO::FETCH_ASSOC);
    $stAbrege = $db->prepare('UPDATE entrepot_etage SET code_abrege = :a WHERE id = :id');
    foreach ($etages as $et) {
        $ab = trim((string) ($et['code_abrege'] ?? ''));
        if ($ab === '') {
            $stAbrege->execute([
                ':a' => migrate_hierarchie_code_abrege($et['code'] ?? ('E' . $et['id'])),
                ':id' => (int) $et['id'],
            ]);
        }
    }
    echo "OK: code_abrege niveaux renseignés.\n";

    // Zones ↔ rayons : lier rayon.zone_id
    $rayons = $db->query('SELECT id, etage_id, numero, zone_id FROM entrepot_rayon ORDER BY etage_id, numero')->fetchAll(PDO::FETCH_ASSOC);
    $stZoneFromRayon = $db->prepare('SELECT id FROM entrepot_zone WHERE etage_id = :e AND rayon_id = :r LIMIT 1');
    $stCreateZone = $db->prepare(
        'INSERT INTO entrepot_zone (etage_id, rayon_id, numero, nom, date_modification)
         VALUES (:e, :r, :n, :nom, NOW())'
    );
    $stLinkRayon = $db->prepare('UPDATE entrepot_rayon SET zone_id = :z WHERE id = :id');
    foreach ($rayons as $rayon) {
        $rid = (int) $rayon['id'];
        $eid = (int) $rayon['etage_id'];
        $num = (int) $rayon['numero'];
        if ((int) ($rayon['zone_id'] ?? 0) > 0) {
            continue;
        }
        $stZoneFromRayon->execute([':e' => $eid, ':r' => $rid]);
        $zid = (int) $stZoneFromRayon->fetchColumn();
        if ($zid <= 0) {
            $stZoneByNum = $db->prepare('SELECT id FROM entrepot_zone WHERE etage_id = :e AND numero = :n LIMIT 1');
            $stZoneByNum->execute([':e' => $eid, ':n' => $num]);
            $zid = (int) $stZoneByNum->fetchColumn();
        }
        if ($zid <= 0) {
            $stCreateZone->execute([
                ':e' => $eid,
                ':r' => $rid,
                ':n' => $num,
                ':nom' => 'Zone ' . $num,
            ]);
            $zid = (int) $db->lastInsertId();
        }
        $stLinkRayon->execute([':z' => $zid, ':id' => $rid]);
    }
    echo "OK: rayons liés aux zones.\n";

    // Étagères : depuis éléments lie_barre ou 1 par rayon
    entrepot_champ_element_ensure_table();
    $lie = entrepot_structure_champ_get_lie_barre();
    $stEtagExists = $db->prepare('SELECT id FROM entrepot_etagere WHERE rayon_id = :r AND numero = :n LIMIT 1');
    $stEtagInsert = $db->prepare(
        'INSERT INTO entrepot_etagere (etage_id, zone_id, rayon_id, numero, nom, date_modification)
         VALUES (:e, :z, :r, :n, :nom, NOW())'
    );
    foreach ($rayons as $rayon) {
        $rid = (int) $rayon['id'];
        $eid = (int) $rayon['etage_id'];
        $zid = (int) ($rayon['zone_id'] ?? 0);
        if ($zid <= 0) {
            $stZ = $db->prepare('SELECT zone_id FROM entrepot_rayon WHERE id = :id');
            $stZ->execute([':id' => $rid]);
            $zid = (int) $stZ->fetchColumn();
        }
        $elements = [];
        if ($lie !== null) {
            $elements = entrepot_get_champ_elements_etage($eid, (int) $lie['id']);
        }
        if ($elements === []) {
            $stEtagExists->execute([':r' => $rid, ':n' => 1]);
            if ((int) $stEtagExists->fetchColumn() <= 0) {
                $stEtagInsert->execute([
                    ':e' => $eid, ':z' => $zid > 0 ? $zid : null, ':r' => $rid,
                    ':n' => 1, ':nom' => 'Étagère 1',
                ]);
            }
            continue;
        }
        foreach ($elements as $el) {
            $num = max(1, (int) ($el['numero'] ?? 1));
            $nom = trim((string) ($el['nom'] ?? ''));
            if ($nom === '') {
                $nom = 'Étagère ' . $num;
            }
            $stEtagExists->execute([':r' => $rid, ':n' => $num]);
            if ((int) $stEtagExists->fetchColumn() <= 0) {
                $stEtagInsert->execute([
                    ':e' => $eid, ':z' => $zid > 0 ? $zid : null, ':r' => $rid,
                    ':n' => $num, ':nom' => $nom,
                ]);
            }
        }
    }
    echo "OK: étagères créées.\n";

    // Barres → étagère
    $stBarres = $db->query('SELECT id, etage_id, rayon_id, champ_element_id, etagere_id FROM entrepot_barre');
    $stElNum = $db->prepare('SELECT numero FROM entrepot_champ_element WHERE id = :id LIMIT 1');
    $stEtagByRayonNum = $db->prepare('SELECT id FROM entrepot_etagere WHERE rayon_id = :r AND numero = :n LIMIT 1');
    $stEtagDefault = $db->prepare('SELECT id FROM entrepot_etagere WHERE rayon_id = :r ORDER BY numero ASC LIMIT 1');
    $stBarreEtag = $db->prepare('UPDATE entrepot_barre SET etagere_id = :et WHERE id = :id');
    foreach ($stBarres->fetchAll(PDO::FETCH_ASSOC) as $b) {
        if ((int) ($b['etagere_id'] ?? 0) > 0) {
            continue;
        }
        $bid = (int) $b['id'];
        $rayon_id = (int) ($b['rayon_id'] ?? 0);
        $etag_id = 0;
        $ceid = (int) ($b['champ_element_id'] ?? 0);
        if ($ceid > 0 && $rayon_id > 0) {
            $stElNum->execute([':id' => $ceid]);
            $el_num = (int) $stElNum->fetchColumn();
            if ($el_num > 0) {
                $stEtagByRayonNum->execute([':r' => $rayon_id, ':n' => $el_num]);
                $etag_id = (int) $stEtagByRayonNum->fetchColumn();
            }
        }
        if ($etag_id <= 0 && $rayon_id > 0) {
            $stEtagDefault->execute([':r' => $rayon_id]);
            $etag_id = (int) $stEtagDefault->fetchColumn();
        }
        if ($etag_id > 0) {
            $stBarreEtag->execute([':et' => $etag_id, ':id' => $bid]);
        }
    }
    echo "OK: barres liées aux étagères.\n";

    // Niveaux hiérarchiques + slug_canonique sur champs système
    $map_niveau = [
        'zones' => 'zone',
        'rayons' => 'rayon',
        'etageres' => 'etagere',
        'barres' => 'barre',
        'positions' => 'position',
    ];
    $champs = entrepot_structure_champs_list();
    foreach ($champs as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        $id = (int) ($ch['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $canon = entrepot_structure_champ_slug_canonique((string) ($ch['label'] ?? $slug));
        $niv = $map_niveau[$slug] ?? null;
        $db->prepare(
            'UPDATE entrepot_structure_champ SET slug_canonique = :c, niveau_hierarchie = COALESCE(niveau_hierarchie, :n) WHERE id = :id'
        )->execute([':c' => $canon, ':n' => $niv, ':id' => $id]);
    }

    // Seed champ système étagères si absent
    if (entrepot_structure_champ_get_by_slug('etageres') === null) {
        $ordre = (int) $db->query('SELECT COALESCE(MAX(ordre), 0) FROM entrepot_structure_champ')->fetchColumn();
        $db->prepare(
            'INSERT INTO entrepot_structure_champ (slug, slug_canonique, label, icon, colonne_db, ordre, est_systeme, lie_barre, niveau_hierarchie, max_valeur, date_creation)
             VALUES (:slug, :canon, :label, :icon, :col, :ordre, 1, 0, :niv, 50, NOW())'
        )->execute([
            ':slug' => 'etageres',
            ':canon' => 'etageres',
            ':label' => 'Étagères',
            ':icon' => 'fa-bars-staggered',
            ':col' => 'nb_etageres',
            ':ordre' => 35,
            ':niv' => 'etagere',
        ]);
        entrepot_structure_champ_ajouter_colonne_etage('nb_etageres', 10);
        echo "OK: champ système étagères ajouté.\n";
    }

    // Index / FK optionnels
    if (!migrate_hierarchie_index_existe($db, 'entrepot_rayon', 'idx_entrepot_rayon_zone')) {
        try {
            $db->exec('ALTER TABLE `entrepot_rayon` ADD KEY `idx_entrepot_rayon_zone` (`zone_id`)');
        } catch (PDOException $e) {
            // ignore
        }
    }
    if (!migrate_hierarchie_fk_existe($db, 'entrepot_rayon', 'fk_entrepot_rayon_zone')) {
        try {
            $db->exec(
                'ALTER TABLE `entrepot_rayon`
                 ADD CONSTRAINT `fk_entrepot_rayon_zone`
                 FOREIGN KEY (`zone_id`) REFERENCES `entrepot_zone` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        } catch (PDOException $e) {
            echo "Note: FK rayon.zone_id non ajoutée (" . $e->getMessage() . ").\n";
        }
    }
    if (!migrate_hierarchie_fk_existe($db, 'entrepot_barre', 'fk_entrepot_barre_etagere')) {
        try {
            $db->exec(
                'ALTER TABLE `entrepot_barre`
                 ADD CONSTRAINT `fk_entrepot_barre_etagere`
                 FOREIGN KEY (`etagere_id`) REFERENCES `entrepot_etagere` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        } catch (PDOException $e) {
            echo "Note: FK barre.etagere_id non ajoutée (" . $e->getMessage() . ").\n";
        }
    }

    echo "Migration hiérarchie CRUD terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
