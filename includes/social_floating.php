<?php
/**
 * Boutons réseaux sociaux en position fixe (bas à droite)
 * WhatsApp, LinkedIn, Facebook, TikTok
 */

$social_config = [];
if (file_exists(__DIR__ . '/../config/social.php')) {
    $social_config = require __DIR__ . '/../config/social.php';
}

$whatsapp = $social_config['whatsapp'] ?? '';
$linkedin = $social_config['linkedin'] ?? '';
$facebook = $social_config['facebook'] ?? '';
$tiktok = $social_config['tiktok'] ?? '';

// WhatsApp : format wa.me/CODE_PAYS_NUMERO (sans + ni espaces)
$whatsapp_url = '';
if (!empty($whatsapp)) {
    $whatsapp_clean = preg_replace('/[^0-9]/', '', $whatsapp);
    $whatsapp_url = 'https://wa.me/' . $whatsapp_clean;
}
?>
<?php if (!function_exists('get_asset_version')) { require_once __DIR__ . '/asset_version.php'; } ?>
<link rel="stylesheet" href="/css/social-floating.css<?php echo asset_version_query(); ?>">
<div class="social-floating" id="socialFloating" aria-label="Réseaux sociaux">
    <?php if (!empty($whatsapp_url)): ?>
    <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" class="social-floating-btn social-whatsapp" title="Contactez-nous sur WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
    <?php endif; ?>
    <?php if (!empty($linkedin)): ?>
    <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="social-floating-btn social-linkedin" title="FOUTA POIDS LOURDS sur LinkedIn">
        <i class="fab fa-linkedin-in"></i>
    </a>
    <?php endif; ?>
    <?php if (!empty($facebook)): ?>
    <a href="<?php echo htmlspecialchars($facebook); ?>" target="_blank" rel="noopener noreferrer" class="social-floating-btn social-facebook" title="Suivez-nous sur Facebook">
        <i class="fab fa-facebook-f"></i>
    </a>
    <?php endif; ?>
    <?php if (!empty($tiktok)): ?>
    <a href="<?php echo htmlspecialchars($tiktok); ?>" target="_blank" rel="noopener noreferrer" class="social-floating-btn social-tiktok" title="FOUTA POIDS LOURDS sur TikTok">
        <i class="fab fa-tiktok"></i>
    </a>
    <?php endif; ?>
</div>
