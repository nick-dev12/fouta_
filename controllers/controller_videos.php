<?php
/**
 * Contrôleur pour la gestion des vidéos
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_videos.php';

/**
 * Traite le formulaire d'ajout/modification de vidéo
 * @return array Résultat de l'opération ['success' => bool, 'message' => string]
 */
function process_video_form()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Méthode non autorisée'];
    }

    // Vérifier si le POST a été tronqué à cause de post_max_size
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_post_size = ini_get('post_max_size');
        $max_upload_size = ini_get('upload_max_filesize');
        return ['success' => false, 'message' => 'Le fichier est trop volumineux. Configuration PHP actuelle: post_max_size=' . $max_post_size . ', upload_max_filesize=' . $max_upload_size . '. Augmentez ces valeurs dans votre php.ini (recherchez "php.ini" via WampServer → PHP → php.ini) et REDÉMARREZ Apache après modification. Consultez le fichier GUIDE_CONFIGURATION_PHP.md pour plus d\'informations.'];
    }

    // Récupération des données
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $statut = isset($_POST['statut']) ? trim($_POST['statut']) : 'actif';
    $video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

    // Si le titre est vide, générer un titre par défaut
    if (empty($titre)) {
        $titre = 'Vidéo - ' . date('d/m/Y à H:i');
    }

    // Gestion de l'upload de la vidéo
    $fichier_video = null;
    $image_preview = null;

    // Vérifier si un fichier a été uploadé - méthode la plus simple possible
    $has_file = false;
    if (isset($_FILES['fichier_video']) && !empty($_FILES['fichier_video']['name'])) {
        // Un fichier a été sélectionné
        if ($_FILES['fichier_video']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['fichier_video']['tmp_name'])) {
            // Fichier uploadé avec succès
            $has_file = true;
            $upload_result = upload_video_file($_FILES['fichier_video']);

            if ($upload_result['success']) {
                $fichier_video = $upload_result['filename'];
                $image_preview = $upload_result['thumbnail'] ?? null;

                // Supprimer l'ancienne vidéo et son thumbnail si elle existe
                if ($video_id > 0) {
                    $current_video = get_video_by_id($video_id);
                    if ($current_video) {
                        if (!empty($current_video['fichier_video']) && $current_video['fichier_video'] !== $fichier_video) {
                            $old_video_path = __DIR__ . '/../upload/videos/' . $current_video['fichier_video'];
                            if (file_exists($old_video_path)) {
                                unlink($old_video_path);
                            }
                        }
                        // Supprimer l'ancien thumbnail
                        if (!empty($current_video['image_preview'])) {
                            $old_thumbnail_path = __DIR__ . '/../upload/videos/thumbnails/' . $current_video['image_preview'];
                            if (file_exists($old_thumbnail_path)) {
                                unlink($old_thumbnail_path);
                            }
                        }
                    }
                }
            } else {
                return ['success' => false, 'message' => $upload_result['message']];
            }
        } elseif ($_FILES['fichier_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Il y a une erreur d'upload
            $error_code = $_FILES['fichier_video']['error'];
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur PHP (upload_max_filesize). Taille actuelle: ' . ini_get('upload_max_filesize'),
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire. post_max_size: ' . ini_get('post_max_size'),
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé. Veuillez réessayer.',
                UPLOAD_ERR_NO_TMP_DIR => 'Erreur serveur : dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Erreur serveur : impossible d\'écrire le fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Erreur serveur : une extension PHP a arrêté l\'upload'
            ];

            $error_message = isset($error_messages[$error_code])
                ? $error_messages[$error_code]
                : 'Erreur lors de l\'upload (code: ' . $error_code . ')';

            return ['success' => false, 'message' => $error_message];
        }
    }

    // Si aucun fichier n'a été uploadé avec succès
    if (!$has_file) {
        // Si c'est une modification, garder le fichier existant
        if ($video_id > 0) {
            $current_video = get_video_by_id($video_id);
            if ($current_video && !empty($current_video['fichier_video'])) {
                $fichier_video = $current_video['fichier_video'];
                // Garder l'image_preview existante si aucune nouvelle n'a été générée
                if (!isset($image_preview)) {
                    $image_preview = $current_video['image_preview'] ?? null;
                }
            } else {
                // Pas de fichier existant et pas de nouveau fichier uploadé
                return ['success' => false, 'message' => 'Veuillez sélectionner un fichier vidéo'];
            }
        } else {
            // Nouvelle vidéo sans fichier
            return ['success' => false, 'message' => 'Veuillez sélectionner un fichier vidéo'];
        }
    }

    // Préparer les données
    $data = [
        'titre' => $titre,
        'fichier_video' => $fichier_video,
        'statut' => $statut
    ];

    // Ajouter image_preview si disponible
    if (isset($image_preview)) {
        $data['image_preview'] = $image_preview;
    }

    // Créer ou mettre à jour
    if ($video_id > 0) {
        // Mise à jour
        if (update_video($video_id, $data)) {
            return ['success' => true, 'message' => 'Vidéo mise à jour avec succès'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour de la vidéo'];
        }
    } else {
        // Création
        $new_id = create_video($data);
        if ($new_id) {
            return ['success' => true, 'message' => 'Vidéo créée avec succès', 'id' => $new_id];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la création de la vidéo'];
        }
    }
}

