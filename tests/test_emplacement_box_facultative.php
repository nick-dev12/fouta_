<?php
/**
 * LES POSITIONS POSÉES SOUS UNE BARRE SE VOIENT DANS LE CHAMP POSITION
 * (09/09/2026, constat de la direction).
 *
 * « Sur le chemin Étage 2 · 4 · 15A · E1 · B1, les données s'affichent dans le
 * champ Box alors qu'elles ont été enregistrées en Position, et Position dit
 * « choisissez d'abord le niveau précédent ». »
 *
 * Deux causes, vérifiées sur les données du serveur :
 *  1. dans l'écran Structure, la pastille cochée d'avance était Box — le
 *     niveau FACULTATIF — et l'équipe a créé « 01 », « 02 » sans la changer :
 *     ces positions sont nées box (8 nœuds, remis en Position le 09/09) ;
 *  2. les deux sélecteurs d'emplacement exigeaient chaque niveau rempli avant
 *     de proposer le suivant : une box laissée vide bloquait le champ Position,
 *     alors que la box se saute depuis le 07/09.
 *
 * Vérifications structurelles, en lecture seule.
 * À jouer :  php tests/test_emplacement_box_facultative.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/models/model_entrepot_hierarchie_libre.php';
require_once $RACINE . '/models/model_entrepot_structure_champs.php';

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
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ", obtenu " . var_export($obtenu, true) . ")\n";
    }
}

echo "— 1. l'écran Structure coche d'avance le niveau qu'on remplit, pas le facultatif —\n";
$ecran = file_get_contents($RACINE . '/admin/produits/structure-entrepot.php');
verifie('la pastille cochée d\'avance est calculée ($defaut_idx)', true, strpos($ecran, '$defaut_idx = $i;') !== false);
verifie('…comme le premier niveau qui n\'est PAS facultatif', true,
    strpos($ecran, "!entrepot_hierarchie_def_est_facultatif(\$de)") !== false);
verifie('…et la pastille cochée suit ce choix', true,
    strpos($ecran, "\$i === \$defaut_idx ? ' checked'") !== false && strpos($ecran, "\$i === 0 ? ' checked'") === false);
verifie('le nom proposé suit la pastille cochée', true,
    strpos($ecran, "\$defs_enfants[\$defaut_idx ?? 0]['label']") !== false);

echo "— 2. les niveaux disent au sélecteur s'ils sont facultatifs —\n";
$carto = file_get_contents($RACINE . '/admin/parametres/partials/entrepot-modals-hierarchie.php');
verifie('cartographie : le drapeau facultatif est envoyé au JS', true, strpos($carto, "'facultatif' => (function_exists('entrepot_hierarchie_def_est_facultatif')") !== false);
$champs = function_exists('produit_emplacement_cascade_fields_config') ? produit_emplacement_cascade_fields_config() : [];
$noeuds = array_values(array_filter($champs, function ($f) { return ($f['type'] ?? '') === 'noeud'; }));
verifie('fiche pièce : chaque champ de niveau porte le drapeau facultatif', true,
    $noeuds !== [] && count(array_filter($noeuds, function ($f) { return array_key_exists('facultatif', $f); })) === count($noeuds));
if (function_exists('entrepot_hierarchie_facultatif_schema_ok') && entrepot_hierarchie_facultatif_schema_ok()) {
    $box = array_values(array_filter($noeuds, function ($f) { return strtolower((string) ($f['niveau'] ?? '')) === 'box'; }));
    if ($box) {
        verifie('…et la box est bien marquée facultative', 1, (int) $box[0]['facultatif']);
    }
} else {
    echo "  (la colonne facultatif n'existe pas dans cette base : le marquage de la box ne peut pas être prouvé ici)\n";
}

echo "— 3. les deux sélecteurs passent par-dessus une box vide —\n";
$js1 = file_get_contents($RACINE . '/js/admin-emplacement-produit.js');
$js2 = file_get_contents($RACINE . '/js/admin-emplacement-entrepot.js');
verifie('fiche pièce : un niveau facultatif vide ne bloque pas le suivant', true,
    (bool) preg_match('/if \(!val\) \{\s*\/\/[^\n]*\n(\s*\/\/[^\n]*\n)*\s*if \(parseInt\(prev\.facultatif, 10\) === 1\) \{\s*continue;/s', $js1));
verifie('fiche pièce : le libellé dit « (facultatif) »', true, strpos($js1, "(facultatif) —')") !== false);
verifie('cartographie : un niveau facultatif vide ne bloque pas le suivant', true,
    strpos($js2, "if (parseInt(ancestors[j].facultatif, 10) === 1) {") !== false);
verifie('cartographie : le parent est le niveau choisi le plus proche au-dessus', true,
    strpos($js2, 'for (var k = i - 1; k >= 0; k--)') !== false);
verifie('cartographie : la box n\'est plus obligatoire', true,
    strpos($js2, "sel.required = parseInt(anc.facultatif, 10) !== 1;") !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
