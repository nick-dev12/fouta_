<?php
/**
 * Référentiel entrepôt nommé (étage → position).
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_entrepot_emplacement.php';
require_once __DIR__ . '/model_entrepot_structure_champs.php';
require_once __DIR__ . '/model_produits.php';

/**
 * @return bool
 */
function entrepot_referentiel_tables_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM entrepot_etage LIMIT 1');
        $db->query('SELECT 1 FROM entrepot_barre LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @param int $numero_etage
 * @return array<string, mixed>|null
 */
function entrepot_get_etage_ref_by_numero($numero_etage) {
    global $db;
    $numero_etage = (int) $numero_etage;
    if ($numero_etage <= 0 || !entrepot_referentiel_tables_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM entrepot_etage WHERE numero_etage = :n LIMIT 1');
        $stmt->execute([':n' => $numero_etage]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function entrepot_get_etage_ref_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !entrepot_referentiel_tables_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM entrepot_etage WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Synchronise le référentiel nommé depuis les quantités configurées.
 *
 * @param int $numero_etage
 * @return array{success: bool, message: string, etage_id?: int}
 */
function entrepot_sync_referentiel_depuis_config($numero_etage) {
    require_once __DIR__ . '/model_entrepot_hierarchie.php';
    if (entrepot_hierarchie_schema_ok()) {
        $etage = entrepot_get_etage_ref_by_numero((int) $numero_etage);
        if ($etage === null) {
            return ['success' => false, 'message' => 'Niveau introuvable dans le référentiel.'];
        }

        return ['success' => true, 'message' => 'Référentiel CRUD actif (sync bulk désactivée).', 'etage_id' => (int) $etage['id']];
    }

    global $db;
    $numero_etage = (int) $numero_etage;
    if ($numero_etage <= 0 || !entrepot_referentiel_tables_ok() || !entrepot_emplacement_tables_ok()) {
        return ['success' => false, 'message' => 'Tables entrepôt absentes.'];
    }

    $cfg = entrepot_emplacement_get_etage($numero_etage);
    if ($cfg === null) {
        return ['success' => false, 'message' => 'Étage non configuré dans les quantités.'];
    }

    $nb_rayons = max(1, (int) ($cfg['nb_rayons'] ?? 1));
    $nb_allees = max(1, (int) ($cfg['nb_allees'] ?? 1));
    $nb_zones = max(1, (int) ($cfg['nb_zones'] ?? 1));
    $nb_barres = max(1, (int) ($cfg['nb_barres'] ?? 1));
    $nb_positions = max(1, (int) ($cfg['nb_positions'] ?? 1));

    try {
        $db->beginTransaction();

        $etage = entrepot_get_etage_ref_by_numero($numero_etage);
        if ($etage === null) {
            $code = 'E' . $numero_etage;
            $nom = 'Étage ' . $numero_etage;
            $stmt = $db->prepare(
                'INSERT INTO entrepot_etage (numero_etage, nom, code, actif, date_modification)
                 VALUES (:num, :nom, :code, 1, NOW())'
            );
            $stmt->execute([':num' => $numero_etage, ':nom' => $nom, ':code' => $code]);
            $etage_id = (int) $db->lastInsertId();
        } else {
            $etage_id = (int) $etage['id'];
        }

        if (entrepot_structure_champ_slug_actif('rayons')) {
            entrepot_sync_lignes_niveau($db, 'entrepot_rayon', $etage_id, $nb_rayons, 'Rayon', true);
        }
        if (entrepot_structure_champ_slug_actif('allees')) {
            entrepot_sync_lignes_niveau($db, 'entrepot_allee', $etage_id, $nb_allees, 'Allée', false);
        }
        if (entrepot_structure_champ_slug_actif('zones')) {
            entrepot_sync_zones($db, $etage_id, $nb_zones, $nb_rayons);
        }
        if (entrepot_structure_champ_slug_actif('barres')) {
            entrepot_sync_barres_par_rayon($db, $etage_id, $nb_barres);
        }
        if (entrepot_structure_champ_slug_actif('positions')) {
            entrepot_sync_positions_etage($db, $etage_id, $nb_positions);
        }

        entrepot_sync_champs_custom_etage($db, $etage_id, $numero_etage);
        entrepot_sync_lie_barre_barres($db, $etage_id);

        $db->commit();

        return ['success' => true, 'message' => 'Référentiel synchronisé.', 'etage_id' => $etage_id];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['success' => false, 'message' => 'Erreur sync : ' . $e->getMessage()];
    }
}

/**
 * @param PDO $db
 * @param string $table
 * @param int $etage_id
 * @param int $count
 * @param string $prefix
 * @param bool $with_code
 */
function entrepot_sync_lignes_niveau($db, $table, $etage_id, $count, $prefix, $with_code) {
    $existing = [];
    $stmt = $db->prepare("SELECT id, numero, nom FROM `$table` WHERE etage_id = :e ORDER BY numero ASC");
    $stmt->execute([':e' => $etage_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int) $row['numero']] = $row;
    }

    for ($i = 1; $i <= $count; $i++) {
        if (isset($existing[$i])) {
            continue;
        }
        $nom = $prefix . ' ' . $i;
        if ($with_code) {
            $ins = $db->prepare(
                "INSERT INTO `$table` (etage_id, numero, nom, code, date_modification) VALUES (:e, :n, :nom, :code, NOW())"
            );
            $ins->execute([':e' => $etage_id, ':n' => $i, ':nom' => $nom, ':code' => 'R' . $i]);
        } else {
            $ins = $db->prepare(
                "INSERT INTO `$table` (etage_id, numero, nom, date_modification) VALUES (:e, :n, :nom, NOW())"
            );
            $ins->execute([':e' => $etage_id, ':n' => $i, ':nom' => $nom]);
        }
    }

    $stmt_del = $db->prepare("DELETE FROM `$table` WHERE etage_id = :e AND numero > :max");
    $stmt_del->execute([':e' => $etage_id, ':max' => $count]);
}

/**
 * @param PDO $db
 * @param int $etage_id
 * @param int $nb_zones
 * @param int $nb_rayons
 */
function entrepot_sync_zones($db, $etage_id, $nb_zones, $nb_rayons) {
    $rayons = [];
    $st = $db->prepare('SELECT id, numero FROM entrepot_rayon WHERE etage_id = :e ORDER BY numero ASC');
    $st->execute([':e' => $etage_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rayons[] = $r;
    }

    $existing = [];
    $st2 = $db->prepare('SELECT id, numero FROM entrepot_zone WHERE etage_id = :e');
    $st2->execute([':e' => $etage_id]);
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $z) {
        $existing[(int) $z['numero']] = true;
    }

    for ($i = 1; $i <= $nb_zones; $i++) {
        if (isset($existing[$i])) {
            continue;
        }
        $rayon_id = null;
        if ($rayons !== []) {
            $idx = ($i - 1) % count($rayons);
            $rayon_id = (int) $rayons[$idx]['id'];
        }
        $ins = $db->prepare(
            'INSERT INTO entrepot_zone (etage_id, rayon_id, numero, nom, date_modification)
             VALUES (:e, :r, :n, :nom, NOW())'
        );
        $ins->execute([':e' => $etage_id, ':r' => $rayon_id, ':n' => $i, ':nom' => 'Zone ' . $i]);
    }

    $db->prepare('DELETE FROM entrepot_zone WHERE etage_id = :e AND numero > :max')
        ->execute([':e' => $etage_id, ':max' => $nb_zones]);
}

/**
 * Synchronise les barres : nb_barres = nombre de barres PAR RAYON.
 *
 * @param PDO $db
 * @param int $etage_id
 * @param int $nb_barres_par_rayon
 */
function entrepot_sync_barres_par_rayon($db, $etage_id, $nb_barres_par_rayon) {
    $nb_barres_par_rayon = max(1, (int) $nb_barres_par_rayon);
    $etage_id = (int) $etage_id;

    $st = $db->prepare('SELECT id, numero FROM entrepot_rayon WHERE etage_id = :e ORDER BY numero ASC');
    $st->execute([':e' => $etage_id]);
    $rayons = $st->fetchAll(PDO::FETCH_ASSOC);

    $rayon_ids = [];
    foreach ($rayons as $rayon) {
        $rayon_ids[] = (int) $rayon['id'];
    }

    foreach ($rayons as $rayon) {
        $rayon_id = (int) $rayon['id'];
        $existing = [];
        $stb = $db->prepare('SELECT id, numero FROM entrepot_barre WHERE rayon_id = :r');
        $stb->execute([':r' => $rayon_id]);
        foreach ($stb->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $existing[(int) $b['numero']] = (int) $b['id'];
        }

        for ($i = 1; $i <= $nb_barres_par_rayon; $i++) {
            if (isset($existing[$i])) {
                continue;
            }
            $ins = $db->prepare(
                'INSERT INTO entrepot_barre (etage_id, rayon_id, allee_id, zone_id, numero, nom, date_modification)
                 VALUES (:e, :r, NULL, NULL, :n, :nom, NOW())'
            );
            $ins->execute([
                ':e' => $etage_id,
                ':r' => $rayon_id,
                ':n' => $i,
                ':nom' => 'Barre ' . $i,
            ]);
            entrepot_barre_generer_code_scan((int) $db->lastInsertId());
        }

        $st_del = $db->prepare('SELECT id FROM entrepot_barre WHERE rayon_id = :r AND numero > :max');
        $st_del->execute([':r' => $rayon_id, ':max' => $nb_barres_par_rayon]);
        foreach ($st_del->fetchAll(PDO::FETCH_COLUMN) as $bid) {
            $db->prepare('DELETE FROM entrepot_barre WHERE id = :id')->execute([':id' => (int) $bid]);
        }
    }

    if ($rayon_ids !== []) {
        $placeholders = implode(',', array_fill(0, count($rayon_ids), '?'));
        $params = array_merge([$etage_id], $rayon_ids);
        $db->prepare(
            'DELETE FROM entrepot_barre WHERE etage_id = ? AND (rayon_id IS NULL OR rayon_id NOT IN (' . $placeholders . '))'
        )->execute($params);
    } else {
        $db->prepare('DELETE FROM entrepot_barre WHERE etage_id = :e')->execute([':e' => $etage_id]);
    }
}

/**
 * @deprecated Utiliser entrepot_sync_barres_par_rayon
 * @param PDO $db
 */
function entrepot_sync_barres($db, $etage_id, $nb_barres, $nb_rayons, $nb_allees, $nb_zones) {
    entrepot_sync_barres_par_rayon($db, $etage_id, $nb_barres);
}

/**
 * @param PDO $db
 * @param int $etage_id
 * @param int $nb_positions
 */
function entrepot_sync_positions_etage($db, $etage_id, $nb_positions) {
    $st = $db->prepare('SELECT id FROM entrepot_barre WHERE etage_id = :e ORDER BY numero ASC');
    $st->execute([':e' => $etage_id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $barre_id) {
        entrepot_sync_positions_barre($db, (int) $barre_id, $nb_positions);
    }
}

/**
 * @param PDO $db
 * @param int $barre_id
 * @param int $nb_positions
 */
function entrepot_sync_positions_barre($db, $barre_id, $nb_positions) {
    $existing = [];
    $st = $db->prepare('SELECT numero FROM entrepot_position WHERE barre_id = :b');
    $st->execute([':b' => $barre_id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $num) {
        $existing[(int) $num] = true;
    }
    for ($i = 1; $i <= $nb_positions; $i++) {
        if (isset($existing[$i])) {
            continue;
        }
        $db->prepare(
            'INSERT INTO entrepot_position (barre_id, numero, nom, date_modification) VALUES (:b, :n, :nom, NOW())'
        )->execute([':b' => $barre_id, ':n' => $i, ':nom' => 'Position ' . $i]);
    }
    $db->prepare('DELETE FROM entrepot_position WHERE barre_id = :b AND numero > :max')
        ->execute([':b' => $barre_id, ':max' => $nb_positions]);
}

/**
 * @param PDO $db
 * @param string $table
 * @param int $etage_id
 * @return array<int, int> numero => id
 */
function entrepot_fetch_ids_by_numero($db, $table, $etage_id) {
    $out = [];
    $st = $db->prepare("SELECT id, numero FROM `$table` WHERE etage_id = :e");
    $st->execute([':e' => $etage_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['numero']] = (int) $row['id'];
    }

    return $out;
}

/**
 * @param array<int, int> $map
 * @param int $numero
 * @param int $max
 * @return int|null
 */
function entrepot_pick_id_for_numero(array $map, $numero, $max) {
    if ($map === [] || $max <= 0) {
        return null;
    }
    $n = (($numero - 1) % $max) + 1;

    return $map[$n] ?? null;
}

/**
 * Valeur du champ nom barre en formulaire (vide si nom par défaut).
 */
function entrepot_barre_nom_valeur_formulaire($nom, $numero) {
    $nom = trim((string) $nom);
    $numero = (int) $numero;
    if ($nom === '' || $nom === 'Barre ' . $numero) {
        return '';
    }

    return $nom;
}

/**
 * Libellé étiquette barre type maquette « B01-01 » (code étage + n° rayon + n° barre).
 *
 * @param array<string,mixed> $barre
 * @param array<string,mixed>|null $etage
 * @param array<string,mixed>|null $rayon
 */
function entrepot_barre_etiquette_libelle(array $barre, $etage = null, $rayon = null) {
    $code = '';
    if (is_array($etage)) {
        if (!empty($etage['code_abrege'])) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $etage['code_abrege']));
        } elseif (!empty($etage['code'])) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $etage['code']));
        }
    }
    if ($code === '') {
        $code = 'B';
    }
    if (strlen($code) > 10) {
        $code = substr($code, 0, 10);
    }

    $num_rayon = 1;
    if (is_array($rayon) && isset($rayon['numero'])) {
        $num_rayon = max(1, (int) $rayon['numero']);
    } elseif (!empty($barre['rayon_id'])) {
        global $db;
        if ($db) {
            $st = $db->prepare('SELECT numero FROM entrepot_rayon WHERE id = :id LIMIT 1');
            $st->execute([':id' => (int) $barre['rayon_id']]);
            $nr = $st->fetchColumn();
            if ($nr !== false) {
                $num_rayon = max(1, (int) $nr);
            }
        }
    }

    $num_barre = max(1, (int) ($barre['numero'] ?? 1));

    return sprintf('%s%02d-%02d', $code, $num_rayon, $num_barre);
}

