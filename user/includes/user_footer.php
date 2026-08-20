        </main>
    </div>
</div>

<script>
(function () {
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1000) {
            var sidebar = document.getElementById('userSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.remove('show', 'open');
            if (overlay) overlay.classList.remove('show');
            document.body.classList.remove('drawer-open');
            document.body.style.overflow = '';
        }
    });
})();
</script>
<?php include __DIR__ . '/../../includes/social_floating.php'; ?>
</body>
</html>
