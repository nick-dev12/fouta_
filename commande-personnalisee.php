<?php
/**
 * Page de demande de commande personnalisée
 * Accessible à tous (connectés ou non)
 */

session_start();

require_once __DIR__ . '/controllers/controller_commandes_personnalisees.php';
require_once __DIR__ . '/models/model_zones_livraison.php';
$result = process_commande_personnalisee();
$zones_livraison = get_all_zones_livraison('actif');

if ($result['success']) {
    $_SESSION['commande_perso_success'] = $result['message'];
    header('Location: index.php?commande_perso=1');
    exit;
}

$prefill = [
    'nom' => $_SESSION['user_nom'] ?? '',
    'prenom' => $_SESSION['user_prenom'] ?? '',
    'email' => $_SESSION['user_email'] ?? '',
    'telephone' => $_SESSION['user_telephone'] ?? ''
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefill = [
        'nom' => $_POST['nom'] ?? '',
        'prenom' => $_POST['prenom'] ?? '',
        'email' => $_POST['email'] ?? '',
        'telephone' => $_POST['telephone'] ?? ''
    ];
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Demande de pièces sur mesure - FOUTA POIDS LOURDS';
$seo_description = 'Demande de pièces de véhicules sur mesure. FOUTA POIDS LOURDS : pièces poids lourds, camions, bus, tracteurs, remorques, cylindres. Devis personnalisé.';
$seo_canonical = $base . '/commande-personnalisee.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <style>
    .page-commande-perso {
        padding: 100px 20px 60px;
        max-width: 700px;
        margin: 0 auto;
    }

    .page-commande-perso h1 {
        font-family: var(--font-titres);
        color: var(--titres);
        margin-bottom: 10px;
        font-size: 28px;
    }

    .page-commande-perso .intro {
        color: var(--texte-fonce);
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .form-commande-perso {
        background: var(--glass-bg);
        border-radius: 16px;
        padding: 30px;
        border: 1px solid var(--glass-border);
        box-shadow: var(--glass-shadow);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 500;
        color: var(--titres);
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid var(--border-input);
        border-radius: 8px;
        font-size: 15px;
        font-family: inherit;
    }

    .form-group textarea {
        min-height: 120px;
        resize: vertical;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--couleur-dominante);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .upload-reference-box {
        border: 2px dashed var(--border-input);
        border-radius: 14px;
        padding: 18px;
        background: var(--blanc-casse);
    }

    .upload-reference-box:focus-within {
        border-color: var(--couleur-dominante);
    }

    .upload-reference-box input[type="file"] {
        padding: 12px;
        border-style: dashed;
        background: #fff;
        cursor: pointer;
    }

    .upload-help {
        margin-top: 10px;
        font-size: 13px;
        color: var(--texte-fonce);
        line-height: 1.5;
    }

    .upload-help strong {
        color: var(--couleur-dominante);
    }

    .preview-reference {
        display: none;
        margin-top: 16px;
        padding: 14px;
        border-radius: 12px;
        background: var(--bleu-pale);
        border: 1px solid var(--border-input);
    }

    .preview-reference.show {
        display: block;
    }

    .preview-reference img {
        display: block;
        width: 100%;
        max-height: 280px;
        object-fit: contain;
        border-radius: 10px;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.06);
    }

    .preview-reference-actions {
        margin-top: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .preview-reference-note {
        font-size: 13px;
        color: var(--texte-fonce);
    }

    .btn-remove-preview {
        border: none;
        background: rgba(0, 0, 0, 0.06);
        color: var(--titres);
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-remove-preview:hover {
        background: var(--bleu-pale);
        color: var(--couleur-dominante);
    }

    @media (max-width: 600px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }

    .error-message {
        background: var(--error-bg);
        border-left: 4px solid var(--error-border);
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
        color: var(--titres);
    }

    .btn-submit {
        width: 100%;
        padding: 14px;
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-submit:hover {
        background: var(--couleur-dominante-hover);
        transform: translateY(-2px);
    }

    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: var(--couleur-dominante);
        text-decoration: none;
        font-weight: 600;
    }

    .back-link:hover {
        color: var(--orange);
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <?php include 'nav_bar.php'; ?>

    <div class="page-commande-perso">
        <h1><i class="fas fa-palette"></i> Commande personnalisée</h1>
        <p class="intro">
            Vous avez une demande spécifique ? Décrivez-nous vos besoins et notre équipe vous contactera pour préparer
            un devis sur mesure.
        </p>

        <?php if (!empty($result['message']) && !$result['success']): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> <?php echo $result['message']; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="form-commande-perso" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                    <input type="text" id="nom" name="nom" required
                        value="<?php echo htmlspecialchars($prefill['nom']); ?>" placeholder="Votre nom">
                </div>
                <div class="form-group">
                    <label for="prenom"><i class="fas fa-user"></i> Prénom *</label>
                    <input type="text" id="prenom" name="prenom" required
                        value="<?php echo htmlspecialchars($prefill['prenom']); ?>" placeholder="Votre prénom">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo htmlspecialchars($prefill['email']); ?>" placeholder="votre@email.com">
                </div>
                <div class="form-group">
                    <label for="telephone"><i class="fas fa-phone"></i> Téléphone *</label>
                    <input type="tel" id="telephone" name="telephone" required
                        value="<?php echo htmlspecialchars($prefill['telephone']); ?>" placeholder="+237 6XX XXX XXX">
                </div>
            </div>
            <div class="form-group">
                <label for="description"><i class="fas fa-align-left"></i> Décrivez votre demande *</label>
                <textarea id="description" name="description" required
                    placeholder="Décrivez en détail ce que vous souhaitez : type de produit, quantités, spécificités, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="type_produit"><i class="fas fa-tag"></i> Type de produit (optionnel)</label>
                    <select id="type_produit" name="type_produit">
                        <option value="">— Choisir un type —</option>
                        <option value="Cake Topper"
                            <?php echo (($_POST['type_produit'] ?? '') === 'Cake Topper') ? ' selected' : ''; ?>>Cake
                            Topper</option>
                        <option value="Papier sucre A4"
                            <?php echo (($_POST['type_produit'] ?? '') === 'Papier sucre A4') ? ' selected' : ''; ?>>
                            Papier sucre A4</option>
                        <option value="Papier sucre A3"
                            <?php echo (($_POST['type_produit'] ?? '') === 'Papier sucre A3') ? ' selected' : ''; ?>>
                            Papier sucre A3</option>
                        <option value="Papier Azym A4"
                            <?php echo (($_POST['type_produit'] ?? '') === 'Papier Azym A4') ? ' selected' : ''; ?>>
                            Papier Azym A4</option>
                        <option value="Papier choco transfert A4"
                            <?php echo (($_POST['type_produit'] ?? '') === 'Papier choco transfert A4') ? ' selected' : ''; ?>>
                            Papier choco transfert A4</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantite"><i class="fas fa-cubes"></i> Quantité souhaitée (optionnel)</label>
                    <input type="text" id="quantite" name="quantite"
                        value="<?php echo htmlspecialchars($_POST['quantite'] ?? ''); ?>"
                        placeholder="Ex: 5 kg, 10 bouteilles...">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="date_souhaitee"><i class="fas fa-calendar"></i> Date souhaitée (optionnel)</label>
                    <input type="date" id="date_souhaitee" name="date_souhaitee"
                        value="<?php echo htmlspecialchars($_POST['date_souhaitee'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="zone_livraison_id"><i class="fas fa-map-marker-alt"></i> Zone de
                        livraison<?php echo !empty($zones_livraison) ? ' *' : ''; ?></label>
                    <select id="zone_livraison_id" name="zone_livraison_id"
                        <?php echo !empty($zones_livraison) ? ' required' : ''; ?>>
                        <option value="">— Choisir une zone —</option>
                        <?php foreach ($zones_livraison as $z): ?>
                        <option value="<?php echo (int) $z['id']; ?>"
                            data-prix="<?php echo (float) $z['prix_livraison']; ?>"
                            <?php echo (isset($_POST['zone_livraison_id']) && (int)$_POST['zone_livraison_id'] === (int)$z['id']) ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($z['ville'] . ' - ' . $z['quartier']); ?>
                            (<?php echo number_format($z['prix_livraison'], 0, ',', ' '); ?> FCFA)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="image_reference"><i class="fas fa-image"></i> Image de référence (optionnel)</label>
                <div class="upload-reference-box">
                    <input type="file" id="image_reference" name="image_reference"
                        accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                    <p class="upload-help">
                        Ajoutez une photo d'inspiration si vous en avez une.
                        <strong>Formats acceptés :</strong> JPG, PNG, WEBP, GIF.
                    </p>
                    <div class="preview-reference" id="preview-reference">
                        <img id="preview-reference-image" src="" alt="Prévisualisation de l'image sélectionnée">
                        <div class="preview-reference-actions">
                            <span class="preview-reference-note">Aperçu de l'image qui sera jointe à votre
                                demande.</span>
                            <button type="button" class="btn-remove-preview" id="btn-remove-preview">
                                <i class="fas fa-times"></i> Retirer l'image
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Envoyer ma demande
            </button>
        </form>

        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var fileInput = document.getElementById('image_reference');
        var previewBox = document.getElementById('preview-reference');
        var previewImage = document.getElementById('preview-reference-image');
        var removeButton = document.getElementById('btn-remove-preview');

        if (!fileInput || !previewBox || !previewImage || !removeButton) {
            return;
        }

        function hidePreview() {
            previewBox.classList.remove('show');
            previewImage.removeAttribute('src');
            fileInput.value = '';
        }

        fileInput.addEventListener('change', function() {
            if (!fileInput.files || !fileInput.files[0]) {
                previewBox.classList.remove('show');
                previewImage.removeAttribute('src');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(event) {
                previewImage.src = event.target.result;
                previewBox.classList.add('show');
            };
            reader.readAsDataURL(fileInput.files[0]);
        });

        removeButton.addEventListener('click', hidePreview);
    });
    </script>
</body>

</html>