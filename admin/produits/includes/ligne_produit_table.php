<?php
/**
 * Ligne tableau — liste admin produits (index + recherche AJAX)
 * Variables : $produit (array), $upload_base (string, optionnel)
 */
if (!isset($produit) || !is_array($produit)) {
    return;
}

if (!function_exists('e')) {
    require_once __DIR__ . '/../../../includes/fpl_ui.php';
}
if (!function_exists('pf_liste_col_prix_visible')) {
    require_once __DIR__ . '/../../../includes/produit_formulaire_champs.php';
}

$upload_base = isset($upload_base) ? (string) $upload_base : '/upload/';
if ($upload_base !== '' && substr($upload_base, -1) !== '/') {
    $upload_base .= '/';
}

$show_img = pf_liste_col_image_visible();
$show_cat = pf_liste_col_categorie_visible();
$show_prix = pf_liste_col_prix_visible();
$show_stock = pf_liste_col_stock_visible();
$show_statut = pf_liste_col_statut_visible();
$show_ident = pf_liste_col_ident_visible();

$statut = (string) ($produit['statut'] ?? '');
$statut_label = ucfirst(str_replace('_', ' ', $statut));
$badge_class = 'page-produits-badge--muted';
if ($statut === 'actif') {
    $badge_class = 'page-produits-badge--ok';
} elseif ($statut === 'rupture_stock') {
    $badge_class = 'page-produits-badge--warn';
} elseif ($statut === 'inactif') {
    $badge_class = 'page-produits-badge--muted';
}

$prix = array_key_exists('prix', $produit) ? (float) $produit['prix'] : null;
$prix_promo = array_key_exists('prix_promotion', $produit) ? $produit['prix_promotion'] : null;
$prix_aff = null;
if ($prix_promo !== null && $prix_promo !== '' && (float) $prix_promo > 0) {
    $prix_aff = (float) $prix_promo;
} elseif ($prix !== null) {
    $prix_aff = $prix;
}

$img = $show_img ? trim((string) ($produit['image_principale'] ?? '')) : '';
$ref_fpl = '';
if ($show_ident && !empty($produit['identifiant_interne'])) {
    $ref_fpl = strtoupper(trim((string) $produit['identifiant_interne']));
}
$pid = (int) ($produit['id'] ?? 0);
$produits_path_prefix = isset($produits_path_prefix) ? (string) $produits_path_prefix : '';
/* Colonnes de la fiche pièce (reprise de FPL natif) : Marque, Modèle et
 * Réf. OEM à la place de Catégorie, Prix, Stock et Statut. Interrupteur posé
 * par la page appelante ; vaut faux par défaut, donc les écrans qui ne le
 * connaissent pas gardent exactement leurs colonnes d'aujourd'hui. */
$fpl_colonnes_piece = !empty($fpl_colonnes_piece);

/* La catégorie n'apparaît pas dans les colonnes de FPL natif — et quand on
 * parcourt déjà une catégorie, la répéter à chaque ligne n'apprend rien. */
$hide_categorie_col = !empty($hide_categorie_col) || $fpl_colonnes_piece || !$show_cat;
$fpl_modeles_noms = isset($fpl_modeles_noms) && is_array($fpl_modeles_noms) ? $fpl_modeles_noms : [];
require_once __DIR__ . '/../../../includes/fpl_texte.php';
$detail_href = $produits_path_prefix . 'ajuster-stock.php?id=' . $pid;

