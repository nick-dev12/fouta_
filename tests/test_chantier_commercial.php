<?php
/**
 * LE CHANTIER DU COMMERCIAL GÉNÉRAL (10/09/2026) — contrôles en ligne de commande.
 *
 * Ce fichier grandit à chaque point du chantier décrit dans le rapport
 * « Chaîne commerciale Fouta ». Chaque section dit ce que la direction a
 * demandé et vérifie la règle, pas seulement la présence du code.
 *
 * À jouer :  php tests/test_chantier_commercial.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/admin_route_access.php';
require_once $RACINE . '/includes/admin_permissions.php';

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

/** Le texte d'un bloc « if ($action === 'x') » jusqu'au bloc suivant. */
function bloc_action($source, $action) {
    $debut = strpos($source, "if (\$action === '$action')");
    if ($debut === false) {
        return '';
    }
    $suite = strpos($source, "if (\$action === '", $debut + 10);
    return $suite === false ? substr($source, $debut) : substr($source, $debut, $suite - $debut);
}

echo "— historique des mouvements et rapport journalier (demande de la direction) —\n";
foreach (['stock/mouvements.php', 'produits/rapport-jour.php'] as $p) {
    verifie("le fichier existe : $p", true, is_file("$RACINE/admin/$p"));
    verifie("ouvert au commercial général : $p", true, admin_route_is_allowed('commercial_general', $p));
    verifie("encore fermé au commercial simple : $p", false, admin_route_is_allowed('commercial', $p));
}
foreach (['produits/ajuster-stock.php', 'produits/entree.php'] as $p) {
    verifie("toujours fermé au commercial général : $p", false, admin_route_is_allowed('commercial_general', $p));
}
$nav = file_get_contents("$RACINE/admin/includes/nav.php");
verifie("le menu réserve les deux entrées au commercial général", true, strpos($nav, "<?php if (\$admin_role === 'commercial_general'): ?>") !== false);
$mouv = file_get_contents("$RACINE/admin/stock/mouvements.php");
verifie("l'historique ne mène pas un commercial vers la fiche stock", true, strpos($mouv, '$mouvements_fiche_fermee') !== false);
$rapport = file_get_contents("$RACINE/admin/produits/rapport-jour.php");
verifie("le Retour du rapport mène un commercial à son accueil", true, strpos($rapport, "'../commercial/index.php'") !== false);
verifie("le rapport d'un vendeur compte les sorties de ses tickets", true, strpos($rapport, "m.reference_type = 'caisse_vente'") !== false);

echo "— point 1 : encaisser est le métier du caissier —\n";
$encaissement_direct = [
    'commercial_general' => false,
    'commercial' => false,
    'caissier' => false,
    'informaticien' => true,
    'developpeur' => true,
];
foreach ($encaissement_direct as $role => $attendu) {
    $_SESSION['admin_role'] = $role;
    verifie("encaissement direct pour « $role »", $attendu, admin_can_caisse_vendeur() && admin_can_encaisser_ticket());
}
$_SESSION['admin_role'] = 'caissier';
verifie('le caissier encaisse toujours les tickets préparés', true, admin_can_encaisser_ticket());
$_SESSION['admin_role'] = 'commercial_general';
verifie('le commercial général prépare toujours ses tickets', true, admin_can_caisse_vendeur());

$api = file_get_contents("$RACINE/admin/caisse/api.php");
$post = file_get_contents("$RACINE/admin/caisse/post.php");
foreach (['admin/caisse/api.php' => $api, 'admin/caisse/post.php' => $post] as $f => $source) {
    $bloc = bloc_action($source, 'encaisser');
    verifie("$f : le bloc « encaisser » existe", true, $bloc !== '');
    verifie("$f : il exige le droit d'encaisser", true, strpos($bloc, 'admin_can_encaisser_ticket()') !== false);
}
verifie("générer un ticket reste ouvert au vendeur", false, strpos(bloc_action($api, 'generer_ticket'), 'admin_can_encaisser_ticket()') !== false);
verifie("finaliser un ticket reste au caissier", true, strpos(bloc_action($post, 'finaliser_ticket'), 'admin_can_encaisser_ticket()') !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
