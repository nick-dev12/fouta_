<?php
/**
 * RENUMÉROTER LES BARRES D'APRÈS LEUR NOM, RAYON PAR RAYON (09/09/2026).
 *
 * CE QUE LA MESURE A MONTRÉ sur le serveur de l'entreprise : dans 25 rayons,
 * l'équipe a NOMMÉ les barres à la suite d'une étagère à l'autre (rayon 3A :
 * E1 = B1…B7, E2 = B8…B13, … E7 = B56…B66) — c'est la règle de la direction —
 * mais le NUMÉRO, lui, repartait de 1 à chaque étagère. Or le libellé de
 * l'étiquette n'affiche QUE le numéro : sept barres du rayon 3A portent donc
 * « C3A-01 », et la barre nommée B14 affiche « 01 ». C'est le « B5 qui
 * affiche 01 » constaté par la direction.
 *
 * CE QUE FAIT LA MIGRATION : pour chaque rayon, si TOUTES ses barres portent un
 * nom « préfixe + nombre » (B14), avec un seul préfixe et des nombres tous
 * différents, le numéro de chaque barre devient le nombre de son nom. Le nom
 * et l'étiquette redisent alors la même chose, et les libellés redeviennent
 * uniques dans le rayon. Un rayon qui ne remplit pas ces conditions (deux
 * préfixes, un nom sans nombre, deux barres de même nom) est LAISSÉ TEL QUEL et
 * nommé dans le compte rendu : la direction tranchera à la main.
 *
 * Les étiquettes déjà imprimées de ces barres CHANGENT (elles étaient fausses :
 * plusieurs barres partageaient le même libellé) — la direction l'a accepté le
 * 08/09 : « même s'il y a des erreurs, on réimprime après ». Le compte rendu
 * liste chaque barre dont le libellé change, pour la réimpression.
 *
 * SANS OPTION, RIEN N'EST ÉCRIT : c'est une répétition. Avec --appliquer, les
 * numéros sont écrits dans UNE transaction (tout ou rien), après une sauvegarde
 * CSV de (id, nom, numéro, libellé) dans backups/.
 *
 *   php migrations/run_renumeroter_barres_par_rayon.php             (répétition)
 *   php migrations/run_renumeroter_barres_par_rayon.php --appliquer (écrit)
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_entrepot_hierarchie_libre.php';
require_once __DIR__ . '/../includes/entrepot_nommage.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$appliquer = in_array('--appliquer', $argv ?? [], true);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), $appliquer ? "  —  ÉCRITURE\n" : "  —  RÉPÉTITION (rien n'est écrit)\n";

$def_barre = null;
foreach (entrepot_hierarchie_defs_etiquette() as $d) {
    if (strtolower((string) ($d['slug'] ?? '')) === 'barre') {
        $def_barre = $d;
    }
}
if ($def_barre === null || (string) ($def_barre['etiquette_lie_type'] ?? '') !== 'niveau') {
    echo "Aucun niveau « barre » lié à un rayon : rien à faire.\n";
    exit(0);
}
$nid = (int) $def_barre['id'];
$nid_rayon = (int) $def_barre['etiquette_lie_niveau_id'];

/* toutes les barres, groupées par rayon (l'ancêtre au niveau lié) */
$par_rayon = [];
foreach ($db->query("SELECT id, nom, numero, parent_id, etage_id FROM entrepot_hierarchie_noeud WHERE niveau_id = $nid ORDER BY id") as $b) {
    $rayon = entrepot_noeud_ancetre_de_niveau((int) $b['parent_id'], $nid_rayon);
    $cle = $rayon ? (int) $rayon['id'] : 0;
    $par_rayon[$cle]['rayon'] = $rayon;
    $par_rayon[$cle]['barres'][] = $b;
}

$a_changer = [];   // [id, nom, ancien numero, nouveau numero, ancien libellé]
$laisses = [];     // rayons non traités, avec la raison
$deja_bons = 0;
foreach ($par_rayon as $cle => $g) {
    $nom_rayon = $g['rayon']['nom'] ?? '(sans rayon)';
    if ($cle === 0) {
        $laisses[] = "$nom_rayon : " . count($g['barres']) . ' barre(s) sans rayon au-dessus';
        continue;
    }
    $prefixes = [];
    $nombres = [];
    $probleme = null;
    foreach ($g['barres'] as $b) {
        $d = entrepot_nom_decomposer($b['nom']);
        if ($d === null) {
            $probleme = 'la barre « ' . $b['nom'] . ' » n\'a pas de nombre dans son nom';
            break;
        }
        if ($d['numero'] < 1) {
            /* « B0 » (rayon 22B, 09/09) : un numéro 0 s'affiche « 01 » sur
               l'étiquette et recoupe B1 — on ne le pose jamais */
            $probleme = "la barre « " . $b['nom'] . " » porte le nombre 0, qui n'est pas un numéro";
            break;
        }
        $prefixes[mb_strtolower(trim($d['prefixe']))] = true;
        if (isset($nombres[$d['numero']])) {
            $probleme = 'deux barres portent le nombre ' . $d['numero'] . ' (« ' . $nombres[$d['numero']] . ' » et « ' . $b['nom'] . ' »)';
            break;
        }
        $nombres[$d['numero']] = $b['nom'];
    }
    if ($probleme === null && count($prefixes) > 1) {
        $probleme = 'plusieurs préfixes de nom (' . implode(', ', array_keys($prefixes)) . ')';
    }
    if ($probleme !== null) {
        $laisses[] = "$nom_rayon : $probleme";
        continue;
    }
    foreach ($g['barres'] as $b) {
        $d = entrepot_nom_decomposer($b['nom']);
        if ((int) $b['numero'] === $d['numero']) {
            $deja_bons++;
            continue;
        }
        $a_changer[] = [
            'id' => (int) $b['id'], 'rayon' => $nom_rayon, 'nom' => $b['nom'],
            'avant' => (int) $b['numero'], 'apres' => $d['numero'],
            'libelle_avant' => entrepot_noeud_etiquette_libelle((int) $b['id']),
        ];
    }
}

