<?php
/**
 * « ÇA RECOMMENCE 1, 2, 3, 4, 5 » (08/09/2026, constat de la direction).
 *
 * À l'écran Structure, ajouter une barre sous une étagère qui allait jusqu'à B4
 * créait un second B1 : le nom était fabriqué par « nom . (i + 1) », donc
 * toujours à partir de 1. Et l'étiquette d'une barre nommée B5 affichait 01,
 * parce que le NUMÉRO — le seul que le libellé montre — était compté sous
 * l'ÉTAGÈRE, pas dans le rayon.
 *
 * La règle est celle des étiquettes DÉJÀ IMPRIMÉES, mesurée sur les données :
 * le numéro d'une barre est son rang DANS SON RAYON, en une seule suite
 * continue à travers les étagères (15A : 1 à 21 ; 21A : 1 à 33). Le format du
 * libellé ne change pas, et aucune ligne existante n'est renumérotée.
 *
 * Ces vérifications sont en LECTURE SEULE, sauf la preuve en base qui se joue
 * dans une TRANSACTION ANNULÉE — la dernière vérification le prouve.
 *
 * À jouer :  php tests/test_structure_numerotation_barres.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/models/model_entrepot_hierarchie_libre.php';
require_once $RACINE . '/includes/entrepot_nommage.php';

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

echo "— 1. lire un nom : « préfixe + numéro », ou un nom entier —\n";
verifie('« B5 » se lit B puis 5', ['prefixe' => 'B', 'numero' => 5, 'largeur' => 1], entrepot_nom_decomposer('B5'));
verifie('« B05 » garde ses deux chiffres', ['prefixe' => 'B', 'numero' => 5, 'largeur' => 2], entrepot_nom_decomposer('B05'));
verifie('« 12 » est un numéro sans préfixe', ['prefixe' => '', 'numero' => 12, 'largeur' => 2], entrepot_nom_decomposer('12'));
verifie('« Zone A » n\'est pas numéroté', null, entrepot_nom_decomposer('Zone A'));
verifie('« 15A » non plus (il ne finit pas par un nombre)', null, entrepot_nom_decomposer('15A'));
verifie('un nom vide non plus', null, entrepot_nom_decomposer('   '));
verifie('composer : B + 5 sur 2 chiffres', 'B05', entrepot_nom_composer('B', 5, 2));
verifie('composer : sans zéro de tête', 'B5', entrepot_nom_composer('B', 5));
verifie('deux noms se comparent sans casse ni blancs', true, entrepot_nom_meme(' b1 ', 'B1'));

echo "— 2. la série continue la suite, elle ne la recommence pas —\n";
$freres = ['B1', 'B2', 'B3', 'B4'];
$s = entrepot_noms_serie('B', 1, 5, $freres);
verifie('après B4, « B » donne B5', ['B5'], $s['noms']);
verifie('…et son numéro est 5 (celui de l\'étiquette)', [5], $s['numeros']);
$s = entrepot_noms_serie('B', 3, 5, $freres);
verifie('« B » ×3 donne B5, B6, B7', ['B5', 'B6', 'B7'], $s['noms']);
verifie('…numérotées 5, 6, 7', [5, 6, 7], $s['numeros']);
$s = entrepot_noms_serie('B', 2, 1, []);
verifie('sans frère, la série part de 1', ['B1', 'B2'], $s['noms']);
$s = entrepot_noms_serie('B9', 3, 5, $freres);
verifie('un numéro écrit à la main est honoré : « B9 » ×3', ['B9', 'B10', 'B11'], $s['noms']);
verifie('…avec les numéros correspondants', [9, 10, 11], $s['numeros']);
$s = entrepot_noms_serie('B', 2, 5, ['B01', 'B02', 'B03', 'B04']);
verifie('les zéros de tête des frères sont repris', ['B05', 'B06'], $s['noms']);
$s = entrepot_noms_serie('Zone A', 1, 7, $freres);
verifie('un nom entier reste tel quel', ['Zone A'], $s['noms']);
verifie('…et laisse la base numéroter', false, $s['suit_le_numero']);
$s = entrepot_noms_serie('Zone A', 2, 7, $freres);
verifie('…mais en série il devient un préfixe', ['Zone A7', 'Zone A8'], $s['noms']);
$s = entrepot_noms_serie('b', 1, 5, $freres);
verifie('la casse du préfixe saisi est conservée', ['b5'], $s['noms']);
$s = entrepot_noms_serie('', 2, 3, ['1', '2']);
verifie('un préfixe vide numérote tout court', ['3', '4'], $s['noms']);

echo "— 3. la portée du numéro suit l'étiquette —\n";
$defs_etiq = entrepot_hierarchie_defs_etiquette();
verifie('au moins un niveau porte une étiquette', true, $defs_etiq !== []);
$def_barre = null;
foreach ($defs_etiq as $d) {
    if (strtolower((string) ($d['slug'] ?? '')) === 'barre') {
        $def_barre = $d;
    }
}
if ($def_barre === null) {
    echo "  (aucun niveau « barre » configuré : la preuve en base est impossible ici)\n";
} else {
    $nid = (int) $def_barre['id'];
    $lie_niveau = (int) ($def_barre['etiquette_lie_niveau_id'] ?? 0);
    verifie('la barre est liée à un niveau (le rayon)', true, (string) ($def_barre['etiquette_lie_type'] ?? '') === 'niveau' && $lie_niveau > 0);

    /* le rayon qui a le plus de barres, et une de ses étagères */
    $rayon = $db->query(
        "SELECT r.id, r.nom, COUNT(b.id) n
           FROM entrepot_hierarchie_noeud r
           JOIN entrepot_hierarchie_noeud e ON e.parent_id = r.id
           JOIN entrepot_hierarchie_noeud b ON b.parent_id = e.id AND b.niveau_id = $nid
          WHERE r.niveau_id = $lie_niveau
       GROUP BY r.id, r.nom ORDER BY n DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$rayon) {
        echo "  (aucun rayon peuplé : preuve en base impossible ici)\n";
    } else {
        $etageres = $db->query('SELECT * FROM entrepot_hierarchie_noeud WHERE parent_id = ' . (int) $rayon['id'] . ' ORDER BY numero')->fetchAll(PDO::FETCH_ASSOC);
        $etagere = $etageres[0];
        $portee = entrepot_noeud_portee_numero((int) $etagere['etage_id'], $nid, (int) $etagere['id']);
        verifie('la portée d\'une barre est son RAYON, pas son étagère', 'rayon', $portee['portee']);
        verifie('…la racine est bien le rayon trouvé', (int) $rayon['id'], $portee['racine_id']);
        verifie('…et elle compte les barres de TOUTES les étagères du rayon', (int) $rayon['n'], count($portee['ids']));
        $sous_cette_etagere = count(entrepot_noeud_liste((int) $etagere['etage_id'], $nid, (int) $etagere['id']));
        verifie('…soit davantage que les seules barres de cette étagère', true, count($portee['ids']) > $sous_cette_etagere);

        echo "— 4. la preuve en base, dans une transaction annulée —\n";
        $avant = (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_noeud')->fetchColumn();
        $suite = entrepot_noms_serie('B', 3, $portee['max'] + 1, $portee['noms']);
        $db->beginTransaction();
        $creees = [];
        foreach ($suite['noms'] as $i => $nom_i) {
            $r = entrepot_noeud_ajouter((int) $etagere['etage_id'], $nid, (int) $etagere['id'], $nom_i, (int) $suite['numeros'][$i]);
            $creees[] = $r;
        }
        $numeros = [];
        $libelles = [];
        foreach ($creees as $r) {
            $numeros[] = empty($r['success']) ? null : (int) $r['noeud']['numero'];
            $libelles[] = empty($r['success']) ? null : entrepot_noeud_etiquette_libelle((int) $r['noeud']['id']);
        }
        $refus_nom = entrepot_noeud_ajouter((int) $etagere['etage_id'], $nid, (int) $etagere['id'], (string) $portee['noms'][0], 0);
        $refus_num = entrepot_noeud_ajouter((int) $etagere['etage_id'], $nid, (int) $etagere['id'], 'Barre dessai', 1);
        $db->rollBack();

        verifie('les trois barres sont créées', [true, true, true], array_map(function ($r) { return !empty($r['success']); }, $creees));
        verifie('leurs numéros continuent la suite du rayon', [$portee['max'] + 1, $portee['max'] + 2, $portee['max'] + 3], $numeros);
        verifie('le nom porte le même nombre que le numéro', true,
            entrepot_nom_decomposer($suite['noms'][0])['numero'] === $numeros[0]);
        verifie('l\'étiquette affiche ce numéro', true,
            $libelles[0] !== null && substr($libelles[0], -3) === '-' . sprintf('%02d', $numeros[0]));
        verifie('un nom déjà pris dans le rayon est refusé', false, !empty($refus_nom['success']));
        verifie('…avec un message qui dit pourquoi', true, strpos((string) $refus_nom['message'], 'existe déjà') !== false);
        verifie('un numéro déjà pris dans le rayon est refusé', false, !empty($refus_num['success']));
        verifie('…en nommant l\'emplacement qui le porte', true, strpos((string) $refus_num['message'], 'déjà pris') !== false);
        verifie('la base ressort intacte', $avant, (int) $db->query('SELECT COUNT(*) FROM entrepot_hierarchie_noeud')->fetchColumn());
    }
}

