<?php
/**
 * LES COTES EXACTES DE L'ÉTIQUETTE DE BARRE (07/09/2026).
 *
 * La direction a fixé, pour le format 150 × 60 : QR 43 mm, écriture
 * 80 × 20 mm, écart 0,8 mm. On vérifie ici que la géométrie rend EXACTEMENT
 * ces millimètres, que l'écriture tient vraiment dans sa boîte quand on la
 * mesure sur la police d'impression, et — tout aussi important — que les
 * formats SANS cote en mm gardent au millimètre le calcul d'avant.
 *
 * Aucun accès à la base : la géométrie se nourrit d'un tableau de format.
 *
 * À jouer :  php tests/test_etiquette_barre_cotes.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/models/model_etiquettes_fpl.php';

$ok = 0;
$ko = 0;
function verifie($libelle, $attendu, $obtenu, $tolerance = 0.005) {
    global $ok, $ko;
    $bon = is_float($attendu) || is_int($attendu)
        ? ($obtenu !== null && abs((float) $obtenu - (float) $attendu) <= $tolerance)
        : $attendu === $obtenu;
    if ($bon) {
        $ok++;
        echo "  OK  $libelle\n";
    } else {
        $ko++;
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ", obtenu " . var_export($obtenu, true) . ")\n";
    }
}

function format_150x60($disposition = null) {
    return [
        'id' => 1, 'nom' => '150 × 60 mm', 'type' => 'barre',
        'largeur_mm' => 150, 'hauteur_mm' => 60,
        'disposition_barre' => $disposition === null ? null : json_encode($disposition),
    ];
}

/* La mesure du texte réellement dessiné, sur LA police du PDF. */
function texte_dessine($libelle, $police_mm) {
    $police = dirname(__DIR__) . '/fonts/etiquette70/barlow-condensed-700.ttf';
    $b = imagettfbbox($police_mm * (72.0 / 96.0), 0, $police, $libelle);

    return ['l' => abs($b[2] - $b[0]), 'h' => abs($b[7] - $b[1])];
}

echo "— les cotes de la direction : 150 × 60, QR 43, écriture 80 × 20, écart 0,8 —\n";
$cotes = ['qr_mm' => 43, 'texte_l_mm' => 80, 'texte_h_mm' => 20, 'ecart' => 0.8];
foreach (['AR1-01', 'C15A-01', 'AR12-013', 'B7'] as $lib) {
    $g = etiquette_geometrie_barre(format_150x60($cotes), $lib);
    verifie("« $lib » : QR = 43 mm", 43.0, $g['qr']);
    verifie("« $lib » : boîte de l'écriture = 80 mm de long", 80.0, $g['code_largeur']);
    verifie("« $lib » : boîte de l'écriture = 20 mm de haut", 20.0, $g['code_hauteur']);
    verifie("« $lib » : écart QR ↔ écriture = 0,8 mm", 0.8, $g['gap']);
    $m = texte_dessine($lib, $g['code']);
    verifie("« $lib » : l'écriture dessinée tient dans 80 mm (" . round($m['l'], 1) . ')', true, $m['l'] <= 80.05);
    verifie("« $lib » : l'écriture dessinée tient dans 20 mm (" . round($m['h'], 1) . ')', true, $m['h'] <= 20.05);
    verifie("« $lib » : l'écriture REMPLIT sa boîte (une des deux cotes atteinte)", true,
        $m['l'] >= 79.9 || $m['h'] >= 19.9);
}

echo "— tout tient dans l'étiquette —\n";
$g = etiquette_geometrie_barre(format_150x60($cotes), 'AR1-01');
verifie('QR + écart + écriture ≤ largeur utile', true,
    $g['qr'] + $g['gap'] + $g['code_largeur'] <= 150 - 2 * $g['pad'] + 0.001);
verifie("la boîte de l'écriture ne dépasse pas la hauteur utile", true,
    $g['code_hauteur'] <= 60 - 2 * $g['pad'] + 0.001);

echo "— une cote impossible est ramenée dans l'étiquette —\n";
$g = etiquette_geometrie_barre(format_150x60(['qr_mm' => 300, 'ecart' => 0.8]), 'AR1-01');
verifie('QR demandé à 300 mm → ramené à la hauteur utile (51)', 51.0, $g['qr']);

echo "— SANS cote en mm, rien ne change (le calcul d'avant, au millimètre) —\n";
$g = etiquette_geometrie_barre(format_150x60(null), 'AR1-01');
verifie('QR automatique = 26 mm', 26.0, $g['qr']);
verifie('marge automatique = 4,5 mm', 4.5, $g['pad']);
verifie("écart automatique = la marge", 4.5, $g['gap']);
verifie("largeur de l'écriture = tout ce qui reste", 110.5, $g['code_largeur']);
verifie('police automatique = 29,7 mm', 29.7, $g['code'], 0.02);
verifie("pas de hauteur imposée à l'écriture", null, $g['code_hauteur']);

$g = etiquette_geometrie_barre(format_150x60(['qr_echelle' => 150]), 'AR1-01');
verifie('les pourcentages agissent toujours : QR 150 % = 39 mm', 39.0, $g['qr']);
$g = etiquette_geometrie_barre(format_150x60(['code_echelle' => 80]), 'AR1-01');
verifie('les pourcentages agissent toujours : police 80 % = 23,76 mm', 23.76, $g['code'], 0.02);

echo "— la normalisation des cotes —\n";
$n = etiquette_disposition_barre_normaliser(['qr_mm' => '', 'texte_l_mm' => null, 'texte_h_mm' => 'abc']);
verifie('vide → automatique (qr_mm)', null, $n['qr_mm']);
verifie('vide → automatique (texte_l_mm)', null, $n['texte_l_mm']);
verifie('texte → automatique (texte_h_mm)', null, $n['texte_h_mm']);
$n = etiquette_disposition_barre_normaliser(['qr_mm' => 43, 'texte_l_mm' => 80, 'texte_h_mm' => 20, 'ecart' => 0.8]);
verifie('43 mm relu tel quel', 43.0, $n['qr_mm']);
verifie('80 mm relu tel quel', 80.0, $n['texte_l_mm']);
verifie('20 mm relu tel quel', 20.0, $n['texte_h_mm']);
verifie('0,8 mm relu tel quel', 0.8, $n['ecart']);
$n = etiquette_disposition_barre_normaliser(['qr_mm' => 5000, 'texte_h_mm' => 0.1]);
verifie('une valeur démesurée est bornée (qr_mm ≤ 200)', 200.0, $n['qr_mm']);
verifie('une valeur minuscule est bornée (texte_h_mm ≥ 2)', 2.0, $n['texte_h_mm']);

echo "— le panneau et le PDF parlent la même langue —\n";
verifie('les cotes voyagent dans l\'URL du PDF', true,
    etiquette_disposition_barre_dans_requete(['qr_mm' => '43']));

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
