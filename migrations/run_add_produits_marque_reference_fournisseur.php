<?php
/**
 * Colonnes marque_id et reference_fournisseur sur produits
 * php migrations/run_add_produits_marque_reference_fournisseur.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $st = $db->query('SHOW COLUMNS FROM produits');
    $existing = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Field']] = true;
    }
    $after_cat = !empty($existing['categorie_id']) ? 'AFTER categorie_id' : '';
    if (empty($existing['marque_id'])) {
        $db->exec("ALTER TABLE produits ADD COLUMN marque_id INT UNSIGNED NULL DEFAULT NULL $after_cat");
        echo "+ produits.marque_id\n";
        try {
            $db->exec('CREATE INDEX idx_produits_marque_id ON produits (marque_id)');
        } catch (PDOException $e) {
            // index peut exister ou syntaxe MySQL ancienne
        }
    } else {
        echo "— produits.marque_id existe déjà\n";
    }
    $st2 = $db->query('SHOW COLUMNS FROM produits');
    $existing2 = [];
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing2[$row['Field']] = true;
    }
    if (empty($existing2['reference_fournisseur'])) {
        $after_m = !empty($existing2['marque_id']) ? 'AFTER marque_id' : ($after_cat ? 'AFTER categorie_id' : '');
        $db->exec("ALTER TABLE produits ADD COLUMN reference_fournisseur VARCHAR(120) NULL DEFAULT NULL $after_m");
        echo "+ produits.reference_fournisseur\n";
    } else {
        echo "— produits.reference_fournisseur existe déjà\n";
    }
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'Duplicate column') !== false) {
        echo "— Colonnes déjà présentes.\n";
    } else {
        fwrite(STDERR, "Erreur : $m\n");
        exit(1);
    }
}
echo "Terminé.\n";