echo "— 4 bis. les blocs se suivent d'une étagère à l'autre —\n";
/* La règle, dite par la direction : « si l'étagère 1 a quatre barres, 1 à 4,
   l'étagère 2 doit commencer par 5, 6, 7, 8 et continuer ». Chaque étagère
   occupe donc un BLOC de numéros à la suite de la précédente. On le vérifie
   sur les données, rayon par rayon, et on nomme ceux qui s'en écartent. */
$conformes = [];
$casses = [];
if (!empty($nid) && !empty($lie_niveau)) {
    foreach ($db->query("SELECT id, nom FROM entrepot_hierarchie_noeud WHERE niveau_id = $lie_niveau ORDER BY etage_id, numero") as $r_) {
        $blocs = [];
        foreach ($db->query('SELECT id, nom, etage_id FROM entrepot_hierarchie_noeud WHERE parent_id = ' . (int) $r_['id'] . ' ORDER BY numero, id') as $e_) {
            $nums = $db->query('SELECT numero FROM entrepot_hierarchie_noeud WHERE parent_id = ' . (int) $e_['id'] . " AND niveau_id = $nid ORDER BY numero")->fetchAll(PDO::FETCH_COLUMN);
            if ($nums) {
                $blocs[] = ['etagere' => $e_, 'nums' => array_map('intval', $nums)];
            }
        }
        if (count($blocs) < 2) {
            continue;
        }
        $attendu = 1;
        $ok_rayon = true;
        foreach ($blocs as $b) {
            if ($b['nums'][0] !== $attendu) {
                $ok_rayon = false;
            }
            for ($k = 1; $k < count($b['nums']); $k++) {
                if ($b['nums'][$k] !== $b['nums'][$k - 1] + 1) {
                    $ok_rayon = false;
                }
            }
            $attendu = max($b['nums']) + 1;
        }
        if ($ok_rayon) {
            $conformes[(string) $r_['nom']] = $blocs;
        } else {
            $casses[] = (string) $r_['nom'];
        }
    }
}
verifie('des rayons suivent la règle sur plusieurs étagères', true, $conformes !== []);
verifie('les seuls rayons qui s\'en écartent sont ceux de test (1 et 2A)', ['1', '2A'], $casses);

