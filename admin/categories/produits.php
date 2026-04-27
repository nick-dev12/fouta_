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
    <title>Produits de <?php echo htmlspecialchars($categorie['nom']); ?> - Administration</title>
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
                    <?php echo htmlspecialchars($categorie['nom']); ?>
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
                    <p class="section-title-hint">Catalogue filtré sur « <?php echo htmlspecialchars($categorie['nom']); ?> »</p>
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
                    $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                    ?>
                    <div class="produit-card produit-card--dashboard">
                        <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                        <div class="produit-card-media">
                            <img src="../../upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                                alt="<?php echo htmlspecialchars($produit['nom']); ?>"
                                class="produit-card-image"
                                onerror="this.src='../../image/produit1.jpg'">
                        </div>
                        <div class="produit-card-body">
                            <h3 class="produit-card-nom"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                            <p class="produit-card-categorie">
                                <i class="fas fa-tag" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($categorie['nom']); ?>
                            </p>
                            <p class="produit-card-prix">
                                <span class="prix-montant"><?php echo number_format($produit['prix'], 0, ',', ' '); ?></span>
                                <span class="prix-unite">FCFA</span>
                                <?php if ($produit['prix_promotion']): ?>
                                <span class="prix-promo-inline">Promo <?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA</span>
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock">
                                <i class="fas fa-cubes" aria-hidden="true"></i>
                                Stock <span class="stock-value"><?php echo (int) $produit['stock']; ?></span>
                            </p>
                            <div class="produit-card-actions produit-card-actions--triple">
                                <a href="../produits/ajuster-stock.php?id=<?php echo (int) $produit['id']; ?>"
                                    class="btn-card btn-stock" title="Ajuster le stock">
                                    <i class="fas fa-boxes-stacked"></i> Stock
                                </a>
                                <a href="../produits/modifier.php?id=<?php echo (int) $produit['id']; ?>" class="btn-card btn-edit">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="../produits/supprimer.php?id=<?php echo (int) $produit['id']; ?>"
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

    <?php include '../includes/footer.php'; ?>
