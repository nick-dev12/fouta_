<?php
/**
 * MIGRATION — le niveau « Box » (04/09/2026).
 *
 * Une box est un contenant-feuille, ALTERNATIVE à la barre : sous l'étagère,
 * elle porte sa propre étiquette QR jaune (comme une barre) mais son libellé
 * la distingue (C15A-BOX-01 vs C15A-01). Depuis ce jour un entrepôt peut avoir
 * PLUSIEURS niveaux à QR (barre ET box) — l'exclusivité a été levée dans le
 * code (entrepot_hierarchie_def_clear_autres_etiquette est un no-op).
 *
 * Idempotente : si le niveau 'box' existe déjà, on ne fait que garantir ses
 * réglages (QR actif, lié au rayon, ordre juste après la barre).
 *
 * Usage : php migrations/run_niveau_box.php
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!entrepot_hierarchie_etiquette_ensure_schema()) {
    fwrite(STDERR, "Schema hierarchie/etiquette indisponible.\n");
    exit(1);
}

// Le niveau « Barre » sert de modèle (son lien = le rayon, son ordre).
$barre = null;
foreach (entrepot_hierarchie_def_list(false) as $d) {
    if (strtolower((string) ($d['slug'] ?? '')) === 'barre') {
        $barre = $d;
        break;
    }
}
$rayon_id = $barre ? (int) ($barre['etiquette_lie_niveau_id'] ?? 0) : 0;
$ordre_barre = $barre ? (int) ($barre['ordre'] ?? 60) : 60;

// Box existe déjà ?
$box = null;
foreach (entrepot_hierarchie_def_list(false) as $d) {
    if (strtolower((string) ($d['slug'] ?? '')) === 'box') {
        $box = $d;
        break;
    }
}

if ($box === null) {
    $res = entrepot_hierarchie_def_ajouter('Box', 'fa-box', 1, $rayon_id > 0 ? 'niveau' : 'etage', $rayon_id > 0 ? $rayon_id : null);
    if (empty($res['ok'])) {
        fwrite(STDERR, 'Creation du niveau Box refusee : ' . ($res['message'] ?? '') . "\n");
        exit(1);
    }
    echo "Niveau 'Box' cree.\n";
} else {
    echo "Niveau 'Box' deja present (id " . (int) $box['id'] . ").\n";
}

// Relire le box et garantir : QR actif, lie au rayon, ordre juste apres la barre.
$box = null;
foreach (entrepot_hierarchie_def_list(false) as $d) {
    if (strtolower((string) ($d['slug'] ?? '')) === 'box') {
        $box = $d;
        break;
    }
}
if ($box === null) {
    fwrite(STDERR, "Box introuvable apres creation.\n");
    exit(1);
}
$box_id = (int) $box['id'];

// Ordre : la box se range juste apres la barre dans la chaine des niveaux.
$ordre_box = $ordre_barre + 2;
$db->prepare('UPDATE entrepot_hierarchie_niveau SET ordre = :o, actif = 1, est_etiquette_qr = 1,
              etiquette_lie_type = :lt, etiquette_lie_niveau_id = :ln WHERE id = :id')
   ->execute([
       ':o' => $ordre_box,
       ':lt' => $rayon_id > 0 ? 'niveau' : 'etage',
       ':ln' => $rayon_id > 0 ? $rayon_id : null,
       ':id' => $box_id,
   ]);

// Preuve : la barre garde SON QR (l'exclusivite est bien levee).
$qr = entrepot_hierarchie_defs_etiquette();
$noms = [];
foreach ($qr as $d) {
    $noms[] = $d['slug'] . '(#' . $d['id'] . ')';
}
echo "Niveaux a QR : " . implode(', ', $noms) . "\n";
echo (count($qr) >= 2 ? "OK : barre ET box portent le QR.\n" : "ATTENTION : un seul niveau QR — l'exclusivite n'a pas ete levee ?\n");
echo "Termine.\n";
