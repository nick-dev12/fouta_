<?php
/**
 * Inclusion de la barre de navigation admin — layout FPL
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/require_access.php';
require_once __DIR__ . '/../../includes/site_url.php';

$admin_nav_base = rtrim(get_public_root_uri_path(), '/') . '/admin/';
$admin_nav_home = $admin_nav_base . admin_role_default_redirect_path(admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin'));
$admin_image_base = rtrim(get_public_root_uri_path(), '/') . '/image/';

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

require_once __DIR__ . '/../../includes/admin_permissions.php';
$nav_can_devis = admin_can_devis();
$nav_can_bl_hub = admin_can_bl_retours_b2b();
$is_nav_devis_section = $is_devis && (
    $current_page === 'devis.php'
    || in_array($current_page, ['details.php', 'modifier.php', 'facture.php', 'devis_par_client.php', 'create.php', 'generer_facture.php', 'update.php', 'supprimer_devis.php'], true)
);
$is_nav_bl_section = $is_devis && (
    $current_page === 'index.php'
    || strpos($current_page, 'bl_') === 0
    || strpos($current_page, 'br_') === 0
    || $current_page === 'convertir_bl.php'
    || $current_page === 'clients_b2b_create.php'
    || strpos($current_page, 'facture_mensuelle') === 0
);

$admin_nav_prenom = trim($_SESSION['admin_prenom'] ?? '');
$admin_nav_nom = trim($_SESSION['admin_nom'] ?? '');
$admin_nav_display = trim($admin_nav_prenom . ' ' . $admin_nav_nom);
if ($admin_nav_display === '') {
    $admin_nav_display = trim($_SESSION['admin_email'] ?? 'Administrateur');
}
$admin_nav_initials = '';
if ($admin_nav_prenom !== '') {
    $admin_nav_initials .= strtoupper(substr($admin_nav_prenom, 0, 1));
}
if ($admin_nav_nom !== '') {
    $admin_nav_initials .= strtoupper(substr($admin_nav_nom, 0, 1));
}
if ($admin_nav_initials === '') {
    $admin_nav_initials = 'A';
}

if (!function_exists('fpl_date_longue')) {
    require_once __DIR__ . '/../../includes/fpl_ui.php';
}

include __DIR__ . '/../../includes/pwa_admin_boot.php';

?>
<div class="app admin-container" id="adminApp">

    <aside class="sidebar admin-sidebar" id="adminSidebar">
        <button type="button" class="side-toggle js-nav-toggle" title="Masquer le menu" aria-label="Masquer le menu">
            <?php echo fpl_icone('chevron-left', 14); ?>
        </button>

        <div class="brand sidebar-header">
            <button type="button" class="sidebar-drawer-close js-sidebar-close" aria-label="Fermer le menu" title="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <a href="<?php echo htmlspecialchars($admin_nav_home); ?>" class="sidebar-header-brand" title="Administration — FOUTA POIDS LOURDS">
                <span class="sidebar-header-logo-badge">
                    <img src="<?php echo htmlspecialchars($admin_image_base . 'logo-fpl-blanc.png', ENT_QUOTES, 'UTF-8'); ?>" alt="FOUTA POIDS LOURDS" class="brand-logo sidebar-header-logo" loading="eager" decoding="async" width="48" height="48" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($admin_image_base . 'logo-fpl.png', ENT_QUOTES, 'UTF-8'); ?>'">
                </span>
            </a>
            <div class="brand-name">FOUTA POIDS LOURDS<small>The Solution</small></div>
        </div>

        <nav class="nav sidebar-menu" aria-label="Navigation principale">
            <div class="sidebar-menu__main">
            <?php if ($admin_role === 'admin' || $admin_nav_is_tech_full): ?>
            <a href="<?php echo $admin_nav_base; ?>dashboard.php"
                class="menu-item mi-dashboard<?php echo $current_page == 'dashboard.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('home', 16); ?></span>
                <span class="menu-item-text">Tableau de bord</span>
            </a>
            <?php if ($nav_can_devis): ?>
            <a href="<?php echo $admin_nav_base; ?>devis/devis.php"
                class="menu-item mi-devis<?php echo $is_nav_devis_section ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                <span class="menu-item-text">Devis</span>
            </a>
            <?php endif; ?>
            <?php if ($nav_can_bl_hub): ?>
            <a href="<?php echo $admin_nav_base; ?>devis/index.php"
                class="menu-item mi-bl-hub<?php echo $is_nav_bl_section ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                <span class="menu-item-text">BL &amp; retours</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $admin_nav_base; ?>comptabilite/index.php"
                class="menu-item mi-compta<?php echo $is_comptabilite_hub ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-calculator"></i></span>
                <span class="menu-item-text">Comptabilité</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/index.php"
                class="menu-item mi-produits<?php echo ($is_produits) ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('tool', 16); ?></span>
                <span class="menu-item-text">Pièces</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/etiquettes.php"
                class="menu-item mi-etiquettes<?php echo $current_page == 'etiquettes.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('printer', 16); ?></span>
                <span class="menu-item-text">Toutes les étiquettes</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/entree.php"
                class="menu-item mi-entree<?php echo $current_page == 'entree.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('download', 16); ?></span>
                <span class="menu-item-text">Entrée en stock</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/transfert.php"
                class="menu-item mi-transfert<?php echo $current_page == 'transfert.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('transfer', 16); ?></span>
                <span class="menu-item-text">Transfert d'emplacement</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/defectueux.php"
                class="menu-item mi-defectueux<?php echo $current_page == 'defectueux.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('alert-triangle', 16); ?></span>
                <span class="menu-item-text">Pièces défectueuses</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>stock/mouvements.php"
                class="menu-item mi-mouvements<?php echo ($is_stock && $current_page == 'mouvements.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('clock', 16); ?></span>
                <span class="menu-item-text">Historique des mouvements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/rapport-jour.php"
                class="menu-item mi-rapport<?php echo $current_page == 'rapport-jour.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('printer', 16); ?></span>
                <span class="menu-item-text">Rapport journalier</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/structure-entrepot.php"
                class="menu-item mi-structure<?php echo $current_page == 'structure-entrepot.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('layers', 16); ?></span>
                <span class="menu-item-text">Structure de l'entrepôt</span>
            </a>
            <?php if ($admin_nav_is_tech_full): ?>
            <a href="<?php echo $admin_nav_base; ?>commandes/index.php"
                class="menu-item mi-commandes<?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php' || $current_page == 'historique-ventes.php')) ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                <span class="menu-item-text">Commandes</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $admin_nav_base; ?>contacts/index.php"
                class="menu-item mi-contacts<?php echo $is_contacts ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                <span class="menu-item-text">Contacts</span>
            </a>
            <?php if ($admin_nav_is_tech_full): ?>
            <a href="<?php echo $admin_nav_base; ?>users/index.php"
                class="menu-item mi-clients<?php echo $is_users ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span class="menu-item-text">Clients</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>comptes/index.php"
                class="menu-item mi-comptes<?php echo $is_comptes ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="menu-item-text">Employés</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>parametres.php"
                class="menu-item mi-params<?php echo ($current_page == 'parametres.php' || strpos($current_dir, '/parametres') !== false) ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('settings', 16); ?></span>
                <span class="menu-item-text">Paramètres</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/index.php"
                class="menu-item mi-caisse<?php echo ($is_caisse && $current_page === 'index.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                <span class="menu-item-text">Caisse magasin</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/encaisser-ticket.php"
                class="menu-item mi-encaisse<?php echo $is_caisse_encaisser ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></span>
                <span class="menu-item-text">Encaissement tickets</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/historique-encaissements.php"
                class="menu-item mi-hist-enc<?php echo $is_caisse_historique ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-history"></i></span>
                <span class="menu-item-text">Historique encaissements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>zones-livraison/index.php"
                class="menu-item mi-zones<?php echo $is_zones_livraison ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-map-location-dot"></i></span>
                <span class="menu-item-text">Zones de livraison</span>
            </a>
            <?php endif; ?>
            <?php elseif ($admin_role === 'commercial_general' || $admin_role === 'commercial'): ?>
            <?php if ($nav_can_devis): ?>
            <a href="<?php echo $admin_nav_base; ?>devis/devis.php"
                class="menu-item mi-devis<?php echo $is_nav_devis_section ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                <span class="menu-item-text">Devis</span>
            </a>
            <?php endif; ?>
            <?php if ($nav_can_bl_hub): ?>
            <a href="<?php echo $admin_nav_base; ?>devis/index.php"
                class="menu-item mi-bl-hub<?php echo $is_nav_bl_section ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-truck-loading"></i></span>
                <span class="menu-item-text">BL &amp; retours</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo $admin_nav_base; ?>commandes/index.php"
                class="menu-item mi-commandes<?php echo ($is_commandes && ($current_page == 'index.php' || $current_page == 'livrees.php' || $current_page == 'annulees.php' || $current_page == 'details.php' || $current_page == 'historique-ventes.php')) ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                <span class="menu-item-text">Commandes</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/index.php"
                class="menu-item mi-caisse<?php echo ($is_caisse && $current_page === 'index.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-cash-register"></i></span>
                <span class="menu-item-text">Caisse magasin</span>
            </a>
            <?php elseif ($admin_role === 'caissier'): ?>
            <a href="<?php echo $admin_nav_base; ?>caisse/encaisser-ticket.php"
                class="menu-item mi-encaisse<?php echo $is_caisse_encaisser ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></span>
                <span class="menu-item-text">Encaissement tickets</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/historique-encaissements.php"
                class="menu-item mi-hist-enc<?php echo $is_caisse_historique ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-history"></i></span>
                <span class="menu-item-text">Historique encaissements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>caisse/depenses.php"
                class="menu-item mi-depenses-caisse<?php echo $is_caisse_depenses ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-wallet"></i></span>
                <span class="menu-item-text">Dépenses caisse</span>
            </a>
            <?php elseif ($admin_role === 'comptabilite'): ?>
            <a href="<?php echo $admin_nav_base; ?>comptabilite/index.php"
                class="menu-item mi-compta<?php echo $is_comptabilite_hub ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-calculator"></i></span>
                <span class="menu-item-text">Comptabilité</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>contacts/index.php"
                class="menu-item mi-contacts<?php echo $is_contacts ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                <span class="menu-item-text">Contacts</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>commandes/historique-ventes.php"
                class="menu-item mi-hist-ventes<?php echo ($is_commandes && $current_page === 'historique-ventes.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                <span class="menu-item-text">Historique des ventes</span>
            </a>
            <?php elseif ($admin_role === 'rh'): ?>
            <a href="<?php echo $admin_nav_base; ?>contacts/index.php"
                class="menu-item mi-contacts<?php echo $is_contacts ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                <span class="menu-item-text">Contacts</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>users/index.php"
                class="menu-item mi-clients<?php echo $is_users ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span class="menu-item-text">Clients</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>comptes/index.php"
                class="menu-item mi-comptes<?php echo $is_comptes ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="menu-item-text">Employés</span>
            </a>
            <?php elseif ($admin_role === 'gestion_stock_general'): ?>
            <a href="<?php echo $admin_nav_base; ?>dashboard.php"
                class="menu-item mi-dashboard<?php echo $current_page == 'dashboard.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('home', 16); ?></span>
                <span class="menu-item-text">Tableau de bord</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/index.php"
                class="menu-item mi-produits<?php echo ($is_produits && $current_page == 'index.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('tool', 16); ?></span>
                <span class="menu-item-text">Pièces</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/etiquettes.php"
                class="menu-item mi-etiquettes<?php echo $current_page == 'etiquettes.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('printer', 16); ?></span>
                <span class="menu-item-text">Toutes les étiquettes</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/entree.php"
                class="menu-item mi-entree<?php echo $current_page == 'entree.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('download', 16); ?></span>
                <span class="menu-item-text">Entrée en stock</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/transfert.php"
                class="menu-item mi-transfert<?php echo $current_page == 'transfert.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('transfer', 16); ?></span>
                <span class="menu-item-text">Transfert d'emplacement</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/defectueux.php"
                class="menu-item mi-defectueux<?php echo $current_page == 'defectueux.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('alert-triangle', 16); ?></span>
                <span class="menu-item-text">Pièces défectueuses</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>stock/mouvements.php"
                class="menu-item mi-mouvements<?php echo ($is_stock && $current_page == 'mouvements.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('clock', 16); ?></span>
                <span class="menu-item-text">Historique des mouvements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/rapport-jour.php"
                class="menu-item mi-rapport<?php echo $current_page == 'rapport-jour.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('printer', 16); ?></span>
                <span class="menu-item-text">Rapport journalier</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/structure-entrepot.php"
                class="menu-item mi-structure<?php echo $current_page == 'structure-entrepot.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('layers', 16); ?></span>
                <span class="menu-item-text">Structure de l'entrepôt</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>categories/index.php"
                class="menu-item mi-categories<?php echo ($is_categories) ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('folder', 16); ?></span>
                <span class="menu-item-text">Catégories</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>parametres.php"
                class="menu-item mi-params<?php echo ($current_page == 'parametres.php' || $is_parametres) ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('settings', 16); ?></span>
                <span class="menu-item-text">Paramètres stock</span>
            </a>
            <?php elseif ($admin_role === 'gestion_stock'): ?>
            <a href="<?php echo $admin_nav_base; ?>produits/mon-travail.php"
                class="menu-item mi-mon-travail<?php echo $current_page == 'mon-travail.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('home', 16); ?></span>
                <span class="menu-item-text">Mon travail</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/index.php"
                class="menu-item mi-produits<?php echo ($is_produits && $current_page == 'index.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('tool', 16); ?></span>
                <span class="menu-item-text">Pièces</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/etiquettes.php"
                class="menu-item mi-etiquettes<?php echo $current_page == 'etiquettes.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('printer', 16); ?></span>
                <span class="menu-item-text">Toutes les étiquettes</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/entree.php"
                class="menu-item mi-entree<?php echo $current_page == 'entree.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('download', 16); ?></span>
                <span class="menu-item-text">Entrée en stock</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/transfert.php"
                class="menu-item mi-transfert<?php echo $current_page == 'transfert.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('transfer', 16); ?></span>
                <span class="menu-item-text">Transfert d'emplacement</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/defectueux.php"
                class="menu-item mi-defectueux<?php echo $current_page == 'defectueux.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('alert-triangle', 16); ?></span>
                <span class="menu-item-text">Pièces défectueuses</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>stock/mouvements.php"
                class="menu-item mi-mouvements<?php echo ($is_stock && $current_page == 'mouvements.php') ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('clock', 16); ?></span>
                <span class="menu-item-text">Historique des mouvements</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/rapport-jour.php"
                class="menu-item mi-rapport<?php echo $current_page == 'rapport-jour.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('printer', 16); ?></span>
                <span class="menu-item-text">Rapport journalier</span>
            </a>
            <a href="<?php echo $admin_nav_base; ?>produits/structure-entrepot.php"
                class="menu-item mi-structure<?php echo $current_page == 'structure-entrepot.php' ? ' active' : ''; ?>">
                <span class="menu-item-icon ico" aria-hidden="true"><?php echo fpl_icone('layers', 16); ?></span>
                <span class="menu-item-text">Structure de l'entrepôt</span>
            </a>
            <?php endif; ?>
            </div>
        </nav>

        <div class="sidebar-user">
            <div class="avatar" aria-hidden="true"><?php echo e($admin_nav_initials); ?></div>
            <div class="who">
                <strong><?php echo e($admin_nav_display); ?></strong>
                <span><?php echo e(ucfirst(str_replace('_', ' ', $admin_role))); ?></span>
                <a href="<?php echo $admin_nav_base; ?>profil.php">Mon profil</a>
                ·
                <a href="<?php echo $admin_nav_base; ?>logout.php">Déconnexion</a>
            </div>
        </div>
    </aside>

    <div class="main admin-main">
        <div class="topbar admin-topbar admin-topbar--maquette">
            <div class="admin-topbar__left">
                <?php /* Le burger vit DANS la barre du haut, jamais en flottant :
                         la classe mobile-menu-toggle (pavé fixe 50 px de
                         admin-dashboard.css) lui est retirée. */ ?>
                <button type="button" class="burger admin-burger js-nav-toggle" id="menuToggle" title="Afficher le menu" aria-label="Afficher le menu">
                    <?php echo fpl_icone('menu', 16); ?>
                </button>
                <?php /* LE BOUTON RETOUR ET LE TITRE DE LA PAGE — barre du haut de
                         FPL natif. La zone ne s'affiche QUE si la page a posé
                         $fpl_titre_page avant d'inclure ce fichier : toutes les
                         autres pages du dépôt restent exactement comme avant. */ ?>
                <?php if (!empty($fpl_titre_page)): ?>
                    <?php if (!empty($fpl_retour_page)): ?>
                    <a href="<?php echo e($fpl_retour_page); ?>" class="btn-back" title="Revenir à la page précédente">
                        <?php echo fpl_icone('arrow-left', 14); ?> Retour
                    </a>
                    <?php endif; ?>
                    <h1><?php echo fpl_e($fpl_titre_page); ?></h1>
                <?php endif; ?>
                <form class="admin-topbar__search" action="<?php echo $admin_nav_base; ?>produits/index.php" method="get" role="search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" name="recherche" placeholder="Rechercher une pièce…" autocomplete="off">
                </form>
            </div>
            <div class="admin-topbar__right">
                <?php /* LA DATE DU JOUR, en toutes lettres, à droite de la barre —
                         comme dans FPL natif. Même condition : rien ne change pour
                         les pages qui ne demandent pas cette barre. */ ?>
                <?php if (!empty($fpl_titre_page)): ?>
                <span class="date"><?php echo fpl_date_longue(); ?></span>
                <?php endif; ?>
                <div class="admin-topbar__tools" aria-label="Raccourcis">
                    <button type="button" class="admin-topbar__tool" id="topbarNotifyBtn" title="Notifications">
                        <i class="fas fa-bell" aria-hidden="true"></i>
                    </button>
                    <?php if ($admin_role === 'admin' || $admin_nav_is_tech_full): ?>
                    <a href="<?php echo $admin_nav_base; ?>parametres.php" class="admin-topbar__tool" title="Paramètres">
                        <i class="fas fa-cog" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="admin-topbar__user">
                    <span class="admin-topbar__avatar" aria-hidden="true"><?php echo e($admin_nav_initials); ?></span>
                    <span class="admin-topbar__name"><?php echo e($admin_nav_display); ?></span>
                    <?php /* La flèche vers le bas a été retirée : elle promettait un
                             menu déroulant qui n'existe nulle part — pas de gestionnaire
                             de clic, aucun menu dans la page, le bloc n'est même pas un
                             lien. Une flèche qui ne mène à rien invite à cliquer pour
                             rien. Le profil et la déconnexion sont dans le menu de
                             gauche, en bas. */ ?>
                </div>
            </div>
        </div>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <main class="content admin-content" id="adminContent">

