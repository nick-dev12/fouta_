<?php
/**
 * LES COTES DE L'ÉTIQUETTE DE BARRE 150 × 60 (07/09/2026) — décision de la
 * direction, au millimètre :
 *   • côté du code QR ............ 43 mm
 *   • écriture ................... 80 mm de long × 20 mm de haut
 *   • écart entre le QR et l'écriture 0,8 mm
 *
 * Ces trois cotes ne s'exprimaient pas jusqu'ici : la disposition ne connaissait
 * que des pourcentages (QR plafonné à 26 mm avant échelle, écriture sans hauteur
 * réglable). Le format porte désormais les millimètres eux-mêmes
 * (qr_mm / texte_l_mm / texte_h_mm), lus à l'identique par l'écran et le PDF.
 *
 * SEMIS, PAS ÉCRASEMENT : on n'écrit que si le format n'a pas déjà ces trois
 * cotes. Un réglage fait à l'écran après coup n'est donc jamais rejeté par un
 * futur déploiement. Utiliser --forcer pour reposer les valeurs de la direction.
 *
 * À jouer :  php migrations/run_cotes_barre_150x60.php [--forcer]
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_etiquettes_fpl.php';

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$forcer = in_array('--forcer', $argv ?? [], true);

$COTES = ['qr_mm' => 43.0, 'texte_l_mm' => 80.0, 'texte_h_mm' => 20.0, 'ecart' => 0.8];

$st = $db->prepare("SELECT id, nom, largeur_mm, hauteur_mm, disposition_barre
                      FROM etiquette_formats
                     WHERE type = 'barre' AND sync_deleted_at IS NULL
                       AND ABS(largeur_mm - 150) < 0.01 AND ABS(hauteur_mm - 60) < 0.01");
$st->execute();
$formats = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($formats === []) {
    echo "Aucun format de barre 150 × 60 : rien à régler.\n";
    exit(0);
}

foreach ($formats as $f) {
    $actuel = !empty($f['disposition_barre']) ? json_decode((string) $f['disposition_barre'], true) : [];
    if (!is_array($actuel)) {
        $actuel = [];
    }
    $deja = true;
    foreach ($COTES as $clef => $valeur) {
        if (!isset($actuel[$clef]) || abs((float) $actuel[$clef] - $valeur) > 0.001) {
            $deja = false;
        }
    }
    if ($deja) {
        echo 'Format #', $f['id'], ' « ', $f['nom'], " » : cotes déjà en place.\n";
        continue;
    }
    if (!empty($actuel) && !$forcer && (isset($actuel['qr_mm']) || isset($actuel['texte_l_mm']) || isset($actuel['texte_h_mm']))) {
        echo 'Format #', $f['id'], ' « ', $f['nom'],
             " » : des cotes en mm sont déjà réglées à l'écran — laissées telles quelles (--forcer pour reposer celles de la direction).\n";
        continue;
    }

    $disposition = etiquette_disposition_barre_normaliser(array_merge($actuel, $COTES));
    etiquette_maj_disposition_barre((int) $f['id'], $disposition);
    echo 'Format #', $f['id'], ' « ', $f['nom'], ' » : QR 43 mm, écriture 80 × 20 mm, écart 0,8 mm — posé.', "\n";

    /* La preuve, tout de suite : la géométrie que liront l'écran et le PDF. */
    $f['disposition_barre'] = json_encode($disposition, JSON_UNESCAPED_UNICODE);
    $g = etiquette_geometrie_barre($f, 'AR1-01');
    printf("   vérification (libellé AR1-01) : QR %.2f mm · boîte %.2f × %s mm · écart %.2f mm · police %.2f mm\n",
        $g['qr'], $g['code_largeur'], var_export($g['code_hauteur'], true), $g['gap'], $g['code']);
}
