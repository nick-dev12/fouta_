<?php
/**
 * Contrôleur pour les commandes personnalisées
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_commandes_personnalisees.php';

/**
 * S'assure que la colonne image_reference existe (migration automatique)
 */
function ensure_image_reference_column() {
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'image_reference'");
        if ($stmt && $stmt->rowCount() > 0) {
            return true;
        }
        $db->exec("ALTER TABLE commandes_personnalisees ADD COLUMN image_reference VARCHAR(255) NULL DEFAULT NULL AFTER description");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Retourne le type MIME réel d'une image uploadée
 * @param string $tmp_name
 * @return string
 */
function get_commande_personnalisee_image_mime_type($tmp_name) {
    if (!is_string($tmp_name) || $tmp_name === '' || !file_exists($tmp_name)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            return is_string($mime) ? $mime : '';
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp_name);
        return is_string($mime) ? $mime : '';
    }

    return '';
}

/**
 * Valide l'image jointe à une commande personnalisée
 * @param array $file
 * @return array
 */
function validate_commande_personnalisee_image($file) {
    if (!is_array($file) || empty($file)) {
        return ['success' => true, 'message' => '', 'mime' => '', 'extension' => ''];
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => '', 'mime' => '', 'extension' => ''];
    }

    if ($error_code !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Le téléversement de l\'image a échoué. Veuillez réessayer.', 'mime' => '', 'extension' => ''];
    }

    $file_size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($file_size <= 0) {
        return ['success' => false, 'message' => 'Le fichier image est invalide.', 'mime' => '', 'extension' => ''];
    }

    $mime_type = get_commande_personnalisee_image_mime_type($file['tmp_name'] ?? '');
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    if (!isset($allowed_mimes[$mime_type])) {
        return ['success' => false, 'message' => 'Format d\'image non autorisé. Utilisez JPG, PNG, WEBP ou GIF.', 'mime' => '', 'extension' => ''];
    }

    return [
        'success' => true,
        'message' => '',
        'mime' => $mime_type,
        'extension' => $allowed_mimes[$mime_type]
    ];
}

/**
 * Upload l'image jointe à une commande personnalisée
 * @param array $file
 * @return array
 */
function upload_commande_personnalisee_image($file) {
    $validation = validate_commande_personnalisee_image($file);
    if (!$validation['success']) {
        return ['success' => false, 'message' => $validation['message'], 'path' => null];
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => '', 'path' => null];
    }

    $upload_dir = __DIR__ . '/../upload/commandes-personnalisees/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['success' => false, 'message' => 'Impossible de préparer le dossier d\'upload de l\'image.', 'path' => null];
    }

    $filename = 'commande_perso_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $validation['extension'];
    $target_path = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => false, 'message' => 'Impossible d\'enregistrer l\'image de référence.', 'path' => null];
    }

    return ['success' => true, 'message' => '', 'path' => 'commandes-personnalisees/' . $filename];
}

/**
 * Traite la soumission d'une commande personnalisée
 * @return array ['success' => bool, 'message' => string]
 */
function process_commande_personnalisee() {
    $errors = [];
    $success = false;
    $message = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $type_produit = isset($_POST['type_produit']) ? trim($_POST['type_produit']) : '';
    $quantite = isset($_POST['quantite']) ? trim($_POST['quantite']) : '';
    $date_souhaitee = isset($_POST['date_souhaitee']) ? trim($_POST['date_souhaitee']) : '';
    $zone_livraison_id = isset($_POST['zone_livraison_id']) ? (int) $_POST['zone_livraison_id'] : null;
    $image_reference = null;
    $image_file = $_FILES['image_reference'] ?? null;

    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }

    if (empty($prenom)) {
        $errors[] = 'Le prénom est obligatoire.';
    } elseif (strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }

    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    }

    if (empty($telephone)) {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
        $errors[] = 'Le format du téléphone n\'est pas valide.';
    }

    if (empty($description)) {
        $errors[] = 'La description de votre demande est obligatoire.';
    } elseif (strlen($description) < 10) {
        $errors[] = 'Veuillez détailler davantage votre demande (minimum 10 caractères).';
    }

    if (!empty($date_souhaitee) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_souhaitee)) {
        $errors[] = 'La date souhaitée n\'est pas valide.';
    }

    if (is_array($image_file) && (($image_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $image_validation = validate_commande_personnalisee_image($image_file);
        if (!$image_validation['success']) {
            $errors[] = $image_validation['message'];
        }
    }

    if (empty($errors)) {
        if (is_array($image_file) && (($image_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            $upload_result = upload_commande_personnalisee_image($image_file);
            if (!$upload_result['success']) {
                $errors[] = $upload_result['message'];
            } else {
                $image_reference = $upload_result['path'];
            }
        }
    }

    if (empty($errors)) {
        ensure_image_reference_column();
        $data = [
            'user_id' => $user_id,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'description' => $description,
            'image_reference' => $image_reference,
            'type_produit' => $type_produit ?: null,
            'quantite' => $quantite ?: null,
            'date_souhaitee' => $date_souhaitee ?: null,
            'zone_livraison_id' => $zone_livraison_id > 0 ? $zone_livraison_id : null
        ];

        $id = create_commande_personnalisee($data);
        if ($id) {
            $success = true;
            $message = 'Votre demande de commande personnalisée a été envoyée avec succès. Nous vous contacterons rapidement.';
        } else {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }

    $message = $success ? $message : implode('<br>', $errors);
    return ['success' => $success, 'message' => $message];
}
