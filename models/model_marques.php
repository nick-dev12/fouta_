<?php
/**
 * Modèle marques (référentiel paramètres)
 * Procédural uniquement
 */
require_once __DIR__ . '/../conn/conn.php';

function marques_table_ok() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT 1 FROM marques LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return array<int, array{id:int,nom:string,date_creation:string}>
 */
function get_all_marques_ordered_by_nom() {
    global $db;
    if (!$db || !marques_table_ok()) {
        return [];
    }
    try {
        /* toutes les colonnes (abréviation comprise, 07/09) ; les marques retirées (suppression douce) n'apparaissent plus */
        $stmt = $db->query('SELECT * FROM marques WHERE sync_deleted_at IS NULL ORDER BY nom ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array{id:int,nom:string,date_creation:string}|null
 */
function get_marque_by_id($id) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !$db || !marques_table_ok()) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM marques WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @return array{success:bool, message:string, id:int|null}
 */
function create_marque_row($nom, $abreviation = null) {
    global $db;
    if (!marques_table_ok()) {
        return ['success' => false, 'message' => 'Table marques absente. Exécutez la migration create_marques.', 'id' => null];
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom de la marque est obligatoire.', 'id' => null];
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($nom, 'UTF-8') > 255) {
            $nom = mb_substr($nom, 0, 255, 'UTF-8');
        }
    } elseif (strlen($nom) > 255) {
        $nom = substr($nom, 0, 255);
    }
    try {
        $stmt = $db->prepare('INSERT INTO marques (nom, date_creation) VALUES (:nom, NOW())');
        $stmt->execute(['nom' => $nom]);
        $nouvel_id = (int) $db->lastInsertId();
        /* L'ABRÉVIATION DANS LA RÉFÉRENCE FPL (07/09) : celle saisie, sinon proposée
         * (liste de la direction, sinon 3 lettres), toujours unique. */
        marque_abreviation_poser($nouvel_id, $abreviation, $nom);
        return [
            'success' => true,
            'message' => 'Marque enregistrée.',
            'id' => $nouvel_id,
        ];
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'message' => 'Ce nom de marque existe déjà.', 'id' => null];
        }
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.', 'id' => null];
    }
}

/**
 * @return array{success:bool, message:string}
 */
function update_marque_row($id, $nom, $abreviation = null) {
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !marques_table_ok()) {
        return ['success' => false, 'message' => 'Données invalides.'];
    }
    $nom = trim((string) $nom);
    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom de la marque est obligatoire.'];
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($nom, 'UTF-8') > 255) {
            $nom = mb_substr($nom, 0, 255, 'UTF-8');
        }
    } elseif (strlen($nom) > 255) {
        $nom = substr($nom, 0, 255);
    }
    try {
        $stmt = $db->prepare('UPDATE marques SET nom = :nom WHERE id = :id');
        $stmt->execute(['nom' => $nom, 'id' => $id]);
        if ($abreviation !== null) {
            $r = marque_abreviation_poser($id, $abreviation, $nom);
            if (!$r['success']) {
                return $r;
            }
        }
        return ['success' => true, 'message' => 'Marque mise à jour.'];
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'message' => 'Ce nom de marque existe déjà.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }
}

/**
 * Pose l'abréviation d'une marque (07/09) : celle donnée (2 à 4 lettres/chiffres,
 * en majuscules, unique), sinon une proposition. Si elle change, les références
 * FPL des pièces de la marque sont recalculées. Sans la colonne, ne fait rien.
 *
 * @return array{success:bool, message:string}
 */
function marque_abreviation_poser($id, $abreviation, $nom = '') {
    global $db;
    $id = (int) $id;
    if (!is_file(__DIR__ . '/model_reference_fpl.php')) {
        return ['success' => true, 'message' => ''];
    }
    require_once __DIR__ . '/model_reference_fpl.php';
    if (!rf_colonne_ok('marques', 'abreviation')) {
        return ['success' => true, 'message' => ''];
    }
    $abr = strtoupper(trim((string) $abreviation));
    if ($abr !== '' && !preg_match('/^[A-Z0-9]{2,4}$/', $abr)) {
        return ['success' => false, 'message' => 'L’abréviation doit faire 2 à 4 lettres ou chiffres (ex. MER, RVI, HW).'];
    }
    if ($abr === '') {
        $abr = marque_abreviation_proposer($nom, $id);
    } else {
        $st = $db->prepare('SELECT nom FROM marques WHERE abreviation = :a AND id <> :id AND sync_deleted_at IS NULL');
        $st->execute([':a' => $abr, ':id' => $id]);
        $autre = $st->fetchColumn();
        if ($autre) {
            return ['success' => false, 'message' => 'L’abréviation ' . $abr . ' est déjà prise par ' . $autre . '.'];
        }
    }
    $actuelle = (string) $db->query('SELECT abreviation FROM marques WHERE id = ' . $id)->fetchColumn();
    if ($actuelle === $abr) {
        return ['success' => true, 'message' => ''];
    }
    $db->prepare('UPDATE marques SET abreviation = :a WHERE id = :id')->execute([':a' => $abr, ':id' => $id]);
    produit_reference_fpl_cache_vider();
    /* les références des pièces de cette marque suivent */
    foreach ($db->query('SELECT id FROM produits WHERE marque_id = ' . $id . ' AND sync_deleted_at IS NULL') as $p) {
        try {
            produit_reference_fpl_maj((int) $p['id']);
        } catch (Throwable $e) {
        }
    }
    return ['success' => true, 'message' => ''];
}

