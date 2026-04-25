<?php
/**
 * Contrôleur pour la gestion de la section4
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_section4.php';

/**
 * Traite le formulaire de modification de la section4
 * @return array Résultat de l'opération ['success' => bool, 'message' => string]
 */
function process_update_section4() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Méthode non autorisée'];
    }
    
    // Données optionnelles (titre et texte peuvent être vides)
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $texte = isset($_POST['texte']) ? trim($_POST['texte']) : '';
    $statut = isset($_POST['statut']) && $_POST['statut'] === 'inactif' ? 'inactif' : 'actif';
    
    // Gestion de l'upload de l'image
    $image_fond = null;
    
    if (isset($_FILES['image_fond']) && $_FILES['image_fond']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_section4_image($_FILES['image_fond']);
        
        if ($upload_result['success']) {
            $image_fond = $upload_result['filename'];
            
            // Supprimer l'ancienne image si elle existe
            $current_config = get_section4_config();
            if ($current_config && !empty($current_config['image_fond']) && $current_config['image_fond'] !== $image_fond) {
                delete_section4_image($current_config['image_fond']);
            }
        } else {
            return ['success' => false, 'message' => $upload_result['message']];
        }
    } else {
        // Garder l'image existante si aucune nouvelle image n'est uploadée
        $current_config = get_section4_config();
        if ($current_config && !empty($current_config['image_fond'])) {
            $image_fond = $current_config['image_fond'];
        }
    }
    
    $data = [
        'titre' => $titre,
        'texte' => $texte,
        'image_fond' => $image_fond,
        'statut' => $statut
    ];
    
    $result = update_section4_config($data);
    if ($result['success']) {
        return ['success' => true, 'message' => 'Configuration de la section4 mise à jour avec succès'];
    }
    $msg = !empty($result['message']) ? $result['message'] : 'Erreur lors de la mise à jour de la configuration';
    return ['success' => false, 'message' => $msg];
}

/**
 * Gère l'upload de l'image de fond de la section4
 * @param array $file Le fichier uploadé ($_FILES['image_fond'])
 * @return array Résultat de l'upload ['success' => bool, 'filename' => string|null, 'message' => string]
 */
function upload_section4_image($file) {
    $upload_dir = __DIR__ . '/../upload/section4/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Vérifier le type de fichier
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'filename' => null, 'message' => 'Type de fichier non autorisé. Formats acceptés: JPEG, JPG, PNG, GIF, WEBP'];
    }
    
    // Vérifier la taille (max 50MB pour permettre les images 4K)
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'filename' => null, 'message' => 'Le fichier est trop volumineux. Taille maximale: 50MB'];
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'section4_' . time() . '_' . uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'filename' => $filename, 'message' => 'Image uploadée avec succès'];
    } else {
        return ['success' => false, 'filename' => null, 'message' => 'Erreur lors de l\'upload du fichier'];
    }
}

?>

