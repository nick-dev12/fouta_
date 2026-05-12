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
        $stmt = $db->query('SELECT id, nom, date_creation FROM marques ORDER BY nom ASC');
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
        $stmt = $db->prepare('SELECT id, nom, date_creation FROM marques WHERE id = ? LIMIT 1');
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
function create_marque_row($nom) {
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
        return [
            'success' => true,
            'message' => 'Marque enregistrée.',
            'id' => (int) $db->lastInsertId(),
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
function update_marque_row($id, $nom) {
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
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Marque introuvable.'];
        }
        return ['success' => true, 'message' => 'Marque mise à jour.'];
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'message' => 'Ce nom de marque existe déjà.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }
}
