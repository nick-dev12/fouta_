<?php
/**
 * LE PDF D'UN LOT D'ÉTIQUETTES DE PIÈCES (09/09/2026) — le banc en ligne de commande.
 *
 * Ce que la direction demande : « je coche plusieurs étiquettes, je clique, et
 * elles sont TOUTES dans un seul PDF » — n'importe lesquelles, quel que soit
 * le rayon. On vérifie donc, du plus profond au plus visible :
 *   1. le moteur PDF multi-pages (une page par étiquette, à la taille réelle,
 *      et un lot d'UNE étiquette identique au PDF d'une étiquette seule) ;
 *   2. le filtre : « tout sélectionner » lit EXACTEMENT le même filtre que la
 *      liste affichée (sinon le PDF contiendrait d'autres pièces) ;
 *   3. les portes : le compte restreint (infographiste) n'imprime pas de lot ;
 *   4. l'écran : la case à cocher, la barre du lot et son formulaire existent
 *      vraiment dans la page, et le plafond dit la même chose des deux côtés.
 *
 * À jouer :  php tests/test_etiquettes_lot_pdf.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/models/model_produits.php';
require_once $RACINE . '/models/model_etiquettes_fpl.php';
require_once $RACINE . '/includes/etiquette_fpl70.php';
require_once $RACINE . '/includes/admin_route_access.php';

$ok = 0;
$ko = 0;
function verifie($libelle, $attendu, $obtenu)
{
    global $ok, $ko;
    if ($attendu === $obtenu) {
        $ok++;
        echo "  OK  $libelle\n";
    } else {
        $ko++;
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true) . ")\n";
    }
}
function vrai($libelle, $cond)
{
    verifie($libelle, true, (bool) $cond);
}

echo "— les fichiers existent (une liste blanche qui nomme un fichier absent est une porte peinte sur un mur) —\n";
foreach (['admin/produits/etiquette-piece-pdf-lot.php',
    'admin/produits/ajax_etiquettes_ids.php',
    'admin/produits/etiquettes.php'] as $f) {
    vrai("le fichier existe : $f", is_file($RACINE . '/' . $f));
}
vrai('le moteur porte etiquette70_pdf_multi()', function_exists('etiquette70_pdf_multi'));
vrai('le modèle porte etiquettes_pieces_ids()', function_exists('etiquettes_pieces_ids'));
vrai('le modèle porte etiquettes_pieces_criteres()', function_exists('etiquettes_pieces_criteres'));

echo "— le filtre du lot est CELUI de la liste affichée —\n";
$liste = etiquettes_pieces_liste('', null, null, null, 1, 20);
$tous = etiquettes_pieces_ids('', null, null, null, 300);
vrai('la liste rend des pièces', count($liste['lignes']) > 0);
verifie('les ids commencent par ce que montre la page 1, dans le même ordre',
    array_map(function ($l) { return (int) $l['id']; }, $liste['lignes']),
    array_slice($tous, 0, count($liste['lignes'])));
verifie('le plafond est tenu', true, count($tous) <= 300);

foreach (['retro', 'filtre', 'FPL'] as $q) {
    $l = etiquettes_pieces_liste($q, null, null, null, 1, 20);
    $i = etiquettes_pieces_ids($q, null, null, null, 300);
    verifie("la recherche « $q » donne autant d'ids que de résultats",
        min(300, (int) $l['total']), count($i));
}
$l_af = etiquettes_pieces_liste('', 'a_imprimer', null, null, 1, 20);
$i_af = etiquettes_pieces_ids('', 'a_imprimer', null, null, 300);
verifie("le filtre « à imprimer » vaut pour le lot aussi",
    min(300, (int) $l_af['total']), count($i_af));

echo "— le PDF du lot —\n";
$cote = (int) round(70 / 25.4 * 300);
$choix = array_slice($tous, 0, 4);
$pages = [];
foreach ($choix as $id) {
    $p = get_produit_by_id_sans_filtre_acces($id);
    if ($p === false) {
        continue;
    }
    $img = etiquette70_rendu(etiquette70_donnees_pour_produit($p), $cote);
    ob_start();
    imagejpeg($img, null, 94);
    $pages[] = ['jpeg' => (string) ob_get_clean(), 'w' => $cote, 'h' => $cote];
    imagedestroy($img);
}
verifie('quatre dessins produits', 4, count($pages));

$pdf = etiquette70_pdf_multi($pages, 70, 70);
vrai("le PDF n'est pas vide", strlen($pdf) > 40000);
verifie("l'en-tête PDF est là", '%PDF-1.4', substr($pdf, 0, 8));
verifie('le PDF est clos par %%EOF', '%%EOF', substr(rtrim($pdf), -5));
verifie('quatre pages', 4, substr_count($pdf, '/Type /Page /Parent'));
verifie('quatre images', 4, substr_count($pdf, '/Subtype /Image'));
vrai('le compte des pages annonce 4', strpos($pdf, '/Count 4') !== false);
$carre = preg_match('#/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]#', $pdf, $m70) === 1;
vrai('la page fait 70 × 70 mm', $carre
    && abs((float) $m70[1] - 70 * 72 / 25.4) < 0.02
    && abs((float) $m70[2] - 70 * 72 / 25.4) < 0.02);

// les quatre dessins doivent DIFFÉRER : quatre pièces, quatre étiquettes
$empreintes = [];
foreach ($pages as $pg) {
    $empreintes[md5($pg['jpeg'])] = true;
}
verifie('les quatre étiquettes sont différentes', 4, count($empreintes));

// LA PREUVE LA PLUS PARLANTE : un lot d'une seule étiquette EST le PDF d'une
// seule étiquette — le lot n'invente pas un autre dessin.
verifie("un lot d'une étiquette = le PDF de l'étiquette seule",
    etiquette70_pdf($pages[0]['jpeg'], $cote, $cote, 70, 70),
    etiquette70_pdf_multi([$pages[0]], 70, 70));

verifie('un lot vide rend une chaîne vide', '', etiquette70_pdf_multi([], 70, 70));

/* Une page qui n'est pas carrée : le dessin au côté court, centré.
   On LIT les nombres du PDF au lieu de comparer une chaîne toute faite :
   PHP 8.4 a corrigé round() sur les cas limites, et 49.605 y devient 49.6
   là où PHP 8.3 donnait 49.61 (quatre millièmes de millimètre). Le VPS
   tourne en 8.4, le poste en 8.3 — un test écrit en dur y échouait sans
   qu'aucun dessin ne bouge. */
