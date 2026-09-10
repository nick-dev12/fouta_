<?php
/**
 * L'ESPACE DES COMMERCIAUX (10/09/2026) — le contrôle en ligne de commande.
 *
 * Pour le commercial et le commercial général, on vérifie :
 *   1. ils atterrissent sur leur accueil, qui leur est ouvert ;
 *   2. chaque lien de l'accueil mène à un fichier qui EXISTE et que les deux
 *      rôles ont le droit d'ouvrir (pas de porte peinte sur un mur) ;
 *   3. leurs droits nommés n'ont pas bougé (l'équipe Fouta a ouvert les BL
 *      au commercial le 14/08, commit 58b36f3 : on ne les lui retire pas) ;
 *   4. la barre de recherche du haut envoie chaque rôle là où il peut aller,
 *      et disparaît pour qui n'a aucune page de recherche ouverte ;
 *   5. seuls les modules de vente retrouvent la racine de 16 px ;
 *   6. les files de l'accueil se lisent SANS filet (aucun catch) et comptent
 *      la même chose qu'une seconde lecture indépendante.
 *
 * À jouer :  php tests/test_espace_commercial.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/admin_route_access.php';
require_once $RACINE . '/includes/admin_permissions.php';
require_once $RACINE . '/includes/fpl_assets.php';

$ok = 0;
$ko = 0;
function verifie($libelle, $attendu, $obtenu) {
    global $ok, $ko;
    if ($attendu === $obtenu) {
        $ok++;
        echo "  OK  $libelle\n";
    } else {
        $ko++;
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ", obtenu " . var_export($obtenu, true) . ")\n";
    }
}

$ROLES = ['commercial', 'commercial_general'];

echo "— l'accueil —\n";
verifie("le fichier de l'accueil existe", true, is_file($RACINE . '/admin/commercial/index.php'));
foreach ($ROLES as $role) {
    verifie("« $role » atterrit sur son accueil", 'commercial/index.php', admin_role_default_redirect_path($role));
    verifie("« $role » peut ouvrir son accueil", true, admin_route_is_allowed($role, 'commercial/index.php'));
}

echo "— chaque lien de l'accueil mène à une page ouverte qui existe —\n";
$source = file_get_contents($RACINE . '/admin/commercial/index.php');
preg_match_all('#(?:href|action)="\.\./([a-z_-]+/[a-z_-]+\.php)#', $source, $m);
$liens = array_values(array_unique($m[1]));
verifie("l'accueil porte au moins 5 liens distincts", true, count($liens) >= 5);
foreach ($liens as $l) {
    verifie("le fichier existe : $l", true, is_file($RACINE . '/admin/' . $l));
    foreach ($ROLES as $role) {
        verifie("ouvert à « $role » : $l", true, admin_route_is_allowed($role, $l));
    }
}

echo "— les droits nommés des deux rôles —\n";
foreach ($ROLES as $role) {
    $_SESSION['admin_role'] = $role;
    verifie("« $role » prépare des tickets", true, admin_can_caisse_vendeur());
    verifie("« $role » fait des devis", true, admin_can_devis());
    verifie("« $role » fait des bons de livraison", true, admin_can_bl_retours_b2b());
    verifie("« $role » n'encaisse pas", false, admin_can_encaisser_ticket());
}

echo "— ce qui reste fermé —\n";
/* stock/mouvements.php a quitté cette liste le 10/09 : la direction a ouvert
   l'historique des mouvements au commercial général (tests/test_chantier_commercial.php). */
foreach (['produits/index.php', 'produits/ajuster-stock.php', 'parametres.php', 'comptabilite/index.php', 'users/index.php'] as $r) {
    foreach ($ROLES as $role) {
        verifie("fermé à « $role » : $r", false, admin_route_is_allowed($role, $r));
    }
}

