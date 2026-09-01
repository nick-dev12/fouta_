<?php
/**
 * Carte produit — dashboard admin et page catégorie (grille div).
 * Variables : $produit, $pcm_paths (base, upload), $pcm_categorie_nom (optionnel)
 */
if (!isset($produit) || !is_array($produit)) {
    return;
}

if (!function_exists('pf_liste_col_prix_visible')) {
    require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';
}

$show_img = pf_liste_col_image_visible();
$show_cat = pf_liste_col_categorie_visible();
$show_prix = pf_liste_col_prix_visible();
$show_stock = pf_liste_col_stock_visible();
$show_fournisseur = pf_liste_col_fournisseur_visible();

$pcm_paths = isset($pcm_paths) && is_array($pcm_paths) ? $pcm_paths : [
    'base' => 'produits/',
    'upload' => '/upload/',
];
$pcm_base = (string) ($pcm_paths['base'] ?? 'produits/');
$pcm_upload = (string) ($pcm_paths['upload'] ?? '/upload/');

$statut_class = 'statut-actif';
if (($produit['statut'] ?? '') === 'inactif') {
    $statut_class = 'statut-inactif';
} elseif (($produit['statut'] ?? '') === 'rupture_stock') {
    $statut_class = 'statut-rupture';
}
$statut_label = ucfirst(str_replace('_', ' ', (string) ($produit['statut'] ?? '')));
$img_catalogue = $show_img && !empty($produit['image_principale']) ? trim((string) $produit['image_principale']) : '';
$pcm_four = $show_fournisseur && function_exists('produits_fournisseur_nom_affichage')
    ? produits_fournisseur_nom_affichage($produit) : '';
$cat_nom = isset($pcm_categorie_nom) && $pcm_categorie_nom !== ''
    ? (string) $pcm_categorie_nom
    : (string) ($produit['categorie_nom'] ?? 'Sans catégorie');
?>
<div class="produit-card produit-card-linkable produit-card--dashboard"
    data-href="<?php echo htmlspecialchars($pcm_base . 'ajuster-stock.php?id=' . (int) $produit['id'], ENT_QUOTES, 'UTF-8'); ?>">
    <span class="statut-badge <?php echo $statut_class; ?>"><?php echo htmlspecialchars($statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
    <?php if ($show_img): ?>
    <div class="produit-card-media">
        <?php if ($img_catalogue !== ''): ?>
            <img src="<?php echo htmlspecialchars($pcm_upload . $img_catalogue, ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                class="produit-card-image"
                onerror="this.onerror=null;var w=document.createElement('div');w.className='produit-card-media-placeholder';w.setAttribute('role','img');w.setAttribute('aria-label','Sans image');w.innerHTML='<i class=\'fas fa-truck\' aria-hidden=\'true\'></i>';this.replaceWith(w);"
                loading="lazy" decoding="async">
        <?php else: ?>
            <div class="produit-card-media-placeholder" role="img" aria-label="Pas d'image">
                <i class="fas fa-truck" aria-hidden="true"></i>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="produit-card-body">
        <h3 class="produit-card-nom"><?php echo produits_card_heading_inner_html($produit, 20); ?></h3>
        <?php if ($pcm_four !== ''): ?>
            <p class="produit-card-fournisseur"><i class="fas fa-truck-field" aria-hidden="true"></i>
                <?php echo htmlspecialchars($pcm_four, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($show_cat): ?>
        <p class="produit-card-categorie">
            <i class="fas fa-tag" aria-hidden="true"></i>
            <?php echo htmlspecialchars($cat_nom, ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php endif; ?>
        <?php if ($show_prix && array_key_exists('prix', $produit)): ?>
        <p class="produit-card-prix">
            <span class="prix-montant"><?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?></span>
            <span class="prix-unite">FCFA</span>
            <?php if (!empty($produit['prix_promotion']) && pf_champ_visible('prix_promotion')): ?>
                <span class="prix-promo">
                    Promo <?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?> FCFA
                </span>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <?php if ($show_stock && array_key_exists('stock', $produit)): ?>
        <p class="produit-card-stock">
            <i class="fas fa-cubes" aria-hidden="true"></i>
            Stock <span class="stock-value"><?php echo (int) ($produit['stock'] ?? 0); ?></span>
        </p>
        <?php endif; ?>
        <div class="produit-card-actions">
            <a href="<?php echo htmlspecialchars($pcm_base . 'modifier.php?id=' . (int) $produit['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn-card btn-edit">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <a href="<?php echo htmlspecialchars($pcm_base . 'supprimer.php?id=' . (int) $produit['id'], ENT_QUOTES, 'UTF-8'); ?>"
                class="btn-card btn-delete"
                data-delete-confirm="true"
                data-delete-name="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-trash"></i> Supprimer
            </a>
        </div>
    </div>
</div>
