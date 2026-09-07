<?php
/**
 * LE RÔLE « photographe » (05/09/2026, durci le 07/09/2026).
 *
 * Un profil dédié aux PHOTOS des pièces : téléverser / retirer / réordonner
 * les images, vérifier que le détourage rend bien. Accès RESTREINT (aucun
 * prix, stock, fournisseur, vente, structure) — voir includes/admin_route_access.php.
 *
 * (1) ajoute 'photographe' à l'ENUM admin.role EN CONSERVANT les valeurs déjà
 *     présentes sur le serveur (on lit l'ENUM courant, on n'impose pas une liste
 *     figée : un rôle ajouté par ailleurs ne serait jamais écrasé) ;
 * (2) sème le compte de test fpl.photographe@local.test (mot de passe : Photo2026)
 *     UNIQUEMENT si l'on passe --compte-test (base de développement). Sur un
 *     serveur, le vrai compte se crée par l'écran de gestion des utilisateurs.
 *
 * Idempotente : rejouable sans effet.
 *
 * Usage : php migrations/run_role_photographe.php [--compte-test]
 */
require_once dirname(__DIR__) . '/conn/conn.php';

$avec_compte_test = in_array('--compte-test', $argv ?? [], true);

try {
    /** @var PDO $db */
    $col = $db->query("SHOW COLUMNS FROM `admin` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    if (!$col || !preg_match("/^enum\((.*)\)$/i", (string) $col['Type'], $m)) {
        fwrite(STDERR, "ENUM : la colonne admin.role n'est pas un ENUM (" . ($col['Type'] ?? 'absente') . ")\n");
        exit(1);
    }
    $valeurs = array_map(
        static function (string $v): string { return trim($v, "'"); },
        str_getcsv($m[1], ',', "'")
    );
    if (in_array('photographe', $valeurs, true)) {
        echo "ENUM admin.role : 'photographe' déjà présent (" . count($valeurs) . " valeurs), rien à faire.\n";
    } else {
        $valeurs[] = 'photographe';
        $liste = implode(',', array_map(static function (string $v) use ($db): string { return $db->quote($v); }, $valeurs));
        $defaut = $col['Default'] !== null ? ' DEFAULT ' . $db->quote((string) $col['Default']) : '';
        $nul = strtoupper((string) $col['Null']) === 'YES' ? ' NULL' : ' NOT NULL';
        $db->exec("ALTER TABLE `admin` MODIFY COLUMN `role` ENUM($liste)$nul$defaut");
        $apres = $db->query("SHOW COLUMNS FROM `admin` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        if (strpos((string) $apres['Type'], "'photographe'") === false) {
            fwrite(STDERR, "ENUM : la valeur n'a pas été ajoutée (" . $apres['Type'] . ")\n");
            exit(1);
        }
        echo "ENUM admin.role : 'photographe' ajouté (" . count($valeurs) . " valeurs, les " . (count($valeurs) - 1) . " existantes conservées).\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'ENUM : ' . $e->getMessage() . "\n");
    exit(1);
}

if ($avec_compte_test) {
    try {
        $existe = $db->prepare("SELECT COUNT(*) FROM admin WHERE email = :e");
        $existe->execute([':e' => 'fpl.photographe@local.test']);
        if ((int) $existe->fetchColumn() === 0) {
            $ins = $db->prepare(
                "INSERT INTO admin (nom, prenom, email, password, date_creation, statut, role, sync_uuid, sync_updated_at)
                 VALUES ('FPL', 'Photographe', 'fpl.photographe@local.test', :mdp, NOW(), 'actif', 'photographe', UUID(), NOW())"
            );
            $ins->execute([':mdp' => password_hash('Photo2026', PASSWORD_DEFAULT)]);
            echo "Compte de test fpl.photographe@local.test créé (mot de passe : Photo2026).\n";
        } else {
            echo "Compte de test fpl.photographe@local.test déjà présent.\n";
        }
    } catch (PDOException $e) {
        fwrite(STDERR, 'Compte : ' . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    echo "Compte de test : non semé (passer --compte-test sur une base de développement).\n";
}

echo "Migration rôle photographe : OK\n";
