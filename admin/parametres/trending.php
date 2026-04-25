<?php
/**
 * Page de modification de la configuration de la section trending
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer la configuration actuelle
require_once __DIR__ . '/../../models/model_trending.php';
$config = get_trending_config();

// Traiter le formulaire uniquement si c'est une requête POST
$error_message = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../controllers/controller_trending.php';
    $result = process_update_trending();
    
    // Si la modification est réussie, rediriger
    if (isset($result['success']) && $result['success']) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: ../parametres.php');
        exit;
    }
    
    // Afficher les messages d'erreur
    if (isset($result['success']) && !$result['success']) {
        $error_message = $result['message'];
    }
}

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Trending - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-fire"></i> Configuration de la Section Trending</h2>
            <p class="section-subtitle">
                Configurez le label, le titre, le bouton et l'image de la section trending
            </p>
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

        <div class="form-add-container param-form-container">
            <form method="POST" action="" enctype="multipart/form-data" class="form-add">
                <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
                <div class="form-group">
                    <label for="label">
                        <i class="fas fa-tag"></i> Label (petit texte)
                    </label>
                    <input type="text" id="label" name="label" 
                           value="<?php echo isset($_POST['label']) ? htmlspecialchars($_POST['label']) : htmlspecialchars($config['label']); ?>" 
                           required
                           placeholder="Ex: categories">
                </div>

                <div class="form-group">
                    <label for="titre">
                        <i class="fas fa-heading"></i> Titre principal
                    </label>
                    <input type="text" id="titre" name="titre" 
                           value="<?php echo isset($_POST['titre']) ? htmlspecialchars($_POST['titre']) : htmlspecialchars($config['titre']); ?>" 
                           required
                           placeholder="Ex: Enhance Your Music Experience">
                </div>

                <div class="form-group">
                    <label for="bouton_texte">
                        <i class="fas fa-hand-pointer"></i> Texte du bouton
                    </label>
                    <input type="text" id="bouton_texte" name="bouton_texte" 
                           value="<?php echo isset($_POST['bouton_texte']) ? htmlspecialchars($_POST['bouton_texte']) : htmlspecialchars($config['bouton_texte']); ?>" 
                           required
                           placeholder="Ex: Buy Now!">
                </div>

                <div class="form-group">
                    <label for="bouton_lien">
                        <i class="fas fa-link"></i> Lien du bouton
                    </label>
                    <input type="text" id="bouton_lien" name="bouton_lien" 
                           value="<?php echo isset($_POST['bouton_lien']) ? htmlspecialchars($_POST['bouton_lien']) : htmlspecialchars($config['bouton_lien']); ?>" 
                           placeholder="Ex: /produits.php ou #">
                </div>

                <div class="form-group">
                    <label for="image"><i class="fas fa-image"></i> Image</label>
                    <small class="form-help">Formats acceptés: JPEG, JPG, PNG, GIF, WEBP (max 50MB - Images 4K acceptées)</small>
                    
                    <?php if (!empty($config['image'])): ?>
                        <div class="current-image">
                            <strong>Image actuelle:</strong>
                            <?php if ($config['image'] !== 'speaker.png'): ?>
                                <img src="/upload/trending/<?php echo htmlspecialchars($config['image']); ?>" 
                                     alt="Image actuelle"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <?php else: ?>
                                <img src="/image/<?php echo htmlspecialchars($config['image']); ?>" 
                                     alt="Image actuelle"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <?php endif; ?>
                            <p style="display: none; color: var(--texte-fonce); margin-top: 10px;">
                                <i class="fas fa-info-circle"></i> Image non trouvée
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="file-input-wrapper">
                        <label for="image" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span><?php echo !empty($config['image']) ? 'Changer l\'image' : 'Choisir une image'; ?></span>
                        </label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input">
                    </div>
                    <div id="imagePreview" style="display: none; margin-top: 15px;">
                        <img src="" alt="Aperçu" class="image-preview" id="previewImg">
                    </div>
                </div>

                <div class="form-add-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Enregistrer la configuration
                    </button>
                    <a href="../parametres.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Retour aux paramètres
                    </a>
                </div>
            </form>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Aperçu de l'image avant upload
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('imagePreview').style.display = 'none';
            }
        });
    </script>
</body>
</html>

