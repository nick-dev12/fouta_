<?php
/**
 * Inclusion de la barre de navigation utilisateur
 * Programmation procédurale uniquement
 */

// Déterminer le chemin de base
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        function toggleUserSidebar() {
            var sidebar = document.getElementById('userSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
        }
        window.toggleSidebar = toggleUserSidebar;
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('menuToggle');
            var overlay = document.getElementById('sidebarOverlay');
            if (btn) btn.addEventListener('click', toggleUserSidebar);
            if (overlay) overlay.addEventListener('click', toggleUserSidebar);
        });
    })();
</script>

<div class="user-container">
    <!-- Barre de navigation verticale -->
    <aside class="user-sidebar" id="userSidebar">
        <div class="sidebar-header">
            <i class="fas fa-user-circle logo-icon"></i>
            <h2>Mon Compte</h2>
        </div>
        <nav class="sidebar-menu">
            <a href="mon-compte.php" class="menu-item <?php echo $current_page == 'mon-compte.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="/panier.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Mon panier</span>
            </a>
            <a href="mes-commandes.php"
                class="menu-item <?php echo $current_page == 'mes-commandes.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>Mes commandes</span>
            </a>
            <a href="commandes-annulees.php"
                class="menu-item <?php echo $current_page == 'commandes-annulees.php' ? 'active' : ''; ?>">
                <i class="fas fa-ban"></i>
                <span>Commandes annulées</span>
            </a>
            <a href="produits-livres.php"
                class="menu-item <?php echo $current_page == 'produits-livres.php' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Produits livrés</span>
            </a>
            <a href="produits-visites.php" class="menu-item">
                <i class="fas fa-eye"></i>
                <span>Produits visités</span>
            </a>

            <a href="profil.php" class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Mon profil</span>
            </a>
            <a href="deconnexion.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </nav>
    </aside>

    <!-- Contenu principal -->
    <main class="user-content" id="userContent">