<?php
/**
 * Configuration Firebase - Source unique pour toutes les pages
 * En cas d'erreur "API key not valid", voir FIX_API_KEY_NOTIFICATIONS.md
 */
$firebase_config = require __DIR__ . '/../config/firebase_config.php';
?>
<script>
    window.FIREBASE_CONFIG = <?php echo json_encode([
        'apiKey' => $firebase_config['apiKey'],
        'authDomain' => $firebase_config['authDomain'],
        'projectId' => $firebase_config['projectId'],
        'storageBucket' => $firebase_config['storageBucket'],
        'messagingSenderId' => $firebase_config['messagingSenderId'],
        'appId' => $firebase_config['appId'],
        'measurementId' => $firebase_config['measurementId'] ?? null
    ]); ?>;
</script>
