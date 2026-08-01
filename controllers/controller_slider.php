<?php
/**
 * Contrôleur pour la gestion du slider
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/fouta_upload_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../models/model_slider.php';

/**
 * Upload une image de slider
 * @param string $file_input_name Le nom du champ input file
 * @param string $current_image Le nom de l'image actuelle (pour mise à jour)
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_slider_image($file_input_name, $current_image = null) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_image; // Retourner l'image actuelle si pas de nouveau fichier
    }
    
    $file = $_FILES[$file_input_name];
    $upload_dir = __DIR__ . '/../upload/slider/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Vérifier le type de fichier
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];
    $file_type = $file['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['upload_error'] = 'Type de fichier non autorisé. Formats acceptés: JPEG, JPG, PNG, GIF, WEBP, AVIF';
        return false;
    }
    
    if ($file['size'] > FOUTA_UPLOAD_IMAGE_MAX_BYTES) {
        $_SESSION['upload_error'] = 'Le fichier est trop volumineux. Taille maximale : ' . fouta_upload_image_max_mo_int() . ' Mo';
        return false;
    }

    $name_prefix = 'slider_' . time() . '_';
    $result = upload_optimize_image_file($file, $upload_dir, 'slider', $name_prefix);
    if (empty($result['success'])) {
        $_SESSION['upload_error'] = !empty($result['message'])
            ? (string) $result['message']
            : 'Erreur lors de l\'upload de l\'image.';
        return false;
    }

    $new_filename = basename((string) ($result['relative_path'] ?? ''));
    if ($new_filename === '') {
        $_SESSION['upload_error'] = 'Erreur lors de l\'enregistrement de l\'image.';
        return false;
    }

    if ($current_image) {
        image_optimizer_delete_slider_file($current_image);
    }

    return $new_filename;
}

/**
 * Traite l'ajout d'un slide
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_slide() {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération des données
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $paragraphe = isset($_POST['paragraphe']) ? trim($_POST['paragraphe']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $bouton_lien = isset($_POST['bouton_lien']) ? trim($_POST['bouton_lien']) : '';
    $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    
    // Validation
    if (empty($titre)) {
        $errors[] = 'Le titre est obligatoire.';
    }
    
    if (empty($paragraphe)) {
        $errors[] = 'Le paragraphe est obligatoire.';
    }
    
    // Upload de l'image
    $image = upload_slider_image('image');
    if (!$image) {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'L\'image est obligatoire.';
        } else {
            // Vérifier s'il y a un message d'erreur spécifique
            if (isset($_SESSION['upload_error'])) {
                $errors[] = $_SESSION['upload_error'];
                unset($_SESSION['upload_error']);
            } else {
                $errors[] = 'Erreur lors de l\'upload de l\'image. Vérifiez que le fichier est au format JPEG, JPG, PNG, GIF, WEBP ou AVIF et ne dépasse pas ' . fouta_upload_image_max_mo_int() . ' Mo.';
            }
        }
    }
    
    // Si aucune erreur, créer le slide
    if (empty($errors)) {
        $slide_id = add_slide($titre, $paragraphe, $image, $bouton_texte, $bouton_lien, $ordre, $statut);
        
        if ($slide_id) {
            $success = true;
            $message = 'Slide ajouté avec succès.';
        } else {
            $errors[] = 'Une erreur est survenue lors de l\'ajout du slide.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la modification d'un slide
 * @param int $slide_id L'ID du slide
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_slide($slide_id) {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupérer le slide actuel
    $current_slide = get_slide_by_id($slide_id);
    if (!$current_slide) {
        return ['success' => false, 'message' => 'Slide non trouvé.'];
    }
    
    // Récupération des données
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $paragraphe = isset($_POST['paragraphe']) ? trim($_POST['paragraphe']) : '';
    $bouton_texte = isset($_POST['bouton_texte']) ? trim($_POST['bouton_texte']) : '';
    $bouton_lien = isset($_POST['bouton_lien']) ? trim($_POST['bouton_lien']) : '';
    $ordre = isset($_POST['ordre']) ? intval($_POST['ordre']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    
    // Validation
    if (empty($titre)) {
        $errors[] = 'Le titre est obligatoire.';
    }
    
    if (empty($paragraphe)) {
        $errors[] = 'Le paragraphe est obligatoire.';
    }
    
    // Upload de l'image (si nouvelle image fournie)
    $image = upload_slider_image('image', $current_slide['image']);
    if (!$image) {
        // Vérifier si une nouvelle image a été fournie mais qu'il y a eu une erreur
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Il y a eu une erreur lors de l'upload
            if (isset($_SESSION['upload_error'])) {
                $errors[] = $_SESSION['upload_error'];
                unset($_SESSION['upload_error']);
            } else {
                $errors[] = 'Erreur lors de l\'upload de l\'image. Vérifiez que le fichier est au format JPEG, JPG, PNG, GIF, WEBP ou AVIF et ne dépasse pas ' . fouta_upload_image_max_mo_int() . ' Mo.';
            }
            // Garder l'ancienne image en cas d'erreur
            $image = $current_slide['image'];
        } else {
            // Pas de nouvelle image fournie, garder l'ancienne
            $image = $current_slide['image'];
        }
    }
    
    // Si aucune erreur, mettre à jour le slide
    if (empty($errors)) {
        if (update_slide($slide_id, $titre, $paragraphe, $image, $bouton_texte, $bouton_lien, $ordre, $statut)) {
            $success = true;
            $message = 'Slide modifié avec succès.';
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification du slide.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la suppression d'un slide
 * @param int $slide_id L'ID du slide
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_slide($slide_id) {
    // Récupérer le slide pour supprimer l'image
    $slide = get_slide_by_id($slide_id);
    
    if (!$slide) {
        return ['success' => false, 'message' => 'Slide non trouvé.'];
    }
    
    // Supprimer le slide
    if (delete_slide($slide_id)) {
        if (!empty($slide['image'])) {
            image_optimizer_delete_slider_file($slide['image']);
        }

        return ['success' => true, 'message' => 'Slide supprimé avec succès.'];
    }
    
    return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
}

