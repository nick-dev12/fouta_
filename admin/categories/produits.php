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

    <div class="content-header">
        <h1>
            <i class="fas fa-box"></i> Produits de la catégorie: <?php echo htmlspecialchars($categorie['nom']); ?>
        </h1>
        <div class="header-actions">
            <a href="../stock/index.php" class="btn-back" style="margin-right: 10px;">
                <i class="fas fa-arrow-left"></i> Retour au stock
            </a>
            <a href="../produits/ajouter.php?categorie_id=<?php echo (int) $categorie_id; ?>" class="btn-primary">
                <i class="fas fa-plus"></i> Ajouter un produit
            </a>
        </div>
    </div>

    <section class="produits-section">
        <div class="section-title">
            <h2>
                <i class="fas fa-box"></i>
                Produits de "<?php echo htmlspecialchars($categorie['nom']); ?>"
                (<?php echo count($produits); ?>)
            </h2>
        </div>

        <?php if (empty($produits)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>Aucun produit dans cette catégorie pour le moment.</p>
                <a href="../produits/ajouter.php?categorie_id=<?php echo (int) $categorie_id; ?>" class="btn-primary"
                    style="margin-top: 20px; display: inline-block;">
                    <i class="fas fa-plus"></i> Ajouter un produit à cette catégorie
                </a>
            </div>
        <?php else: ?>
            <div class="produits-grid">
                <?php foreach ($produits as $produit): ?>
                    <div class="produit-card">
                        <?php
                        $statut_class = 'statut-actif';
                        if ($produit['statut'] == 'inactif') {
                            $statut_class = 'statut-inactif';
                        } elseif ($produit['statut'] == 'rupture_stock') {
                            $statut_class = 'statut-rupture';
                        }
                        $statut_label = ucfirst(str_replace('_', ' ', $produit['statut']));
                        ?>
                        <span class="statut-badge <?php echo $statut_class; ?>"><?php echo $statut_label; ?></span>
                        <img src="../../upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                            alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-card-image"
                            onerror="this.src='../../image/produit1.jpg'">
                        <div class="produit-card-body">
                            <h3 class="produit-card-nom"><?php echo htmlspecialchars($produit['nom']); ?></h3>
                            <p class="produit-card-categorie">
                                <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Sans catégorie'); ?>
                            </p>
                            <p class="produit-card-prix">
                                <?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                <span class="prix-unite">FCFA</span>
                                <?php if ($produit['prix_promotion']): ?>
                                    <span style="color: #c26638; font-size: 12px; margin-left: 5px;">
                                        (Promo: <?php echo number_format($produit['prix_promotion'], 0, ',', ' '); ?> FCFA)
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="produit-card-stock">
                                Stock: <span class="stock-value"><?php echo $produit['stock']; ?></span>

                            </p>
                            <div class="produit-card-actions">
                                <a href="../produits/ajuster-stock.php?id=<?php echo $produit['id']; ?>"
                                    class="btn-card btn-stock" title="Ajuster le stock">
                                    <i class="fas fa-boxes-stacked"></i> Stock
                                </a>
                                <a href="../produits/modifier.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-edit">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <a href="../produits/supprimer.php?id=<?php echo $produit['id']; ?>" class="btn-card btn-delete"
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

    <?php include '../includes/footer.php'; ?>