$galerie_urls = [];
if ($show_img && function_exists('produits_galerie_web_urls')) {
    $galerie_urls = produits_galerie_web_urls($produit);
}
if ($show_img && empty($galerie_urls) && $img !== '') {
    $galerie_urls = [$upload_base . ltrim($img, '/')];
} elseif ($show_img && !empty($galerie_urls)) {
    $root_prefix = '';
    if (function_exists('get_public_root_uri_path')) {
        $root_prefix = rtrim((string) get_public_root_uri_path(), '/');
    }
    $fixed = [];
    foreach ($galerie_urls as $one) {
        $one = (string) $one;
        if ($one === '') {
            continue;
        }
        if ($one[0] === '/' && $root_prefix !== '' && strpos($one, $root_prefix . '/') !== 0) {
            $fixed[] = $root_prefix . $one;
        } else {
            $fixed[] = $one;
        }
    }
    $galerie_urls = $fixed;
}
$galerie_json = htmlspecialchars(json_encode(array_values($galerie_urls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$nom_produit = (string) ($produit['nom'] ?? '');
?>
<tr class="page-produits-table__row page-produits-table__row--linkable"
    data-href="<?php echo e($detail_href); ?>"
    tabindex="0"
    role="link"
    aria-label="<?php echo e('Voir la fiche : ' . $nom_produit); ?>">
    <?php if ($show_img): ?>
    <td class="col-thumb" data-label="Visuel">
        <?php if ($img !== ''): ?>
        <button type="button" class="page-produits-table__thumb-btn"
            data-produit-gallery="<?php echo $galerie_json; ?>"
            data-produit-nom="<?php echo e($nom_produit); ?>"
            aria-label="<?php echo e('Voir la galerie photos : ' . $nom_produit); ?>"
            title="Voir la galerie photos">
            <img src="<?php echo e($upload_base . $img); ?>" alt="" class="page-produits-table__thumb" loading="lazy" decoding="async"
                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span class="page-produits-table__thumb page-produits-table__thumb--ph" style="display:none" aria-hidden="true"><i class="fas fa-box"></i></span>
        </button>
        <?php else: ?>
        <span class="page-produits-table__thumb page-produits-table__thumb--ph" aria-hidden="true"><i class="fas fa-box"></i></span>
        <?php endif; ?>
    </td>
    <?php endif; ?>
    <td data-label="<?php echo $fpl_colonnes_piece ? 'Pièce' : 'Produit'; ?>">
        <?php if ($fpl_colonnes_piece): ?>
        <a class="page-produits-table__nom fpl-cell-title" href="<?php echo e($detail_href); ?>"><?php echo e($nom_produit); ?></a>
        <?php else: ?>
        <span class="page-produits-table__nom"><?php echo e($nom_produit); ?></span>
        <?php endif; ?>
        <?php if ($fpl_colonnes_piece): ?>
        <?php $fpl_code = trim((string) ($produit['identifiant_interne'] ?? '')); ?>
        <?php if ($fpl_code !== '' && $show_ident): ?>
        <div class="fpl-cell-sub"><span class="fpl-chip-code"><?php
            echo e(function_exists('fpl_code_afficher') ? fpl_code_afficher($fpl_code) : $fpl_code);
        ?></span></div>
        <?php endif; ?>
        <?php elseif ($ref_fpl !== ''): ?>
        <span class="page-produits-table__ref-fpl"><code><?php echo e($ref_fpl); ?></code></span>
        <?php endif; ?>
    </td>
    <?php if ($fpl_colonnes_piece): ?>
    <?php // Les colonnes de la fiche pièce : ce qui identifie une pièce de
          // camion — la marque du véhicule, son modèle, la référence d'origine. ?>
    <td data-label="Marque"><?php echo fpl_e($produit['marque_libelle_catalogue'] ?? '') ?: '—'; ?></td>
    <td data-label="Modèle"><?php
        $mid = (int) ($produit['modele_id'] ?? 0);
        echo ($mid > 0 && isset($fpl_modeles_noms[$mid])) ? fpl_e($fpl_modeles_noms[$mid]) : '—';
    ?></td>
    <td data-label="Réf. OEM"><?php
        $roem = trim((string) ($produit['reference_oem'] ?? ''));
        echo $roem !== '' ? '<span class="fpl-ref-oem">' . fpl_e($roem) . '</span>' : '—';
    ?></td>
    <?php endif; ?>
    <?php if (!$hide_categorie_col): ?>
    <td data-label="Catégorie"><?php echo fpl_e($produit['categorie_nom'] ?? '—'); ?></td>
    <?php endif; ?>
    <?php if (!$fpl_colonnes_piece && $show_prix): ?>
    <td class="col-num" data-label="Prix">
        <?php if ($prix_aff !== null): ?>
        <?php echo e(function_exists('fpl_montant') ? fpl_montant($prix_aff) : number_format($prix_aff, 0, ',', ' ')); ?> FCFA
        <?php if ($prix_promo !== null && $prix_promo !== '' && (float) $prix_promo > 0 && pf_champ_visible('prix') && $prix !== null): ?>
        <span class="page-produits-table__promo"><?php echo e(function_exists('fpl_montant') ? fpl_montant($prix) : number_format($prix, 0, ',', ' ')); ?></span>
        <?php endif; ?>
        <?php else: ?>
        —
        <?php endif; ?>
    </td>
    <?php endif; ?>
    <?php if ($show_stock): ?>
    <td class="col-num" data-label="Stock"><?php echo (int) ($produit['stock'] ?? 0); ?></td>
    <?php endif; ?>
    <?php if ($show_statut): ?>
    <td data-label="Statut">
        <span class="page-produits-badge <?php echo $badge_class; ?>"><?php echo e($statut_label); ?></span>
    </td>
    <?php endif; ?>
    <td class="col-actions" data-label="Actions">
        <?php if ($fpl_colonnes_piece): ?>
        <?php // FPL natif ouvre la fiche par un bouton « Détail » en toutes lettres.
              // Il s'AJOUTE au crayon de ce dépôt, il ne le remplace pas.
              // Il mène à LA FICHE — la même adresse que le clic sur la ligne —
              // et non plus au formulaire de modification, qui reste au crayon. ?>
        <a href="<?php echo e($detail_href); ?>" class="fpl-btn-detail"><i class="fas fa-eye" aria-hidden="true"></i> Détail</a>
        <?php endif; ?>
        <a href="<?php echo e($produits_path_prefix); ?>modifier.php?id=<?php echo $pid; ?>" class="page-produits-table__action" title="Modifier"><i class="fas fa-edit" aria-hidden="true"></i></a>
        <a href="<?php echo e($produits_path_prefix); ?>supprimer.php?id=<?php echo $pid; ?>" class="page-produits-table__action page-produits-table__action--danger"
            data-delete-confirm="true"
            data-delete-name="<?php echo e($produit['nom'] ?? ''); ?>"
            title="Supprimer"><i class="fas fa-trash" aria-hidden="true"></i></a>
    </td>
</tr>
