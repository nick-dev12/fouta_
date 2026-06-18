<?php
/**
 * Page de liste des produits
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

// Afficher le message de succès s'il existe
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer tous les produits
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';

$has_marque_filtre = produits_has_column('marque_id');
$has_fournisseur_filtre = produits_has_column('fournisseur_id');
$marques_filtre = [];
$fournisseurs_filtre = [];

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

$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;

$categories = get_all_categories();

$per_page = ADMIN_PRODUITS_LISTE_PER_PAGE;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total_produits = count_admin_produits_liste($categorie_id, $marque_id, $fournisseur_id);
$total_pages = max(1, (int) ceil($total_produits / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$produits = get_admin_produits_liste_paginated($categorie_id, $marque_id, $fournisseur_id, $offset, $per_page);

$pagination_query_base = [];
if ($categorie_id > 0) {
    $pagination_query_base['categorie_id'] = $categorie_id;
}
if ($marque_id > 0) {
    $pagination_query_base['marque_id'] = $marque_id;
}
if ($fournisseur_id > 0) {
    $pagination_query_base['fournisseur_id'] = $fournisseur_id;
}

$filtres_form_classes = 'admin-filters-bar page-produits-filters';
if (!empty($marques_filtre)) {
    $filtres_form_classes .= ' page-produits-filters--has-marque';
}
if (!empty($fournisseurs_filtre)) {
    $filtres_form_classes .= ' page-produits-filters--has-fournisseur';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Produits - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-produits-index.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">
        <div class="content-header dashboard-hero page-produits-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Catalogue boutique</p>
                <h1 id="page-produits-title"><i class="fas fa-box" aria-hidden="true"></i> Liste des produits</h1>
                <div class="page-produits-hero__actions">
                    <?php if (!admin_is_restricted_admin_account()): ?>
                        <a href="ajouter.php" class="btn-primary page-produits-hero__btn">
                            <i class="fas fa-upload" aria-hidden="true"></i> Publier un produit
                        </a>
                        <a href="export-catalogue.php" class="btn-secondary page-produits-hero__btn">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i> Export catalogue PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success page-produits-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <section class="produits-section page-produits-section" aria-labelledby="produits-section-heading"
            data-produits-index-page
            data-ajax-url="ajax_live_search.php"
            data-ajax-context="index"
            data-total-catalog="<?php echo (int) $total_produits; ?>"
            data-id-main-wrap="page-produits-main-wrap"
            data-id-main-grid="page-produits-grid"
            data-id-live-wrap="page-produits-live-wrap"
            data-id-live-grid="page-produits-live-grid"
            data-id-live-empty="page-produits-live-empty"
            data-id-live-meta="page-produits-live-meta"
            data-id-pagination="page-produits-pagination"
            data-id-count="page-produits-count"
            data-id-catalog-empty="page-produits-catalog-empty">
            <div class="section-title page-produits-section__head">
                <h2 id="produits-section-heading"><i class="fas fa-th-large" aria-hidden="true"></i> Tous les produits
                    <span class="page-produits-count" id="page-produits-count" aria-live="polite">(<?php echo (int) $total_produits; ?>)</span>
                </h2>
            </div>

            <form method="GET" action="" class="<?php echo htmlspecialchars($filtres_form_classes, ENT_QUOTES, 'UTF-8'); ?>"
                data-produits-index-form>
                <div class="admin-filter-field page-produits-filters__search">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche"
                        placeholder="Nom, description… — filtre en direct"
                        value="<?php echo htmlspecialchars($recherche); ?>" autocomplete="off" inputmode="search"
                        data-produits-index-search>
                </div>
                <div class="admin-filter-field page-produits-filters__categorie">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="0">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?php echo (int) $categorie['id']; ?>" <?php echo $categorie_id === (int) $categorie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categorie['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($marques_filtre)): ?>
                <div class="admin-filter-field page-produits-filters__marque">
                    <label for="marque_id">Marque</label>
                    <select id="marque_id" name="marque_id">
                        <option value="0">Toutes les marques</option>
                        <?php foreach ($marques_filtre as $marque): ?>
                            <option value="<?php echo (int) $marque['id']; ?>" <?php echo $marque_id === (int) $marque['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($marque['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($fournisseurs_filtre)): ?>
                <div class="admin-filter-field page-produits-filters__fournisseur">
                    <label for="fournisseur_id">Fournisseur</label>
                    <select id="fournisseur_id" name="fournisseur_id">
                        <option value="0">Tous les fournisseurs</option>
                        <?php foreach ($fournisseurs_filtre as $fournisseur): ?>
                            <option value="<?php echo (int) $fournisseur['id']; ?>" <?php echo $fournisseur_id === (int) $fournisseur['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fournisseur['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="admin-filter-actions page-produits-filters__actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="index.php" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                    </a>
                </div>
            </form>

            <?php if ($total_produits === 0): ?>
                <div class="empty-state page-produits-empty" id="page-produits-catalog-empty">
                    <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-box-open"></i></div>
                    <p class="page-produits-empty__title">Aucun produit à afficher</p>
                    <p class="page-produits-empty__hint">Élargissez la recherche, réinitialisez les filtres (catégorie, marque, fournisseur…) ou <a
                            href="index.php">tout effacer</a>.<?php echo admin_is_restricted_admin_account() ? '' : ' Vous pouvez aussi ajouter un produit.'; ?>
                    </p>
                    <?php if (!admin_is_restricted_admin_account()): ?>
                        <a href="ajouter.php" class="btn-primary page-produits-empty__cta">
                            <i class="fas fa-upload" aria-hidden="true"></i> Publier un produit
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div id="page-produits-main-wrap" <?php echo $total_produits === 0 ? 'hidden' : ''; ?>>
                <?php if ($total_produits > 0): ?>
                <ul class="produits-grid page-produits-grid" id="page-produits-grid" role="list">
                    <?php foreach ($produits as $produit): ?>
                        <?php include __DIR__ . '/includes/carte_produit_liste.php'; ?>
                    <?php endforeach; ?>
                </ul>

                <?php if ($total_pages > 1): ?>
                <nav class="page-produits-pagination" id="page-produits-pagination" aria-label="Pagination du catalogue">
                    <?php if ($page > 1): ?>
                        <?php $prev_q = array_merge($pagination_query_base, ['page' => $page - 1]); ?>
                        <a href="index.php?<?php echo htmlspecialchars(http_build_query($prev_q), ENT_QUOTES, 'UTF-8'); ?>" class="page-produits-pagination__link">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <span class="page-produits-pagination__info">
                        Page <?php echo (int) $page; ?> / <?php echo (int) $total_pages; ?>
                        <span class="page-produits-pagination__detail">(<?php echo (int) $per_page; ?> par page · <?php echo (int) $total_produits; ?> au total)</span>
                    </span>

                    <?php if ($page < $total_pages): ?>
                        <?php $next_q = array_merge($pagination_query_base, ['page' => $page + 1]); ?>
                        <a href="index.php?<?php echo htmlspecialchars(http_build_query($next_q), ENT_QUOTES, 'UTF-8'); ?>" class="page-produits-pagination__link">
                            Suivant <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
                <?php else: ?>
                <ul class="produits-grid page-produits-grid" id="page-produits-grid" role="list" hidden></ul>
                <?php endif; ?>
            </div>

            <div id="page-produits-live-wrap" class="page-produits-live-wrap" hidden>
                <p class="page-produits-live-meta" id="page-produits-live-meta" aria-live="polite" hidden></p>
                <ul class="produits-grid page-produits-grid page-produits-live-grid" id="page-produits-live-grid" role="list"></ul>
                <div class="empty-state page-produits-empty page-produits-empty--live" id="page-produits-live-empty" hidden>
                    <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-search"></i></div>
                    <p class="page-produits-empty__title">Aucun produit ne correspond</p>
                    <p class="page-produits-empty__hint">Modifiez les mots de recherche ou les filtres pour élargir les résultats.</p>
                </div>
            </div>
        </section>

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>

    <script src="/js/admin-produits-index-search.js<?php echo asset_version_query(); ?>"></script>

    <!-- Modal de confirmation de suppression -->
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
        document.addEventListener('DOMContentLoaded', function () {
            // Navigation par clic sur les cards (délégation — grille paginée + résultats live)
            document.addEventListener('click', function (event) {
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

            // Modal de confirmation de suppression
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

                // Ajuster si dépasse l'écran
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
                var productName = link.getAttribute('data-delete-name') || 'ce produit';
                deleteProduct.textContent = productName;

                deleteOverlay.classList.add('visible');
                deleteModal.classList.add('visible', 'animated');
                deleteCancel.focus();
            }

            function hideModal() {
                deleteOverlay.classList.remove('visible');
                deleteModal.classList.remove('visible', 'animated');
                currentDeleteLink = null;
            }

            // Gestion des clics sur les liens de suppression (délégation)
            document.addEventListener('click', function (event) {
                var link = event.target.closest('.page-produits-section a[data-delete-confirm="true"]');
                if (!link) {
                    return;
                }
                event.preventDefault();
                positionModal(link);
                showModal(link);
            });

            // Boutons de la modal
            deleteCancel.addEventListener('click', hideModal);
            deleteOverlay.addEventListener('click', hideModal);

            deleteConfirm.addEventListener('click', function () {
                if (currentDeleteLink) {
                    window.location.href = currentDeleteLink.href;
                }
            });

            // Fermer avec Escape
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && deleteModal.classList.contains('visible')) {
                    hideModal();
                }
            });
        });
    </script>