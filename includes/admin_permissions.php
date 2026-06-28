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
        return $r === 'admin' || $r === 'informaticien' || $r === 'developpeur';
    }

    /**
     * Compte au rôle ENUM « admin » (périmètre restreint, sans commandes/caisse/comptes, etc.).
     * Pas de raccourcis « créer » catalogue / devis / contacts…
     */
    function admin_is_restricted_admin_account() {
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
        return in_array($r, ['admin', 'commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
    }

    /**
     * Devis (création, détail, facture client B2C) — commercial, commercial général, informaticien / développeur.
     * Exclut le compte rôle « admin » restreint (table admin) comme pour l’ancien périmètre devis/BL.
     */
    function admin_can_devis() {
        $r = admin_current_role();
        return in_array($r, ['commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
    }

    /**
     * BL, bons de retour B2B, hub index.php — commercial général ou informaticien / développeur (pas le rôle « Commercial » seul).
     */
    function admin_can_bl_retours_b2b() {
        $r = admin_current_role();
        return in_array($r, ['commercial_general', 'informaticien', 'developpeur'], true);
    }

    function admin_can_comptabilite() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'comptabilite' || $r === 'informaticien' || $r === 'developpeur';
    }

    /** Consultation BL, factures BL et bons de retour (lecture seule pour la comptabilité). */
    function admin_can_consulter_bl_b2b_compta() {
        return admin_can_bl_retours_b2b() || admin_can_comptabilite();
    }

    /** Consultation devis et factures devis (lecture seule pour la comptabilité). */
    function admin_can_consulter_devis_compta() {
        return admin_can_devis() || admin_can_comptabilite();
    }

    function admin_can_rh() {
        $r = admin_current_role();
        return $r === 'rh' || $r === 'informaticien' || $r === 'developpeur';
    }

    /**
     * Caisse — accès aux scripts caisse (POST, pages caisse)
     */
    function admin_can_caisse() {
        $r = admin_current_role();
        return in_array($r, ['commercial', 'commercial_general', 'caissier', 'informaticien', 'developpeur'], true);
    }

    /** Bureau vendeur : scan, panier, génération de ticket (commercial / commercial général / informaticien / développeur). */
    function admin_can_caisse_vendeur() {
        $r = admin_current_role();
        return $r === 'commercial' || $r === 'commercial_general' || $r === 'informaticien' || $r === 'developpeur';
    }

    /** Encaissement caissier (zone encaissement, historique, validation paiement ticket). */
    function admin_can_encaisser_ticket() {
        $r = admin_current_role();
        return $r === 'caissier' || $r === 'informaticien' || $r === 'developpeur';
    }

    /** Saisie des dépenses / charges (caissier ou informaticien / développeur). */
    function admin_can_saisir_depenses_caisse() {
        $r = admin_current_role();
        return $r === 'caissier' || $r === 'informaticien' || $r === 'developpeur';
    }

    /**
     * Catalogue / produits (tableau de bord, aperçu du catalogue)
     * — gestion des stocks (compte) et administrateur complet
     */
    function admin_can_gestion_boutique() {
        $r = admin_current_role();
        return $r === 'gestion_stock' || $r === 'admin' || $r === 'informaticien' || $r === 'developpeur';
    }

    /**
     * Popup alertes stock : admin, gestion des stocks, commercial, commercial général, informaticien, développeur
     */
    function admin_can_receive_stock_alerte_popup() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'gestion_stock', 'commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
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
