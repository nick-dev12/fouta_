<?php
/**
 * Inclusion de la barre de navigation utilisateur — layout FPL
 */

$current_page = basename($_SERVER['PHP_SELF']);

$user_prenom = trim($_SESSION['user_prenom'] ?? '');
$user_nom = trim($_SESSION['user_nom'] ?? '');
$user_display = trim($user_prenom . ' ' . $user_nom);
if ($user_display === '') {
    $user_display = trim($_SESSION['user_email'] ?? 'Mon compte');
}
$user_initials = '';
if ($user_prenom !== '') {
    $user_initials .= strtoupper(substr($user_prenom, 0, 1));
}
if ($user_nom !== '') {
    $user_initials .= strtoupper(substr($user_nom, 0, 1));
}
if ($user_initials === '') {
    $user_initials = 'U';
}

if (!function_exists('e')) {
    require_once __DIR__ . '/../../includes/fpl_ui.php';
}
?>
<div class="app user-container" id="userApp">
    <aside class="sidebar user-sidebar" id="userSidebar">
        <div class="brand sidebar-header">
            <div class="avatar" aria-hidden="true"><?php echo e($user_initials); ?></div>
            <div class="brand-name">Mon Compte<small><?php echo e($user_display); ?></small></div>
        </div>
        <nav class="nav sidebar-menu">
            <a href="mon-compte.php" class="menu-item <?php echo $current_page == 'mon-compte.php' ? 'active' : ''; ?>">
                <span class="menu-item-icon ico"><i class="fas fa-home"></i></span>
                <span class="menu-item-text">Tableau de bord</span>
            </a>
            <a href="/panier.php" class="menu-item">
                <span class="menu-item-icon ico"><i class="fas fa-shopping-cart"></i></span>
                <span class="menu-item-text">Mon panier</span>
            </a>
            <a href="mes-commandes.php" class="menu-item <?php echo $current_page == 'mes-commandes.php' ? 'active' : ''; ?>">
                <span class="menu-item-icon ico"><i class="fas fa-shopping-bag"></i></span>
                <span class="menu-item-text">Mes commandes</span>
            </a>
            <a href="commandes-annulees.php" class="menu-item <?php echo $current_page == 'commandes-annulees.php' ? 'active' : ''; ?>">
                <span class="menu-item-icon ico"><i class="fas fa-ban"></i></span>
                <span class="menu-item-text">Commandes annulées</span>
            </a>
            <a href="produits-livres.php" class="menu-item <?php echo $current_page == 'produits-livres.php' ? 'active' : ''; ?>">
                <span class="menu-item-icon ico"><i class="fas fa-check-circle"></i></span>
                <span class="menu-item-text">Produits livrés</span>
            </a>
            <a href="produits-visites.php" class="menu-item">
                <span class="menu-item-icon ico"><i class="fas fa-eye"></i></span>
                <span class="menu-item-text">Produits visités</span>
            </a>
            <a href="profil.php" class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <span class="menu-item-icon ico"><i class="fas fa-user"></i></span>
                <span class="menu-item-text">Mon profil</span>
            </a>
            <a href="deconnexion.php" class="menu-item mi-logout">
                <span class="menu-item-icon ico"><i class="fas fa-sign-out-alt"></i></span>
                <span class="menu-item-text">Déconnexion</span>
            </a>
        </nav>
    </aside>

    <div class="main user-main">
        <div class="topbar admin-topbar">
            <button type="button" class="burger js-nav-toggle mobile-menu-toggle" id="menuToggle" aria-label="Ouvrir le menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <main class="content user-content" id="userContent">

<script>
(function () {
    var sidebar = document.getElementById('userSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    function tiroir(on) {
        if (!sidebar) return;
        sidebar.classList.toggle('show', on);
        sidebar.classList.toggle('open', on);
        if (overlay) overlay.classList.toggle('show', on);
        document.body.classList.toggle('drawer-open', on);
        document.body.style.overflow = on ? 'hidden' : '';
    }
    window.toggleSidebar = function () { tiroir(!sidebar.classList.contains('show')); };
    document.querySelectorAll('.js-nav-toggle, #menuToggle').forEach(function (btn) {
        btn.addEventListener('click', window.toggleSidebar);
    });
    if (overlay) overlay.addEventListener('click', function () { tiroir(false); });
})();
</script>
