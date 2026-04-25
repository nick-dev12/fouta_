<?php
/**
 * Page de vérification de la configuration Firebase
 * Affiche la config actuelle et les instructions pour la corriger
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$config = [
    'apiKey' => 'AIzaSyAOGTcYf7i-Jj6jj5KuTOJboFVagkbdBW4',
    'projectId' => 'sugar-paper',
    'messagingSenderId' => '409713248489',
    'appId' => '1:409713248489:web:6bff9f5584e52c05a04878',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification config Firebase - Admin</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="contents-container">
        <div class="content-header">
            <h1><i class="fas fa-key"></i> Configuration Firebase</h1>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <div class="detail-box" style="max-width: 700px;">
            <h3>Configuration actuelle (côté client)</h3>
            <p style="margin-bottom: 15px; color: var(--texte-fonce);">Si vous avez l'erreur "API key not valid", la clé API ci-dessous doit être corrigée dans Google Cloud Console.</p>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px; font-weight: 600;">apiKey</td><td style="padding: 8px; word-break: break-all;"><?php echo htmlspecialchars($config['apiKey']); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">projectId</td><td style="padding: 8px;"><?php echo htmlspecialchars($config['projectId']); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">messagingSenderId</td><td style="padding: 8px;"><?php echo htmlspecialchars($config['messagingSenderId']); ?></td></tr>
                <tr><td style="padding: 8px; font-weight: 600;">appId</td><td style="padding: 8px;"><?php echo htmlspecialchars($config['appId']); ?></td></tr>
            </table>
        </div>
        <div class="detail-box" style="max-width: 700px; margin-top: 20px;">
            <h3><i class="fas fa-wrench"></i> Correction rapide</h3>
            <ol style="line-height: 2; color: var(--texte-fonce);">
                <li>Ouvrez <a href="https://console.cloud.google.com/apis/credentials?project=sugar-paper" target="_blank">Google Cloud Console - Identifiants</a></li>
                <li>Trouvez la clé API qui correspond à la valeur ci-dessus</li>
                <li>Cliquez sur l'icône crayon pour la modifier</li>
                <li>Dans <strong>"Restrictions relatives aux API"</strong>, sélectionnez <strong>"Ne pas restreindre la clé"</strong></li>
                <li>Enregistrez et attendez 2-5 minutes</li>
            </ol>
            <p><a href="../FIX_API_KEY_NOTIFICATIONS.md" target="_blank">Voir le guide complet (FIX_API_KEY_NOTIFICATIONS.md)</a></p>
        </div>
    </div>
</body>
</html>
