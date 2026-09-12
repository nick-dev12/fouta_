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
     * BL, bons de retour B2B, hub index.php — commercial, commercial général, informaticien / développeur.
     */
    function admin_can_bl_retours_b2b() {
        $r = admin_current_role();
        return in_array($r, ['commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
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
        return in_array($r, ['gestion_stock', 'gestion_stock_general', 'admin', 'informaticien', 'developpeur'], true);
    }

    /**
     * VOIR les étiquettes (pièces et barres) — lecture et impression seulement.
     * Les profils du stock, plus l'INFOGRAPHISTE (rôle « photographe ») : la
     * direction veut qu'il voie le rendu de l'étiquette de n'importe quelle
     * pièce, puisque c'est son image qui s'y imprime. Ces écrans ne montrent ni
     * prix, ni stock, ni fournisseur, et ne changent rien : régler la
     * disposition reste à admin_can_gestion_stock_etendue().
     */
    function admin_can_voir_etiquettes() {
        return admin_can_gestion_stock() || admin_current_role() === 'photographe';
    }

    /**
     * LA CONCEPTION DE L'ÉTIQUETTE (12/09/2026, demande de la direction) :
     * l'infographiste dessine l'étiquette et juge son rendu ; il doit donc
     * atteindre le même écran que l'informaticien — les dimensions
     * d'impression de l'étiquette de pièce.
     */
    function admin_can_conception_etiquettes() {
        return admin_can_gestion_stock_etendue() || admin_current_role() === 'photographe';
    }

    /**
     * LES DEUX RÉFÉRENCES DE LA PIÈCE — OEM et fournisseur (12/09/2026, demande
     * de la direction) : ce sont elles qui s'impriment sur l'étiquette et qui
     * servent à retrouver la pièce ; l'infographiste les corrige depuis SON
     * éditeur, sans toucher au reste de la fiche (ni prix, ni stock).
     */
    function admin_can_modifier_references_piece() {
        return admin_can_gestion_stock_etendue() || admin_current_role() === 'photographe';
    }

    /** Périmètre étendu stocks : catégories complètes, paramètres stock, entrepôt, alertes. */
    function admin_can_gestion_stock_etendue() {
        $r = admin_current_role();
        return in_array($r, ['gestion_stock_general', 'admin', 'informaticien', 'developpeur'], true);
    }

    /** Accès de base gestion stock (produits, stock, catégories limitées). */
    function admin_can_gestion_stock() {
        $r = admin_current_role();
        return in_array($r, ['gestion_stock', 'gestion_stock_general', 'admin', 'informaticien', 'developpeur'], true);
    }

    /**
     * Popup alertes stock : admin, gestion des stocks, commercial, commercial général, informaticien, développeur
     */
    function admin_can_receive_stock_alerte_popup() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'gestion_stock', 'gestion_stock_general', 'commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
    }

    /**
     * La page « Commandes » du site est-elle montrée ? (11/09/2026)
     * Décision de la direction : masquée tant qu'elle ne sert pas (aucune commande
     * du site enregistrée, aucune zone de livraison). Les entrées du menu et les
     * boutons qui y mènent disparaissent ; les pages restent en place et s'ouvrent
     * par leur adresse. Remettre true pour les réafficher partout.
     */
    function admin_commandes_site_visibles() {
        return false;
    }

    /**
     * Préparer un retour client en caisse (11/09/2026) : le commercial général
     * constate, choisit le motif et la solution. Le caissier valide ensuite : il
     * rend ou reçoit les espèces (admin_can_encaisser_ticket). Le commercial
     * simple n'en a pas encore : ses droits se définissent après ce chantier.
     */
    function admin_can_preparer_retour_caisse() {
        $r = admin_current_role();
        return in_array($r, ['commercial_general', 'informaticien', 'developpeur'], true);
    }

    /**
     * Enregistrer ou annuler le paiement d'une facture : facture de devis, facture
     * de bon de livraison, facture mensuelle (10/09/2026). Réglé sur la comptabilité
     * en attendant la décision de la direction (« qui a le droit de dire qu'une
     * facture de devis est payée ») : le vendeur ne coche plus la facture de sa vente.
     */
    function admin_can_enregistrer_paiement_facture() {
        return admin_can_comptabilite();
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
