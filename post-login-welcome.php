<?php
/**
 * Affichage unique après connexion : loader 3s puis redirection
 * (la cible de session n’est lue qu’une fois, puis supprimée)
 */

require_once __DIR__ . '/includes/post_login_welcome.php';

if (session_status() === PHP_SESSION_NONE) {
    if (is_file(__DIR__ . '/includes/session_user.php')) {
        require_once __DIR__ . '/includes/session_user.php';
    }
    session_start();
}

$target = $_SESSION['just_logged_in_target'] ?? '';
if ($target === '' || $target === null) {
    header('Location: /index.php');
    exit;
}

$is_user = !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']);
$is_admin = !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_email']);
if (!$is_user && !$is_admin) {
    unset($_SESSION['just_logged_in_target']);
    header('Location: /user/connexion.php');
    exit;
}

$next = post_login_sanitize_next_url($target);
unset($_SESSION['just_logged_in_target']);

require_once __DIR__ . '/includes/asset_version.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chargement — FOUTA POIDS LOURDS</title>
    <link rel="stylesheet" href="/css/post-login-welcome.css<?php echo asset_version_query(); ?>">
    <noscript>
        <meta http-equiv="refresh" content="3;url=<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
    </noscript>
</head>
<body class="post-welcome" id="post-welcome">
    <div class="post-welcome__logo-wrap" aria-hidden="true">
        <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS" class="post-welcome__logo" width="280" height="80" />
    </div>
    <div class="post-welcome__load" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="Chargement">
        <div class="post-welcome__load-track">
            <span class="post-welcome__load-bar"></span>
        </div>
    </div>
    <p class="post-welcome__text">
        Bienvenue à <span>FOUTA POIDS LOURDS</span>
    </p>
    <div class="post-welcome__spinner" aria-hidden="true"></div>
    <script>
        (function () {
            var next = <?php echo json_encode($next, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
            var t = 3000;
            window.setTimeout(function () {
                window.location.replace(next);
            }, t);
        })();
    </script>
</body>
</html>
