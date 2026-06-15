<?php
/**
 * Pagination catalogue admin (dashboard, catégorie, liste produits).
 * Variables : $page, $total_pages, $per_page, $total_produits, $pagination_href_base, $pagination_query_base
 */
if (!isset($page, $total_pages, $per_page, $total_produits, $pagination_href_base, $pagination_query_base)) {
    return;
}
if ((int) $total_pages <= 1) {
    return;
}
$pagination_id = isset($pagination_id) ? (string) $pagination_id : 'page-produits-pagination';
$pagination_class = isset($pagination_class) ? (string) $pagination_class : 'page-produits-pagination';
?>
<nav class="<?php echo htmlspecialchars($pagination_class, ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($pagination_id, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Pagination du catalogue">
    <?php if ($page > 1): ?>
        <?php $prev_q = array_merge($pagination_query_base, ['page' => $page - 1]); ?>
        <a href="<?php echo htmlspecialchars($pagination_href_base . '?' . http_build_query($prev_q), ENT_QUOTES, 'UTF-8'); ?>" class="page-produits-pagination__link">
            <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
        </a>
    <?php endif; ?>

    <span class="page-produits-pagination__info">
        Page <?php echo (int) $page; ?> / <?php echo (int) $total_pages; ?>
        <span class="page-produits-pagination__detail">(<?php echo (int) $per_page; ?> par page · <?php echo (int) $total_produits; ?> au total)</span>
    </span>

    <?php if ($page < $total_pages): ?>
        <?php $next_q = array_merge($pagination_query_base, ['page' => $page + 1]); ?>
        <a href="<?php echo htmlspecialchars($pagination_href_base . '?' . http_build_query($next_q), ENT_QUOTES, 'UTF-8'); ?>" class="page-produits-pagination__link">
            Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
</nav>