/**
 * Regroupe les barres par rayon_id.
 *
 * @param array<int, array<string,mixed>> $barres
 * @return array<int, array<int, array<string,mixed>>>
 */
function entrepot_barres_grouper_par_rayon(array $barres) {
    $out = [];
    foreach ($barres as $b) {
        $rid = (int) ($b['rayon_id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        if (!isset($out[$rid])) {
            $out[$rid] = [];
        }
        $out[$rid][] = $b;
    }
    foreach ($out as $rid => $list) {
        usort($list, function ($a, $b) {
            return (int) ($a['numero'] ?? 0) <=> (int) ($b['numero'] ?? 0);
        });
        $out[$rid] = $list;
    }

    return $out;
}

/**
 * Regroupe les barres par élément de champ lié (champ_element_id).
 *
 * @param array<int, array<string,mixed>> $barres
 * @return array{by_element: array<int, array<int, array<string,mixed>>>, unassigned: array<int, array<string,mixed>>}
 */
function entrepot_barres_grouper_par_champ_element(array $barres) {
    $by_element = [];
    $unassigned = [];
    foreach ($barres as $b) {
        $eid = (int) ($b['champ_element_id'] ?? 0);
        if ($eid > 0) {
            if (!isset($by_element[$eid])) {
                $by_element[$eid] = [];
            }
            $by_element[$eid][] = $b;
        } else {
            $unassigned[] = $b;
        }
    }
    $sort_fn = function ($a, $b) {
        return (int) ($a['numero'] ?? 0) <=> (int) ($b['numero'] ?? 0);
    };
    foreach ($by_element as $eid => $list) {
        usort($list, $sort_fn);
        $by_element[$eid] = $list;
    }
    usort($unassigned, $sort_fn);

    return ['by_element' => $by_element, 'unassigned' => $unassigned];
}

/**
 * Lie automatiquement les barres aux éléments du champ lie_barre (même principe que rayons).
 * Élément #N reçoit toutes les barres du rayon #N.
 *
 * @param PDO $db
 * @param int $etage_id
 */
function entrepot_sync_lie_barre_barres($db, $etage_id) {
    entrepot_barre_ensure_champ_element_schema();
    $lie = entrepot_structure_champ_get_lie_barre();
    if ($lie === null || !entrepot_structure_champ_slug_actif('barres')) {
        return;
    }

    $etage_id = (int) $etage_id;
    $champ_id = (int) ($lie['id'] ?? 0);
    if ($etage_id <= 0 || $champ_id <= 0) {
        return;
    }

    $st_el = $db->prepare(
        'SELECT id, numero FROM entrepot_champ_element WHERE etage_id = :e AND champ_id = :c ORDER BY numero ASC'
    );
    $st_el->execute([':e' => $etage_id, ':c' => $champ_id]);
    $elements = $st_el->fetchAll(PDO::FETCH_ASSOC);
    if ($elements === []) {
        return;
    }

    $rayons = [];
    $st_r = $db->prepare('SELECT id, numero FROM entrepot_rayon WHERE etage_id = :e ORDER BY numero ASC');
    $st_r->execute([':e' => $etage_id]);
    foreach ($st_r->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rayons[(int) $r['numero']] = (int) $r['id'];
    }
    if ($rayons === []) {
        return;
    }

    $rayon_numeros = array_keys($rayons);
    sort($rayon_numeros, SORT_NUMERIC);
    $nb_rayons = count($rayon_numeros);

    $upd = $db->prepare(
        'UPDATE entrepot_barre SET champ_element_id = :ce, date_modification = NOW()
         WHERE etage_id = :e AND rayon_id = :r'
    );

    foreach ($elements as $el) {
        $el_id = (int) ($el['id'] ?? 0);
        $el_num = (int) ($el['numero'] ?? 0);
        if ($el_id <= 0 || $el_num <= 0) {
            continue;
        }
        $rayon_id = $rayons[$el_num] ?? null;
        if ($rayon_id === null && $nb_rayons > 0) {
            $idx = ($el_num - 1) % $nb_rayons;
            $rayon_num = $rayon_numeros[$idx];
            $rayon_id = $rayons[$rayon_num];
        }
        if ($rayon_id === null || $rayon_id <= 0) {
            continue;
        }
        $upd->execute([':ce' => $el_id, ':e' => $etage_id, ':r' => $rayon_id]);
    }
}

/**
 * Réassigne les barres lie_barre si aucune n’est encore liée (migration douce).
 *
 * @param int $etage_id
 */
function entrepot_maybe_sync_lie_barre_barres($etage_id) {
    global $db;
    entrepot_barre_ensure_champ_element_schema();
    if (!entrepot_structure_champ_get_lie_barre() || !entrepot_referentiel_tables_ok()) {
        return;
    }
    $etage_id = (int) $etage_id;
    if ($etage_id <= 0) {
        return;
    }
    try {
        $st = $db->prepare(
            'SELECT COUNT(*) FROM entrepot_barre WHERE etage_id = :e AND champ_element_id IS NOT NULL AND champ_element_id > 0'
        );
        $st->execute([':e' => $etage_id]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        entrepot_sync_lie_barre_barres($db, $etage_id);
    } catch (PDOException $e) {
        return;
    }
}

/**
 * @param int $barre_id
 * @return string|null
 */
function entrepot_barre_generer_code_scan($barre_id) {
    global $db;
    $barre_id = (int) $barre_id;
    if ($barre_id <= 0 || !entrepot_referentiel_tables_ok()) {
        return null;
    }

    $st = $db->prepare('SELECT code_scan FROM entrepot_barre WHERE id = :id LIMIT 1');
    $st->execute([':id' => $barre_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['code_scan'])) {
        return (string) $row['code_scan'];
    }

    try {
        $own_tx = !$db->inTransaction();
        if ($own_tx) {
            $db->beginTransaction();
        }
        $db->exec('INSERT INTO entrepot_barre_code_seq (id, dernier_numero) VALUES (1, 0)
            ON DUPLICATE KEY UPDATE id = id');
        $db->exec('UPDATE entrepot_barre_code_seq SET dernier_numero = dernier_numero + 1 WHERE id = 1');
        $seq = (int) $db->query('SELECT dernier_numero FROM entrepot_barre_code_seq WHERE id = 1')->fetchColumn();
        $code = 'FOUTA-BAR-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        $db->prepare('UPDATE entrepot_barre SET code_scan = :c, date_modification = NOW() WHERE id = :id')
            ->execute([':c' => $code, ':id' => $barre_id]);
        entrepot_barre_refresh_chemin_libelle($barre_id, $db);
        if ($own_tx) {
            $db->commit();
        }

        return $code;
    } catch (PDOException $e) {
        if (isset($own_tx) && $own_tx && $db->inTransaction()) {
            $db->rollBack();
        }

        return null;
    }
}

/**
 * @param int $barre_id
 * @param PDO|null $db_override
 */
function entrepot_barre_refresh_chemin_libelle($barre_id, $db_override = null) {
    global $db;
    $conn = $db_override ?: $db;
    $barre_id = (int) $barre_id;
    $chemin = entrepot_build_chemin_barre($barre_id);
    if ($chemin === '') {
        return;
    }
    $conn->prepare('UPDATE entrepot_barre SET chemin_libelle = :c, date_modification = NOW() WHERE id = :id')
        ->execute([':c' => $chemin, ':id' => $barre_id]);
}

/**
 * @param int $barre_id
 * @return string
 */
function entrepot_build_chemin_barre($barre_id) {
    global $db;
    $barre_id = (int) $barre_id;
    if ($barre_id <= 0) {
        return '';
    }
    $sql = 'SELECT b.nom AS barre_nom, e.nom AS etage_nom, r.nom AS rayon_nom, a.nom AS allee_nom, z.nom AS zone_nom
            FROM entrepot_barre b
            INNER JOIN entrepot_etage e ON e.id = b.etage_id
            LEFT JOIN entrepot_rayon r ON r.id = b.rayon_id
            LEFT JOIN entrepot_allee a ON a.id = b.allee_id
            LEFT JOIN entrepot_zone z ON z.id = b.zone_id
            WHERE b.id = :id LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $barre_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '';
    }
    $parts = array_filter([
        $row['etage_nom'] ?? '',
        $row['rayon_nom'] ?? '',
        $row['allee_nom'] ?? '',
        $row['zone_nom'] ?? '',
        $row['barre_nom'] ?? '',
    ]);

    return implode(' · ', $parts);
}

/**
 * @param int $position_id
 * @return array<string, mixed>
 */
function entrepot_get_position_meta($position_id) {
    global $db;
    $position_id = (int) $position_id;
    $empty = [
        'position_id' => 0,
        'barre_id' => 0,
        'rayon_id' => 0,
        'allee_id' => 0,
        'zone_id' => 0,
        'numero_etage' => 0,
        'position_num' => 0,
    ];
    if ($position_id <= 0 || !entrepot_referentiel_tables_ok()) {
        return $empty;
    }
    $sql = 'SELECT p.id AS position_id, p.numero AS position_num,
                   b.id AS barre_id, b.rayon_id, b.allee_id, b.zone_id, e.numero_etage
            FROM entrepot_position p
            INNER JOIN entrepot_barre b ON b.id = p.barre_id
            INNER JOIN entrepot_etage e ON e.id = b.etage_id
            WHERE p.id = :id LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $position_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $empty;
    }

    return [
        'position_id' => (int) $row['position_id'],
        'barre_id' => (int) $row['barre_id'],
        'rayon_id' => (int) ($row['rayon_id'] ?? 0),
        'allee_id' => (int) ($row['allee_id'] ?? 0),
        'zone_id' => (int) ($row['zone_id'] ?? 0),
        'numero_etage' => (int) ($row['numero_etage'] ?? 0),
        'position_num' => (int) ($row['position_num'] ?? 0),
    ];
}

/**
 * @param int $position_id
 * @return array<string, mixed>
 */
function entrepot_get_chemin_complet($position_id) {
    global $db;
    $position_id = (int) $position_id;
    $empty = ['libelle' => '', 'position_id' => 0, 'barre_id' => 0, 'code_scan' => ''];
    if ($position_id <= 0 || !entrepot_referentiel_tables_ok()) {
        return $empty;
    }
    $sql = 'SELECT p.id AS position_id, p.numero AS position_num, p.nom AS position_nom,
                   b.id AS barre_id, b.nom AS barre_nom, b.code_scan,
                   e.nom AS etage_nom, e.numero_etage, r.nom AS rayon_nom, a.nom AS allee_nom, z.nom AS zone_nom
            FROM entrepot_position p
            INNER JOIN entrepot_barre b ON b.id = p.barre_id
            INNER JOIN entrepot_etage e ON e.id = b.etage_id
            LEFT JOIN entrepot_rayon r ON r.id = b.rayon_id
            LEFT JOIN entrepot_allee a ON a.id = b.allee_id
            LEFT JOIN entrepot_zone z ON z.id = b.zone_id
            WHERE p.id = :id LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $position_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $empty;
    }
    $parts = array_filter([
        $row['etage_nom'] ?? '',
        $row['rayon_nom'] ?? '',
        $row['allee_nom'] ?? '',
        $row['zone_nom'] ?? '',
        $row['barre_nom'] ?? '',
        $row['position_nom'] ?? '',
    ]);

    return [
        'libelle' => implode(' · ', $parts),
        'position_id' => (int) $row['position_id'],
        'barre_id' => (int) $row['barre_id'],
        'code_scan' => (string) ($row['code_scan'] ?? ''),
        'position_num' => (int) ($row['position_num'] ?? 0),
        'position_nom' => (string) ($row['position_nom'] ?? ''),
        'numero_etage' => (int) ($row['numero_etage'] ?? 0),
    ];
}

