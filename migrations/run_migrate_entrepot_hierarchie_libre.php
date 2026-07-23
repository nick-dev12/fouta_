<?php
/**
 * Migration hiérarchie libre (niveaux configurables + nœuds génériques).
 * Usage : php migrations/run_migrate_entrepot_hierarchie_libre.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

function migrate_libre_colonne_existe(PDO $db, $table, $colonne) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
    );
    $stmt->execute([':tbl' => $table, ':col' => $colonne]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate_libre_table_existe(PDO $db, $table) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl'
    );
    $stmt->execute([':tbl' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrate_libre_slug($label) {
    $label = trim((string) $label);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($ascii !== false) {
            $label = $ascii;
        }
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $label));
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'niveau';
    }
    if (strlen($slug) > 60) {
        $slug = substr($slug, 0, 60);
    }

    return $slug;
}

try {
    $sqlFile = __DIR__ . '/migrate_entrepot_hierarchie_libre.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('Fichier SQL absent.');
    }
    $sql = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        // Retirer commentaires de ligne en tête / isolés sans supprimer le CREATE.
        $lines = preg_split('/\R/', $statement) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '--') === 0) {
                continue;
            }
            $clean[] = $line;
        }
        $statement = trim(implode("\n", $clean));
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }
    echo "OK: tables entrepot_hierarchie_niveau / entrepot_hierarchie_noeud.\n";

    // Seed niveaux par défaut si vide
    $countNiv = (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_niveau')->fetchColumn();
    if ($countNiv === 0) {
        $defaults = [
            ['zone', 'Zones', 'fa-map-marker-alt', 10],
            ['rayon', 'Rayons', 'fa-th-large', 20],
            ['etagere', 'Étagères', 'fa-bars-staggered', 30],
            ['barre', 'Barres / rayon', 'fa-grip-lines', 40],
            ['position', 'Positions', 'fa-crosshairs', 50],
        ];
        // Enrichir depuis structure_champ si présent
        if (migrate_libre_table_existe($db, 'entrepot_structure_champ')) {
            try {
                $rows = $db->query(
                    "SELECT slug, label, icon, niveau_hierarchie, ordre
                     FROM entrepot_structure_champ
                     WHERE niveau_hierarchie IS NOT NULL AND niveau_hierarchie != ''
                     ORDER BY ordre ASC, id ASC"
                )->fetchAll(PDO::FETCH_ASSOC);
                $byNiv = [];
                foreach ($rows as $r) {
                    $niv = (string) ($r['niveau_hierarchie'] ?? '');
                    if ($niv === '' || isset($byNiv[$niv])) {
                        continue;
                    }
                    $byNiv[$niv] = $r;
                }
                if ($byNiv !== []) {
                    $defaults = [];
                    $ordreMap = ['zone' => 10, 'rayon' => 20, 'etagere' => 30, 'barre' => 40, 'position' => 50];
                    foreach ($ordreMap as $nivKey => $ord) {
                        if (!isset($byNiv[$nivKey])) {
                            continue;
                        }
                        $r = $byNiv[$nivKey];
                        $defaults[] = [
                            $nivKey,
                            (string) ($r['label'] ?? ucfirst($nivKey)),
                            (string) ($r['icon'] ?? 'fa-cube'),
                            $ord,
                        ];
                    }
                }
            } catch (PDOException $e) {
                // seed par défaut
            }
        }
        $ins = $db->prepare(
            'INSERT INTO entrepot_hierarchie_niveau (slug, label, icon, ordre, actif, date_creation)
             VALUES (:slug, :label, :icon, :ordre, 1, NOW())'
        );
        foreach ($defaults as $d) {
            $ins->execute([
                ':slug' => $d[0],
                ':label' => $d[1],
                ':icon' => $d[2],
                ':ordre' => $d[3],
            ]);
        }
        echo 'OK: seed ' . count($defaults) . " niveau(x).\n";
    } else {
        echo "— niveaux déjà présents ($countNiv).\n";
    }

    // Colonne produits.entrepot_noeud_id
    if (migrate_libre_table_existe($db, 'produits') && !migrate_libre_colonne_existe($db, 'produits', 'entrepot_noeud_id')) {
        $db->exec(
            'ALTER TABLE `produits`
             ADD COLUMN `entrepot_noeud_id` INT UNSIGNED NULL DEFAULT NULL AFTER `entrepot_position_id`'
        );
        try {
            $db->exec('ALTER TABLE `produits` ADD KEY `idx_produits_entrepot_noeud` (`entrepot_noeud_id`)');
        } catch (PDOException $e) {
            // ignore
        }
        echo "OK: produits.entrepot_noeud_id.\n";
    } else {
        echo "— produits.entrepot_noeud_id OK.\n";
    }

    // Reprise arbre legacy → nœuds (idempotente via legacy_table/legacy_id)
    $nivBySlug = [];
    foreach ($db->query('SELECT id, slug FROM entrepot_hierarchie_niveau')->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $nivBySlug[(string) $n['slug']] = (int) $n['id'];
    }

    $findNoeud = $db->prepare(
        'SELECT id FROM entrepot_hierarchie_noeud WHERE legacy_table = :t AND legacy_id = :i LIMIT 1'
    );
    $insNoeud = $db->prepare(
        'INSERT INTO entrepot_hierarchie_noeud
         (etage_id, niveau_id, parent_id, numero, nom, legacy_table, legacy_id, date_creation)
         VALUES (:e, :n, :p, :num, :nom, :lt, :lid, NOW())'
    );

    $ensureNoeud = function ($legacyTable, $legacyId, $etageId, $niveauId, $parentId, $numero, $nom) use ($findNoeud, $insNoeud, $db) {
        $legacyId = (int) $legacyId;
        if ($legacyId <= 0 || $niveauId <= 0 || $etageId <= 0) {
            return 0;
        }
        $findNoeud->execute([':t' => $legacyTable, ':i' => $legacyId]);
        $existing = (int) $findNoeud->fetchColumn();
        if ($existing > 0) {
            return $existing;
        }
        try {
            $insNoeud->execute([
                ':e' => (int) $etageId,
                ':n' => (int) $niveauId,
                ':p' => $parentId > 0 ? (int) $parentId : null,
                ':num' => max(1, (int) $numero),
                ':nom' => (string) $nom,
                ':lt' => $legacyTable,
                ':lid' => $legacyId,
            ]);

            return (int) $db->lastInsertId();
        } catch (PDOException $e) {
            $findNoeud->execute([':t' => $legacyTable, ':i' => $legacyId]);

            return (int) $findNoeud->fetchColumn();
        }
    };

    $created = 0;
    if (
        migrate_libre_table_existe($db, 'entrepot_etage')
        && migrate_libre_table_existe($db, 'entrepot_zone')
        && isset($nivBySlug['zone'])
    ) {
        $etages = $db->query('SELECT id FROM entrepot_etage ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($etages as $et) {
            $etageId = (int) $et['id'];
            $zones = $db->prepare('SELECT * FROM entrepot_zone WHERE etage_id = :e ORDER BY numero ASC');
            $zones->execute([':e' => $etageId]);
            foreach ($zones->fetchAll(PDO::FETCH_ASSOC) as $z) {
                $zid = (int) $z['id'];
                $zoneNoeud = $ensureNoeud(
                    'entrepot_zone',
                    $zid,
                    $etageId,
                    $nivBySlug['zone'],
                    0,
                    (int) ($z['numero'] ?? 1),
                    (string) ($z['nom'] ?? ('Zone ' . $zid))
                );
                if ($zoneNoeud > 0) {
                    $created++;
                }
                if (!isset($nivBySlug['rayon']) || !migrate_libre_table_existe($db, 'entrepot_rayon')) {
                    continue;
                }
                $rayons = $db->prepare('SELECT * FROM entrepot_rayon WHERE etage_id = :e AND zone_id = :z ORDER BY numero ASC');
                $rayons->execute([':e' => $etageId, ':z' => $zid]);
                foreach ($rayons->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rid = (int) $r['id'];
                    $rayonNoeud = $ensureNoeud(
                        'entrepot_rayon',
                        $rid,
                        $etageId,
                        $nivBySlug['rayon'],
                        $zoneNoeud,
                        (int) ($r['numero'] ?? 1),
                        (string) ($r['nom'] ?? ('Rayon ' . $rid))
                    );
                    if ($rayonNoeud > 0) {
                        $created++;
                    }
                    if (!isset($nivBySlug['etagere']) || !migrate_libre_table_existe($db, 'entrepot_etagere')) {
                        continue;
                    }
                    $etageres = $db->prepare('SELECT * FROM entrepot_etagere WHERE rayon_id = :r ORDER BY numero ASC');
                    $etageres->execute([':r' => $rid]);
                    foreach ($etageres->fetchAll(PDO::FETCH_ASSOC) as $eg) {
                        $egid = (int) $eg['id'];
                        $etagereNoeud = $ensureNoeud(
                            'entrepot_etagere',
                            $egid,
                            $etageId,
                            $nivBySlug['etagere'],
                            $rayonNoeud,
                            (int) ($eg['numero'] ?? 1),
                            (string) ($eg['nom'] ?? ('Étagère ' . $egid))
                        );
                        if ($etagereNoeud > 0) {
                            $created++;
                        }
                        if (!isset($nivBySlug['barre']) || !migrate_libre_table_existe($db, 'entrepot_barre')) {
                            continue;
                        }
                        $barres = $db->prepare('SELECT * FROM entrepot_barre WHERE etagere_id = :eg ORDER BY numero ASC');
                        $barres->execute([':eg' => $egid]);
                        foreach ($barres->fetchAll(PDO::FETCH_ASSOC) as $b) {
                            $bid = (int) $b['id'];
                            $barreNoeud = $ensureNoeud(
                                'entrepot_barre',
                                $bid,
                                $etageId,
                                $nivBySlug['barre'],
                                $etagereNoeud,
                                (int) ($b['numero'] ?? 1),
                                (string) ($b['nom'] ?? ('Barre ' . $bid))
                            );
                            if ($barreNoeud > 0) {
                                $created++;
                            }
                            if (!isset($nivBySlug['position']) || !migrate_libre_table_existe($db, 'entrepot_position')) {
                                continue;
                            }
                            $positions = $db->prepare('SELECT * FROM entrepot_position WHERE barre_id = :b ORDER BY numero ASC');
                            $positions->execute([':b' => $bid]);
                            foreach ($positions->fetchAll(PDO::FETCH_ASSOC) as $p) {
                                $pid = (int) $p['id'];
                                $posNoeud = $ensureNoeud(
                                    'entrepot_position',
                                    $pid,
                                    $etageId,
                                    $nivBySlug['position'],
                                    $barreNoeud,
                                    (int) ($p['numero'] ?? 1),
                                    (string) ($p['nom'] ?? ('Position ' . $pid))
                                );
                                if ($posNoeud > 0) {
                                    $created++;
                                }
                            }
                        }
                    }
                }
            }
        }
        echo "OK: reprise nœuds (passes ~$created).\n";
    }

    // Lier produits.entrepot_position_id → entrepot_noeud_id
    if (
        migrate_libre_colonne_existe($db, 'produits', 'entrepot_position_id')
        && migrate_libre_colonne_existe($db, 'produits', 'entrepot_noeud_id')
    ) {
        $upd = $db->exec(
            "UPDATE produits p
             INNER JOIN entrepot_hierarchie_noeud n
               ON n.legacy_table = 'entrepot_position' AND n.legacy_id = p.entrepot_position_id
             SET p.entrepot_noeud_id = n.id
             WHERE p.entrepot_position_id IS NOT NULL
               AND (p.entrepot_noeud_id IS NULL OR p.entrepot_noeud_id = 0)"
        );
        echo "OK: produits liés aux nœuds ($upd).\n";
    }

    echo "Migration hiérarchie libre terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur: ' . $e->getMessage() . "\n");
    exit(1);
}
