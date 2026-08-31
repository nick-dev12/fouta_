<?php
/**
 * LE SEUIL D'UNE PIÈCE (31/08/2026) — l'exception qui manquait.
 *
 * Jusqu'ici, un seuil d'alerte se posait sur une CATÉGORIE (ou une
 * sous-catégorie) : un seul nombre gouvernait tout un rayon. Dans un magasin
 * de pièces, le boulon et la boîte de vitesses vivent dans la même catégorie
 * et n'ont rien à voir. FPL natif a depuis toujours une surcharge par pièce
 * (`produits.seuil_alerte`) ; on la porte ici.
 *
 * L'ordre de résolution devient :
 *     le seuil de la PIÈCE  →  sinon la règle de SOUS-CATÉGORIE
 *                           →  sinon la règle de CATÉGORIE
 *                           →  sinon la règle GLOBALE  →  sinon rien.
 *
 * Colonne NULL partout au départ : aucune pièce n'a d'exception, rien ne
 * change tant que personne n'en pose une.
 *
 * Idempotent :
 *   php migrations/run_seuil_alerte_piece.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$col = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = 'produits'
                           AND COLUMN_NAME = 'seuil_alerte'")->fetchColumn();

if ($col === 0) {
    $db->exec("ALTER TABLE `produits`
               ADD COLUMN `seuil_alerte` INT UNSIGNED NULL DEFAULT NULL
               COMMENT 'Exception : seuil propre à cette pièce, prime sur les règles' AFTER `stock`");
    echo "colonne `produits`.`seuil_alerte` ajoutée (NULL partout : rien ne change)\n";
} else {
    echo "colonne `produits`.`seuil_alerte` : déjà là\n";
}

$avec = (int) $db->query('SELECT COUNT(*) FROM produits WHERE seuil_alerte IS NOT NULL')->fetchColumn();
echo "pièces ayant leur propre seuil : $avec\n";
echo "Terminé.\n";
