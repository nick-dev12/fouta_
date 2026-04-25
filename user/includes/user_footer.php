    </main>
</div>

<script>
    (function() {
        function closeUserSidebar() {
            var sidebar = document.getElementById('userSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 600) {
                closeUserSidebar();
            }
        });
    })();
</script>
<?php include __DIR__ . '/../../includes/social_floating.php'; ?>
</body>
</html>

