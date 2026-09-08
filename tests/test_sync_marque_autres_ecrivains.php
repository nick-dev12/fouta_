<?php
/**
 * LES AUTRES ÉCRIVAINS DE produits NE MARQUAIENT PAS LA SYNCHRO (08/09/2026).
 *
 * Suite du constat de la direction « les anciennes photos restent après avoir
 * changé les photos » : sans déclencheurs MySQL (perdus sur foutasvr le
 * 01/09), seule la colonne sync_updated_at décide si une ligne part vers le
 * site public. L'éditeur photo et update_produit() la font avancer depuis le
 * 08/09 ; le lot d'optimisation d'images (includes/image_optimizer_db.php),
 * qui réécrit les chemins .jpg → .webp DANS produits, ne le faisait pas : une
 * photo convertie restait invisible du VPS.
 *
 * Vérifications STRUCTURELLES (chaque UPDATE produits du fichier porte la
 * marque conditionnelle) + la preuve du geste dans une TRANSACTION ANNULÉE :
 * la base ressort intacte (vérifié en fin de test).
 * Le modèle produits n'est volontairement PAS chargé : c'est le contexte réel
 * du lot (conn.php + image_optimizer_batch.php), où produits_has_column()
 * n'existe pas et où le repli information_schema doit jouer.
 *
 * À jouer :  php tests/test_sync_marque_autres_ecrivains.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/includes/image_optimizer_db.php';

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

/** Le corps d'une fonction, lu par réflexion — sans l'exécuter. */
function corps_fonction($nom) {
    $r = new ReflectionFunction($nom);
    $lignes = file($r->getFileName());

    return implode('', array_slice($lignes, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
}

$src = file_get_contents($RACINE . '/includes/image_optimizer_db.php');
/* le code sans ses commentaires : un docblock qui PARLE d'« UPDATE produits »
   n'est pas un UPDATE */
$code = preg_replace('#/\*.*?\*/#s', '', $src);

echo "— 1. chaque UPDATE produits du lot d'images porte la marque —\n";
verifie('la fonction de marque existe', true, function_exists('image_db_produits_marque_sync'));

/* Toutes les instructions du fichier (découpées au « ; » de fin de ligne) ;
   on ne retient que les UPDATE qui visent produits, nommément ou par la table
   générique `{$table}` (appelée avec 'produits' pour image_principale et
   image_etiquette_fpl). La requête peut être bâtie dans $sql puis préparée,
   ou préparée directement : les deux formes sont vues. */
$updates = [];
foreach (preg_split('/;\s*\r?\n/', $code) as $instruction) {
    if (strpos($instruction, 'UPDATE produits') !== false || strpos($instruction, 'UPDATE `{$table}`') !== false) {
        $updates[] = $instruction;
    }
}
verifie('4 UPDATE visent produits (2 nommés + 2 génériques)', 4, count($updates));
foreach ($updates as $i => $sql) {
    $court = preg_replace('/\s+/', ' ', substr(trim($sql), 0, 48));
    verifie('UPDATE n° ' . ($i + 1) . " porte la marque : {$court}…", true,
        strpos($sql, 'image_db_produits_marque_sync($db)') !== false);
    verifie('UPDATE n° ' . ($i + 1) . ' : la marque est dans le SET, avant le WHERE', true,
        strpos($sql, 'image_db_produits_marque_sync($db)') < strpos($sql, 'WHERE'));
}
$generiques = array_values(array_filter($updates, function ($s) { return strpos($s, '{$table}') !== false; }));
verifie('2 UPDATE génériques (image_principale / image_etiquette_fpl)', 2, count($generiques));
foreach ($generiques as $sql) {
    verifie("l'UPDATE générique ne marque QUE la table produits (jamais categories/slider)", true,
        strpos($sql, "(\$table === 'produits' ? image_db_produits_marque_sync(\$db) : '')") !== false);
}
verifie('aucun UPDATE produits passé par exec() (hors prepare) dans le fichier', 0,
    preg_match_all('/\$db->exec\([^;]*UPDATE[^;]*produits/si', $code));

echo "— 2. la marque est conditionnelle à la colonne —\n";
$corps = corps_fonction('image_db_produits_marque_sync');
verifie('elle réutilise produits_has_column() quand le modèle est chargé', true,
    strpos($corps, "function_exists('produits_has_column')") !== false
    && strpos($corps, "produits_has_column('sync_updated_at')") !== false);
verifie('sinon elle interroge information_schema (image_db_table_has_column)', true,
    strpos($corps, "image_db_table_has_column(\$db, 'produits', 'sync_updated_at')") !== false);
verifie('le résultat est mis en cache statique (une requête par base, pas par ligne)', true,
    strpos($corps, 'static $cache') !== false);
verifie('sans base : fragment vide, pas de plantage', '', image_db_produits_marque_sync(null));
verifie("ici le modèle produits n'est PAS chargé (contexte réel du lot d'images)", false,
    function_exists('produits_has_column'));

echo "— 3. sur cette base —\n";
if (!isset($db) || !($db instanceof PDO)) {
    echo "  (pas de base : preuve impossible ici)\n";
} else {
    $colonne = image_db_table_has_column($db, 'produits', 'sync_updated_at');
    verifie("le fragment suit l'existence de la colonne", $colonne ? ', sync_updated_at = NOW()' : '',
        image_db_produits_marque_sync($db));
    verifie('deuxième appel identique (cache)', image_db_produits_marque_sync($db), image_db_produits_marque_sync($db));
    verifie('une colonne absente donne bien false (le repli sait dire non)', false,
        image_db_table_has_column($db, 'produits', 'colonne_inexistante_08_09'));

    if ($colonne) {
        echo "— 4. le geste, dans une transaction ANNULÉE —\n";
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $piece = $db->query("SELECT id, image_principale FROM produits
                             WHERE image_principale IS NOT NULL AND image_principale != '' ORDER BY id LIMIT 1")
                    ->fetch(PDO::FETCH_ASSOC);
        /* une pièce dont la galerie JSON contient au moins un chemin */
        $galerie = null;
        $q = $db->query("SELECT id, images FROM produits WHERE images IS NOT NULL AND images != '' ORDER BY id LIMIT 50");
        foreach ($q as $r) {
            $d = json_decode((string) $r['images'], true);
            if (is_array($d) && isset($d[0]) && is_string($d[0]) && trim($d[0]) !== '') {
                $galerie = ['id' => (int) $r['id'], 'chemin' => trim($d[0])];
                break;
            }
        }
        if (!$piece || !$galerie) {
            echo "  (aucune pièce avec image : preuve sautée)\n";
        } else {
            $ids = array_unique([(int) $piece['id'], $galerie['id']]);
            $liste = implode(',', $ids);
            $etat = function () use ($db, $liste) {
                return $db->query("SELECT id, image_principale, images, sync_updated_at FROM produits WHERE id IN ($liste) ORDER BY id")
                          ->fetchAll(PDO::FETCH_ASSOC);
            };
            $avant = $etat();
            $db->beginTransaction();
            try {
                /* là où des déclencheurs existent encore, ils ne re-marquent pas
                   sous @sync_applying : seul le code est jugé ici */
                $db->exec('SET @sync_applying = 1');
                $db->exec("UPDATE produits SET sync_updated_at = '2000-01-01 00:00:00' WHERE id IN ($liste)");

                $id = (int) $piece['id'];
                $vieux = (string) $piece['image_principale'];
                $neuf = $vieux . '.test-08-09';
                $n = image_db_replace_column_exact($db, 'produits', 'image_principale', $vieux, $neuf);
                verifie('image_db_replace_column_exact() touche la ligne', true, $n >= 1);
                $lu = $db->query("SELECT image_principale, sync_updated_at FROM produits WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
                verifie('le chemin est réécrit', $neuf, $lu['image_principale']);
                verifie('…et sync_updated_at a avancé (la ligne partira au VPS)', true,
                    (string) $lu['sync_updated_at'] > '2000-01-01 00:00:00');

                $gid = $galerie['id'];
                $n2 = image_db_replace_in_produits_images_json($db, $galerie['chemin'], $galerie['chemin'] . '.test-08-09');
                verifie('image_db_replace_in_produits_images_json() touche la galerie', true, $n2 >= 1);
                $lu2 = $db->query("SELECT images, sync_updated_at FROM produits WHERE id = $gid")->fetch(PDO::FETCH_ASSOC);
                verifie('la galerie est réécrite', true, strpos((string) $lu2['images'], '.test-08-09') !== false);
                verifie('…et sync_updated_at a avancé aussi', true,
                    (string) $lu2['sync_updated_at'] > '2000-01-01 00:00:00');
            } finally {
                $db->rollBack();
                $db->exec('SET @sync_applying = NULL');
            }
            verifie('la base ressort intacte (transaction annulée)', $avant, $etat());
        }
    } else {
        echo "  (produits sans colonne sync_updated_at : le fragment vide est la bonne réponse)\n";
    }
}

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
