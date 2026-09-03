<?php
/**
 * NOUVEAU CHAMP « PRIX D'ACHAT » = LE COÛT RÉEL (02/09/2026).
 *
 * Décision de la direction, après clarification du sens des prix :
 *  - « Prix grossiste » (colonne `prix_achat`, malgré son nom technique) est
 *    le prix auquel FPL VEND aux grossistes/revendeurs — donc SOUS le prix de
 *    vente au détail. Le personnel y saisissait déjà ce prix (d'où le
 *    renommage du libellé « Prix d'achat » → « Prix grossiste » du 01/09).
 *  - Il manquait le VRAI prix d'achat : ce que la pièce a COÛTÉ à FPL. C'est
 *    ce champ, stocké dans une colonne NEUVE `prix_revient` pour ne pas
 *    réutiliser `prix_achat` (déjà pris par le prix grossiste).
 *
 * Hiérarchie voulue : prix_revient (coût) ≤ prix grossiste < prix de vente < prix entreprise.
 *
 * Droits : ce champ suit la visibilité du « Prix grossiste » (prix_achat) —
 * modifiable par l'administrateur, le responsable stock et l'informaticien,
 * visible par la comptabilité, caché au caissier, au commercial et au
 * rayonniste. Ce partage se fait dans le code (pas de ligne de registre en
 * plus), en s'appuyant sur le droit du champ prix_achat.
 *
 * Idempotent. Rejouable en production.
 *   php migrations/run_prix_revient.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$col = (int) $db->query("SELECT COUNT(*) FROM information_schema.columns
                         WHERE table_schema = DATABASE() AND table_name = 'produits' AND column_name = 'prix_revient'")->fetchColumn();
if ($col === 0) {
    $db->exec("ALTER TABLE produits ADD COLUMN prix_revient DECIMAL(10,2) NULL
               COMMENT 'Prix d''achat reel (cout) — le plus bas ; <= prix grossiste' AFTER prix_achat");
    echo "colonne prix_revient AJOUTÉE\n";
} else {
    echo "colonne prix_revient déjà présente\n";
}

/* le commentaire de prix_entreprise disait faux (« sous le prix public ») :
   en réalité le prix entreprise est AU-DESSUS du prix de vente. */
try {
    $db->exec("ALTER TABLE produits MODIFY COLUMN prix_entreprise DECIMAL(12,2) NULL
               COMMENT 'Tarif des clients professionnels — au-dessus du prix de vente'");
    echo "commentaire prix_entreprise corrigé\n";
} catch (PDOException $e) {
    echo "note : commentaire prix_entreprise inchangé (" . $e->getMessage() . ")\n";
}

$c = $db->query("SELECT column_name AS cn, column_comment AS cc FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'produits'
                 AND column_name IN ('prix_revient','prix_achat','prix','prix_entreprise')
                 ORDER BY FIELD(column_name,'prix_revient','prix_achat','prix','prix_entreprise')")->fetchAll(PDO::FETCH_ASSOC);
echo "relecture des colonnes de prix :\n";
foreach ($c as $r) {
    $r = array_change_key_case($r, CASE_LOWER);
    printf("  %-16s %s\n", (string) ($r['cn'] ?? ''), ($r['cc'] ?? '') !== '' ? '— ' . $r['cc'] : '');
}
