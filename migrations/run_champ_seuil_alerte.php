<?php
/**
 * « SEUIL D'ALERTE » DEVIENT UN CHAMP DE LA FICHE PIÈCE (31/08/2026).
 *
 * Décision de la direction : chaque pièce a SON seuil. Pas un chiffre pour
 * tout le magasin — un seuil n'est pas une quantité, c'est un rythme de
 * consommation, et celui d'une plaquette de frein n'a rien à voir avec celui
 * d'un bras de rétroviseur.
 *
 * Le champ entre donc dans le formulaire de la pièce, avec les mêmes règles
 * d'accès que les prix — une seule mécanique pour tout le logiciel :
 *
 *   MODIFIER : responsable stock, administrateur, informaticien, développeur
 *   VOIR     : tous les autres profils. Le rayonniste doit savoir où il en
 *              est ; il ne décide pas du chiffre.
 *
 * Rappel de la règle d'alerte : elle parle dès que le stock est INFÉRIEUR OU
 * ÉGAL au seuil, et tant que le stock n'est pas remonté au-dessus.
 * Case vide = aucun seuil, le logiciel ne dit rien sur cette pièce.
 * Zéro      = préviens-moi seulement quand il n'y en a plus du tout.
 *
 * Idempotent :
 *   php migrations/run_champ_seuil_alerte.php
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$col = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'produits'
                           AND COLUMN_NAME = 'seuil_alerte'")->fetchColumn();
if ($col === 0) {
    echo "colonne produits.seuil_alerte absente — lancez d'abord run_seuil_alerte_piece.php\n";
    exit(1);
}

/* 1. Le champ lui-même (le semeur des champs système le crée s'il manque). */
produit_formulaire_champs_ensure_schema();
$res = produit_formulaire_champs_seed_systeme();
echo 'semis des champs système : ', ($res['message'] ?? 'fait'), "\n";

$champ = produit_formulaire_champ_get_by_slug('seuil_alerte');
if ($champ === null) {
    echo "le champ « seuil_alerte » n'a pas été créé — arrêt.\n";
    exit(1);
}
$cid = (int) $champ['id'];
echo "champ « ", $champ['label'], " » : id $cid, section ", $champ['section'], "\n";

/* 2. Qui voit, qui modifie. */
/* « developpeur » ne se sème plus : le bypass technique le couvre. */
$modifier = ['gestion_stock_general', 'admin', 'informaticien'];
$voir = ['gestion_stock', 'commercial', 'commercial_general', 'caissier', 'comptabilite', 'rh'];

$db->prepare('DELETE FROM produit_formulaire_champ_role WHERE champ_id = :c')->execute([':c' => $cid]);
$poser = $db->prepare('INSERT INTO produit_formulaire_champ_role (champ_id, role, niveau, date_modification)
                       VALUES (:c, :r, :n, NOW())');
foreach ($modifier as $r) {
    $poser->execute([':c' => $cid, ':r' => $r, ':n' => 'modifier']);
}
foreach ($voir as $r) {
    $poser->execute([':c' => $cid, ':r' => $r, ':n' => 'voir']);
}
produit_formulaire_champ_roles_map_reset();
printf("accès posés : %d en modifier, %d en lecture seule\n", count($modifier), count($voir));

/* 3. Relecture. */
$lu = $db->prepare("SELECT niveau, GROUP_CONCAT(role ORDER BY role SEPARATOR ', ') roles
                    FROM produit_formulaire_champ_role WHERE champ_id = :c GROUP BY niveau");
$lu->execute([':c' => $cid]);
foreach ($lu as $row) {
    printf("  %-9s %s\n", $row['niveau'], $row['roles']);
}
echo "Terminé.\n";
