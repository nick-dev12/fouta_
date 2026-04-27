<?php
/**
 * Page de modification de produit
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

// Récupérer l'ID du produit
$produit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($produit_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer le produit et ses variantes
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_variantes.php';
$produit = get_produit_by_id($produit_id);
$variantes = $produit ? get_variantes_by_produit($produit_id) : [];

if (!$produit) {
    header('Location: index.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_update_produit($produit_id);

// Si la modification est réussie, rediriger vers la liste
if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    header('Location: index.php');
    exit;
}

// Récupérer les catégories (stock géré via produits.stock)
require_once __DIR__ . '/../../models/model_categories.php';
$categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="../../css/admin-produit-modifier.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-produit-modifier">
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container pm-page">
        <header class="pm-hero" role="banner">
            <div class="pm-hero__text">
                <p class="pm-eyebrow">Fiche produit n°&nbsp;<?php echo (int) $produit_id; ?></p>
                <h1 class="pm-title">
                    <i class="fas fa-pen-to-square" aria-hidden="true"></i> Modifier le produit
                </h1>
                <p class="pm-subtitle"><?php echo htmlspecialchars($produit['nom']); ?></p>
            </div>
            <div class="pm-hero__actions">
                <span class="pm-badge-id">#<?php echo (int) $produit_id; ?></span>
                <a href="index.php" class="pm-btn-back">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
                </a>
            </div>
        </header>

    <div class="form-container">
        <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
        <div class="error-message" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php echo $result['message']; ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="pm-form" id="form-produit-modifier">
        <div class="pm-sections">
            <section class="pm-card" aria-labelledby="pm-sec-info">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-align-left"></i></span>
                    <div>
                        <h2 id="pm-sec-info" class="pm-card__title">Informations générales</h2>
                        <p class="pm-card__hint">Nom et description tels qu’affichés sur la boutique</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <div class="form-group">
                <label for="nom">Nom du produit *</label>
                <input type="text" id="nom" name="nom" required
                    value="<?php echo htmlspecialchars($produit['nom']); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description"
                    required><?php echo htmlspecialchars($produit['description']); ?></textarea>
            </div>
                </div>
            </section>

            <section class="pm-card" aria-labelledby="pm-sec-prix">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                    <div>
                        <h2 id="pm-sec-prix" class="pm-card__title">Prix, stock &amp; catégorie</h2>
                        <p class="pm-card__hint">Tarif, inventaire et classement</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <div class="form-row">
                <div class="form-group">
                    <label for="prix">Prix (FCFA) *</label>
                    <input type="number" id="prix" name="prix" step="0.01" min="0" required
                        value="<?php echo $produit['prix']; ?>">
                </div>

                <div class="form-group">
                    <label for="prix_promotion">Prix promotionnel (FCFA)</label>
                    <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0"
                        value="<?php echo $produit['prix_promotion'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="stock">Stock *</label>
                    <input type="number" id="stock" name="stock" min="0" required
                        value="<?php echo $produit['stock']; ?>">
                </div>

                <div class="form-group">
                    <label for="categorie_id">Catégorie *</label>
                    <select id="categorie_id" name="categorie_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php if ($categories && count($categories) > 0): ?>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"
                            <?php echo ($produit['categorie_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <option value="" disabled>Aucune catégorie disponible</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$categories || count($categories) == 0): ?>
                    <small class="form-hint form-hint--warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Aucune catégorie disponible. <a href="../categories/ajouter.php">Créer une catégorie</a>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
                </div>
            </section>

            <section class="pm-card" aria-labelledby="pm-sec-ref">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-warehouse"></i></span>
                    <div>
                        <h2 id="pm-sec-ref" class="pm-card__title">Référence &amp; emplacement</h2>
                        <p class="pm-card__hint">Code interne FPL et repères entrepôt</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <?php
            $identifiant_ro = $produit['identifiant_interne'] ?? '';
            $etage_val = isset($_POST['etage']) ? $_POST['etage'] : ($produit['etage'] ?? '');
            $rayon_val = isset($_POST['numero_rayon']) ? $_POST['numero_rayon'] : ($produit['numero_rayon'] ?? '');
            ?>
            <?php if ($identifiant_ro !== ''): ?>
            <div class="form-group">
                <label>Identifiant interne (FPL)</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($identifiant_ro); ?>"
                    class="pm-input-readonly">
                <small class="form-hint">Référence interne unique, non modifiable.</small>
            </div>
            <?php else: ?>
            <p class="pm-hint">
                <i class="fas fa-info-circle"></i> L’identifiant <strong>FPLxxxxxx</strong> sera généré après migration de la base de données si absent.
            </p>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="etage"><i class="fas fa-warehouse"></i> Étage (entrepôt)</label>
                    <input type="text" id="etage" name="etage" placeholder="Ex. RDC, 1, 2"
                        value="<?php echo htmlspecialchars((string) $etage_val); ?>">
                </div>
                <div class="form-group">
                    <label for="numero_rayon">N° de rayon</label>
                    <input type="text" id="numero_rayon" name="numero_rayon" placeholder="Ex. A12"
                        value="<?php echo htmlspecialchars((string) $rayon_val); ?>">
                </div>
            </div>
                </div>
            </section>

            <section class="pm-card" aria-labelledby="pm-sec-var">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <div>
                        <h2 id="pm-sec-var" class="pm-card__title">Variantes (optionnel)</h2>
                        <p class="pm-card__hint">Noms, prix et visuels distincts (les options poids / couleurs s’y appliquent aussi)</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <div class="form-group">
                <p class="form-hint" style="margin-bottom: 12px;">Ajoutez des variantes avec un nom, un prix
                    et une image différents.</p>
                <div id="variantes-container" class="variantes-container">
                    <?php if (!empty($variantes)): ?>
                    <?php foreach ($variantes as $idx => $var): ?>
                    <div class="variante-item" data-index="<?php echo $idx; ?>">
                        <div class="variante-row">
                            <input type="hidden" name="variantes_id[]" value="<?php echo (int)$var['id']; ?>">
                            <input type="text" name="variantes_nom[]" placeholder="Nom de la variante"
                                class="variante-nom" value="<?php echo htmlspecialchars($var['nom']); ?>">
                            <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01"
                                class="variante-prix" value="<?php echo htmlspecialchars($var['prix']); ?>">
                            <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0"
                                step="0.01" class="variante-prix-promo"
                                value="<?php echo $var['prix_promotion'] ? htmlspecialchars($var['prix_promotion']) : ''; ?>">
                            <div class="variante-image-wrap">
                                <div class="variante-image-area">
                                    <input type="file" name="variantes_image[]" accept="image/*"
                                        class="variante-image-input">
                                    <span class="variante-image-label"
                                        <?php echo $var['image'] ? 'style="display: none;"' : ''; ?>><i
                                            class="fas fa-image"></i>
                                        <?php echo $var['image'] ? 'Changer' : 'Image'; ?></span>
                                    <img class="variante-preview-img"
                                        src="<?php echo $var['image'] ? '../../upload/' . htmlspecialchars($var['image']) : ''; ?>"
                                        alt="" <?php echo $var['image'] ? '' : 'style="display: none;"'; ?>>
                                </div>
                            </div>
                            <button type="button" class="btn-remove-variante" title="Supprimer">&times;</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="variante-item" data-index="0">
                        <div class="variante-row">
                            <input type="hidden" name="variantes_id[]" value="">
                            <input type="text" name="variantes_nom[]" placeholder="Nom de la variante"
                                class="variante-nom">
                            <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01"
                                class="variante-prix">
                            <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0"
                                step="0.01" class="variante-prix-promo">
                            <div class="variante-image-wrap">
                                <div class="variante-image-area">
                                    <input type="file" name="variantes_image[]" accept="image/*"
                                        class="variante-image-input">
                                    <span class="variante-image-label"><i class="fas fa-image"></i> Image</span>
                                    <img class="variante-preview-img" src="" alt="" style="display: none;">
                                </div>
                            </div>
                            <button type="button" class="btn-remove-variante" title="Supprimer">&times;</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="btn-add-variante" class="btn-add-variante"><i class="fas fa-plus"></i> Ajouter
                    une variante</button>
            </div>
                </div>
            </section>

            <section class="pm-card" aria-labelledby="pm-sec-opts">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-sliders"></i></span>
                    <div>
                        <h2 id="pm-sec-opts" class="pm-card__title">Options d’achat</h2>
                        <p class="pm-card__hint">Poids et couleurs proposés au client</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <div class="form-row">
                <div class="form-group">
                    <label>Poids disponibles</label>
                    <div class="options-add-block options-with-surcharge">
                        <div class="options-add-row">
                            <input type="text" id="poids-input" placeholder="Ex: 500g, 1kg" class="options-input">
                            <input type="number" id="poids-surcharge" placeholder="+ FCFA" min="0" step="1"
                                class="options-surcharge" title="Montant à ajouter au prix">
                            <button type="button" class="btn-add-option" id="btn-add-poids">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                        </div>
                        <div id="poids-list" class="options-tags-list options-tags-with-surcharge"></div>
                        <?php
                        $poids_val = $produit['poids'] ?? '';
                        if ($poids_val === '[]' || $poids_val === '') {
                            $poids_val = '';
                        } elseif ($poids_val) {
                            $poids_dec = json_decode($poids_val, true);
                            if (is_array($poids_dec)) {
                                $poids_dec = array_filter($poids_dec, function($x) {
                                    $v = is_array($x) ? ($x['v'] ?? '') : $x;
                                    return $v !== '' && $v !== '[]';
                                });
                                $poids_val = !empty($poids_dec) ? json_encode(array_values($poids_dec)) : '';
                            }
                        }
                        ?>
                        <input type="hidden" name="poids" id="poids-hidden"
                            value="<?php echo htmlspecialchars($poids_val); ?>">
                    </div>
                    <small class="form-hint">Poids + montant
                        optionnel (ex: 1kg + 300). Laissez vide pour 0.</small>
                </div>

                <!-- <div class="form-group">
                    <label for="unite">Unité par défaut</label>
                    <select id="unite" name="unite">
                        <option value="unité" <?php echo (($produit['unite'] ?? '') == 'unité') ? 'selected' : ''; ?>>Unité</option>
                        <option value="kg" <?php echo (($produit['unite'] ?? '') == 'kg') ? 'selected' : ''; ?>>Kilogramme</option>
                        <option value="g" <?php echo (($produit['unite'] ?? '') == 'g') ? 'selected' : ''; ?>>Gramme</option>
                        <option value="L" <?php echo (($produit['unite'] ?? '') == 'L') ? 'selected' : ''; ?>>Litre</option>
                    </select>
                </div> -->
            </div>

            <?php
            $couleurs_init = [];
            $couleurs_raw = trim($produit['couleurs'] ?? '');
            if ($couleurs_raw) {
                $dec = json_decode($couleurs_raw, true);
                if (is_array($dec)) {
                    $couleurs_init = array_filter($dec, function($c) {
                        return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
                    });
                }
            }
            ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Couleurs disponibles (optionnel)</label>
                    <div class="couleurs-picker-block">
                        <div class="couleurs-add-row">
                            <input type="color" id="couleur-input" value="#3564A6" title="Choisir une couleur">
                            <button type="button" class="btn-add-couleur" id="btn-add-couleur">
                                <i class="fas fa-plus"></i> Ajouter cette couleur
                            </button>
                        </div>
                        <div id="couleurs-list" class="couleurs-swatches"></div>
                        <?php
                        $couleurs_hidden_val = ($couleurs_raw && $couleurs_raw !== '[]') ? (empty($couleurs_init) ? $couleurs_raw : json_encode($couleurs_init)) : '';
                        ?>
                        <input type="hidden" name="couleurs" id="couleurs-hidden"
                            value="<?php echo htmlspecialchars($couleurs_hidden_val); ?>">
                    </div>
                    <?php if ($couleurs_raw && empty($couleurs_init)): ?>
                    <small class="form-hint">Ancien format (texte)
                        : <?php echo htmlspecialchars($couleurs_raw); ?> — remplacez par des couleurs via le sélecteur
                        ci-dessus.</small>
                    <?php else: ?>
                    <small class="form-hint">Cliquez sur la
                        pastille pour choisir une couleur, puis sur « Ajouter ». Vous pouvez ajouter plusieurs
                        couleurs.</small>
                    <?php endif; ?>
                </div>
                <!-- <div class="form-group">
                    <label>Tailles disponibles</label>
                    <div class="options-add-block options-with-surcharge">
                        <div class="options-add-row">
                            <input type="text" id="taille-input" placeholder="Ex: S, M, L" class="options-input">
                            <input type="number" id="taille-surcharge" placeholder="+ FCFA" min="0" step="1"
                                class="options-surcharge" title="Montant à ajouter au prix">
                            <button type="button" class="btn-add-option" id="btn-add-taille">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                        </div>
                        <div id="taille-list" class="options-tags-list options-tags-with-surcharge"></div>
                        <?php
                        $taille_val = $produit['taille'] ?? '';
                        if ($taille_val === '[]' || $taille_val === '') {
                            $taille_val = '';
                        } elseif ($taille_val) {
                            $taille_dec = json_decode($taille_val, true);
                            if (is_array($taille_dec)) {
                                $taille_dec = array_filter($taille_dec, function($x) {
                                    $v = is_array($x) ? ($x['v'] ?? '') : $x;
                                    return $v !== '' && $v !== '[]';
                                });
                                $taille_val = !empty($taille_dec) ? json_encode(array_values($taille_dec)) : '';
                            }
                        }
                        ?>
                        <input type="hidden" name="taille" id="taille-hidden"
                            value="<?php echo htmlspecialchars($taille_val); ?>">
                    </div>
                    <small class="form-hint">Taille + montant
                        optionnel (ex: L + 200). Laissez vide pour 0.</small>
                </div> -->
            </div>
                </div>
            </section>

            <section class="pm-card" aria-labelledby="pm-sec-media">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-images"></i></span>
                    <div>
                        <h2 id="pm-sec-media" class="pm-card__title">Galerie photos</h2>
                        <p class="pm-card__hint">Image principale en premier — cliquez sur × pour retirer une photo</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <div class="form-group">
                <label><i class="fas fa-image"></i> Images du produit</label>
                <p class="form-hint" style="margin-bottom: 10px;">Images actuelles — cliquez sur &times;
                    pour supprimer une image. La première est l’image principale.</p>
                <?php 
                $images_produit = [];
                if (!empty($produit['images'])) {
                    $dec = json_decode($produit['images'], true);
                    if (is_array($dec)) $images_produit = $dec;
                }
                if (empty($images_produit) && !empty($produit['image_principale'])) {
                    $images_produit = [$produit['image_principale']];
                }
                ?>
                <div id="gallery-existing" class="gallery-preview-edit">
                    <?php foreach ($images_produit as $idx => $img_path): ?>
                    <div class="gallery-thumb-edit" data-path="<?php echo htmlspecialchars($img_path); ?>">
                        <input type="hidden" name="images_to_keep[]" value="<?php echo htmlspecialchars($img_path); ?>">
                        <span class="img-badge"><?php echo $idx === 0 ? 'Principale' : ($idx + 1); ?></span>
                        <button type="button" class="img-remove-btn" title="Supprimer cette image">&times;</button>
                        <img src="../../upload/<?php echo htmlspecialchars($img_path); ?>"
                            alt="Image <?php echo $idx + 1; ?>" onerror="this.src='/image/produit1.jpg'">
                    </div>
                    <?php endforeach; ?>
                </div>
                <label for="images_supplementaires" class="pm-upload-label">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter des images à la galerie
                </label>
                <input type="file" id="images_supplementaires" name="images_supplementaires[]" accept="image/*" multiple
                    class="pm-file-hidden" onchange="previewMultipleImages(this, 'preview-supplementaires')">
                <div id="preview-supplementaires" class="image-preview-grid"></div>
                <small class="form-hint">Formats : JPG, PNG, GIF, WEBP. Au moins une image doit rester.</small>
            </div>
                </div>
            </section>

            <section class="pm-card" aria-labelledby="pm-sec-statut">
                <div class="pm-card__head">
                    <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-toggle-on"></i></span>
                    <div>
                        <h2 id="pm-sec-statut" class="pm-card__title">Visibilité</h2>
                        <p class="pm-card__hint">Statut affiché dans le catalogue</p>
                    </div>
                </div>
                <div class="pm-card__body">
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="actif" <?php echo ($produit['statut'] == 'actif') ? 'selected' : ''; ?>>Actif
                    </option>
                    <option value="inactif" <?php echo ($produit['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif
                    </option>
                    <option value="rupture_stock"
                        <?php echo ($produit['statut'] == 'rupture_stock') ? 'selected' : ''; ?>>Rupture de stock
                    </option>
                </select>
            </div>
                </div>
            </section>

        </div><!-- .pm-sections -->

            <div class="pm-form-spacer" aria-hidden="true"></div>
        </form>
    </div>

    <div class="pm-sticky-actions" role="contentinfo" aria-label="Enregistrement">
        <div class="pm-sticky-inner">
            <button type="submit" form="form-produit-modifier" class="pm-btn-primary btn-primary">
                <i class="fas fa-save" aria-hidden="true"></i> Enregistrer les modifications
            </button>
        </div>
    </div>
    </div><!-- .contents-container.pm-page -->

    <script>
    (function() {
        var galleryExisting = document.getElementById('gallery-existing');
        var inputSupp = document.getElementById('images_supplementaires');
        if (galleryExisting) {
            galleryExisting.addEventListener('click', function(e) {
                var btn = e.target.closest('.img-remove-btn');
                if (btn) {
                    e.preventDefault();
                    btn.closest('.gallery-thumb-edit').remove();
                }
            });
        }

        function previewMultipleImages(input, containerId) {
            var c = document.getElementById(containerId);
            c.innerHTML = '';
            if (input.files)
                for (var i = 0; i < input.files.length; i++) {
                    (function(f) {
                        var r = new FileReader();
                        r.onload = function(e) {
                            var d = document.createElement('div');
                            d.className = 'preview-item';
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            d.appendChild(img);
                            c.appendChild(d);
                        };
                        r.readAsDataURL(f);
                    })(input.files[i]);
                }
        }
        if (inputSupp) inputSupp.addEventListener('change', function() {
            previewMultipleImages(this, 'preview-supplementaires');
        });
        document.getElementById('form-produit-modifier').addEventListener('submit', function(e) {
            var kept = document.querySelectorAll('input[name="images_to_keep[]"]').length;
            var newFiles = inputSupp && inputSupp.files ? inputSupp.files.length : 0;
            if (kept === 0 && newFiles === 0) {
                e.preventDefault();
                alert(
                    'Au moins une image est obligatoire. Veuillez conserver ou ajouter au moins une image.'
                );
                return false;
            }
        });
    })();
    (function() {
        var couleurInput = document.getElementById('couleur-input');
        var btnAdd = document.getElementById('btn-add-couleur');
        var list = document.getElementById('couleurs-list');
        var hidden = document.getElementById('couleurs-hidden');
        var couleurs = [];
        try {
            if (hidden && hidden.value && hidden.value !== '[]') {
                var parsed = JSON.parse(hidden.value);
                if (Array.isArray(parsed)) {
                    couleurs = parsed.filter(function(c) {
                        return typeof c === 'string' && /^#[0-9A-Fa-f]{6}$/.test(c);
                    });
                }
            }
        } catch (e) {}

        function updateHidden() {
            if (hidden) hidden.value = JSON.stringify(couleurs);
        }

        function render() {
            if (!list) return;
            list.innerHTML = '';
            couleurs.forEach(function(hex, i) {
                var div = document.createElement('div');
                div.className = 'couleur-swatch';
                div.innerHTML = '<span class="swatch-preview" style="background:' + hex +
                    '"></span><span class="swatch-hex">' + hex +
                    '</span><button type="button" class="swatch-remove" data-i="' + i +
                    '" title="Retirer">&times;</button>';
                list.appendChild(div);
            });
            updateHidden();
        }
        if (btnAdd && couleurInput) {
            btnAdd.addEventListener('click', function() {
                var hex = couleurInput.value;
                if (hex && couleurs.indexOf(hex) === -1) {
                    couleurs.push(hex);
                    render();
                }
            });
        }
        if (list) {
            list.addEventListener('click', function(e) {
                var btn = e.target.closest('.swatch-remove');
                if (btn) {
                    var i = parseInt(btn.dataset.i, 10);
                    couleurs.splice(i, 1);
                    render();
                }
            });
        }
        render();
    })();
    (function() {
        function initOptionsWithSurcharge(idInput, idSurcharge, idList, idHidden, btnId) {
            var input = document.getElementById(idInput);
            var surchargeInput = document.getElementById(idSurcharge);
            var list = document.getElementById(idList);
            var hidden = document.getElementById(idHidden);
            var btn = document.getElementById(btnId);
            var values = [];
            try {
                if (hidden && hidden.value && hidden.value !== '[]') {
                    var parsed = JSON.parse(hidden.value);
                    if (Array.isArray(parsed)) values = parsed;
                    else values = (hidden.value.split(',').map(function(s) {
                        return {
                            v: s.trim(),
                            s: 0
                        };
                    })).filter(function(x) {
                        return x.v && x.v !== '[]';
                    });
                }
            } catch (e) {
                if (hidden && hidden.value && hidden.value !== '[]') {
                    values = hidden.value.split(',').map(function(s) {
                        return {
                            v: s.trim(),
                            s: 0
                        };
                    }).filter(function(x) {
                        return x.v && x.v !== '[]';
                    });
                }
            }
            values = values.filter(function(item) {
                var v = typeof item === 'object' ? item.v : item;
                return v && v !== '[]' && String(v).trim() !== '';
            });

            function updateHidden() {
                if (hidden) hidden.value = JSON.stringify(values);
            }

            function render() {
                if (!list) return;
                list.innerHTML = '';
                values.forEach(function(item, i) {
                    var v = typeof item === 'object' ? item.v : item;
                    var s = typeof item === 'object' ? (item.s || 0) : 0;
                    var surc = s > 0 ? ' <span class="tag-surcharge">+' + s + ' FCFA</span>' : '';
                    var div = document.createElement('div');
                    div.className = 'option-tag';
                    div.innerHTML = '<span>' + (v.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + surc +
                        '</span><button type="button" class="tag-remove" data-i="' + i +
                        '" title="Retirer">&times;</button>';
                    list.appendChild(div);
                });
                updateHidden();
            }
            if (btn && input) {
                btn.addEventListener('click', function() {
                    var val = (input.value || '').trim();
                    var surc = surchargeInput ? (parseInt(surchargeInput.value, 10) || 0) : 0;
                    if (val) {
                        var exists = values.some(function(x) {
                            return (typeof x === 'object' ? x.v : x) === val;
                        });
                        if (!exists) {
                            values.push({
                                v: val,
                                s: surc
                            });
                            input.value = '';
                            if (surchargeInput) surchargeInput.value = '';
                            render();
                        }
                    }
                });
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        btn.click();
                    }
                });
            }
            if (list) {
                list.addEventListener('click', function(e) {
                    var b = e.target.closest('.tag-remove');
                    if (b) {
                        values.splice(parseInt(b.dataset.i, 10), 1);
                        render();
                    }
                });
            }
            render();
        }
        initOptionsWithSurcharge('poids-input', 'poids-surcharge', 'poids-list', 'poids-hidden', 'btn-add-poids');
        initOptionsWithSurcharge('taille-input', 'taille-surcharge', 'taille-list', 'taille-hidden',
            'btn-add-taille');
    })();
    (function() {
        var container = document.getElementById('variantes-container');
        var btnAdd = document.getElementById('btn-add-variante');
        var idx = container ? container.children.length : 1;

        function previewVarianteImage(input) {
            var wrap = input.closest('.variante-image-wrap');
            if (!wrap) return;
            var img = wrap.querySelector('.variante-preview-img');
            var label = wrap.querySelector('.variante-image-label');
            if (!img || !label) return;
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    label.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                img.src = '';
                img.style.display = 'none';
                label.style.display = '';
            }
        }
        if (container) {
            container.addEventListener('change', function(e) {
                if (e.target.classList.contains('variante-image-input')) {
                    previewVarianteImage(e.target);
                }
            });
        }
        if (btnAdd && container) {
            btnAdd.addEventListener('click', function() {
                var div = document.createElement('div');
                div.className = 'variante-item';
                div.dataset.index = idx++;
                div.innerHTML = '<div class="variante-row">' +
                    '<input type="hidden" name="variantes_id[]" value="">' +
                    '<input type="text" name="variantes_nom[]" placeholder="Nom de la variante" class="variante-nom">' +
                    '<input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix">' +
                    '<input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo">' +
                    '<div class="variante-image-wrap">' +
                    '<div class="variante-image-area">' +
                    '<input type="file" name="variantes_image[]" accept="image/*" class="variante-image-input">' +
                    '<span class="variante-image-label"><i class="fas fa-image"></i> Image</span>' +
                    '<img class="variante-preview-img" src="" alt="" style="display: none;">' +
                    '</div></div>' +
                    '<button type="button" class="btn-remove-variante" title="Supprimer">&times;</button></div>';
                container.appendChild(div);
                div.querySelector('.btn-remove-variante').addEventListener('click', function() {
                    div.remove();
                });
            });
            container.addEventListener('click', function(e) {
                var b = e.target.closest('.btn-remove-variante');
                if (b && container.children.length > 1) b.closest('.variante-item').remove();
            });
        }
    })();
    </script>
    <?php include '../includes/footer.php'; ?>
