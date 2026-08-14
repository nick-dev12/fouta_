<?php
/**
 * Page d'affichage des produits d'une catégorie
 * Programmation procédurale uniquement
 */

session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$success_message = '';
if (!empty($_SESSION['success_message'])) {
    $success_message = (string) $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Récupérer l'ID de la catégorie
$categorie_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($categorie_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer la catégorie
require_once __DIR__ . '/../../models/model_categories.php';
$categorie = get_categorie_by_id($categorie_id);

if (!$categorie) {
    header('Location: index.php');
    exit;
}

// Récupérer les produits de cette catégorie (pagination serveur)
require_once __DIR__ . '/../../models/model_produits.php';

$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;
$recherche = trim($_GET['recherche'] ?? '');

$marques_filtre = [];
$fournisseurs_filtre = [];
if (produits_has_column('marque_id')) {
    require_once __DIR__ . '/../../models/model_marques.php';
    if (marques_table_ok()) {
        $marques_filtre = get_all_marques_ordered_by_nom();
    }
}
if (produits_has_column('fournisseur_id')) {
    require_once __DIR__ . '/../../models/model_fournisseurs.php';
    $fournisseurs_filtre = get_all_fournisseurs_ordered_by_nom();
}

$per_page = ADMIN_PRODUITS_LISTE_PER_PAGE;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total_produits = count_admin_produits_liste($categorie_id, $marque_id, $fournisseur_id);
$total_pages = max(1, (int) ceil($total_produits / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$produits = get_admin_produits_liste_paginated($categorie_id, $marque_id, $fournisseur_id, $offset, $per_page);

$pagination_query_base = ['id' => $categorie_id];
if ($marque_id > 0) {
    $pagination_query_base['marque_id'] = $marque_id;
}
if ($fournisseur_id > 0) {
    $pagination_query_base['fournisseur_id'] = $fournisseur_id;
}

$categorie_filtres_classes = 'admin-filters-bar page-categorie-produits-filters';
if (!empty($marques_filtre)) {
    $categorie_filtres_classes .= ' page-categorie-produits-filters--has-marque';
}
if (!empty($fournisseurs_filtre)) {
    $categorie_filtres_classes .= ' page-categorie-produits-filters--has-fournisseur';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits de <?php echo htmlspecialchars((string) ($categorie['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="../../css/admin-categorie-produits.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container dashboard-page page-categorie-produits">
        <div class="content-header dashboard-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-eyebrow">Catégorie</p>
                <h1>
                    <i class="fas fa-box" aria-hidden="true"></i>
                    <?php echo htmlspecialchars((string) ($categorie['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </h1>
            </div>
            <div class="header-actions header-actions--categorie-produits">
                <a href="../stock/index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour au stock
                </a>
                <a href="../produits/ajouter.php?categorie_id=<?php echo (int) $categorie_id; ?>" class="btn-primary">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </a>
            </div>
        </div>

        <section class="produits-section produits-section--dashboard" aria-labelledby="cat-produits-heading"
            data-produits-index-page
            data-ajax-url="../produits/ajax_live_search.php"
            data-ajax-context="categorie"
            data-fixed-categorie-id="<?php echo (int) $categorie_id; ?>"
            data-total-catalog="<?php echo (int) $total_produits; ?>"
            data-id-main-wrap="page-categorie-main-wrap"
            data-id-main-grid="page-categorie-produits-grid"
            data-id-live-wrap="page-categorie-live-wrap"
            data-id-live-grid="page-categorie-live-grid"
            data-id-live-empty="page-categorie-produits-live-empty"
            data-id-live-meta="page-categorie-live-meta"
            data-id-pagination="page-categorie-pagination"
            data-id-count-hint="page-categorie-produits-count-hint"
            data-id-catalog-empty="page-categorie-catalog-empty">
            <div class="section-title section-title--dashboard">
                <div>
                    <h2 id="cat-produits-heading">
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Produits
                    </h2>
                    <p class="section-title-hint" id="page-categorie-produits-count-hint">Catalogue filtré sur «
                        <?php echo htmlspecialchars((string) ($categorie['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> » —
                        <?php echo (int) $total_produits; ?> produit<?php echo $total_produits > 1 ? 's' : ''; ?></p>
                </div>
            </div>

            <form method="GET" action="" class="<?php echo htmlspecialchars($categorie_filtres_classes, ENT_QUOTES, 'UTF-8'); ?>"
                data-produits-index-form>
                <input type="hidden" name="id" value="<?php echo (int) $categorie_id; ?>">
                <div class="admin-filter-field page-categorie-produits-filters__search">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche"
                        placeholder="Nom, description… — filtre en direct"
                        value="<?php echo htmlspecialchars($recherche); ?>"
                        autocomplete="off" inputmode="search"
                        data-produits-index-search>
                </div>
                <?php if (!empty($marques_filtre)): ?>
                <div class="admin-filter-field page-categorie-produits-filters__marque">
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
                <div class="admin-filter-field page-categorie-produits-filters__fournisseur">
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
                <div class="admin-filter-actions page-categorie-produits-filters__actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="produits.php?id=<?php echo (int) $categorie_id; ?>" class="btn-filter-reset">
                        <i class="fas fa-rotate-left"></i>&nbsp;Réinitialiser
                    </a>
                </div>
            </form>

            <?php if ($total_produits === 0): ?>
                <div class="empty-state page-categorie-produits-empty" id="page-categorie-catalog-empty">
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <p>Aucun produit dans cette catégorie pour le moment.</p>
                    <a href="../produits/ajouter.php?categorie_id=<?php echo (int) $categorie_id; ?>" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter un produit à cette catégorie
                    </a>
                </div>
            <?php endif; ?>

            <div id="page-categorie-main-wrap" <?php echo $total_produits === 0 ? 'hidden' : ''; ?>>
                <?php if ($total_produits > 0): ?>
                <div class="produits-grid page-categorie-produits-grid" id="page-categorie-produits-grid">
                    <?php
                    $pcm_paths = ['base' => '../produits/', 'upload' => '../../upload/'];
                    $pcm_categorie_nom = (string) ($categorie['nom'] ?? '');
                    foreach ($produits as $produit):
                        include __DIR__ . '/../includes/carte_produit_dashboard.php';
                    endforeach;
                    ?>
                </div>
                <?php
                $pagination_href_base = 'produits.php';
                $pagination_id = 'page-categorie-pagination';
                include __DIR__ . '/../includes/pagination_catalogue.php';
                ?>
                <?php else: ?>
                <div class="produits-grid page-categorie-produits-grid" id="page-categorie-produits-grid" hidden></div>
                <?php endif; ?>
            </div>

            <div id="page-categorie-live-wrap" class="page-produits-live-wrap" hidden>
                <p class="page-produits-live-meta" id="page-categorie-live-meta" aria-live="polite" hidden></p>
                <div class="produits-grid page-categorie-produits-grid page-produits-live-grid" id="page-categorie-live-grid"></div>
                <div class="empty-state page-categorie-produits-empty page-categorie-produits-empty--live" id="page-categorie-produits-live-empty" hidden>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <p>Aucun produit ne correspond à votre recherche dans cette catégorie.</p>
                </div>
            </div>
        </section>
    </div>

    <?php
    if ($success_message !== '') {
        $flash_success_message = $success_message;
        include __DIR__ . '/../includes/flash_success_popup.php';
    }
    ?>
    <?php include '../includes/footer.php'; ?>

    <script src="/js/admin-produits-index-search.js<?php echo asset_version_query(); ?>"></script>

    <!-- Modal de confirmation de suppression -->
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
                var card = event.target.closest('.page-categorie-produits .produit-card-linkable');
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

            function positionModal() {
                deleteModal.style.removeProperty('left');
                deleteModal.style.removeProperty('top');
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

            document.addEventListener('click', function (event) {
                var link = event.target.closest('.page-categorie-produits a[data-delete-confirm="true"]');
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