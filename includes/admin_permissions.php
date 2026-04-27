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
        return admin_current_role() === 'admin';
    }

    /**
     * Zones de livraison : tous les rôles sauf le rôle « admin » (compte restreint)
     */
    function admin_can_zones_livraison() {
        return admin_current_role() !== 'admin';
    }

    function admin_can_commercial() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'commercial';
    }

    /** Devis, BL, conversion — même périmètre que l'espace commercial */
    function admin_can_devis_bl() {
        return admin_can_commercial();
    }

    function admin_can_comptabilite() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'comptabilite';
    }

    function admin_can_rh() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'rh';
    }

    /**
     * Caisse — accès aux scripts caisse (POST, pages caisse)
     */
    function admin_can_caisse() {
        $r = admin_current_role();
        return in_array($r, ['commercial', 'caissier'], true);
    }

    /** Bureau vendeur : scan, panier, génération de ticket (pas l’encaissement caissier seul) */
    function admin_can_caisse_vendeur() {
        $r = admin_current_role();
        return $r === 'commercial';
    }

    /** Encaissement (validation paiement) : caissier */
    function admin_can_encaisser_ticket() {
        return admin_current_role() === 'caissier';
    }

    /**
     * Catalogue / produits (rôle admin sans accès — réservé à gestion_stock)
     */
    function admin_can_gestion_boutique() {
        return admin_current_role() === 'gestion_stock';
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