if ($conformes !== []) {
    /* le plus grand des rayons conformes : ses blocs se suivent vraiment */
    $noms_conf = array_keys($conformes);
    usort($noms_conf, function ($a, $b) use ($conformes) { return count($conformes[$b]) <=> count($conformes[$a]); });
    $sain = $conformes[$noms_conf[0]];
    $suite_blocs = [];
    foreach ($sain as $b) {
        $suite_blocs[] = $b['nums'][0] . '→' . max($b['nums']);
    }
    $attendu_suite = [];
    $n_ = 1;
    foreach ($sain as $b) {
        $attendu_suite[] = $n_ . '→' . ($n_ + count($b['nums']) - 1);
        $n_ += count($b['nums']);
    }
    verifie('rayon « ' . $noms_conf[0] . ' » : chaque étagère reprend là où la précédente s\'arrête', $attendu_suite, $suite_blocs);

    echo "— 4 ter. l'écran annonce le numéro, et prévient si on insère au milieu —\n";
    $etats = [];
    foreach ($sain as $b) {
        $etats[] = entrepot_noeud_suite_etat((int) $b['etagere']['etage_id'], $nid, (int) $b['etagere']['id']);
    }
    $premiere = $etats[0];
    $derniere = $etats[count($etats) - 1];
    verifie('la dernière étagère peuplée prolonge la suite sans rien réimprimer', [true, 0],
        [$derniere['est_dernier_bloc'], $derniere['a_reimprimer']]);
    verifie("une étagère du milieu sait qu'elle sortirait de sa série", false, $premiere['est_dernier_bloc']);
    verifie("…et chiffre les étiquettes à réimprimer pour l'ordre strict", true, $premiere['a_reimprimer'] > 0);
    verifie('toutes annoncent le même prochain numéro (celui du rayon)', 1,
        count(array_unique(array_map(function ($x) { return $x['prochain']; }, $etats))));
}
$ecran_suite = file_get_contents($RACINE . '/admin/produits/structure-entrepot.php');
verifie("l'écran annonce le numéro avant de cliquer", true,
    strpos($ecran_suite, 'entrepot_noeud_suite_etat(') !== false);
