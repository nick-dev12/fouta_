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

// Récupérer les produits de cette catégorie
require_once __DIR__ . '/../../models/model_produits.php';
$produits = get_produits_by_categorie($categorie_id);
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
                <p class="dashboard-subtitle">
                    <?php echo count($produits); ?> produit<?php echo count($produits) > 1 ? 's' : ''; ?>
                    dans cette catégorie — ajustement de stock, modification ou suppression.
                </p>
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

        <section class="produits-section produits-section--dashboard" aria-labelledby="cat-produits-heading">
            <div class="section-title section-title--dashboard">
                <div>
                    <h2 id="cat-produits-heading">
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Produits
                    </h2>
                    <p class="section-title-hint">Catalogue filtré sur «
                        <?php echo htmlspecialchars((string) ($categorie['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> »</p>
                </div>
            </div>

            <?php if (empty($produits)): ?>
                <div class="empty-state page-categorie-produits-empty">
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <p>Aucun produit dans cette catégorie pour le moment.</p>
                    <a href="../produits/ajouter.php?categorie_id=<?php echo (int) $categorie_id; ?>" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter un produit à cette catégorie
                    </a>
                </div>
            <?php else: ?>
                <div class="produits-grid">
                    <?php foreach ($produits as $produit): ?>
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', (string) ($produit['statut'] ?? '')));
                        ?>
                        <div class="produit-card produit-card--dashboard produit-card-linkable" data-href="../produits/ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>">
                            <span class="statut-badge <?php echo $statut_class; ?>"><?php echo htmlspecialchars((string) $statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="produit-card-media">
                                <?php
                                $img_principale = '';
                                if (!empty($produit['image_principale'])) {
                                    $img_principale = trim((string) $produit['image_principale']);
                                }
                                if ($img_principale !== ''):
                                ?>
                                <img src="../../upload/<?php echo htmlspecialchars($img_principale, ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    class="produit-card-image"
                                    onerror="this.onerror=null;var w=document.createElement('div');w.className='produit-card-media-placeholder';w.setAttribute('role','img');w.setAttribute('aria-label','Sans image');w.innerHTML='<i class=\'fas fa-truck\' aria-hidden=\'true\'></i>';this.replaceWith(w);">
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
                                <p class="produit-card-fournisseur"><i class="fas fa-truck-field" aria-hidden="true"></i> <?php echo htmlspecialchars($pcm_four, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <p class="produit-card-categorie">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) $categorie['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <p class="produit-card-prix">
                                    <span
                                        class="prix-montant"><?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?></span>
                                    <span class="prix-unite">FCFA</span>
                                    <?php if (!empty($produit['prix_promotion'])): ?>
                                        <span class="prix-promo-inline">Promo
                                            <?php echo number_format((float) $produit['prix_promotion'], 0, ',', ' '); ?> FCFA</span>
                                    <?php endif; ?>
                                </p>
                                <p class="produit-card-stock">
                                    <i class="fas fa-cubes" aria-hidden="true"></i>
                                    Stock <span class="stock-value"><?php echo (int) $produit['stock']; ?></span>
                                </p>
                                <div class="produit-card-actions">
                                    <a href="../produits/modifier.php?id=<?php echo (int) $produit['id']; ?>"
                                        class="btn-card btn-edit">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="../produits/supprimer.php?id=<?php echo (int) $produit['id']; ?>"
                                        class="btn-card btn-delete"
                                        data-delete-confirm="true"
                                        data-delete-name="<?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php
    if ($success_message !== '') {
        $flash_success_message = $success_message;
        include __DIR__ . '/../includes/flash_success_popup.php';
    }
    ?>
    <?php include '../includes/footer.php'; ?>

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