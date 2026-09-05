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
 * le PROUVE en comparant les libellés de barre avant / après — et ANNULE tout
 * (rollback) si un seul libellé de barre bougeait.
 *
 * Ce que fait la migration, de façon idempotente, transactionnelle et
 * auto-réparante :
 *   1. garantit l'existence du niveau « box » ;
 *   2. le règle en contenant SANS QR (est_etiquette_qr = 0), juste APRÈS la
 *      barre dans l'ordre des niveaux (donc enfant direct de la barre) ;
 *   3. nettoie UNIQUEMENT les niveaux parasites NOMMÉMENT connus
 *      ($parasites_slugs = position/osition…) — jamais un niveau métier créé
 *      par la direction : un nœud parasite-FEUILLE déjà posé sous une barre est
 *      converti en box (les pièces restent sous leur barre) ; ce qui n'est pas
 *      une feuille sous une barre est SIGNALÉ (jamais détruit) ; un niveau vidé
 *      est désactivé. Tout niveau hors modèle NON listé comme parasite est
 *      seulement SIGNALÉ, jamais touché ;
 *   4. signale toute box qui ne serait pas enfant d'une barre ;
 *   5. PROUVE (avant commit) que les étiquettes de barre sont INCHANGÉES.
 *
 * Codes de sortie : 0 = propre ; 1 = échec dur (barres changées / box encore à
 * QR / rollback) ; 2 = fait mais ACTION REQUISE (des nœuds restent à trancher).
 *
 * Usage : php migrations/run_niveau_box.php
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Les seuls slugs traités comme parasites (nettoyage). Tout autre niveau hors
// modèle est un niveau MÉTIER potentiel : on ne le touche pas, on le signale.
$parasites_slugs = ['position', 'positions', 'osition', 'ositions'];
$modele = ['etage', 'zone', 'rayon', 'etagere', 'barre', 'box'];

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
/** Une colonne existe-t-elle sur une table ? */
$col_existe = function (string $table, string $col) use ($db): bool {
    try {
        return (bool) $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetch();
    } catch (Throwable $e) {
        return false;
    }
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

// --- 1. garantir le niveau box (hors transaction : def_ajouter peut faire du DDL) --
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

// Tables synchronisées (foutasvr → VPS) : toute ligne modifiée doit bouger son
// sync_updated_at, sinon le différentiel ne la pousse pas. Détecté par table.
$bump_niv = $col_existe('entrepot_hierarchie_niveau', 'sync_updated_at') ? ', sync_updated_at = NOW()' : '';
$bump_noe = $col_existe('entrepot_hierarchie_noeud', 'sync_updated_at') ? ', sync_updated_at = NOW()' : '';

// ============================================================================
// SECTIONS 2 à 4 + preuve 5a : TOUT-OU-RIEN dans une transaction. Un échec ou
// une barre qui bougerait ⇒ rollback complet, rien n'est poussé au VPS.
// ============================================================================
$convertis = 0;
$conversions_log = [];
$desactives = 0;
$signales = [];
$box_orphelines = [];
$change = 0;

$db->beginTransaction();
try {
    // --- 2. box = contenant SANS QR, juste après la barre --------------------
    $ordre_box = $ordre_barre + 1;
    $cur = $db->query(
        'SELECT est_etiquette_qr, etiquette_lie_niveau_id, actif, ordre, etiquette_lie_type
           FROM entrepot_hierarchie_niveau WHERE id = ' . $box_id . ' LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    $besoin_maj = ((int) $cur['est_etiquette_qr'] !== 0)
        || ((int) $cur['actif'] !== 1)
        || ((int) $cur['ordre'] !== $ordre_box)
        || ($cur['etiquette_lie_niveau_id'] !== null)
        || ((string) $cur['etiquette_lie_type'] !== 'etage');
    if ($besoin_maj) {
        $db->prepare(
            'UPDATE entrepot_hierarchie_niveau
                SET actif = 1, est_etiquette_qr = 0, etiquette_lie_type = \'etage\',
                    etiquette_lie_niveau_id = NULL, ordre = :o' . $bump_niv . '
              WHERE id = :id'
        )->execute([':o' => $ordre_box, ':id' => $box_id]);
        echo "Box réglée : SANS QR, ordre $ordre_box (juste après la barre).\n";
    } else {
        echo "Box déjà conforme (sans QR, ordre $ordre_box) — aucune écriture.\n";
    }

    // --- 3. nettoyage des niveaux parasites NOMMÉMENT connus -----------------
    $slug_etage = function_exists('entrepot_hierarchie_def_slug_etage')
        ? entrepot_hierarchie_def_slug_etage() : 'etage';
    $tous = $db->query('SELECT id, slug, label, actif FROM entrepot_hierarchie_niveau ORDER BY ordre, id')
               ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tous as $d) {
        $slug = (string) $d['slug'];
        if (in_array($slug, $modele, true) || $slug === $slug_etage) {
            continue; // niveau du modèle propre : on n'y touche pas
        }
        if ((int) $d['actif'] !== 1) {
            continue; // déjà inactif
        }
        $nid = (int) $d['id'];
        if (!in_array($slug, $parasites_slugs, true)) {
            // Niveau hors modèle NON listé comme parasite = niveau métier possible.
            // On ne le touche JAMAIS ; on le signale pour information.
            $nb = (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $nid . ' AND sync_deleted_at IS NULL')->fetchColumn();
            $signales[] = "niveau hors modèle CONSERVÉ (non listé comme parasite) : « {$d['label']} » (slug $slug, $nb nœud[s]) — vérifier si voulu";
            continue;
        }
        // Parasite connu : convertir les nœuds FEUILLES posés sous une barre.
        $noeuds = $db->query(
            'SELECT id, parent_id, numero, nom FROM entrepot_hierarchie_noeud
              WHERE niveau_id = ' . $nid . ' AND sync_deleted_at IS NULL ORDER BY numero, id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $orphelins = [];
        foreach ($noeuds as $n) {
            $node_id = (int) $n['id'];
            $pid = (int) ($n['parent_id'] ?? 0);
            $parent = $pid > 0
                ? $db->query('SELECT niveau_id, etage_id FROM entrepot_hierarchie_noeud WHERE id = ' . $pid . ' AND sync_deleted_at IS NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC)
                : null;
            $a_enfants = (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_noeud WHERE parent_id = ' . $node_id . ' AND sync_deleted_at IS NULL')->fetchColumn() > 0;
            $parent_est_barre = $parent && (int) $parent['niveau_id'] === $barre_id;
            if ($parent_est_barre && !$a_enfants) {
                // Devient une box FEUILLE sous cette barre (numérotée à la suite).
                $newnum = 1 + (int) $db->query(
                    'SELECT COALESCE(MAX(numero), 0) FROM entrepot_hierarchie_noeud
                      WHERE niveau_id = ' . $box_id . ' AND parent_id = ' . $pid . ' AND sync_deleted_at IS NULL'
                )->fetchColumn();
                // Nom : on garde un nom PARLANT ; on ne remplace par « Box N » que
                // s'il est vide ou purement numérique (ancien numéro de position).
                $ancien = trim((string) ($n['nom'] ?? ''));
                $generique = ($ancien === '' || preg_match('/^0*\d+$/', $ancien) === 1);
                $nouveau = $generique ? ('Box ' . $newnum) : $ancien;
                $etage_barre = (int) ($parent['etage_id'] ?? 0);
                $db->prepare(
                    'UPDATE entrepot_hierarchie_noeud
                        SET niveau_id = :box, numero = :num, nom = :nom, etage_id = :et,
                            date_modification = NOW()' . $bump_noe . '
                      WHERE id = :id'
                )->execute([':box' => $box_id, ':num' => $newnum, ':nom' => $nouveau, ':et' => $etage_barre, ':id' => $node_id]);
                $convertis++;
                if (count($conversions_log) < 8) {
                    $conversions_log[] = "#$node_id [$ancien] → box « $nouveau » sous barre #$pid";
                }
            } else {
                $orphelins[] = $node_id . ($a_enfants ? '(a des enfants)' : '(hors barre)');
            }
        }
        $reste = (int) $db->query(
            'SELECT COUNT(*) FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $nid . ' AND sync_deleted_at IS NULL'
        )->fetchColumn();
        if ($reste === 0) {
            $db->prepare('UPDATE entrepot_hierarchie_niveau SET actif = 0' . $bump_niv . ' WHERE id = :id')->execute([':id' => $nid]);
            $desactives++;
            echo "  Parasite « {$d['label']} » (slug $slug) → " . count($noeuds) . " nœud(s) traité(s), désactivé.\n";
        } else {
            $signales[] = "parasite « {$d['label']} » (slug $slug) : $reste nœud(s) non convertis (ids " . implode(',', $orphelins) . ')';
        }
    }
    if ($conversions_log !== []) {
        echo "  Conversions (échantillon) :\n";
        foreach ($conversions_log as $c) {
            echo "    · $c\n";
        }
    }
    echo "Nettoyage : $convertis nœud(s) converti(s) en box, $desactives niveau(x) parasite(s) désactivé(s), "
        . count($signales) . " signalement(s).\n";

    // --- 4. box qui ne seraient pas enfant d'une barre -----------------------
    $box_noeuds = $db->query(
        'SELECT id, parent_id FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . $box_id . ' AND sync_deleted_at IS NULL'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($box_noeuds as $bn) {
        $pid = (int) ($bn['parent_id'] ?? 0);
        $pn = $pid > 0
            ? $db->query('SELECT niveau_id FROM entrepot_hierarchie_noeud WHERE id = ' . $pid . ' AND sync_deleted_at IS NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC)
            : null;
        if (!($pn && (int) $pn['niveau_id'] === $barre_id)) {
            $box_orphelines[] = (int) $bn['id'];
        }
    }
    echo 'Box existantes : ' . count($box_noeuds) . ' — dont ' . count($box_orphelines) . " non enfant d'une barre.\n";

    // --- 5a. PREUVE (avant commit) : les libellés de barre n'ont pas bougé ----
    foreach ($libelles_avant as $bid => $lib) {
        if (entrepot_noeud_etiquette_libelle((int) $bid) !== $lib) {
            if ($change < 5) {
                echo "  CHANGÉ #$bid : [$lib] → [" . entrepot_noeud_etiquette_libelle((int) $bid) . "]\n";
            }
            $change++;
        }
    }
    if ($change !== 0) {
        $db->rollBack();
        fwrite(STDERR, "ÉCHEC : $change étiquette(s) de barre changée(s) → ROLLBACK, rien n'est écrit.\n");
        exit(1);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'ÉCHEC (rollback) : ' . $e->getMessage() . "\n");
    exit(1);
}

echo 'PREUVE ✓ Étiquettes de barre INCHANGÉES (' . count($libelles_avant) . ") — les QR imprimés restent valides.\n";

// --- 5b. les niveaux à QR = seulement la barre (après commit) -----------------
$qr = entrepot_hierarchie_defs_etiquette();
$noms = [];
$box_a_qr = false;
foreach ($qr as $d) {
    $noms[] = $d['slug'] . '(#' . $d['id'] . ')';
    if ((string) $d['slug'] === 'box') {
        $box_a_qr = true;
    }
}
echo 'Niveaux à QR : ' . (implode(', ', $noms) ?: '(aucun)') . "\n";
echo $box_a_qr
    ? "ÉCHEC : la box porte encore un QR !\n"
    : "PREUVE ✓ La box NE porte PAS de QR (seule la barre en a un).\n";

// --- 6. STATUT FINAL + code de sortie ----------------------------------------
$action_requise = ($signales !== []) || ($box_orphelines !== []);
if ($action_requise) {
    echo "\n================= ACTION REQUISE =================\n";
    foreach ($signales as $s) {
        echo "  - $s\n";
    }
    if ($box_orphelines !== []) {
        echo '  - box non rattachées à une barre (ids : ' . implode(',', $box_orphelines) . ") — à rattacher.\n";
    }
    echo "=================================================\n";
}

if ($box_a_qr) {
    echo "Terminé AVEC ANOMALIE : la box porte encore un QR.\n";
    exit(1);
}
if ($action_requise) {
    echo "Terminé — box réglée + preuves OK, MAIS des nœuds restent à trancher (voir ACTION REQUISE).\n";
    exit(2);
}
echo "Terminé — modèle propre en place.\n";
exit(0);
