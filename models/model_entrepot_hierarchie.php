<?php
/**
 * Hiérarchie CRUD entrepôt : Niveau → Zone → Rayon → Étagère → Barre → Position.
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_entrepot_referentiel.php';
require_once __DIR__ . '/model_entrepot_emplacement.php';
require_once __DIR__ . '/model_entrepot_structure_champs.php';
require_once __DIR__ . '/model_entrepot_hierarchie_libre.php';

/**
 * @param array<int, int> $position_ids
 * @return int
 */
function entrepot_hierarchie_compter_produits_par_positions(array $position_ids) {
    global $db;
    if (!function_exists('produits_has_column')) {
        require_once __DIR__ . '/model_produits.php';
    }
    if (!$db || $position_ids === [] || !function_exists('produits_has_column') || !produits_has_column('entrepot_position_id')) {
        return 0;
    }
    $position_ids = array_values(array_unique(array_filter(array_map('intval', $position_ids))));
    if ($position_ids === []) {
        return 0;
    }
    try {
        $placeholders = implode(',', array_fill(0, count($position_ids), '?'));
        $st = $db->prepare(
            'SELECT COUNT(*) FROM produits WHERE entrepot_position_id IN (' . $placeholders . ')'
        );
        $st->execute($position_ids);

        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param array<int, int> $position_ids
 * @return int
 */
function entrepot_hierarchie_detacher_produits_par_positions(array $position_ids) {
    global $db;
    if (!$db || $position_ids === [] || !function_exists('produits_has_column') || !produits_has_column('entrepot_position_id')) {
        return 0;
    }
    $count = entrepot_hierarchie_compter_produits_par_positions($position_ids);
    if ($count <= 0) {
        return 0;
    }
    $position_ids = array_values(array_unique(array_filter(array_map('intval', $position_ids))));
    try {
        $placeholders = implode(',', array_fill(0, count($position_ids), '?'));
        $st = $db->prepare(
            'UPDATE produits SET entrepot_position_id = NULL WHERE entrepot_position_id IN (' . $placeholders . ')'
        );
        $st->execute($position_ids);

        return $count;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param int $zone_id
 * @param int $etage_id
 * @return array{rayons: int, etageres: int, barres: int, positions: int, produits: int, position_ids: array<int, int>}
 */
function entrepot_hierarchie_scope_zone($zone_id, $etage_id) {
    global $db;
    $zone_id = (int) $zone_id;
    $etage_id = (int) $etage_id;
    $out = ['rayons' => 0, 'etageres' => 0, 'barres' => 0, 'positions' => 0, 'produits' => 0, 'position_ids' => []];
    if (!$db || $zone_id <= 0 || $etage_id <= 0) {
        return $out;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM entrepot_rayon WHERE zone_id = :z AND etage_id = :e');
        $st->execute([':z' => $zone_id, ':e' => $etage_id]);
        $out['rayons'] = (int) $st->fetchColumn();

        $st = $db->prepare(
            'SELECT COUNT(*) FROM entrepot_etagere e
             INNER JOIN entrepot_rayon r ON r.id = e.rayon_id
             WHERE r.zone_id = :z AND r.etage_id = :e'
        );
        $st->execute([':z' => $zone_id, ':e' => $etage_id]);
        $out['etageres'] = (int) $st->fetchColumn();

        $st = $db->prepare(
            'SELECT COUNT(*) FROM entrepot_barre b
             INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
             WHERE r.zone_id = :z AND r.etage_id = :e'
        );
        $st->execute([':z' => $zone_id, ':e' => $etage_id]);
        $out['barres'] = (int) $st->fetchColumn();

        $st = $db->prepare(
            'SELECT p.id FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
             WHERE r.zone_id = :z AND r.etage_id = :e'
        );
        $st->execute([':z' => $zone_id, ':e' => $etage_id]);
        $out['position_ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $out['positions'] = count($out['position_ids']);
        $out['produits'] = entrepot_hierarchie_compter_produits_par_positions($out['position_ids']);
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @param int $rayon_id
 * @param int $etage_id
 * @return array{etageres: int, barres: int, positions: int, produits: int, position_ids: array<int, int>}
 */
function entrepot_hierarchie_scope_rayon($rayon_id, $etage_id) {
    global $db;
    $rayon_id = (int) $rayon_id;
    $etage_id = (int) $etage_id;
    $out = ['etageres' => 0, 'barres' => 0, 'positions' => 0, 'produits' => 0, 'position_ids' => []];
    if (!$db || $rayon_id <= 0 || $etage_id <= 0) {
        return $out;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM entrepot_etagere WHERE rayon_id = :r AND etage_id = :e');
        $st->execute([':r' => $rayon_id, ':e' => $etage_id]);
        $out['etageres'] = (int) $st->fetchColumn();

        $st = $db->prepare('SELECT COUNT(*) FROM entrepot_barre WHERE rayon_id = :r AND etage_id = :e');
        $st->execute([':r' => $rayon_id, ':e' => $etage_id]);
        $out['barres'] = (int) $st->fetchColumn();

        $st = $db->prepare(
            'SELECT p.id FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             WHERE b.rayon_id = :r AND b.etage_id = :e'
        );
        $st->execute([':r' => $rayon_id, ':e' => $etage_id]);
        $out['position_ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $out['positions'] = count($out['position_ids']);
        $out['produits'] = entrepot_hierarchie_compter_produits_par_positions($out['position_ids']);
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @param int $etagere_id
 * @param int $etage_id
 * @return array{barres: int, positions: int, produits: int, position_ids: array<int, int>}
 */
function entrepot_hierarchie_scope_etagere($etagere_id, $etage_id) {
    global $db;
    $etagere_id = (int) $etagere_id;
    $etage_id = (int) $etage_id;
    $out = ['barres' => 0, 'positions' => 0, 'produits' => 0, 'position_ids' => []];
    if (!$db || $etagere_id <= 0 || $etage_id <= 0) {
        return $out;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM entrepot_barre WHERE etagere_id = :et AND etage_id = :e');
        $st->execute([':et' => $etagere_id, ':e' => $etage_id]);
        $out['barres'] = (int) $st->fetchColumn();

        $st = $db->prepare(
            'SELECT p.id FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             WHERE b.etagere_id = :et AND b.etage_id = :e'
        );
        $st->execute([':et' => $etagere_id, ':e' => $etage_id]);
        $out['position_ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $out['positions'] = count($out['position_ids']);
        $out['produits'] = entrepot_hierarchie_compter_produits_par_positions($out['position_ids']);
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @param int $barre_id
 * @return array{positions: int, produits: int, position_ids: array<int, int>}
 */
function entrepot_hierarchie_scope_barre($barre_id) {
    global $db;
    $barre_id = (int) $barre_id;
    $out = ['positions' => 0, 'produits' => 0, 'position_ids' => []];
    if (!$db || $barre_id <= 0) {
        return $out;
    }
    try {
        $st = $db->prepare('SELECT id FROM entrepot_position WHERE barre_id = :b');
        $st->execute([':b' => $barre_id]);
        $out['position_ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $out['positions'] = count($out['position_ids']);
        $out['produits'] = entrepot_hierarchie_compter_produits_par_positions($out['position_ids']);
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @param int $position_id
 * @return array{produits: int, position_ids: array<int, int>}
 */
function entrepot_hierarchie_scope_position($position_id) {
    $position_id = (int) $position_id;
    $ids = $position_id > 0 ? [$position_id] : [];

    return [
        'produits' => entrepot_hierarchie_compter_produits_par_positions($ids),
        'position_ids' => $ids,
    ];
}

/**
 * @param int $etage_id
 * @return array<string, int|array<int, int>>
 */
function entrepot_hierarchie_scope_niveau($etage_id) {
    global $db;
    $etage_id = (int) $etage_id;
    $out = [
        'zones' => 0,
        'rayons' => 0,
        'etageres' => 0,
        'barres' => 0,
        'positions' => 0,
        'produits' => 0,
        'position_ids' => [],
    ];
    if (!$db || $etage_id <= 0) {
        return $out;
    }
    try {
        $tables = [
            'zones' => 'entrepot_zone',
            'rayons' => 'entrepot_rayon',
            'etageres' => 'entrepot_etagere',
            'barres' => 'entrepot_barre',
        ];
        foreach ($tables as $key => $table) {
            $st = $db->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE etage_id = :e');
            $st->execute([':e' => $etage_id]);
            $out[$key] = (int) $st->fetchColumn();
        }
        $st = $db->prepare(
            'SELECT p.id FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             WHERE b.etage_id = :e'
        );
        $st->execute([':e' => $etage_id]);
        $out['position_ids'] = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $out['positions'] = count($out['position_ids']);
        $out['produits'] = entrepot_hierarchie_compter_produits_par_positions($out['position_ids']);
    } catch (PDOException $e) {
        // ignore
    }

    return $out;
}

/**
 * @param string $table
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_impact_suppression_entite($table, $id, $etage_id, $nom = '', $numero = 0) {
    $allowed = [
        'entrepot_zone' => ['type' => 'zone', 'label' => 'Zone'],
        'entrepot_rayon' => ['type' => 'rayon', 'label' => 'Rayon'],
        'entrepot_etagere' => ['type' => 'etagere', 'label' => 'Étagère'],
        'entrepot_barre' => ['type' => 'barre', 'label' => 'Barre'],
        'entrepot_position' => ['type' => 'position', 'label' => 'Position'],
    ];
    if (!isset($allowed[$table])) {
        return null;
    }
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    if ($id <= 0) {
        return null;
    }
    $meta = $allowed[$table];
    $nom = trim((string) $nom);
    $numero = (int) $numero;
    $titre = $meta['label'];
    if ($nom !== '') {
        $titre .= ' « ' . $nom . ' »';
    } elseif ($numero > 0) {
        $titre .= ' #' . $numero;
    }

    $entites = [];
    $produits = 0;
    $avertissements = [];

    if ($table === 'entrepot_zone') {
        $scope = entrepot_hierarchie_scope_zone($id, $etage_id);
        if ($scope['rayons'] > 0) {
            $entites[] = ['label' => 'Rayons', 'count' => $scope['rayons']];
        }
        if ($scope['etageres'] > 0) {
            $entites[] = ['label' => 'Étagères', 'count' => $scope['etageres']];
        }
        if ($scope['barres'] > 0) {
            $entites[] = ['label' => 'Barres', 'count' => $scope['barres']];
        }
        if ($scope['positions'] > 0) {
            $entites[] = ['label' => 'Positions', 'count' => $scope['positions']];
        }
        $produits = (int) $scope['produits'];
        if ($scope['rayons'] > 0 || $scope['positions'] > 0) {
            $avertissements[] = 'Toutes les entités enfants de cette zone seront supprimées définitivement.';
        }
    } elseif ($table === 'entrepot_rayon') {
        $scope = entrepot_hierarchie_scope_rayon($id, $etage_id);
        if ($scope['etageres'] > 0) {
            $entites[] = ['label' => 'Étagères', 'count' => $scope['etageres']];
        }
        if ($scope['barres'] > 0) {
            $entites[] = ['label' => 'Barres', 'count' => $scope['barres']];
        }
        if ($scope['positions'] > 0) {
            $entites[] = ['label' => 'Positions', 'count' => $scope['positions']];
        }
        $produits = (int) $scope['produits'];
        if ($scope['etageres'] > 0 || $scope['positions'] > 0) {
            $avertissements[] = 'Toutes les entités enfants de ce rayon seront supprimées définitivement.';
        }
    } elseif ($table === 'entrepot_etagere') {
        $scope = entrepot_hierarchie_scope_etagere($id, $etage_id);
        if ($scope['barres'] > 0) {
            $entites[] = ['label' => 'Barres', 'count' => $scope['barres']];
        }
        if ($scope['positions'] > 0) {
            $entites[] = ['label' => 'Positions', 'count' => $scope['positions']];
        }
        $produits = (int) $scope['produits'];
        if ($scope['barres'] > 0 || $scope['positions'] > 0) {
            $avertissements[] = 'Toutes les barres et positions de cette étagère seront supprimées.';
        }
    } elseif ($table === 'entrepot_barre') {
        $scope = entrepot_hierarchie_scope_barre($id);
        if ($scope['positions'] > 0) {
            $entites[] = ['label' => 'Positions', 'count' => $scope['positions']];
        }
        $produits = (int) $scope['produits'];
        if ($scope['positions'] > 0) {
            $avertissements[] = 'Toutes les positions de cette barre seront supprimées.';
        }
    } else {
        $scope = entrepot_hierarchie_scope_position($id);
        $produits = (int) $scope['produits'];
    }

    if ($produits > 0) {
        $avertissements[] = $produits . ' produit(s) perdront leur emplacement assigné (référence position effacée).';
    }
    if ($entites === [] && $produits === 0) {
        $avertissements[] = 'Cette entité ne contient aucun élément enfant ni produit lié.';
    }

    return [
        'mode' => 'entite',
        'type' => $meta['type'],
        'table' => $table,
        'id' => $id,
        'etage_id' => $etage_id,
        'label' => $titre,
        'entites' => $entites,
        'produits_lies' => $produits,
        'avertissements' => $avertissements,
    ];
}

/**
 * @param int $numero_etage
 * @param string $nom_niveau
 * @param int $etage_id
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_impact_suppression_niveau($numero_etage, $nom_niveau = '', $etage_id = 0) {
    $numero_etage = (int) $numero_etage;
    $etage_id = (int) $etage_id;
    if ($numero_etage <= 0) {
        return null;
    }
    if ($etage_id <= 0) {
        $et = entrepot_get_etage_ref_by_numero($numero_etage);
        $etage_id = (int) ($et['id'] ?? 0);
    }
    if ($etage_id <= 0) {
        return null;
    }
    $nom_niveau = trim((string) $nom_niveau);
    $titre = $nom_niveau !== '' ? $nom_niveau : ('Niveau ' . $numero_etage);
    $scope = entrepot_hierarchie_scope_niveau($etage_id);
    $entites = [];
    $labels = [
        'zones' => 'Zones',
        'rayons' => 'Rayons',
        'etageres' => 'Étagères',
        'barres' => 'Barres',
        'positions' => 'Positions',
    ];
    foreach ($labels as $key => $lbl) {
        if ((int) ($scope[$key] ?? 0) > 0) {
            $entites[] = ['label' => $lbl, 'count' => (int) $scope[$key]];
        }
    }
    $produits = (int) ($scope['produits'] ?? 0);
    $avertissements = [
        'Tout le contenu hiérarchique de ce niveau sera supprimé définitivement.',
        'La configuration structurelle de ce niveau sera retirée.',
    ];
    if ($produits > 0) {
        $avertissements[] = $produits . ' produit(s) perdront leur emplacement assigné sur ce niveau.';
    }

    return [
        'mode' => 'niveau',
        'numero_etage' => $numero_etage,
        'etage_id' => $etage_id,
        'label' => $titre,
        'entites' => $entites,
        'produits_lies' => $produits,
        'avertissements' => $avertissements,
    ];
}

/**
 * @param int $zone_id
 * @param int $etage_id
 * @return void
 */
function entrepot_hierarchie_supprimer_zone_cascade($zone_id, $etage_id) {
    global $db;
    $zone_id = (int) $zone_id;
    $etage_id = (int) $etage_id;
    if ($zone_id <= 0 || $etage_id <= 0) {
        return;
    }
    $scope = entrepot_hierarchie_scope_zone($zone_id, $etage_id);
    entrepot_hierarchie_detacher_produits_par_positions($scope['position_ids']);
    $db->prepare(
        'DELETE p FROM entrepot_position p
         INNER JOIN entrepot_barre b ON b.id = p.barre_id
         INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
         WHERE r.zone_id = :z AND r.etage_id = :e'
    )->execute([':z' => $zone_id, ':e' => $etage_id]);
    $db->prepare(
        'DELETE b FROM entrepot_barre b
         INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
         WHERE r.zone_id = :z AND r.etage_id = :e'
    )->execute([':z' => $zone_id, ':e' => $etage_id]);
    $db->prepare(
        'DELETE e FROM entrepot_etagere e
         INNER JOIN entrepot_rayon r ON r.id = e.rayon_id
         WHERE r.zone_id = :z AND r.etage_id = :e'
    )->execute([':z' => $zone_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_rayon WHERE zone_id = :z AND etage_id = :e')
        ->execute([':z' => $zone_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_zone WHERE id = :id AND etage_id = :e')
        ->execute([':id' => $zone_id, ':e' => $etage_id]);
}

/**
 * @param int $rayon_id
 * @param int $etage_id
 * @return void
 */
function entrepot_hierarchie_supprimer_rayon_cascade($rayon_id, $etage_id) {
    global $db;
    $rayon_id = (int) $rayon_id;
    $etage_id = (int) $etage_id;
    if ($rayon_id <= 0 || $etage_id <= 0) {
        return;
    }
    $scope = entrepot_hierarchie_scope_rayon($rayon_id, $etage_id);
    entrepot_hierarchie_detacher_produits_par_positions($scope['position_ids']);
    $db->prepare(
        'DELETE p FROM entrepot_position p
         INNER JOIN entrepot_barre b ON b.id = p.barre_id
         WHERE b.rayon_id = :r AND b.etage_id = :e'
    )->execute([':r' => $rayon_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_barre WHERE rayon_id = :r AND etage_id = :e')
        ->execute([':r' => $rayon_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_etagere WHERE rayon_id = :r AND etage_id = :e')
        ->execute([':r' => $rayon_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_rayon WHERE id = :id AND etage_id = :e')
        ->execute([':id' => $rayon_id, ':e' => $etage_id]);
}

/**
 * @param int $etagere_id
 * @param int $etage_id
 * @return void
 */
function entrepot_hierarchie_supprimer_etagere_cascade($etagere_id, $etage_id) {
    global $db;
    $etagere_id = (int) $etagere_id;
    $etage_id = (int) $etage_id;
    if ($etagere_id <= 0 || $etage_id <= 0) {
        return;
    }
    $scope = entrepot_hierarchie_scope_etagere($etagere_id, $etage_id);
    entrepot_hierarchie_detacher_produits_par_positions($scope['position_ids']);
    $db->prepare(
        'DELETE p FROM entrepot_position p
         INNER JOIN entrepot_barre b ON b.id = p.barre_id
         WHERE b.etagere_id = :et AND b.etage_id = :e'
    )->execute([':et' => $etagere_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_barre WHERE etagere_id = :et AND etage_id = :e')
        ->execute([':et' => $etagere_id, ':e' => $etage_id]);
    $db->prepare('DELETE FROM entrepot_etagere WHERE id = :id AND etage_id = :e')
        ->execute([':id' => $etagere_id, ':e' => $etage_id]);
}

/**
 * @param int $barre_id
 * @param int $etage_id
 * @return void
 */
function entrepot_hierarchie_supprimer_barre_cascade($barre_id, $etage_id) {
    global $db;
    $barre_id = (int) $barre_id;
    $etage_id = (int) $etage_id;
    if ($barre_id <= 0) {
        return;
    }
    $scope = entrepot_hierarchie_scope_barre($barre_id);
    entrepot_hierarchie_detacher_produits_par_positions($scope['position_ids']);
    $db->prepare('DELETE FROM entrepot_position WHERE barre_id = :b')->execute([':b' => $barre_id]);
    if ($etage_id > 0) {
        $db->prepare('DELETE FROM entrepot_barre WHERE id = :id AND etage_id = :e')
            ->execute([':id' => $barre_id, ':e' => $etage_id]);
    } else {
        $db->prepare('DELETE FROM entrepot_barre WHERE id = :id')->execute([':id' => $barre_id]);
    }
}

/**
 * @param int $position_id
 * @return void
 */
function entrepot_hierarchie_supprimer_position_cascade($position_id) {
    global $db;
    $position_id = (int) $position_id;
    if ($position_id <= 0) {
        return;
    }
    entrepot_hierarchie_detacher_produits_par_positions([$position_id]);
    $db->prepare('DELETE FROM entrepot_position WHERE id = :id')->execute([':id' => $position_id]);
}

/**
 * @return bool
 */
function entrepot_hierarchie_schema_ok() {
    global $db;
    if (!$db || !entrepot_referentiel_tables_ok()) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT code_abrege FROM entrepot_etage LIMIT 1');
        $db->query('SELECT zone_id FROM entrepot_rayon LIMIT 1');
        $db->query('SELECT etagere_id FROM entrepot_barre LIMIT 1');
        $db->query('SELECT 1 FROM entrepot_etagere LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @return bool
 */
function entrepot_hierarchie_ensure_schema() {
    $runner = __DIR__ . '/../migrations/run_migrate_entrepot_hierarchie_crud.php';
    if (!is_file($runner)) {
        return false;
    }
    ob_start();
    include $runner;
    ob_end_clean();

    return entrepot_hierarchie_schema_ok();
}

/**
 * Un numéro de niveau (étage) est-il libre ?
 * Doublon uniquement parmi les niveaux — pas avec zones/rayons/nœuds.
 *
 * @param int $numero_etage
 * @return bool
 */
function entrepot_niveau_numero_est_disponible($numero_etage) {
    $numero_etage = (int) $numero_etage;
    if ($numero_etage < 1 || $numero_etage > (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX) {
        return false;
    }
    // Source de vérité des onglets « niveaux » : entrepot_emplacement_etage
    if (entrepot_emplacement_get_etage($numero_etage) !== null) {
        return false;
    }
    // Alignement référentiel (même entité niveau, pas le reste de la hiérarchie)
    require_once __DIR__ . '/model_entrepot_referentiel.php';
    if (entrepot_referentiel_tables_ok() && entrepot_get_etage_ref_by_numero($numero_etage) !== null) {
        return false;
    }

    return true;
}

/**
 * Numéros déjà pris par des niveaux (étages) uniquement.
 *
 * @return array<int, int>
 */
function entrepot_niveau_numeros_occupes() {
    $out = [];
    foreach (entrepot_hierarchie_liste_niveaux() as $nv) {
        $n = (int) ($nv['numero_etage'] ?? 0);
        if ($n > 0) {
            $out[] = $n;
        }
    }
    $out = array_values(array_unique($out));
    sort($out, SORT_NUMERIC);

    return $out;
}

/**
 * @param string $nom
 * @param string $code_abrege
 * @param int $numero_etage
 * @return array{success: bool, message: string, etage_id?: int, numero_etage?: int}
 */
function entrepot_niveau_ajouter($nom, $code_abrege, $numero_etage) {
    global $db;
    if (!entrepot_referentiel_tables_ok() || !entrepot_emplacement_tables_ok()) {
        return ['success' => false, 'message' => 'Tables entrepôt absentes.'];
    }
    $nom = trim((string) $nom);
    $code_abrege = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $code_abrege)));
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom du niveau est obligatoire.'];
    }
    if ($code_abrege === '') {
        return ['success' => false, 'message' => 'Le code abrégé est obligatoire.'];
    }
    if (strlen($code_abrege) > 10) {
        $code_abrege = substr($code_abrege, 0, 10);
    }

    $numero = (int) $numero_etage;
    if ($numero < 1) {
        return ['success' => false, 'message' => 'Le numéro du niveau est obligatoire (minimum 1).'];
    }
    if ($numero > (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX) {
        return [
            'success' => false,
            'message' => 'Numéro de niveau invalide (1 à ' . (int) ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX . ').',
        ];
    }
    if (!entrepot_niveau_numero_est_disponible($numero)) {
        return [
            'success' => false,
            'message' => 'Le numéro de niveau ' . $numero . ' est déjà utilisé par un autre niveau. Choisissez un autre numéro (les zones, rayons, etc. peuvent réutiliser ce numéro).',
        ];
    }

    $res_cfg = entrepot_emplacement_ajouter_niveau($nom, entrepot_structure_champs_valeurs_defaut(), $numero);
    if (!$res_cfg['success']) {
        return $res_cfg;
    }

    $numero = (int) ($res_cfg['numero_etage'] ?? 0);
    if ($numero <= 0) {
        $numero = (int) $db->query('SELECT COALESCE(MAX(numero_etage), 0) FROM entrepot_emplacement_etage')->fetchColumn();
    }

    $code = 'E' . $numero;
    try {
        $etage = entrepot_get_etage_ref_by_numero($numero);
        if ($etage === null) {
            $db->prepare(
                'INSERT INTO entrepot_etage (numero_etage, nom, code, code_abrege, actif, date_modification)
                 VALUES (:n, :nom, :code, :ab, 1, NOW())'
            )->execute([':n' => $numero, ':nom' => $nom, ':code' => $code, ':ab' => $code_abrege]);
            $etage_id = (int) $db->lastInsertId();
        } else {
            $etage_id = (int) $etage['id'];
            $db->prepare(
                'UPDATE entrepot_etage SET nom = :nom, code_abrege = :ab, date_modification = NOW() WHERE id = :id'
            )->execute([':nom' => $nom, ':ab' => $code_abrege, ':id' => $etage_id]);
        }

        return [
            'success' => true,
            'message' => 'Niveau « ' . $nom . ' » créé.',
            'etage_id' => $etage_id,
            'numero_etage' => $numero,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string, id?: int}
 */
function entrepot_zone_ajouter($etage_id, $nom, $numero) {
    global $db;
    $etage_id = (int) $etage_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($etage_id <= 0) {
        return ['success' => false, 'message' => 'Niveau invalide.'];
    }
    if ($nom === '') {
        $nom = 'Zone ' . $numero;
    }
    try {
        $db->prepare(
            'INSERT INTO entrepot_zone (etage_id, rayon_id, numero, nom, date_modification)
             VALUES (:e, NULL, :n, :nom, NOW())'
        )->execute([':e' => $etage_id, ':n' => $numero, ':nom' => $nom]);

        return ['success' => true, 'message' => 'Zone ajoutée.', 'id' => (int) $db->lastInsertId()];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de zone existe déjà sur ce niveau (parmi les zones uniquement).'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $etage_id
 * @param int $zone_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string, id?: int}
 */
function entrepot_rayon_ajouter($etage_id, $zone_id, $nom, $numero) {
    global $db;
    $etage_id = (int) $etage_id;
    $zone_id = (int) $zone_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($etage_id <= 0 || $zone_id <= 0) {
        return ['success' => false, 'message' => 'Niveau et zone obligatoires.'];
    }
    if ($nom === '') {
        $nom = 'Rayon ' . $numero;
    }
    try {
        $chk = $db->prepare('SELECT id FROM entrepot_zone WHERE id = :z AND etage_id = :e LIMIT 1');
        $chk->execute([':z' => $zone_id, ':e' => $etage_id]);
        if (!$chk->fetchColumn()) {
            return ['success' => false, 'message' => 'Zone introuvable sur ce niveau.'];
        }
        // Doublon numéro uniquement parmi les rayons de cette zone (pas tout l’étage / système)
        $dup = $db->prepare('SELECT id FROM entrepot_rayon WHERE zone_id = :z AND numero = :n LIMIT 1');
        $dup->execute([':z' => $zone_id, ':n' => $numero]);
        if ($dup->fetchColumn()) {
            return ['success' => false, 'message' => 'Ce numéro de rayon existe déjà dans cette zone.'];
        }
        $db->prepare(
            'INSERT INTO entrepot_rayon (etage_id, zone_id, numero, nom, date_modification)
             VALUES (:e, :z, :n, :nom, NOW())'
        )->execute([':e' => $etage_id, ':z' => $zone_id, ':n' => $numero, ':nom' => $nom]);
        $rid = (int) $db->lastInsertId();
        $db->prepare('UPDATE entrepot_zone SET rayon_id = :r WHERE id = :z AND rayon_id IS NULL')
            ->execute([':r' => $rid, ':z' => $zone_id]);

        return ['success' => true, 'message' => 'Rayon ajouté.', 'id' => $rid];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de rayon existe déjà dans cette zone (les autres zones peuvent réutiliser ce numéro).'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $etage_id
 * @param int $rayon_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string, id?: int}
 */
function entrepot_etagere_ajouter($etage_id, $rayon_id, $nom, $numero) {
    global $db;
    $etage_id = (int) $etage_id;
    $rayon_id = (int) $rayon_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($etage_id <= 0 || $rayon_id <= 0) {
        return ['success' => false, 'message' => 'Niveau et rayon obligatoires.'];
    }
    if ($nom === '') {
        $nom = 'Étagère ' . $numero;
    }
    try {
        $st = $db->prepare('SELECT zone_id FROM entrepot_rayon WHERE id = :r AND etage_id = :e LIMIT 1');
        $st->execute([':r' => $rayon_id, ':e' => $etage_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Rayon introuvable sur ce niveau.'];
        }
        $zone_id = (int) ($row['zone_id'] ?? 0);
        $db->prepare(
            'INSERT INTO entrepot_etagere (etage_id, zone_id, rayon_id, numero, nom, date_modification)
             VALUES (:e, :z, :r, :n, :nom, NOW())'
        )->execute([
            ':e' => $etage_id,
            ':z' => $zone_id > 0 ? $zone_id : null,
            ':r' => $rayon_id,
            ':n' => $numero,
            ':nom' => $nom,
        ]);

        return ['success' => true, 'message' => 'Étagère ajoutée.', 'id' => (int) $db->lastInsertId()];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro d’étagère existe déjà sur ce rayon.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $etage_id
 * @param int $rayon_id
 * @param int $etagere_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string, id?: int}
 */
function entrepot_barre_ajouter($etage_id, $rayon_id, $etagere_id, $nom, $numero) {
    global $db;
    $etage_id = (int) $etage_id;
    $rayon_id = (int) $rayon_id;
    $etagere_id = (int) $etagere_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($etage_id <= 0 || $rayon_id <= 0 || $etagere_id <= 0) {
        return ['success' => false, 'message' => 'Niveau, rayon et étagère obligatoires.'];
    }
    if ($nom === '') {
        $nom = 'Barre ' . $numero;
    }
    try {
        $st = $db->prepare(
            'SELECT e.id FROM entrepot_etagere e
             INNER JOIN entrepot_rayon r ON r.id = e.rayon_id
             WHERE e.id = :et AND e.rayon_id = :r AND e.etage_id = :eid AND r.etage_id = :eid LIMIT 1'
        );
        $st->execute([':et' => $etagere_id, ':r' => $rayon_id, ':eid' => $etage_id]);
        if (!$st->fetchColumn()) {
            return ['success' => false, 'message' => 'Étagère introuvable sur ce rayon.'];
        }
        $db->prepare(
            'INSERT INTO entrepot_barre (etage_id, rayon_id, etagere_id, numero, nom, date_modification)
             VALUES (:e, :r, :et, :n, :nom, NOW())'
        )->execute([
            ':e' => $etage_id, ':r' => $rayon_id, ':et' => $etagere_id, ':n' => $numero, ':nom' => $nom,
        ]);
        $bid = (int) $db->lastInsertId();
        entrepot_barre_generer_code_scan($bid);
        if (function_exists('entrepot_generer_codes_barre')) {
            entrepot_generer_codes_barre($bid);
        }

        return ['success' => true, 'message' => 'Barre ajoutée.', 'id' => $bid];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de barre existe déjà sur ce rayon.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $barre_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string, id?: int}
 */
function entrepot_position_ajouter($barre_id, $nom, $numero) {
    global $db;
    $barre_id = (int) $barre_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($barre_id <= 0) {
        return ['success' => false, 'message' => 'Barre obligatoire.'];
    }
    if ($nom === '') {
        $nom = 'Position ' . $numero;
    }
    try {
        $st = $db->prepare('SELECT id FROM entrepot_barre WHERE id = :b LIMIT 1');
        $st->execute([':b' => $barre_id]);
        if (!$st->fetchColumn()) {
            return ['success' => false, 'message' => 'Barre introuvable.'];
        }
        $db->prepare(
            'INSERT INTO entrepot_position (barre_id, numero, nom, date_modification)
             VALUES (:b, :n, :nom, NOW())'
        )->execute([':b' => $barre_id, ':n' => $numero, ':nom' => $nom]);

        return ['success' => true, 'message' => 'Position ajoutée.', 'id' => (int) $db->lastInsertId()];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de position existe déjà sur cette barre.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Arbre complet pour un onglet niveau.
 *
 * @param int $numero_etage
 * @return array<string, mixed>|null
 */
function entrepot_hierarchie_liste_pour_niveau($numero_etage) {
    global $db;
    $numero_etage = (int) $numero_etage;
    $etage = entrepot_get_etage_ref_by_numero($numero_etage);
    if ($etage === null) {
        return null;
    }
    $etage_id = (int) $etage['id'];

    $zones = $db->prepare('SELECT * FROM entrepot_zone WHERE etage_id = :e ORDER BY numero ASC');
    $zones->execute([':e' => $etage_id]);
    $zones_rows = $zones->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rayons = $db->prepare('SELECT * FROM entrepot_rayon WHERE etage_id = :e ORDER BY numero ASC');
    $rayons->execute([':e' => $etage_id]);
    $rayons_rows = $rayons->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $etageres = $db->prepare('SELECT * FROM entrepot_etagere WHERE etage_id = :e ORDER BY rayon_id ASC, numero ASC');
    $etageres->execute([':e' => $etage_id]);
    $etageres_rows = $etageres->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $barres = $db->prepare(
        'SELECT b.* FROM entrepot_barre b
         INNER JOIN entrepot_rayon r ON r.id = b.rayon_id
         WHERE b.etage_id = :e ORDER BY r.numero ASC, b.numero ASC'
    );
    $barres->execute([':e' => $etage_id]);
    $barres_rows = [];
    foreach ($barres->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $bid = (int) $b['id'];
        $stp = $db->prepare('SELECT * FROM entrepot_position WHERE barre_id = :b ORDER BY numero ASC');
        $stp->execute([':b' => $bid]);
        $b['positions'] = $stp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $b['etiquette'] = entrepot_barre_etiquette_libelle($b, $etage, null);
        $barres_rows[] = $b;
    }

    $rayons_by_zone = [];
    foreach ($rayons_rows as $r) {
        $zid = (int) ($r['zone_id'] ?? 0);
        if ($zid <= 0) {
            $zid = 0;
        }
        if (!isset($rayons_by_zone[$zid])) {
            $rayons_by_zone[$zid] = [];
        }
        $rayons_by_zone[$zid][] = $r;
    }

    $etageres_by_rayon = [];
    foreach ($etageres_rows as $et) {
        $rid = (int) $et['rayon_id'];
        if (!isset($etageres_by_rayon[$rid])) {
            $etageres_by_rayon[$rid] = [];
        }
        $etageres_by_rayon[$rid][] = $et;
    }

    $barres_by_etagere = [];
    foreach ($barres_rows as $b) {
        $eid = (int) ($b['etagere_id'] ?? 0);
        if ($eid <= 0) {
            $eid = 0;
        }
        if (!isset($barres_by_etagere[$eid])) {
            $barres_by_etagere[$eid] = [];
        }
        $barres_by_etagere[$eid][] = $b;
    }

    $arbre = [];
    foreach ($zones_rows as $z) {
        $zid = (int) $z['id'];
        $node = $z;
        $node['rayons'] = [];
        foreach ($rayons_by_zone[$zid] ?? [] as $r) {
            $rid = (int) $r['id'];
            $rnode = $r;
            $rnode['etageres'] = [];
            foreach ($etageres_by_rayon[$rid] ?? [] as $et) {
                $etid = (int) $et['id'];
                $etnode = $et;
                $etnode['barres'] = $barres_by_etagere[$etid] ?? [];
                $rnode['etageres'][] = $etnode;
            }
            $node['rayons'][] = $rnode;
        }
        $arbre[] = $node;
    }

    if (!empty($rayons_by_zone[0])) {
        $arbre[] = [
            'id' => 0,
            'nom' => 'Sans zone',
            'numero' => 0,
            'rayons' => array_map(function ($r) use ($etageres_by_rayon, $barres_by_etagere) {
                $rid = (int) $r['id'];
                $rnode = $r;
                $rnode['etageres'] = [];
                foreach ($etageres_by_rayon[$rid] ?? [] as $et) {
                    $etid = (int) $et['id'];
                    $etnode = $et;
                    $etnode['barres'] = $barres_by_etagere[$etid] ?? [];
                    $rnode['etageres'][] = $etnode;
                }

                return $rnode;
            }, $rayons_by_zone[0]),
        ];
    }

    return [
        'etage' => $etage,
        'zones' => $arbre,
        'rayons' => $rayons_rows,
        'etageres' => $etageres_rows,
        'barres' => $barres_rows,
    ];
}

/**
 * Listes filtrées pour modals en cascade.
 *
 * @param int $etage_id
 * @param int $zone_id
 * @param int $rayon_id
 * @param int $etagere_id
 * @return array<string, array<int, array<string, mixed>>>
 */
function entrepot_hierarchie_liste_pour_cascade($etage_id, $zone_id = 0, $rayon_id = 0, $etagere_id = 0) {
    global $db;
    $etage_id = (int) $etage_id;
    $zone_id = (int) $zone_id;
    $rayon_id = (int) $rayon_id;
    $etagere_id = (int) $etagere_id;
    $out = [
        'niveaux' => [],
        'zones' => [],
        'rayons' => [],
        'etageres' => [],
        'barres' => [],
    ];
    if ($etage_id <= 0) {
        $st = $db->query('SELECT id, numero_etage, nom, code_abrege FROM entrepot_etage WHERE actif = 1 ORDER BY numero_etage ASC');
        $out['niveaux'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $out;
    }

    $stz = $db->prepare('SELECT id, numero, nom FROM entrepot_zone WHERE etage_id = :e ORDER BY numero ASC');
    $stz->execute([':e' => $etage_id]);
    $out['zones'] = $stz->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($zone_id > 0) {
        $str = $db->prepare(
            'SELECT id, numero, nom FROM entrepot_rayon WHERE etage_id = :e AND zone_id = :z ORDER BY numero ASC'
        );
        $str->execute([':e' => $etage_id, ':z' => $zone_id]);
        $out['rayons'] = $str->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($rayon_id > 0) {
        $ste = $db->prepare(
            'SELECT id, numero, nom FROM entrepot_etagere WHERE etage_id = :e AND rayon_id = :r ORDER BY numero ASC'
        );
        $ste->execute([':e' => $etage_id, ':r' => $rayon_id]);
        $out['etageres'] = $ste->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($etagere_id > 0) {
        $stb = $db->prepare(
            'SELECT id, numero, nom FROM entrepot_barre WHERE etage_id = :e AND etagere_id = :et ORDER BY numero ASC'
        );
        $stb->execute([':e' => $etage_id, ':et' => $etagere_id]);
        $out['barres'] = $stb->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $out;
}

/**
 * @return array<int, array<string, mixed>>
 */
function entrepot_hierarchie_liste_niveaux() {
    global $db;
    if (!entrepot_referentiel_tables_ok()) {
        return [];
    }
    try {
        $stmt = $db->query(
            'SELECT id, numero_etage, nom, code, code_abrege, actif
             FROM entrepot_etage WHERE actif = 1 ORDER BY numero_etage ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Clé de comparaison insensible à la casse pour les noms.
 *
 * @param string $nom
 * @return string
 */
function entrepot_hierarchie_nom_cle($nom) {
    $n = mb_strtolower(trim((string) $nom), 'UTF-8');
    if ($n === '') {
        return '';
    }

    return (string) preg_replace('/\s+/u', ' ', $n);
}

/**
 * Vérifie l’unicité du numéro et du nom dans un périmètre donné.
 *
 * @param string $table
 * @param string $scope_sql ex. etage_id = :e
 * @param array<string, mixed> $scope_params
 * @param int $numero
 * @param string $nom
 * @param int $exclude_id
 * @param string $scope_label ex. « niveau », « rayon »
 * @param string $entity_label ex. « zone », « rayon »
 * @return array{ok: bool, message: string}
 */
function entrepot_hierarchie_verifier_uniques($table, $scope_sql, $scope_params, $numero, $nom, $exclude_id, $scope_label, $entity_label) {
    global $db;
    $allowed = ['entrepot_zone', 'entrepot_rayon', 'entrepot_etagere', 'entrepot_barre', 'entrepot_position'];
    if (!in_array($table, $allowed, true)) {
        return ['ok' => false, 'message' => 'Entité non autorisée.'];
    }

    $exclude_id = (int) $exclude_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    $nom_cle = entrepot_hierarchie_nom_cle($nom);

    $sql_num = "SELECT id FROM `$table` WHERE $scope_sql AND numero = :num";
    $params_num = $scope_params;
    $params_num[':num'] = $numero;
    if ($exclude_id > 0) {
        $sql_num .= ' AND id != :ex';
        $params_num[':ex'] = $exclude_id;
    }
    $st = $db->prepare($sql_num);
    $st->execute($params_num);
    if ($st->fetchColumn()) {
        return [
            'ok' => false,
            'message' => 'Un(e) ' . $entity_label . ' du même ' . $scope_label . ' porte déjà le numéro ' . $numero . '.',
        ];
    }

    if ($nom_cle !== '') {
        $sql_nom = "SELECT id, nom FROM `$table` WHERE $scope_sql";
        $params_nom = $scope_params;
        if ($exclude_id > 0) {
            $sql_nom .= ' AND id != :ex';
            $params_nom[':ex'] = $exclude_id;
        }
        $st2 = $db->prepare($sql_nom);
        $st2->execute($params_nom);
        while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
            if (entrepot_hierarchie_nom_cle($row['nom'] ?? '') === $nom_cle) {
                return [
                    'ok' => false,
                    'message' => 'Un(e) ' . $entity_label . ' du même ' . $scope_label . ' porte déjà le nom « ' . $nom . ' ».',
                ];
            }
        }
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_zone_modifier($id, $etage_id, $nom, $numero) {
    global $db;
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($id <= 0 || $etage_id <= 0) {
        return ['success' => false, 'message' => 'Identifiants invalides.'];
    }
    if ($nom === '') {
        $nom = 'Zone ' . $numero;
    }
    try {
        $st = $db->prepare('SELECT id FROM entrepot_zone WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $id, ':e' => $etage_id]);
        if (!$st->fetchColumn()) {
            return ['success' => false, 'message' => 'Zone introuvable sur ce niveau.'];
        }
        $chk = entrepot_hierarchie_verifier_uniques(
            'entrepot_zone',
            'etage_id = :e',
            [':e' => $etage_id],
            $numero,
            $nom,
            $id,
            'niveau',
            'zone'
        );
        if (!$chk['ok']) {
            return ['success' => false, 'message' => $chk['message']];
        }
        $db->prepare(
            'UPDATE entrepot_zone SET numero = :n, nom = :nom, date_modification = NOW() WHERE id = :id AND etage_id = :e'
        )->execute([':n' => $numero, ':nom' => $nom, ':id' => $id, ':e' => $etage_id]);

        return ['success' => true, 'message' => 'Zone modifiée.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de zone existe déjà sur ce niveau (parmi les zones uniquement).'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_rayon_modifier($id, $etage_id, $nom, $numero) {
    global $db;
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($id <= 0 || $etage_id <= 0) {
        return ['success' => false, 'message' => 'Identifiants invalides.'];
    }
    if ($nom === '') {
        $nom = 'Rayon ' . $numero;
    }
    try {
        $st = $db->prepare('SELECT id, zone_id FROM entrepot_rayon WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $id, ':e' => $etage_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Rayon introuvable sur ce niveau.'];
        }
        $zone_id = (int) ($row['zone_id'] ?? 0);
        if ($zone_id <= 0) {
            return ['success' => false, 'message' => 'Zone parente introuvable pour ce rayon.'];
        }
        $chk = entrepot_hierarchie_verifier_uniques(
            'entrepot_rayon',
            'zone_id = :z',
            [':z' => $zone_id],
            $numero,
            $nom,
            $id,
            'zone',
            'rayon'
        );
        if (!$chk['ok']) {
            return ['success' => false, 'message' => $chk['message']];
        }
        $db->prepare(
            'UPDATE entrepot_rayon SET numero = :n, nom = :nom, date_modification = NOW() WHERE id = :id AND etage_id = :e'
        )->execute([':n' => $numero, ':nom' => $nom, ':id' => $id, ':e' => $etage_id]);

        return ['success' => true, 'message' => 'Rayon modifié.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de rayon existe déjà dans cette zone.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_etagere_modifier($id, $etage_id, $nom, $numero) {
    global $db;
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($id <= 0 || $etage_id <= 0) {
        return ['success' => false, 'message' => 'Identifiants invalides.'];
    }
    if ($nom === '') {
        $nom = 'Étagère ' . $numero;
    }
    try {
        $st = $db->prepare('SELECT rayon_id FROM entrepot_etagere WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $id, ':e' => $etage_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Étagère introuvable sur ce niveau.'];
        }
        $rayon_id = (int) ($row['rayon_id'] ?? 0);
        if ($rayon_id <= 0) {
            return ['success' => false, 'message' => 'Rayon parent introuvable.'];
        }
        $chk = entrepot_hierarchie_verifier_uniques(
            'entrepot_etagere',
            'rayon_id = :r',
            [':r' => $rayon_id],
            $numero,
            $nom,
            $id,
            'rayon',
            'étagère'
        );
        if (!$chk['ok']) {
            return ['success' => false, 'message' => $chk['message']];
        }
        $db->prepare(
            'UPDATE entrepot_etagere SET numero = :n, nom = :nom, date_modification = NOW() WHERE id = :id AND etage_id = :e'
        )->execute([':n' => $numero, ':nom' => $nom, ':id' => $id, ':e' => $etage_id]);

        return ['success' => true, 'message' => 'Étagère modifiée.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro d’étagère existe déjà sur ce rayon.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_barre_modifier($id, $etage_id, $nom, $numero) {
    global $db;
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($id <= 0 || $etage_id <= 0) {
        return ['success' => false, 'message' => 'Identifiants invalides.'];
    }
    if ($nom === '') {
        $nom = 'Barre ' . $numero;
    }
    try {
        $st = $db->prepare('SELECT rayon_id, numero, nom FROM entrepot_barre WHERE id = :id AND etage_id = :e LIMIT 1');
        $st->execute([':id' => $id, ':e' => $etage_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Barre introuvable sur ce niveau.'];
        }
        $rayon_id = (int) ($row['rayon_id'] ?? 0);
        $old_num = (int) ($row['numero'] ?? 0);
        $old_nom = (string) ($row['nom'] ?? '');
        if ($rayon_id <= 0) {
            return ['success' => false, 'message' => 'Rayon parent introuvable.'];
        }
        $chk = entrepot_hierarchie_verifier_uniques(
            'entrepot_barre',
            'rayon_id = :r',
            [':r' => $rayon_id],
            $numero,
            $nom,
            $id,
            'rayon',
            'barre'
        );
        if (!$chk['ok']) {
            return ['success' => false, 'message' => $chk['message']];
        }
        $db->prepare(
            'UPDATE entrepot_barre SET numero = :n, nom = :nom, date_modification = NOW() WHERE id = :id AND etage_id = :e'
        )->execute([':n' => $numero, ':nom' => $nom, ':id' => $id, ':e' => $etage_id]);
        if ($old_num !== $numero || entrepot_hierarchie_nom_cle($old_nom) !== entrepot_hierarchie_nom_cle($nom)) {
            if (function_exists('entrepot_barre_generer_code_scan')) {
                entrepot_barre_generer_code_scan($id);
            }
            if (function_exists('entrepot_generer_codes_barre')) {
                entrepot_generer_codes_barre($id);
            }
        }

        return ['success' => true, 'message' => 'Barre modifiée.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de barre existe déjà sur ce rayon.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_position_modifier($id, $etage_id, $nom, $numero) {
    global $db;
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    $numero = max(1, (int) $numero);
    $nom = trim((string) $nom);
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Identifiant invalide.'];
    }
    if ($nom === '') {
        $nom = 'Position ' . $numero;
    }
    try {
        $st = $db->prepare(
            'SELECT p.barre_id
             FROM entrepot_position p
             INNER JOIN entrepot_barre b ON b.id = p.barre_id
             WHERE p.id = :id' . ($etage_id > 0 ? ' AND b.etage_id = :e' : '') . '
             LIMIT 1'
        );
        $params = [':id' => $id];
        if ($etage_id > 0) {
            $params[':e'] = $etage_id;
        }
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Position introuvable.'];
        }
        $barre_id = (int) ($row['barre_id'] ?? 0);
        if ($barre_id <= 0) {
            return ['success' => false, 'message' => 'Barre parente introuvable.'];
        }
        $chk = entrepot_hierarchie_verifier_uniques(
            'entrepot_position',
            'barre_id = :b',
            [':b' => $barre_id],
            $numero,
            $nom,
            $id,
            'barre',
            'position'
        );
        if (!$chk['ok']) {
            return ['success' => false, 'message' => $chk['message']];
        }
        $db->prepare(
            'UPDATE entrepot_position SET numero = :n, nom = :nom, date_modification = NOW() WHERE id = :id'
        )->execute([':n' => $numero, ':nom' => $nom, ':id' => $id]);

        return ['success' => true, 'message' => 'Position modifiée.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            return ['success' => false, 'message' => 'Ce numéro de position existe déjà sur cette barre.'];
        }

        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param string $table
 * @param int $id
 * @param int $etage_id
 * @param string $nom
 * @param int $numero
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_modifier_entite($table, $id, $etage_id, $nom, $numero) {
    $allowed = [
        'entrepot_zone' => 'entrepot_zone_modifier',
        'entrepot_rayon' => 'entrepot_rayon_modifier',
        'entrepot_etagere' => 'entrepot_etagere_modifier',
        'entrepot_barre' => 'entrepot_barre_modifier',
        'entrepot_position' => 'entrepot_position_modifier',
    ];
    if (!isset($allowed[$table]) || !function_exists($allowed[$table])) {
        return ['success' => false, 'message' => 'Entité non autorisée.'];
    }

    return call_user_func($allowed[$table], (int) $id, (int) $etage_id, (string) $nom, (int) $numero);
}

/**
 * @param string $table
 * @param int $id
 * @param int $etage_id
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_supprimer_entite($table, $id, $etage_id) {
    global $db;
    $allowed = [
        'entrepot_zone' => 'zone',
        'entrepot_rayon' => 'rayon',
        'entrepot_etagere' => 'étagère',
        'entrepot_barre' => 'barre',
        'entrepot_position' => 'position',
    ];
    if (!isset($allowed[$table])) {
        return ['success' => false, 'message' => 'Entité non autorisée.'];
    }
    $id = (int) $id;
    $etage_id = (int) $etage_id;
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Identifiant invalide.'];
    }
    try {
        switch ($table) {
            case 'entrepot_zone':
                entrepot_hierarchie_supprimer_zone_cascade($id, $etage_id);
                break;
            case 'entrepot_rayon':
                entrepot_hierarchie_supprimer_rayon_cascade($id, $etage_id);
                break;
            case 'entrepot_etagere':
                entrepot_hierarchie_supprimer_etagere_cascade($id, $etage_id);
                break;
            case 'entrepot_barre':
                entrepot_hierarchie_supprimer_barre_cascade($id, $etage_id);
                break;
            case 'entrepot_position':
                entrepot_hierarchie_supprimer_position_cascade($id);
                break;
            default:
                return ['success' => false, 'message' => 'Entité non autorisée.'];
        }

        return ['success' => true, 'message' => ucfirst($allowed[$table]) . ' supprimé(e) avec son contenu lié.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * @param int $numero_etage
 * @return array{success: bool, message: string}
 */
function entrepot_hierarchie_supprimer_niveau($numero_etage) {
    return entrepot_emplacement_supprimer_etage((int) $numero_etage);
}

/**
 * JSON hiérarchique strict pour formulaire produit.
 *
 * @return array<int, array<string, mixed>>
 */
function entrepot_hierarchie_json_produit() {
    require_once __DIR__ . '/model_entrepot_structure_champs.php';

    $actifs = entrepot_hierarchie_niveaux_actifs();
    $has = function ($niveau) use ($actifs) {
        return isset($actifs[(string) $niveau]);
    };

    $out = [];
    foreach (entrepot_hierarchie_liste_niveaux() as $et) {
        $n = (int) ($et['numero_etage'] ?? 0);
        if ($n <= 0) {
            continue;
        }
        $tree = entrepot_hierarchie_liste_pour_niveau($n);
        if ($tree === null) {
            continue;
        }
        $etage_id = (int) ($tree['etage']['id'] ?? 0);
        $node = [
            'etage' => $tree['etage'],
            'zones' => [],
        ];

        $rayons_plats = [];
        $etageres_plats = [];
        $barres_plats = [];

        if ($has('zone')) {
            foreach ($tree['zones'] as $z) {
                $znode = [
                    'id' => (int) ($z['id'] ?? 0),
                    'numero' => (int) ($z['numero'] ?? 0),
                    'nom' => (string) ($z['nom'] ?? ''),
                    'rayons' => [],
                ];
                foreach ($z['rayons'] ?? [] as $r) {
                    if (!$has('rayon')) {
                        continue;
                    }
                    $rayons_plats[] = $r;
                    $rnode = [
                        'id' => (int) ($r['id'] ?? 0),
                        'numero' => (int) ($r['numero'] ?? 0),
                        'nom' => (string) ($r['nom'] ?? ''),
                        'zone_id' => (int) ($r['zone_id'] ?? 0),
                        'etageres' => [],
                    ];
                    foreach ($r['etageres'] ?? [] as $etg) {
                        if (!$has('etagere')) {
                            continue;
                        }
                        $etageres_plats[] = $etg;
                        $enode = [
                            'id' => (int) ($etg['id'] ?? 0),
                            'numero' => (int) ($etg['numero'] ?? 0),
                            'nom' => (string) ($etg['nom'] ?? ''),
                            'rayon_id' => (int) ($etg['rayon_id'] ?? 0),
                            'barres' => [],
                        ];
                        foreach ($etg['barres'] ?? [] as $b) {
                            if (!$has('barre')) {
                                continue;
                            }
                            $barres_plats[] = $b;
                            $bnode = [
                                'id' => (int) ($b['id'] ?? 0),
                                'numero' => (int) ($b['numero'] ?? 0),
                                'nom' => (string) ($b['nom'] ?? ''),
                                'etagere_id' => (int) ($b['etagere_id'] ?? 0),
                                'rayon_id' => (int) ($b['rayon_id'] ?? 0),
                                'positions' => [],
                            ];
                            if ($has('position')) {
                                foreach ($b['positions'] ?? [] as $p) {
                                    $bnode['positions'][] = [
                                        'id' => (int) ($p['id'] ?? 0),
                                        'numero' => (int) ($p['numero'] ?? 0),
                                        'nom' => (string) ($p['nom'] ?? ''),
                                    ];
                                }
                            }
                            $enode['barres'][] = $bnode;
                        }
                        if ($enode['barres'] !== [] || $has('etagere')) {
                            $rnode['etageres'][] = $enode;
                        }
                    }
                    if ($rnode['etageres'] !== [] || $has('rayon')) {
                        $znode['rayons'][] = $rnode;
                    }
                }
                $node['zones'][] = $znode;
            }
        } else {
            foreach ($tree['zones'] as $z) {
                foreach ($z['rayons'] ?? [] as $r) {
                    if ($has('rayon')) {
                        $rayons_plats[] = $r;
                    }
                    foreach ($r['etageres'] ?? [] as $etg) {
                        if ($has('etagere')) {
                            $etageres_plats[] = $etg;
                        }
                        foreach ($etg['barres'] ?? [] as $b) {
                            if ($has('barre')) {
                                $barres_plats[] = $b;
                            }
                        }
                    }
                }
            }
        }

        if (!$has('zone') && $has('rayon') && $rayons_plats !== []) {
            $node['rayons'] = array_values(array_map(function ($r) {
                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'numero' => (int) ($r['numero'] ?? 0),
                    'nom' => (string) ($r['nom'] ?? ''),
                    'zone_id' => (int) ($r['zone_id'] ?? 0),
                ];
            }, $rayons_plats));
        }
        if (!$has('rayon') && $has('etagere') && $etageres_plats !== []) {
            $node['etageres'] = array_values(array_map(function ($e) {
                return [
                    'id' => (int) ($e['id'] ?? 0),
                    'numero' => (int) ($e['numero'] ?? 0),
                    'nom' => (string) ($e['nom'] ?? ''),
                    'rayon_id' => (int) ($e['rayon_id'] ?? 0),
                ];
            }, $etageres_plats));
        }
        if (!$has('etagere') && $has('barre') && $barres_plats !== []) {
            $node['barres'] = array_values(array_map(function ($b) use ($has) {
                $bnode = [
                    'id' => (int) ($b['id'] ?? 0),
                    'numero' => (int) ($b['numero'] ?? 0),
                    'nom' => (string) ($b['nom'] ?? ''),
                    'etagere_id' => (int) ($b['etagere_id'] ?? 0),
                    'rayon_id' => (int) ($b['rayon_id'] ?? 0),
                    'positions' => [],
                ];
                if ($has('position')) {
                    foreach ($b['positions'] ?? [] as $p) {
                        $bnode['positions'][] = [
                            'id' => (int) ($p['id'] ?? 0),
                            'numero' => (int) ($p['numero'] ?? 0),
                            'nom' => (string) ($p['nom'] ?? ''),
                        ];
                    }
                }

                return $bnode;
            }, $barres_plats));
        }

        $champs_custom = produit_emplacement_champs_custom_json_etage($etage_id);
        if ($champs_custom !== []) {
            $node['champs_custom'] = $champs_custom;
        }

        $lie_barre = entrepot_structure_champ_get_lie_barre();
        if ($lie_barre !== null && $etage_id > 0) {
            $node['lie_barre'] = [
                'champ_id' => (int) $lie_barre['id'],
                'label' => (string) ($lie_barre['label'] ?? ''),
                'icon' => (string) ($lie_barre['icon'] ?? 'fa-cube'),
                'elements' => entrepot_get_champ_elements_etage($etage_id, (int) $lie_barre['id']),
            ];
        }

        $out[$n] = $node;
    }

    return $out;
}