verifie("…en disant « à la suite du rayon » quand c'est le cas", true,
    strpos($ecran_suite, 'à la suite du rayon') !== false);
verifie('…et en chiffrant les réimpressions sinon', true,
    strpos($ecran_suite, 'a_reimprimer') !== false && strpos($ecran_suite, 'réimprimer') !== false);

echo "— 5. un niveau SANS étiquette garde la portée du parent —\n";
$sans_etiq = null;
foreach (entrepot_hierarchie_def_list(true) as $d) {
    if ((int) ($d['est_etiquette_qr'] ?? 0) === 0 && !entrepot_hierarchie_def_est_etage($d)) {
        $noeud = $db->query('SELECT * FROM entrepot_hierarchie_noeud WHERE niveau_id = ' . (int) $d['id'] . ' AND parent_id IS NOT NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($noeud) {
            $sans_etiq = [$d, $noeud];
            break;
        }
    }
}
if ($sans_etiq === null) {
    echo "  (aucun niveau sans étiquette peuplé ici : rien à prouver)\n";
} else {
    list($d, $noeud) = $sans_etiq;
    $p = entrepot_noeud_portee_numero((int) $noeud['etage_id'], (int) $d['id'], (int) $noeud['parent_id']);
    verifie('« ' . $d['label'] .' » (sans étiquette) se compte sous son parent', 'parent', $p['portee']);
    $freres = entrepot_noeud_liste((int) $noeud['etage_id'], (int) $d['id'], (int) $noeud['parent_id']);
    verifie('…et ne voit que ses frères directs', count($freres), count($p['ids']));
}

echo "— 6. rien n'est renuméroté : aucune migration, aucun UPDATE —\n";
$lot = [
    'includes/entrepot_nommage.php',
    'models/model_entrepot_hierarchie_libre.php',
    'admin/produits/structure-entrepot.php',
];
$en_masse = [];
foreach ($lot as $f) {
    $src = file_get_contents($RACINE . '/' . $f);
    if (preg_match_all('/UPDATE\s+entrepot_hierarchie_noeud\s+SET[^;]*?\bnumero\b[^;]*/is', $src, $m)) {
        foreach ($m[0] as $requete) {
            /* écrire UN seul emplacement (WHERE id = :id) est le renommage à
               l'unité, qui existait avant ce lot ; ce qu'on interdit, c'est
               une renumérotation EN MASSE des lignes déjà là. */
            if (!preg_match('/WHERE\s+id\s*=\s*:id/i', $requete)) {
                $en_masse[] = $f;
            }
        }
    }
}
verifie('aucune renumérotation en masse dans le lot', [], $en_masse);
verifie('aucune migration de renumérotation ajoutée', [], array_values(array_map('basename', array_filter(
    glob($RACINE . '/migrations/*.php') ?: [],
    function ($f) { return strpos(basename($f), 'numerot') !== false; }
))));
verifie('le renommage contrôle la même portée que la création', true,
    strpos(file_get_contents($RACINE . '/models/model_entrepot_hierarchie_libre.php'), "MÊME PORTÉE QU'À LA CRÉATION") !== false);
$ecran = file_get_contents($RACINE . '/admin/produits/structure-entrepot.php');
verifie('l\'écran ne fabrique plus les noms à partir de 1', false, strpos($ecran, '$nom . ($i + 1)') !== false);
verifie('il passe par les règles partagées', true, strpos($ecran, 'entrepot_noms_serie(') !== false);
verifie('et par la portée du numéro', true, strpos($ecran, 'entrepot_noeud_portee_numero(') !== false);
verifie('l\'aide du champ Nom ne promet plus « B1, B2 »', false, strpos($ecran, 'donne B1, B2') !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
