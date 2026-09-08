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
echo "— 4. une clé primaire composée se FUSIONNE au lieu d'échouer en silence —\n";
$src = file_get_contents($RACINE . '/includes/sync_functions.php');
verifie("sync_local_to_vps() retient le curseur d'avant le push pour la passe fichiers", true,
    strpos($src, "'since' => \$curseur_avant_push") !== false);
verifie('sync_push_table() remonte les erreurs du nœud distant', true,
    strpos($src, "\$errors += (int) (\$stats['errors'] ?? 0);") !== false && strpos($src, "'errors' => \$errors, 'max_seen'") !== false);
$pk = sync_table_primary_key_columns($db, 'produit_formulaire_champ_role');
verifie('produit_formulaire_champ_role a une clé primaire composée (champ_id, role)', ['champ_id', 'role'], $pk);
$ligne = $db->query("SELECT r.*, c.sync_uuid champ_uuid FROM produit_formulaire_champ_role r JOIN produit_formulaire_champ c ON c.id = r.champ_id WHERE r.sync_uuid IS NOT NULL AND r.sync_uuid <> '' AND c.sync_uuid IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$ligne) { echo "  (aucune ligne de droits avec uuid : preuve impossible ici)\n"; }
else {
    $fabrique = function ($role, $niveau) use ($ligne) {
        return ['table' => 'produit_formulaire_champ_role', 'sync_uuid' => 'test' . substr(md5($role . $niveau), 0, 32), 'sync_updated_at' => '2030-01-01 00:00:00',
            'sync_deleted_at' => null, 'sync_origin_node' => 'test',
            'data' => ['champ_id' => (int) $ligne['champ_id'], 'role' => $role, 'niveau' => $niveau, 'date_modification' => '2030-01-01 00:00:00'],
            'fk_uuids' => ['champ_id' => ['ref_table' => 'produit_formulaire_champ', 'sync_uuid' => $ligne['champ_uuid']]]];
    };
    /* une valeur VALIDE de l'énumération `niveau`, différente de l'actuelle */
    $type = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produit_formulaire_champ_role' AND COLUMN_NAME = 'niveau'")->fetchColumn();
    preg_match_all("/'([^']+)'/", (string) $type, $m);
    $autres = array_values(array_diff($m[1], [$ligne['niveau']]));
    $niveau_test = $autres[0] ?? $ligne['niveau'];
    $zero = ['inserted' => 0, 'updated' => 0, 'merged' => 0, 'skipped' => 0, 'conflicts' => 0, 'errors' => 0];
    $avant = (int) $db->query('SELECT COUNT(*) FROM produit_formulaire_champ_role')->fetchColumn();
    $db->beginTransaction();
    $s1 = $zero; $r1 = sync_apply_record($db, $fabrique($ligne['role'], $niveau_test), ['node_id' => 'test'], $s1);
    $apres = $db->query("SELECT niveau, sync_uuid FROM produit_formulaire_champ_role WHERE champ_id = " . (int) $ligne['champ_id'] . " AND role = " . $db->quote($ligne['role']))->fetch(PDO::FETCH_ASSOC);
    $pendant = (int) $db->query('SELECT COUNT(*) FROM produit_formulaire_champ_role')->fetchColumn();
    $s2 = $zero; $r2 = sync_apply_record($db, $fabrique('role_de_test_zz', $niveau_test), ['node_id' => 'test'], $s2);
    $neuf = $db->query("SELECT niveau FROM produit_formulaire_champ_role WHERE champ_id = " . (int) $ligne['champ_id'] . " AND role = 'role_de_test_zz'")->fetchColumn();
    $apres_insert = (int) $db->query('SELECT COUNT(*) FROM produit_formulaire_champ_role')->fetchColumn();
    $db->rollBack();
    verifie('une ligne (champ, rôle) déjà présente est FUSIONNÉE (merged 1, errors 0)', [true, 1, 0], [$r1, $s1['merged'], $s1['errors']]);
    verifie("…elle porte désormais l'uuid et le niveau reçus", ['test' . substr(md5($ligne['role'] . $niveau_test), 0, 32), $niveau_test], [$apres['sync_uuid'] ?? null, $apres['niveau'] ?? null]);
    verifie('…sans créer de doublon', $avant, $pendant);
    verifie('une ligne (champ, rôle) inconnue est INSÉRÉE avec ses deux colonnes de clé', [true, 1, 0, $niveau_test], [$r2, $s2['inserted'], $s2['errors'], $neuf]);
    verifie('…une ligne de plus', $avant + 1, $apres_insert);
    verifie('la transaction annulée a laissé les droits intacts', $avant, (int) $db->query('SELECT COUNT(*) FROM produit_formulaire_champ_role')->fetchColumn());
}
echo "— 5. déclencheurs jamais détruits sans pouvoir être recréés ; nœud miroir —\n";
verifie('sync_create_triggers_for_table() vérifie avant tout DROP', true,
    (bool) preg_match('/if \(!sync_triggers_creation_possible\(\$db\)\) \{.*?\}\s*\$db->exec\("DROP TRIGGER/s', corps('sync_create_triggers_for_table')));
$possible = sync_triggers_creation_possible($db);
$r = $db->query('SELECT @@global.log_bin AS b, @@global.log_bin_trust_function_creators AS t')->fetch(PDO::FETCH_ASSOC);
verifie('sync_triggers_creation_possible() répond un booléen cohérent avec la base locale', true,
    is_bool($possible) && ((!(int) $r['b'] || (int) $r['t']) ? $possible === true : true));
verifie("la règle « nœud miroir » est lue dans la configuration ('noeud_miroir')", true,
    strpos(corps('sync_apply_record'), "\$miroir = !empty(\$config['noeud_miroir']);") !== false);
verifie("l'exemple de configuration documente 'noeud_miroir'", true,
    strpos(file_get_contents($RACINE . '/config/sync.example.php'), "'noeud_miroir' => false") !== false);
if (!empty($ligne)) {
    /* même ligne (champ, rôle), marque locale PLUS RÉCENTE que l'envoi : refusée en règle générale, acceptée en miroir */
    $vieux = $fabrique($ligne['role'], $niveau_test); $vieux['sync_updated_at'] = '2000-01-01 00:00:00'; $vieux['sync_uuid'] = $ligne['sync_uuid'];
    $db->beginTransaction();
    $sa = $zero; $ra = sync_apply_record($db, $vieux, ['node_id' => 'test'], $sa);
    $sb = $zero; $rb = sync_apply_record($db, $vieux, ['node_id' => 'test', 'noeud_miroir' => true], $sb);
    $niveau_apres = $db->query("SELECT niveau FROM produit_formulaire_champ_role WHERE champ_id = " . (int) $ligne['champ_id'] . " AND role = " . $db->quote($ligne['role']))->fetchColumn();
    $db->rollBack();
    verifie('règle générale : une ligne locale plus récente REFUSE l\'envoi (conflit)', [false, 1], [$ra, $sa['conflicts']]);
    verifie('nœud miroir : le même envoi est APPLIQUÉ (l\'émetteur est la référence)', [true, 1, 0, $niveau_test], [$rb, $sb['updated'], $sb['conflicts'], $niveau_apres]);
}
echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
