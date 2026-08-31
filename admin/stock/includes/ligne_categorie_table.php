<?php
/**
 * Ligne tableau catégorie — page stock
 * Variables : $categorie (array), $upload_base (string), $nb_sous_cat (int, optionnel)
 */
if (!isset($categorie) || !is_array($categorie)) {
    return;
}

if (!function_exists('e')) {
    require_once __DIR__ . '/../../../includes/fpl_ui.php';
}

$upload_base = isset($upload_base) ? (string) $upload_base : '/upload/';
if ($upload_base !== '' && substr($upload_base, -1) !== '/') {
    $upload_base .= '/';
}

$cid = (int) ($categorie['id'] ?? 0);
$img = trim((string) ($categorie['image'] ?? ''));
$nb_produits = (int) ($categorie['nb_produits'] ?? 0);
$nb_sous = isset($nb_sous_cat) ? (int) $nb_sous_cat : 0;
/* Le clic sur une catégorie ouvre désormais LE CATALOGUE (admin/produits/index.php,
 * le portage de la page Pièces de FPL natif) : le bandeau de ses rayons, puis
 * les pièces. L'ancienne page ../categories/produits.php reste en place, elle
 * n'est simplement plus la destination de ce tableau. */
$detail_href = '../produits/index.php?categorie_id=' . $cid;
$can_edit = !function_exists('admin_is_restricted_admin_account') || !admin_is_restricted_admin_account();
?>
<tr class="stock-cat-table__row stock-cat-table__row--linkable"
    data-href="<?php echo e($detail_href); ?>"
    tabindex="0"
    role="link"
    aria-label="<?php echo e('Voir les produits : ' . ($categorie['nom'] ?? '')); ?>">
    <td class="col-thumb" data-label="Visuel">
        <?php if ($img !== ''): ?>
        <img src="<?php echo e($upload_base . $img); ?>" alt="" class="stock-cat-table__thumb" loading="lazy" decoding="async"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <span class="stock-cat-table__thumb stock-cat-table__thumb--ph" style="display:none" aria-hidden="true"><i class="fas fa-tag"></i></span>
        <?php else: ?>
        <span class="stock-cat-table__thumb stock-cat-table__thumb--ph" aria-hidden="true"><i class="fas fa-tag"></i></span>
        <?php endif; ?>
    </td>
    <td data-label="Catégorie">
        <span class="stock-cat-table__nom"><?php echo e($categorie['nom'] ?? ''); ?></span>
    </td>
    <td class="col-num" data-label="Produits"><?php echo $nb_produits; ?></td>
    <?php if ($stock_sous_cat_ok ?? false): ?>
    <td class="col-num" data-label="Sous-cat."><?php echo $nb_sous; ?></td>
    <?php endif; ?>
    <td class="col-actions" data-label="Actions">
        <a href="<?php echo e($detail_href); ?>" class="stock-cat-table__action" title="Produits"><i class="fas fa-box" aria-hidden="true"></i></a>
        <?php if ($can_edit): ?>
        <a href="../categories/modifier.php?id=<?php echo $cid; ?>" class="stock-cat-table__action" title="Modifier"><i class="fas fa-edit" aria-hidden="true"></i></a>
        <?php // Pas de confirm() ici : le lien mène à la page de confirmation
              // supprimer.php (comme le rail du catalogue) — une seule étape. ?>
        <a href="../categories/supprimer.php?id=<?php echo $cid; ?>" class="stock-cat-table__action stock-cat-table__action--danger"
            title="Supprimer"><i class="fas fa-trash" aria-hidden="true"></i></a>
        <?php endif; ?>
    </td>
</tr>
