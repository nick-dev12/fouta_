<?php
/**
 * Crée le référentiel entrepôt nommé + colonne produits.entrepot_position_id
 * php migrations/run_create_entrepot_referentiel_nomme.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $sql = file_get_contents(__DIR__ . '/create_entrepot_referentiel_nomme.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL introuvable.');
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }
    echo "+ Tables référentiel entrepôt OK\n";

    $st = $db->query('SHOW COLUMNS FROM produits');
    $cols = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $cols[$r['Field']] = true;
    }
    if (empty($cols['entrepot_position_id'])) {
        $db->exec(
            'ALTER TABLE produits ADD COLUMN entrepot_position_id INT UNSIGNED NULL DEFAULT NULL AFTER barre_rayon'
        );
        echo "+ produits.entrepot_position_id\n";
    } else {
        echo "— produits.entrepot_position_id existe déjà\n";
    }

    try {
        $db->exec(
            'ALTER TABLE produits ADD KEY idx_produits_entrepot_position (entrepot_position_id)'
        );
        echo "+ index idx_produits_entrepot_position\n";
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'Duplicate') === false) {
            throw $e;
        }
    }

    try {
        $db->exec(
            'ALTER TABLE produits ADD CONSTRAINT fk_produits_entrepot_position
             FOREIGN KEY (entrepot_position_id) REFERENCES entrepot_position (id)
             ON DELETE SET NULL ON UPDATE CASCADE'
        );
        echo "+ FK fk_produits_entrepot_position\n";
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'Duplicate') === false && stripos($e->getMessage(), 'already exists') === false) {
            echo "— FK (peut exister) : " . $e->getMessage() . "\n";
        }
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Terminé.\n";
