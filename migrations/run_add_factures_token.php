<?php
/**
 * Migration: Ajouter colonne token à factures (accès public sécurisé)
 * Exécuter: php migrations/run_add_factures_token.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $db->exec("ALTER TABLE factures ADD COLUMN token VARCHAR(64) NULL UNIQUE AFTER date_creation");
    echo "Colonne token ajoutée.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Colonne token existe déjà.\n";
    } else {
        echo "Erreur: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Générer des tokens pour les factures existantes
try {
    $stmt = $db->query("SELECT id FROM factures WHERE token IS NULL OR token = ''");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $upd = $db->prepare("UPDATE factures SET token = :token WHERE id = :id");
    foreach ($rows as $r) {
        $upd->execute(['token' => bin2hex(random_bytes(32)), 'id' => $r['id']]);
    }
    if (count($rows) > 0) {
        echo count($rows) . " facture(s) mise(s) à jour avec un token.\n";
    }
} catch (PDOException $e) {
    echo "Note: " . $e->getMessage() . "\n";
}
