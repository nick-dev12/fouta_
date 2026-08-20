<?php
/**
 * Gabarit auth admin FPL — corps de page (.auth)
 *
 * Variables attendues :
 *   $auth_title (string)
 *   $auth_lead (string, optionnel)
 *   $auth_form_html (string) — contenu du formulaire
 *   $auth_extra_html (string, optionnel) — alertes, liens
 */
if (!function_exists('e')) {
    require_once __DIR__ . '/../../includes/fpl_ui.php';
}
if (!function_exists('fpl_asset_uri')) {
    require_once __DIR__ . '/../../includes/fpl_assets.php';
}
if (!function_exists('get_public_root_uri_path')) {
    require_once __DIR__ . '/../../includes/site_url.php';
}
$auth_root = rtrim(get_public_root_uri_path(), '/');
$auth_image_base = $auth_root . '/image/';
$auth_title = isset($auth_title) ? $auth_title : 'Connexion';
$auth_lead = isset($auth_lead) ? $auth_lead : '';
$auth_form_html = isset($auth_form_html) ? $auth_form_html : '';
$auth_extra_html = isset($auth_extra_html) ? $auth_extra_html : '';
?>
<div class="auth">
    <div class="auth-panel">
        <div class="auth-container">
            <div class="auth-brand-center">
                <a href="<?php echo htmlspecialchars($auth_root . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">
                    <img class="auth-logo-large" src="<?php echo htmlspecialchars($auth_image_base . 'logo-fpl-blanc.png', ENT_QUOTES, 'UTF-8'); ?>" alt="FOUTA POIDS LOURDS" onerror="this.src='<?php echo htmlspecialchars($auth_image_base . 'logo-fpl.png', ENT_QUOTES, 'UTF-8'); ?>'">
                </a>
                <div class="auth-brand-text">
                    <span class="auth-brand-name">FOUTA POIDS LOURDS</span>
                    <span class="auth-brand-subtitle">The Solution</span>
                </div>
            </div>
            <div class="auth-card">
                <div class="auth-form">
                    <h1><?php echo e($auth_title); ?></h1>
                    <?php if ($auth_lead !== ''): ?>
                        <p class="lead"><?php echo e($auth_lead); ?></p>
                    <?php endif; ?>
                    <?php echo $auth_extra_html; ?>
                    <?php echo $auth_form_html; ?>
                </div>
            </div>
        </div>
    </div>
</div>
