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

$produits = get_all_produits();
$categories = get_all_categories();
$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;

if (!empty($produits)) {
    $produits = array_values(array_filter($produits, function ($produit) use ($categorie_id, $marque_id, $fournisseur_id) {
        return produit_admin_liste_pass_filtres($produit, '', $categorie_id, $marque_id, $fournisseur_id);
    }));
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success page-produits-flash" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <section class="produits-section page-produits-section" aria-labelledby="produits-section-heading">
            <div class="section-title page-produits-section__head">
                <h2 id="produits-section-heading"><i class="fas fa-th-large" aria-hidden="true"></i> Tous les produits
                    <span class="page-produits-count" id="page-produits-count" aria-live="polite">(<?php echo count($produits); ?>)</span>
                </h2>
            </div>

            <form method="GET" action="" class="<?php echo htmlspecialchars($filtres_form_classes, ENT_QUOTES, 'UTF-8'); ?>"
                data-produits-live-search-form
                data-live-grid="page-produits-grid"
                data-live-count="page-produits-count"
                data-live-empty="page-produits-live-empty">
                <div class="admin-filter-field page-produits-filters__search">
                    <label for="recherche">Recherche</label>
                    <input type="text" id="recherche" name="recherche"
                        placeholder="Nom, description… — filtre en direct"
                        value="<?php echo htmlspecialchars($recherche); ?>" autocomplete="off" inputmode="search"
                        data-live-search-input>
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

            <?php if (empty($produits)): ?>
                <div class="empty-state page-produits-empty">
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
            <?php else: ?>
                <ul class="produits-grid page-produits-grid" id="page-produits-grid" role="list"
                    data-total="<?php echo count($produits); ?>">
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $pcm_search_blob = produit_admin_liste_search_blob($produit);
                        $pcm_nom_norm = produits_recherche_normalize((string) ($produit['nom'] ?? ''));
                        $pcm_ident = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
                        ?>
                        <li class="produit-card produit-card--admin produit-card-linkable"
                            data-href="ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>" role="listitem"
                            data-produit-search="<?php echo htmlspecialchars($pcm_search_blob, ENT_QUOTES, 'UTF-8'); ?>"
                            data-produit-nom="<?php echo htmlspecialchars($pcm_nom_norm, ENT_QUOTES, 'UTF-8'); ?>"
                            data-produit-ident="<?php echo htmlspecialchars($pcm_ident, ENT_QUOTES, 'UTF-8'); ?>"
                            data-categorie-id="<?php echo (int) ($produit['categorie_id'] ?? 0); ?>"
                            data-marque-id="<?php echo (int) ($produit['marque_id'] ?? 0); ?>"
                            data-fournisseur-id="<?php echo (int) ($produit['fournisseur_id'] ?? 0); ?>">
                            <?php
                            $statut_class = 'statut-actif';
                            if ($produit['statut'] == 'inactif') {
                                $statut_class = 'statut-inactif';
                            } elseif ($produit['statut'] == 'rupture_stock') {
                                $statut_class = 'statut-rupture';
                            }
                            $statut_label = ucfirst(str_replace('_', ' ', (string) ($produit['statut'] ?? '')));
                            ?>
                            <span
                                class="statut-badge produit-card__statut <?php echo $statut_class; ?>"><?php echo htmlspecialchars($statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="produit-card-media">
                                <?php
                                $img_principale = '';
                                if (!empty($produit['image_principale'])) {
                                    $img_principale = trim((string) $produit['image_principale']);
                                }
                                if ($img_principale !== ''):
                                    ?>
                                    <img src="/upload/<?php echo htmlspecialchars($img_principale, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        class="produit-card-image"
                                        onerror="this.onerror=null;var w=document.createElement('div');w.className='produit-card-media-placeholder';w.setAttribute('role','img');w.setAttribute('aria-label','Sans image');w.innerHTML='<i class=\'fas fa-truck\' aria-hidden=\'true\'></i>';this.replaceWith(w);"
                                        width="300" height="300" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <div class="produit-card-media-placeholder" role="img" aria-label="Pas d'image">
                                        <i class="fas fa-truck" aria-hidden="true"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="produit-card-body">
                                <h3 class="produit-card-nom"><?php echo produits_card_heading_inner_html($produit, 20); ?></h3>
                                <?php
                                $pcm_four = function_exists('produits_fournisseur_nom_affichage')
                                    ? produits_fournisseur_nom_affichage($produit) : '';
                                ?>
                                <?php if ($pcm_four !== ''): ?>
                                    <p class="produit-card-fournisseur"><i class="fas fa-truck-field" aria-hidden="true"></i>
                                        <?php echo htmlspecialchars($pcm_four, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <p class="produit-card-categorie">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) ($produit['categorie_nom'] ?? 'Sans catégorie'), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <p class="produit-card-prix">
                                    <?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?>
                                    <span class="prix-unite">FCFA</span>
                                    <?php if (!empty($produit['prix_promotion'])): ?>
                                        <span class="prix-promo">
                                            (Promo: <?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?>
                                            FCFA)
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-stock">
                                    <i class="fas fa-cubes" aria-hidden="true"></i>
                                    <span class="produit-card-stock__label">Stock</span>
                                    <span class="stock-value"><?php echo $produit['stock']; ?></span>
                                </p>
                                <div class="produit-card-actions produit-card-actions--admin">
                                    <a href="modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                        <i class="fas fa-edit" aria-hidden="true"></i> Modifier
                                    </a>
                                    <a href="supprimer.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-delete"
                                        data-delete-confirm="true"
                                        data-delete-name="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-trash" aria-hidden="true"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="empty-state page-produits-empty page-produits-empty--live" id="page-produits-live-empty" hidden>
                    <div class="page-produits-empty__icon" aria-hidden="true"><i class="fas fa-search"></i></div>
                    <p class="page-produits-empty__title">Aucun produit ne correspond</p>
                    <p class="page-produits-empty__hint">Modifiez les mots de recherche ou les filtres pour élargir les résultats.</p>
                </div>
            <?php endif; ?>
        </section>

    </div><!-- .page-produits-admin -->

    <?php include '../includes/footer.php'; ?>

    <script src="/js/admin-produits-live-search.js<?php echo asset_version_query(); ?>"></script>

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
            // Navigation par clic sur les cards
            document.querySelectorAll('.produit-card-linkable').forEach(function (card) {
                card.addEventListener('click', function (event) {
                    if (event.target.closest('a, button, input, select, textarea, form')) {
                        return;
                    }
                    var href = card.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
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

            // Gestion des clics sur les liens de suppression
            document.querySelectorAll('a[data-delete-confirm="true"]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    positionModal(link);
                    showModal(link);
                });
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