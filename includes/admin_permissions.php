<?php
/**
 * Permissions et rôles — espace administration
 * Programmation procédurale uniquement
 */

if (!function_exists('admin_current_role')) {

    /**
     * Rôle de l'admin connecté (session)
     */
    function admin_current_role() {
        $r = isset($_SESSION['admin_role']) ? (string) $_SESSION['admin_role'] : 'admin';
        if ($r === 'utilisateur') {
            return 'gestion_stock';
        }
        return $r;
    }

    function admin_is_full_admin() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'informaticien';
    }

    /**
     * Zones de livraison : tous les rôles sauf le rôle « admin » (compte restreint)
     */
    function admin_can_zones_livraison() {
        return admin_current_role() !== 'admin';
    }

    function admin_can_commercial() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'commercial', 'commercial_general', 'informaticien'], true);
    }

    /** Devis, BL, conversion — admin, commercial général, informaticien (pas le rôle « Commercial » restreint). */
    function admin_can_devis_bl() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'commercial_general', 'informaticien'], true);
    }

    function admin_can_comptabilite() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'comptabilite' || $r === 'informaticien';
    }

    function admin_can_rh() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'rh' || $r === 'informaticien';
    }

    /**
     * Caisse — accès aux scripts caisse (POST, pages caisse)
     */
    function admin_can_caisse() {
        $r = admin_current_role();
        return in_array($r, ['commercial', 'commercial_general', 'caissier', 'informaticien'], true);
    }

    /** Bureau vendeur : scan, panier, génération de ticket (commercial / commercial général / informaticien). */
    function admin_can_caisse_vendeur() {
        $r = admin_current_role();
        return $r === 'commercial' || $r === 'commercial_general' || $r === 'informaticien';
    }

    /** Encaissement caissier (zone encaissement, historique, validation paiement ticket). */
    function admin_can_encaisser_ticket() {
        $r = admin_current_role();
        return $r === 'caissier' || $r === 'informaticien';
    }

    /** Saisie des dépenses / charges (caissier ou informaticien). */
    function admin_can_saisir_depenses_caisse() {
        $r = admin_current_role();
        return $r === 'caissier' || $r === 'informaticien';
    }

    /**
     * Catalogue / produits (tableau de bord, aperçu du catalogue)
     * — gestion des stocks (compte) et administrateur complet
     */
    function admin_can_gestion_boutique() {
        $r = admin_current_role();
        return $r === 'gestion_stock' || $r === 'admin' || $r === 'informaticien';
    }

    /**
     * Popup alertes stock : admin, gestion des stocks, commercial, commercial général, informaticien
     */
    function admin_can_receive_stock_alerte_popup() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'gestion_stock', 'commercial', 'commercial_general', 'informaticien'], true);
    }

    /**
     * Redirige si le rôle n'est pas autorisé
     */
    function admin_require_roles($allowed_roles, $redirect = 'dashboard.php') {
        $r = admin_current_role();
        if (in_array($r, $allowed_roles, true)) {
            return;
        }
        header('Location: ' . $redirect);
        exit;
    }
}
