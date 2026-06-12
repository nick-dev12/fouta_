<?php
require_once __DIR__ . '/includes/session_user.php';
session_start();

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = "Conditions d'Utilisation - FOUTA POIDS LOURDS";
$seo_description = "Conditions générales d'utilisation du site et de l'application FOUTA POIDS LOURDS — vente de pièces détachées pour véhicules poids lourds.";
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
    <?php include __DIR__ . '/includes/legal_page_styles.php'; ?>
</head>
<body>
    <?php include('nav_bar.php'); ?>

    <div class="legal-page">
        <h1><i class="fas fa-file-contract"></i> Conditions générales d'utilisation</h1>
        <p><strong>Dernière mise à jour : <?php echo date('d/m/Y'); ?></strong></p>

        <ul class="legal-nav">
            <li><a href="/politique-confidentialite.php">Politique de confidentialité</a></li>
            <li><a href="/politique-suppression-compte.php">Suppression de compte</a></li>
        </ul>

        <h2>1. Éditeur et objet</h2>
        <p>
            Les présentes conditions générales d'utilisation (ci-après « CGU ») régissent l'accès et l'utilisation du site
            <strong>FOUTA POIDS LOURDS</strong> accessible à l'adresse
            <a href="https://www.e.foutapoidslourds.com/">https://www.e.foutapoidslourds.com/</a>,
            ainsi que de l'application mobile associée (ci-après « la Plateforme »).
        </p>
        <p>
            <strong>FOUTA POIDS LOURDS</strong> est une boutique en ligne spécialisée dans la vente de pièces détachées
            pour véhicules poids lourds (camions, bus, tracteurs, remorques, cylindres) et l'approvisionnement
            des professionnels du transport et de la mécanique.
        </p>
        <p>
            Siège / contact : Rond point Zack Mbao, Dakar — Tél. <a href="tel:+221338700070">+221 33 870 00 70</a> —
            E-mail : <a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a>.
        </p>

        <h2>2. Acceptation</h2>
        <p>
            En naviguant sur la Plateforme, en créant un compte ou en passant commande, vous reconnaissez avoir lu,
            compris et accepté sans réserve les présentes CGU ainsi que notre
            <a href="/politique-confidentialite.php">politique de confidentialité</a>.
            Si vous n'acceptez pas ces documents, vous devez cesser d'utiliser la Plateforme.
        </p>

        <h2>3. Compte utilisateur</h2>
        <ul>
            <li>L'inscription requiert des informations exactes, complètes et à jour (identité, coordonnées, type de client le cas échéant).</li>
            <li>Vous êtes responsable de la confidentialité de vos identifiants et de toute activité réalisée depuis votre compte.</li>
            <li>Vous vous engagez à nous informer sans délai de toute utilisation non autorisée.</li>
            <li>FOUTA POIDS LOURDS peut suspendre ou clôturer un compte en cas de violation des CGU, de fraude ou d'usage abusif.</li>
            <li>La suppression de compte est décrite dans notre <a href="/politique-suppression-compte.php">politique de suppression de compte</a>.</li>
        </ul>

        <h2>4. Commandes, prix et disponibilité</h2>
        <p>
            Les fiches produits (références, compatibilités, photos, prix en FCFA) sont présentées avec le plus grand soin.
            Toutefois, des erreurs typographiques, des ruptures de stock ou des mises à jour tarifaires peuvent survenir.
        </p>
        <ul>
            <li>Toute commande constitue une offre d'achat soumise à notre acceptation (confirmation écrite ou électronique).</li>
            <li>Les prix affichés peuvent être modifiés à tout moment ; le prix applicable est celui confirmé lors de la validation de la commande.</li>
            <li>En cas d'indisponibilité d'un article après commande, nous vous contacterons pour proposer un substitut, un délai ou un remboursement.</li>
        </ul>

        <h2>5. Paiement et facturation</h2>
        <p>
            Les modalités de paiement acceptées sont indiquées lors du passage de commande.
            Les factures et documents comptables sont conservés conformément aux obligations légales en vigueur au Sénégal.
        </p>

        <h2>6. Livraison et retrait</h2>
        <p>
            Les zones, délais et frais de livraison sont précisés lors de la commande.
            Vous devez fournir une adresse exacte et être joignable aux coordonnées indiquées.
        </p>
        <ul>
            <li>Les délais sont indicatifs et peuvent varier (logistique, disponibilité pièce, force majeure).</li>
            <li>Le transfert de risques et de propriété intervient selon les conditions convenues lors de la remise ou de la livraison.</li>
            <li>En cas de retard significatif, contactez notre service client.</li>
        </ul>

        <h2>7. Garanties, conformité et SAV</h2>
        <p>
            Les pièces proposées sont des pièces détachées destinées à des usages professionnels ou de maintenance.
            Les garanties constructeur ou fournisseur, le cas échéant, sont mentionnées sur la fiche produit ou la facture.
        </p>
        <p>
            Toute réclamation (pièce non conforme, erreur de référence, dommage à réception) doit être signalée dans les
            meilleurs délais avec justificatifs (photos, numéro de commande). Le traitement SAV est effectué au cas par cas
            conformément à la réglementation applicable et à nos procédures internes.
        </p>

        <h2>8. Retours et annulations</h2>
        <p>
            Les conditions de retour dépendent de la nature de la pièce (standard, commande spéciale, produit périssable ou usagé).
            Contactez-nous avant tout retour : <a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a>.
        </p>
        <p>
            L'annulation par le client est possible tant que la commande n'a pas été préparée ou expédiée.
            Passé ce stade, des frais ou un refus d'annulation peuvent s'appliquer.
        </p>

        <h2>9. Utilisation de la Plateforme</h2>
        <p>Il est interdit notamment de :</p>
        <ul>
            <li>Utiliser la Plateforme à des fins illicites, frauduleuses ou portant atteinte aux droits de tiers ;</li>
            <li>Tenter d'accéder aux systèmes, bases de données ou espaces administrateurs sans autorisation ;</li>
            <li>Extraire massivement des données (scraping) ou perturber le fonctionnement du site ;</li>
            <li>Publier ou transmettre des contenus illicites, diffamatoires ou contrefaisants via les formulaires ou le support.</li>
        </ul>

        <h2>10. Application mobile et autorisations</h2>
        <p>
            L'application mobile FOUTA PL charge le site dans une WebView et peut solliciter, <strong>uniquement sur action explicite de l'utilisateur</strong>,
            les autorisations suivantes :
        </p>
        <ul>
            <li><strong>Caméra</strong> : photographier des pièces détachées et produits camions (catalogue, stock) ou la photo d'un employé ;</li>
            <li><strong>Galerie / stockage</strong> : téléverser la photo d'un employé, un contrat de travail, un CV ou un document administratif ;</li>
            <li><strong>Localisation</strong> : livraisons de pièces, confirmation d'adresse client, affichage sur carte ;</li>
            <li><strong>Notifications</strong> : suivi des commandes, livraisons et messages liés au compte.</li>
        </ul>
        <p>
            Le détail du traitement des données liées à ces fonctionnalités figure dans la
            <a href="/politique-confidentialite.php">politique de confidentialité</a>.
        </p>

        <h2>11. Propriété intellectuelle</h2>
        <p>
            L'ensemble des éléments de la Plateforme (textes, visuels, logos, charte graphique, bases de données, logiciels)
            est protégé par le droit de la propriété intellectuelle et appartient à <strong>FOUTA POIDS LOURDS</strong> ou à ses partenaires.
            Toute reproduction, représentation ou exploitation non autorisée est interdite.
        </p>

        <h2>12. Responsabilité</h2>
        <p>
            FOUTA POIDS LOURDS met en œuvre les moyens raisonnables pour assurer l'accès et la sécurité de la Plateforme.
            Sa responsabilité ne saurait être engagée pour les dommages indirects, la perte de chiffre d'affaires,
            ou les conséquences d'une mauvaise utilisation d'une pièce détachée lorsque la référence ou la pose
            n'ont pas été validées par un professionnel qualifié.
        </p>
        <p>
            La responsabilité de FOUTA POIDS LOURDS est, le cas échéant, limitée au montant de la commande concernée,
            sauf faute lourde ou manquement à une obligation essentielle.
        </p>

        <h2>13. Données personnelles</h2>
        <p>
            Le traitement de vos données est décrit dans notre
            <a href="/politique-confidentialite.php">politique de confidentialité</a>.
            Vous disposez de droits d'accès, de rectification, d'opposition et de suppression dans les conditions prévues par la loi.
        </p>

        <h2>14. Cookies et traceurs</h2>
        <p>
            La Plateforme utilise des cookies et technologies similaires (session, panier, préférences, mesure d'audience selon configuration).
            Vous pouvez paramétrer votre navigateur pour limiter ou refuser les cookies ; certaines fonctionnalités pourraient alors être réduites.
        </p>

        <h2>15. Droit applicable et litiges</h2>
        <p>
            Les présentes CGU sont soumises au droit sénégalais.
            En cas de litige, une solution amiable sera recherchée prioritairement.
            À défaut, les tribunaux compétents de Dakar seront seuls compétents, sous réserve des règles impératives applicables aux consommateurs.
        </p>

        <h2>16. Modification des CGU</h2>
        <p>
            FOUTA POIDS LOURDS peut modifier les présentes CGU à tout moment.
            La version en vigueur est celle publiée sur la Plateforme à la date de consultation.
            Nous vous invitons à les consulter régulièrement.
        </p>

        <h2>17. Contact</h2>
        <p>
            Pour toute question relative aux CGU :
            <a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a> —
            <a href="tel:+221338700070">+221 33 870 00 70</a>.
        </p>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
