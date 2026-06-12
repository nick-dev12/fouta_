<?php
require_once __DIR__ . '/includes/session_user.php';
session_start();

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Politique de Confidentialité - FOUTA POIDS LOURDS';
$seo_description = 'Politique de confidentialité et protection des données — FOUTA POIDS LOURDS, site et application mobile.';
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
    <?php include __DIR__ . '/includes/legal_page_styles.php'; ?>
</head>
<body>
    <?php include('nav_bar.php'); ?>

    <div class="legal-page">
        <h1><i class="fas fa-shield-alt"></i> Politique de confidentialité</h1>
        <p><strong>Dernière mise à jour : <?php echo date('d/m/Y'); ?></strong></p>

        <ul class="legal-nav">
            <li><a href="/conditions-utilisation.php">Conditions d'utilisation</a></li>
            <li><a href="/politique-suppression-compte.php">Suppression de compte</a></li>
        </ul>

        <h2>1. Responsable du traitement</h2>
        <p>
            <strong>FOUTA POIDS LOURDS</strong><br>
            Rond point Zack Mbao, Dakar, Sénégal<br>
            Téléphone : <a href="tel:+221338700070">+221 33 870 00 70</a><br>
            E-mail général : <a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a><br>
            E-mail données personnelles / suppression de compte :
            <a href="mailto:contact@foutapoidslourds.com">contact@foutapoidslourds.com</a>
        </p>
        <p>
            La présente politique s'applique au site
            <a href="https://www.e.foutapoidslourds.com/">https://www.e.foutapoidslourds.com/</a>,
            à l'application mobile FOUTA PL et aux services associés (commande en ligne, compte client, notifications).
        </p>

        <h2>2. Principes</h2>
        <p>
            Nous collectons uniquement les données nécessaires à la gestion commerciale, à la relation client et au bon
            fonctionnement de la Plateforme. Nous ne vendons pas vos données personnelles.
            Les traitements respectent les principes de licéité, loyauté, transparence, limitation des finalités,
            minimisation, exactitude, limitation de conservation et sécurité.
        </p>

        <h2>3. Données collectées</h2>
        <table>
            <thead>
                <tr>
                    <th>Catégorie</th>
                    <th>Exemples</th>
                    <th>Origine</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Identité &amp; compte</td>
                    <td>Nom, prénom, e-mail, téléphone, mot de passe (hashé), type de client (particulier / pro)</td>
                    <td>Inscription, formulaire compte</td>
                </tr>
                <tr>
                    <td>Commandes &amp; facturation</td>
                    <td>Produits, quantités, montants, adresse de livraison, zone, historique, numéro de commande</td>
                    <td>Passage de commande</td>
                </tr>
                <tr>
                    <td>Support &amp; communication</td>
                    <td>Messages, pièces jointes (photos), demandes SAV</td>
                    <td>Contact volontaire</td>
                </tr>
                <tr>
                    <td>Navigation &amp; technique</td>
                    <td>Adresse IP, logs, cookies, identifiant de session, pages visitées, appareil / navigateur</td>
                    <td>Automatique</td>
                </tr>
                <tr>
                    <td>Application mobile</td>
                    <td>Jeton de notification push (FCM), type d'appareil, URL de navigation sauvegardée localement sur le téléphone</td>
                    <td>App FOUTA PL (avec consentement)</td>
                </tr>
                <tr>
                    <td>Localisation</td>
                    <td>Coordonnées GPS ponctuelles</td>
                    <td>Uniquement si vous activez la fonction sur le site / l'app</td>
                </tr>
                <tr>
                    <td>Authentification tierce</td>
                    <td>Identifiant Google (si connexion Google activée), jeton Firebase Auth</td>
                    <td>Action volontaire</td>
                </tr>
            </tbody>
        </table>

        <h2>4. Finalités et bases légales</h2>
        <ul>
            <li><strong>Exécution du contrat</strong> : création de compte, traitement et suivi des commandes, livraison, SAV, facturation.</li>
            <li><strong>Obligation légale</strong> : conservation comptable et fiscale, réponse aux autorités compétentes.</li>
            <li><strong>Intérêt légitime</strong> : sécurité du site, prévention de la fraude, amélioration du service, statistiques agrégées.</li>
            <li><strong>Consentement</strong> : notifications push, cookies non essentiels, communications marketing (si proposées et acceptées).</li>
        </ul>

        <h2>5. Destinataires et sous-traitants</h2>
        <p>Vos données peuvent être communiquées, dans la stricte limite de leur mission, à :</p>
        <ul>
            <li>Personnel habilité de FOUTA POIDS LOURDS ;</li>
            <li>Prestataires de livraison et logistique ;</li>
            <li>Hébergeur et prestataires techniques (hébergement, messagerie, maintenance) ;</li>
            <li><strong>Google Firebase</strong> (notifications push FCM, authentification selon configuration) — projet hébergé chez Google ;</li>
            <li>Autorités administratives ou judiciaires lorsque la loi l'exige.</li>
        </ul>
        <p>
            Les sous-traitants sont tenus par des obligations contractuelles de confidentialité et de sécurité.
            Certains peuvent être situés hors du Sénégal ; dans ce cas, des garanties appropriées sont mises en place
            lorsque requis par la réglementation.
        </p>

        <h2>6. Durées de conservation</h2>
        <table>
            <thead>
                <tr>
                    <th>Données</th>
                    <th>Durée indicative</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Compte client actif</td>
                    <td>Tant que le compte est actif, puis archivage ou suppression selon demande et obligations légales</td>
                </tr>
                <tr>
                    <td>Commandes &amp; factures</td>
                    <td>Durée légale de conservation comptable (généralement 10 ans pour les pièces comptables)</td>
                </tr>
                <tr>
                    <td>Jetons FCM (notifications)</td>
                    <td>Jusqu'à désinstallation de l'app, retrait du consentement ou invalidation du jeton</td>
                </tr>
                <tr>
                    <td>Cookies de session</td>
                    <td>Durée de la session ou selon paramétrage (voir section cookies)</td>
                </tr>
                <tr>
                    <td>Logs techniques</td>
                    <td>Durée limitée nécessaire à la sécurité et au diagnostic (ex. plusieurs mois)</td>
                </tr>
            </tbody>
        </table>
        <p>
            Au-delà de ces durées, les données sont supprimées ou anonymisées, sauf obligation légale de conservation plus longue.
        </p>

        <h2>7. Sécurité</h2>
        <p>
            Nous mettons en œuvre des mesures techniques et organisationnelles adaptées : mots de passe hashés,
            accès restreints, connexions sécurisées (HTTPS), sauvegardes, journalisation.
            Aucune transmission sur Internet n'est totalement invulnérable ; nous vous invitons à protéger vos identifiants.
        </p>

        <h2>8. Vos droits</h2>
        <p>Conformément à la réglementation applicable en matière de protection des données personnelles, vous disposez notamment des droits suivants :</p>
        <ul>
            <li><strong>Droit d'accès</strong> et de copie de vos données ;</li>
            <li><strong>Droit de rectification</strong> des données inexactes ;</li>
            <li><strong>Droit à l'effacement</strong> (« droit à l'oubli ») dans les limites légales ;</li>
            <li><strong>Droit d'opposition</strong> au traitement fondé sur l'intérêt légitime ou à des fins de prospection ;</li>
            <li><strong>Droit à la limitation</strong> du traitement dans certains cas ;</li>
            <li><strong>Droit de retirer votre consentement</strong> à tout moment pour les traitements qui en dépendent.</li>
        </ul>
        <p>
            Pour exercer vos droits : <a href="mailto:contact@foutapoidslourds.com">contact@foutapoidslourds.com</a>
            (objet : « Données personnelles »), en joignant une copie d'une pièce d'identité si nécessaire pour vérifier votre identité.
        </p>
        <p>
            Pour la <strong>suppression de compte</strong>, consultez notre page dédiée :
            <a href="/politique-suppression-compte.php">Politique de suppression de compte</a>.
        </p>

        <h2>9. Application mobile — autorisations</h2>
        <div class="legal-highlight">
            <p><strong>Caméra</strong> — Lorsque vous appuyez sur « Prendre une photo » : photographier des pièces détachées et produits camions (catalogue, stock) ou la photo d'un employé. Exemple : photo d'un amortisseur châssis ou photo d'identité d'un collaborateur.</p>
            <p><strong>Galerie / stockage</strong> — Lorsque vous importez ou téléversez un fichier : photo d'employé, contrat de travail, CV ou document administratif. Exemple : joindre le CV d'un nouvel employé.</p>
            <p><strong>Localisation</strong> — Lorsque vous l'activez : livraisons de pièces, adresse de livraison client, point sur la carte. Exemple : lieu de livraison d'une commande. Pas de suivi en arrière-plan.</p>
            <p><strong>Notifications</strong> — Suivi des commandes, livraisons et compte. Désactivables dans les paramètres du téléphone.</p>
        </div>
        <p>
            Avant toute demande système, l'application affiche une explication de la finalité (conformément aux exigences des stores Apple et Google).
        </p>

        <h2>10. Cookies et traceurs web</h2>
        <p>Nous utilisons notamment :</p>
        <ul>
            <li><strong>Cookies strictement nécessaires</strong> : session, panier, authentification ;</li>
            <li><strong>Cookies de fonctionnement</strong> : préférences d'affichage ;</li>
            <li><strong>Cookies de mesure</strong> (le cas échéant) : statistiques de fréquentation anonymisées ou pseudonymisées.</li>
        </ul>
        <p>
            Vous pouvez configurer votre navigateur pour refuser les cookies. L'installation de l'application PWA ou l'usage
            de l'app native peut impliquer un stockage local équivalent (cache, préférences).
        </p>

        <h2>11. Mineurs</h2>
        <p>
            La Plateforme s'adresse principalement à des professionnels et à des adultes.
            Nous ne collectons pas sciemment de données concernant des mineurs sans consentement parental requis.
        </p>

        <h2>12. Modifications</h2>
        <p>
            Cette politique peut être mise à jour. La date en tête de page indique la dernière révision.
            En cas de changement substantiel, nous pourrons vous en informer par e-mail ou via un avis sur la Plateforme.
        </p>

        <h2>13. Contact et réclamation</h2>
        <p>
            Questions ou exercice de vos droits :
            <a href="mailto:contact@foutapoidslourds.com">contact@foutapoidslourds.com</a> —
            <a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a> —
            <a href="tel:+221338700070">+221 33 870 00 70</a>.
        </p>
        <p>
            Si vous estimez, après nous avoir contactés, que vos droits ne sont pas respectés,
            vous pouvez introduire une réclamation auprès de l'autorité de protection des données compétente au Sénégal.
        </p>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
