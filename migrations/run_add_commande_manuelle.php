<?php
/**
 * Migration: Support commandes manuelles
 * Exécuter: php migrations/run_add_commande_manuelle.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$columns = [
    'client_nom' => 'VARCHAR(255) NULL DEFAULT NULL',
    'client_prenom' => 'VARCHAR(255) NULL DEFAULT NULL',
    'client_email' => 'VARCHAR(255) NULL DEFAULT NULL',
    'client_telephone' => 'VARCHAR(50) NULL DEFAULT NULL'
];

foreach ($columns as $col => $def) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes LIKE '$col'");
        if ($stmt->rowCount() === 0) {
            $order = ['client_nom' => 'user_id', 'client_prenom' => 'client_nom', 'client_email' => 'client_prenom', 'client_telephone' => 'client_email'];
            $after = $order[$col] ?? 'user_id';
            $db->exec("ALTER TABLE commandes ADD COLUMN `$col` $def AFTER $after");
            echo "Colonne $col ajoutée.\n";
        } else {
            echo "Colonne $col existe déjà.\n";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "Colonne $col existe déjà.\n";
        } else {
            echo "Erreur $col: " . $e->getMessage() . "\n";
        }
    }
}

// Rendre user_id nullable
try {
    $db->exec("ALTER TABLE commandes DROP FOREIGN KEY fk_commandes_user");
    echo "FK supprimée.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'check that it exists') !== false || strpos($e->getMessage(), '1091') !== false) {
        echo "FK déjà absente ou nom différent.\n";
    } else {
        echo "Note FK: " . $e->getMessage() . "\n";
    }
}
try {
    $db->exec("ALTER TABLE commandes MODIFY user_id INT(11) NULL DEFAULT NULL");
    echo "user_id rendu nullable.\n";
} catch (PDOException $e) {
    echo "Note user_id: " . $e->getMessage() . "\n";
}

// Réajouter la FK avec ON DELETE SET NULL pour les commandes manuelles
try {
    $db->exec("ALTER TABLE commandes ADD CONSTRAINT fk_commandes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "FK user_id réajoutée (ON DELETE SET NULL).\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1061') !== false) {
        echo "FK user_id existe déjà.\n";
    } else {
        echo "Note FK: " . $e->getMessage() . "\n";
    }
}

echo "Migration commande manuelle terminée.\n";
