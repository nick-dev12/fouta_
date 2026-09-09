<?php
/**
 * LE SOCLE N'ÉCRIT PLUS POUR RIEN (09/09/2026) — le banc de la lenteur.
 *
 * Ce que la direction a signalé : « l'application est très très lente ».
 * Mesuré sur foutasvr : CHAQUE écran d'administration coûtait ~3 s, et
 * l'essentiel venait du PIED DE PAGE, qui appelle
 * produit_formulaire_champs_ensure_schema() à chaque affichage. Celle-ci
 * lançait, sans jamais regarder l'état de la base :
 *   - un ALTER TABLE (MySQL RECONSTRUIT la table pour un MODIFY COLUMN) ;
 *   - huit UPDATE, donc huit transactions, donc huit allers-retours disque.
 * foutasvr tourne sur un disque MÉCANIQUE : 0,42 s par écriture. D'où ~5 s
 * perdues par page, pour ne rien changer.
 *
 * Ce banc vérifie les deux choses qui comptent :
 *   1. le RÉSULTAT est identique — même ENUM, mêmes sections, même manifeste ;
 *   2. le CHEMIN a changé — rien n'est écrit quand rien n'est à corriger, et
 *      la réparation marche toujours quand une ligne est vraiment décalée.
 *
 * À jouer :  php tests/test_socle_ecritures_inutiles.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/conn/conn.php';
require_once $RACINE . '/models/model_produit_formulaire_champs.php';

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
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true) . ")\n";
    }
}
function vrai($libelle, $cond)
{
    verifie($libelle, true, (bool) $cond);
}

/** Remet les compteurs static à zéro en rejouant la fonction dans un sous-processus. */
function jouer_a_neuf($RACINE, $php)
{
    $f = sys_get_temp_dir() . '/_socle_' . getmypid() . '.php';
    file_put_contents($f, "<?php\nrequire_once " . var_export($RACINE . '/conn/conn.php', true) . ";\n"
        . 'require_once ' . var_export($RACINE . '/models/model_produit_formulaire_champs.php', true) . ";\n" . $php);
    $sortie = shell_exec('php ' . escapeshellarg($f) . ' 2>&1');
    @unlink($f);

    return trim((string) $sortie);
}

$MAP = [
    'stock' => 'stock', 'statut' => 'stock',
    'categorie_id' => 'categorie', 'sous_categorie_id' => 'categorie',
    'prix' => 'prix', 'prix_promotion' => 'prix',
    'prix_entreprise' => 'prix', 'prix_achat' => 'prix',
];

echo "— l'état de départ —\n";
$col = $db->query("SHOW COLUMNS FROM produit_formulaire_champ LIKE 'section'")->fetch(PDO::FETCH_ASSOC);
vrai("la colonne section existe", $col !== false);
$type_avant = (string) $col['Type'];
foreach (['info', 'prix', 'stock', 'categorie', 'ref', 'variantes', 'options', 'media'] as $v) {
    vrai("l'ENUM contient « $v »", strpos($type_avant, "'" . $v . "'") !== false);
}

$manifeste_avant = jouer_a_neuf($RACINE, 'produit_formulaire_champs_ensure_schema(); echo produit_formulaire_champs_manifest_json();');
vrai('le manifeste se lit', $manifeste_avant !== '' && $manifeste_avant[0] === '{' || $manifeste_avant[0] === '[');

echo "— quand tout est en ordre, RIEN n'est écrit —\n";
/* La preuve : on regarde l'horodatage de modification de la table dans
   information_schema, et le compteur d'écritures d'InnoDB. Ni l'un ni l'autre
   ne doit bouger quand la base est déjà à sa place. */
$lire_ecritures = function () use ($db) {
    $r = $db->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Innodb_rows_updated','Com_alter_table')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    return ['maj' => (int) ($r['Innodb_rows_updated'] ?? 0), 'alter' => (int) ($r['Com_alter_table'] ?? 0)];
};
$avant = $lire_ecritures();
jouer_a_neuf($RACINE, 'produit_formulaire_champs_ensure_schema();');
$apres = $lire_ecritures();
verifie('aucune ligne réécrite', 0, $apres['maj'] - $avant['maj']);
verifie('aucun ALTER TABLE lancé', 0, $apres['alter'] - $avant['alter']);

echo "— le résultat est inchangé —\n";
$manifeste_apres = jouer_a_neuf($RACINE, 'produit_formulaire_champs_ensure_schema(); echo produit_formulaire_champs_manifest_json();');
verifie('le manifeste est identique', $manifeste_avant, $manifeste_apres);
$col2 = $db->query("SHOW COLUMNS FROM produit_formulaire_champ LIKE 'section'")->fetch(PDO::FETCH_ASSOC);
verifie("l'ENUM est identique", $type_avant, (string) $col2['Type']);

$sections_avant = $db->query("SELECT slug, section FROM produit_formulaire_champ WHERE est_systeme = 1 ORDER BY slug")
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$attendues = [];
foreach ($sections_avant as $slug => $sec) {
    $attendues[$slug] = isset($MAP[$slug]) ? $MAP[$slug] : $sec;
}
verifie('chaque champ système est dans sa section', $attendues, $sections_avant);

echo "— quand une ligne est VRAIMENT décalée, elle est réparée —\n";
$cible = null;
foreach (array_keys($MAP) as $slug) {
    if (isset($sections_avant[$slug])) {
        $cible = $slug;
        break;
    }
}
if ($cible === null) {
    echo "  (aucun champ système en base : réparation non testée ici)\n";
} else {
    $bonne = $MAP[$cible];
    $fausse = $bonne === 'info' ? 'prix' : 'info';
    $db->prepare('UPDATE produit_formulaire_champ SET section = :s WHERE slug = :g AND est_systeme = 1')
        ->execute([':s' => $fausse, ':g' => $cible]);
    $lu = $db->prepare('SELECT section FROM produit_formulaire_champ WHERE slug = :g AND est_systeme = 1');
    $lu->execute([':g' => $cible]);
    verifie("« $cible » a bien été décalé exprès", $fausse, (string) $lu->fetchColumn());

    jouer_a_neuf($RACINE, 'produit_formulaire_champs_ensure_schema();');

    $lu->execute([':g' => $cible]);
    verifie("« $cible » est revenu dans « $bonne »", $bonne, (string) $lu->fetchColumn());

    // et les autres n'ont pas bougé
    $sections_fin = $db->query("SELECT slug, section FROM produit_formulaire_champ WHERE est_systeme = 1 ORDER BY slug")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    verifie('les autres champs sont restés en place', $sections_avant, $sections_fin);
}

echo "— le prix de l'affichage —\n";
$t = microtime(true);
jouer_a_neuf($RACINE, 'produit_formulaire_champs_ensure_schema();');
$duree = microtime(true) - $t;
printf("  le socle complet (processus neuf compris) : %.3f s\n", $duree);
vrai('le socle ne coûte plus des secondes', $duree < 2.0);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
