<?php
require_once __DIR__ . '/includes/session_user.php';
session_start();

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();
$seo_title = 'Politique de suppression de compte - FOUTA POIDS LOURDS';
$seo_description = 'Comment demander la suppression de votre compte FOUTA POIDS LOURDS et quelles données sont effacées ou conservées.';
$seo_canonical = $base . '/politique-suppression-compte.php';
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
        <h1><i class="fas fa-user-slash"></i> Politique de suppression de compte</h1>
        <p><strong>Dernière mise à jour : <?php echo date('d/m/Y'); ?></strong></p>

        <ul class="legal-nav">
            <li><a href="/politique-confidentialite.php">Politique de confidentialité</a></li>
            <li><a href="/conditions-utilisation.php">Conditions d'utilisation</a></li>
        </ul>

        <div class="legal-highlight">
            <p><strong>Demande de suppression de compte</strong></p>
            <p>
                Pour demander la suppression définitive de votre compte client FOUTA POIDS LOURDS, envoyez un e-mail à :
            </p>
            <p style="text-align:center; font-size:1.1rem; margin:12px 0;">
                <a href="mailto:contact@foutapoidslourds.com?subject=Demande%20de%20suppression%20de%20compte">
                    <strong>contact@foutapoidslourds.com</strong>
                </a>
            </p>
            <p>
                Objet recommandé : <em>« Demande de suppression de compte »</em>.
                Indiquez l'adresse e-mail associée à votre compte et, si possible, votre numéro de téléphone
                enregistré afin que nous puissions vérifier votre identité.
            </p>
        </div>

        <h2>1. Champ d'application</h2>
        <p>
            Cette politique décrit la procédure de suppression du <strong>compte utilisateur</strong> créé sur le site
            <a href="https://www.e.foutapoidslourds.com/">https://www.e.foutapoidslourds.com/</a>
            ou utilisé via l'application mobile FOUTA PL.
            Elle complète notre <a href="/politique-confidentialite.php">politique de confidentialité</a>.
        </p>

        <h2>2. Comment faire votre demande</h2>
        <ol>
            <li>Rédigez un e-mail depuis l'adresse liée à votre compte (ou justifiez votre identité si ce n'est plus possible) ;</li>
            <li>Adressez-le à <a href="mailto:contact@foutapoidslourds.com">contact@foutapoidslourds.com</a> ;</li>
            <li>Précisez clairement que vous souhaitez la <strong>suppression définitive</strong> de votre compte ;</li>
            <li>Joignez, si demandé, une pièce d'identité pour confirmer que vous êtes le titulaire du compte.</li>
        </ol>
        <p>
            Nous accuserons réception de votre demande dans un délai raisonnable et vous informerons de la suite du traitement.
            Le délai de suppression effective peut varier selon la charge de traitement et les vérifications de sécurité
            (généralement sous <strong>30 jours</strong> à compter de la validation de votre identité).
        </p>

        <h2>3. Conséquences de la suppression</h2>
        <p>Une fois le compte supprimé :</p>
        <ul>
            <li>Vous ne pourrez plus vous connecter avec les identifiants associés ;</li>
            <li>Votre profil client (nom, e-mail, téléphone, adresses enregistrées) sera effacé ou anonymisé ;</li>
            <li>Les jetons de notification push (FCM) liés à votre compte seront invalidés ;</li>
            <li>Le panier et les préférences liés au compte seront supprimés.</li>
        </ul>
        <p>
            La suppression du compte n'entraîne pas automatiquement la suppression de l'application mobile de votre téléphone :
            vous pouvez la désinstaller séparément depuis les paramètres de votre appareil.
        </p>

        <h2>4. Données conservées après suppression</h2>
        <p>
            Certaines données peuvent être <strong>conservées</strong> même après suppression du compte, lorsque la loi l'impose
            ou lorsque nous avons un intérêt légitime documenté :
        </p>
        <table>
            <thead>
                <tr>
                    <th>Type de données</th>
                    <th>Raison de conservation</th>
                    <th>Durée indicative</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Factures, commandes, paiements</td>
                    <td>Obligations comptables, fiscales et commerciales</td>
                    <td>Durée légale (souvent jusqu'à 10 ans pour les pièces comptables)</td>
                </tr>
                <tr>
                    <td>Preuves de litige, réclamations SAV</td>
                    <td>Défense de nos droits en justice ou gestion de réclamation</td>
                    <td>Durée du litige + délais de prescription applicables</td>
                </tr>
                <tr>
                    <td>Logs de sécurité (IP, horodatage)</td>
                    <td>Prévention de la fraude et sécurité des systèmes</td>
                    <td>Durée limitée (plusieurs mois selon politique interne)</td>
                </tr>
                <tr>
                    <td>Données anonymisées / agrégées</td>
                    <td>Statistiques ne permettant plus de vous identifier</td>
                    <td>Sans limitation tant qu'elles restent anonymes</td>
                </tr>
            </tbody>
        </table>
        <p>
            Les données conservées ne sont plus utilisées à des fins commerciales ou marketing vous concernant personnellement,
            sauf obligation légale contraire.
        </p>

        <h2>5. Données supprimées</h2>
        <p>Sont en principe <strong>supprimés ou anonymisés</strong> :</p>
        <ul>
            <li>Identifiants de connexion (e-mail / mot de passe) ;</li>
            <li>Coordonnées personnelles du profil non requises légalement ;</li>
            <li>Adresses de livraison enregistrées sur le compte ;</li>
            <li>Jetons FCM et préférences de notification liés au compte ;</li>
            <li>Historique de navigation sauvegardé côté serveur lorsqu'il est rattaché uniquement au compte.</li>
        </ul>

        <h2>6. Compte professionnel / commandes en cours</h2>
        <p>
            Si des commandes sont en cours de traitement, de livraison ou de facturation au moment de votre demande,
            nous pourrons retarder la clôture du compte jusqu'à leur finalisation, ou procéder à une anonymisation
            partielle tout en conservant les éléments nécessaires à l'exécution contractuelle.
        </p>

        <h2>7. Alternative : désactivation</h2>
        <p>
            Si vous souhaitez uniquement cesser d'utiliser le service sans supprimer l'historique légal de vos commandes,
            contactez-nous pour une <strong>désactivation</strong> du compte (accès bloqué, données conservées selon les durées légales).
        </p>

        <h2>8. Droits complémentaires</h2>
        <p>
            Outre la suppression du compte, vous pouvez exercer vos droits d'accès, de rectification et d'opposition
            conformément à la <a href="/politique-confidentialite.php">politique de confidentialité</a>,
            à la même adresse : <a href="mailto:contact@foutapoidslourds.com">contact@foutapoidslourds.com</a>.
        </p>

        <h2>9. Contact</h2>
        <p>
            <strong>Suppression de compte :</strong>
            <a href="mailto:contact@foutapoidslourds.com">contact@foutapoidslourds.com</a><br>
            <strong>Service client :</strong>
            <a href="mailto:info@foutapoidslourds.com">info@foutapoidslourds.com</a> —
            <a href="tel:+221338700070">+221 33 870 00 70</a><br>
            <strong>Adresse :</strong> Rond point Zack Mbao, Dakar
        </p>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
