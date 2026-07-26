<?php
/**
 * Table entrepot_etiquette_parametres — dimensions d’impression des étiquettes.
 */
require_once __DIR__ . '/../conn/conn.php';

if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    return;
}

try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS `entrepot_etiquette_parametres` (
            `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `largeur_mm` DECIMAL(5,1) NOT NULL DEFAULT 90.0,
            `hauteur_mm` DECIMAL(5,1) NOT NULL DEFAULT 40.0,
            `qr_mm` DECIMAL(5,1) NOT NULL DEFAULT 30.0,
            `texte_mm` DECIMAL(5,1) NOT NULL DEFAULT 11.0,
            `date_modification` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK: table entrepot_etiquette_parametres.\n";

    $exists = (int) $db->query('SELECT COUNT(*) FROM entrepot_etiquette_parametres WHERE id = 1')->fetchColumn();
    if ($exists === 0) {
        $db->exec(
            'INSERT INTO entrepot_etiquette_parametres (id, largeur_mm, hauteur_mm, qr_mm, texte_mm, date_modification)
             VALUES (1, 90.0, 40.0, 30.0, 11.0, NOW())'
        );
        echo "OK: ligne défaut 90×40 mm.\n";
    } else {
        echo "— ligne id=1 déjà présente.\n";
    }
} catch (PDOException $e) {
    echo 'ERREUR: ' . $e->getMessage() . "\n";
}
