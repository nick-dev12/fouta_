<?php
/**
 * LES ANCIENNES PHOTOS RESTAIENT SUR LA PAGE DU QR (08/09/2026, constat de la
 * direction) — trois défauts de la synchronisation foutasvr → VPS :
 *
 *  1. AUCUN code n'avançait sync_updated_at : seuls les déclencheurs MySQL le
 *     faisaient, et foutasvr les a perdus à l'import du 01/09 (erreur 1419 à
 *     chaque tentative de les recréer). Une photo changée n'était donc jamais
 *     poussée. → l'éditeur photo et update_produit() marquent eux-mêmes.
 *  2. Le curseur du push (« sync_updated_at > ? ») PERDAIT des lignes dès que
 *     plus de 500 lignes partageaient la même seconde : 445 poussées sur 3 338.
 *     → curseur à deux clés (seconde, id) — prouvé ici sur 1 200 lignes.
 *  3. Le MySQL du VPS écrivait NOW() en heure de Paris (+2 h) : ses re-marques
 *     gagnaient deux heures sur les écritures de Dakar. → SET time_zone +00:00.
 *
 * La preuve du curseur écrit dans une TRANSACTION ANNULÉE : la base ressort
 * intacte (vérifié en fin de test). À jouer : php tests/test_sync_marques_et_curseur.php
 */
$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/includes/sync_functions.php';
require_once $RACINE . '/models/model_produits.php';

$ok = 0; $ko = 0;
function verifie($lib, $attendu, $obtenu) {
    global $ok, $ko;
    if ($attendu === $obtenu) { $ok++; echo "  OK  $lib\n"; }
    else { $ko++; echo "  KO  $lib (attendu " . var_export($attendu, true) . ", obtenu " . var_export($obtenu, true) . ")\n"; }
}
function corps($nom) { $r = new ReflectionFunction($nom); $l = file($r->getFileName()); return implode('', array_slice($l, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)); }

echo "— 1. les écritures marquent elles-mêmes la synchro —\n";
verifie('update_produit() avance sync_updated_at', true, substr_count(corps('update_produit'), 'sync_updated_at = NOW()') >= 2);
$photo = file_get_contents($RACINE . '/admin/produits/ajax_photo_enregistrer.php');
verifie("l'éditeur photo avance sync_updated_at", true, strpos($photo, 'sync_updated_at = NOW()') !== false);
verifie('…seulement si la colonne existe', true, strpos($photo, "produits_has_column('sync_updated_at')") !== false);

echo "— 3. l'heure de la base est celle de Dakar —\n";
verifie("conn.php force le fuseau +00:00", true, strpos(file_get_contents($RACINE . '/conn/conn.php'), "SET time_zone = '+00:00'") !== false);
$h = $db->query("SELECT @@session.time_zone tz, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) ecart")->fetch(PDO::FETCH_ASSOC);
verifie('la session est en +00:00', '+00:00', $h['tz']);
verifie('NOW() = UTC_TIMESTAMP() (écart 0 s)', 0, (int) $h['ecart']);

echo "— 2. le curseur à deux clés ne perd aucune ligne —\n";
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!sync_registry_has_sync_columns($db, 'produits')) { echo "  (produits sans colonnes de synchro : preuve impossible ici)\n"; }
else {
    $ids = $db->query('SELECT id FROM produits ORDER BY id LIMIT 1200')->fetchAll(PDO::FETCH_COLUMN);
    $n = count($ids);
    $db->beginTransaction();
    $db->exec('SET @sync_applying = 1'); // les déclencheurs, là où ils existent, ne re-marquent pas
    $db->exec("UPDATE produits SET sync_updated_at = '2030-01-01 00:00:00', sync_origin_node = NULL WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')');
    $since = '2029-12-31 23:59:59';
    /* le filtre d'origine (config/sync.php, absent sur un poste de dev) n'est
       pas ce qu'on prouve : on le laisse à false, la pagination est la même */

    $ancien = []; $cursor = $since;                       // l'ancien curseur : strictement après la seconde vue
    do { $lot = sync_get_pending_records($db, 'produits', $cursor, 500, false); if (!$lot) break;
         foreach ($lot as $it) { $ancien[(int) $it['data']['id']] = 1; $cursor = $it['sync_updated_at']; }
    } while (count($lot) >= 500);

    $vus = []; $total = 0; $cursor = $since; $pk = null;   // le curseur à deux clés
    do { $lot = sync_get_pending_records($db, 'produits', $cursor, 500, false, $pk); if (!$lot) break;
         foreach ($lot as $it) { $vus[(int) $it['data']['id']] = 1; $total++; }
         $d = $lot[count($lot) - 1]; $cursor = $d['sync_updated_at']; $pk = $d['data']['id'];
    } while (count($lot) >= 500);
    $db->rollBack();

    verifie("$n lignes marquées la même seconde", true, $n >= 1000);
    verifie("l'ancien curseur en perdait (il n'en voyait que 500)", 500, count($ancien));
    verifie('le curseur à deux clés les voit toutes', $n, count($vus));
    verifie('…chacune une seule fois', $n, $total);
    verifie('la transaction annulée a laissé la base intacte', 0, (int) $db->query("SELECT COUNT(*) FROM produits WHERE sync_updated_at = '2030-01-01 00:00:00'")->fetchColumn());
    $src = corps('sync_push_table');
    verifie('sync_push_table() reprend après la dernière ligne du lot', true, strpos($src, '$cursor_pk = $suite_pk;') !== false && strpos($src, '$cursor = $suite_ts;') !== false);
}
echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
