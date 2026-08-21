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
require_once __DIR__ . '/../../includes/fouta_upload_limits.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

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

require_once __DIR__ . '/../../models/model_sous_categories.php';
$has_prix_achat_col = produits_has_column('prix_achat');
$has_sous_cat_col = produits_has_column('sous_categorie_id')
    && function_exists('sous_categories_table_ok')
    && sous_categories_table_ok();
$sous_categories_all = $has_sous_cat_col ? get_all_sous_categories_with_categorie_nom() : [];
$sous_cat_preselect = isset($_GET['sous_categorie_id']) ? (int) $_GET['sous_categorie_id'] : 0;
$has_ident_col = produits_has_column('identifiant_interne');
$has_img_etiq_col = produits_has_column('image_etiquette_fpl');
$has_marque_col = produits_has_column('marque_id');
// Identité technique des pièces poids lourds (reprise de FPL natif)
$has_ref_oem_col = produits_has_column('reference_oem');
$has_nom_fourn_col = produits_has_column('nom_fournisseur');
$has_position_col = produits_has_column('position_montage');
$has_taille_col = produits_has_column('taille');
$has_ref_fourn_col = produits_has_column('reference_fournisseur');
require_once __DIR__ . '/../../models/model_marques.php';
$marques_catalogue = ($has_marque_col && marques_table_ok()) ? get_all_marques_ordered_by_nom() : [];
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';
$emplacement_form_vals = produit_emplacement_form_values_for_form($_POST);
$pf_custom_vals = [];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
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
                <p class="pm-subtitle">Même présentation que la fiche modification : informations, tarifs, emplacement puis galerie. La référence <strong>FPL</strong> est attribuée automatiquement à l’enregistrement.</p>
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

            <?php if (!empty($_SESSION['produit_form_notice'])): ?>
            <div class="message success" role="status" style="margin-bottom:1rem;">
                <i class="fas fa-info-circle" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $_SESSION['produit_form_notice'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['produit_form_notice']); endif; ?>
            <form method="POST" action="" enctype="multipart/form-data" class="pm-form" id="form-produit-ajouter">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FOUTA_UPLOAD_IMAGE_MAX_BYTES; ?>">
                <div class="pm-sections">
                    <?php // --- Véhicule compatible : le camion avant la pièce (ordre FPL) --- ?>
                    <?php if (pf_champ_visible('marque_id') && $has_marque_col): ?>
                    <section class="pm-card" aria-labelledby="pm-sec-vehicule-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-truck"></i></span>
                            <div>
                                <h2 id="pm-sec-vehicule-add" class="pm-card__title">Véhicule compatible</h2>
                                <p class="pm-card__hint">Le camion sur lequel la pièce se monte</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="marque_id">Marque</label>
                                    <select id="marque_id" name="marque_id">
                                        <option value="">— Aucune —</option>
                                        <?php foreach ($marques_catalogue as $mq): ?>
                                        <option value="<?php echo (int) $mq['id']; ?>" <?php echo (isset($_POST['marque_id']) && (string) $_POST['marque_id'] === (string) $mq['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mq['nom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-hint">Les modèles et générations compatibles viendront s'ajouter ici.</small>
                                </div>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>
                    <?php if (pf_section_visible('info')): ?>
                    <section class="pm-card" aria-labelledby="pm-sec-info-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-align-left"></i></span>
                            <div>
                                <h2 id="pm-sec-info-add" class="pm-card__title">Identité de la pièce</h2>
                                <p class="pm-card__hint">Nom, références et fournisseur</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <?php if (pf_champ_visible('nom')): ?>
                            <div class="form-group">
                                <label for="nom">Nom du produit<?php echo pf_champ_obligatoire('nom') ? ' *' : ''; ?></label>
                                <input type="text" id="nom" name="nom"<?php echo pf_champ_obligatoire('nom') ? ' required' : ''; ?> placeholder="Ex : Filtre à air compatible…"
                                    value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('description')): ?>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" placeholder="Décrivez le produit… (facultatif)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <small class="form-hint">Facultatif.</small>
                            </div>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('fournisseur_id')): ?>
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
                            <?php endif; ?>
                            <?php // La marque a rejoint la section « Véhicule compatible », en tête. ?>
                            <?php if (pf_champ_visible('reference_fournisseur')): ?>
                            <div class="form-row">
                                <?php if (pf_champ_visible('reference_fournisseur') && $has_ref_fourn_col): ?>
                                <div class="form-group">
                                    <label for="reference_fournisseur">Référence fournisseur</label>
                                    <input type="text" id="reference_fournisseur" name="reference_fournisseur" maxlength="120"
                                        placeholder="Code ou réf. chez le fournisseur"
                                        value="<?php echo isset($_POST['reference_fournisseur']) ? htmlspecialchars((string) $_POST['reference_fournisseur'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php // --- Pièces poids lourds : identité technique (FPL) --- ?>
                            <?php if ($has_ref_oem_col || $has_nom_fourn_col): ?>
                            <div class="form-row">
                                <?php if ($has_ref_oem_col): ?>
                                <div class="form-group">
                                    <label for="reference_oem">Référence OEM</label>
                                    <input type="text" id="reference_oem" name="reference_oem" maxlength="120"
                                        placeholder="Référence du constructeur"
                                        value="<?php echo isset($_POST['reference_oem']) ? htmlspecialchars((string) $_POST['reference_oem'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    <small class="form-hint">La référence d'origine du camion, gravée sur la pièce.</small>
                                </div>
                                <?php endif; ?>
                                <?php if ($has_nom_fourn_col): ?>
                                <div class="form-group">
                                    <label for="nom_fournisseur">Nom du fournisseur</label>
                                    <input type="text" id="nom_fournisseur" name="nom_fournisseur" maxlength="150"
                                        placeholder="Si absent de la liste ci-dessus"
                                        value="<?php echo isset($_POST['nom_fournisseur']) ? htmlspecialchars((string) $_POST['nom_fournisseur'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    <small class="form-hint">À remplir seulement quand le fournisseur n'est pas au référentiel.</small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($has_position_col || $has_taille_col): ?>
                            <div class="form-row">
                                <?php if ($has_position_col): ?>
                                <div class="form-group">
                                    <label for="position_montage">Côté de montage</label>
                                    <select id="position_montage" name="position_montage">
                                        <option value="">— Indifférent —</option>
                                        <?php foreach (['gauche' => 'Gauche', 'droite' => 'Droite'] as $pv => $pl): ?>
                                        <option value="<?php echo $pv; ?>" <?php echo (isset($_POST['position_montage']) && (string) $_POST['position_montage'] === $pv) ? 'selected' : ''; ?>>
                                            <?php echo $pl; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-hint">Pour les pièces qui existent en paire (rétroviseurs, phares…).</small>
                                </div>
                                <?php endif; ?>
                                <?php if ($has_taille_col): ?>
                                <div class="form-group">
                                    <label for="taille">Taille</label>
                                    <input type="text" id="taille" name="taille" maxlength="120"
                                        placeholder="Ex. 4PK1250, 24V, 80 mm"
                                        value="<?php echo isset($_POST['taille']) ? htmlspecialchars((string) $_POST['taille'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php produit_formulaire_render_champs_custom('info', $pf_custom_vals, $_POST); ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (pf_section_visible('prix')): ?>
                    <section class="pm-card" aria-labelledby="pm-sec-prix-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                            <div>
                                <h2 id="pm-sec-prix-add" class="pm-card__title">Prix &amp; rangement</h2>
                                <p class="pm-card__hint">Tarif, inventaire et classement</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <?php if (pf_champ_visible('prix') || pf_champ_visible('prix_promotion')): ?>
                            <div class="form-row">
                                <?php if (pf_champ_visible('prix')): ?>
                                <div class="form-group">
                                    <label for="prix">Prix de vente (FCFA)</label>
                                    <input type="number" id="prix" name="prix" step="0.01" min="0"
                                        value="<?php echo isset($_POST['prix']) ? htmlspecialchars($_POST['prix']) : ''; ?>">
                                    <small class="form-hint">Facultatif — vide = 0&nbsp;FCFA en base.</small>
                                </div>
                                <?php endif; ?>
                                <?php if (pf_champ_visible('prix_promotion')): ?>
                                <div class="form-group">
                                    <label for="prix_promotion">Prix promotionnel (FCFA)</label>
                                    <input type="number" id="prix_promotion" name="prix_promotion" step="0.01" min="0"
                                        value="<?php echo isset($_POST['prix_promotion']) ? htmlspecialchars($_POST['prix_promotion']) : ''; ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('prix_achat') && $has_prix_achat_col): ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="prix_achat">Prix d'achat (FCFA)</label>
                                    <input type="number" id="prix_achat" name="prix_achat" step="0.01" min="0"
                                        value="<?php echo isset($_POST['prix_achat']) ? htmlspecialchars((string) $_POST['prix_achat'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    <small class="form-hint">Facultatif.</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('stock') || pf_champ_visible('categorie_id')): ?>
                            <div class="form-row">
                                <?php if (pf_champ_visible('stock')): ?>
                                <div class="form-group">
                                    <label for="stock">Stock<?php echo pf_champ_obligatoire('stock') ? ' *' : ''; ?></label>
                                    <input type="number" id="stock" name="stock" min="0"<?php echo pf_champ_obligatoire('stock') ? ' required' : ''; ?>
                                        value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>">
                                </div>
                                <?php endif; ?>
                                <?php if (pf_champ_visible('categorie_id')): ?>
                                <div class="form-group">
                                    <label for="categorie_id">Catégorie<?php echo pf_champ_obligatoire('categorie_id') ? ' *' : ''; ?></label>
                                    <select id="categorie_id" name="categorie_id"<?php echo pf_champ_obligatoire('categorie_id') ? ' required' : ''; ?>>
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
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('sous_categorie_id') && $has_sous_cat_col): ?>
                            <div class="form-row" id="sous-categorie-field-row">
                                <div class="form-group">
                                    <label for="sous_categorie_id">Sous-catégorie</label>
                                    <select id="sous_categorie_id" name="sous_categorie_id">
                                        <option value="">— Aucune —</option>
                                        <?php
                                        $sc_post = isset($_POST['sous_categorie_id']) ? (int) $_POST['sous_categorie_id'] : $sous_cat_preselect;
                                        foreach ($sous_categories_all as $sc):
                                        ?>
                                        <option value="<?php echo (int) $sc['id']; ?>"
                                            data-categorie-id="<?php echo (int) $sc['categorie_id']; ?>"
                                            <?php echo $sc_post === (int) $sc['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sc['nom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-hint">Affiché seulement si la catégorie possède des sous-catégories. <a href="../stock/index.php">Créer une sous-catégorie</a>.</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php produit_formulaire_render_champs_custom('prix', $pf_custom_vals, $_POST); ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (pf_section_visible('ref')): ?>
                    <section class="pm-card" aria-labelledby="pm-sec-ref-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-warehouse"></i></span>
                            <div>
                                <h2 id="pm-sec-ref-add" class="pm-card__title">Stock &amp; emplacement</h2>
                                <p class="pm-card__hint">Référence FPL automatique · position : <?php echo htmlspecialchars(function_exists('entrepot_hierarchie_chemin_libelle') ? entrepot_hierarchie_chemin_libelle() : 'Niveau → …', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <?php if (!$has_ident_col && pf_champ_visible('identifiant_interne')): ?>
                            <p class="pm-hint">
                                <i class="fas fa-info-circle" aria-hidden="true"></i> Activez la colonne <code>identifiant_interne</code> (migrations) pour activer la référence FPL automatique sur ce formulaire.
                            </p>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('emplacement')): ?>
                            <?php produit_emplacement_render_form_fields($emplacement_form_vals); ?>
                            <?php endif; ?>
                            <?php produit_formulaire_render_champs_custom('ref', $pf_custom_vals, $_POST); ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (pf_champ_visible('variantes')): ?>
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
                    <?php endif; ?>

                    <?php if (pf_section_visible('options')): ?>
                    <section class="pm-card admin-ajouter-produit-masquer" aria-labelledby="pm-sec-opts-add" aria-hidden="true">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-sliders"></i></span>
                            <div>
                                <h2 id="pm-sec-opts-add" class="pm-card__title">Options d’achat</h2>
                                <p class="pm-card__hint">Poids et couleurs proposés au client</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <?php if (pf_champ_visible('poids')): ?>
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
                            <?php endif; ?>
                            <?php if (pf_champ_visible('couleurs')): ?>
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
                            <?php endif; ?>
                            <?php produit_formulaire_render_champs_custom('options', $pf_custom_vals, $_POST); ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (pf_section_visible('media')): ?>
                    <section class="pm-card" aria-labelledby="pm-sec-media-add">
                        <div class="pm-card__head">
                            <span class="pm-card__icon" aria-hidden="true"><i class="fas fa-images"></i></span>
                            <div>
                                <h2 id="pm-sec-media-add" class="pm-card__title">Photos de la pièce</h2>
                                <p class="pm-card__hint">La première image devient la photo principale</p>
                            </div>
                        </div>
                        <div class="pm-card__body">
                            <?php if (pf_champ_visible('images_produit')): ?>
                            <div class="form-group">
                                <label><i class="fas fa-image"></i> Images du produit</label>
                                <p class="form-hint" style="margin-bottom: 10px;">Ajoutez une ou plusieurs images (cumul possible). Cliquez sur × sur un aperçu pour le retirer avant envoi.</p>
                                <label for="images_produit" class="pm-upload-label">
                                    <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                                    Parcourir ou déposer vos fichiers
                                </label>
                                <input type="file" id="images_produit" name="images_produit[]" accept="image/*" multiple
                                    class="pm-file-hidden">
                                <div id="preview-images" class="image-preview-accumulator"></div>
                            </div>
                            <?php endif; ?>
                            <?php if (pf_champ_visible('image_etiquette_fpl') && $has_img_etiq_col): ?>
                            <div class="form-group" style="margin-top: 1.25rem;">
                                <label for="image_etiquette_fpl"><i class="fas fa-tag" aria-hidden="true"></i> Photo pour l’étiquette FPL (optionnel)</label>
                                <label for="image_etiquette_fpl" class="pm-upload-label">
                                    <i class="fas fa-image" aria-hidden="true"></i> Choisir une image
                                </label>
                                <input type="file" id="image_etiquette_fpl" name="image_etiquette_fpl" accept="image/*" class="pm-file-hidden">
                                <div id="preview-image-etiquette-fpl" class="pm-etiquette-fpl-preview-wrap" aria-live="polite"></div>
                                <p id="preview-image-etiquette-fpl-placeholder" class="form-hint" style="margin-top:8px;">Aucun fichier sélectionné — les pictogrammes par défaut seront utilisés sur l’étiquette.</p>
                                <small class="form-hint">JPG, PNG, GIF, WEBP · max <?php echo (int) fouta_upload_image_max_mo_int(); ?> Mo.</small>
                            </div>
                            <?php endif; ?>
                            <?php produit_formulaire_render_champs_custom('media', $pf_custom_vals, $_POST); ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (pf_champ_visible('statut')): ?>
                    <input type="hidden" name="statut" value="actif">
                    <?php endif; ?>
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

        })();
        (function () {
            var inp = document.getElementById('image_etiquette_fpl');
            var box = document.getElementById('preview-image-etiquette-fpl');
            var ph = document.getElementById('preview-image-etiquette-fpl-placeholder');
            if (!inp || !box) {
                return;
            }
            inp.addEventListener('change', function () {
                box.innerHTML = '';
                if (ph) {
                    ph.style.display = '';
                }
                var f = inp.files && inp.files[0];
                if (!f || !f.type.match(/^image\//)) {
                    return;
                }
                if (ph) {
                    ph.style.display = 'none';
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    var im = document.createElement('img');
                    im.src = e.target.result;
                    im.alt = 'Aperçu étiquette FPL';
                    im.className = 'pm-etiquette-fpl-preview-img';
                    box.appendChild(im);
                };
                reader.readAsDataURL(f);
            });
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
    <?php if ($has_sous_cat_col): ?>
    <script>
        (function () {
            var cat = document.getElementById('categorie_id');
            var sub = document.getElementById('sous_categorie_id');
            var row = document.getElementById('sous-categorie-field-row');
            if (!cat || !sub || !row) return;

            function applySousCategorieFiltre() {
                var cid = String(cat.value || '');
                var i, o;
                var countForCat = 0;

                for (i = 0; i < sub.options.length; i++) {
                    o = sub.options[i];
                    if (!o.value) {
                        continue;
                    }
                    var match = cid !== '' && o.getAttribute('data-categorie-id') === cid;
                    o.hidden = !match;
                    if (match) {
                        countForCat++;
                    }
                }

                var sel = sub.options[sub.selectedIndex];
                if (sel && sel.value && (cid === '' || sel.getAttribute('data-categorie-id') !== cid)) {
                    sub.value = '';
                }

                if (cid === '' || countForCat === 0) {
                    row.style.display = 'none';
                    sub.value = '';
                } else {
                    row.style.removeProperty('display');
                    if (sub.options[0] && !sub.options[0].value) {
                        sub.options[0].hidden = false;
                    }
                }
            }

            cat.addEventListener('change', applySousCategorieFiltre);
            applySousCategorieFiltre();
        })();
    </script>
    <?php endif; ?>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <script src="/js/admin-emplacement-produit.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/fpl-description-auto.js<?php echo asset_version_query(); ?>"></script>
    <?php include '../includes/footer.php'; ?>
