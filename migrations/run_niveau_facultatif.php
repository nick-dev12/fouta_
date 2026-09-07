<?php
/**
 * LES NIVEAUX QU'ON PEUT SAUTER (07/09/2026) — décision de la direction.
 *
 * « Même si on active la Box dans « Gérer les niveaux », elle doit rester
 * OPTIONNELLE : on doit pouvoir la sauter et aller directement à la position. »
 * Or l'écran de structure ne proposait que le niveau immédiatement suivant :
 * dès que la box existait, plus moyen de créer une position sous une barre.
 *
 * Ce que fait la migration :
 *   1. la colonne entrepot_hierarchie_niveau.facultatif (0/1) — un niveau
 *      marqué facultatif se saute, l'écran propose alors aussi le suivant ;
 *   2. SEMIS : la BOX est marquée facultative, puisque c'est la demande. Rien
 *      d'autre n'est touché, et un réglage fait à l'écran n'est jamais rejeté
 *      (on ne pose la valeur que si la colonne vient d'être créée, ou avec
 *      --forcer).
 *
 * Idempotente :  php migrations/run_niveau_facultatif.php [--forcer]
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$forcer = in_array('--forcer', $argv ?? [], true);

$existe = $db->query("SHOW COLUMNS FROM entrepot_hierarchie_niveau LIKE 'facultatif'")->fetch(PDO::FETCH_ASSOC);
$neuve = false;
if (!$existe) {
    $db->exec("ALTER TABLE entrepot_hierarchie_niveau
               ADD COLUMN facultatif TINYINT(1) NOT NULL DEFAULT 0
               COMMENT 'niveau qu\'on peut sauter dans la structure' AFTER est_etiquette_qr");
    $neuve = true;
    echo "colonne « facultatif » ajoutée\n";
} else {
    echo "colonne « facultatif » déjà là\n";
}

/* la box : facultative, c'est la demande de la direction */
$box = $db->query("SELECT id, label, facultatif FROM entrepot_hierarchie_niveau
                   WHERE slug = 'box' AND sync_deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$box) {
    echo "aucun niveau « box » : rien à marquer.\n";
} elseif ((int) $box['facultatif'] === 1) {
    echo 'niveau #', $box['id'], ' « ', $box['label'], " » : déjà facultatif.\n";
} elseif ($neuve || $forcer) {
    $db->prepare('UPDATE entrepot_hierarchie_niveau SET facultatif = 1, sync_updated_at = NOW() WHERE id = ?')
       ->execute([(int) $box['id']]);
    echo 'niveau #', $box['id'], ' « ', $box['label'], " » : marqué FACULTATIF (on peut le sauter).\n";
} else {
    echo 'niveau #', $box['id'], ' « ', $box['label'],
         " » : laissé obligatoire (réglé à l'écran depuis — --forcer pour reposer la décision).\n";
}

echo "relecture :\n";
foreach ($db->query("SELECT id, slug, label, ordre, facultatif FROM entrepot_hierarchie_niveau
                     WHERE sync_deleted_at IS NULL ORDER BY ordre") as $r) {
    printf("  %-4s %-12s %-16s ordre %-5s %s\n", $r['id'], $r['slug'], $r['label'], $r['ordre'],
        (int) $r['facultatif'] === 1 ? 'FACULTATIF (peut être sauté)' : '');
}
