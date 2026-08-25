<?php
/**
 * Configuration Firebase - Source unique pour toutes les pages
 *
 * Copiez config/firebase_config.example.php en config/firebase_config.php
 * (config/ est gitignoré, chaque poste a le sien).
 *
 * Sans ce fichier, window.FIREBASE_CONFIG n'est pas défini : la page s'affiche
 * normalement et les notifications restent inactives, au lieu de tuer la page.
 * Même patron défensif que services/firebase_push.php et services/mail.php.
 *
 * En cas d'erreur "API key not valid", voir FIX_API_KEY_NOTIFICATIONS.md
 */
$firebase_config_path = __DIR__ . '/../config/firebase_config.php';
$firebase_config = file_exists($firebase_config_path) ? require $firebase_config_path : null;
$firebase_config_utilisable = is_array($firebase_config) && !empty($firebase_config['apiKey']);
?>
<?php if ($firebase_config_utilisable): ?>
<script>
    window.FIREBASE_CONFIG = <?php echo json_encode([
        'apiKey' => $firebase_config['apiKey'],
        'authDomain' => $firebase_config['authDomain'] ?? null,
        'projectId' => $firebase_config['projectId'] ?? null,
        'storageBucket' => $firebase_config['storageBucket'] ?? null,
        'messagingSenderId' => $firebase_config['messagingSenderId'] ?? null,
        'appId' => $firebase_config['appId'] ?? null,
        'measurementId' => $firebase_config['measurementId'] ?? null
    ]); ?>;
</script>
<?php else: ?>
<!-- Firebase : config/firebase_config.php absent ou incomplet, notifications inactives -->
<?php endif; ?>
