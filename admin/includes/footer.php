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
</body>
</html>

