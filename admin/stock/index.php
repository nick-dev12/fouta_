<?php
/**
 * Gestion du stock - Catégories et produits
 * Contenu déplacé depuis categories/index.php
 * Utilise la table produits et la colonne stock (plus de table stock_articles)
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_sous_categories.php';
require_once __DIR__ . '/../../includes/site_url.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';

$categories = get_all_categories_with_count();
$nb_cat = count($categories);
$stock_sous_cat_ok = produits_has_column('sous_categorie_id')
    && function_exists('sous_categories_table_ok')
    && sous_categories_table_ok();
$sous_categories = $stock_sous_cat_ok ? get_all_sous_categories_with_categorie_nom() : [];
$sous_cat_count_by_categorie = [];
foreach ($sous_categories as $sc_row) {
    $cid_sc = (int) ($sc_row['categorie_id'] ?? 0);
    if ($cid_sc > 0) {
        $sous_cat_count_by_categorie[$cid_sc] = ($sous_cat_count_by_categorie[$cid_sc] ?? 0) + 1;
    }
}
$stock_upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du stock — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
    <?php fpl_css_link('admin-stock-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

        <?php
        if (!empty($success_message)) {
            $flash_success_message = $success_message;
            include __DIR__ . '/../includes/flash_success_popup.php';
            $success_message = '';
        }
        ?>

        <div class="page-lead">
            <div>
                <div class="page-lead-title">Gestion du stock</div>
                <div class="muted">
                    <?php echo (int) $nb_cat; ?> catégorie<?php echo $nb_cat > 1 ? 's' : ''; ?> —
                    ouvrez-en une pour voir ses rayons et ses pièces.
                </div>
            </div>
            <div class="stock-hero__actions">
                <a href="mouvements.php" class="btn btn-outline">
                    <?php echo fpl_icone('clock', 14); ?> Historique des mouvements
                </a>
                <?php if ($stock_sous_cat_ok && !admin_is_restricted_admin_account()): ?>
                <a href="sous-categories/index.php" class="btn btn-outline">
                    <?php echo fpl_icone('layers', 14); ?> Voir les sous-catégories
                </a>
                <?php endif; ?>
                <?php if (!admin_is_restricted_admin_account()): ?>
                <a href="../categories/ajouter.php" class="btn btn-primary">
                    <?php echo fpl_icone('plus', 14); ?> Nouvelle catégorie
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($_SESSION['produit_form_notice'])): ?>
        <div class="stock-banner-ok" role="status">
            <?php echo fpl_icone('info', 14); ?>
            <span><?php echo htmlspecialchars((string) $_SESSION['produit_form_notice'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php unset($_SESSION['produit_form_notice']); endif; ?>

        <?php if ($stock_sous_cat_ok && !empty($sous_categories)): ?>
            <?php include __DIR__ . '/includes/sous_categories_carousel.php'; ?>
        <?php endif; ?>

        <?php if (empty($categories)): ?>
        <div class="form-card">
          <div class="stock-empty">
            <?php echo fpl_icone('folder', 30); ?>
            <h3>Aucune catégorie</h3>
            <p><?php echo admin_is_restricted_admin_account()
                ? 'Aucune catégorie n’est encore définie.'
                : 'Créez une première catégorie pour organiser vos pièces et le stock.'; ?></p>
            <?php if (!admin_is_restricted_admin_account()): ?>
            <a href="../categories/ajouter.php" class="btn btn-primary">
                <?php echo fpl_icone('plus', 14); ?> Ajouter une catégorie
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="stock-cat-table">
                <thead>
                    <tr>
                        <th class="col-thumb">Visuel</th>
                        <th>Catégorie</th>
                        <th class="col-num">Pièces</th>
                        <?php if ($stock_sous_cat_ok): ?>
                        <th class="col-num">Sous-cat.</th>
                        <?php endif; ?>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $upload_base = $stock_upload_base;
                    foreach ($categories as $categorie):
                        $nb_sous_cat = $sous_cat_count_by_categorie[(int) ($categorie['id'] ?? 0)] ?? 0;
                        include __DIR__ . '/includes/ligne_categorie_table.php';
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- .page-produits-admin -->

    <?php include __DIR__ . '/../../includes/admin_stock_alerte_popup.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function goToRow(row) {
                if (!row) { return; }
                var href = row.getAttribute('data-href');
                if (href) { window.location.href = href; }
            }
            document.addEventListener('click', function (event) {
                var row = event.target.closest('.stock-cat-table__row--linkable');
                if (!row || event.target.closest('.stock-cat-table__action')) { return; }
                goToRow(row);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') { return; }
                var row = event.target.closest('.stock-cat-table__row--linkable');
                if (!row || event.target.closest('.stock-cat-table__action')) { return; }
                event.preventDefault();
                goToRow(row);
            });
        });
    </script>
</body>

</html>
