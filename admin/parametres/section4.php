<?php
/**
 * Page de modification de la configuration de la section4
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer la configuration actuelle
require_once __DIR__ . '/../../models/model_section4.php';
$config = get_section4_config();

// Traiter le formulaire uniquement si c'est une requête POST
$error_message = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../controllers/controller_section4.php';
    $result = process_update_section4();

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
    <title>Configuration Section4 - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <section class="produits-section">
        <div class="section-title">
            <h2><i class="fas fa-image"></i> Configuration de la Section4</h2>
            <p class="section-subtitle">
                Configurez le titre, le texte et l'image de fond de la section d'accueil
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
                    <label for="titre">
                        <i class="fas fa-heading"></i> Titre principal <span class="optional">(optionnel)</span>
                    </label>
                    <input type="text" id="titre" name="titre"
                        value="<?php echo isset($_POST['titre']) ? htmlspecialchars($_POST['titre']) : htmlspecialchars($config['titre'] ?? ''); ?>"
                        placeholder="Ex: Bienvenue chez FOUTA POIDS LOURDS">
                    <small>Si vide, le titre ne sera pas affiché sur la page d'accueil</small>
                </div>

                <div class="form-group">
                    <label for="texte">
                        <i class="fas fa-text-width"></i> Texte secondaire <span class="optional">(optionnel)</span>
                    </label>
                    <input type="text" id="texte" name="texte"
                        value="<?php echo isset($_POST['texte']) ? htmlspecialchars($_POST['texte']) : htmlspecialchars($config['texte'] ?? ''); ?>"
                        placeholder="Ex: Tous les produits a petit prix">
                    <small>Si vide, le texte ne sera pas affiché sur la page d'accueil</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Afficher la section</label>
                    <div class="toggle-section-wrap">
                        <input type="hidden" name="statut" value="inactif">
                        <label class="toggle-switch">
                            <input type="checkbox" name="statut" value="actif" <?php echo ($config['statut'] ?? 'actif') === 'actif' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label" id="statut-label"><?php echo ($config['statut'] ?? 'actif') === 'actif' ? 'Section activée' : 'Section désactivée'; ?></span>
                    </div>
                    <small>Désactivez pour masquer complètement cette section sur la page d'accueil</small>
                </div>

                <div class="form-group">
                    <label for="image_fond"><i class="fas fa-image"></i> Image de fond <span class="optional">(optionnel)</span></label>
                    <small class="form-help">Formats acceptés: JPEG, JPG, PNG, GIF, WEBP (max 50MB). Laissez vide pour conserver l'image actuelle ou enregistrer sans image.</small>

                    <?php if (!empty($config['image_fond'])): ?>
                        <div class="current-image">
                            <strong>Image actuelle:</strong>
                            <img src="/upload/section4/<?php echo htmlspecialchars($config['image_fond']); ?>"
                                alt="Image de fond actuelle"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <p style="display: none; color: var(--texte-fonce); margin-top: 10px;">
                                <i class="fas fa-info-circle"></i> Image non trouvée
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="file-input-wrapper">
                        <label for="image_fond" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span><?php echo !empty($config['image_fond']) ? 'Changer l\'image' : 'Choisir une image'; ?></span>
                        </label>
                        <input type="file" id="image_fond" name="image_fond" class="file-input"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
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
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </form>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <style>
        .optional { font-weight: 400; color: #888; font-size: 12px; }
        .toggle-section-wrap { display: flex; align-items: center; gap: 15px; margin: 10px 0; }
        .toggle-switch { position: relative; display: inline-block; width: 54px; height: 28px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 28px; transition: 0.3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background: var(--couleur-dominante, #E5488A); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(26px); }
        .toggle-label { font-weight: 600; color: var(--titres, #3d2800); }
    </style>
    <script>
        document.querySelector('input[name="statut"][type="checkbox"]')?.addEventListener('change', function() {
            document.getElementById('statut-label').textContent = this.checked ? 'Section activée' : 'Section désactivée';
        });
        document.getElementById('image_fond').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
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