<script>
(function () {
    var app = document.getElementById('adminApp');
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var petitEcran = function () { return window.matchMedia('(max-width: 1000px)').matches; };

    function tiroir(ouvrir) {
        if (!sidebar) return;
        sidebar.classList.toggle('show', ouvrir);
        sidebar.classList.toggle('open', ouvrir);
        if (overlay) overlay.classList.toggle('show', ouvrir);
        document.body.classList.toggle('drawer-open', ouvrir);
        document.body.style.overflow = ouvrir ? 'hidden' : '';
        document.body.style.cursor = ouvrir ? '' : '';
    }

    window.toggleSidebar = function () { tiroir(!sidebar.classList.contains('show')); };
    window.closeAdminDrawer = function () { tiroir(false); };

    if (app && localStorage.getItem('fpl-nav') === 'off' && !petitEcran()) {
        app.classList.add('nav-hidden');
    }

    document.querySelectorAll('.js-nav-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (petitEcran()) {
                tiroir(!sidebar.classList.contains('show'));
                return;
            }
            if (!app) return;
            var masque = app.classList.toggle('nav-hidden');
            localStorage.setItem('fpl-nav', masque ? 'off' : 'on');
        });
    });

    if (overlay) {
        overlay.addEventListener('click', function () { tiroir(false); });
    }

    document.querySelectorAll('.js-sidebar-close').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            tiroir(false);
        });
    });

    document.addEventListener('click', function (e) {
        if (!petitEcran() || !sidebar.classList.contains('show')) {
            return;
        }
        if (sidebar.contains(e.target)) {
            return;
        }
        if (e.target.closest('.js-nav-toggle, .burger, .mobile-menu-toggle, #menuToggle')) {
            return;
        }
        tiroir(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show') && petitEcran()) {
            tiroir(false);
        }
    });

    if (sidebar) {
        sidebar.querySelectorAll('.menu-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (petitEcran()) {
                    tiroir(false);
                }
            });
        });
    }

    window.addEventListener('resize', function () {
        if (!petitEcran()) tiroir(false);
    });

    var topbarNotify = document.getElementById('topbarNotifyBtn');
    if (topbarNotify) {
        topbarNotify.addEventListener('click', function () {
            var dashNotify = document.getElementById('btn-enable-notifications');
            if (dashNotify) {
                dashNotify.click();
            }
        });
    }
})();
</script>
