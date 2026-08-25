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
            SELECT s.*, c.nom AS categorie_nom
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
            SELECT *
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
        /* L'image et les mots-clés (colonnes du 23/08, reprises de FPL natif)
         * ne s'écrivent que si la base les a : la fonction marche avant comme
         * après la migration. */
        $cols = 'categorie_id, nom, description, date_creation';
        $vals = ':cid, :nom, :descr, NOW()';
        $params = [
            'cid' => $cid,
            'nom' => $nom,
            'descr' => $desc !== '' ? $desc : null,
        ];
        if (sous_categories_has_column('image') && array_key_exists('image', $data)) {
            $cols .= ', image';
            $vals .= ', :image';
            $img = trim((string) $data['image']);
            $params['image'] = $img !== '' ? $img : null;
        }
        if (sous_categories_has_column('mots_cles') && array_key_exists('mots_cles', $data)) {
            $cols .= ', mots_cles';
            $vals .= ', :mots_cles';
            $mc = trim((string) $data['mots_cles']);
            $params['mots_cles'] = $mc !== '' ? mb_substr($mc, 0, 500) : null;
        }
        $stmt = $db->prepare("INSERT INTO sous_categories ($cols) VALUES ($vals)");
        $stmt->execute($params);
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
        $sets = 'categorie_id = :cid, nom = :nom, description = :descr';
        $params = [
            'cid' => $cid,
            'nom' => $nom,
            'descr' => $desc !== '' ? $desc : null,
            'id' => $id,
        ];
        // L'image ne change que si on en donne une (clé présente et non vide)
        if (sous_categories_has_column('image') && !empty($data['image'])) {
            $sets .= ', image = :image';
            $params['image'] = trim((string) $data['image']);
        }
        if (sous_categories_has_column('mots_cles') && array_key_exists('mots_cles', $data)) {
            $sets .= ', mots_cles = :mots_cles';
            $mc = trim((string) $data['mots_cles']);
            $params['mots_cles'] = $mc !== '' ? mb_substr($mc, 0, 500) : null;
        }
        $stmt = $db->prepare("UPDATE sous_categories SET $sets WHERE id = :id");
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/** Une colonne de sous_categories existe-t-elle ? (une vérification par requête) */
function sous_categories_has_column($nom)
{
    global $db;
    static $colonnes = null;

    if ($colonnes === null) {
        $colonnes = [];
        try {
            foreach ($db->query('SHOW COLUMNS FROM sous_categories') as $c) {
                $colonnes[strtolower((string) $c['Field'])] = true;
            }
        } catch (PDOException $e) {
            $colonnes = [];
        }
    }

    return isset($colonnes[strtolower((string) $nom)]);
}

/**
 * L'image d'un rayon — upload TOLÉRANT : un fichier refusé renvoie null, la
 * sous-catégorie s'enregistre quand même. Même règle que les catégories de
 * ce dépôt (JPG, PNG, WEBP, GIF ; taille plafonnée), rangée à part dans
 * upload/sous_categories/ ; la valeur renvoyée est relative à upload/.
 * (Portage de categorie_image_enregistrer() de FPL natif.)
 */
function sous_categorie_image_enregistrer($fichier)
{
    if (!is_array($fichier) || !isset($fichier['error']) || (int) $fichier['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $max = defined('FOUTA_UPLOAD_IMAGE_MAX_BYTES') ? FOUTA_UPLOAD_IMAGE_MAX_BYTES : 8 * 1024 * 1024;
    if ((int) $fichier['size'] > $max) {
        return null;
    }
    $extensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
    $extension = strtolower(pathinfo((string) $fichier['name'], PATHINFO_EXTENSION));
    if (!isset($extensions[$extension])) {
        return null;
    }
    if (function_exists('getimagesize') && @getimagesize($fichier['tmp_name']) === false) {
        return null;
    }

    $dossier = __DIR__ . '/../upload/sous_categories';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0775, true);
    }
    $nom = 'rayon_' . bin2hex(random_bytes(10)) . '.' . $extensions[$extension];
    if (!move_uploaded_file($fichier['tmp_name'], $dossier . '/' . $nom)) {
        return null;
    }

    return 'sous_categories/' . $nom;
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
