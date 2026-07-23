<?php
/**
 * Page historique des mouvements de stock
 * Recherche live, filtres catégorie / type, pagination
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../models/model_mouvements_stock.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/stock_mouvements_render.php';

$search = trim((string) ($_GET['q'] ?? $_GET['recherche'] ?? ''));
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['entree', 'sortie', 'inventaire'], true)
    ? (string) $_GET['type']
    : null;

$per_page = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total_mouvements = count_stock_mouvements(
    $categorie_id > 0 ? $categorie_id : null,
    $type_filter,
    $search !== '' ? $search : null
);
$total_pages = max(1, (int) ceil($total_mouvements / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$mouvements = get_stock_mouvements_paginated(
    $categorie_id > 0 ? $categorie_id : null,
    $type_filter,
    $search !== '' ? $search : null,
    $offset,
    $per_page
);

$categories = get_all_categories();
$has_filters = ($search !== '' || $categorie_id > 0 || $type_filter !== null);
$from_row = $total_mouvements === 0 ? 0 : $offset + 1;
$to_row = min($page * $per_page, $total_mouvements);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements de stock — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-stock-mouvements.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="mv-page"
        data-mv-page
        data-ajax-url="ajax_mouvements_live.php"
        data-per-page="<?php echo (int) $per_page; ?>"
        data-initial-page="<?php echo (int) $page; ?>">

        <header class="mv-hero">
            <div>
                <p class="mv-hero__eyebrow">Inventaire &amp; traçabilité</p>
                <h1 class="mv-hero__title">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    Historique des mouvements
                </h1>
            </div>
            <a href="index.php" class="mv-hero__back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour au stock
            </a>
        </header>

        <section class="mv-toolbar" data-mv-filters aria-label="Filtres des mouvements">
            <div class="mv-toolbar__head">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                Recherche et filtres en direct
            </div>

            <div class="mv-search-wrap">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search"
                    class="mv-search"
                    id="mv-search"
                    data-mv-search
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Produit, référence, notes, n° commande…"
                    autocomplete="off"
                    inputmode="search"
                    aria-label="Rechercher un mouvement">
            </div>

            <div class="mv-filters-row">
                <div class="mv-filters-grid">
                    <div class="mv-filter-group">
                        <label for="mv-categorie"><i class="fas fa-tags" aria-hidden="true"></i> Catégorie</label>
                        <div class="mv-select-wrap">
                            <select id="mv-categorie" data-mv-categorie class="mv-select">
                                <option value="0">Toutes les catégories</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>" <?php echo $categorie_id === (int) $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down mv-select-wrap__icon" aria-hidden="true"></i>
                        </div>
                    </div>

                    <div class="mv-filter-group">
                        <label for="mv-type"><i class="fas fa-exchange-alt" aria-hidden="true"></i> Type de mouvement</label>
                        <div class="mv-select-wrap">
                            <select id="mv-type" data-mv-type class="mv-select">
                                <option value="" <?php echo $type_filter === null ? 'selected' : ''; ?>>Tous les types</option>
                                <option value="entree" <?php echo $type_filter === 'entree' ? 'selected' : ''; ?>>Entrées</option>
                                <option value="sortie" <?php echo $type_filter === 'sortie' ? 'selected' : ''; ?>>Sorties</option>
                                <option value="inventaire" <?php echo $type_filter === 'inventaire' ? 'selected' : ''; ?>>Inventaires</option>
                            </select>
                            <i class="fas fa-chevron-down mv-select-wrap__icon" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>

                <div class="mv-filters-actions">
                    <button type="button" class="mv-reset-btn" data-mv-reset>
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        <span>Réinitialiser</span>
                    </button>
                </div>
            </div>
        </section>

        <div class="mv-summary">
            <p class="mv-summary__count">
                <strong data-mv-count><?php echo (int) $total_mouvements; ?></strong>
                mouvement<?php echo $total_mouvements > 1 ? 's' : ''; ?>
            </p>
            <p class="mv-summary__hint" data-mv-count-hint>
                <?php if ($total_mouvements === 0): ?>
                    Aucun résultat
                <?php else: ?>
                    <?php echo (int) $from_row; ?>–<?php echo (int) $to_row; ?> sur <?php echo (int) $total_mouvements; ?> mouvement<?php echo $total_mouvements > 1 ? 's' : ''; ?>
                <?php endif; ?>
            </p>
        </div>

        <section class="mv-panel" data-mv-table-section<?php echo empty($mouvements) ? ' hidden' : ''; ?>>
            <div class="mv-loading" data-mv-loading hidden>
                <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Chargement…
            </div>

            <div class="mv-table-wrap">
                <table class="mv-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>Avant</th>
                            <th>Après</th>
                            <th>Référence</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody data-mv-tbody>
                        <?php echo stock_mouvements_render_table_rows($mouvements); ?>
                    </tbody>
                </table>
            </div>

            <div class="mv-cards" data-mv-cards>
                <?php echo stock_mouvements_render_cards($mouvements); ?>
            </div>

            <div data-mv-pagination-wrap>
                <?php echo stock_mouvements_render_pagination($page, $total_pages, $per_page, $total_mouvements); ?>
            </div>
        </section>

        <div class="mv-empty" data-mv-empty<?php echo empty($mouvements) ? '' : ' hidden'; ?>>
            <i class="fas fa-inbox" aria-hidden="true"></i>
            <p class="mv-empty__title">Aucun mouvement trouvé</p>
            <p><?php echo $has_filters ? 'Essayez d’autres critères de recherche ou réinitialisez les filtres.' : 'Les mouvements apparaîtront ici dès qu’un stock sera modifié.'; ?></p>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="/js/admin-stock-mouvements.js<?php echo asset_version_query(); ?>"></script>
</body>

</html>
