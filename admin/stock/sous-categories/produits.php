<?php
/**
 * Produits rattachés à une sous-catégorie
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/require_access.php';

$sous_categorie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($sous_categorie_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../../models/model_sous_categories.php';
require_once __DIR__ . '/../../../models/model_categories.php';
require_once __DIR__ . '/../../../models/model_produits.php';
require_once __DIR__ . '/../../includes/produit_formulaire_champs.php';

$pf_col_img = pf_liste_col_image_visible();
$pf_col_cat = pf_liste_col_categorie_visible();
$pf_col_prix = pf_liste_col_prix_visible();
$pf_col_stock = pf_liste_col_stock_visible();
$pf_col_statut = pf_liste_col_statut_visible();

if (!produits_has_column('sous_categorie_id') || !sous_categories_table_ok()) {
    header('Location: index.php');
    exit;
}

$sous = get_sous_categorie_by_id($sous_categorie_id);
if (!$sous) {
    header('Location: index.php');
    exit;
}

$categorie = get_categorie_by_id((int) $sous['categorie_id']);
$categorie_nom = $categorie ? (string) $categorie['nom'] : '—';
$produits = get_produits_by_sous_categorie_id($sous_categorie_id);

$success_message = '';
if (!empty($_SESSION['success_message'])) {
    $success_message = (string) $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

require_once __DIR__ . '/../../../includes/site_url.php';
require_once __DIR__ . '/../../../includes/fpl_ui.php';
$produits_upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
$produits_path_prefix = '../../produits/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits — <?php echo htmlspecialchars((string) ($sous['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> — Admin</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="page-produits-admin">
        <div class="content-header dashboard-hero page-produits-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Sous-catégorie · <?php echo htmlspecialchars($categorie_nom, ENT_QUOTES, 'UTF-8'); ?></p>
                <h1>
                    <i class="fas fa-sitemap" aria-hidden="true"></i>
                    <?php echo htmlspecialchars((string) ($sous['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo count($produits); ?> produit<?php echo count($produits) > 1 ? 's' : ''; ?>
                    classé<?php echo count($produits) > 1 ? 's' : ''; ?> dans cette sous-catégorie.
                </p>
            </div>
            <div class="header-actions header-actions--categorie-produits">
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Sous-catégories
                </a>
                <a href="../index.php" class="btn-back">
                    <i class="fas fa-boxes-stacked"></i> Stock
                </a>
                <a href="../../produits/ajouter.php?categorie_id=<?php echo (int) $sous['categorie_id']; ?>&amp;sous_categorie_id=<?php echo (int) $sous_categorie_id; ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </a>
            </div>
        </div>

        <section class="produits-section page-produits-section" aria-labelledby="sc-prod-heading">
            <div class="section-title section-title--dashboard">
                <div>
                    <h2 id="sc-prod-heading">
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Produits
                    </h2>
                    <p class="section-title-hint">Filtré sur « <?php echo htmlspecialchars((string) ($sous['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> »</p>
                </div>
            </div>

            <?php if (empty($produits)): ?>
                <div class="empty-state page-produits-empty">
                    <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-box-open"></i></div>
                    <p class="page-produits-empty__title">Aucun produit dans cette sous-catégorie</p>
                    <a href="../../produits/ajouter.php?categorie_id=<?php echo (int) $sous['categorie_id']; ?>&amp;sous_categorie_id=<?php echo (int) $sous_categorie_id; ?>" class="btn-primary page-produits-empty__cta">
                        <i class="fas fa-plus"></i> Ajouter un produit
                    </a>
                </div>
            <?php else: ?>
                <div class="page-produits-table-wrap">
                    <table class="page-produits-table">
                        <thead>
                            <tr>
                                <?php if ($pf_col_img): ?><th class="col-thumb">Visuel</th><?php endif; ?>
                                <th>Produit</th>
                                <?php if ($pf_col_cat): ?><th>Catégorie</th><?php endif; ?>
                                <?php if ($pf_col_prix): ?><th class="col-num">Prix</th><?php endif; ?>
                                <?php if ($pf_col_stock): ?><th class="col-num">Stock</th><?php endif; ?>
                                <?php if ($pf_col_statut): ?><th>Statut</th><?php endif; ?>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="page-sous-cat-produits-table-body">
                            <?php
                            $upload_base = $produits_upload_base;
                            $hide_categorie_col = !$pf_col_cat;
                            foreach ($produits as $produit):
                                include __DIR__ . '/../../produits/includes/ligne_produit_table.php';
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php
    if ($success_message !== '') {
        $flash_success_message = $success_message;
        include __DIR__ . '/../../includes/flash_success_popup.php';
    }
    ?>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script src="<?php echo htmlspecialchars(fpl_script_src('admin-produits-gallery-lightbox.js'), ENT_QUOTES, 'UTF-8'); ?><?php echo asset_version_query(); ?>"></script>

    <div class="delete-confirm-overlay" id="deleteConfirmOverlay"></div>
    <div class="delete-confirm-modal" id="deleteConfirmModal" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
        <div class="delete-confirm-modal__icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="delete-confirm-modal__title" id="deleteConfirmTitle">Confirmer la suppression</h3>
        <p class="delete-confirm-modal__text">Êtes-vous sûr de vouloir supprimer ce produit ?</p>
        <div class="delete-confirm-modal__product" id="deleteConfirmProduct"></div>
        <p class="delete-confirm-modal__warning">
            <i class="fas fa-info-circle"></i> Cette action est irréversible
        </p>
        <div class="delete-confirm-modal__actions">
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--cancel" id="deleteConfirmCancel">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--confirm" id="deleteConfirmConfirm">
                <i class="fas fa-trash"></i> Confirmer
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                var row = event.target.closest('.page-produits-table__row--linkable');
                if (!row) {
                    return;
                }
                if (event.target.closest('.page-produits-table__action, .page-produits-table__thumb-btn, a[data-delete-confirm="true"]')) {
                    return;
                }
                var href = row.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                var row = event.target.closest('.page-produits-table__row--linkable');
                if (!row || event.target.closest('.page-produits-table__thumb-btn, .page-produits-table__action')) {
                    return;
                }
                event.preventDefault();
                var href = row.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });

            var deleteOverlay = document.getElementById('deleteConfirmOverlay');
            var deleteModal = document.getElementById('deleteConfirmModal');
            var deleteProduct = document.getElementById('deleteConfirmProduct');
            var deleteCancel = document.getElementById('deleteConfirmCancel');
            var deleteConfirm = document.getElementById('deleteConfirmConfirm');
            var currentDeleteLink = null;

            function showModal(link) {
                currentDeleteLink = link;
                deleteProduct.textContent = link.getAttribute('data-delete-name') || 'ce produit';
                deleteOverlay.classList.add('visible');
                deleteModal.classList.add('visible', 'animated');
                deleteCancel.focus();
            }

            function hideModal() {
                deleteOverlay.classList.remove('visible');
                deleteModal.classList.remove('visible', 'animated');
                currentDeleteLink = null;
            }

            document.querySelectorAll('a[data-delete-confirm="true"]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    showModal(link);
                });
            });

            deleteCancel.addEventListener('click', hideModal);
            deleteOverlay.addEventListener('click', hideModal);
            deleteConfirm.addEventListener('click', function () {
                if (currentDeleteLink) {
                    window.location.href = currentDeleteLink.href;
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && deleteModal.classList.contains('visible')) {
                    hideModal();
                }
            });
        });
    </script>
</body>
</html>
