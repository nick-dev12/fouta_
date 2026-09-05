<?php
/**
 * MIGRATION — le niveau « Box », modèle PROPRE (révisé le 05/09/2026).
 *
 * DÉCISION DIRECTION (05/09/2026) : SEULES LES BARRES portent un code QR.
 * La box N'A PAS de QR. Une box est un simple contenant NUMÉROTÉ, ENFANT
 * d'une barre : une barre peut recevoir des pièces DIRECTEMENT, ou bien des
 * box (Box 1, Box 2…) empilées/rangées, chacune contenant des pièces.
 *
 *   Étage › Zone › Rayon › Étagère › Barre (QR, inchangé) › Box (sans QR)
 *
 * Le graphique du QR de barre NE CHANGE PAS (déjà imprimé) : cette migration
 * le PROUVE en comparant les libellés de barre avant / après.
 *
 * Ce que fait la migration, de façon idempotente et auto-réparante :
 *   1. garantit l'existence du niveau « box » ;
 *   2. le règle en contenant SANS QR (est_etiquette_qr = 0), juste APRÈS la
 *      barre dans l'ordre des niveaux (donc enfant direct de la barre) ;
 *   3. nettoie les niveaux parasites qui n'appartiennent pas au modèle propre
 *      (« position », doublons de slug…) : ceux qui sont VIDES sont désactivés,
 *      ceux qui contiennent encore des nœuds/pièces sont SIGNALÉS (jamais
 *      détruits — la direction tranche) ;
 *   4. re-rattache toute box existante à sa barre si besoin (signale l'ambigu) ;
 *   5. prouve que les étiquettes de barre sont INCHANGÉES.
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

/** Petit utilitaire : le def d'un slug (ou null). */
$def_par_slug = function (string $slug) use ($db): ?array {
    $st = $db->prepare('SELECT * FROM entrepot_hierarchie_niveau WHERE slug = :s LIMIT 1');
    $st->execute([':s' => $slug]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
};

// --- 0. baseline : libellés de barre AVANT (preuve d'invariance) --------------
$barre = $def_par_slug('barre');
if ($barre === null) {
    fwrite(STDERR, "Niveau 'barre' introuvable : entrepot non configure.\n");
    exit(1);
}
$barre_id = (int) $barre['id'];
$ordre_barre = (int) ($barre['ordre'] ?? 60);

$barres_noeuds = $db->query(
    'SELECT id FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $barre_id . ' AND sync_deleted_at IS NULL ORDER BY id'
)->fetchAll(PDO::FETCH_COLUMN);
$libelles_avant = [];
foreach ($barres_noeuds as $bid) {
    $libelles_avant[(int) $bid] = entrepot_noeud_etiquette_libelle((int) $bid);
}
echo 'Baseline : ' . count($libelles_avant) . " libellé(s) de barre capturé(s).\n";

// --- 1. garantir le niveau box ------------------------------------------------
$box = $def_par_slug('box');
if ($box === null) {
    // Créé SANS QR (3e argument = 0), non lié à un niveau d'étiquette.
    $res = entrepot_hierarchie_def_ajouter('Box', 'fa-box', 0, 'etage', null);
    if (empty($res['success'])) {
        fwrite(STDERR, 'Creation du niveau Box refusee : ' . ($res['message'] ?? '') . "\n");
        exit(1);
    }
    echo "Niveau 'Box' créé (sans QR).\n";
    $box = $def_par_slug('box');
}
if ($box === null) {
    fwrite(STDERR, "Box introuvable apres creation.\n");
    exit(1);
}
$box_id = (int) $box['id'];

// --- 2. box = contenant SANS QR, juste après la barre -------------------------
$ordre_box = $ordre_barre + 1;
$db->prepare(
    // etiquette_lie_type est NOT NULL (défaut 'etage') : inerte quand
    // est_etiquette_qr = 0, on le remet simplement à sa valeur par défaut.
    'UPDATE entrepot_hierarchie_niveau
        SET actif = 1,
            est_etiquette_qr = 0,
            etiquette_lie_type = \'etage\',
            etiquette_lie_niveau_id = NULL,
            ordre = :o
      WHERE id = :id'
)->execute([':o' => $ordre_box, ':id' => $box_id]);
echo "Box réglée : SANS QR, ordre $ordre_box (juste après la barre).\n";

// --- 3. nettoyage des niveaux parasites (hors modèle propre) ------------------
// Le modèle propre n'a PAS de « position ». Un contenant hors-modèle dont le
// parent est DÉJÀ une barre EST, sémantiquement, une box : on le convertit
// (les pièces qu'il porte restent sous la même barre, via une box). Ce qui
// n'est pas rattaché à une barre est SIGNALÉ (jamais détruit). Un niveau vidé
// de tous ses nœuds est désactivé.
$modele = ['etage', 'zone', 'rayon', 'etagere', 'barre', 'box'];
$tous = $db->query('SELECT id, slug, label, actif FROM entrepot_hierarchie_niveau ORDER BY ordre, id')
           ->fetchAll(PDO::FETCH_ASSOC);
$slug_etage = function_exists('entrepot_hierarchie_def_slug_etage')
    ? entrepot_hierarchie_def_slug_etage() : 'etage';
$desactives = 0;
$convertis = 0;
$signales = [];
foreach ($tous as $d) {
    $slug = (string) $d['slug'];
    if (in_array($slug, $modele, true) || $slug === $slug_etage) {
        continue; // niveau du modèle propre : on n'y touche pas
    }
    if ((int) $d['actif'] !== 1) {
        continue; // déjà inactif
    }
    $nid = (int) $d['id'];
    $noeuds = $db->query(
        'SELECT id, parent_id FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $nid . ' AND sync_deleted_at IS NULL ORDER BY numero, id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $orphelins = [];
    foreach ($noeuds as $n) {
        $pid = (int) ($n['parent_id'] ?? 0);
        $parent = $pid > 0
            ? $db->query('SELECT niveau_id FROM entrepot_hierarchie_noeud WHERE id = ' . $pid . ' AND sync_deleted_at IS NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC)
            : null;
        if ($parent && (int) $parent['niveau_id'] === $barre_id) {
            // Devient une box sous cette barre (numérotée après les box existantes).
            $newnum = 1 + (int) $db->query(
                'SELECT COALESCE(MAX(numero), 0) FROM entrepot_hierarchie_noeud
                  WHERE niveau_id = ' . $box_id . ' AND parent_id = ' . $pid . ' AND sync_deleted_at IS NULL'
            )->fetchColumn();
            $db->prepare(
                'UPDATE entrepot_hierarchie_noeud
                    SET niveau_id = :box, numero = :num, nom = :nom,
                        date_modification = NOW(), sync_updated_at = NOW()
                  WHERE id = :id'
            )->execute([':box' => $box_id, ':num' => $newnum, ':nom' => 'Box ' . $newnum, ':id' => (int) $n['id']]);
            $convertis++;
        } else {
            $orphelins[] = (int) $n['id'];
        }
    }
    $reste = (int) $db->query(
        'SELECT COUNT(*) FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $nid . ' AND sync_deleted_at IS NULL'
    )->fetchColumn();
    if ($reste === 0) {
        $db->prepare('UPDATE entrepot_hierarchie_niveau SET actif = 0 WHERE id = :id')->execute([':id' => $nid]);
        $desactives++;
        echo "  Niveau parasite « {$d['label']} » (slug $slug) → " . count($noeuds)
            . " nœud(s) traité(s), désactivé.\n";
    } else {
        $signales[] = "« {$d['label']} » (slug $slug) : $reste nœud(s) hors barre (ids "
            . implode(',', $orphelins) . ')';
    }
}
if ($signales !== []) {
    echo "  ATTENTION — nœuds parasites NON rattachés à une barre (conservés, à trancher) :\n";
    foreach ($signales as $s) {
        echo "    - $s\n";
    }
}
echo "Nettoyage : $convertis nœud(s) converti(s) en box, $desactives niveau(x) parasite(s) désactivé(s), "
    . count($signales) . " signalé(s).\n";

// --- 4. re-rattacher les box existantes à une barre --------------------------
$box_noeuds = $db->query(
    'SELECT id, parent_id FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $box_id . ' AND sync_deleted_at IS NULL'
)->fetchAll(PDO::FETCH_ASSOC);
$box_orphelines = [];
foreach ($box_noeuds as $bn) {
    $pid = (int) ($bn['parent_id'] ?? 0);
    $ok_parent = false;
    if ($pid > 0) {
        $pn = $db->query('SELECT niveau_id FROM entrepot_hierarchie_noeud WHERE id = ' . $pid . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $ok_parent = $pn && (int) $pn['niveau_id'] === $barre_id;
    }
    if (!$ok_parent) {
        $box_orphelines[] = (int) $bn['id'];
    }
}
if ($box_orphelines !== []) {
    echo '  ATTENTION — ' . count($box_orphelines) . " box existante(s) ne sont pas enfant d'une barre "
        . '(ids : ' . implode(',', $box_orphelines) . "). À rattacher manuellement à leur barre.\n";
} else {
    echo 'Box existantes : ' . count($box_noeuds) . " (toutes enfant d'une barre, ou aucune).\n";
}

// --- 5. PREUVES ---------------------------------------------------------------
// 5a. les étiquettes de barre n'ont pas bougé
$change = 0;
foreach ($libelles_avant as $bid => $lib) {
    $ap = entrepot_noeud_etiquette_libelle((int) $bid);
    if ($ap !== $lib) {
        if ($change < 5) {
            echo "  CHANGÉ #$bid : [$lib] → [$ap]\n";
        }
        $change++;
    }
}
echo $change === 0
    ? 'PREUVE ✓ Étiquettes de barre INCHANGÉES (' . count($libelles_avant) . ") — les QR imprimés restent valides.\n"
    : "ÉCHEC : $change étiquette(s) de barre ont changé !\n";

// 5b. les niveaux à QR = seulement la barre
$qr = entrepot_hierarchie_defs_etiquette();
$noms = [];
foreach ($qr as $d) {
    $noms[] = $d['slug'] . '(#' . $d['id'] . ')';
}
echo 'Niveaux à QR : ' . (implode(', ', $noms) ?: '(aucun)') . "\n";
$box_a_qr = false;
foreach ($qr as $d) {
    if ((string) $d['slug'] === 'box') {
        $box_a_qr = true;
    }
}
echo $box_a_qr
    ? "ÉCHEC : la box porte encore un QR !\n"
    : "PREUVE ✓ La box NE porte PAS de QR (seule la barre en a un).\n";

echo ($change === 0 && !$box_a_qr) ? "Terminé — modèle propre en place.\n" : "Terminé AVEC ANOMALIE (voir ci-dessus).\n";