$rect = etiquette70_pdf_multi(array_slice($pages, 0, 2), 65, 100);
$L65 = 65 * 72 / 25.4;
$H100 = 100 * 72 / 25.4;
$boite = preg_match('#/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]#', $rect, $mb) === 1;
vrai('la page annonce ses deux côtés', $boite);
vrai('page large de 65 mm', $boite && abs((float) $mb[1] - $L65) < 0.02);
vrai('page haute de 100 mm', $boite && abs((float) $mb[2] - $H100) < 0.02);

$pose = preg_match('#q ([\d.]+) 0 0 ([\d.]+) ([\d.]+) ([\d.]+) cm#', $rect, $mc) === 1;
vrai('le dessin est posé sur la page', $pose);
vrai('le dessin est carré au côté court', $pose && abs((float) $mc[1] - (float) $mc[2]) < 0.02
    && abs((float) $mc[1] - $L65) < 0.02);
vrai('le dessin est collé au bord gauche', $pose && abs((float) $mc[3]) < 0.02);
vrai('le dessin est centré en hauteur', $pose && abs((float) $mc[4] - ($H100 - $L65) / 2) < 0.02);
verifie('deux pages sur la planche 65 × 100', 2, substr_count($rect, '/Type /Page /Parent'));

// la table xref doit pointer sur de VRAIS objets, sinon les lecteurs stricts refusent
$lignes = [];
if (preg_match('/xref\s+0 (\d+)\s+(.*?)trailer/s', $pdf, $m)) {
    preg_match_all('/^(\d{10}) 00000 n $/m', $m[2], $mm);
    $lignes = $mm[1];
}
verifie('la table xref liste les 14 objets (2 + 3 × 4)', 14, count($lignes));
$xref_bon = true;
foreach ($lignes as $i => $off) {
    $attendu = ($i + 1) . ' 0 obj';
    if (substr($pdf, (int) $off, strlen($attendu)) !== $attendu) {
        $xref_bon = false;
    }
}
vrai('chaque décalage de la xref tombe sur son objet', $xref_bon);

