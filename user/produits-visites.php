<?php
/**
 * Page des produits visités
 * Design identique à la page principale (index/produits)
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

require_once __DIR__ . '/../models/model_visites.php';
$produits_visites = get_produits_visites_by_user($_SESSION['user_id'], 50);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Produits Visités - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .produits-visites-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .produits-visites-header {
            background: var(--couleur-dominante);
            padding: 30px 20px;
            text-align: center;
            color: var(--texte-clair);
            margin-bottom: 30px;
            border-radius: 12px;
        }

        .produits-visites-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .produits-visites-header p {
            font-size: 15px;
            opacity: 0.95;
        }

        .produits-visites .produit_vedetes {
            margin: 0;
        }

        .produits-visites .carousel {
            position: relative;
        }

        .produits-visites .date-visite-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            z-index: 5;
        }

        .produits-visites .date-visite-badge i {
            margin-right: 4px;
        }
    </style>
</head>

<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="produits-visites-page">
        <div class="produits-visites-header">
             <h1><i class="fas fa-eye"></i> Produits Visités</h1>
            <p>Historique de vos consultations (<?php echo count($produits_visites); ?>
                produit<?php echo count($produits_visites) > 1 ? 's' : ''; ?>)</p>
        </div>

        <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
        <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(32, 197, 199, 0.15); border-left: 4px solid var(--turquoise); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(229, 72, 138, 0.15); border-left: 4px solid var(--couleur-dominante); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        <section class="content-section produits-visites">
            <?php if (empty($produits_visites)): ?>
                <div class="empty-state">
                    <i class="fas fa-eye-slash"></i>
                    <h3>Aucun produit visité</h3>
                    <p>Vous n'avez pas encore consulté de produits. Vos consultations apparaîtront ici.</p>
                    <a href="/produits.php" class="btn-primary">
                        <i class="fas fa-box"></i> Découvrir nos produits
                    </a>
                </div>
            <?php else: ?>
                <section class="produit_vedetes">
                    <article class="articles carousel11">
                        <?php foreach ($produits_visites as $produit): ?>
                            <?php
                            $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                                ? $produit['prix_promotion']
                                : $produit['prix'];
                            $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                            ?>
                            <div class="carousel" data-produit-id="<?php echo $produit['id']; ?>">
                                <a href="/produit.php?id=<?php echo $produit['id']; ?>" class="product-card-link">
                                    <span class="date-visite-badge"
                                        title="Consulté le <?php echo date('d/m/Y à H:i', strtotime($produit['date_visite'])); ?>">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d/m/Y', strtotime($produit['date_visite'])); ?>
                                    </span>
                                    <div class="image-wrapper">
                                        <img src="/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? 'produit1.jpg'); ?>"
                                            alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                            onerror="this.src='/image/produit1.jpg'">
                                    </div>
                                    <div class="produit-content">
                                        <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                                        <?php if (!empty($produit['categorie_nom'])): ?>
                                            <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                                        <?php endif; ?>
                                        <p class="prix">
                                            <?php if ($has_promotion): ?>
                                                <span class="span2"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                                    FCFA</span>
                                                <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                                    FCFA</span>
                                            <?php else: ?>
                                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?><span class="span1">
                                                    FCFA</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($produit['stock'])): ?>
                                            <p class="produit-card-stock-info">
                                                <strong>Stock:</strong> <?php echo $produit['stock']; ?>
                                                <?php if (!empty($produit['poids'])): ?>
                                                    (<?php echo htmlspecialchars($produit['poids']); ?>)
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                    <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/user/produits-visites.php'); ?>">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </article>
                </section>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'includes/user_footer.php'; ?>
</body>

</html>