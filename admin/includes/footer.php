<?php
if (!function_exists('fpl_css_link')) {
    require_once __DIR__ . '/../../includes/fpl_assets.php';
}
?>
        </main>
    </div>
</div>

<script>
(function () {
    var alerte = document.querySelector('.content > .alert, .admin-content > .alert, .alert-success, .alert-error');
    if (alerte) {
        alerte.classList.add('alert-neuve');
    }
})();
</script>
<?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php fpl_css_link('admin-export-catalogue-tracker.css'); ?>
<script src="<?php echo htmlspecialchars(fpl_script_src('admin-pdf-download.js'), ENT_QUOTES, 'UTF-8'); ?><?php echo asset_version_query(); ?>"></script>
<script src="<?php echo htmlspecialchars(fpl_script_src('admin-export-catalogue-pdf.js'), ENT_QUOTES, 'UTF-8'); ?><?php echo asset_version_query(); ?>"></script>
<?php
if (!empty($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';
    produit_formulaire_champs_ensure_schema();
    echo '<script>window.adminProduitChampsManifest = ' . produit_formulaire_champs_manifest_json() . ';</script>' . "\n";
}
?>
</body>
</html>
