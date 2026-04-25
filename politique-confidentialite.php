<?php
session_start();

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Politique de Confidentialité - FOUTA POIDS LOURDS';
$seo_description = 'Politique de confidentialité et protection des données personnelles de FOUTA POIDS LOURDS, vente de pièces de véhicules poids lourds. Vos informations sont sécurisées.';
$seo_canonical = $base . '/politique-confidentialite.php';
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
        <h1><i class="fas fa-shield-alt"></i> Politique de Confidentialité</h1>
        
        <p><strong>Dernière mise à jour : <?php echo date('d/m/Y'); ?></strong></p>
        
        <h2>1. Introduction</h2>
        <p>
            <strong>FOUTA POIDS LOURDS</strong> est une boutique en ligne spécialisée dans la vente de pièces de véhicules poids lourds (camions, bus, tracteurs, remorques, cylindres). Nous nous engageons à protéger la confidentialité de vos informations personnelles. 
            Cette politique de confidentialité explique comment nous collectons, utilisons et protégeons vos données 
            lorsque vous utilisez notre site et nos services.
        </p>
        
        <h2>2. Données Collectées</h2>
        <p>Dans le cadre de nos activités (vente de pièces de véhicules poids lourds, commandes sur mesure), nous collectons les informations suivantes :</p>
        <ul>
            <li>Nom et prénom</li>
            <li>Adresse email</li>
            <li>Numéro de téléphone</li>
            <li>Adresse de livraison et zone de livraison</li>
            <li>Informations relatives à vos commandes (produits, quantités, personnalisations)</li>
            <li>Instructions spéciales pour les livraisons (codes d'accès, étage, etc.)</li>
            <li>Données de navigation et cookies</li>
        </ul>
        
        <h2>3. Utilisation des Données</h2>
        <p>Vos données sont utilisées pour :</p>
        <ul>
            <li>Traiter et préparer vos commandes de gâteaux et accessoires de pâtisserie</li>
            <li>Personnaliser vos produits selon vos demandes</li>
            <li>Gérer la livraison de vos commandes</li>
            <li>Vous contacter concernant vos commandes (confirmation, suivi, livraison)</li>
            <li>Améliorer nos services et votre expérience sur le site</li>
            <li>Vous envoyer des communications marketing (uniquement avec votre consentement explicite)</li>
        </ul>
        
        <h2>4. Protection des Données</h2>
        <p>
            Nous mettons en œuvre des mesures de sécurité appropriées pour protéger vos informations personnelles 
            contre tout accès non autorisé, modification, divulgation ou destruction. Vos données de paiement 
            et informations sensibles sont traitées avec une attention particulière.
        </p>
        
        <h2>5. Partage des Données</h2>
        <p>
            Nous ne vendons, n'échangeons ni ne louons vos informations personnelles à des tiers. 
            Vos données peuvent être partagées uniquement avec :
        </p>
        <ul>
            <li>Nos prestataires de livraison pour l'acheminement de vos commandes</li>
            <li>Les autorités compétentes en cas d'obligation légale</li>
        </ul>
        
        <h2>6. Conservation des Données</h2>
        <p>
            Nous conservons vos données personnelles pendant la durée nécessaire à l'exécution de nos services 
            et au respect de nos obligations légales (notamment comptables). Les données relatives aux commandes 
            sont conservées conformément à la réglementation en vigueur.
        </p>
        
        <h2>7. Vos Droits</h2>
        <p>Conformément à la réglementation sur la protection des données, vous disposez des droits suivants :</p>
        <ul>
            <li><strong>Droit d'accès</strong> : obtenir une copie de vos données personnelles</li>
            <li><strong>Droit de rectification</strong> : corriger vos informations inexactes ou incomplètes</li>
            <li><strong>Droit à l'effacement</strong> : demander la suppression de votre compte et de vos données</li>
            <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données à des fins marketing</li>
        </ul>
        <p>Pour exercer ces droits, contactez-nous à l'adresse indiquée ci-dessous.</p>
        
        <h2>8. Cookies</h2>
        <p>
            Notre site utilise des cookies pour améliorer votre expérience de navigation (mémorisation du panier, 
            préférences, session). Vous pouvez configurer ou désactiver les cookies dans les paramètres de votre navigateur. 
            Certaines fonctionnalités du site pourraient être limitées en cas de désactivation.
        </p>
        
        <h2>9. Contact</h2>
        <p>
            Pour toute question concernant cette politique de confidentialité ou pour exercer vos droits, 
            contactez-nous à : 
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
