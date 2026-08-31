<?php
/**
 * LA MAIN L'EMPORTE SUR LE CALCUL (31/08/2026).
 *
 * Le seuil d'une pièce peut venir de deux endroits : de quelqu'un qui l'a
 * posé en connaissance de cause, ou du calcul sur les ventes. Tant qu'on ne
 * savait pas d'où il venait, appliquer les suggestions écrasait tout —
 * y compris les chiffres décidés à la main.
 *
 * Cette colonne dit l'origine :
 *   'manuel'     → posé par une personne. Le calcul ne l'écrase JAMAIS.
 *   'suggestion' → posé par le calcul. Il peut être recalculé plus tard.
 *
 * Décision de la direction du 31/08 : au début, on règle à la main. La base
 * ne compte que quelques dizaines de ventes — une moyenne sur si peu ne
 * vaut pas un avis d'homme de métier. Le calcul reste là pour le jour où la
 * caisse aura tourné.
 *
 * Idempotent :
 *   php migrations/run_seuil_alerte_source.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$col_seuil = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'produits'
                                 AND COLUMN_NAME = 'seuil_alerte'")->fetchColumn();
if ($col_seuil === 0) {
    echo "colonne `seuil_alerte` absente — lancez d'abord migrations/run_seuil_alerte_piece.php\n";
    exit(1);
}

$col = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'produits'
                           AND COLUMN_NAME = 'seuil_alerte_source'")->fetchColumn();
if ($col === 0) {
    $db->exec("ALTER TABLE `produits`
               ADD COLUMN `seuil_alerte_source` ENUM('manuel','suggestion') NULL DEFAULT NULL
               COMMENT 'D ou vient le seuil de la piece : la main ou le calcul' AFTER `seuil_alerte`");
    echo "colonne `produits`.`seuil_alerte_source` ajoutée\n";
} else {
    echo "colonne `produits`.`seuil_alerte_source` : déjà là\n";
}

/* Un seuil déjà posé sans origine connue est réputé posé à la main : dans le
   doute, on protège le travail de la personne, jamais celui de la machine. */
$repris = $db->exec("UPDATE produits SET seuil_alerte_source = 'manuel'
                     WHERE seuil_alerte IS NOT NULL AND seuil_alerte_source IS NULL");
echo "seuils existants repris comme « posés à la main » : $repris\n";

$compte = $db->query("SELECT COALESCE(seuil_alerte_source, 'aucun') AS src, COUNT(*) n
                      FROM produits WHERE seuil_alerte IS NOT NULL GROUP BY src");
foreach ($compte as $row) {
    printf("  %-12s %d\n", $row['src'], $row['n']);
}
echo "Terminé.\n";
