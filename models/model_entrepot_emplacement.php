<?php
/**
 * Configuration structure entrepôt (par étage).
 */
require_once __DIR__ . '/../conn/conn.php';

/** Limites globales de saisie admin */
define('ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX', 20);
define('ENTREPOT_EMPLACEMENT_NB_RAYONS_MAX', 500);
define('ENTREPOT_EMPLACEMENT_NB_PETIT_MAX', 50);

/**
 * Valeurs par défaut (fallback si tables absentes).
 *
 * @return array<string, int>
 */
function entrepot_emplacement_defaults_fallback() {
    return [
        'nb_etages' => 3,
        'nb_rayons' => 100,
        'nb_allees' => 10,
        'nb_zones' => 10,
        'nb_positions' => 10,
        'nb_barres' => 10,
    ];
}

/**
 * @return bool
 */
function entrepot_emplacement_tables_ok() {
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM entrepot_emplacement_config LIMIT 1');
        $db->query('SELECT 1 FROM entrepot_emplacement_etage LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @return array{nb_etages: int, date_modification: ?string}
 */
function entrepot_emplacement_get_config_row() {
    global $db;
    if (!entrepot_emplacement_tables_ok()) {
        return ['nb_etages' => 0, 'date_modification' => null];
    }
    try {
        $stmt = $db->query('SELECT nb_etages, date_modification FROM entrepot_emplacement_config WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['nb_etages' => 0, 'date_modification' => null];
        }

        return [
            'nb_etages' => (int) ($row['nb_etages'] ?? 0),
            'date_modification' => $row['date_modification'] ?? null,
        ];
    } catch (PDOException $e) {
        return ['nb_etages' => 0, 'date_modification' => null];
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function entrepot_emplacement_get_all_etages() {
    global $db;
    if (!entrepot_emplacement_tables_ok()) {
        return [];
    }
    try {
        $stmt = $db->query(
            'SELECT id, numero_etage, nb_rayons, nb_allees, nb_zones, nb_positions, nb_barres, date_modification
             FROM entrepot_emplacement_etage
             ORDER BY numero_etage ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array{config: array{nb_etages: int, date_modification: ?string}, etages: array<int, array<string, mixed>>}
 */
function entrepot_emplacement_get_config() {
    return [
        'config' => entrepot_emplacement_get_config_row(),
        'etages' => entrepot_emplacement_get_all_etages(),
    ];
}

/**
 * @param int $numero
 * @return array<string, mixed>|null
 */
function entrepot_emplacement_get_etage($numero) {
    global $db;
    $numero = (int) $numero;
    if ($numero <= 0 || !entrepot_emplacement_tables_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare(
            'SELECT id, numero_etage, nb_rayons, nb_allees, nb_zones, nb_positions, nb_barres, date_modification
             FROM entrepot_emplacement_etage WHERE numero_etage = :n LIMIT 1'
        );
        $stmt->execute([':n' => $numero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @return bool
 */
function entrepot_emplacement_est_configure() {
    if (!entrepot_emplacement_tables_ok()) {
        return false;
    }
    $cfg = entrepot_emplacement_get_config_row();
    if ((int) ($cfg['nb_etages'] ?? 0) <= 0) {
        return false;
    }
    $etages = entrepot_emplacement_get_all_etages();

    return count($etages) > 0;
}

/**
 * Map colonne produit → clé config étage.
 *
 * @return array<string, string>
 */
function entrepot_emplacement_col_to_config_key() {
    return [
        'etage' => 'numero_etage',
        'numero_rayon' => 'nb_rayons',
        'allee' => 'nb_allees',
        'zone_emplacement' => 'nb_zones',
        'position_emplacement' => 'nb_positions',
        'barre_rayon' => 'nb_barres',
    ];
}

/**
 * @param string $col
 * @param int|null $numero_etage
 * @return array{min: int, max: int}
 */
function entrepot_emplacement_get_limites_champ($col, $numero_etage = null) {
    $fallback = entrepot_emplacement_defaults_fallback();
    $min = 1;

    if ($col === 'etage') {
        if (entrepot_emplacement_est_configure()) {
            $cfg = entrepot_emplacement_get_config_row();

            return ['min' => 1, 'max' => max(1, (int) $cfg['nb_etages'])];
        }

        return ['min' => 1, 'max' => (int) $fallback['nb_etages']];
    }

    $map = entrepot_emplacement_col_to_config_key();
    $key = $map[$col] ?? null;
    if ($key === null) {
        return ['min' => 1, 'max' => 10];
    }

    if ($numero_etage !== null && $numero_etage > 0 && entrepot_emplacement_est_configure()) {
        $etage = entrepot_emplacement_get_etage($numero_etage);
        if ($etage && isset($etage[$key])) {
            return ['min' => 1, 'max' => max(1, (int) $etage[$key])];
        }
    }

    if (!entrepot_emplacement_est_configure()) {
        $fb_key = str_replace('numero_etage', 'nb_etages', $key);
        if ($key === 'nb_rayons') {
            return ['min' => 1, 'max' => (int) $fallback['nb_rayons']];
        }
        if (in_array($key, ['nb_allees', 'nb_zones', 'nb_positions', 'nb_barres'], true)) {
            return ['min' => 1, 'max' => (int) $fallback[$key]];
        }
    }

    return ['min' => 1, 'max' => 10];
}

/**
 * @param int $nb_etages
 * @param array<int, array<string, mixed>> $etages_data keyed by numero_etage
 * @return array{success: bool, message: string}
 */
function entrepot_emplacement_enregistrer_config($nb_etages, array $etages_data) {
    global $db;
    if (!entrepot_emplacement_tables_ok()) {
        return ['success' => false, 'message' => 'Tables absentes — exécutez migrations/run_create_entrepot_emplacement_config.php'];
    }

    $nb_etages = (int) $nb_etages;
    if ($nb_etages < 1 || $nb_etages > ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX) {
        return ['success' => false, 'message' => 'Nombre d’étages invalide (1 à ' . ENTREPOT_EMPLACEMENT_NB_ETAGES_MAX . ').'];
    }

    $prepared = [];
    for ($i = 1; $i <= $nb_etages; $i++) {
        if (!isset($etages_data[$i]) || !is_array($etages_data[$i])) {
            return ['success' => false, 'message' => 'Configuration manquante pour l’étage ' . $i . '.'];
        }
        $row = $etages_data[$i];
        $nb_rayons = isset($row['nb_rayons']) ? (int) $row['nb_rayons'] : 0;
        $nb_allees = isset($row['nb_allees']) ? (int) $row['nb_allees'] : 0;
        $nb_zones = isset($row['nb_zones']) ? (int) $row['nb_zones'] : 0;
        $nb_positions = isset($row['nb_positions']) ? (int) $row['nb_positions'] : 0;
        $nb_barres = isset($row['nb_barres']) ? (int) $row['nb_barres'] : 0;

        if ($nb_rayons < 1 || $nb_rayons > ENTREPOT_EMPLACEMENT_NB_RAYONS_MAX) {
            return ['success' => false, 'message' => 'Nombre de rayons invalide pour l’étage ' . $i . '.'];
        }
        foreach ([
            'allées' => $nb_allees,
            'zones' => $nb_zones,
            'positions' => $nb_positions,
            'barres' => $nb_barres,
        ] as $label => $val) {
            if ($val < 1 || $val > ENTREPOT_EMPLACEMENT_NB_PETIT_MAX) {
                return ['success' => false, 'message' => 'Nombre de ' . $label . ' invalide pour l’étage ' . $i . ' (1 à ' . ENTREPOT_EMPLACEMENT_NB_PETIT_MAX . ').'];
            }
        }

        $prepared[$i] = [
            'numero_etage' => $i,
            'nb_rayons' => $nb_rayons,
            'nb_allees' => $nb_allees,
            'nb_zones' => $nb_zones,
            'nb_positions' => $nb_positions,
            'nb_barres' => $nb_barres,
        ];
    }

    try {
        $db->beginTransaction();

        $stmt_cfg = $db->prepare(
            'INSERT INTO entrepot_emplacement_config (id, nb_etages, date_modification)
             VALUES (1, :nb, NOW())
             ON DUPLICATE KEY UPDATE nb_etages = VALUES(nb_etages), date_modification = NOW()'
        );
        $stmt_cfg->execute([':nb' => $nb_etages]);

        $stmt_upsert = $db->prepare(
            'INSERT INTO entrepot_emplacement_etage
                (numero_etage, nb_rayons, nb_allees, nb_zones, nb_positions, nb_barres, date_modification)
             VALUES (:numero_etage, :nb_rayons, :nb_allees, :nb_zones, :nb_positions, :nb_barres, NOW())
             ON DUPLICATE KEY UPDATE
                nb_rayons = VALUES(nb_rayons),
                nb_allees = VALUES(nb_allees),
                nb_zones = VALUES(nb_zones),
                nb_positions = VALUES(nb_positions),
                nb_barres = VALUES(nb_barres),
                date_modification = NOW()'
        );

        foreach ($prepared as $row) {
            $stmt_upsert->execute([
                ':numero_etage' => $row['numero_etage'],
                ':nb_rayons' => $row['nb_rayons'],
                ':nb_allees' => $row['nb_allees'],
                ':nb_zones' => $row['nb_zones'],
                ':nb_positions' => $row['nb_positions'],
                ':nb_barres' => $row['nb_barres'],
            ]);
        }

        $stmt_del = $db->prepare('DELETE FROM entrepot_emplacement_etage WHERE numero_etage > :max');
        $stmt_del->execute([':max' => $nb_etages]);

        $db->commit();

        return ['success' => true, 'message' => 'Structure entrepôt enregistrée (' . $nb_etages . ' étage(s)).'];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement : ' . $e->getMessage()];
    }
}

/**
 * Supprime un étage (config, référentiel nommé, emplacements produits liés).
 *
 * @param int $numero_etage
 * @return array{success: bool, message: string}
 */
function entrepot_emplacement_supprimer_etage($numero_etage) {
    global $db;
    if (!entrepot_emplacement_tables_ok()) {
        return ['success' => false, 'message' => 'Tables absentes — exécutez migrations/run_create_entrepot_emplacement_config.php'];
    }

    $numero_etage = (int) $numero_etage;
    if ($numero_etage <= 0) {
        return ['success' => false, 'message' => 'Numéro d’étage invalide.'];
    }

    if (entrepot_emplacement_get_etage($numero_etage) === null) {
        return ['success' => false, 'message' => 'Étage introuvable.'];
    }

    require_once __DIR__ . '/model_produits.php';
    require_once __DIR__ . '/model_entrepot_referentiel.php';

    try {
        $db->beginTransaction();

        if (produits_has_column('etage')) {
            $sql_legacy = 'UPDATE produits SET etage = NULL';
            if (produits_has_column('numero_rayon')) {
                $sql_legacy .= ', numero_rayon = NULL';
            }
            if (produits_has_column('allee')) {
                $sql_legacy .= ', allee = NULL';
            }
            if (produits_has_column('zone_emplacement')) {
                $sql_legacy .= ', zone_emplacement = NULL';
            }
            if (produits_has_column('position_emplacement')) {
                $sql_legacy .= ', position_emplacement = NULL';
            }
            if (produits_has_column('barre_rayon')) {
                $sql_legacy .= ', barre_rayon = NULL';
            }
            $sql_legacy .= ' WHERE etage = :e';
            $db->prepare($sql_legacy)->execute([':e' => (string) $numero_etage]);
        }

        if (entrepot_referentiel_tables_ok()) {
            entrepot_supprimer_referentiel_etage($numero_etage, $db);
        }

        $db->prepare('DELETE FROM entrepot_emplacement_etage WHERE numero_etage = :n')
            ->execute([':n' => $numero_etage]);

        $max = (int) $db->query('SELECT COALESCE(MAX(numero_etage), 0) FROM entrepot_emplacement_etage')->fetchColumn();
        $db->prepare(
            'UPDATE entrepot_emplacement_config SET nb_etages = :nb, date_modification = NOW() WHERE id = 1'
        )->execute([':nb' => $max]);

        $db->commit();

        return [
            'success' => true,
            'message' => 'Étage ' . $numero_etage . ' supprimé' . ($max > 0 ? ' (' . $max . ' étage(s) restant(s)).' : '. Aucun étage configuré.'),
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()];
    }
}

/**
 * Met à jour les quantités structurelles d’un étage (rayons, allées, zones, positions, barres).
 *
 * @param int $numero_etage
 * @param array<string, mixed> $data
 * @return array{success: bool, message: string}
 */
function entrepot_emplacement_enregistrer_quantites_etage($numero_etage, array $data) {
    global $db;
    if (!entrepot_emplacement_tables_ok()) {
        return ['success' => false, 'message' => 'Tables absentes — exécutez migrations/run_create_entrepot_emplacement_config.php'];
    }

    $numero_etage = (int) $numero_etage;
    if ($numero_etage <= 0 || entrepot_emplacement_get_etage($numero_etage) === null) {
        return ['success' => false, 'message' => 'Étage non configuré.'];
    }

    $nb_rayons = isset($data['nb_rayons']) ? (int) $data['nb_rayons'] : 0;
    $nb_allees = isset($data['nb_allees']) ? (int) $data['nb_allees'] : 0;
    $nb_zones = isset($data['nb_zones']) ? (int) $data['nb_zones'] : 0;
    $nb_positions = isset($data['nb_positions']) ? (int) $data['nb_positions'] : 0;
    $nb_barres = isset($data['nb_barres']) ? (int) $data['nb_barres'] : 0;

    if ($nb_rayons < 1 || $nb_rayons > ENTREPOT_EMPLACEMENT_NB_RAYONS_MAX) {
        return ['success' => false, 'message' => 'Nombre de rayons invalide (1 à ' . ENTREPOT_EMPLACEMENT_NB_RAYONS_MAX . ').'];
    }
    foreach ([
        'allées' => $nb_allees,
        'zones' => $nb_zones,
        'positions par barre' => $nb_positions,
        'barres' => $nb_barres,
    ] as $label => $val) {
        if ($val < 1 || $val > ENTREPOT_EMPLACEMENT_NB_PETIT_MAX) {
            return ['success' => false, 'message' => 'Nombre de ' . $label . ' invalide (1 à ' . ENTREPOT_EMPLACEMENT_NB_PETIT_MAX . ').'];
        }
    }

    try {
        $stmt = $db->prepare(
            'UPDATE entrepot_emplacement_etage
             SET nb_rayons = :nb_rayons, nb_allees = :nb_allees, nb_zones = :nb_zones,
                 nb_positions = :nb_positions, nb_barres = :nb_barres, date_modification = NOW()
             WHERE numero_etage = :numero_etage'
        );
        $stmt->execute([
            ':nb_rayons' => $nb_rayons,
            ':nb_allees' => $nb_allees,
            ':nb_zones' => $nb_zones,
            ':nb_positions' => $nb_positions,
            ':nb_barres' => $nb_barres,
            ':numero_etage' => $numero_etage,
        ]);

        return ['success' => true, 'message' => 'Quantités de l’étage ' . $numero_etage . ' mises à jour.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur quantités : ' . $e->getMessage()];
    }
}

/**
 * Données JSON pour le formulaire produit (limites par étage).
 *
 * @return array<int, array{nb_rayons: int, nb_allees: int, nb_zones: int, nb_positions: int, nb_barres: int}>
 */
function entrepot_emplacement_json_limites_par_etage() {
    $out = [];
    if (!entrepot_emplacement_est_configure()) {
        return $out;
    }
    foreach (entrepot_emplacement_get_all_etages() as $row) {
        $n = (int) ($row['numero_etage'] ?? 0);
        if ($n <= 0) {
            continue;
        }
        $out[$n] = [
            'nb_rayons' => (int) ($row['nb_rayons'] ?? 1),
            'nb_allees' => (int) ($row['nb_allees'] ?? 1),
            'nb_zones' => (int) ($row['nb_zones'] ?? 1),
            'nb_positions' => (int) ($row['nb_positions'] ?? 1),
            'nb_barres' => (int) ($row['nb_barres'] ?? 1),
        ];
    }

    return $out;
}
