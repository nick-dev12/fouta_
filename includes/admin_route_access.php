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
     * URL de secours (relative à admin/) si la route est interdite
     */
    function admin_role_default_redirect_path($role) {
        $r = admin_normalize_role_for_route($role);
        switch ($r) {
            case 'commercial':
                return 'devis/index.php';
            case 'caissier':
                return 'caisse/encaisser-ticket.php';
            case 'comptabilite':
                return 'comptabilite/index.php';
            case 'rh':
                return 'contacts/index.php';
            case 'gestion_stock':
                return 'stock/index.php';
            default:
                return 'dashboard.php';
        }
    }

    /**
     * Indique si le rôle peut accéder au script courant
     */
    function admin_route_is_allowed($role, $relativePath) {
        $r = admin_normalize_role_for_route($role);
        if ($r === 'admin') {
            return true;
        }

        $p = $relativePath;
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
            case 'commercial':
                return $starts('devis/')
                    || $starts('commandes/')
                    || $starts('caisse/')
                    || $starts('commercial/');

            case 'comptabilite':
                if ($p === 'comptabilite/index.php' || $p === 'comptabilite/bl-fiche-client.php') {
                    return true;
                }
                if ($p === 'commandes/historique-ventes.php') {
                    return true;
                }
                $compta_devis = [
                    'devis/facture_mensuelle.php',
                    'devis/facture_mensuelle_generer.php',
                    'devis/facture_mensuelle_valider.php',
                    'devis/bl_voir.php',
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
                    || $p === 'caisse/post.php';

            case 'gestion_stock':
                return $starts('stock/')
                    || $starts('produits/')
                    || $p === 'categories/produits.php'
                    || $p === 'categories/modifier.php'
                    || $p === 'categories/ajouter.php'
                    || $p === 'categories/supprimer.php';

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
        if ($role === 'admin' || admin_route_is_allowed($role, $rel)) {
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
}
