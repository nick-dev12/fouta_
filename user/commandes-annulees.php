<?php
/**
 * Page de liste des commandes annulées par l'utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

// Récupérer les commandes annulées de l'utilisateur
require_once __DIR__ . '/../models/model_commandes.php';

// Traitement de la recommandation (ajouter les produits au panier)
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recommander'])) {
    $commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commande_id > 0) {
        // Vérifier que la commande est annulée et appartient à l'utilisateur
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);

        if ($commande && $commande['statut'] === 'annulee') {
            // Récupérer les produits de la commande
            require_once __DIR__ . '/../models/model_panier.php';
            $produits_commande = get_commande_produits($commande_id);

            if (!empty($produits_commande)) {
                $added_count = 0;
                foreach ($produits_commande as $produit) {
                    // Vérifier si le produit existe encore et est actif
                    require_once __DIR__ . '/../models/model_produits.php';
                    $produit_info = get_produit_by_id($produit['produit_id']);

                    if ($produit_info && $produit_info['statut'] === 'actif' && $produit_info['stock'] > 0) {
                        // Vérifier si le produit est déjà dans le panier
                        $panier_existant = is_in_panier($_SESSION['user_id'], $produit['produit_id']);
                        if ($panier_existant) {
                            // Mettre à jour la quantité
                            $new_quantite = min($panier_existant['quantite'] + $produit['quantite'], $produit_info['stock']);
                            if (update_panier_quantite($panier_existant['id'], $new_quantite)) {
                                $added_count++;
                            }
                        } else {
                            // Ajouter au panier
                            $quantite = min($produit['quantite'], $produit_info['stock']);
                            if (add_to_panier($_SESSION['user_id'], $produit['produit_id'], $quantite)) {
                                $added_count++;
                            }
                        }
                    }
                }

                if ($added_count > 0) {
                    header('Location: /panier.php?recommande=1&count=' . $added_count);
                    exit;
                } else {
                    $error_message = 'Aucun produit disponible à recommander.';
                }
            } else {
                $error_message = 'Aucun produit trouvé dans cette commande.';
            }
        } else {
            $error_message = 'Cette commande ne peut pas être recommandée.';
        }
    }
}

// Récupérer toutes les commandes de l'utilisateur
$commandes = get_commandes_by_user($_SESSION['user_id']);

// Filtrer pour afficher uniquement les commandes annulées
$commandes_annulees = array_filter($commandes, function ($commande) {
    return $commande['statut'] === 'annulee';
});
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Commandes Annulées - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-times-circle"></i> Commandes Annulées</h1>
    </div>

    <section class="content-section">
        <?php if ($success_message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="section-title">
            <h2><i class="fas fa-list"></i> Mes Commandes Annulées (<?php echo count($commandes_annulees); ?>)</h2>
        </div>

        <?php if (empty($commandes_annulees)): ?>
            <div class="empty-state">
                <i class="fas fa-ban"></i>
                <p>Aucune commande annulée pour le moment.</p>
                <a href="mes-commandes.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Retour aux commandes
                </a>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes_annulees as $commande): ?>
                    <div class="commande-item">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                                <p>Date: <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                            </div>
                            <span class="commande-statut statut-annulee" style="align-self: flex-start;">
                                Annulée
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Montant total</label>
                                <div class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Adresse</label>
                                <div class="value"
                                    style="font-size: 11px; max-width: 150px; text-align: right; word-break: break-word;">
                                    <?php echo htmlspecialchars(substr($commande['adresse_livraison'], 0, 30)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Téléphone</label>
                                <div class="value" style="font-size: 12px;">
                                    <?php echo htmlspecialchars($commande['telephone_livraison']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="commande-actions">
                            <a href="commande-categorie.php?commande_id=<?php echo $commande['id']; ?>"
                                class="btn-view-categories btn-view-commande">
                                <i class="fas fa-eye"></i> Voir les produits
                            </a>

                            <!-- Bouton Recommander -->
                            <form method="POST" action="" style="margin: 0;">
                                <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                <button type="submit" name="recommander" class="btn-recommander">
                                    <i class="fas fa-redo"></i> Recommander
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include 'includes/user_footer.php'; ?>
</body>

</html>

