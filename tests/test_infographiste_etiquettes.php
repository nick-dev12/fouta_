<?php
/**
 * L'INFOGRAPHISTE VOIT LES ÉTIQUETTES (07/09/2026) — le contrôle en ligne de commande.
 *
 * Ce que la direction demande : « il doit avoir la possibilité de voir les
 * étiquettes de pièces ». On vérifie donc, rôle par rôle :
 *   1. les routes d'étiquette lui sont ouvertes (et les pages à prix/stock
 *      restent fermées) ;
 *   2. le droit admin_can_voir_etiquettes() dit oui pour lui, non pour un rôle
 *      qui n'a rien à y faire ;
 *   3. les fichiers visés existent VRAIMENT (une liste blanche qui nomme un
 *      fichier absent est une porte peinte sur un mur — c'était le cas de
 *      « etiquette-piece.php »).
 *
 * À jouer :  php tests/test_infographiste_etiquettes.php
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

echo "— les routes de l'infographiste —\n";
$ouvertes = [
    'produits/photo-travail.php',
    'produits/photo-editer.php',
    'produits/etiquettes.php',
    'produits/etiquette-piece-voir.php',
    'produits/etiquette-piece-image.php',
    'produits/etiquette-piece-pdf.php',
    'produits/ajax_etiquette_imprimee.php',
    'produits/etiquette-barre.php',
    'parametres/emplacement-noeud-etiquette.php',
    /* 12/09/2026 : « il voit les étiquettes comme l'informaticien les voit »
       — la conception (dimensions d'impression), le lot en un seul PDF, et
       l'enregistrement des deux références de la pièce. */
    'parametres/etiquettes-produit.php',
    'produits/etiquette-piece-pdf-lot.php',
    'produits/ajax_references_enregistrer.php',
];
foreach ($ouvertes as $r) {
    verifie("ouverte : $r", true, admin_route_is_allowed('photographe', $r));
}

echo "— ce qui doit rester fermé —\n";
$fermees = [
    'produits/ajuster-stock.php',      // prix, stock, fournisseur
    'produits/index.php',              // le catalogue avec les prix
    'dashboard.php',                   // les chiffres
    'produits/detourage-lot.php',      // l'outil de lot de l'informaticien
];
foreach ($fermees as $r) {
    verifie("fermée : $r", false, admin_route_is_allowed('photographe', $r));
}

echo "— les fichiers de la liste blanche existent —\n";
foreach ($ouvertes as $r) {
    verifie("le fichier existe : $r", true, is_file($RACINE . '/admin/' . $r));
}

echo "— le droit de voir les étiquettes —\n";
$cas = [
    'photographe' => true,
    'gestion_stock' => true,
    'gestion_stock_general' => true,
    'informaticien' => true,
    'developpeur' => true,
    'admin' => true,
    'caissier' => false,
    'rh' => false,
];
foreach ($cas as $role => $attendu) {
    $_SESSION['admin_role'] = $role;
    verifie("admin_can_voir_etiquettes() pour « $role »", $attendu, admin_can_voir_etiquettes());
}

echo "— les deux droits nommés du 12/09 —\n";
$cas_conception = [
    'photographe' => true,          // il dessine l'étiquette : il la règle
    'gestion_stock_general' => true,
    'informaticien' => true,
    'gestion_stock' => false,       // le rayonniste imprime, il ne conçoit pas
    'caissier' => false,
    'commercial_general' => false,
];
foreach ($cas_conception as $role => $attendu) {
    $_SESSION['admin_role'] = $role;
    verifie("admin_can_conception_etiquettes() pour « $role »", $attendu, admin_can_conception_etiquettes());
    verifie("admin_can_modifier_references_piece() pour « $role »", $attendu, admin_can_modifier_references_piece());
}

echo "— le périmètre stock lui reste fermé —\n";
$_SESSION['admin_role'] = 'photographe';
verifie('le photographe ne règle pas la disposition', false, admin_can_gestion_stock_etendue());
verifie("le photographe n'a pas le périmètre stock", false, admin_can_gestion_stock());

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