/**
 * @param int $barre_id
 * @return array<int, array<string, mixed>>
 */
function entrepot_produits_par_barre($barre_id) {
    global $db;
    $barre_id = (int) $barre_id;
    if ($barre_id <= 0 || !produits_has_column('entrepot_position_id')) {
        return [];
    }
    try {
        $sql = 'SELECT p.*, pos.nom AS position_nom, pos.numero AS position_num
                FROM produits p
                INNER JOIN entrepot_position pos ON pos.id = p.entrepot_position_id
                WHERE pos.barre_id = :bid
                ORDER BY pos.numero ASC, p.nom ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute([':bid' => $barre_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param string $code_scan
 * @return array<string, mixed>|null
 */
function entrepot_get_barre_by_code_scan($code_scan) {
    global $db;
    $code_scan = strtoupper(trim((string) $code_scan));
    if ($code_scan === '' || !entrepot_referentiel_tables_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM entrepot_barre WHERE code_scan = :c LIMIT 1');
        $stmt->execute([':c' => $code_scan]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param int $barre_id
 * @return array<string, mixed>|null
 */
function entrepot_get_barre_by_id($barre_id) {
    global $db;
    $barre_id = (int) $barre_id;
    if ($barre_id <= 0) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM entrepot_barre WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $barre_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param string $code_scan
 * @return array{barre: array<string, mixed>|null, produits: array<int, array<string, mixed>>}
 */
function entrepot_produits_par_code_barre($code_scan) {
    $barre = entrepot_get_barre_by_code_scan($code_scan);
    if ($barre === null) {
        return ['barre' => null, 'contexte' => null, 'produits' => []];
    }
    $barre_id = (int) $barre['id'];

    return [
        'barre' => $barre,
        'contexte' => entrepot_get_barre_contexte($barre_id),
        'produits' => entrepot_produits_par_barre($barre_id),
    ];
}

/**
 * Contexte nommé d’une barre (étage, rayon, allée, zone).
 *
 * @param int $barre_id
 * @return array<string, mixed>|null
 */
function entrepot_get_barre_contexte($barre_id) {
    global $db;
    $barre_id = (int) $barre_id;
    if ($barre_id <= 0 || !entrepot_referentiel_tables_ok()) {
        return null;
    }
    try {
        $sql = 'SELECT b.id AS barre_id, b.numero AS barre_num, b.nom AS barre_nom, b.code_scan,
                       e.id AS etage_id, e.numero_etage, e.nom AS etage_nom, e.code AS etage_code,
                       r.id AS rayon_id, r.numero AS rayon_num, r.nom AS rayon_nom,
                       a.id AS allee_id, a.numero AS allee_num, a.nom AS allee_nom,
                       z.id AS zone_id, z.numero AS zone_num, z.nom AS zone_nom
                FROM entrepot_barre b
                INNER JOIN entrepot_etage e ON e.id = b.etage_id
                LEFT JOIN entrepot_rayon r ON r.id = b.rayon_id
                LEFT JOIN entrepot_allee a ON a.id = b.allee_id
                LEFT JOIN entrepot_zone z ON z.id = b.zone_id
                WHERE b.id = :id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $barre_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Charge le référentiel complet d’un étage pour édition / JSON produit.
 *
 * @param int $numero_etage
 * @return array<string, mixed>|null
 */
function entrepot_get_referentiel_etage_complet($numero_etage) {
    global $db;
    $numero_etage = (int) $numero_etage;
    entrepot_sync_referentiel_depuis_config($numero_etage);
    $etage = entrepot_get_etage_ref_by_numero($numero_etage);
    if ($etage === null) {
        return null;
    }
    $etage_id = (int) $etage['id'];
    entrepot_maybe_sync_lie_barre_barres($etage_id);

    $fetch = function ($table, $extra = '') use ($db, $etage_id) {
        $sql = "SELECT * FROM `$table` WHERE etage_id = :e ORDER BY numero ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':e' => $etage_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    };

    $barres = [];
    $st = $db->prepare(
        'SELECT b.* FROM entrepot_barre b
         INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
         WHERE b.etage_id = :e
         ORDER BY r.numero ASC, b.numero ASC'
    );
    $st->execute([':e' => $etage_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $bid = (int) $b['id'];
        $stp = $db->prepare('SELECT * FROM entrepot_position WHERE barre_id = :b ORDER BY numero ASC');
        $stp->execute([':b' => $bid]);
        $b['positions'] = $stp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $barres[] = $b;
    }

    $barres_par_rayon = entrepot_barres_grouper_par_rayon($barres);

    $champs_custom = [];
    foreach (entrepot_structure_champs_custom_list() as $ch) {
        if ((int) ($ch['lie_barre'] ?? 0) === 1) {
            continue;
        }
        $cid = (int) ($ch['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $champs_custom[$cid] = [
            'label' => (string) ($ch['label'] ?? ''),
            'icon' => (string) ($ch['icon'] ?? 'fa-cube'),
            'elements' => entrepot_get_champ_elements_etage($etage_id, $cid),
        ];
    }

    $lie_barre = entrepot_structure_champ_get_lie_barre();
    $lie_barre_block = null;
    if ($lie_barre !== null) {
        $lie_barre_block = [
            'champ_id' => (int) $lie_barre['id'],
            'label' => (string) ($lie_barre['label'] ?? ''),
            'icon' => (string) ($lie_barre['icon'] ?? 'fa-cube'),
            'elements' => entrepot_get_champ_elements_etage($etage_id, (int) $lie_barre['id']),
        ];
    }

    return [
        'etage' => $etage,
        'rayons' => entrepot_structure_champ_slug_actif('rayons') ? $fetch('entrepot_rayon') : [],
        'allees' => entrepot_structure_champ_slug_actif('allees') ? $fetch('entrepot_allee') : [],
        'zones' => entrepot_structure_champ_slug_actif('zones') ? $fetch('entrepot_zone') : [],
        'barres' => $barres,
        'barres_par_rayon' => $barres_par_rayon,
        'champs_custom' => $champs_custom,
        'lie_barre' => $lie_barre_block,
    ];
}

/**
 * @param int $numero_etage
 * @param array<string, mixed> $post
 * @return array{success: bool, message: string}
 */
function entrepot_enregistrer_referentiel_etage($numero_etage, array $post) {
    global $db;
    $numero_etage = (int) $numero_etage;

    if (isset($post['nom_etage']) || entrepot_structure_champs_valeurs_depuis_post($post) !== []) {
        $qres = entrepot_emplacement_enregistrer_quantites_etage($numero_etage, $post);
        if (!$qres['success']) {
            return $qres;
        }
    }

    $sync = entrepot_sync_referentiel_depuis_config($numero_etage);
    if (!$sync['success']) {
        return $sync;
    }
    $etage = entrepot_get_etage_ref_by_numero($numero_etage);
    if ($etage === null) {
        return ['success' => false, 'message' => 'Étage introuvable.'];
    }
    $etage_id = (int) $etage['id'];

    try {
        $db->beginTransaction();

        $nom_etage = trim((string) ($post['nom_etage'] ?? $etage['nom']));
        $code_etage = trim((string) ($post['code_etage'] ?? $etage['code']));
        if ($nom_etage === '') {
            $nom_etage = 'Étage ' . $numero_etage;
        }
        if ($code_etage === '') {
            $code_etage = 'E' . $numero_etage;
        }
        $db->prepare('UPDATE entrepot_etage SET nom = :nom, code = :code, date_modification = NOW() WHERE id = :id')
            ->execute([':nom' => $nom_etage, ':code' => $code_etage, ':id' => $etage_id]);

        entrepot_update_noms_table($db, 'entrepot_rayon', $etage_id, $post['rayons'] ?? [], 'code');
        entrepot_update_noms_table($db, 'entrepot_allee', $etage_id, $post['allees'] ?? [], null);
        entrepot_update_noms_table($db, 'entrepot_zone', $etage_id, $post['zones'] ?? [], null);
        entrepot_update_noms_champs_custom($db, $etage_id, isset($post['champs_custom']) && is_array($post['champs_custom']) ? $post['champs_custom'] : []);

        if (isset($post['barres']) && is_array($post['barres'])) {
            foreach ($post['barres'] as $bid => $bdata) {
                if (!is_array($bdata)) {
                    continue;
                }
                $bid = (int) $bid;
                $nom = trim((string) ($bdata['nom'] ?? ''));
                $existing_barre = entrepot_get_barre_by_id($bid);
                if ($existing_barre === null) {
                    continue;
                }
                if ($nom === '') {
                    $nom = 'Barre ' . (int) ($existing_barre['numero'] ?? 0);
                }
                $rayon_id = isset($bdata['rayon_id']) && $bdata['rayon_id'] !== '' ? (int) $bdata['rayon_id'] : null;
                if ($rayon_id === null && !empty($existing_barre['rayon_id'])) {
                    $rayon_id = (int) $existing_barre['rayon_id'];
                }
                $allee_id = isset($bdata['allee_id']) && $bdata['allee_id'] !== '' ? (int) $bdata['allee_id'] : null;
                $zone_id = isset($bdata['zone_id']) && $bdata['zone_id'] !== '' ? (int) $bdata['zone_id'] : null;
                $champ_element_id = isset($bdata['champ_element_id']) && $bdata['champ_element_id'] !== ''
                    ? (int) $bdata['champ_element_id']
                    : null;
                $db->prepare(
                    'UPDATE entrepot_barre SET nom = :nom, rayon_id = :r, allee_id = :a, zone_id = :z,
                     champ_element_id = :ce, date_modification = NOW() WHERE id = :id AND etage_id = :e'
                )->execute([
                    ':nom' => $nom,
                    ':r' => $rayon_id,
                    ':a' => $allee_id,
                    ':z' => $zone_id,
                    ':ce' => ($champ_element_id !== null && $champ_element_id > 0) ? $champ_element_id : null,
                    ':id' => $bid,
                    ':e' => $etage_id,
                ]);
                entrepot_barre_refresh_chemin_libelle($bid, $db);
                if (empty($existing_barre['code_scan'])) {
                    entrepot_barre_generer_code_scan($bid);
                }
                if (isset($bdata['positions']) && is_array($bdata['positions'])) {
                    foreach ($bdata['positions'] as $pid => $pdata) {
                        if (!is_array($pdata)) {
                            continue;
                        }
                        $pid = (int) $pid;
                        $pnom = trim((string) ($pdata['nom'] ?? ''));
                        if ($pid <= 0 || $pnom === '') {
                            continue;
                        }
                        $db->prepare(
                            'UPDATE entrepot_position SET nom = :nom, date_modification = NOW()
                             WHERE id = :id AND barre_id = :b'
                        )->execute([':nom' => $pnom, ':id' => $pid, ':b' => $bid]);
                    }
                }
            }
        }

        $db->commit();

        return ['success' => true, 'message' => 'Étage ' . $numero_etage . ' enregistré (structure et noms).'];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param PDO $db
 * @param string $table
 * @param int $etage_id
 * @param array<int|string, mixed> $rows
 * @param string|null $code_field
 */
function entrepot_update_noms_table($db, $table, $etage_id, array $rows, $code_field) {
    foreach ($rows as $id => $data) {
        if (!is_array($data)) {
            continue;
        }
        $id = (int) $id;
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($id <= 0 || $nom === '') {
            continue;
        }
        if ($code_field !== null && isset($data['code'])) {
            $code = trim((string) $data['code']);
            $db->prepare("UPDATE `$table` SET nom = :nom, `$code_field` = :code, date_modification = NOW() WHERE id = :id AND etage_id = :e")
                ->execute([':nom' => $nom, ':code' => $code !== '' ? $code : null, ':id' => $id, ':e' => $etage_id]);
        } else {
            $db->prepare("UPDATE `$table` SET nom = :nom, date_modification = NOW() WHERE id = :id AND etage_id = :e")
                ->execute([':nom' => $nom, ':id' => $id, ':e' => $etage_id]);
        }
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function entrepot_get_referentiel_json_produit() {
    require_once __DIR__ . '/model_entrepot_hierarchie.php';
    if (entrepot_hierarchie_schema_ok()) {
        return entrepot_hierarchie_json_produit();
    }

    $out = [];
    if (!entrepot_emplacement_est_configure() || !entrepot_referentiel_tables_ok()) {
        return $out;
    }
    foreach (entrepot_emplacement_get_all_etages() as $row) {
        $n = (int) ($row['numero_etage'] ?? 0);
        if ($n <= 0) {
            continue;
        }
        $ref = entrepot_get_referentiel_etage_complet($n);
        if ($ref !== null) {
            $out[$n] = $ref;
        }
    }

    return $out;
}

/**
 * Résout entrepot_position_id depuis colonnes legacy numériques.
 *
 * @param array<string, mixed> $produit
 * @return int|null
 */
function entrepot_resolve_position_id_from_legacy(array $produit) {
    if (!entrepot_referentiel_tables_ok()) {
        return null;
    }
    $etage_n = isset($produit['etage']) && ctype_digit((string) $produit['etage']) ? (int) $produit['etage'] : 0;
    if ($etage_n <= 0) {
        return null;
    }
    entrepot_sync_referentiel_depuis_config($etage_n);
    $etage = entrepot_get_etage_ref_by_numero($etage_n);
    if ($etage === null) {
        return null;
    }
    $etage_id = (int) $etage['id'];

    global $db;
    $barre_n = isset($produit['barre_rayon']) && $produit['barre_rayon'] !== '' ? (int) $produit['barre_rayon'] : 0;
    $pos_n = isset($produit['position_emplacement']) && $produit['position_emplacement'] !== '' ? (int) $produit['position_emplacement'] : 0;
    if ($barre_n <= 0) {
        return null;
    }

    $rayon_n = isset($produit['numero_rayon']) && $produit['numero_rayon'] !== '' ? (int) $produit['numero_rayon'] : 0;
    $barre_id = 0;

    if ($rayon_n > 0) {
        $st = $db->prepare('SELECT id FROM entrepot_rayon WHERE etage_id = :e AND numero = :n LIMIT 1');
        $st->execute([':e' => $etage_id, ':n' => $rayon_n]);
        $rayon_id = (int) $st->fetchColumn();
        if ($rayon_id > 0) {
            $st = $db->prepare('SELECT id FROM entrepot_barre WHERE rayon_id = :r AND numero = :n LIMIT 1');
            $st->execute([':r' => $rayon_id, ':n' => $barre_n]);
            $barre_id = (int) $st->fetchColumn();
        }
    }

    if ($barre_id <= 0) {
        $st = $db->prepare('SELECT id FROM entrepot_barre WHERE etage_id = :e AND numero = :n LIMIT 1');
        $st->execute([':e' => $etage_id, ':n' => $barre_n]);
        $barre_id = (int) $st->fetchColumn();
    }
    if ($barre_id <= 0) {
        return null;
    }

    if ($pos_n <= 0) {
        $pos_n = 1;
    }
    $stp = $db->prepare('SELECT id FROM entrepot_position WHERE barre_id = :b AND numero = :n LIMIT 1');
    $stp->execute([':b' => $barre_id, ':n' => $pos_n]);
    $pid = (int) $stp->fetchColumn();

    return $pid > 0 ? $pid : null;
}

/**
 * @param int $produit_id
 * @param int|null $position_id
 * @return bool
 */
function entrepot_assign_produit_position($produit_id, $position_id) {
    global $db;
    if (!produits_has_column('entrepot_position_id')) {
        return false;
    }
    $produit_id = (int) $produit_id;
    $position_id = $position_id !== null ? (int) $position_id : null;
    if ($produit_id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE produits SET entrepot_position_id = :pid WHERE id = :id');
        $stmt->execute([':pid' => $position_id > 0 ? $position_id : null, ':id' => $produit_id]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $position_id
 * @return array<string, string|null>
 */
function entrepot_legacy_columns_from_position_id($position_id) {
    global $db;
    $position_id = (int) $position_id;
    $out = [
        'etage' => null,
        'numero_rayon' => null,
        'allee' => null,
        'zone_emplacement' => null,
        'position_emplacement' => null,
        'barre_rayon' => null,
    ];
    if ($position_id <= 0) {
        return $out;
    }
    $sql = 'SELECT e.numero_etage, r.numero AS rayon_num, a.numero AS allee_num, z.numero AS zone_num,
                   b.numero AS barre_num, p.numero AS position_num
            FROM entrepot_position p
            INNER JOIN entrepot_barre b ON b.id = p.barre_id
            INNER JOIN entrepot_etage e ON e.id = b.etage_id
            LEFT JOIN entrepot_rayon r ON r.id = b.rayon_id
            LEFT JOIN entrepot_allee a ON a.id = b.allee_id
            LEFT JOIN entrepot_zone z ON z.id = b.zone_id
            WHERE p.id = :id LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $position_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $out;
    }
    $out['etage'] = (string) (int) $row['numero_etage'];
    if (!empty($row['rayon_num'])) {
        $out['numero_rayon'] = (string) (int) $row['rayon_num'];
    }
    if (!empty($row['allee_num'])) {
        $out['allee'] = (string) (int) $row['allee_num'];
    }
    if (!empty($row['zone_num'])) {
        $out['zone_emplacement'] = (string) (int) $row['zone_num'];
    }
    $out['barre_rayon'] = (string) (int) $row['barre_num'];
    $out['position_emplacement'] = (string) (int) $row['position_num'];

    return $out;
}

/**
 * Définit manuellement les liens rayon / allée / zone d’une barre (choix indépendants).
 *
 * @param int $barre_id
 * @param int|null $rayon_id
 * @param int|null $allee_id
 * @param int|null $zone_id
 * @return bool
 */
function entrepot_barre_definir_liens($barre_id, $rayon_id = null, $allee_id = null, $zone_id = null) {
    global $db;
    $barre_id = (int) $barre_id;
    if ($barre_id <= 0 || !entrepot_referentiel_tables_ok()) {
        return false;
    }
    $barre = entrepot_get_barre_by_id($barre_id);
    if ($barre === null) {
        return false;
    }
    $etage_id = (int) ($barre['etage_id'] ?? 0);

    $rayon_id = $rayon_id !== null ? (int) $rayon_id : 0;
    $allee_id = $allee_id !== null ? (int) $allee_id : 0;
    $zone_id = $zone_id !== null ? (int) $zone_id : 0;

    // Ne met à jour que les liens explicitement fournis (> 0)
    $sets = [];
    $params = [':id' => $barre_id];
    if ($rayon_id > 0) {
        $st = $db->prepare('SELECT id FROM entrepot_rayon WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $rayon_id, ':e' => $etage_id]);
        if ((int) $st->fetchColumn() > 0) {
            $sets[] = 'rayon_id = :rayon_id';
            $params[':rayon_id'] = $rayon_id;
        }
    }
    if ($allee_id > 0) {
        $st = $db->prepare('SELECT id FROM entrepot_allee WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $allee_id, ':e' => $etage_id]);
        if ((int) $st->fetchColumn() > 0) {
            $sets[] = 'allee_id = :allee_id';
            $params[':allee_id'] = $allee_id;
        }
    }
    if ($zone_id > 0) {
        $st = $db->prepare('SELECT id FROM entrepot_zone WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $zone_id, ':e' => $etage_id]);
        if ((int) $st->fetchColumn() > 0) {
            $sets[] = 'zone_id = :zone_id';
            $params[':zone_id'] = $zone_id;
        }
    }
    if ($sets === []) {
        return true;
    }
    $sets[] = 'date_modification = NOW()';
    try {
        $db->prepare('UPDATE entrepot_barre SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        entrepot_barre_refresh_chemin_libelle($barre_id, $db);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime le référentiel nommé d’un étage (cascade BDD + entrepot_position_id → NULL).
 *
 * @param int $numero_etage
 * @param PDO|null $db_override
 * @return bool
 */
function entrepot_supprimer_referentiel_etage($numero_etage, $db_override = null) {
    global $db;
    $pdo = $db_override ?? $db;
    $numero_etage = (int) $numero_etage;
    if ($numero_etage <= 0 || !entrepot_referentiel_tables_ok()) {
        return false;
    }

    $etage = entrepot_get_etage_ref_by_numero($numero_etage);
    if ($etage === null) {
        return true;
    }

    $etage_id = (int) $etage['id'];
    $st = $pdo->prepare('SELECT id FROM entrepot_barre WHERE etage_id = :e');
    $st->execute([':e' => $etage_id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $barre_id) {
        $bid = (int) $barre_id;
        if ($bid <= 0) {
            continue;
        }
        $bc = __DIR__ . '/../upload/barcodes/barre_' . $bid . '.png';
        $qr = __DIR__ . '/../upload/qrcodes/barre_' . $bid . '.png';
        if (is_file($bc)) {
            @unlink($bc);
        }
        if (is_file($qr)) {
            @unlink($qr);
        }
    }

    $pdo->prepare('DELETE FROM entrepot_etage WHERE id = :id')->execute([':id' => $etage_id]);

    return true;
}

/**
 * @return bool
 */
function admin_can_scan_entrepot_barre() {
    require_once __DIR__ . '/../includes/admin_permissions.php';

    return admin_can_gestion_boutique();
}
