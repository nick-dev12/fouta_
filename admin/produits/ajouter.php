<?php
/**
 * Page d'ajout de produit
 * Formulaire direct - stock géré via la colonne produits.stock (plus de lien stock_articles)
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_add_produit();

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    $categorie_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
    if ($categorie_id > 0) {
        header('Location: ../categories/produits.php?id=' . $categorie_id);
    } else {
        header('Location: ../stock/index.php');
    }
    exit;
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_fournisseurs.php';

$categories = get_all_categories();
$categorie_id_prefill = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;

$has_ff_col = produits_has_column('fournisseur_id');
$fournisseurs_catalogue = $has_ff_col ? get_all_fournisseurs_ordered_by_nom() : [];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
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
                <p class="pm-eyebrow">Nouveau catalogue</p>
                <h1 class="pm-title">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter un produit
                </h1>
                <p class="pm-subtitle">Même présentation que la fiche modification : informations, tarifs, emplacement puis galerie. L’identifiant <strong>FPL</strong> est créé automatiquement.</p>
            </div>
            <div class="pm-hero__actions">
                <?php if ($categorie_id_prefill > 0): ?>
                <a href="../categories/produits.php?id=<?php echo (int) $categorie_id_prefill; ?>" class="pm-btn-back">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux produits
                </a>
                <?php else: ?>
                <a href="../stock/index.php" class="pm-btn-back">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour au stock
                </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="form-container">
            <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
            <div class="error-message" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo $result['message']; ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" class="pm-form" id="form-produit-ajouter">
                <div class="pm-sections">
                    <section class="pm-card" aria-labelledby="pm-sec-info-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-align-left"></i></span>
                            <div>
                                <h2 id="pm-sec-info-add" class="pm-card__title">Informations générales</h2>
                                <p class="pm-card__hint">Nom et texte présentés sur la boutique</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <div class="form-group">
                                <label for="nom">Nom du produit *</label>
                                <input type="text" id="nom" name="nom" required placeholder="Ex : Filtre à air compatible…"
                                    value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" required placeholder="Décrivez le produit…"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="fournisseur_id">Fournisseur</label>
                                <?php if ($has_ff_col): ?>
                                <select id="fournisseur_id" name="fournisseur_id">
                                    <option value="">— Aucun —</option>
                                    <?php foreach ($fournisseurs_catalogue as $ff): ?>
                                    <option value="<?php echo (int) $ff['id']; ?>" <?php echo (isset($_POST['fournisseur_id']) && (string) $_POST['fournisseur_id'] === (string) $ff['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ff['nom']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">Liste gérée dans <a href="../parametres/logos.php?tab=fournisseurs">Paramètres → Logos &amp; fournisseurs</a>.</small>
                                <?php else: ?>
                                <p class="form-hint form-hint--warning">
                                    Migration requise : exécutez <code>migrations/run_create_fournisseurs.php</code> pour activer la liste déroulante.
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="pm-card" aria-labelledby="pm-sec-prix-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                            <div>
                                <h2 id="pm-sec-prix-add" class="pm-card__title">Prix, stock &amp; catégorie</h2>
                                <p class="pm-card__hint">Tarif, inventaire et classement</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="prix">Prix (FCFA) *</label>
                                    <input type="number" id="prix" name="prix" step="0.01" min="0" required
                                        value="<?php echo isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="prix_promotion">Prix promotionnel (FCFA)</label>
                                    <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0"
                                        value="<?php echo isset($_POST['prix_promotion']) ? htmlspecialchars($_POST['prix_promotion']) : ''; ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="stock">Stock *</label>
                                    <input type="number" id="stock" name="stock" min="0" required
                                        value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="categorie_id">Catégorie *</label>
                                    <select id="categorie_id" name="categorie_id" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php if ($categories && count($categories) > 0): ?>
                                            <?php foreach ($categories as $c): ?>
                                            <option value="<?php echo (int) $c['id']; ?>" <?php echo ((isset($_POST['categorie_id']) && (string) $_POST['categorie_id'] === (string) $c['id']) || ($categorie_id_prefill > 0 && (int) $c['id'] === $categorie_id_prefill)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['nom']); ?>
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

                    <section class="pm-card" aria-labelledby="pm-sec-ref-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-warehouse"></i></span>
                            <div>
                                <h2 id="pm-sec-ref-add" class="pm-card__title">Référence &amp; emplacement</h2>
                                <p class="pm-card__hint">Repères entrepôt (identifiant FPL attribué à l’enregistrement)</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <p class="pm-hint">
                                <i class="fas fa-barcode" aria-hidden="true"></i> Un code interne <strong>FPLxxxxxx</strong> sera généré automatiquement pour ce produit.
                            </p>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="etage"><i class="fas fa-warehouse"></i> Étage (entrepôt)</label>
                                    <input type="text" id="etage" name="etage" placeholder="Ex. RDC, 1, 2"
                                        value="<?php echo isset($_POST['etage']) ? htmlspecialchars($_POST['etage']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="numero_rayon">N° de rayon</label>
                                    <input type="text" id="numero_rayon" name="numero_rayon" placeholder="Ex. A12"
                                        value="<?php echo isset($_POST['numero_rayon']) ? htmlspecialchars($_POST['numero_rayon']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="pm-card admin-ajouter-produit-masquer" aria-labelledby="pm-sec-var-add" aria-hidden="true">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                            <div>
                                <h2 id="pm-sec-var-add" class="pm-card__title">Variantes (optionnel)</h2>
                                <p class="pm-card__hint">Noms, prix et visuels distincts</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <div class="form-group">
                                <p class="form-hint" style="margin-bottom: 12px;">Variantes avec nom, prix et image différents du produit de base.</p>
                                <div id="variantes-container" class="variantes-container">
                                    <div class="variante-item" data-index="0">
                                        <div class="variante-row">
                                            <input type="text" name="variantes_nom[]" placeholder="Nom (ex : Format familial)" class="variante-nom">
                                            <input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix">
                                            <input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo">
                                            <div class="variante-image-wrap">
                                                <div class="variante-image-area">
                                                    <input type="file" name="variantes_image[]" accept="image/*" class="variante-image-input">
                                                    <span class="variante-image-label"><i class="fas fa-image"></i> Image</span>
                                                    <img class="variante-preview-img" src="" alt="" style="display: none;">
                                                </div>
                                            </div>
                                            <button type="button" class="btn-remove-variante" title="Supprimer">&times;</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="btn-add-variante" class="btn-add-variante"><i class="fas fa-plus"></i> Ajouter une variante</button>
                            </div>
                        </div>
                    </section>

                    <section class="pm-card admin-ajouter-produit-masquer" aria-labelledby="pm-sec-opts-add" aria-hidden="true">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-sliders"></i></span>
                            <div>
                                <h2 id="pm-sec-opts-add" class="pm-card__title">Options d’achat</h2>
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
                                            <button type="button" class="btn-add-option" id="btn-add-poids"><i class="fas fa-plus"></i> Ajouter</button>
                                        </div>
                                        <div id="poids-list" class="options-tags-list options-tags-with-surcharge"></div>
                                        <input type="hidden" name="poids" id="poids-hidden"
                                            value="<?php echo isset($_POST['poids']) ? htmlspecialchars($_POST['poids']) : ''; ?>">
                                    </div>
                                    <small class="form-hint">Poids + surcoût optionnel (+ FCFA).</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Couleurs disponibles (optionnel)</label>
                                    <div class="couleurs-picker-block">
                                        <div class="couleurs-add-row">
                                            <input type="color" id="couleur-input" value="#3564A6" title="Choisir une couleur">
                                            <button type="button" class="btn-add-couleur" id="btn-add-couleur"><i class="fas fa-plus"></i> Ajouter cette couleur</button>
                                        </div>
                                        <div id="couleurs-list" class="couleurs-swatches"></div>
                                        <input type="hidden" name="couleurs" id="couleurs-hidden"
                                            value="<?php echo isset($_POST['couleurs']) ? htmlspecialchars($_POST['couleurs']) : ''; ?>">
                                    </div>
                                    <small class="form-hint">Une ou plusieurs pastilles hexadécimales.</small>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="pm-card" aria-labelledby="pm-sec-media-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-images"></i></span>
                            <div>
                                <h2 id="pm-sec-media-add" class="pm-card__title">Galerie photos</h2>
                                <p class="pm-card__hint">La première image devient la photo principale</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <div class="form-group">
                                <label><i class="fas fa-image"></i> Images du produit *</label>
                                <p class="form-hint" style="margin-bottom: 10px;">Ajoutez une ou plusieurs images (cumul possible). Cliquez sur × sur un aperçu pour le retirer avant envoi.</p>
                                <label for="images_produit" class="pm-upload-label">
                                    <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                    Parcourir ou déposer vos fichiers
                                </label>
                                <input type="file" id="images_produit" name="images_produit[]" accept="image/*" multiple
                                    class="pm-file-hidden">
                                <div id="preview-images" class="image-preview-accumulator"></div>
                                <small class="form-hint">Formats : JPG, PNG, GIF, WEBP. Au moins une image requise.</small>
                            </div>
                        </div>
                    </section>

                    <input type="hidden" name="statut" value="actif">
                </div><!-- .pm-sections -->

                <div class="pm-form-spacer" aria-hidden="true"></div>
            </form>
        </div><!-- .form-container -->

        <div class="pm-sticky-actions" role="contentinfo" aria-label="Enregistrement">
            <div class="pm-sticky-inner pm-sticky-inner--split">
                <?php if ($categorie_id_prefill > 0): ?>
                <a href="../categories/produits.php?id=<?php echo (int) $categorie_id_prefill; ?>" class="pm-btn-back">Annuler</a>
                <?php else: ?>
                <a href="../stock/index.php" class="pm-btn-back">Annuler</a>
                <?php endif; ?>
                <button type="submit" form="form-produit-ajouter" class="pm-btn-primary btn-primary">
                    <i class="fas fa-plus" aria-hidden="true"></i> Ajouter le produit
                </button>
            </div>
        </div>
    </div><!-- .contents-container.pm-page -->

    <script>
        (function () {
            var input = document.getElementById('images_produit');
            var container = document.getElementById('preview-images');
            var accumulatedFiles = [];

            function updateInputFiles() {
                if (!input) return;
                var dt = new DataTransfer();
                for (var i = 0; i < accumulatedFiles.length; i++) {
                    dt.items.add(accumulatedFiles[i]);
                }
                input.files = dt.files;
            }

            function addPreviews(newFiles) {
                if (!container) return;
                for (var i = 0; i < newFiles.length; i++) {
                    (function (file) {
                        if (!file.type.match('image.*')) return;
                        var pos = accumulatedFiles.length;
                        accumulatedFiles.push(file);
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            var div = document.createElement('div');
                            div.className = 'preview-item';
                            div.dataset.index = pos;
                            var badge = document.createElement('span');
                            badge.className = 'preview-badge';
                            badge.textContent = pos === 0 ? 'Principale' : (pos + 1);
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Aperçu ' + (pos + 1);
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'preview-remove';
                            btn.innerHTML = '&times;';
                            btn.title = 'Retirer';
                            btn.onclick = function (ev) {
                                ev.preventDefault();
                                ev.stopPropagation();
                                var idx = parseInt(div.dataset.index, 10);
                                accumulatedFiles.splice(idx, 1);
                                div.remove();
                                for (var j = 0; j < container.children.length; j++) {
                                    container.children[j].dataset.index = j;
                                    container.children[j].querySelector('.preview-badge').textContent =
                                        j === 0 ? 'Principale' : (j + 1);
                                }
                                updateInputFiles();
                            };
                            div.appendChild(badge);
                            div.appendChild(img);
                            div.appendChild(btn);
                            container.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    })(newFiles[i]);
                }
                updateInputFiles();
            }

            if (input && container) {
                input.addEventListener('change', function () {
                    if (this.files && this.files.length > 0) {
                        var nf = [];
                        for (var k = 0; k < this.files.length; k++) {
                            nf.push(this.files[k]);
                        }
                        addPreviews(nf);
                    }
                });
            }

            var ajForm = document.getElementById('form-produit-ajouter');
            if (ajForm) {
                ajForm.addEventListener('submit', function (e) {
                    if (accumulatedFiles.length === 0) {
                        e.preventDefault();
                        alert('Veuillez ajouter au moins une image.');
                        return false;
                    }
                });
            }
        })();
        (function () {
            var couleurInput = document.getElementById('couleur-input');
            var btnAdd = document.getElementById('btn-add-couleur');
            var list = document.getElementById('couleurs-list');
            var hidden = document.getElementById('couleurs-hidden');
            if (!couleurInput || !list || !hidden) {
                return;
            }
            var couleurs = [];
            try {
                if (hidden && hidden.value) {
                    var parsed = JSON.parse(hidden.value);
                    if (Array.isArray(parsed)) couleurs = parsed;
                }
            } catch (e) { }

            function updateHidden() {
                if (hidden) hidden.value = JSON.stringify(couleurs);
            }

            function render() {
                if (!list) return;
                list.innerHTML = '';
                couleurs.forEach(function (hex, i) {
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
                btnAdd.addEventListener('click', function () {
                    var hex = couleurInput.value;
                    if (hex && couleurs.indexOf(hex) === -1) {
                        couleurs.push(hex);
                        render();
                    }
                });
            }
            if (list) {
                list.addEventListener('click', function (e) {
                    var btn = e.target.closest('.swatch-remove');
                    if (btn) {
                        var ix = parseInt(btn.dataset.i, 10);
                        couleurs.splice(ix, 1);
                        render();
                    }
                });
            }
            render();
        })();
        (function () {
            function initOptionsWithSurcharge(idInput, idSurcharge, idList, idHidden, btnId) {
                var elInput = document.getElementById(idInput);
                var surchargeInput = document.getElementById(idSurcharge);
                var list = document.getElementById(idList);
                var hidden = document.getElementById(idHidden);
                var btn = document.getElementById(btnId);
                var values = [];
                try {
                    if (hidden && hidden.value && hidden.value !== '[]') {
                        var parsed = JSON.parse(hidden.value);
                        if (Array.isArray(parsed)) values = parsed;
                        else values = (hidden.value.split(',').map(function (s) {
                            return { v: s.trim(), s: 0 };
                        })).filter(function (x) {
                            return x.v && x.v !== '[]';
                        });
                    }
                } catch (e) {
                    if (hidden && hidden.value && hidden.value !== '[]') {
                        values = hidden.value.split(',').map(function (s) {
                            return { v: s.trim(), s: 0 };
                        }).filter(function (x) {
                            return x.v && x.v !== '[]';
                        });
                    }
                }
                values = values.filter(function (item) {
                    var v = typeof item === 'object' ? item.v : item;
                    return v && v !== '[]' && String(v).trim() !== '';
                });

                function updateHidden() {
                    if (hidden) hidden.value = JSON.stringify(values);
                }

                function render() {
                    if (!list) return;
                    list.innerHTML = '';
                    values.forEach(function (item, i) {
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
                if (btn && elInput) {
                    btn.addEventListener('click', function () {
                        var val = (elInput.value || '').trim();
                        var surc = surchargeInput ? (parseInt(surchargeInput.value, 10) || 0) : 0;
                        if (val) {
                            var exists = values.some(function (x) {
                                return (typeof x === 'object' ? x.v : x) === val;
                            });
                            if (!exists) {
                                values.push({ v: val, s: surc });
                                elInput.value = '';
                                if (surchargeInput) surchargeInput.value = '';
                                render();
                            }
                        }
                    });
                    elInput.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            btn.click();
                        }
                    });
                }
                if (list) {
                    list.addEventListener('click', function (e) {
                        var b = e.target.closest('.tag-remove');
                        if (b) {
                            values.splice(parseInt(b.dataset.i, 10), 1);
                            render();
                        }
                    });
                }
                render();
            }
            if (document.getElementById('poids-input')) {
                initOptionsWithSurcharge('poids-input', 'poids-surcharge', 'poids-list', 'poids-hidden', 'btn-add-poids');
            }
            if (document.getElementById('taille-input')) {
                initOptionsWithSurcharge('taille-input', 'taille-surcharge', 'taille-list', 'taille-hidden', 'btn-add-taille');
            }
        })();
        (function () {
            var container = document.getElementById('variantes-container');
            var btnAdd = document.getElementById('btn-add-variante');
            var idx = 1;

            function getVarianteRowHtml() {
                return '<div class="variante-row">' +
                    '<input type="text" name="variantes_nom[]" placeholder="Nom (ex: Format familial)" class="variante-nom">' +
                    '<input type="number" name="variantes_prix[]" placeholder="Prix FCFA" min="0" step="0.01" class="variante-prix">' +
                    '<input type="number" name="variantes_prix_promo[]" placeholder="Prix promo" min="0" step="0.01" class="variante-prix-promo">' +
                    '<div class="variante-image-wrap">' +
                    '<div class="variante-image-area">' +
                    '<input type="file" name="variantes_image[]" accept="image/*" class="variante-image-input">' +
                    '<span class="variante-image-label"><i class="fas fa-image"></i> Image</span>' +
                    '<img class="variante-preview-img" src="" alt="" style="display: none;">' +
                    '</div></div>' +
                    '<button type="button" class="btn-remove-variante" title="Supprimer">&times;</button></div>';
            }

            function previewVarianteImage(inp) {
                var wrap = inp.closest('.variante-image-wrap');
                if (!wrap) return;
                var img = wrap.querySelector('.variante-preview-img');
                var label = wrap.querySelector('.variante-image-label');
                if (!img || !label) return;
                if (inp.files && inp.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                        label.style.display = 'none';
                    };
                    reader.readAsDataURL(inp.files[0]);
                } else {
                    img.src = '';
                    img.style.display = 'none';
                    label.style.display = '';
                }
            }
            if (container) {
                container.addEventListener('change', function (e) {
                    if (e.target.classList.contains('variante-image-input')) {
                        previewVarianteImage(e.target);
                    }
                });
            }
            if (btnAdd && container) {
                btnAdd.addEventListener('click', function () {
                    var div = document.createElement('div');
                    div.className = 'variante-item';
                    div.dataset.index = idx++;
                    div.innerHTML = getVarianteRowHtml();
                    container.appendChild(div);
                    div.querySelector('.btn-remove-variante').addEventListener('click', function () {
                        div.remove();
                    });
                });
                container.addEventListener('click', function (e) {
                    var b = e.target.closest('.btn-remove-variante');
                    if (b && container.children.length > 1) b.closest('.variante-item').remove();
                });
            }
        })();
    </script>
    <?php include '../includes/footer.php'; ?>
