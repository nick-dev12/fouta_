<?php
/**
 * Page de gestion des vidéos pour la section carrousel vidéo
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les vidéos
require_once __DIR__ . '/../../models/model_videos.php';
$videos = get_all_videos(null);

// Traiter les actions
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../controllers/controller_videos.php';

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $result = process_delete_video();
    } else {
        $result = process_video_form();
    }

    if (isset($result['success']) && $result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: videos.php');
        exit;
    } else {
        $error_message = $result['message'] ?? 'Une erreur est survenue';
    }
}

// Afficher le message de succès s'il existe
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer la vidéo à modifier si ID fourni
$video_to_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $video_to_edit = get_video_by_id((int) $_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Vidéos - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .btn-primary .btn-content { display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary .btn-loader { display: none; align-items: center; gap: 8px; }
    .btn-primary.loading .btn-content { display: none; }
    .btn-primary.loading .btn-loader { display: inline-flex; }
    .btn-primary.loading { pointer-events: none; }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <div class="videos-header">
            <div>
                <h2><i class="fas fa-video"></i> Gestion des Vidéos</h2>
                <p>Gérez les vidéos du carrousel "Ils ont découvert ICON"</p>
            </div>
            <button class="btn-add-video" onclick="openModal()">
                <i class="fas fa-plus"></i> Ajouter une vidéo
            </button>
        </div>

        <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if (empty($videos)): ?>
        <div class="empty-state">
            <i class="fas fa-video"></i>
            <h3>Aucune vidéo pour le moment</h3>
            <p>Cliquez sur "Ajouter une vidéo" pour commencer</p>
        </div>
        <?php else: ?>
        <div class="videos-grid">
            <?php foreach ($videos as $video): ?>
            <div class="video-card">
                <div class="video-card-preview">
                    <?php 
                    $video_path = '/upload/videos/' . htmlspecialchars($video['fichier_video']);
                    $thumbnail_path = !empty($video['image_preview']) ? '/upload/videos/thumbnails/' . htmlspecialchars($video['image_preview']) : null;
                    // Détecter le type MIME en fonction de l'extension
                    $video_ext = strtolower(pathinfo($video['fichier_video'], PATHINFO_EXTENSION));
                    $mime_types = [
                        'mp4' => 'video/mp4',
                        'webm' => 'video/webm',
                        'ogg' => 'video/ogg',
                        'ogv' => 'video/ogg',
                        'avi' => 'video/x-msvideo',
                        'mov' => 'video/quicktime',
                        'wmv' => 'video/x-ms-wmv',
                        'flv' => 'video/x-flv',
                        'mkv' => 'video/x-matroska'
                    ];
                    $video_type = isset($mime_types[$video_ext]) ? $mime_types[$video_ext] : 'video/mp4';
                    ?>
                    <video controls preload="metadata"
                        <?php if ($thumbnail_path && file_exists(__DIR__ . '/../../upload/videos/thumbnails/' . $video['image_preview'])): ?>
                        poster="<?php echo htmlspecialchars($thumbnail_path); ?>" <?php endif; ?>>
                        <source src="<?php echo htmlspecialchars($video_path); ?>"
                            type="<?php echo htmlspecialchars($video_type); ?>">
                        Votre navigateur ne supporte pas la lecture de vidéos.
                    </video>
                </div>

                <div class="video-card-title"><?php echo htmlspecialchars($video['titre']); ?></div>

                <div class="video-card-meta">
                    <span class="<?php echo $video['statut'] === 'actif' ? 'badge-actif' : 'badge-inactif'; ?>">
                        <?php echo strtoupper($video['statut']); ?>
                    </span>
                </div>

                <div class="video-card-actions">
                    <a href="?edit=<?php echo $video['id']; ?>" class="btn-edit"
                        onclick="openModal(<?php echo $video['id']; ?>); return false;">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                    <form method="POST" style="display: inline; flex: 1;"
                        onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette vidéo ?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                        <button type="submit" class="btn-delete">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Modal plein écran -->
    <div class="modal-overlay" id="videoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-<?php echo $video_to_edit ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $video_to_edit ? 'Modifier la Vidéo' : 'Ajouter une Vidéo'; ?>
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="videoForm">
                <?php if ($video_to_edit): ?>
                <input type="hidden" name="video_id" value="<?php echo $video_to_edit['id']; ?>">
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="titre">
                        <i class="fas fa-heading"></i> Titre de la vidéo (optionnel)
                    </label>
                    <input type="text" id="titre" name="titre"
                        value="<?php echo isset($_POST['titre']) ? htmlspecialchars($_POST['titre']) : ($video_to_edit ? htmlspecialchars($video_to_edit['titre']) : ''); ?>"
                        placeholder="Ex: Témoignage client (un titre sera généré automatiquement si vide)"
                        autocomplete="off">
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        Si vous laissez ce champ vide, un titre par défaut sera généré automatiquement.
                    </small>
                </div>

                <!-- Champ pour upload -->
                <div class="form-group">
                    <label for="fichier_video">
                        <i class="fas fa-file-video"></i> Fichier vidéo *
                    </label>
                    <div class="file-input-wrapper">
                        <label for="fichier_video" class="file-input-label">
                            <i class="fas fa-upload"></i>
                            <span>Choisir un fichier vidéo</span>
                        </label>
                        <input type="file" id="fichier_video" name="fichier_video"
                            <?php if (!$video_to_edit): ?>required<?php endif; ?> onchange="previewVideoFromFile(this)">
                    </div>
                    <small style="display: block; color: #666; font-size: 12px; margin-top: 5px;">
                        Tous les formats vidéo sont acceptés, sans limite de taille
                    </small>
                    <?php if ($video_to_edit && !empty($video_to_edit['fichier_video'])): ?>
                    <p style="margin-top: 10px; color: #666; font-size: 12px;">
                        <i class="fas fa-info-circle"></i> Fichier actuel:
                        <?php echo htmlspecialchars($video_to_edit['fichier_video']); ?>
                        <br>
                        <small style="color: #999;">Laissez vide pour conserver ce fichier, ou sélectionnez un nouveau
                            fichier pour le remplacer.</small>
                    </p>
                    <?php endif; ?>
                    <!-- Prévisualisation pour upload -->
                    <div id="previewUpload" class="video-preview-container" style="display: none; margin-top: 15px;">
                        <label style="display: block; margin-bottom: 10px; color: #6b2f20; font-weight: 600;">
                            <i class="fas fa-eye"></i> Aperçu de la vidéo
                        </label>
                        <div class="video-preview-wrapper">
                            <video id="previewVideo" controls style="width: 100%; max-height: 400px;"
                                <?php if ($video_to_edit && !empty($video_to_edit['fichier_video'])): ?>
                                data-existing-video="/upload/videos/<?php echo htmlspecialchars($video_to_edit['fichier_video']); ?>"
                                <?php endif; ?>>
                                Votre navigateur ne supporte pas la lecture de vidéos.
                            </video>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="statut">
                        <i class="fas fa-toggle-on"></i> Statut
                    </label>
                    <select id="statut" name="statut">
                        <option value="actif"
                            <?php echo (isset($_POST['statut']) && $_POST['statut'] === 'actif') || ($video_to_edit && $video_to_edit['statut'] === 'actif') ? 'selected' : ''; ?>>
                            Actif</option>
                        <option value="inactif"
                            <?php echo (isset($_POST['statut']) && $_POST['statut'] === 'inactif') || ($video_to_edit && $video_to_edit['statut'] === 'inactif') ? 'selected' : ''; ?>>
                            Inactif
                        </option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <span class="btn-content">
                            <i class="fas fa-save btn-icon"></i>
                            <span class="btn-text"><?php echo $video_to_edit ? 'Mettre à jour' : 'Ajouter'; ?> la
                                vidéo</span>
                        </span>
                        <span class="btn-loader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Enregistrement...</span>
                        </span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeModal()" id="cancelBtn">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>

                <!-- Indicateur de chargement -->
                    <div id="loadingIndicator" style="display: none; text-align: center; padding: 20px; margin-top: 20px;">
                    <div class="current-image" style="display: inline-block;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--couleur-dominante); margin-bottom: 10px;"></i>
                        <p style="color: var(--titres); font-weight: 600; margin: 0;">Enregistrement en cours...</p>
                        <p class="form-help" style="margin-top: 5px;">Veuillez patienter</p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    function openModal(videoId = null) {
        const modal = document.getElementById('videoModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Si on modifie, charger les données
        if (videoId) {
            // Initialiser la prévisualisation après un court délai
            setTimeout(function() {
                // Vérifier si une vidéo existante est disponible
                const previewVideo = document.getElementById('previewVideo');
                const existingVideoSrc = previewVideo ? previewVideo.getAttribute('data-existing-video') : null;
                if (existingVideoSrc) {
                    const previewContainer = document.getElementById('previewUpload');
                    previewVideo.src = existingVideoSrc;
                    previewContainer.style.display = 'block';
                }
            }, 300);
        } else {
            // Réinitialiser le formulaire
            document.getElementById('videoForm').reset();
        }
    }

    function closeModal() {
        // Réinitialiser l'état de chargement si le formulaire est en cours de soumission
        const submitBtn = document.getElementById('submitBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const loadingIndicator = document.getElementById('loadingIndicator');

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
        }
        if (cancelBtn) {
            cancelBtn.disabled = false;
            cancelBtn.style.opacity = '1';
            cancelBtn.style.cursor = 'pointer';
        }
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }

        const modal = document.getElementById('videoModal');
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';

        // Réinitialiser le formulaire
        document.getElementById('videoForm').reset();

        // Réinitialiser les prévisualisations
        const previewContainer = document.getElementById('previewUpload');
        if (previewContainer) {
            previewContainer.style.display = 'none';
        }

        window.location.href = 'videos.php';
    }

    // Initialiser la prévisualisation au chargement si une vidéo existe
    document.addEventListener('DOMContentLoaded', function() {
        const previewVideo = document.getElementById('previewVideo');
        const existingVideoSrc = previewVideo ? previewVideo.getAttribute('data-existing-video') : null;
        if (existingVideoSrc) {
            const previewContainer = document.getElementById('previewUpload');
            previewVideo.src = existingVideoSrc;
            previewContainer.style.display = 'block';
        }
    });

    // Ouvrir le modal si on modifie une vidéo OU si il y a une erreur
    <?php if ($video_to_edit || !empty($error_message)): ?>
    window.addEventListener('DOMContentLoaded', function() {
        <?php if ($video_to_edit): ?>
        openModal(<?php echo $video_to_edit['id']; ?>);
        <?php else: ?>
        // Ouvrir le modal en cas d'erreur pour afficher le message
        openModal();
        <?php endif; ?>
    });
    <?php endif; ?>

    // Fermer le modal en cliquant en dehors
    document.getElementById('videoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Fonction pour prévisualiser la vidéo depuis un fichier
    function previewVideoFromFile(input) {
        const previewContainer = document.getElementById('previewUpload');
        const previewVideo = document.getElementById('previewVideo');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                previewVideo.src = e.target.result;
                previewContainer.style.display = 'block';
            };

            reader.readAsDataURL(file);
        } else {
            previewContainer.style.display = 'none';
        }
    }

    // Afficher l'indicateur de chargement lors de la soumission
    // Le formulaire se soumet normalement côté PHP, JavaScript ne fait que gérer l'affichage visuel
    document.getElementById('videoForm').addEventListener('submit', function(e) {
        // Ne PAS utiliser preventDefault() - laisser le formulaire se soumettre normalement
        const submitBtn = document.getElementById('submitBtn');
        const loadingIndicator = document.getElementById('loadingIndicator');

        // Afficher le loader visuel seulement (sans bloquer la soumission)
        if (submitBtn) {
            submitBtn.classList.add('loading');
        }
        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }
    });
    </script>
</body>

</html>