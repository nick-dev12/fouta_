<?php
/**
 * Page d'aide pour corriger l'erreur "API key not valid"
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corriger la clé API Firebase</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-key"></i> Corriger l'erreur "API key not valid"</h1>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>

        <div class="message error" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i>
            Si vous voyez <strong>"API key not valid. Please pass a valid API key"</strong>, suivez les étapes ci-dessous.
        </div>

        <section class="content-section">
            <h2><i class="fas fa-list-ol"></i> Étapes à suivre</h2>
            <ol style="line-height: 2; margin: 20px 0;">
                <li>
                    <strong>Ouvrez Google Cloud Console</strong><br>
                    <a href="https://console.cloud.google.com/apis/credentials?project=sugar-paper" target="_blank" rel="noopener">
                        https://console.cloud.google.com/apis/credentials?project=sugar-paper
                    </a>
                </li>
                <li>
                    <strong>Cliquez sur votre clé API</strong> (celle qui commence par AIzaSy...)
                </li>
                <li>
                    <strong>Restrictions d'application</strong> :<br>
                    Sélectionnez « Référents HTTP (sites web) » et ajoutez :
                    <ul style="margin-top: 8px;">
                        <li><code>http://localhost:5000/*</code></li>
                        <li><code>http://localhost:5000</code></li>
                        <li><code>http://127.0.0.1:5000/*</code></li>
                        <li><code>http://127.0.0.1:5000</code></li>
                    </ul>
                </li>
                <li>
                    <strong>Restrictions d'API</strong> : Sélectionnez « Ne pas restreindre la clé »
                </li>
                <li>
                    <strong>Cliquez sur Enregistrer</strong>
                </li>
                <li>
                    <strong>Attendez 2 à 5 minutes</strong> que les changements se propagent
                </li>
                <li>
                    <strong>Vérifiez les APIs activées</strong> :<br>
                    <a href="https://console.cloud.google.com/apis/library?project=sugar-paper" target="_blank" rel="noopener">
                        https://console.cloud.google.com/apis/library?project=sugar-paper
                    </a><br>
                    Recherchez et activez : <strong>Firebase Installations API</strong> et <strong>Firebase Cloud Messaging API</strong>
                </li>
            </ol>

            <div class="message success" style="margin-top: 25px;">
                <i class="fas fa-check-circle"></i>
                Après ces modifications, fermez complètement le navigateur, rouvrez-le et réessayez « Activer les notifications ».
            </div>

            <p style="margin-top: 25px;">
                <a href="dashboard.php" class="btn-primary">
                    <i class="fas fa-bell"></i> Retour au tableau de bord
                </a>
            </p>
        </section>
    </div>
</body>
</html>
