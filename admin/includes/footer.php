    </main>
</div>

<script>
    (function() {
        function closeAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                closeAdminSidebar();
            }
        });
    })();
</script>
<?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<link rel="stylesheet" href="/css/admin-export-catalogue-tracker.css<?php echo asset_version_query(); ?>">
<script src="/js/admin-pdf-download.js<?php echo asset_version_query(); ?>"></script>
<script src="/js/admin-export-catalogue-pdf.js<?php echo asset_version_query(); ?>"></script>
<?php
if (!empty($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../../models/model_produit_formulaire_champs.php';
    produit_formulaire_champs_ensure_schema();
    echo '<script>window.adminProduitChampsManifest = ' . produit_formulaire_champs_manifest_json() . ';</script>' . "\n";
}
?>
</body>
</html>

