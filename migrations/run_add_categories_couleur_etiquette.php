<?php
/**
 * Couleur d’étiquette FPL stock (bandeaux / logo / pied) par catégorie.
 * php migrations/run_add_categories_couleur_etiquette.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec("
        ALTER TABLE `categories`
            ADD COLUMN `couleur_etiquette` VARCHAR(9) NOT NULL DEFAULT '#1e3a5f'
    ");
    echo "+ colonne categories.couleur_etiquette\n";
} catch (PDOException $e) {
    if (strpos(strtolower($e->getMessage()), 'duplicate column') !== false
        || strpos(strtolower($e->getMessage()), 'déjà utilisé') !== false) {
        echo "— categories.couleur_etiquette existe déjà\n";
    } else {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Migration terminée.\n";
