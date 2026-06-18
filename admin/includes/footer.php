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
<script src="/js/admin-pdf-download.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>

