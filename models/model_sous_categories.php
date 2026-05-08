<?php
/**
 * Sous-catégories (liées à categories.id)
 */
require_once __DIR__ . '/../conn/conn.php';

function sous_categories_table_ok()
{
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
        $db->query('SELECT 1 FROM sous_categories LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_all_sous_categories_with_categorie_nom()
{
    global $db;
    if (!sous_categories_table_ok()) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT s.id, s.categorie_id, s.nom, s.description, c.nom AS categorie_nom
            FROM sous_categories s
            INNER JOIN categories c ON c.id = s.categorie_id
            ORDER BY c.nom ASC, s.nom ASC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function get_sous_categories_by_categorie_id($categorie_id)
{
    global $db;
    $categorie_id = (int) $categorie_id;
    if ($categorie_id <= 0 || !sous_categories_table_ok()) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT id, categorie_id, nom, description
            FROM sous_categories
            WHERE categorie_id = :cid
            ORDER BY nom ASC
        ');
        $stmt->execute(['cid' => $categorie_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_sous_categorie_by_id($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !sous_categories_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM sous_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param array{n?: string, categorie_id?: int, description?: string|null} $data
 * @return int|false id créé
 */
function create_sous_categorie($data)
{
    global $db;
    if (!sous_categories_table_ok()) {
        return false;
    }
    $nom = isset($data['nom']) ? trim((string) $data['nom']) : '';
    $cid = isset($data['categorie_id']) ? (int) $data['categorie_id'] : 0;
    $desc = isset($data['description']) ? trim((string) $data['description']) : '';
    if ($nom === '' || $cid <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO sous_categories (categorie_id, nom, description, date_creation)
            VALUES (:cid, :nom, :descr, NOW())
        ');
        $stmt->execute([
            'cid' => $cid,
            'nom' => $nom,
            'descr' => $desc !== '' ? $desc : null,
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $id
 * @param array{n?: string, categorie_id?: int, description?: string|null} $data
 * @return bool
 */
function update_sous_categorie($id, $data)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !sous_categories_table_ok()) {
        return false;
    }
    if (!get_sous_categorie_by_id($id)) {
        return false;
    }
    $nom = isset($data['nom']) ? trim((string) $data['nom']) : '';
    $cid = isset($data['categorie_id']) ? (int) $data['categorie_id'] : 0;
    $desc = array_key_exists('description', $data) ? trim((string) $data['description']) : '';
    if ($nom === '' || $cid <= 0) {
        return false;
    }
    try {
        require_once __DIR__ . '/model_categories.php';
        if (!get_categorie_by_id($cid)) {
            return false;
        }
        $stmt = $db->prepare('
            UPDATE sous_categories
            SET categorie_id = :cid, nom = :nom, description = :descr
            WHERE id = :id
        ');
        return $stmt->execute([
            'cid' => $cid,
            'nom' => $nom,
            'descr' => $desc !== '' ? $desc : null,
            'id' => $id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $id
 * @return bool
 */
function delete_sous_categorie($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !sous_categories_table_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('DELETE FROM sous_categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    } catch (PDOException $e) {
        return false;
    }
}
