<?php
/**
 * LE RÔLE « photographe » (05/09/2026).
 *
 * Un profil dédié aux PHOTOS des pièces : téléverser / retirer / réordonner
 * les images, vérifier que le détourage rend bien. Accès RESTREINT (aucun
 * prix, stock, fournisseur, vente, structure) — voir includes/admin_route_access.php.
 *
 * (1) ajoute 'photographe' à l'ENUM admin.role, (2) sème le compte de test
 * fpl.photographe@local.test (mot de passe : Photo2026) s'il n'existe pas.
 *
 * Usage : php migrations/run_role_photographe.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';

try {
    /** @var PDO $db */
    $db->exec(
        "ALTER TABLE `admin` MODIFY COLUMN `role` ENUM(
            'admin','gestion_stock','gestion_stock_general','commercial',
            'commercial_general','informaticien','developpeur','comptabilite',
            'rh','caissier','photographe'
        ) NOT NULL DEFAULT 'admin'"
    );
    echo "ENUM admin.role : 'photographe' ajouté.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'ENUM : ' . $e->getMessage() . "\n");
    exit(1);
}

/* Compte de test (base locale de développement uniquement). En production, le
   compte réel se crée par l'écran de gestion des utilisateurs. */
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

echo "Migration rôle photographe : OK\n";
