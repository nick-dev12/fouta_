<?php
require_once __DIR__ . '/includes/session_user.php';
session_start();

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_visites.php';
require_once __DIR__ . '/models/model_variantes.php';
require_once __DIR__ . '/controllers/controller_panier.php';

// Récupérer l'ID du produit depuis l'URL ou POST
$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id'])) {
    $produit_id = (int) $_POST['produit_id'];
}

// Traitement de l'ajout au panier
$message = '';
$message_type = '';

// Vérifier si c'est une redirection après ajout au panier (pattern Post-Redirect-Get)
if (isset($_GET['added']) && ($_GET['added'] === 'success' || $_GET['added'] === '1')) {
    $message = 'Produit ajouté au panier avec succès.';
    $message_type = 'success';
}
if (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $message_type = 'error';
}

// Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_panier') {
    $result = process_add_to_panier();

    // Redirection vers le panier après ajout réussi
    if ($result['success']) {
        header('Location: /panier.php?added=1');
        exit;
    }
    // Redirection vers la connexion si non connecté
    if (!$result['success'] && strpos($result['message'] ?? '', 'connecté') !== false) {
        $pid = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
        $redirect = $pid > 0 ? '/produit.php?id=' . $pid : '/panier';
        header('Location: /user/connexion.php?redirect=' . urlencode($redirect));
        exit;
    }
    $message = $result['message'] ?? '';
    $message_type = 'error';
}

// Récupérer les informations du produit
$produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;

// Si le produit n'existe pas, rediriger vers l'accueil
if (!$produit || $produit['statut'] != 'actif') {
    header('Location: index.php');
    exit;
}

// Enregistrer la visite si l'utilisateur est connecté
if (isset($_SESSION['user_id']) && $produit_id > 0) {
    add_visite($_SESSION['user_id'], $produit_id);
}

// Calculer le prix à afficher (promotion si disponible)
$prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix_promotion']
    : $produit['prix'];
$prix_original = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix']
    : null;
$pourcentage_reduction = 0;
if ($prix_original) {
    $pourcentage_reduction = round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100);
}

// Récupérer les variantes du produit
$variantes = get_variantes_by_produit($produit_id);

