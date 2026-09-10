<?php
/**
 * LE STATUT « PAYÉE » DES COMMANDES DU SITE (10/09/2026).
 *
 * Le code met une commande à « paye » pour sortir sa marchandise du stock, mais
 * la base locale ne connaissait pas cette valeur : en mode strict, chaque
 * passage à « payée » échouait et était annulé. Aucune commande du site ne
 * pouvait aboutir. La migration SQL add_statut_paye_commandes.sql existait mais
 * imposait une liste figée de statuts.
 *
 * Ajoute 'paye' à l'ENUM commandes.statut EN CONSERVANT les valeurs déjà
 * présentes sur le serveur (on lit l'ENUM courant, on n'impose pas une liste).
 *
 * Idempotente. À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS) : la table est
 * synchronisée.
 *
 * Usage : php migrations/run_commandes_statut_paye.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';

try {
    /** @var PDO $db */
    $col = $db->query("SHOW COLUMNS FROM `commandes` LIKE 'statut'")->fetch(PDO::FETCH_ASSOC);
    if (!$col || !preg_match("/^enum\((.*)\)$/i", (string) $col['Type'], $m)) {
        fwrite(STDERR, "commandes.statut n'est pas un ENUM (" . ($col['Type'] ?? 'absente') . ")\n");
        exit(1);
    }
    $valeurs = array_map(
        static function (string $v): string { return trim($v, "'"); },
        str_getcsv($m[1], ',', "'")
    );
    if (in_array('paye', $valeurs, true)) {
        echo "ENUM commandes.statut : 'paye' déjà présent (" . count($valeurs) . " valeurs), rien à faire.\n";
        exit(0);
    }
    // « payée » se range juste avant « annulée » quand elle existe, sinon à la fin.
    $position = array_search('annulee', $valeurs, true);
    if ($position === false) {
        $valeurs[] = 'paye';
    } else {
        array_splice($valeurs, (int) $position, 0, ['paye']);
    }
    $liste = implode(',', array_map(static function (string $v) use ($db): string { return $db->quote($v); }, $valeurs));
    $defaut = $col['Default'] !== null ? ' DEFAULT ' . $db->quote((string) $col['Default']) : '';
    $nul = strtoupper((string) $col['Null']) === 'YES' ? ' NULL' : ' NOT NULL';
    $db->exec("ALTER TABLE `commandes` MODIFY COLUMN `statut` ENUM($liste)$nul$defaut");
    $apres = $db->query("SHOW COLUMNS FROM `commandes` LIKE 'statut'")->fetch(PDO::FETCH_ASSOC);
    if (strpos((string) $apres['Type'], "'paye'") === false) {
        fwrite(STDERR, "ENUM commandes.statut : la valeur n'a pas été ajoutée (" . $apres['Type'] . ")\n");
        exit(1);
    }
    echo "ENUM commandes.statut : 'paye' ajouté (" . count($valeurs) . " valeurs, les " . (count($valeurs) - 1) . " existantes conservées).\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'commandes : ' . $e->getMessage() . "\n");
    exit(1);
}
