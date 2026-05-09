<?php
/**
 * Inclusion de la barre de navigation admin
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/require_access.php';
require_once __DIR__ . '/../../includes/site_url.php';

/**
 * Base URI absolue du dossier admin (compatible sous-dossier WAMP, ex. /Fouta/admin/).
 * Évite les liens relatifs cassés depuis admin/comptes/employes/, admin/parametres/, etc.
 */
$admin_nav_base = rtrim(get_public_root_uri_path(), '/') . '/admin/';

$current_dir = dirname($_SERVER['PHP_SELF']);
$current_page = basename($_SERVER['PHP_SELF']);
$is_produits = strpos($current_dir, '/produits') !== false;
$is_categories = strpos($current_dir, '/categories') !== false;
$is_stock = strpos($current_dir, '/stock') !== false;
$is_slider = strpos($current_dir, '/slider') !== false;
$is_parametres = strpos($current_dir, '/parametres') !== false;
$is_commandes = strpos($current_dir, '/commandes') !== false;
$is_caisse = strpos($current_dir, '/caisse') !== false;
$is_caisse_encaisser = $is_caisse && ($current_page === 'encaisser-ticket.php');
$is_caisse_historique = $is_caisse && ($current_page === 'historique-encaissements.php');
$is_caisse_depenses = $is_caisse && ($current_page === 'depenses.php');
$is_devis = strpos($current_dir, '/devis') !== false;
$is_users = strpos($current_dir, '/users') !== false;
$is_contacts = strpos($current_dir, '/contacts') !== false;
$is_zones_livraison = strpos($current_dir, '/zones-livraison') !== false;
$is_comptes = strpos($current_dir, '/comptes') !== false;
$is_commercial_hub = strpos($current_dir, '/commercial') !== false;
$is_comptabilite_hub = strpos($current_dir, '/comptabilite') !== false;
$admin_role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$admin_nav_is_tech_full = ($admin_role === 'informaticien' || $admin_role === 'developpeur');

include __DIR__ . '/../../includes/pwa_admin_boot.php';

?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        function toggleAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
        }
        window.toggleSidebar = toggleAdminSidebar;
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('menuToggle');
            var overlay = document.getElementById('sidebarOverlay');
            if (btn) btn.addEventListener('click', toggleAdminSidebar);
            if (overlay) overlay.addEventListener('click', toggleAdminSidebar);
        });
    })();
</script>

