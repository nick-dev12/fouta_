<?php
/**
 * Page de liste des commandes utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

// Récupérer les commandes de l'utilisateur
require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';

// Traitement de la confirmation de livraison
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_livraison'])) {
    $commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);
        if ($commande && $commande['statut'] === 'livraison_en_cours') {
            require_once __DIR__ . '/../models/model_commandes_admin.php';
            if (update_commande_statut($commande_id, 'paye')) {
                $success_message = 'Colis reçu confirmé avec succès !';
                header('Location: mes-commandes.php?livraison_confirmee=1');
                exit;
            }
        }
        if (empty($success_message)) {
            $error_message = 'Une erreur est survenue lors de la confirmation de la réception du colis.';
        }
    }
}

// Traitement de l'annulation de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    $commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commande_id > 0) {
        // Vérifier que la commande peut être annulée (pas déjà livrée ou annulée)
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);

        if ($commande && $commande['statut'] !== 'livree' && $commande['statut'] !== 'annulee') {
            if (update_commande_statut_user($commande_id, $_SESSION['user_id'], 'annulee')) {
                $success_message = 'Commande annulée avec succès !';
                // Recharger les commandes pour afficher le nouveau statut
                header('Location: mes-commandes.php?commande_annulee=1');
                exit;
            } else {
                $error_message = 'Une erreur est survenue lors de l\'annulation de la commande.';
            }
        } else {
            $error_message = 'Cette commande ne peut pas être annulée.';
        }
    }
}

// Traitement de la recommandation (ajouter les produits au panier)
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
                require_once __DIR__ . '/../models/model_variantes.php';
                $added_count = 0;
                foreach ($produits_commande as $produit) {
                    require_once __DIR__ . '/../models/model_produits.php';
                    $produit_info = get_produit_by_id($produit['produit_id']);

                    if ($produit_info && $produit_info['statut'] === 'actif' && $produit_info['stock'] > 0) {
                        $quantite = min($produit['quantite'], $produit_info['stock']);
                        $variante_id = !empty($produit['variante_id']) ? (int) $produit['variante_id'] : null;
                        $variante_nom = !empty($produit['variante_nom']) ? trim($produit['variante_nom']) : null;
                        $variante_image = null;
                        if ($variante_id) {
                            $var = get_variante_by_id($variante_id);
                            $variante_image = $var && !empty($var['image']) ? $var['image'] : null;
                        }
                        $surcout_poids = isset($produit['surcout_poids']) ? (float) $produit['surcout_poids'] : 0;
                        $surcout_taille = isset($produit['surcout_taille']) ? (float) $produit['surcout_taille'] : 0;
                        $prix_unitaire = isset($produit['prix_unitaire']) ? (float) $produit['prix_unitaire'] : null;

                        if (add_to_panier($_SESSION['user_id'], $produit['produit_id'], $quantite,
                            $produit['couleur'] ?? null, $produit['poids'] ?? null, $produit['taille'] ?? null,
                            $variante_id, $variante_nom, $variante_image, $surcout_poids, $surcout_taille, $prix_unitaire)) {
                            $added_count++;
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

// Message de succès après création de commande
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['numero'])) {
    $success_message = 'Votre commande #' . htmlspecialchars($_GET['numero']) . ' a été créée avec succès !';
}

// Message de succès après confirmation de livraison
if (isset($_GET['livraison_confirmee']) && $_GET['livraison_confirmee'] == '1') {
    $success_message = 'Colis reçu confirmé avec succès !';
}

// Message de succès après annulation de commande
if (isset($_GET['commande_annulee']) && $_GET['commande_annulee'] == '1') {
    $success_message = 'Commande annulée avec succès !';
}

$commandes = get_commandes_by_user($_SESSION['user_id']);

// Filtrer pour exclure les commandes avec le statut "livree", "paye" et "annulee"
$commandes_actives = array_filter($commandes, function ($commande) {
    return $commande['statut'] !== 'livree' && $commande['statut'] !== 'paye' && $commande['statut'] !== 'annulee';
});

// Commandes personnalisées actives (en cours, hors terminées/refusées/annulées)
$commandes_perso = get_commandes_personnalisees_by_user($_SESSION['user_id']);
$commandes_perso_actives = array_filter($commandes_perso, function ($cp) {
    return !in_array($cp['statut'], ['terminee', 'refusee', 'annulee']);
});
$statuts_labels = get_statuts_commande_personnalisee();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mes Commandes - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-shopping-bag"></i> Mes Commandes</h1>
    </div>

    <div class="continue-shopping-banner">
        <div class="continue-shopping-content">
            <i class="fas fa-shopping-cart"></i>
            <div>
                <strong>Continuer vos achats</strong>
                <p>Retournez à l'accueil ou parcourez nos produits pour continuer vos courses.</p>
            </div>
            <a href="/index.php" class="btn-continue-shopping">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a>
            <a href="/produits.php" class="btn-continue-shopping btn-continue-products">
                <i class="fas fa-shopping-cart"></i> Voir les produits
            </a>
        </div>
    </div>
    <style>
        .continue-shopping-banner {
            background: linear-gradient(135deg, rgba(229, 72, 138, 0.1) 0%, rgba(145, 138, 68, 0.15) 100%);
            border: 1px solid rgba(229, 72, 138, 0.2);
            border-radius: 12px;
            padding: 20px 24px;
            margin: 0 20px 24px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        .continue-shopping-content {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .continue-shopping-content > i {
            font-size: 36px;
            color: var(--couleur-dominante);
        }
        .continue-shopping-content > div {
            flex: 1;
            min-width: 200px;
        }
        .continue-shopping-content strong {
            display: block;
            font-size: 16px;
            color: var(--titres);
            margin-bottom: 4px;
        }
        .continue-shopping-content p {
            margin: 0;
            font-size: 14px;
            color: var(--texte-fonce);
            opacity: 0.9;
        }
        .btn-continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--couleur-dominante);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .btn-continue-shopping:hover {
            background: rgba(229, 72, 138, 0.9);
            transform: translateY(-2px);
            color: #fff;
        }
        .btn-continue-products {
            background: rgba(145, 138, 68, 0.9);
        }
        .btn-continue-products:hover {
            background: rgba(145, 138, 68, 1);
        }
    </style>

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
            <h2><i class="fas fa-list"></i> Mes Commandes Actives (<?php echo count($commandes_actives); ?>)</h2>
            <a href="commande-categorie.php" class="btn-view-categories">
                <i class="fas fa-layer-group"></i> Voir par catégorie
            </a>
        </div>

        <?php if (empty($commandes_actives)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <p>Aucune commande active pour le moment.</p>
                <a href="/produits.php" class="btn-primary">
                    <i class="fas fa-shopping-cart"></i> Découvrir nos produits
                </a>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes_actives as $commande): ?>
                    <div class="commande-item">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
                                <p>Date: <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                            </div>
                            <span class="commande-statut statut-<?php echo $commande['statut']; ?>"
                                style="align-self: flex-start;">
                                <?php
                                // Formater l'affichage du statut
                                $statut_display = ucfirst(str_replace('_', ' ', $commande['statut']));
                                // Remplacer "Livree" par "Reçu"
                                if ($commande['statut'] == 'livree' || $commande['statut'] == 'paye') {
                                    $statut_display = 'Reçu';
                                }
                                // Remplacer "Annulee" par "Annulée"
                                if ($commande['statut'] == 'annulee') {
                                    $statut_display = 'Annulée';
                                }
                                echo $statut_display;
                                ?>
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
                            <?php if ($commande['date_livraison']): ?>
                                <div class="detail-item">
                                    <label>Date livraison</label>
                                    <div class="value" style="font-size: 12px;">
                                        <?php echo date('d/m/Y', strtotime($commande['date_livraison'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="commande-actions">
                            <a href="commande-categorie.php?commande_id=<?php echo $commande['id']; ?>"
                                class="btn-view-categories btn-view-commande">
                                <i class="fas fa-eye"></i> Voir les produits
                            </a>

                            <!-- Bouton Colis reçu - visible uniquement si statut = livraison_en_cours -->
                            <?php if ($commande['statut'] == 'livraison_en_cours'): ?>
                                <form method="POST" action="" style="margin: 0;">
                                    <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                    <button type="submit" name="confirmer_livraison" class="btn-confirmer-livraison"
                                        onclick="return confirm('Avez-vous bien reçu votre colis ?');" style="width: 100%;">
                                        <i class="fas fa-check-circle"></i> Colis reçu
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Bouton Annuler - visible uniquement si la commande peut être annulée -->
                            <?php
                            $can_cancel = in_array($commande['statut'], ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation']);
                            if ($can_cancel):
                                ?>
                                <form method="POST" action="" style="margin: 0;">
                                    <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                    <button type="submit" name="annuler_commande" class="btn-annuler-commande"
                                        onclick="return confirm('Êtes-vous sûr de vouloir annuler cette commande ? Cette action est irréversible.');">
                                        <i class="fas fa-times-circle"></i> Annuler la commande
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Section Commandes personnalisées -->
        <div class="section-title" style="margin-top: 40px;">
            <h2><i class="fas fa-palette"></i> Mes commandes personnalisées (<?php echo count($commandes_perso_actives); ?>)</h2>
            <a href="/commande-personnalisee.php" class="btn-view-categories">
                <i class="fas fa-plus"></i> Nouvelle demande
            </a>
        </div>

        <?php if (empty($commandes_perso_actives)): ?>
            <div class="empty-state empty-state-compact">
                <i class="fas fa-palette"></i>
                <p>Aucune commande personnalisée en cours.</p>
                <a href="/commande-personnalisee.php" class="btn-primary">
                    <i class="fas fa-palette"></i> Faire une demande personnalisée
                </a>
            </div>
        <?php else: ?>
            <div class="commandes-grid">
                <?php foreach ($commandes_perso_actives as $cp): ?>
                    <div class="commande-item commande-item-perso">
                        <div class="commande-header">
                            <div class="commande-info">
                                <h3>Demande #<?php echo $cp['id']; ?></h3>
                                <p>Date: <?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?></p>
                            </div>
                            <span class="commande-statut statut-<?php echo $cp['statut']; ?>" style="align-self: flex-start;">
                                <?php echo $statuts_labels[$cp['statut']] ?? $cp['statut']; ?>
                            </span>
                        </div>
                        <div class="commande-details">
                            <div class="detail-item">
                                <label>Description</label>
                                <div class="value" style="font-size: 13px; line-height: 1.4;">
                                    <?php echo nl2br(htmlspecialchars(substr($cp['description'], 0, 120))); ?><?php echo strlen($cp['description']) > 120 ? '...' : ''; ?>
                                </div>
                            </div>
                            <?php if ($cp['type_produit']): ?>
                            <div class="detail-item">
                                <label>Type</label>
                                <div class="value"><?php echo htmlspecialchars($cp['type_produit']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="commande-actions">
                            <a href="commande-personnalisee-details.php?id=<?php echo $cp['id']; ?>" class="btn-view-categories btn-view-commande">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php include 'includes/user_footer.php'; ?>