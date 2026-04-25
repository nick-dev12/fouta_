<?php
session_start();

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = "Conditions d'Utilisation - FOUTA POIDS LOURDS";
$seo_description = "Conditions générales d'utilisation du site FOUTA POIDS LOURDS. Règles et modalités de notre boutique de pièces de véhicules poids lourds.";
$seo_canonical = $base . '/conditions-utilisation.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <style>
        .legal-page {
            max-width: 900px;
            margin: 40px auto;
            padding: 40px 20px 80px;
            background: var(--blanc);
            border-radius: 10px;
            box-shadow: var(--ombre-douce);
        }
        .legal-page h1 {
            color: var(--titres);
            font-size: 32px;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 3px solid var(--couleur-dominante);
            padding-bottom: 15px;
        }
        .legal-page h2 {
            color: var(--titres);
            font-size: 20px;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .legal-page p {
            color: var(--texte-fonce);
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 15px;
            text-align: justify;
        }
        .legal-page ul {
            margin: 15px 0;
            padding-left: 30px;
        }
        .legal-page li {
            color: var(--texte-fonce);
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 10px;
        }
        .legal-page a {
            color: var(--couleur-dominante);
            text-decoration: none;
        }
        .legal-page a:hover {
            color: var(--orange);
            text-decoration: underline;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding: 12px 24px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            background: var(--orange);
            color: var(--texte-clair);
        }
    </style>
</head>
<body>
    <?php include('nav_bar.php'); ?>
    
    <div class="legal-page">
        <h1><i class="fas fa-file-contract"></i> Conditions d'Utilisation</h1>
        
        <p><strong>Dernière mise à jour : <?php echo date('d/m/Y'); ?></strong></p>
        
        <h2>1. Acceptation des Conditions</h2>
        <p>
            En accédant et en utilisant le site <strong>FOUTA POIDS LOURDS</strong>, boutique en ligne spécialisée dans la vente de pièces de véhicules poids lourds (camions, bus, tracteurs, remorques, cylindres), vous acceptez d'être lié par ces conditions d'utilisation. 
            Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser notre site.
        </p>
        
        <h2>2. Utilisation du Site</h2>
        <p>Vous vous engagez à :</p>
        <ul>
            <li>Utiliser le site de manière légale et conforme à son objet commercial</li>
            <li>Fournir des informations exactes et à jour lors de votre inscription et de vos commandes</li>
            <li>Ne pas tenter d'accéder à des zones non autorisées ou de perturber le fonctionnement du site</li>
            <li>Ne pas utiliser le site à des fins frauduleuses ou illicites</li>
        </ul>
        
        <h2>3. Commandes et Paiements</h2>
        <p>
            Toutes les commandes de pièces de véhicules sont soumises à notre acceptation. 
            Nous nous réservons le droit de refuser ou d'annuler toute commande en cas de stock insuffisant, 
            d'erreur de prix ou pour toute autre raison légitime. Les prix sont indiqués en FCFA et peuvent 
            être modifiés à tout moment. Le montant total inclut le sous-total des produits et les frais de 
            livraison selon la zone sélectionnée.
        </p>
        
        <h2>4. Produits et Personnalisation</h2>
        <p>
            Nous nous efforçons d'afficher nos pièces de véhicules avec précision. 
            Les produits personnalisés sont réalisés selon vos indications ; toutefois, une légère 
            variation par rapport à la description ou à l'image peut survenir. Nous ne garantissons pas 
            que les descriptions, images ou autres contenus sont exacts, complets ou à jour en permanence.
        </p>
        
        <h2>5. Livraison</h2>
        <p>
            Les délais de livraison sont indicatifs et peuvent varier selon la zone et la charge de travail. 
            Vous devez sélectionner une zone de livraison valide parmi celles proposées sur le site. 
            Nous ne sommes pas responsables des retards dus à des circonstances indépendantes de notre volonté 
            (conditions météo, trafic, etc.). En cas de retard significatif, nous vous contacterons.
        </p>
        
        <h2>6. Annulation et Retours</h2>
        <p>
            Les produits alimentaires (gâteaux) ne peuvent généralement pas être repris pour des raisons 
            d'hygiène. Pour les accessoires de pâtisserie non alimentaires, les retours peuvent être 
            envisagés sous conditions. Contactez-nous avant toute démarche. En cas d'annulation de commande 
            par le client, celle-ci doit être effectuée avant la préparation du produit.
        </p>
        
        <h2>7. Propriété Intellectuelle</h2>
        <p>
            Tout le contenu du site (textes, images, logos, recettes, créations) est la propriété de 
            <strong>Sugar Paper</strong> et est protégé par les lois sur la propriété intellectuelle. 
            Toute reproduction sans autorisation préalable est interdite.
        </p>
        
        <h2>8. Limitation de Responsabilité</h2>
        <p>
            FOUTA POIDS LOURDS ne sera pas responsable des dommages directs, indirects, accessoires ou 
            consécutifs résultant de l'utilisation ou de l'impossibilité d'utiliser le site, 
            sauf en cas de faute lourde ou de manquement à une obligation essentielle du contrat.
        </p>
        
        <h2>9. Modifications</h2>
        <p>
            Nous nous réservons le droit de modifier ces conditions d'utilisation à tout moment. 
            Les modifications entrent en vigueur dès leur publication sur le site. 
            Nous vous invitons à les consulter régulièrement.
        </p>
        
        <h2>10. Contact</h2>
        <p>
            Pour toute question concernant ces conditions d'utilisation, contactez-nous à : 
<a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a>
            ou par téléphone au <a href="tel:+221338700070">+221 33 870 00 70</a>.
        </p>
        
        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
    
    <?php include('footer.php'); ?>
</body>
</html>
