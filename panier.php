<?php
require_once __DIR__ . '/includes/session_user.php';
session_start();

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/controllers/controller_panier.php';

// Traitement des actions du panier
$message = '';
$message_type = '';

// Message de succès après recommandation
if (isset($_GET['recommande']) && $_GET['recommande'] == '1') {
    $count = isset($_GET['count']) ? (int) $_GET['count'] : 0;
    $message = $count > 0
        ? $count . ' produit(s) de votre commande annulée ont été ajoutés au panier avec succès !'
        : 'Les produits ont été ajoutés au panier avec succès !';
    $message_type = 'success';
}

// Message après ajout direct depuis les cartes produits
if (isset($_GET['added']) && $_GET['added'] == '1') {
    $message = 'Produit ajouté au panier avec succès.';
    $message_type = 'success';
}
if (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $result = process_update_panier();
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
            case 'delete':
                $result = process_delete_from_panier();
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: /user/connexion.php?redirect=panier');
    exit;
}

// Récupérer les produits du panier
$panier_items = get_panier_by_user($_SESSION['user_id']);

// Calculer le total et le nombre total d'articles
$panier_total = get_panier_total($_SESSION['user_id']);
$nombre_total_articles = 0;
foreach ($panier_items as $item) {
    $nombre_total_articles += $item['quantite'];
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Mon Panier - FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <style>
        /* Styles panier - Palette FOUTA POIDS LOURDS */
        .panier-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .panier-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 30px;
            text-align: center;
            font-family: var(--font-titres);
        }

        .panier-page .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .panier-page .message.success {
            background: var(--success-bg);
            color: var(--titres);
            border: 1px solid var(--bleu);
        }

        .panier-page .message.error {
            background: var(--error-bg);
            color: var(--titres);
            border: 1px solid var(--error-border);
        }

        .panier-empty {
            text-align: center;
            padding: 60px 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
        }

        .panier-empty i {
            font-size: 64px;
            color: var(--couleur-dominante);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .panier-empty p {
            font-size: 18px;
            color: var(--texte-fonce);
            margin-bottom: 30px;
        }

        .panier-empty .btn-continuer {
            display: inline-block;
            padding: 12px 24px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .panier-empty .btn-continuer:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .panier-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        .panier-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panier-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            transition: all 0.3s;
        }

        .panier-item:hover {
            box-shadow: var(--ombre-gourmande);
        }

        .panier-item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
        }

        .panier-item-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .panier-item-nom {
            font-size: 18px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
        }

        .panier-item-categorie {
            font-size: 13px;
            color: var(--couleur-dominante);
            margin-bottom: 10px;
        }

        .panier-item-prix {
            font-size: 20px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 15px;
        }

        .panier-item-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .quantite-controls {
            display: flex;
            align-items: center;
            border: 2px solid var(--couleur-dominante);
            border-radius: 8px;
            overflow: hidden;
        }

        .quantite-btn {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            width: 35px;
            height: 35px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantite-btn:hover {
            background: var(--couleur-dominante-hover);
        }

        .quantite-input {
            width: 60px;
            height: 35px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--texte-fonce);
        }

        .panier-item-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-promo);
            margin-left: auto;
        }

        .btn-delete {
            background: var(--boutons-secondaires);
            color: var(--texte-clair);
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-delete:hover {
            background: var(--boutons-secondaires-hover);
        }

        .panier-summary {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
            box-shadow: var(--glass-shadow);
        }

        .summary-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-input);
            font-family: var(--font-titres);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
            color: var(--texte-fonce);
        }

        .summary-row.total {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-input);
        }

        .btn-commander {
            width: 100%;
            padding: 15px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-commander:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
            color: var(--texte-clair);
        }

        .btn-commander:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .panier-summary .link-continuer {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--couleur-dominante);
            text-decoration: none;
            font-weight: 500;
        }

        .panier-summary .link-continuer:hover {
            color: var(--orange);
            text-decoration: underline;
        }

        .panier-page .btn-update {
            padding: 8px 15px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .panier-page .badge-promo {
            font-size: 12px;
            background: var(--accent-promo);
            color: var(--texte-clair);
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }

        .panier-prix-label {
            font-size: 16px;
            color: var(--texte-fonce);
        }

        .panier-prix-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--titres);
        }

        .panier-prix-barré {
            font-size: 14px;
            color: var(--gris-moyen);
            text-decoration: line-through;
            margin-left: 10px;
        }

        .panier-update-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panier-quantite-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--texte-fonce);
        }

        .panier-total-detail {
            font-size: 14px;
            color: var(--gris-moyen);
            margin-bottom: 4px;
        }

        .panier-total-montant {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-promo);
        }

        .panier-stock-info {
            font-size: 12px;
            color: var(--gris-moyen);
            margin-top: 10px;
        }

        .panier-item-options {
            font-size: 13px;
            color: var(--couleur-dominante);
            margin-bottom: 8px;
        }

        .panier-item-options .opt-swatch {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.2);
            vertical-align: middle;
        }

        .summary-value {
            font-weight: 600;
            color: var(--titres);
        }

        .summary-value-alt {
            font-weight: 600;
            color: var(--couleur-dominante);
        }

        .summary-subtotal {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-input);
        }

        .summary-livraison {
            color: var(--gris-moyen);
        }

        .summary-total-value {
            color: var(--titres);
        }

        /* Responsive */
        @media (max-width: 968px) {
            .panier-content {
                grid-template-columns: 1fr;
            }

            .panier-summary {
                position: static;
            }

            .panier-item {
                flex-direction: column;
            }

            .panier-item-image {
                width: 100%;
                height: 200px;
            }
        }

        @media (max-width: 600px) {
            .panier-item-controls {
                flex-direction: column;
                align-items: flex-start;
            }

            .panier-item-total {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>

<body class="panier-page">

    <?php include('nav_bar.php') ?>

    <div class="panier-container">
        <h1 class="panier-title">Mon Panier</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($panier_items)): ?>
            <div class="panier-empty">
                <i class="fas fa-shopping-cart"></i>
                <p>Votre panier est vide</p>
                <a href="/index.php" class="btn-continuer">
                    Continuer mes achats
                </a>
            </div>
        <?php else: ?>
            <div class="panier-content">
                <!-- Liste des produits -->
                <div class="panier-items">
                    <?php foreach ($panier_items as $item): ?>
                        <?php
                        // Prix unitaire : variante/surcoûts ou produit de base
                        $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                            ? (float) $item['panier_prix_unitaire']
                            : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
                        $prix_total_item = $prix_unitaire * $item['quantite'];
                        $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : $item['image_principale'];
                        $item_nom = !empty($item['panier_variante_nom'])
                            ? $item['nom'] . ' → ' . $item['panier_variante_nom']
                            : $item['nom'];
                        ?>
                        <div class="panier-item" data-item-id="<?php echo $item['panier_id']; ?>">
                            <img src="/upload/<?php echo htmlspecialchars($item_img); ?>"
                                alt="<?php echo htmlspecialchars($item_nom); ?>" class="panier-item-image"
                                onerror="this.src='/image/produit1.jpg'">

                            <div class="panier-item-info">
                                <h3 class="panier-item-nom"><?php echo htmlspecialchars($item_nom); ?></h3>
                                <p class="panier-item-categorie"><?php echo htmlspecialchars($item['categorie_nom']); ?></p>
                                <?php if (!empty($item['panier_couleur']) || !empty($item['panier_poids']) || !empty($item['panier_taille'])): ?>
                                    <p class="panier-item-options">
                                        <?php
                                        $opts = [];
                                        if (!empty(trim($item['panier_couleur'] ?? ''))) {
                                            $hex = trim($item['panier_couleur']);
                                            $opts[] = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)
                                                ? '<span class="opt-swatch" style="background:' . htmlspecialchars($hex) . '"></span> ' . htmlspecialchars($hex)
                                                : 'Couleur: ' . htmlspecialchars($hex);
                                        }
                                        if (!empty(trim($item['panier_poids'] ?? '')))
                                            $opts[] = 'Poids: ' . htmlspecialchars($item['panier_poids']);
                                        if (!empty(trim($item['panier_taille'] ?? '')))
                                            $opts[] = 'Taille: ' . htmlspecialchars($item['panier_taille']);
                                        echo implode(' • ', $opts);
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <p class="panier-item-prix">
                                    <span class="panier-prix-label">Prix unitaire:</span>
                                    <span class="panier-prix-value">
                                        <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA
                                    </span>
                                    <?php if (empty($item['panier_prix_unitaire']) && !empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix']): ?>
                                        <span class="panier-prix-barré">
                                            <?php echo number_format($item['prix'], 0, ',', ' '); ?> FCFA
                                        </span>
                                        <span class="badge-promo">PROMO</span>
                                    <?php endif; ?>
                                </p>

                                <div class="panier-item-controls">
                                    <form method="POST" action="" class="update-form panier-update-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="panier_id" value="<?php echo $item['panier_id']; ?>">

                                        <label class="panier-quantite-label">Quantité:</label>
                                        <div class="quantite-controls">
                                            <button type="button" class="quantite-btn decrease-btn">-</button>
                                            <input type="number" name="quantite" class="quantite-input"
                                                value="<?php echo $item['quantite']; ?>" min="1"
                                                max="<?php echo $item['stock']; ?>" required>
                                            <button type="button" class="quantite-btn increase-btn">+</button>
                                        </div>

                                        <button type="submit" class="btn-update">
                                            <i class="fas fa-sync-alt"></i> Mettre à jour
                                        </button>
                                    </form>

                                    <form method="POST" action="" style="display: inline;"
                                        onsubmit="return confirm('Êtes-vous sûr de vouloir retirer ce produit du panier ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="panier_id" value="<?php echo $item['panier_id']; ?>">
                                        <button type="submit" class="btn-delete">
                                            <i class="fas fa-trash"></i> Retirer
                                        </button>
                                    </form>

                                    <div class="panier-item-total">
                                        <div class="panier-total-detail">
                                            <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> ×
                                            <?php echo $item['quantite']; ?>
                                        </div>
                                        <div class="panier-total-montant">
                                            Total: <?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA
                                        </div>
                                    </div>
                                </div>

                                <p class="panier-stock-info">
                                    Stock disponible: <?php echo $item['stock']; ?>
                                    <?php echo htmlspecialchars($item['unite']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Résumé du panier -->
                <div class="panier-summary">
                    <h2 class="summary-title">Résumé du panier</h2>

                    <div class="summary-row">
                        <span>Nombre d'articles:</span>
                        <span id="nombre-articles" class="summary-value">
                            <?php echo $nombre_total_articles; ?>
                            article<?php echo $nombre_total_articles > 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span>Nombre de produits:</span>
                        <span class="summary-value-alt">
                            <?php echo count($panier_items); ?> produit<?php echo count($panier_items) > 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="summary-row summary-subtotal">
                        <span>Sous-total:</span>
                        <span id="subtotal" class="summary-value">
                            <?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA
                        </span>
                    </div>

                    <div class="summary-row">
                        <span>Livraison:</span>
                        <span class="summary-livraison">À calculer</span>
                    </div>

                    <div class="summary-row total">
                        <span>Total général:</span>
                        <span id="total" class="summary-total-value">
                            <?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA
                        </span>
                    </div>

                    <a href="/commande.php" class="btn-commander">
                        <i class="fas fa-shopping-bag"></i> Passer la commande
                    </a>

                    <a href="/index.php" class="link-continuer">
                        Continuer mes achats
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include('footer.php') ?>


    <script>
        // Gestion des boutons + et - pour la quantité
        document.querySelectorAll('.increase-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const input = this.parentElement.querySelector('.quantite-input');
                const max = parseInt(input.getAttribute('max'));
                let value = parseInt(input.value) || 1;
                if (value < max) {
                    value++;
                    input.value = value;
                }
            });
        });

        document.querySelectorAll('.decrease-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const input = this.parentElement.querySelector('.quantite-input');
                let value = parseInt(input.value) || 1;
                if (value > 1) {
                    value--;
                    input.value = value;
                }
            });
        });
    </script>

</body>

</html>