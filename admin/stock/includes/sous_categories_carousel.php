<?php
/**
 * Bandeau horizontal sous-catégories — page stock
 * Variables : $sous_categories (array), $sous_categorie_active (int, optionnel)
 */
if (!isset($sous_categories) || !is_array($sous_categories) || empty($sous_categories)) {
    return;
}

$sous_categorie_active = isset($sous_categorie_active) ? (int) $sous_categorie_active : 0;

if (!function_exists('e')) {
    require_once __DIR__ . '/../../../includes/fpl_ui.php';
}
?>
<section class="stock-sous-categories" aria-label="Parcourir par sous-catégorie">
    <div class="stock-sous-categories__head">
        <h2 class="stock-sous-categories__title"><i class="fas fa-sitemap" aria-hidden="true"></i> Sous-catégories</h2>
        <?php if ($stock_sous_cat_ok ?? false): ?>
        <a href="sous-categories/index.php" class="stock-sous-categories__all">Gérer</a>
        <?php endif; ?>
    </div>
    <div class="stock-sous-categories__track" tabindex="0">
        <?php foreach ($sous_categories as $sc):
            $sid = (int) ($sc['id'] ?? 0);
            $is_active = $sous_categorie_active === $sid;
            $cat_nom = trim((string) ($sc['categorie_nom'] ?? ''));
            ?>
        <a href="sous-categories/produits.php?id=<?php echo $sid; ?>"
            class="stock-sous-cat-card <?php echo $is_active ? 'is-active' : ''; ?>"
            title="<?php echo e($sc['nom'] ?? ''); ?>">
            <span class="stock-sous-cat-card__img" aria-hidden="true">
                <i class="fas fa-folder-tree"></i>
            </span>
            <span class="stock-sous-cat-card__name"><?php echo e($sc['nom'] ?? ''); ?></span>
            <?php if ($cat_nom !== ''): ?>
            <span class="stock-sous-cat-card__meta"><?php echo e($cat_nom); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
