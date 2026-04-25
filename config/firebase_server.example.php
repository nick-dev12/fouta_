<?php
/**
 * Configuration Firebase côté serveur (notifications push FCM)
 * Copiez en config/firebase_server.php et ajustez le chemin du fichier credentials
 * NE JAMAIS committer le fichier credentials JSON ni config/firebase_server.php
 */

return [
    // Chemin vers le fichier JSON du compte de service Firebase
    'credentials_path' => __DIR__ . '/../sugar-paper-d34851eeca5a.json',
    // Chemin vers cacert.pem (corrige erreur cURL 60 sur Windows) - exécutez fix_ssl_certificates.php
    'cacert_path' => __DIR__ . '/cacert.pem',
];
