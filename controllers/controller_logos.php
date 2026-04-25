<?php
/**
 * Contrôleur pour la gestion des logos partenaires
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_logos.php';

/**
 * Upload une image de logo
 * @param array $file $_FILES
 * @param string $field Nom du champ (défaut: image)
 * @return string|false Chemin relatif (logos/xxx.png) ou False
 */
function upload_logo_image($file, $field = 'image') {
    if (!isset($file[$field]) || $file[$field]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $upload_dir = __DIR__ . '/../upload/logos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    $info = $file[$field];
    if (!in_array($info['type'], $allowed) || $info['size'] > $max_size) {
        return false;
    }
    $ext = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION)) ?: 'png';
    $filename = uniqid('logo_', true) . '.' . $ext;
    if (move_uploaded_file($info['tmp_name'], $upload_dir . $filename)) {
        return 'logos/' . $filename;
    }
    return false;
}

/**
 * Traite l'ajout d'un logo
 * @return array ['success' => bool, 'message' => string]
 */
function process_add_logo() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['add_logo'])) {
        return ['success' => false, 'message' => ''];
    }
    $image = upload_logo_image($_FILES, 'image');
    if (!$image) {
        return ['success' => false, 'message' => 'Veuillez sélectionner une image valide (JPG, PNG, GIF, WebP, max 2 Mo).'];
    }
    $ordre = isset($_POST['ordre']) ? (int) $_POST['ordre'] : 0;
    $id = create_logo($image, $ordre, 'actif');
    if ($id) {
        return ['success' => true, 'message' => 'Logo ajouté avec succès.'];
    }
    @unlink(__DIR__ . '/../upload/' . $image);
    return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
}

/**
 * Traite la modification d'un logo
 * @return array
 */
function process_update_logo() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['update_logo'])) {
        return ['success' => false, 'message' => ''];
    }
    $logo_id = isset($_POST['logo_id']) ? (int) $_POST['logo_id'] : 0;
    $logo = get_logo_by_id($logo_id);
    if (!$logo) {
        return ['success' => false, 'message' => 'Logo introuvable.'];
    }
    $image = $logo['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $new_image = upload_logo_image($_FILES, 'image');
        if ($new_image) {
            $old_path = __DIR__ . '/../upload/' . $logo['image'];
            if (file_exists($old_path)) {
                @unlink($old_path);
            }
            $image = $new_image;
        }
    }
    $ordre = isset($_POST['ordre']) ? (int) $_POST['ordre'] : $logo['ordre'];
    if (update_logo($logo_id, $image, $ordre, null)) {
        return ['success' => true, 'message' => 'Logo modifié avec succès.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de la modification.'];
}

/**
 * Traite la suppression d'un logo
 * @return array
 */
function process_delete_logo() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action']) || $_POST['action'] !== 'delete') {
        return ['success' => false, 'message' => ''];
    }
    $logo_id = isset($_POST['logo_id']) ? (int) $_POST['logo_id'] : 0;
    $logo = get_logo_by_id($logo_id);
    if (!$logo) {
        return ['success' => false, 'message' => 'Logo introuvable.'];
    }
    if (delete_logo($logo_id)) {
        $path = __DIR__ . '/../upload/' . $logo['image'];
        if (file_exists($path)) {
            @unlink($path);
        }
        return ['success' => true, 'message' => 'Logo supprimé.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
}