<div class="admin-container">
    <!-- Barre de navigation verticale -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="<?php echo htmlspecialchars($admin_nav_base . 'dashboard.php'); ?>" class="sidebar-header-brand" title="Administration — FOUTA POIDS LOURDS">
                <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS" class="sidebar-header-logo" loading="eager" decoding="async" width="220" height="60">
            </a>
        </div>
        <nav class="sidebar-menu" aria-label="Navigation principale">
            <div class="sidebar-menu__main">
            <?php if ($admin_role === 'admin' || $admin_nav_is_tech_full): ?>
            <a href="<?php echo $admin_nav_base; ?>dashboard.php"
                class="menu-item mi-dashboard<?php echo $current_page == 'dashboard.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-home"></i></span>
                <span class="menu-item-text">Tableau de bord</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>devis/index.php"
                class="menu-item mi-devis<?php echo ($is_devis || $is_commercial_hub) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-handshake"></i></span>
                <span class="menu-item-text">Devis &amp; BL</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>comptabilite/index.php"
                class="menu-item mi-compta<?php echo $is_comptabilite_hub ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-calculator"></i></span>
                <span class="menu-item-text">Comptabilité</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>stock/index.php"
                class="menu-item mi-stock<?php echo ($is_stock) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                <span class="menu-item-text">Stock</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/index.php"
                class="menu-item mi-produits<?php echo ($is_produits) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-box"></i></span>
                <span class="menu-item-text">Produits</span>
            </a>
            <?php if ($admin_nav_is_tech_full): ?>
            <a href="<?php echo $admin_nav_base; ?>commandes/index.php"
                class="menu-item mi-commandes<?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php' || $current_page == 'historique-ventes.php')) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                <span class="menu-item-text">Commandes</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $admin_nav_base; ?>contacts/index.php"
                class="menu-item mi-contacts<?php echo $is_contacts ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                <span class="menu-item-text">Contacts</span>
            </a>
            <?php if ($admin_nav_is_tech_full): ?>
            <a href="<?php echo $admin_nav_base; ?>users/index.php"
                class="menu-item mi-clients<?php echo $is_users ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span class="menu-item-text">Clients</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>comptes/index.php"
                class="menu-item mi-comptes<?php echo $is_comptes ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="menu-item-text">Employés</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>parametres.php"
                class="menu-item mi-params<?php echo ($current_page == 'parametres.php' || strpos($current_dir, '/parametres') !== false) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-cog"></i></span>
                <span class="menu-item-text">Paramètres</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/index.php"
                class="menu-item mi-caisse<?php echo ($is_caisse && $current_page === 'index.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                <span class="menu-item-text">Caisse magasin</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/encaisser-ticket.php"
                class="menu-item mi-encaisse<?php echo $is_caisse_encaisser ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></span>
                <span class="menu-item-text">Encaissement tickets</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/historique-encaissements.php"
                class="menu-item mi-hist-enc<?php echo $is_caisse_historique ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-history"></i></span>
                <span class="menu-item-text">Historique encaissements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>zones-livraison/index.php"
                class="menu-item mi-zones<?php echo $is_zones_livraison ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-map-location-dot"></i></span>
                <span class="menu-item-text">Zones de livraison</span>
            </a>
            <?php endif; ?>
            <?php elseif ($admin_role === 'commercial_general'): ?>
            <a href="<?php echo $admin_nav_base; ?>devis/index.php"
                class="menu-item mi-devis<?php echo ($is_devis || $is_commercial_hub) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-handshake"></i></span>
                <span class="menu-item-text">Devis &amp; BL</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>commandes/index.php"
                class="menu-item mi-commandes<?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php' || $current_page == 'historique-ventes.php')) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                <span class="menu-item-text">Commandes</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/index.php"
                class="menu-item mi-caisse<?php echo ($is_caisse && !$is_caisse_encaisser) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                <span class="menu-item-text">Caisse</span>
            </a>
            <?php elseif ($admin_role === 'commercial'): ?>
            <a href="<?php echo $admin_nav_base; ?>commandes/index.php"
                class="menu-item mi-commandes<?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php' || $current_page == 'historique-ventes.php')) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                <span class="menu-item-text">Commandes</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/index.php"
                class="menu-item mi-caisse<?php echo ($is_caisse && !$is_caisse_encaisser) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                <span class="menu-item-text">Caisse</span>
            </a>
            <?php elseif ($admin_role === 'caissier'): ?>
            <a href="<?php echo $admin_nav_base; ?>caisse/encaisser-ticket.php"
                class="menu-item mi-encaisse<?php echo $is_caisse_encaisser ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></span>
                <span class="menu-item-text">Encaissement tickets</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/historique-encaissements.php"
                class="menu-item mi-hist-enc<?php echo $is_caisse_historique ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-history"></i></span>
                <span class="menu-item-text">Historique encaissements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/depenses.php"
                class="menu-item mi-depenses-caisse<?php echo $is_caisse_depenses ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-wallet"></i></span>
                <span class="menu-item-text">Dépenses caisse</span>
            </a>
            <?php elseif ($admin_role === 'comptabilite'): ?>
            <a href="<?php echo $admin_nav_base; ?>comptabilite/index.php"
                class="menu-item mi-compta<?php echo $is_comptabilite_hub ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-calculator"></i></span>
                <span class="menu-item-text">Comptabilité</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>contacts/index.php"
                class="menu-item mi-contacts<?php echo $is_contacts ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                <span class="menu-item-text">Contacts</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>commandes/historique-ventes.php"
                class="menu-item mi-hist-ventes<?php echo ($is_commandes && $current_page === 'historique-ventes.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                <span class="menu-item-text">Historique des ventes</span>
            </a>
            <?php elseif ($admin_role === 'rh'): ?>
            <a href="<?php echo $admin_nav_base; ?>contacts/index.php"
                class="menu-item mi-contacts<?php echo $is_contacts ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                <span class="menu-item-text">Contacts</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>users/index.php"
                class="menu-item mi-clients<?php echo $is_users ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span class="menu-item-text">Clients</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>comptes/index.php"
                class="menu-item mi-comptes<?php echo $is_comptes ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="menu-item-text">Employés</span>
            </a>
            <?php elseif ($admin_role === 'gestion_stock'): ?>
            <a href="<?php echo $admin_nav_base; ?>stock/index.php"
                class="menu-item mi-stock<?php echo ($is_stock) ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                <span class="menu-item-text">Stock</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/index.php"
                class="menu-item mi-produits<?php echo ($is_produits && $current_page == 'index.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-box"></i></span>
                <span class="menu-item-text">Produits</span>
            </a>
            <?php endif; ?>
            </div>
            <div class="sidebar-menu__footer" role="group" aria-label="Compte">
                <p class="sidebar-menu__foot-label">Compte</p>
            <a href="<?php echo $admin_nav_base; ?>profil.php"
                class="menu-item mi-profil<?php echo $current_page == 'profil.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                <span class="menu-item-text">Mon profil</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>logout.php" class="menu-item mi-logout">
                <span class="menu-item-icon" aria-hidden="true"><i class="fas fa-sign-out-alt"></i></span>
                <span class="menu-item-text">Déconnexion</span>
            </a>
            </div>
        </nav>
    </aside>

    <!-- Contenu principal -->
    <main class="admin-content" id="adminContent">