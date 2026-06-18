<?php
/**
 * Export catalogue produits — aperçu par période + filtres.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$has_marque_filtre = produits_has_column('marque_id');
$has_fournisseur_filtre = produits_has_column('fournisseur_id');
$marques_filtre = [];
$fournisseurs_filtre = [];
$categories = get_all_categories();

if ($has_marque_filtre) {
    require_once __DIR__ . '/../../models/model_marques.php';
    if (marques_table_ok()) {
        $marques_filtre = get_all_marques_ordered_by_nom();
    }
}
if ($has_fournisseur_filtre) {
    require_once __DIR__ . '/../../models/model_fournisseurs.php';
    $fournisseurs_filtre = get_all_fournisseurs_ordered_by_nom();
}

$today = date('Y-m-d');
$date_debut = trim($_GET['date_debut'] ?? $today);
$date_fin = trim($_GET['date_fin'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
    $date_debut = $today;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
    $date_fin = $today;
}

$mode = isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'tous';
if (!in_array($mode, ['complet', 'ajout', 'modification', 'tous'], true)) {
    $mode = 'tous';
}

$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;

$total_export = count_admin_produits_export_catalogue($date_debut, $date_fin, $mode, $recherche, $categorie_id, $marque_id, $fournisseur_id);
$produits_export = get_admin_produits_export_catalogue($date_debut, $date_fin, $mode, $recherche, $categorie_id, $marque_id, $fournisseur_id, 500);
$export_truncated = $total_export > count($produits_export);

$pdf_query = http_build_query([
    'date_debut' => $date_debut,
    'date_fin' => $date_fin,
    'mode' => $mode,
    'recherche' => $recherche,
    'categorie_id' => $categorie_id,
    'marque_id' => $marque_id,
    'fournisseur_id' => $fournisseur_id,
]);

$filtres_form_classes = 'admin-filters-bar page-produits-export-filters';
if (!empty($marques_filtre)) {
    $filtres_form_classes .= ' page-produits-export-filters--has-marque';
}
if (!empty($fournisseurs_filtre)) {
    $filtres_form_classes .= ' page-produits-export-filters--has-fournisseur';
}

$mode_labels = export_catalogue_pdf_mode_labels();
$export_use_async_pdf = $total_export >= EXPORT_CATALOGUE_ASYNC_MIN;
$pdf_link_attrs = $export_use_async_pdf
    ? ' data-export-catalogue-async data-export-query="' . htmlspecialchars($pdf_query, ENT_QUOTES, 'UTF-8') . '"'
    : ' data-admin-pdf-download';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export catalogue produits - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-index.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-export.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin page-produits-export" data-export-total="<?php echo (int) $total_export; ?>"
        data-export-async-min="<?php echo (int) EXPORT_CATALOGUE_ASYNC_MIN; ?>">
        <div class="content-header dashboard-hero page-produits-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Catalogue boutique</p>
                <h1><i class="fas fa-file-pdf" aria-hidden="true"></i> Export catalogue PDF</h1>

                <div class="page-produits-export-progress" id="exportCataloguePdfProgress" hidden
                    role="status" aria-live="polite" aria-busy="false">
                    <div class="page-produits-export-progress__row">
                        <p class="page-produits-export-progress__status" id="exportCataloguePdfStatus">
                            Initialisation…
                        </p>
                        <span class="page-produits-export-progress__percent" id="exportCataloguePdfPercent">0&nbsp;%</span>
                    </div>
                    <div class="page-produits-export-progress__track">
                        <div class="page-produits-export-progress__bar" id="exportCataloguePdfBar" style="width:0%"></div>
                    </div>
                    <div class="page-produits-export-progress__actions">
                        <button type="button" class="btn-secondary page-produits-export-progress__cancel"
                            id="exportCataloguePdfCancel">
                            <i class="fas fa-times" aria-hidden="true"></i> Annuler
                        </button>
                    </div>
                </div>

                <div class="page-produits-hero__actions" id="exportCataloguePdfHeroActions">

                    <a href="index.php" class="btn-secondary page-produits-hero__btn">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste
                    </a>
                    <?php if ($total_export > 0): ?>
                    <a href="export-catalogue-pdf.php?<?php echo htmlspecialchars($pdf_query, ENT_QUOTES, 'UTF-8'); ?>"
                        class="btn-primary page-produits-hero__btn page-produits-export-pdf-btn"
                        <?php echo $pdf_link_attrs; ?>>
                        <i class="fas fa-download" aria-hidden="true"></i> Télécharger le PDF
                        (<?php echo (int) $total_export; ?>)
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <section class="produits-section page-produits-section page-produits-export-section"
            aria-labelledby="export-section-heading">
            <div class="section-title page-produits-section__head">
                <h2 id="export-section-heading"><i class="fas fa-filter" aria-hidden="true"></i> Filtres d’export
                    <span class="page-produits-count" aria-live="polite">(<?php echo (int) $total_export; ?>)</span>
                </h2>
            </div>

            <form method="GET" action=""
                class="<?php echo htmlspecialchars($filtres_form_classes, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="admin-filter-field page-produits-export-filters__period">
                    <label for="date_debut">Du</label>
                    <input type="date" id="date_debut" name="date_debut"
                        value="<?php echo htmlspecialchars($date_debut, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="admin-filter-field page-produits-export-filters__period">
                    <label for="date_fin">Au</label>
                    <input type="date" id="date_fin" name="date_fin"
                        value="<?php echo htmlspecialchars($date_fin, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="admin-filter-field page-produits-export-filters__mode">
                    <label for="mode">Type</label>
                    <select id="mode" name="mode">
                        <?php foreach ($mode_labels as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $mode === $k ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-filter-field page-produits-export-filters__search">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche" placeholder="Nom, description, FPL, fournisseur…"
                        value="<?php echo htmlspecialchars($recherche, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off"
                        inputmode="search">
                </div>
                <div class="admin-filter-field page-produits-export-filters__categorie">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo (int) $categorie['id']; ?>"
                            <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($marques_filtre)): ?>
                <div class="admin-filter-field page-produits-export-filters__marque">
                    <label for="marque_id">Marque</label>
                    <select id="marque_id" name="marque_id">
                        <option value="0">Toutes les marques</option>
                        <?php foreach ($marques_filtre as $marque): ?>
                        <option value="<?php echo (int) $marque['id']; ?>"
                            <?php echo $marque_id === (int) $marque['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($marque['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($fournisseurs_filtre)): ?>
                <div class="admin-filter-field page-produits-export-filters__fournisseur">
                    <label for="fournisseur_id">Fournisseur</label>
                    <select id="fournisseur_id" name="fournisseur_id">
                        <option value="0">Tous les fournisseurs</option>
                        <?php foreach ($fournisseurs_filtre as $fournisseur): ?>
                        <option value="<?php echo (int) $fournisseur['id']; ?>"
                            <?php echo $fournisseur_id === (int) $fournisseur['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fournisseur['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="admin-filter-actions page-produits-export-filters__actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Afficher
                    </button>
                    <a href="export-catalogue.php" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Aujourd’hui
                    </a>
                    <?php if ($total_export > 0): ?>
                    <a href="export-catalogue-pdf.php?<?php echo htmlspecialchars($pdf_query, ENT_QUOTES, 'UTF-8'); ?>"
                        class="btn-primary btn-export-pdf-inline page-produits-export-pdf-btn"
                        <?php echo $pdf_link_attrs; ?>>
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($export_truncated): ?>
            <p class="page-produits-export-truncated" role="status">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                Affichage limité à <?php echo count($produits_export); ?> produits sur
                <?php echo (int) $total_export; ?> — le PDF inclura tous les
                résultats<?php echo $export_use_async_pdf ? ' (génération en arrière-plan)' : ''; ?> (max
                <?php echo (int) EXPORT_CATALOGUE_PDF_MAX; ?>).
            </p>
            <?php endif; ?>

            <?php if ($total_export === 0): ?>
            <div class="empty-state page-produits-empty">
                <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-inbox"></i></div>
                <p class="page-produits-empty__title">Aucun produit pour cette période</p>
                <p class="page-produits-empty__hint">Modifiez les dates, le type, la recherche ou les filtres catégorie
                    / marque / fournisseur.</p>
            </div>
            <?php else: ?>
            <div id="page-produits-export-wrap">
                <ul class="produits-grid page-produits-grid" id="page-produits-export-grid" role="list">
                    <?php foreach ($produits_export as $produit): ?>
                    <?php include __DIR__ . '/includes/carte_produit_liste.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include '../includes/footer.php'; ?>

    <div class="delete-confirm-overlay" id="deleteConfirmOverlay"></div>
    <div class="delete-confirm-modal" id="deleteConfirmModal" role="dialog" aria-modal="true"
        aria-labelledby="deleteConfirmTitle">
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
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--cancel"
                id="deleteConfirmCancel">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button type="button" class="delete-confirm-modal__btn delete-confirm-modal__btn--confirm"
                id="deleteConfirmConfirm">
                <i class="fas fa-trash"></i> Confirmer
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(event) {
            var card = event.target.closest('.page-produits-section .produit-card-linkable');
            if (!card) {
                return;
            }
            if (event.target.closest('a, button, input, select, textarea, form')) {
                return;
            }
            var href = card.getAttribute('data-href');
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

        function positionModal(triggerElement) {
            var rect = triggerElement.getBoundingClientRect();
            var modalWidth = deleteModal.offsetWidth || 360;
            var modalHeight = deleteModal.offsetHeight || 300;
            var left = rect.left + (rect.width / 2) - (modalWidth / 2);
            var top = rect.top + rect.height + 10;
            if (left < 10) left = 10;
            if (left + modalWidth > window.innerWidth - 10) {
                left = window.innerWidth - modalWidth - 10;
            }
            if (top + modalHeight > window.innerHeight - 10) {
                top = rect.top - modalHeight - 10;
            }
            if (top < 10) top = 10;
            deleteModal.style.left = left + 'px';
            deleteModal.style.top = top + 'px';
        }

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

        document.addEventListener('click', function(event) {
            var link = event.target.closest('.page-produits-section a[data-delete-confirm="true"]');
            if (!link) {
                return;
            }
            event.preventDefault();
            positionModal(link);
            showModal(link);
        });

        deleteCancel.addEventListener('click', hideModal);
        deleteOverlay.addEventListener('click', hideModal);
        deleteConfirm.addEventListener('click', function() {
            if (currentDeleteLink) {
                window.location.href = currentDeleteLink.href;
            }
        });
    });
    </script>
    <script src="/js/admin-export-catalogue-pdf.js<?php echo asset_version_query(); ?>"></script>
</body>

</html>