/**
 * Traite la suppression d'une vidéo
 * @return array Résultat de l'opération ['success' => bool, 'message' => string]
 */
function process_delete_video()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Méthode non autorisée'];
    }

    $video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

    if ($video_id <= 0) {
        return ['success' => false, 'message' => 'ID de vidéo invalide'];
    }

    if (delete_video($video_id)) {
        return ['success' => true, 'message' => 'Vidéo supprimée avec succès'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la suppression de la vidéo'];
    }
}

/**
 * Génère une image de prévisualisation (thumbnail) à partir d'une vidéo
 * @param string $video_path Chemin complet vers le fichier vidéo
 * @param string $output_path Chemin où sauvegarder l'image générée
 * @param int $time_offset Position dans la vidéo en secondes (par défaut: 1 seconde)
 * @return bool True en cas de succès, False sinon
 */
function generate_video_thumbnail($video_path, $output_path, $time_offset = 1)
{
    // Vérifier que le fichier vidéo existe
    if (!file_exists($video_path)) {
        return false;
    }

    // Essayer d'utiliser FFmpeg (solution la plus fiable)
    $ffmpeg_path = 'ffmpeg'; // Par défaut, supposer que ffmpeg est dans le PATH
    // Si FFmpeg n'est pas dans le PATH, vous pouvez spécifier le chemin complet :
    // $ffmpeg_path = 'C:\\ffmpeg\\bin\\ffmpeg.exe'; // Exemple pour Windows

    // Vérifier si FFmpeg est disponible
    $ffmpeg_check = shell_exec("$ffmpeg_path -version 2>&1");
    if (strpos($ffmpeg_check, 'ffmpeg version') === false) {
        // FFmpeg non disponible, essayer avec chemin complet commun sur Windows
        $common_paths = [
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\wamp64\\bin\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe'
        ];

        $ffmpeg_found = false;
        foreach ($common_paths as $path) {
            if (file_exists($path)) {
                $ffmpeg_path = $path;
                $ffmpeg_found = true;
                break;
            }
        }

        if (!$ffmpeg_found) {
            // FFmpeg non trouvé, on ne peut pas générer de thumbnail
            return false;
        }
    }

    // Générer la commande FFmpeg pour extraire une frame à la position spécifiée
    // -ss : position dans la vidéo (time_offset secondes)
    // -i : fichier d'entrée (la vidéo)
    // -vframes 1 : extraire 1 seule frame
    // -q:v 2 : qualité de l'image (2 = haute qualité)
    // -y : écraser le fichier de sortie s'il existe

    $command = sprintf(
        '"%s" -ss %d -i "%s" -vframes 1 -q:v 2 -y "%s" 2>&1',
        $ffmpeg_path,
        $time_offset,
        escapeshellarg($video_path),
        escapeshellarg($output_path)
    );

    // Exécuter la commande
    $output = shell_exec($command);

    // Vérifier si l'image a été créée
    if (file_exists($output_path) && filesize($output_path) > 0) {
        return true;
    }

    return false;
}

/**
 * Gère l'upload du fichier vidéo - Version simplifiée sans restrictions
 * @param array $file Le fichier uploadé ($_FILES['fichier_video'])
 * @return array Résultat de l'upload ['success' => bool, 'filename' => string|null, 'thumbnail' => string|null, 'message' => string]
 */
function upload_video_file($file)
{
    $upload_dir = __DIR__ . '/../upload/videos/';
    $thumbnails_dir = __DIR__ . '/../upload/videos/thumbnails/';

    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Créer le dossier pour les thumbnails s'il n'existe pas
    if (!file_exists($thumbnails_dir)) {
        mkdir($thumbnails_dir, 0755, true);
    }

    // Vérifier les erreurs d'upload PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur PHP',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload'
        ];

        $error_message = isset($error_messages[$file['error']])
            ? $error_messages[$file['error']]
            : 'Erreur lors de l\'upload (code: ' . $file['error'] . ')';

        return ['success' => false, 'filename' => null, 'thumbnail' => null, 'message' => $error_message];
    }

    // Récupérer l'extension du fichier original
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Si pas d'extension, utiliser une extension par défaut
    if (empty($extension)) {
        $extension = 'mp4';
    }

    // Générer un nom de fichier unique
    $filename = 'video_' . time() . '_' . uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;

    // Déplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Générer une thumbnail (essayer à 1 seconde du début)
        $thumbnail_filename = null;
        $thumbnail_name = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $thumbnail_path = $thumbnails_dir . $thumbnail_name;

        if (generate_video_thumbnail($file_path, $thumbnail_path, 1)) {
            $thumbnail_filename = $thumbnail_name;
        }

        return [
            'success' => true,
            'filename' => $filename,
            'thumbnail' => $thumbnail_filename,
            'message' => 'Vidéo uploadée avec succès' . ($thumbnail_filename ? ' (thumbnail généré)' : ' (thumbnail non généré - FFmpeg requis)')
        ];
    } else {
        return ['success' => false, 'filename' => null, 'thumbnail' => null, 'message' => 'Erreur lors du déplacement du fichier sur le serveur'];
    }
}

?>