echo "— les portes —\n";
verifie("le lot est FERMÉ à l'infographiste", false, admin_route_is_allowed('photographe', 'produits/etiquette-piece-pdf-lot.php'));
verifie("la sélection en masse est FERMÉE à l'infographiste", false, admin_route_is_allowed('photographe', 'produits/ajax_etiquettes_ids.php'));
verifie("l'étiquette seule lui reste ouverte", true, admin_route_is_allowed('photographe', 'produits/etiquette-piece-pdf.php'));
foreach (['informaticien', 'developpeur', 'gestion_stock'] as $role) {
    verifie("le lot est ouvert à « $role »", true, admin_route_is_allowed($role, 'produits/etiquette-piece-pdf-lot.php'));
    verifie("la sélection en masse est ouverte à « $role »", true, admin_route_is_allowed($role, 'produits/ajax_etiquettes_ids.php'));
}

echo "— l'écran —\n";
$page = (string) file_get_contents($RACINE . '/admin/produits/etiquettes.php');
vrai('la colonne des cases est dans le tableau des pièces', strpos($page, 'id="etq-tous"') !== false);
vrai('chaque ligne porte sa case', strpos($page, 'class="etq-case etq-pick"') !== false);
vrai('la barre du lot poste vers le PDF de lot', strpos($page, 'action="etiquette-piece-pdf-lot.php"') !== false);
vrai('le PDF de lot part en POST', strpos($page, 'method="POST" action="etiquette-piece-pdf-lot.php"') !== false);
vrai('le jeton CSRF voyage avec le lot', strpos($page, 'name="csrf_token"') !== false);
vrai('la taille de la planche se choisit', strpos($page, '<select name="format">') !== false);
vrai('« Tout sélectionner » appelle le serveur', strpos($page, 'ajax_etiquettes_ids.php?') !== false);
vrai('la sélection survit au changement de page (sessionStorage)', strpos($page, 'sessionStorage') !== false);
vrai('la case et la barre ne s\'affichent que pour qui imprime', strpos($page, '$peut_imprimer && $formats_piece !== []') !== false);

$lot = (string) file_get_contents($RACINE . '/admin/produits/etiquette-piece-pdf-lot.php');
vrai('le lot refuse un jeton CSRF absent', strpos($lot, 'hash_equals') !== false);
vrai('le lot écarte le compte restreint', strpos($lot, 'admin_is_restricted_admin_account()') !== false);
vrai('le lot trace chaque impression', strpos($lot, "etiquette_tracer_impression('produit'") !== false);
vrai('le lot desserre le temps et la mémoire', strpos($lot, "ini_set('memory_limit'") !== false);

// LE MÊME PLAFOND DES DEUX CÔTÉS : l'écran ne doit pas promettre plus que le PDF n'accepte
preg_match("/define\('FPL_LOT_ETIQUETTES_MAX', (\d+)\)/", $lot, $m1);
preg_match('/\$lot_max = (\d+);/', $page, $m2);
preg_match('/const MAX = <\?php echo \(int\) \$lot_max/', $page, $m3);
verifie('le plafond du PDF et celui de l\'écran sont le même nombre',
    isset($m1[1]) ? $m1[1] : 'aucun', isset($m2[1]) ? $m2[1] : 'aucun');
vrai('le plafond du navigateur vient de celui de la page', $m3 !== []);

$ajax = (string) file_get_contents($RACINE . '/admin/produits/ajax_etiquettes_ids.php');
vrai('la sélection en masse écarte le compte restreint', strpos($ajax, 'admin_is_restricted_admin_account()') !== false);
vrai('la sélection en masse relit le filtre côté serveur', strpos($ajax, 'etiquettes_pieces_ids(') !== false);
vrai("la sélection en masse ne reçoit jamais d'ids tout faits", strpos($ajax, "\$_GET['ids']") === false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
