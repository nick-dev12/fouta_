<?php
/**
 * ANNULER UN TICKET DE CAISSE EN ATTENTE (10/09/2026).
 *
 * Un ticket préparé mais jamais encaissé restait « en attente » pour toujours :
 * 24 tickets pour 1 288 854 FCFA entre juin et septembre, dont des doublons
 * d'un ticket déjà encaissé et deux tickets bloqués par une pièce en rupture.
 * Le vendeur qui l'a préparé, ou le caissier, peut désormais l'annuler avec un
 * motif. Rien n'est effacé, rien ne touche au stock : un ticket en attente
 * n'en a jamais sorti.
 *
 * (1) ajoute 'annule' à l'ENUM caisse_ventes.statut EN CONSERVANT les valeurs
 *     déjà présentes sur le serveur (on lit l'ENUM courant, on n'impose pas une
 *     liste figée) ;
 * (2) ajoute les colonnes annule_par, date_annulation et motif_annulation.
 *
 * Idempotente : rejouable sans effet.
 * À JOUER SUR CHAQUE SERVEUR (foutasvr ET VPS) avant d'utiliser l'annulation :
 * la table est synchronisée, un serveur qui ne connaît pas 'annule' refuserait
 * la ligne reçue.
 *
 * Usage : php migrations/run_caisse_ticket_annulation.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';

try {
    /** @var PDO $db */
    $col = $db->query("SHOW COLUMNS FROM `caisse_ventes` LIKE 'statut'")->fetch(PDO::FETCH_ASSOC);
    if (!$col || !preg_match("/^enum\((.*)\)$/i", (string) $col['Type'], $m)) {
        fwrite(STDERR, "caisse_ventes.statut n'est pas un ENUM (" . ($col['Type'] ?? 'absente') . ")\n");
        exit(1);
    }
    $valeurs = array_map(
        static function (string $v): string { return trim($v, "'"); },
        str_getcsv($m[1], ',', "'")
    );
    if (in_array('annule', $valeurs, true)) {
        echo "ENUM caisse_ventes.statut : 'annule' déjà présent (" . count($valeurs) . " valeurs), rien à faire.\n";
    } else {
        $valeurs[] = 'annule';
        $liste = implode(',', array_map(static function (string $v) use ($db): string { return $db->quote($v); }, $valeurs));
        $defaut = $col['Default'] !== null ? ' DEFAULT ' . $db->quote((string) $col['Default']) : '';
        $nul = strtoupper((string) $col['Null']) === 'YES' ? ' NULL' : ' NOT NULL';
        $db->exec("ALTER TABLE `caisse_ventes` MODIFY COLUMN `statut` ENUM($liste)$nul$defaut");
        $apres = $db->query("SHOW COLUMNS FROM `caisse_ventes` LIKE 'statut'")->fetch(PDO::FETCH_ASSOC);
        if (strpos((string) $apres['Type'], "'annule'") === false) {
            fwrite(STDERR, "ENUM caisse_ventes.statut : la valeur n'a pas été ajoutée (" . $apres['Type'] . ")\n");
            exit(1);
        }
        echo "ENUM caisse_ventes.statut : 'annule' ajouté (" . count($valeurs) . " valeurs, les " . (count($valeurs) - 1) . " existantes conservées).\n";
    }

    $colonnes = [
        'annule_par' => 'INT NULL DEFAULT NULL',
        'date_annulation' => 'DATETIME NULL DEFAULT NULL',
        'motif_annulation' => 'VARCHAR(255) NULL DEFAULT NULL',
    ];
    foreach ($colonnes as $nom => $definition) {
        $existe = $db->query('SHOW COLUMNS FROM `caisse_ventes` LIKE ' . $db->quote($nom))->fetch();
        if ($existe) {
            echo "Colonne caisse_ventes.$nom : déjà présente.\n";
            continue;
        }
        $db->exec("ALTER TABLE `caisse_ventes` ADD COLUMN `$nom` $definition");
        echo "Colonne caisse_ventes.$nom : ajoutée.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'caisse_ventes : ' . $e->getMessage() . "\n");
    exit(1);
}