// Récupérer les produits similaires (même catégorie)
$produits_similaires = get_produits_by_categorie($produit['categorie_id']);
// Exclure le produit actuel
$produits_similaires = array_filter($produits_similaires, function ($p) use ($produit_id) {
    return $p['id'] != $produit_id;
});
$produits_similaires = array_slice($produits_similaires, 0, 4); // Limiter à 4 produits

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = $produit['nom'] . ' - FOUTA POIDS LOURDS';
$desc = !empty($produit['description']) ? strip_tags($produit['description']) : $produit['nom'] . ' - Pièce de véhicule poids lourd FOUTA POIDS LOURDS. Pièces détachées camion, bus, tracteur, remorque.';
$seo_description = mb_substr($desc, 0, 160);
$seo_canonical = $base . '/produit.php?id=' . (int) $produit['id'];
$seo_og_type = 'product';
$img = !empty($produit['image_principale']) ? $produit['image_principale'] : '';
$seo_image = $img ? $base . '/' . ltrim($img, '/') : $base . '/icons/icon-512.png';
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
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="/css/owl.carousel.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/owl.carousel.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/animate.min.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <style>
        /* Styles pour la page produit - Palette gourmande */
        body {
            background: transparent;
        }

        .produit-detail-container {
            width: 100%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
            box-sizing: border-box;
        }

        .produit-detail-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
            width: 100%;
        }

        .produit-image-section {
            position: relative;
            flex: 1 1 100%;
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 769px) {
            .produit-image-section {
                flex: 1 1 calc(50% - 15px);
                max-width: calc(50% - 15px);
            }
        }

        .produit-gallery-main {
            position: relative;
            margin-bottom: 15px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .produit-gallery-thumbs {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            width: 100%;
            max-width: 100%;
        }

        .gallery-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-input);
            background: var(--blanc);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .gallery-nav:hover {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border-color: var(--couleur-dominante);
        }

        .gallery-thumbs-list {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
            flex: 1;
            min-width: 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .gallery-thumbs-list::-webkit-scrollbar {
            height: 4px;
        }

        .gallery-thumbs-list::-webkit-scrollbar-thumb {
            background: var(--border-input);
            border-radius: 4px;
        }

        .gallery-thumb {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            padding: 0;
            border: 3px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            background: var(--blanc-neige);
            transition: all 0.3s;
        }

        .gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .gallery-thumb:hover {
            border-color: var(--border-input);
        }

        .gallery-thumb.active {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px var(--focus-ring);
        }

        .produit-image-main {
            width: 100%;
            max-width: 100%;
            height: 400px;
            object-fit: contain;
            object-position: center;
            border-radius: 16px;
            border: 2px solid var(--border-input);
            background: var(--blanc-casse);
            box-shadow: var(--ombre-douce);
        }

        .produit-info-section {
            display: flex;
            flex-direction: column;
            flex: 1 1 100%;
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 769px) {
            .produit-info-section {
                flex: 1 1 calc(50% - 15px);
                max-width: calc(50% - 15px);
            }
        }

        .produit-nom {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 10px;
            line-height: 1.3;
            font-family: var(--font-titres);
        }

        .produit-categorie {
            display: inline-block;
            font-size: 12px;
            color: var(--couleur-dominante);
            background: var(--bleu-pale);
            padding: 6px 12px;
            border-radius: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .produit-prix-section {
            margin-bottom: 15px;
            padding: 18px;
            background: var(--blanc);
            border-radius: 12px;
            border-left: 4px solid var(--couleur-dominante);
        }

        .prix-principal {
            font-size: 26px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 5px;
        }

        .prix-original {
            font-size: 18px;
            color: var(--texte-fonce);
            text-decoration: line-through;
            margin-right: 8px;
            opacity: 0.7;
        }

        .prix-promo {
            font-size: 22px;
            color: var(--accent-promo);
            font-weight: 600;
        }

        .promo-badge {
            display: inline-block;
            background: var(--accent-promo);
            color: var(--texte-clair);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 8px;
        }

        .produit-stock-info {
            margin-bottom: 15px;
            padding: 14px;
            background: var(--blanc);
            border-radius: 12px;
            border: 1px solid var(--border-input);
        }

        .stock-item {
            font-size: 13px;
            color: var(--texte-fonce);
            margin-bottom: 6px;
        }

        .stock-item strong {
            color: var(--couleur-dominante);
            font-weight: 600;
            min-width: 90px;
            display: inline-block;
        }

        .stock-value {
            color: var(--couleur-dominante);
            font-weight: 700;
        }

        .couleurs-swatches-display {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-left: 4px;
        }

        .couleur-swatch-display {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            cursor: default;
        }

        .produit-options-section {
            margin-bottom: 20px;
            padding: 16px;
            background: var(--blanc);
            border-radius: 12px;
            border: 1px solid var(--border-input);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .option-group {
            margin-bottom: 14px;
        }

        .option-group:last-child {
            margin-bottom: 0;
        }

        .option-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
        }

        .couleurs-swatches-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }

        .couleur-swatch-select {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f5f5f5;
            border-radius: 20px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }

        .couleur-swatch-select:hover {
            border-color: var(--border-input);
            background: var(--blanc);
        }

        .couleur-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px var(--focus-ring);
            background: var(--blanc);
        }

        .couleur-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .couleur-swatch-select .swatch-preview {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }

        .couleur-swatch-select .swatch-text {
            font-size: 13px;
            color: var(--texte-fonce);
        }

        .option-select {
            padding: 10px 14px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 14px;
            min-width: 140px;
            background: var(--blanc);
            cursor: pointer;
        }

        .option-select:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }

        .option-value-display {
            font-size: 14px;
            color: var(--texte-fonce);
            font-weight: 500;
        }

        .produit-description {
            margin-bottom: 24px;
            padding: 24px;
            background: var(--blanc);
            border-radius: 16px;
            line-height: 1.7;
            color: var(--texte-fonce);
            font-size: 15px;
            box-shadow: var(--ombre-douce);
            border: 1px solid var(--border-input);
            border-left: 4px solid var(--couleur-dominante);
        }

        .produit-description h3 {
            font-size: 18px;
            color: var(--couleur-dominante);
            margin-bottom: 14px;
            font-weight: 600;
        }

        .produit-description p {
            margin: 0;
            color: var(--texte-fonce);
        }

        .quantite-section {
            margin-bottom: 20px;
        }

        .quantite-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
            display: block;
        }

        .quantite-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            width: 100%;
            max-width: 100%;
        }

        .quantite-input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-input);
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .quantite-btn {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            width: 38px;
            height: 40px;
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
            height: 38px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--titres);
        }

        .prix-total-section {
            padding: 18px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .prix-total-label {
            font-size: 13px;
            margin-bottom: 5px;
            opacity: 0.95;
        }

        .prix-total-value {
            font-size: 24px;
            font-weight: 700;
        }

        .produit-add-form {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .btn-add-panier {
            width: 100%;
            padding: 14px 25px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--ombre-promo);
        }

        .btn-add-panier:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-gourmande);
        }

        .btn-add-panier:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .message {
            padding: 15px 20px;
            padding-right: 45px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background-color: var(--success-bg);
            color: var(--bleu-fonce);
            border: 1px solid var(--bleu);
        }

        .message.error {
            background-color: var(--error-bg);
            color: var(--titres);
            border: 1px solid var(--error-border);
        }

        .message-close {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .message-close:hover {
            opacity: 1;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .message.fade-out {
            animation: fadeOut 0.3s ease-out forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-10px);
                max-height: 0;
                margin-bottom: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        .produits-similaires {
            margin-top: 60px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .produits-similaires h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 30px;
            text-align: center;
            font-family: var(--font-titres);
        }

        .produit-connect-cta {
            padding: 24px;
            background: var(--blanc);
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-input);
        }

        .produit-connect-cta p {
            margin-bottom: 18px;
            color: var(--texte-fonce);
        }

        .btn-connect-produit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: var(--ombre-promo);
        }

        .btn-connect-produit:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-gourmande);
            color: var(--texte-clair);
        }

        /* Responsive - Tablette */
        @media (max-width: 992px) {
            .produit-detail-wrapper {
                gap: 24px;
            }

            .produit-image-section,
            .produit-info-section {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .produit-image-main {
                height: 360px;
            }

            .produit-nom {
                font-size: 22px;
            }

            .produit-prix-section {
                padding: 14px 16px;
            }

            .prix-principal {
                font-size: 24px;
            }

            .produit-options-section,
            .produit-variantes-section.produit-section-bg {
                padding: 14px 16px;
            }

            .produit-description {
                padding: 18px 20px;
            }

            .produits-similaires {
                margin-top: 40px;
            }

            .produits-similaires h2 {
                font-size: 24px;
                margin-bottom: 24px;
            }
        }

        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .produit-detail-wrapper {
                flex-direction: column;
                gap: 20px;
                margin-bottom: 30px;
            }

            .produit-image-section,
            .produit-info-section {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .produit-image-main {
                height: 280px;
                border-radius: 12px;
            }

            .produit-gallery-main {
                margin-bottom: 12px;
            }

            .gallery-thumb {
                width: 56px;
                height: 56px;
            }

            .gallery-nav {
                width: 36px;
                height: 36px;
                font-size: 12px;
            }

            .produit-nom {
                font-size: 19px;
                line-height: 1.35;
                margin-bottom: 8px;
            }

            .produit-prix-section {
                padding: 12px 14px;
                margin-bottom: 12px;
            }

            .prix-principal {
                font-size: 20px;
            }

            .prix-original {
                font-size: 15px;
            }

            .prix-promo {
                font-size: 18px;
            }

            .promo-badge {
                font-size: 11px;
                padding: 3px 8px;
            }

            .produit-description {
                padding: 16px 18px;
                margin-bottom: 18px;
                font-size: 14px;
            }

            .produit-description h3 {
                font-size: 16px;
                margin-bottom: 10px;
            }

            .produit-section-bg {
                padding: 14px 16px;
                margin-bottom: 16px;
            }

            .produit-variantes-section.produit-section-bg {
                padding: 14px 16px;
            }

            .variante-option {
                flex: 0 0 140px;
                min-width: 140px;
                padding: 10px 12px;
            }

            .variante-option .variante-thumb {
                width: 42px;
                height: 42px;
            }

            .variante-option .variante-nom {
                font-size: 12px;
            }

            .variante-option .variante-prix {
                font-size: 11px;
            }

            .variantes-arrow {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .produit-options-section .quantite-label {
                font-size: 13px;
            }

            .option-group {
                margin-bottom: 12px;
            }

            .options-list-select {
                gap: 8px;
            }

            .option-label {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .couleur-swatch-select {
                padding: 5px 8px;
            }

            .couleur-swatch-select .swatch-preview {
                width: 24px;
                height: 24px;
            }

            .couleur-swatch-select .swatch-text {
                font-size: 12px;
            }

            .option-swatch-select {
                min-width: 70px;
                padding: 8px 12px;
            }

            .option-swatch-select .option-swatch-text {
                font-size: 12px;
            }

            .quantite-section {
                margin-bottom: 16px;
            }

            .quantite-label {
                font-size: 13px;
            }

            .quantite-input-wrapper {
                border-radius: 10px;
            }

            .quantite-btn {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }

            .quantite-input {
                width: 50px;
                height: 42px;
                font-size: 15px;
            }

            .prix-total-section {
                padding: 14px 16px;
                margin-bottom: 16px;
            }

            .prix-total-label {
                font-size: 12px;
            }

            .prix-total-value {
                font-size: 20px;
            }

            .btn-add-panier {
                padding: 12px 20px;
                font-size: 15px;
                border-radius: 20px;
            }

            .produit-detail-container {
                margin: 12px auto;
                padding: 0 12px;
            }

            .produits-similaires {
                margin-top: 36px;
                padding: 0 4px;
            }

            .produits-similaires h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .produits-similaires .produit_vedetes .articles,
            .produits-similaires .carousel11 {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                width: 100%;
            }

            .produits-similaires .carousel {
                flex: 1 1 calc(50% - 8px);
                min-width: 0;
                max-width: calc(50% - 8px);
            }

            .message {
                padding: 12px 16px;
                padding-right: 40px;
                font-size: 14px;
            }
        }

        /* Responsive - Petit mobile */
        @media (max-width: 480px) {
            .produit-detail-container {
                padding: 0 10px;
                margin: 10px auto;
            }

            .produit-image-main {
                height: 240px;
            }

            .gallery-thumb {
                width: 48px;
                height: 48px;
            }

            .produit-nom {
                font-size: 17px;
            }

            .prix-principal {
                font-size: 18px;
            }

            .prix-original {
                font-size: 14px;
            }

            .prix-promo {
                font-size: 16px;
            }

            .variante-option {
                flex: 0 0 120px;
                min-width: 120px;
                padding: 8px 10px;
            }

            .variante-option .variante-thumb {
                width: 36px;
                height: 36px;
            }

            .variante-option .variante-nom {
                font-size: 11px;
            }

            .variante-option .variante-prix {
                font-size: 10px;
            }

            .option-swatch-select {
                min-width: 65px;
                padding: 6px 10px;
            }

            .option-swatch-select .option-swatch-text {
                font-size: 11px;
                line-height: 1.3;
            }

            .quantite-btn {
                width: 40px;
                height: 42px;
            }

            .quantite-input {
                width: 44px;
            }

            .prix-total-value {
                font-size: 18px;
            }

            .btn-add-panier {
                padding: 12px 16px;
                font-size: 14px;
            }

            .produits-similaires h2 {
                font-size: 18px;
                margin-bottom: 16px;
            }

            .produits-similaires .produit_vedetes .articles,
            .produits-similaires .carousel11 {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                width: 100%;
            }

            .produits-similaires .carousel {
                flex: 1 1 calc(50% - 6px);
                min-width: 0;
                max-width: calc(50% - 6px);
            }
        }

        /* Très petit écran : produits similaires en 1 colonne */
        @media (max-width: 360px) {
            .produits-similaires .carousel {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }

        .produit-section-bg {
            background: var(--blanc);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-input);
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .produit-variantes-section {
            margin-bottom: 20px;
        }

        .produit-variantes-section.produit-section-bg {
            padding: 18px 20px;
        }

        .variantes-carousel-wrapper {
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }

        .variantes-scroll-container {
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            width: 100%;
            max-width: 100%;
        }

        .variantes-scroll-container::-webkit-scrollbar {
            display: none;
        }

        .variantes-select {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            padding: 4px 0;
            width: 100%;
            min-width: 0;
        }

        .variante-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            flex: 0 0 calc(50% - 5px);
            min-width: 0;
            box-sizing: border-box;
        }

        @media (min-width: 768px) {
            .variante-option {
                flex: 0 0 calc(33.333% - 7px);
            }
        }

        @media (min-width: 1024px) {
            .variante-option {
                flex: 0 0 calc(25% - 8px);
            }
        }

        .variantes-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--couleur-dominante);
            background: var(--blanc);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .variantes-arrow:hover {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
        }

        .variantes-arrow.visible {
            display: flex;
        }

        .variantes-arrow-left {
            left: 8px;
        }

        .variantes-arrow-right {
            right: 8px;
        }

        .variante-option:hover,
        .variante-option.selected {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
        }

        .variante-option .variante-thumb {
            width: 50px;
            height: 50px;
            object-fit: contain;
            object-position: center;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .variante-option .variante-nom {
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
        }

        .variante-option .variante-prix {
            font-size: 12px;
            color: var(--couleur-dominante);
        }

        .options-list-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            width: 100%;
            max-width: 100%;
        }

        .option-swatch-select {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background: var(--blanc);
            border-radius: 10px;
            border: 2px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.2s;
            min-width: 80px;
            flex: 1 1 auto;
            max-width: 100%;
        }

        .option-swatch-select:hover {
            border-color: var(--border-input);
        }

        .option-swatch-select.selected,
        .option-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
        }

        .option-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .option-swatch-select .option-swatch-text {
            font-size: 13px;
            font-weight: 500;
            color: var(--titres);
            word-break: break-word;
            text-align: center;
            overflow-wrap: break-word;
            min-width: 0;
        }

        .options-list-select {
            flex-wrap: wrap;
        }

        .produit_vedetes,
        .produit_vedetes .articles,
        .produit_vedetes .carousel11 {
            width: 100%;
            max-width: 100%;
        }
    </style>
</head>

<body>

    <?php include('nav_bar.php') ?>

    <div class="produit-detail-container">
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>" id="message-alert">
                <span><?php echo htmlspecialchars($message); ?></span>
                <button type="button" class="message-close" onclick="closeMessage()" aria-label="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="produit-detail-wrapper">
            <!-- Section Image avec galerie -->
            <div class="produit-image-section">
                <?php
                $galerie_images = [];
                if (!empty($produit['images'])) {
                    $dec = json_decode($produit['images'], true);
                    if (is_array($dec))
                        $galerie_images = $dec;
                }
                if (empty($galerie_images) && !empty($produit['image_principale'])) {
                    $galerie_images = [$produit['image_principale']];
                }
                ?>
                <div class="produit-gallery-main">
                    <img src="/upload/<?php echo htmlspecialchars($galerie_images[0] ?? $produit['image_principale']); ?>"
                        alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-image-main"
                        id="produit-image-main" onerror="this.src='/image/produit1.jpg'">
                </div>
                <?php if (count($galerie_images) > 1): ?>
                    <div class="produit-gallery-thumbs">
                        <button type="button" class="gallery-nav gallery-prev" aria-label="Image précédente">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="gallery-thumbs-list">
                            <?php foreach ($galerie_images as $idx => $img_path): ?>
                                <button type="button" class="gallery-thumb <?php echo $idx === 0 ? 'active' : ''; ?>"
                                    data-index="<?php echo $idx; ?>"
                                    data-src="/upload/<?php echo htmlspecialchars($img_path); ?>">
                                    <img src="/upload/<?php echo htmlspecialchars($img_path); ?>"
                                        alt="Vue <?php echo $idx + 1; ?>" onerror="this.src='/image/produit1.jpg'">
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gallery-nav gallery-next" aria-label="Image suivante">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section Informations -->
            <div class="produit-info-section">
                <h1 class="produit-nom" id="produit-nom"><?php echo htmlspecialchars($produit['nom']); ?></h1>

                <!-- Prix -->
                <div class="produit-prix-section">
                    <div class="prix-principal" id="produit-prix-affichage">
                        <?php if ($prix_original): ?>
                            <span class="prix-original"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                FCFA</span>
                            <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                            <span class="promo-badge">-<?php echo $pourcentage_reduction; ?>%</span>
                        <?php else: ?>
                            <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Variantes, Stock, Poids, Couleurs, Taille -->
                <?php
                $couleurs_options = [];
                if (!empty($produit['couleurs'])) {
                    $cr = trim($produit['couleurs']);
                    $dec = json_decode($cr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (is_array($dec) && !empty($dec)) {
                            $couleurs_options = array_filter($dec, function ($c) {
                                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
                            });
                        }
                    } else {
                        $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
                    }
                }
                $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
                $taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
                $poids_options = array_values(array_filter($poids_options, function ($o) {
                    $v = trim((string) ($o['v'] ?? ''));
                    return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
                }));
                $taille_options = array_values(array_filter($taille_options, function ($o) {
                    $v = trim((string) ($o['v'] ?? ''));
                    return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
                }));
                $has_selectable_options = !empty($couleurs_options) || !empty($poids_options) || !empty($taille_options);
                $has_variantes = !empty($variantes);
                $prix_base_js = $prix_affichage;
                $variantes_js = [];
                foreach ($variantes as $v) {
                    $vp = !empty($v['prix_promotion']) && $v['prix_promotion'] < $v['prix'] ? $v['prix_promotion'] : $v['prix'];
                    $variantes_js[] = ['id' => $v['id'], 'nom' => $v['nom'], 'prix' => (float) $vp, 'image' => $v['image'] ?? ''];
                }
                $poids_js = [];
                foreach ($poids_options as $p) {
                    $poids_js[] = ['v' => $p['v'], 's' => (float) ($p['s'] ?? 0)];
                }
                $taille_js = [];
                foreach ($taille_options as $t) {
                    $taille_js[] = ['v' => $t['v'], 's' => (float) ($t['s'] ?? 0)];
                }
                ?>

                <!-- Description (en bas) -->
                <?php if (!empty($produit['description'])): ?>
                    <div class="produit-description produit-section-bg">
                        <h3>Description</h3>
                        <p>
                            <?php echo nl2br(htmlspecialchars($produit['description'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Variantes du produit -->
                <?php if ($has_variantes): ?>
                    <?php $total_variantes = 1 + count($variantes); ?>
                    <div class="produit-variantes-section produit-section-bg">
                        <div class="quantite-label" style="margin-bottom: 10px;"><i class="fas fa-layer-group"></i> Autres
                            quantités disponibles</div>
                        <div class="variantes-carousel-wrapper" data-total="<?php echo $total_variantes; ?>">
                            <button type="button" class="variantes-arrow variantes-arrow-left"
                                aria-label="Variantes précédentes" title="Précédent">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="variantes-arrow variantes-arrow-right"
                                aria-label="Variantes suivantes" title="Suivant">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <div class="variantes-scroll-container">
                                <div class="variantes-select">
                                    <?php
                                    $base_prix_orig = $prix_original ? (float) $produit['prix'] : 0;
                                    $base_prix_promo = $prix_affichage;
                                    $base_pct = $pourcentage_reduction;
                                    ?>
                                    <label class="variante-option variante-base selected" data-id=""
                                        data-prix="<?php echo $base_prix_promo; ?>"
                                        data-prix-original="<?php echo $base_prix_orig; ?>"
                                        data-prix-promo="<?php echo $base_prix_promo; ?>"
                                        data-pourcentage="<?php echo $base_pct; ?>"
                                        data-nom="<?php echo htmlspecialchars($produit['nom']); ?>"
                                        data-image="<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>">
                                        <input type="radio" name="option_variante_radio" value="" checked required>
                                        <?php if (!empty($produit['image_principale'])): ?><img
                                                src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                                                alt="" class="variante-thumb"
                                                onerror="this.style.display='none'"><?php endif; ?>
                                        <span class="variante-nom"><?php echo htmlspecialchars($produit['nom']); ?></span>
                                        <span
                                            class="variante-prix"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                            FCFA</span>
                                    </label>
                                    <?php foreach ($variantes as $var): ?>
                                        <?php
                                        $vp = !empty($var['prix_promotion']) && $var['prix_promotion'] < $var['prix'] ? $var['prix_promotion'] : $var['prix'];
                                        $v_prix_orig = (!empty($var['prix_promotion']) && $var['prix_promotion'] < $var['prix']) ? (float) $var['prix'] : 0;
                                        $v_pct = $v_prix_orig > 0 ? round((($var['prix'] - $var['prix_promotion']) / $var['prix']) * 100) : 0;
                                        ?>
                                        <label class="variante-option" data-id="<?php echo $var['id']; ?>"
                                            data-prix="<?php echo $vp; ?>" data-prix-original="<?php echo $v_prix_orig; ?>"
                                            data-prix-promo="<?php echo $vp; ?>" data-pourcentage="<?php echo $v_pct; ?>"
                                            data-nom="<?php echo htmlspecialchars($var['nom']); ?>"
                                            data-image="<?php echo htmlspecialchars($var['image'] ?? ''); ?>">
                                            <input type="radio" name="option_variante_radio" value="<?php echo $var['id']; ?>">
                                            <?php if (!empty($var['image'])): ?><img
                                                    src="/upload/<?php echo htmlspecialchars($var['image']); ?>" alt=""
                                                    class="variante-thumb" onerror="this.style.display='none'"><?php endif; ?>
                                            <span class="variante-nom"><?php echo htmlspecialchars($var['nom']); ?></span>
                                            <span class="variante-prix"><?php echo number_format($vp, 0, ',', ' '); ?>
                                                FCFA</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>




                <!-- Options (couleur, poids, taille) : affichées pour tous les utilisateurs -->
                <form method="POST" action="" id="add-to-panier-form" class="produit-add-form">
                    <input type="hidden" name="action" value="add_to_panier">
                    <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                    <?php if ($has_variantes): ?>
                        <input type="hidden" name="option_variante_id" id="option-variante-id" value="">
                        <input type="hidden" name="option_variante_nom" id="option-variante-nom"
                            value="<?php echo htmlspecialchars($produit['nom']); ?>">
                        <input type="hidden" name="option_variante_image" id="option-variante-image"
                            value="<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>">
                    <?php endif; ?>

                    <?php if ($has_selectable_options): ?>
                        <div class="produit-options-section produit-section-bg">
                            <div class="quantite-label" style="margin-bottom: 10px;"><i class="fas fa-palette"></i>
                                Choisissez vos options</div>
                            <?php if (!empty($couleurs_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Couleur</label>
                                    <?php if (count($couleurs_options) === 1): ?>
                                        <input type="hidden" name="option_couleur"
                                            value="<?php echo htmlspecialchars($couleurs_options[0]); ?>">
                                        <span class="couleurs-swatches-select">
                                            <?php $hex = $couleurs_options[0]; ?>
                                            <span class="couleur-swatch-select is-hex" style="opacity:0.9;">
                                                <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                    <span class="swatch-preview"
                                                        style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                        title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                    <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                <?php else: ?>
                                                    <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    <?php else: ?>
                                        <span class="couleurs-swatches-select">
                                            <?php foreach ($couleurs_options as $hex): ?>
                                                <label
                                                    class="couleur-swatch-select <?php echo preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? 'is-hex' : ''; ?>">
                                                    <input type="radio" name="option_couleur"
                                                        value="<?php echo htmlspecialchars($hex); ?>" class="option-radio-couleur">
                                                    <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                        <span class="swatch-preview"
                                                            style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                            title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                    <?php else: ?>
                                                        <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($poids_options) && count($poids_options) > 1): ?>
                                <div class="option-group">
                                    <label class="option-label">Poids</label>
                                    <input type="hidden" name="option_surcout_poids" id="option-surcout-poids" value="0">
                                    <span class="options-list-select poids-options-list">
                                        <label class="option-swatch-select selected" data-value="" data-surcout="0">
                                            <input type="radio" name="option_poids" value="" checked>
                                            <span class="option-swatch-text">Prix de base
                                                (<?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA)</span>
                                        </label>
                                        <?php foreach ($poids_options as $opt): ?>
                                            <label class="option-swatch-select"
                                                data-value="<?php echo htmlspecialchars($opt['v']); ?>"
                                                data-surcout="<?php echo (float) ($opt['s'] ?? 0); ?>">
                                                <input type="radio" name="option_poids"
                                                    value="<?php echo htmlspecialchars($opt['v']); ?>">
                                                <span
                                                    class="option-swatch-text"><?php echo htmlspecialchars($opt['v']); ?><?php echo ($opt['s'] ?? 0) > 0 ? ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php elseif (!empty($poids_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Poids</label>
                                    <input type="hidden" name="option_poids"
                                        value="<?php echo htmlspecialchars($poids_options[0]['v']); ?>">
                                    <input type="hidden" name="option_surcout_poids" id="option-surcout-poids"
                                        value="<?php echo (float) ($poids_options[0]['s'] ?? 0); ?>">
                                    <span
                                        class="option-value-display"><?php echo htmlspecialchars($poids_options[0]['v']); ?><?php echo ($poids_options[0]['s'] ?? 0) > 0 ? ' (+' . number_format($poids_options[0]['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($taille_options) && count($taille_options) > 1): ?>
                                <div class="option-group">
                                    <label class="option-label">Taille</label>
                                    <input type="hidden" name="option_surcout_taille" id="option-surcout-taille" value="0">
                                    <span class="options-list-select taille-options-list">
                                        <label class="option-swatch-select selected" data-value="" data-surcout="0">
                                            <input type="radio" name="option_taille" value="" checked>
                                            <span class="option-swatch-text">Prix de base
                                                (<?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA)</span>
                                        </label>
                                        <?php foreach ($taille_options as $opt): ?>
                                            <label class="option-swatch-select"
                                                data-value="<?php echo htmlspecialchars($opt['v']); ?>"
                                                data-surcout="<?php echo (float) ($opt['s'] ?? 0); ?>">
                                                <input type="radio" name="option_taille"
                                                    value="<?php echo htmlspecialchars($opt['v']); ?>">
                                                <span
                                                    class="option-swatch-text"><?php echo htmlspecialchars($opt['v']); ?><?php echo ($opt['s'] ?? 0) > 0 ? ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php elseif (!empty($taille_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Taille</label>
                                    <input type="hidden" name="option_taille"
                                        value="<?php echo htmlspecialchars($taille_options[0]['v']); ?>">
                                    <input type="hidden" name="option_surcout_taille" id="option-surcout-taille"
                                        value="<?php echo (float) ($taille_options[0]['s'] ?? 0); ?>">
                                    <span
                                        class="option-value-display"><?php echo htmlspecialchars($taille_options[0]['v']); ?><?php echo ($taille_options[0]['s'] ?? 0) > 0 ? ' (+' . number_format($taille_options[0]['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Sélection de quantité et ajout au panier -->
                    <input type="hidden" name="option_prix_unitaire" id="option-prix-unitaire"
                        value="<?php echo $prix_affichage; ?>">
                    <div class="quantite-section">
                        <label class="quantite-label">Quantité:</label>
                        <div class="quantite-controls">
                            <div class="quantite-input-wrapper">
                                <button type="button" class="quantite-btn" id="decrease-qty">-</button>
                                <input type="number" name="quantite" id="quantite" class="quantite-input" value="1"
                                    min="1" max="<?php echo $produit['stock']; ?>" required>
                                <button type="button" class="quantite-btn" id="increase-qty">+</button>
                            </div>
                        </div>
                    </div>


                    <!-- Prix total calculé -->
                    <div class="prix-total-section">
                        <div class="prix-total-label">Prix total:</div>
                        <div class="prix-total-value" id="prix-total">
                            <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                        </div>
                    </div>


                    <button type="submit" class="btn-add-panier" id="btn-add-panier">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <?php echo isset($_SESSION['user_id']) ? 'Ajouter au panier' : 'Se connecter pour ajouter au panier'; ?>
                    </button>
                </form>

                <!-- Description (en bas) -->
                <!-- <?php if (!empty($produit['description'])): ?>
                    <div class="produit-description produit-section-bg">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($produit['description'])); ?></p>
                    </div>
                <?php endif; ?> -->
            </div>
        </div>

        <!-- Produits similaires -->
        <?php if (!empty($produits_similaires)): ?>
            <div class="produits-similaires">
                <h2>Produits similaires</h2>
                <section class="produit_vedetes">
                    <article class="articles carousel11">
                        <?php foreach ($produits_similaires as $similaire): ?>
                            <?php
                            $prix_sim = !empty($similaire['prix_promotion']) && $similaire['prix_promotion'] < $similaire['prix']
                                ? $similaire['prix_promotion']
                                : $similaire['prix'];
                            ?>
                            <div class="carousel">
                                <a href="produit.php?id=<?php echo $similaire['id']; ?>" class="product-card-link">
                                    <div class="image-wrapper">
                                        <img src="/upload/<?php echo htmlspecialchars($similaire['image_principale']); ?>"
                                            alt="<?php echo htmlspecialchars($similaire['nom']); ?>"
                                            onerror="this.src='/image/produit1.jpg'">
                                    </div>
                                    <div class="produit-content">
                                        <p id="nom"><?php echo htmlspecialchars($similaire['nom']); ?></p>
                                        <p class="prix"><?php echo number_format($prix_sim, 0, ',', ' '); ?> <span
                                                class="span1">FCFA</span></p>
                                        <p id="ville"><?php echo htmlspecialchars($similaire['categorie_nom']); ?></p>
                                    </div>
                                </a>
                                <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                    <input type="hidden" name="produit_id" value="<?php echo $similaire['id']; ?>">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url"
                                        value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/produit.php'); ?>">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </article>
                </section>
            </div>
        <?php endif; ?>
    </div>

    <?php include('footer.php') ?>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        // Calcul automatique du prix total (variante + surcoûts)
        const prixBase = <?php echo $prix_affichage; ?>;
        const quantiteInput = document.getElementById('quantite');
        const prixTotalElement = document.getElementById('prix-total');
        const prixUnitaireInput = document.getElementById('option-prix-unitaire');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const maxStock = <?php echo $produit['stock']; ?>;

        function getPrixUnitaire() {
            var prix = prixBase;
            var selVariante = document.querySelector('.variante-option.selected, .variante-option input:checked');
            if (selVariante) {
                var el = selVariante.classList ? selVariante : selVariante.closest('.variante-option');
                if (el && el.dataset.prix) prix = parseFloat(el.dataset.prix);
            }
            var surcP = 0,
                surcT = 0;
            var spH = document.getElementById('option-surcout-poids');
            if (spH && spH.value) surcP = parseFloat(spH.value) || 0;
            var stH = document.getElementById('option-surcout-taille');
            if (stH && stH.value) surcT = parseFloat(stH.value) || 0;
            return prix + surcP + surcT;
        }

        function updatePrixTotal() {
            if (!quantiteInput || !prixTotalElement) return;
            const prixUnitaire = getPrixUnitaire();
            const quantite = parseInt(quantiteInput.value) || 1;
            const prixTotal = prixUnitaire * quantite;
            prixTotalElement.textContent = prixTotal.toLocaleString('fr-FR') + ' FCFA';
            if (prixUnitaireInput) prixUnitaireInput.value = prixUnitaire;

            var btnAdd = document.getElementById('btn-add-panier');
            if (btnAdd) btnAdd.disabled = (quantite > maxStock || quantite <= 0);
        }

        var produitNomBase = '<?php echo addslashes(htmlspecialchars($produit['nom'])); ?>';

        function updatePrixEtNomAffichage() {
            var prixUnitaire = getPrixUnitaire();
            var selVariante = document.querySelector('.variante-option.selected, .variante-option input:checked');
            var el = selVariante && selVariante.classList ? selVariante : (selVariante ? selVariante.closest(
                '.variante-option') : null);
            var nomAffichage = produitNomBase;
            if (el && el.dataset.nom) nomAffichage = el.dataset.nom;

            var elNom = document.getElementById('produit-nom');
            var elPrix = document.getElementById('produit-prix-affichage');
            if (elNom) elNom.textContent = nomAffichage;

            if (elPrix) {
                var prixOrig = parseFloat(el && el.dataset.prixOriginal ? el.dataset.prixOriginal : 0) || 0;
                var pourcentage = parseInt(el && el.dataset.pourcentage ? el.dataset.pourcentage : 0) || 0;
                var surcP = 0,
                    surcT = 0;
                var spH = document.getElementById('option-surcout-poids');
                if (spH && spH.value) surcP = parseFloat(spH.value) || 0;
                var stH = document.getElementById('option-surcout-taille');
                if (stH && stH.value) surcT = parseFloat(stH.value) || 0;
                var prixOriginalAvecSurc = prixOrig + surcP + surcT;

                if (prixOrig > 0 && pourcentage > 0) {
                    elPrix.innerHTML = '<span class="prix-original">' + prixOriginalAvecSurc.toLocaleString('fr-FR') +
                        ' FCFA</span> ' +
                        '<span class="prix-promo">' + prixUnitaire.toLocaleString('fr-FR') + ' FCFA</span> ' +
                        '<span class="promo-badge">-' + pourcentage + '%</span>';
                } else {
                    elPrix.textContent = prixUnitaire.toLocaleString('fr-FR') + ' FCFA';
                }
            }
        }

        document.querySelectorAll('.variante-option').forEach(function (el) {
            el.addEventListener('click', function () {
                document.querySelectorAll('.variante-option').forEach(function (x) {
                    x.classList.remove('selected');
                });
                el.classList.add('selected');
                var inp = el.querySelector('input[type="radio"]');
                if (inp) inp.checked = true;
                var hid = document.getElementById('option-variante-id');
                var hnom = document.getElementById('option-variante-nom');
                var himg = document.getElementById('option-variante-image');
                if (hid) hid.value = el.dataset.id || '';
                if (hnom) hnom.value = el.dataset.nom || '';
                if (himg) himg.value = el.dataset.image || '';
                var mainImg = document.getElementById('produit-image-main');
                if (mainImg && el.dataset.image) mainImg.src = '/upload/' + el.dataset.image;
                else if (mainImg && !el.dataset.id) mainImg.src =
                    '/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>';
                updatePrixTotal();
                if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
            });
        });

        (function initVariantesCarousel() {
            var wrapper = document.querySelector('.variantes-carousel-wrapper');
            if (!wrapper) return;
            var scrollContainer = wrapper.querySelector('.variantes-scroll-container');
            var arrowLeft = wrapper.querySelector('.variantes-arrow-left');
            var arrowRight = wrapper.querySelector('.variantes-arrow-right');
            var total = parseInt(wrapper.dataset.total || 0, 10);
            if (!scrollContainer || !arrowLeft || !arrowRight || total <= 0) return;

            function getVisibleCount() {
                var w = window.innerWidth;
                if (w >= 1024) return 4;
                if (w >= 768) return 3;
                return 2;
            }

            function updateArrows() {
                var visible = getVisibleCount();
                if (total <= visible) {
                    arrowLeft.classList.remove('visible');
                    arrowRight.classList.remove('visible');
                    return;
                }
                var sc = scrollContainer.scrollLeft;
                var maxScroll = scrollContainer.scrollWidth - scrollContainer.clientWidth;
                arrowLeft.classList.toggle('visible', sc > 5);
                arrowRight.classList.toggle('visible', sc < maxScroll - 5);
            }

            scrollContainer.addEventListener('scroll', updateArrows);
            window.addEventListener('resize', function () {
                updateArrows();
            });

            arrowLeft.addEventListener('click', function () {
                var step = scrollContainer.clientWidth;
                scrollContainer.scrollBy({
                    left: -step,
                    behavior: 'smooth'
                });
            });
            arrowRight.addEventListener('click', function () {
                var step = scrollContainer.clientWidth;
                scrollContainer.scrollBy({
                    left: step,
                    behavior: 'smooth'
                });
            });

            updateArrows();
        })();

        function setupOptionSwatchListeners() {
            document.querySelectorAll('.poids-options-list .option-swatch-select').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    lbl.closest('.poids-options-list').querySelectorAll('.option-swatch-select').forEach(
                        function (x) {
                            x.classList.remove('selected');
                        });
                    lbl.classList.add('selected');
                    var surc = document.getElementById('option-surcout-poids');
                    if (surc) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('.taille-options-list .option-swatch-select').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    lbl.closest('.taille-options-list').querySelectorAll('.option-swatch-select').forEach(
                        function (x) {
                            x.classList.remove('selected');
                        });
                    lbl.classList.add('selected');
                    var surc = document.getElementById('option-surcout-taille');
                    if (surc) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('input[name="option_poids"]').forEach(function (rad) {
                rad.addEventListener('change', function () {
                    var lbl = this.closest('.option-swatch-select');
                    var surc = document.getElementById('option-surcout-poids');
                    if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('input[name="option_taille"]').forEach(function (rad) {
                rad.addEventListener('change', function () {
                    var lbl = this.closest('.option-swatch-select');
                    var surc = document.getElementById('option-surcout-taille');
                    if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
        }
        setupOptionSwatchListeners();

        var galleryThumbs = document.querySelectorAll('.gallery-thumb');
        var galleryMain = document.getElementById('produit-image-main');
        var galleryPrev = document.querySelector('.gallery-prev');
        var galleryNext = document.querySelector('.gallery-next');
        var galleryList = document.querySelector('.gallery-thumbs-list');
        if (galleryThumbs.length > 0 && galleryMain) {
            var currentIdx = 0;

            function setActiveThumb(idx) {
                galleryThumbs.forEach(function (t, i) {
                    t.classList.toggle('active', i === idx);
                });
                currentIdx = idx;
                var src = galleryThumbs[idx].getAttribute('data-src');
                if (src) galleryMain.src = src;
            }
            galleryThumbs.forEach(function (thumb, idx) {
                thumb.addEventListener('click', function () {
                    setActiveThumb(idx);
                });
            });
            if (galleryPrev) galleryPrev.addEventListener('click', function () {
                currentIdx = (currentIdx - 1 + galleryThumbs.length) % galleryThumbs.length;
                setActiveThumb(currentIdx);
                if (galleryList) galleryList.scrollLeft = galleryThumbs[currentIdx].offsetLeft - galleryList
                    .offsetWidth / 2 + 35;
            });
            if (galleryNext) galleryNext.addEventListener('click', function () {
                currentIdx = (currentIdx + 1) % galleryThumbs.length;
                setActiveThumb(currentIdx);
                if (galleryList) galleryList.scrollLeft = galleryThumbs[currentIdx].offsetLeft - galleryList
                    .offsetWidth / 2 + 35;
            });
        }

        if (quantiteInput) {
            quantiteInput.addEventListener('input', updatePrixTotal);
            quantiteInput.addEventListener('change', function () {
                let value = parseInt(this.value) || 1;
                if (value < 1) value = 1;
                if (value > maxStock) value = maxStock;
                this.value = value;
                updatePrixTotal();
            });
        }

        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value > 1) {
                    value--;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        if (increaseBtn) {
            increaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value < maxStock) {
                    value++;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        // Initialiser le prix total au chargement
        if (quantiteInput) {
            updatePrixTotal();
        }

        // Gestion du message de succès/erreur
        function closeMessage() {
            const message = document.getElementById('message-alert');
            if (message) {
                message.classList.add('fade-out');
                setTimeout(() => {
                    message.style.display = 'none';
                    // Supprimer le paramètre ?added=success de l'URL
                    if (window.location.search.includes('added=success')) {
                        const url = new URL(window.location);
                        url.searchParams.delete('added');
                        window.history.replaceState({}, '', url);
                    }
                }, 300);
            }
        }

        // Fermer automatiquement après 3 secondes si c'est un message de succès
        document.addEventListener('DOMContentLoaded', function () {
            const message = document.getElementById('message-alert');
            if (message && message.classList.contains('success')) {
                setTimeout(() => {
                    closeMessage();
                }, 3000);
            }
        });
    </script>

</body>

</html>