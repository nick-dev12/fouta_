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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits — <?php echo htmlspecialchars((string) ($sous['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> — Admin</title>
    <?php require_once __DIR__ . '/../../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <div class="contents-container dashboard-page page-categorie-produits">
        <div class="content-header dashboard-hero">
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

        <section class="produits-section produits-section--dashboard" aria-labelledby="sc-prod-heading">
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
                <div class="empty-state page-categorie-produits-empty">
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <p>Aucun produit dans cette sous-catégorie.</p>
                    <a href="../../produits/ajouter.php?categorie_id=<?php echo (int) $sous['categorie_id']; ?>&amp;sous_categorie_id=<?php echo (int) $sous_categorie_id; ?>" class="btn-primary">
                        <i class="fas fa-plus"></i> Ajouter un produit
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
                        <div class="produit-card produit-card--dashboard">
                            <span class="statut-badge <?php echo $statut_class; ?>"><?php echo htmlspecialchars((string) $statut_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="produit-card-media">
                                <?php
                                $img_principale = '';
                                if (!empty($produit['image_principale'])) {
                                    $img_principale = trim((string) $produit['image_principale']);
                                }
                                if ($img_principale !== ''):
                                ?>
                                <img src="../../../upload/<?php echo htmlspecialchars($img_principale, ENT_QUOTES, 'UTF-8'); ?>"
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
                                <h3 class="produit-card-nom"><?php echo htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="produit-card-categorie">
                                    <i class="fas fa-tag" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($categorie_nom, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <p class="produit-card-prix">
                                    <span class="prix-montant"><?php echo number_format((float) ($produit['prix'] ?? 0), 0, ',', ' '); ?></span>
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
                                <div class="produit-card-actions produit-card-actions--triple">
                                    <a href="../../produits/ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>"
                                        class="btn-card btn-stock" title="Ajuster le stock">
                                        <i class="fas fa-boxes-stacked"></i> Stock
                                    </a>
                                    <a href="../../produits/modifier.php?id=<?php echo (int) $produit['id']; ?>"
                                        class="btn-card btn-edit">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="../../produits/supprimer.php?id=<?php echo (int) $produit['id']; ?>"
                                        class="btn-card btn-delete"
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
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
        include __DIR__ . '/../../includes/flash_success_popup.php';
    }
    ?>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
