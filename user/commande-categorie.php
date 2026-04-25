<?php

/**
 * Page pour voir les commandes groupées par catégorie
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit;
}

// Inclusion des modèles
require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/format_commande_options.php';

$user_id = $_SESSION['user_id'];
$commande_id = isset($_GET['commande_id']) ? (int) $_GET['commande_id'] : null;
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : null;

// Si une commande spécifique est demandée, récupérer ses produits
if ($commande_id) {
    // Vérifier que la commande appartient à l'utilisateur
    $commande = get_commande_by_id($commande_id, $user_id);
    if (!$commande) {
        header('Location: mes-commandes.php');
        exit;
    }

    // Récupérer les produits de cette commande
    $produits_commande = get_commande_produits($commande_id);

    // Grouper par catégorie
    $grouped_by_categorie = [];
    foreach ($produits_commande as $produit) {
        $cat_id = $produit['categorie_id'];
        $cat_nom = $produit['categorie_nom'] ?? 'Sans catégorie';

        if (!isset($grouped_by_categorie[$cat_id])) {
            $grouped_by_categorie[$cat_id] = [
                'categorie_id' => $cat_id,
                'categorie_nom' => $cat_nom,
                'produits' => []
            ];
        }
        $grouped_by_categorie[$cat_id]['produits'][] = $produit;
    }
    $commandes_by_categorie = array_values($grouped_by_categorie);
} else {
    // Récupérer toutes les commandes groupées par catégorie
    $commandes_by_categorie = get_commandes_by_categorie($user_id, $categorie_id);
}

// Récupérer toutes les catégories pour le filtre
$all_categories = get_all_categories();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mes Commandes par Catégorie - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .categorie-section {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--glass-shadow);
        }

        .categorie-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(229, 72, 138, 0.2);
            flex-wrap: wrap;
            gap: 15px;
        }

        .categorie-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--titres);
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-titres);
        }

        .categorie-badge {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .commande-categorie-page .produits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .commande-categorie-page .produits-grid {
                grid-template-columns: 1fr;
            }
        }

        .produit-card-commande {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 15px 7px;
            border: 1px solid var(--glass-border);
            transition: all 0.3s;
            width: 100%;
            max-width: 300px;
            min-width: 300px;
            margin: 0 auto;
        }

        .produit-card-commande:hover {
            box-shadow: var(--ombre-gourmande);
            transform: translateY(-2px);
            border-color: rgba(229, 72, 138, 0.3);
        }

        .produit-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            width: 280px;
        }

        .produit-card-commande .produit-card-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
        }

        .produit-card-info {
            width: 200px !important;
            padding: 0 10px;
        }

        .produit-card-commande .produit-card-nom {
            font-size: 16px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 5px;
        }

        .produit-card-commande-info {
            font-size: 12px;
            color: var(--texte-fonce);
        }

        .produit-card-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(229, 72, 138, 0.2);
        }

        .produit-card-details div {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }

        .detail-label {
            font-size: 11px;
            color: var(--texte-fonce);
            text-transform: uppercase;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            color: var(--titres);
            font-weight: 600;
            margin-top: 3px;
        }

        .detail-value.prix-total {
            color: var(--accent-promo);
        }

        .commande-info-badge {
            display: inline-block;
            background: var(--accent-promo);
            color: var(--texte-clair);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }

        .filter-section {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--glass-shadow);
        }

        .filter-section h3 {
            font-size: 18px;
            color: var(--titres);
            margin-bottom: 15px;
            font-family: var(--font-titres);
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .filter-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--titres);
            text-decoration: none;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
        }

        .commande-date-info {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(229, 72, 138, 0.2);
            font-size: 11px;
            color: var(--texte-fonce);
        }

        .content-header-link-retour {
            color: var(--couleur-dominante);
            text-decoration: none;
            font-size: 14px;
            margin-top: 10px;
            display: inline-block;
        }

        .content-header-link-retour:hover {
            text-decoration: underline;
        }

        .couleur-display {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .couleur-swatch-large {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            border: 1px solid rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }

        .options-lignes .option-ligne {
            margin-bottom: 4px;
        }

        .options-lignes .option-ligne:last-child {
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-layer-group"></i> Mes Commandes par Catégorie</h1>
        <a href="mes-commandes.php" class="content-header-link-retour">
            <i class="fas fa-arrow-left"></i> Retour aux commandes
        </a>
    </div>

    <section class="content-section commande-categorie-page">
        <!-- Filtre par catégorie -->
        <div class="filter-section">
            <h3>
                <i class="fas fa-filter"></i> Filtrer par catégorie
            </h3>
            <div class="filter-buttons">
                <a href="commande-categorie.php" class="filter-btn <?php echo !$categorie_id ? 'active' : ''; ?>">
                    Toutes les catégories
                </a>
                <?php foreach ($all_categories as $cat): ?>
                    <a href="commande-categorie.php?categorie_id=<?php echo $cat['id']; ?>"
                        class="filter-btn <?php echo $categorie_id == $cat['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['nom']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($commandes_by_categorie)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>Aucune commande trouvée pour cette catégorie.</p>
                <a href="mes-commandes.php" class="btn-primary">
                    <i class="fas fa-shopping-bag"></i> Voir toutes mes commandes
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($commandes_by_categorie as $categorie_data): ?>
                <div class="categorie-section">
                    <div class="categorie-header">
                        <div class="categorie-title">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($categorie_data['categorie_nom']); ?>
                            <span class="categorie-badge">
                                <?php echo count($categorie_data['produits']); ?>
                                produit<?php echo count($categorie_data['produits']) > 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>

                    <div class="produits-grid">
                        <?php foreach ($categorie_data['produits'] as $produit): ?>
                            <?php
                            $produit_nom = $produit['nom'] ?? $produit['produit_nom'] ?? 'Produit sans nom';
                            $produit_nom_affichage = !empty($produit['variante_nom']) ? $produit_nom . ' → ' . $produit['variante_nom'] : $produit_nom;
                            $produit_image = !empty($produit['image_afficher']) ? $produit['image_afficher'] : ($produit['image_principale'] ?? '');
                            ?>
                            <div class="produit-card-commande">
                                <div class="produit-card-header">
                                    <img src="/upload/<?php echo htmlspecialchars($produit_image); ?>"
                                        alt="<?php echo htmlspecialchars($produit_nom_affichage); ?>" class="produit-card-image"
                                        onerror="this.src='/image/produit1.jpg'">
                                    <div class="produit-card-info">
                                        <h4 class="produit-card-nom"><?php echo htmlspecialchars($produit_nom_affichage); ?></h4>
                                        <div class="produit-card-commande-info">
                                            <?php
                                            $numero_commande = $produit['numero_commande'] ?? null;
                                            if ($numero_commande):
                                                ?>
                                                <div class="commande-info-badge">
                                                    Commande #<?php echo htmlspecialchars($numero_commande); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="produit-card-details">
                                    <div>
                                        <div class="detail-label">Quantité</div>
                                        <div class="detail-value"><?php echo $produit['quantite']; ?></div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Prix unitaire</div>
                                        <div class="detail-value">
                                            <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> FCFA
                                        </div>
                                    </div>
                                    <div>
                                        <div class="detail-label">Prix total</div>
                                        <div class="detail-value prix-total">
                                            <?php echo number_format($produit['prix_total'], 0, ',', ' '); ?> FCFA
                                        </div>
                                    </div>
                                    <?php if (!empty($produit['variante_nom'])): ?>
                                        <div>
                                            <div class="detail-label">Variante</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($produit['variante_nom']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                    $couleur = $produit['couleur'] ?? '';
                                    if (!empty($couleur)):
                                        $hex = trim($couleur);
                                        $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
                                        $nom_couleur = format_couleur_commande($hex);
                                        ?>
                                        <div>
                                            <div class="detail-label">Couleur</div>
                                            <div class="detail-value couleur-display">
                                                <?php if ($is_hex): ?>
                                                    <span class="couleur-swatch-large"
                                                        style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                        title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                <?php endif; ?>

                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                    $poids_raw = $produit['poids'] ?? $produit['choix_poids'] ?? '';
                                    $taille_raw = $produit['taille'] ?? '';
                                    $surcout_p = isset($produit['surcout_poids']) ? (float) $produit['surcout_poids'] : 0;
                                    $surcout_t = isset($produit['surcout_taille']) ? (float) $produit['surcout_taille'] : 0;
                                    $poids_lignes = parse_poids_taille_commande($poids_raw, $surcout_p);
                                    $taille_lignes = parse_poids_taille_commande($taille_raw, $surcout_t);
                                    $afficher_poids = !empty($poids_lignes);
                                    $afficher_taille = !empty($taille_lignes);
                                    ?>
                                    <?php if ($afficher_poids): ?>
                                        <div>
                                            <div class="detail-label">Poids</div>
                                            <div class="detail-value options-lignes">
                                                <?php foreach ($poids_lignes as $opt): ?>
                                                    <div class="option-ligne"><?php
                                                    echo htmlspecialchars($opt['v']);
                                                    if (($opt['s'] ?? 0) > 0)
                                                        echo ' (poids +' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)';
                                                    ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($afficher_taille): ?>
                                        <div>
                                            <div class="detail-label">Taille</div>
                                            <div class="detail-value options-lignes">
                                                <?php foreach ($taille_lignes as $opt): ?>
                                                    <div class="option-ligne"><?php
                                                    echo htmlspecialchars($opt['v']);
                                                    if (($opt['s'] ?? 0) > 0)
                                                        echo ' (taille +' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)';
                                                    ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php
                                $date_commande = $produit['date_commande'] ?? null;
                                if ($date_commande):
                                    ?>
                                    <div class="commande-date-info">
                                        <i class="fas fa-calendar"></i>
                                        Date: <?php echo date('d/m/Y à H:i', strtotime($date_commande)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php include 'includes/user_footer.php'; ?>

</body>

</html>