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

$upload_base = isset($upload_base) ? (string) $upload_base : '/upload/';
if ($upload_base !== '' && substr($upload_base, -1) !== '/') {
    $upload_base .= '/';
}

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

$prix = (float) ($produit['prix'] ?? 0);
$prix_aff = !empty($produit['prix_promotion']) ? (float) $produit['prix_promotion'] : $prix;
$img = trim((string) ($produit['image_principale'] ?? ''));
$pcm_four = function_exists('produits_fournisseur_nom_affichage')
    ? produits_fournisseur_nom_affichage($produit) : '';
$pid = (int) ($produit['id'] ?? 0);
$produits_path_prefix = isset($produits_path_prefix) ? (string) $produits_path_prefix : '';
/* Colonnes de la fiche pièce (reprise de FPL natif) : Marque, Modèle et
 * Réf. OEM à la place de Catégorie, Prix, Stock et Statut. Interrupteur posé
 * par la page appelante ; vaut faux par défaut, donc les écrans qui ne le
 * connaissent pas gardent exactement leurs colonnes d'aujourd'hui. */
$fpl_colonnes_piece = !empty($fpl_colonnes_piece);

/* La catégorie n'apparaît pas dans les colonnes de FPL natif — et quand on
 * parcourt déjà une catégorie, la répéter à chaque ligne n'apprend rien. */
$hide_categorie_col = !empty($hide_categorie_col) || $fpl_colonnes_piece;
$fpl_modeles_noms = isset($fpl_modeles_noms) && is_array($fpl_modeles_noms) ? $fpl_modeles_noms : [];
require_once __DIR__ . '/../../../includes/fpl_texte.php';
$detail_href = $produits_path_prefix . 'ajuster-stock.php?id=' . $pid;

$galerie_urls = [];
if (function_exists('produits_galerie_web_urls')) {
    $galerie_urls = produits_galerie_web_urls($produit);
}
if (empty($galerie_urls) && $img !== '') {
    $galerie_urls = [$upload_base . ltrim($img, '/')];
} elseif (!empty($galerie_urls)) {
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
    <td data-label="Produit">
        <span class="page-produits-table__nom"><?php echo e($nom_produit); ?></span>
        <?php if ($pcm_four !== ''): ?>
        <span class="page-produits-table__meta"><?php echo e($pcm_four); ?></span>
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
    <?php if (!$fpl_colonnes_piece): ?>
    <td class="col-num" data-label="Prix">
        <?php echo e(function_exists('fpl_montant') ? fpl_montant($prix_aff) : number_format($prix_aff, 0, ',', ' ')); ?> FCFA
        <?php if (!empty($produit['prix_promotion'])): ?>
        <span class="page-produits-table__promo"><?php echo e(function_exists('fpl_montant') ? fpl_montant($prix) : number_format($prix, 0, ',', ' ')); ?></span>
        <?php endif; ?>
    </td>
    <td class="col-num" data-label="Stock"><?php echo (int) ($produit['stock'] ?? 0); ?></td>
    <td data-label="Statut">
        <span class="page-produits-badge <?php echo $badge_class; ?>"><?php echo e($statut_label); ?></span>
    </td>
    <?php endif; ?>
    <td class="col-actions" data-label="Actions">
        <?php if ($fpl_colonnes_piece): ?>
        <?php // FPL natif ouvre la fiche par un bouton « Détail » en toutes lettres. ?>
        <a href="<?php echo e($produits_path_prefix); ?>modifier.php?id=<?php echo $pid; ?>" class="fpl-btn-detail">Détail</a>
        <?php else: ?>
        <a href="<?php echo e($produits_path_prefix); ?>modifier.php?id=<?php echo $pid; ?>" class="page-produits-table__action" title="Modifier"><i class="fas fa-edit" aria-hidden="true"></i></a>
        <?php endif; ?>
        <a href="<?php echo e($produits_path_prefix); ?>supprimer.php?id=<?php echo $pid; ?>" class="page-produits-table__action page-produits-table__action--danger"
            data-delete-confirm="true"
            data-delete-name="<?php echo e($produit['nom'] ?? ''); ?>"
            title="Supprimer"><i class="fas fa-trash" aria-hidden="true"></i></a>
    </td>
</tr>
