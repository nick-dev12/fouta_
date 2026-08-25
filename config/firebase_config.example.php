<?php
/**
 * Configuration Firebase côté navigateur (notifications push FCM)
 * Copiez ce fichier en config/firebase_config.php — config/ est gitignoré.
 *
 * Ces valeurs ne sont pas des secrets : elles sont envoyées telles quelles à
 * chaque navigateur par includes/firebase_init.php, et le service worker
 * public firebase-messaging-sw.js (versionné) les porte déjà en dur. Les vrais
 * secrets sont ailleurs : config/firebase_server.php et le JSON du compte de
 * service, qui eux ne sortent jamais du serveur.
 *
 * Les valeurs ci-dessous DOIVENT rester identiques à celles de
 * firebase-messaging-sw.js, sinon le jeton FCM du premier plan et celui de
 * l'arrière-plan ne désignent plus le même projet.
 *
 * Sans config/firebase_config.php, la page s'affiche normalement et les
 * notifications restent simplement inactives.
 * En cas d'erreur "API key not valid", voir FIX_API_KEY_NOTIFICATIONS.md
 */

return [
    'apiKey' => 'AIzaSyAOGTcYf7i-Jj6jj5KuTOJboFVagkbdBW4',
    'authDomain' => 'sugar-paper.firebaseapp.com',
    'projectId' => 'sugar-paper',
    'storageBucket' => 'sugar-paper.firebasestorage.app',
    'messagingSenderId' => '409713248489',
    'appId' => '1:409713248489:web:6bff9f5584e52c05a04878',
    // Facultatif (Google Analytics) : laissez la clé absente si non utilisée
    // 'measurementId' => 'G-XXXXXXXXXX',
];
