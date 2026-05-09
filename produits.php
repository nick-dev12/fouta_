<?php
require_once __DIR__ . '/includes/session_user.php';
session_start();

// Inclusion des modèles
require_once __DIR__ . '/models/model_produits.php';

// Récupérer les produits (recherche + filtres ou tous)
$produits_tous = [];
$total_produits = 0;
$recherche_actuelle = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$prix_min = isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? (float) $_GET['prix_min'] : null;
$prix_max = isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? (float) $_GET['prix_max'] : null;
$categorie_id = isset($_GET['categorie']) && $_GET['categorie'] !== '' ? (int) $_GET['categorie'] : null;
$tri = isset($_GET['tri']) && in_array($_GET['tri'], ['date', 'prix_asc', 'prix_desc', 'nom']) ? $_GET['tri'] : 'date';
$has_filters = !empty($recherche_actuelle) || $prix_min !== null || $prix_max !== null || $categorie_id !== null || $tri !== 'date';

if (file_exists(__DIR__ . '/models/model_produits.php')) {
    if ($has_filters) {
        $produits_tous = search_produits_with_filters($recherche_actuelle, $prix_min, $prix_max, $categorie_id, $tri, 0, 20);
        $total_produits = count_search_produits_with_filters($recherche_actuelle, $prix_min, $prix_max, $categorie_id);
    } else {
        $produits_tous = get_all_produits_paginated(0, 20);
        $total_produits = count_all_produits_actifs();
    }
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Catalogue pièces poids lourds - FOUTA POIDS LOURDS';
$seo_description = 'Catalogue de pièces de véhicules poids lourds : camions, bus, tracteurs, remorques, cylindres. Pièces détachées de qualité pour l\'entretien et la réparation.';
$seo_canonical = $base . '/produits.php';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <style>
        .produits-page-header {
            background: var(--couleur-dominante);
            padding: 40px 20px;
            text-align: center;
            color: var(--texte-clair);
            margin-bottom: 40px;
        }

        .produits-page-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .produits-page-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .produits-container-wrapper {
            max-width: 1400px;
            margin: 0 auto;

        }

        .btn-voir-plus {
            padding: 15px 40px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 30px auto;
        }

        .btn-voir-plus:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-promo);
        }

        .btn-voir-plus:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .produits-count {
            text-align: center;
            margin-top: 15px;
            color: var(--gris-moyen);
            font-size: 14px;
        }

        /* Assurer que le contenu principal a un espacement suffisant pour le footer */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .produits-container-wrapper {
            flex: 1;
            padding-bottom: 100px;
            margin-bottom: 0;
        }

        /* S'assurer que le footer est bien positionné et ne se superpose pas */
        .footer {
            margin-top: 80px;
            position: relative;
            width: 100%;
            clear: both;
            flex-shrink: 0;
        }

        /* Espacement supplémentaire pour la section des produits */
        .section00 {
            margin-bottom: 60px;
        }

        /* S'assurer que le wrapper principal a un espacement suffisant */
        .produits-page-header {
            margin-bottom: 40px;
        }

        .filtres-actifs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-top: 12px;
            font-size: 14px;
            opacity: 0.95;
        }

        .filtres-actifs span {
            background: rgba(255, 255, 255, 0.25);
            padding: 6px 12px;
            border-radius: 20px;
        }
    </style>
</head>

