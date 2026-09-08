<?php
/**
 * DÉDOUBLONNER LES FORMATS D'ÉTIQUETTE SUR LE VPS (08/09/2026) — à lancer par la
 * direction, en root, sur le VPS :
 *
 *     cd /home/jomas/foutapoidslourds.com && php scripts/vps_dedoublonner_formats_etiquette.php
 *
 * D'OÙ VIENT LE DOUBLON : les six formats (70×70, 50×30, 65×100, 100×130, 90×40,
 * 150×60) ont été semés séparément sur chaque serveur ; seul le 150×60 porte un
 * uuid fixe. Le rattrapage de synchro du 08/09 a poussé les cinq autres depuis
 * foutasvr : inconnus par uuid sur le VPS, et sans clé métier unique, ils ont
 * été INSÉRÉS (ids 7 à 11) au lieu de fusionner avec les ids 1 à 5. Le VPS
 * montre donc chaque format deux fois.
 *
 * CE QUE FAIT LE SCRIPT (transaction tout-ou-rien, sauvegarde JSON avant tout) :
 *   1. vérifie que chaque paire (i, i+6) désigne le MÊME format (nom, largeur,
 *      hauteur) et que rien ne référence les doublons 7-11 (etiquette_impressions) ;
 *   2. supprime le doublon i+6 ;
 *   3. recopie dans la ligne i TOUT le contenu de foutasvr (dont son uuid) : la
 *      référence, c'est foutasvr — les prochaines synchros mettront à jour i ;
 *   4. corrige sync_id_map (uuid → i) ;
 *   5. refuse et annule tout s'il ne reste pas exactement 6 formats.
 * Idempotent : relancé après coup, il constate qu'il n'y a plus de doublon.
 */
require_once __DIR__ . '/../conn/conn.php';
/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$rows = [];
foreach ($db->query('SELECT * FROM etiquette_formats ORDER BY id') as $r) { $rows[(int) $r['id']] = $r; }
$doublons = array_filter(array_keys($rows), function ($id) { return $id >= 7 && $id <= 11; });
if (!$doublons) { echo "Aucun doublon (ids 7-11 absents) : rien à faire.\n"; exit(0); }

$dossier = __DIR__ . '/../backups';
if (!is_dir($dossier)) { @mkdir($dossier, 0750, true); }
$sauvegarde = $dossier . '/etiquette_formats_avant_dedoublon_' . date('Ymd_His') . '.json';
$map = $db->query("SELECT * FROM sync_id_map WHERE table_name = 'etiquette_formats'")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents($sauvegarde, json_encode(['etiquette_formats' => array_values($rows), 'sync_id_map' => $map], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Sauvegarde : $sauvegarde\n";

$cols = $db->query('DESCRIBE etiquette_formats')->fetchAll(PDO::FETCH_COLUMN);
$db->exec('SET @sync_applying = 1'); // les déclencheurs ne doivent pas re-marquer ces lignes
$db->beginTransaction();
try {
    $n = 0;
    for ($i = 1; $i <= 5; $i++) {
        $a = $rows[$i] ?? null; $b = $rows[$i + 6] ?? null;
        if (!$b) { continue; }
        if (!$a) { throw new RuntimeException("le format $i n'existe pas : rien à fusionner avec " . ($i + 6)); }
        if ($a['nom'] !== $b['nom'] || (float) $a['largeur_mm'] !== (float) $b['largeur_mm'] || (float) $a['hauteur_mm'] !== (float) $b['hauteur_mm']) {
            throw new RuntimeException("paire $i / " . ($i + 6) . " : formats différents ({$a['nom']} / {$b['nom']}) — on s'arrête");
        }
        $refs = (int) $db->query('SELECT COUNT(*) FROM etiquette_impressions WHERE format_id = ' . ($i + 6))->fetchColumn();
        if ($refs > 0) { throw new RuntimeException('le doublon ' . ($i + 6) . " est référencé par $refs impression(s) — on s'arrête"); }
        $db->exec('DELETE FROM etiquette_formats WHERE id = ' . ($i + 6));
        $sets = []; $vals = [];
        foreach ($cols as $c) { if ($c === 'id') { continue; } $sets[] = "`$c` = ?"; $vals[] = $b[$c]; }
        $vals[] = $i;
        $db->prepare('UPDATE etiquette_formats SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        $db->prepare("UPDATE sync_id_map SET local_id = ? WHERE table_name = 'etiquette_formats' AND sync_uuid = ?")->execute([$i, $b['sync_uuid']]);
        $n++;
        echo "  format $i « {$a['nom']} » : contenu et uuid de foutasvr repris (" . substr((string) $b['sync_uuid'], 0, 8) . "…), doublon " . ($i + 6) . " supprimé\n";
    }
    $reste = (int) $db->query('SELECT COUNT(*) FROM etiquette_formats')->fetchColumn();
    if ($reste !== 6) { throw new RuntimeException("il resterait $reste formats au lieu de 6 — annulation"); }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    echo 'ANNULÉ, rien n\'a bougé : ', $e->getMessage(), "\n";
    exit(1);
}
echo "$n paire(s) fusionnée(s). Les formats :\n";
foreach ($db->query('SELECT id, nom, largeur_mm, hauteur_mm, sync_uuid FROM etiquette_formats ORDER BY id') as $r) { echo '  ', implode(' | ', $r), "\n"; }
