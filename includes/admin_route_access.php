<?php
/**
 * Contrôle d'accès aux routes admin par rôle (liste blanche).
 * Programmation procédurale uniquement.
 */

if (!function_exists('admin_route_relative_path')) {

    /**
     * Chemin relatif sous admin/ (ex. devis/bl_enregistrer.php)
     */
    function admin_route_relative_path() {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#/admin/(.+\.php)$#', $script, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Rôle effectif en session (migration utilisateur → gestion_stock)
     */
    function admin_normalize_role_for_route($role) {
        $r = (string) $role;
        if ($r === 'utilisateur') {
            return 'gestion_stock';
        }
        return $r;
    }

    /**
     * Scripts sous admin/devis/ autorisés pour le rôle « commercial » (devis uniquement, pas BL/BR).
     */
    function admin_route_commercial_devis_scripts() {
        return [
            'devis/devis.php',
            'devis/create.php',
            'devis/details.php',
            'devis/modifier.php',
            'devis/update.php',
            'devis/supprimer_devis.php',
            'devis/generer_facture.php',
            'devis/facture.php',
            'devis/devis_par_client.php',
            'devis/ajax_search_produits.php',
            'devis/ajax_search_clients.php',
        ];
    }

    /**
     * Routes autorisées pour le rôle « gestion_stock » (périmètre de base).
     */
    function admin_route_gestion_stock_base_allowed($p) {
        if ($p === '') {
            return false;
        }
        if (strpos($p, 'stock/') === 0 || strpos($p, 'produits/') === 0) {
            return true;
        }

        return in_array($p, [
            'categories/produits.php',
            'categories/modifier.php',
            'categories/ajouter.php',
            'categories/supprimer.php',
        ], true);
    }

    /**
     * Routes supplémentaires pour « gestion_stock_general » (périmètre étendu).
     */
    function admin_route_gestion_stock_general_extra_allowed($p) {
        if ($p === 'dashboard.php' || $p === 'parametres.php') {
            return true;
        }
        if (strpos($p, 'categories/') === 0) {
            return true;
        }
        if ($p === 'parametres/alertes-stock.php') {
            return true;
        }
        if (strpos($p, 'parametres/emplacement-') === 0) {
            return true;
        }

        return false;
    }

    /**
     * URL de secours (relative à admin/) si la route est interdite
     */
    function admin_role_default_redirect_path($role) {
        $r = admin_normalize_role_for_route($role);
        switch ($r) {
            case 'admin':
                return 'dashboard.php';
            case 'informaticien':
            case 'developpeur':
                return 'dashboard.php';
            case 'commercial_general':
                return 'devis/index.php';
            case 'commercial':
                return 'commandes/index.php';
            case 'caissier':
                return 'caisse/encaisser-ticket.php';
            case 'comptabilite':
                return 'comptabilite/index.php';
            case 'rh':
                return 'contacts/index.php';
            case 'gestion_stock':
                return 'stock/index.php';
            case 'gestion_stock_general':
                return 'dashboard.php';
            default:
                return 'dashboard.php';
        }
    }

    /**
     * Indique si le rôle peut accéder au script courant
     */
    function admin_route_is_allowed($role, $relativePath) {
        $r = admin_normalize_role_for_route($role);
        $p = $relativePath;

        $acces_sans_restriction = ($r === 'informaticien' || $r === 'developpeur');

        if ($r === 'admin' || $acces_sans_restriction) {
            if ($p === '' || $p === 'dashboard.php' || $p === 'login.php' || $p === 'logout.php') {
                return true;
            }
            if ($p === 'profil.php' || $p === 'inscription-admin.php' || $p === 'test-notification.php') {
                return true;
            }
            // Paramètres + sous-pages : informaticien / développeur (rôle admin exclu)
            if ($p === 'parametres.php' || strpos($p, 'parametres/') === 0) {
                return $acces_sans_restriction;
            }
            if ($r === 'admin') {
                $interdits = ['caisse/', 'zones-livraison/', 'commandes/', 'users/', 'comptes/'];
                foreach ($interdits as $prefix) {
                    if (strpos($p, $prefix) === 0) {
                        return false;
                    }
                }
            }
            // Informaticien / développeur : accès complet y compris caisse et zones de livraison
            return true;
        }

        if ($p === '') {
            return false;
        }

        // Pages communes à tous les comptes connectés
        if ($p === 'profil.php') {
            return true;
        }

        $starts = function ($prefix) use ($p) {
            return strpos($p, $prefix) === 0;
        };

        switch ($r) {
            case 'commercial_general':
                return $starts('devis/')
                    || $starts('commandes/')
                    || $starts('caisse/')
                    || $starts('commercial/');

            case 'commercial':
                if (in_array($p, admin_route_commercial_devis_scripts(), true)) {
                    return true;
                }
                return $starts('commandes/')
                    || $starts('caisse/')
                    || $starts('commercial/');

            case 'comptabilite':
                if ($starts('comptabilite/')) {
                    return true;
                }
                if ($p === 'contacts/index.php') {
                    return true;
                }
                if ($p === 'commandes/historique-ventes.php') {
                    return true;
                }
                if ($p === 'devis/devis_par_client.php') {
                    return true;
                }
                $compta_devis = [
                    'devis/facture_mensuelle.php',
                    'devis/facture_mensuelle_generer.php',
                    'devis/facture_mensuelle_valider.php',
                    'devis/bl_voir.php',
                    'devis/bl_facture.php',
                    'devis/facture.php',
                    'devis/details.php',
                    'devis/br_voir.php',
                    'devis/br_par_client.php',
                    'devis/bl_modifier.php',
                ];
                return in_array($p, $compta_devis, true);

            case 'rh':
                return $starts('contacts/')
                    || $starts('users/')
                    || $p === 'comptes/index.php'
                    || $p === 'comptes/employe-activite.php'
                    || $p === 'comptes/employe-activite-liste.php'
                    || $starts('comptes/employes/');

            case 'caissier':
                return $p === 'caisse/encaisser-ticket.php'
                    || $p === 'caisse/historique-encaissements.php'
                    || $p === 'caisse/depenses.php'
                    || $p === 'caisse/post.php';

            case 'gestion_stock':
                return admin_route_gestion_stock_base_allowed($p);

            case 'gestion_stock_general':
                return admin_route_gestion_stock_base_allowed($p)
                    || admin_route_gestion_stock_general_extra_allowed($p);

            default:
                return false;
        }
    }

    /**
     * URL absolue (chemin) vers une page sous admin/
     */
    function admin_route_build_url($relativeUnderAdmin) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($script);
        if (preg_match('#^(.*)/admin(?:/|$)#', $dir, $m)) {
            return $m[1] . '/admin/' . ltrim($relativeUnderAdmin, '/');
        }
        return '/admin/' . ltrim($relativeUnderAdmin, '/');
    }

    /**
     * Applique le contrôle d'accès (à apporter après vérification de session admin).
     */
    function admin_route_enforce() {
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
            return;
        }

        $role = $_SESSION['admin_role'] ?? 'admin';
        $_SESSION['admin_role'] = admin_normalize_role_for_route($role);

        $rel = admin_route_relative_path();
        if (admin_route_is_allowed($_SESSION['admin_role'], $rel)) {
            return;
        }

        $target = admin_role_default_redirect_path($_SESSION['admin_role']);
        header('Location: ' . admin_route_build_url($target));
        exit;
    }

    /**
     * Pour scripts AJAX (JSON) : réponse vide / 403 sans redirection HTML.
     */
    function admin_route_enforce_json_empty() {
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode([]);
            exit;
        }
        $role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
        $_SESSION['admin_role'] = $role;
        $rel = admin_route_relative_path();
        if (admin_route_is_allowed($role, $rel)) {
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
}
