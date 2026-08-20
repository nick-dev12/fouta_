<?php
/**
 * Bandeau horizontal des catégories — page liste produits
 * Variables : $categories (array), $categorie_id (int), $upload_base (string)
 */
if (!isset($categories) || !is_array($categories)) {
    return;
}

$categorie_id = isset($categorie_id) ? (int) $categorie_id : 0;
$upload_base = isset($upload_base) ? (string) $upload_base : '/upload/';
if ($upload_base !== '' && substr($upload_base, -1) !== '/') {
    $upload_base .= '/';
}

if (!function_exists('e')) {
    require_once __DIR__ . '/../../../includes/fpl_ui.php';
}
?>
<section class="page-produits-categories" aria-label="Parcourir par catégorie">
    <div class="page-produits-categories__head">
        <h2 class="page-produits-categories__title"><i class="fas fa-layer-group" aria-hidden="true"></i> Catégories</h2>
        <a href="index.php" class="page-produits-categories__all <?php echo $categorie_id === 0 ? 'is-active' : ''; ?>">Toutes</a>
    </div>
    <div class="page-produits-categories__track" tabindex="0">
        <a href="index.php" class="page-produits-cat-card <?php echo $categorie_id === 0 ? 'is-active' : ''; ?>">
            <span class="page-produits-cat-card__img page-produits-cat-card__img--all" aria-hidden="true">
                <i class="fas fa-th"></i>
            </span>
            <span class="page-produits-cat-card__name">Toutes</span>
        </a>
        <?php foreach ($categories as $cat):
            $cid = (int) ($cat['id'] ?? 0);
            $img = trim((string) ($cat['image'] ?? ''));
            $is_active = $categorie_id === $cid;
            ?>
        <a href="index.php?categorie_id=<?php echo $cid; ?>"
            class="page-produits-cat-card <?php echo $is_active ? 'is-active' : ''; ?>"
            title="<?php echo e($cat['nom'] ?? ''); ?>">
            <?php if ($img !== ''): ?>
            <span class="page-produits-cat-card__img">
                <img src="<?php echo e($upload_base . $img); ?>" alt="" loading="lazy" decoding="async"
                    onerror="this.parentElement.classList.add('page-produits-cat-card__img--ph');this.remove();">
            </span>
            <?php else: ?>
            <span class="page-produits-cat-card__img page-produits-cat-card__img--ph" aria-hidden="true">
                <i class="fas fa-tag"></i>
            </span>
            <?php endif; ?>
            <span class="page-produits-cat-card__name"><?php echo e($cat['nom'] ?? ''); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