echo "\nbarres déjà justes (numéro = nom) : $deja_bons\n";
echo 'barres à renuméroter : ', count($a_changer), "\n";
$par = [];
foreach ($a_changer as $c) {
    $par[$c['rayon']] = ($par[$c['rayon']] ?? 0) + 1;
}
ksort($par, SORT_NATURAL);
foreach ($par as $r => $n) {
    printf("  rayon %-6s %3d barre(s) — leurs étiquettes changent, à réimprimer\n", $r, $n);
}
if ($laisses) {
    echo "\nrayons LAISSÉS TELS QUELS (à trancher à la main) :\n";
    foreach ($laisses as $l) {
        echo "  - $l\n";
    }
}

if (!$appliquer) {
    echo "\nRépétition terminée. Relancez avec --appliquer pour écrire.\n";
    exit(0);
}
if (!$a_changer) {
    echo "\nRien à écrire.\n";
    exit(0);
}

/* la sauvegarde, puis l'écriture tout-ou-rien */
$dossier = __DIR__ . '/../backups';
if (!is_dir($dossier)) {
    @mkdir($dossier, 0750, true);
}
$sauvegarde = $dossier . '/barres_avant_renumerotation_' . date('Ymd_His') . '.csv';
$f = fopen($sauvegarde, 'w');
fputcsv($f, ['id', 'rayon', 'nom', 'numero_avant', 'numero_apres', 'libelle_avant']);
foreach ($a_changer as $c) {
    fputcsv($f, [$c['id'], $c['rayon'], $c['nom'], $c['avant'], $c['apres'], $c['libelle_avant']]);
}
fclose($f);
echo "\nsauvegarde : $sauvegarde\n";

$db->beginTransaction();
try {
    $db->exec('SET @sync_applying = 0'); // les déclencheurs (là où ils existent) marquent la synchro
    $maj = $db->prepare('UPDATE entrepot_hierarchie_noeud SET numero = :n, date_modification = NOW() WHERE id = :id');
    $marque = null;
    try {
        $db->query('SELECT sync_updated_at FROM entrepot_hierarchie_noeud LIMIT 1');
        $marque = $db->prepare('UPDATE entrepot_hierarchie_noeud SET sync_updated_at = NOW() WHERE id = :id');
    } catch (PDOException $e) {
        $marque = null;
    }
    /* deux passes : d'abord des numéros provisoires hors de portée, pour ne
       pas croiser la clé unique (étage, niveau, parent, numéro) pendant le
       réarrangement. La colonne est un SMALLINT UNSIGNED (65 535 au plus) :
       on reste sous ce plafond — 100 000 a fait annuler la première tentative
       du 09/09, sans rien écrire. */
    $provisoire = $db->prepare('UPDATE entrepot_hierarchie_noeud SET numero = :n WHERE id = :id');
    foreach ($a_changer as $i => $c) {
        $provisoire->execute([':n' => 30000 + $i, ':id' => $c['id']]);
    }
    foreach ($a_changer as $c) {
        $maj->execute([':n' => $c['apres'], ':id' => $c['id']]);
        if ($marque) {
            $marque->execute([':id' => $c['id']]);
        }
    }
    /* contrôle : plus aucun libellé partagé dans les rayons traités */
    $libs = [];
    foreach ($a_changer as $c) {
        $l = entrepot_noeud_etiquette_libelle($c['id']);
        if ($l !== '' && isset($libs[$l])) {
            throw new RuntimeException("le libellé « $l » serait encore porté par deux barres — annulation");
        }
        $libs[$l] = true;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    echo 'ANNULÉ, rien n\'a bougé : ', $e->getMessage(), "\n";
    exit(1);
}
echo count($a_changer), " barre(s) renumérotée(s). Étiquettes à réimprimer :\n";
foreach ($a_changer as $c) {
    printf("  %-6s %-6s %s → %s\n", $c['rayon'], $c['nom'], $c['libelle_avant'], entrepot_noeud_etiquette_libelle($c['id']));
}