<body>
    <?php include('nav_bar.php'); ?>

    <div class="produits-page-header">
        <h1><i class="fas fa-box"></i>
            <?php echo !empty($recherche_actuelle) ? 'Résultats pour "' . htmlspecialchars($recherche_actuelle) . '"' : 'Tous nos produits'; ?>
        </h1>
        <p><?php echo $has_filters ? $total_produits . ' produit(s) trouvé(s)' : 'Découvrez notre sélection complète de produits naturels'; ?>
        </p>
        <?php if ($has_filters): ?>
            <p class="filtres-actifs">
                <?php if (!empty($recherche_actuelle)): ?><span><i class="fas fa-search"></i>
                        <?php echo htmlspecialchars($recherche_actuelle); ?></span><?php endif; ?>
                <?php if ($prix_min !== null): ?><span><i class="fas fa-coins"></i> Min
                        <?php echo number_format($prix_min, 0, ',', ' '); ?> FCFA</span><?php endif; ?>
                <?php if ($prix_max !== null): ?><span><i class="fas fa-coins"></i> Max
                        <?php echo number_format($prix_max, 0, ',', ' '); ?> FCFA</span><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
        <div
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--success-bg); border-left: 4px solid var(--bleu); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-check-circle"></i> Produit ajouté au panier avec succès.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--error-bg); border-left: 4px solid var(--error-border); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <div class="produits-container-wrapper">
        <section class="section00">
            <section class="produit_vedetes">
                <article data-aos="fade-up" data-aos-delay="0" data-aos-duration="1000" data-aos-easing="ease-in-out"
                    data-aos-mirror="true" data-aos-once="true" data-aos-anchor-placement="top-bottom"
                    class="articles carousel11" id="produits-container">
                    <?php if (empty($produits_tous)): ?>
                        <!-- Message si aucun produit -->
                        <div style="text-align: center; padding: 40px; color: var(--gris-moyen); width: 100%;">
                            <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                            <p style="font-size: 16px;">Aucun produit publié pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($produits_tous as $produit): ?>
                            <?php
                            // Calculer le prix à afficher
                            $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                                ? $produit['prix_promotion']
                                : $produit['prix'];
                            $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                            $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
                            ?>
                            <div class="carousel" data-produit-id="<?php echo $produit['id']; ?>">
                                <a href="produit.php?id=<?php echo $produit['id']; ?>" class="product-card-link">
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

                                    </div>
                                </a>
                                <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                    <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url"
                                        value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/produits.php'); ?>">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </article>

                <?php if (!empty($produits_tous) && $total_produits > 20): ?>
                    <div style="text-align: center; margin-top: 40px; padding: 20px;">
                        <button id="btn-voir-plus" class="btn-voir-plus" onclick="chargerPlusProduits()">
                            <i class="fas fa-chevron-down"></i> Voir plus
                        </button>
                        <p id="produits-count" class="produits-count">
                            Affichés: <span id="count-actuel">20</span> / <?php echo $total_produits; ?> produits
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <?php include('footer.php'); ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init();

        let offsetActuel = 20; // On a déjà affiché les 20 premiers
        const limit = 20;
        const totalProduits = <?php echo $total_produits; ?>;
        const apiBaseParams = '<?php
        $p = ['offset' => 0, 'limit' => 20];
        if ($has_filters) {
            if (!empty($recherche_actuelle))
                $p['recherche'] = $recherche_actuelle;
            if ($prix_min !== null)
                $p['prix_min'] = $prix_min;
            if ($prix_max !== null)
                $p['prix_max'] = $prix_max;
            if ($categorie_id !== null)
                $p['categorie'] = $categorie_id;
            $p['tri'] = $tri;
        }
        echo http_build_query($p);
        ?>';

        function getApiUrl() {
            const params = new URLSearchParams(apiBaseParams);
            params.set('offset', offsetActuel);
            return 'api/get_produits.php?' + params.toString();
        }

        function chargerPlusProduits() {
            const btn = document.getElementById('btn-voir-plus');
            const container = document.getElementById('produits-container');
            const countActuel = document.getElementById('count-actuel');

            // Désactiver le bouton pendant le chargement
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';

            // Faire la requête AJAX
            fetch(getApiUrl())
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.produits.length > 0) {
                        // Ajouter les nouveaux produits
                        data.produits.forEach(produit => {
                            const div = document.createElement('div');
                            div.className = 'carousel';
                            div.setAttribute('data-produit-id', produit.id);

                            let promoHTML = '';
                            if (produit.has_promotion) {
                                promoHTML = `<span class="span2">${formatNumber(produit.prix)}fca</span>
                                             <span class="span3">-${produit.pourcentage_promo}%</span>`;
                            }

                            let categorieStock = '';
                            if (produit.categorie_nom) {
                                categorieStock += produit.categorie_nom;
                            }
                            if (produit.stock) {
                                categorieStock += (categorieStock ? ' | ' : '') + 'Stock: ' + produit.stock;
                            }

                            let prixHTML = '';
                            if (produit.has_promotion) {
                                prixHTML = `<span class="span2">${formatNumber(produit.prix)} FCFA</span>
                                            <span class="prix-promo">${formatNumber(produit.prix_affichage)} FCFA</span>
                                            <span class="span3">-${produit.pourcentage_promo}%</span>`;
                            } else {
                                prixHTML =
                                    `${formatNumber(produit.prix_affichage)}<span class="span1"> FCFA</span>`;
                            }

                            let stockHTML = '';
                            if (produit.stock) {
                                stockHTML = `<p class="produit-card-stock-info">
                                    <strong>Stock:</strong> ${produit.stock}
                                    ${produit.poids ? `(${escapeHtml(produit.poids)})` : ''}
                                </p>`;
                            }

                            const returnUrl = (window.location.pathname + window.location.search).replace(/&/g,
                                '&amp;').replace(/"/g, '&quot;');
                            div.innerHTML = `
                                <a href="produit.php?id=${produit.id}" class="product-card-link">
                                    <div class="image-wrapper">
                                        <img src="/upload/${produit.image_principale}" 
                                             alt="${escapeHtml(produit.nom)}"
                                             onerror="this.src='/image/produit1.jpg'">
                                    </div>
                                    <div class="produit-content">
                                        <p id="nom">${escapeHtml(produit.nom)}</p>
                                        ${produit.categorie_nom ? `<p id="ville">${escapeHtml(produit.categorie_nom)}</p>` : ''}
                                        <p class="prix">${prixHTML}</p>
                                        ${stockHTML}
                                    </div>
                                </a>
                                <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                    <input type="hidden" name="produit_id" value="${produit.id}">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url" value="${returnUrl}">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                                    </button>
                                </form>
                            `;

                            container.appendChild(div);
                        });

                        // Mettre à jour le compteur
                        offsetActuel += data.produits.length;
                        countActuel.textContent = offsetActuel;

                        // Vérifier s'il reste des produits
                        if (offsetActuel >= totalProduits) {
                            btn.style.display = 'none';
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
                        }
                    } else {
                        // Plus de produits à charger
                        btn.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement:', error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus';
                    alert('Une erreur est survenue lors du chargement des produits.');
                });
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>