echo "— la barre de recherche du haut —\n";
foreach ($ROLES as $role) {
    $c = admin_recherche_cible($role);
    verifie("« $role » cherche dans la caisse", 'caisse/index.php', $c['page'] ?? null);
    verifie("« $role » envoie le champ que la caisse lit", 'q', $c['champ'] ?? null);
}
verifie('la caisse lit bien ?q=', true, strpos(file_get_contents($RACINE . '/admin/caisse/index.php'), "\$_GET['q']") !== false);
verifie('le catalogue lit bien ?recherche=', true, strpos(file_get_contents($RACINE . '/admin/produits/index.php'), "\$_GET['recherche']") !== false);
foreach (['gestion_stock', 'gestion_stock_general', 'informaticien', 'developpeur', 'admin'] as $role) {
    $c = admin_recherche_cible($role);
    verifie("« $role » cherche toujours dans le catalogue", 'produits/index.php', $c['page'] ?? null);
}
foreach (['caissier', 'comptabilite', 'rh', 'photographe'] as $role) {
    verifie("« $role » n'a plus de barre qui mène à un refus", null, admin_recherche_cible($role));
}
foreach (['commercial', 'commercial_general', 'gestion_stock', 'gestion_stock_general', 'informaticien', 'developpeur', 'admin', 'caissier', 'comptabilite', 'rh', 'photographe'] as $role) {
    $c = admin_recherche_cible($role);
    if ($c !== null) {
        verifie("la recherche de « $role » mène à une page qu'il peut ouvrir", true, admin_route_is_allowed($role, $c['page']));
    }
}

echo "— la racine de 16 px, pour les modules de vente seulement —\n";
foreach (['devis/devis.php', 'devis/index.php', 'devis/details.php', 'devis/bl_voir.php', 'caisse/index.php', 'caisse/encaisser-ticket.php', 'commandes/index.php'] as $p) {
    verifie("16 px : $p", true, fpl_admin_racine_16px($p));
}
foreach (['produits/index.php', 'produits/mon-travail.php', 'produits/photo-travail.php', 'stock/mouvements.php', 'dashboard.php', 'commercial/index.php', 'parametres/hierarchie-entrepot.php', 'devis/sous/x.php', ''] as $p) {
    verifie('racine inchangée : ' . ($p !== '' ? $p : '(vide)'), false, fpl_admin_racine_16px($p));
}
foreach (['fpl.css', 'fpl-tokens.css', 'fpl-admin-compat.css', 'fpl-admin-overrides.css'] as $f) {
    $n = preg_match_all('/[0-9.]+rem\b/', file_get_contents($RACINE . '/css/' . $f));
    verifie("la couche FPL « $f » n'a aucun rem (le menu ne peut pas bouger)", 0, $n);
}

echo "— les files de l'accueil, lues sans filet —\n";
require_once $RACINE . '/models/model_commercial_accueil.php';
/* Le même ordre que devis/devis.php : SANS model_factures_devis.php chargé
 * avant, get_devis_sans_facture_payee() ne sait pas lire la colonne « payee »
 * et rend TOUS les devis, facture payée comprise (piège vu le 10/09). */
require_once $RACINE . '/models/model_factures_devis.php';
require_once $RACINE . '/models/model_devis.php';
verifie('la règle Fouta sait lire les factures payées', true, function_exists('factures_devis_col_payee_ok') && factures_devis_col_payee_ok());
$comptes = $db->query("SELECT id FROM admin WHERE role IN ('commercial', 'commercial_general') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
verifie('il existe des comptes commerciaux à contrôler', true, count($comptes) > 0);
$devis_liste_fouta = get_devis_sans_facture_payee();
foreach ($comptes as $id) {
    $id = (int) $id;
    $n = (int) $db->query("SELECT COUNT(*) FROM caisse_ventes WHERE statut = 'en_attente' AND sync_deleted_at IS NULL AND admin_id = $id")->fetchColumn();
    verifie("compte $id : tickets en attente = seconde lecture ($n)", $n, count(commercial_tickets_en_attente($id)));

    $attendus = count(array_filter($devis_liste_fouta, function ($d) use ($id) {
        return (int) $d['admin_createur_id'] === $id && empty($d['sync_deleted_at']);
    }));
    verifie("compte $id : devis ouverts = la règle de la liste Devis ($attendus)", $attendus, count(commercial_devis_ouverts($id)));

    $jour = commercial_tickets_du_jour($id);
    $nj = (int) $db->query("SELECT COUNT(*) FROM caisse_ventes WHERE sync_deleted_at IS NULL AND admin_id = $id AND DATE(date_vente) = CURDATE()")->fetchColumn();
    verifie("compte $id : tickets du jour = seconde lecture ($nj)", $nj, $jour['n']);

    $nbl = (int) $db->query("SELECT COUNT(*) FROM bons_livraison WHERE statut = 'brouillon' AND sync_deleted_at IS NULL AND admin_createur_id = $id")->fetchColumn();
    verifie("compte $id : BL en brouillon = seconde lecture ($nbl)", $nbl, count(commercial_bl_brouillons($id)));
}

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
