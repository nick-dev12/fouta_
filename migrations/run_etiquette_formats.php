<?php
/**
 * LES FORMATS D'ÉTIQUETTE — les tailles proposées au moment d'imprimer
 * (24/08/2026). Même table que chez FPL natif (`etiquette_formats`), semée
 * avec les mêmes tailles : pièce 70×70 (modèle de la charte), 50×30,
 * 165×100, 130×100 — et barre 90×40.
 *
 * Table NEUVE, aucune donnée touchée. Se rejoue sans risque :
 *   php migrations/run_etiquette_formats.php
 */

require_once __DIR__ . '/../conn/conn.php';

/** @var PDO $db */
echo 'Base : ', $db->query('SELECT DATABASE()')->fetchColumn(), "\n";

$existe = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etiquette_formats'");
$existe->execute();
if ((int) $existe->fetchColumn() === 0) {
    $db->exec("CREATE TABLE `etiquette_formats` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `nom` VARCHAR(60) NOT NULL,
        `type` VARCHAR(10) NULL DEFAULT NULL COMMENT 'piece ou barre',
        `largeur_mm` DECIMAL(6,2) NOT NULL,
        `hauteur_mm` DECIMAL(6,2) NOT NULL,
        `est_systeme` TINYINT(1) NOT NULL DEFAULT 0,
        `disposition_barre` JSON NULL DEFAULT NULL,
        `ordre` INT UNSIGNED NULL DEFAULT NULL,
        `date_creation` DATETIME NULL DEFAULT NULL,
        `date_modification` DATETIME NULL DEFAULT NULL,
        `sync_uuid` CHAR(36) NULL DEFAULT NULL,
        `sync_updated_at` DATETIME NULL DEFAULT NULL,
        `sync_deleted_at` DATETIME NULL DEFAULT NULL,
        `sync_origin_node` VARCHAR(64) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sync_uuid` (`sync_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "etiquette_formats : table créée\n";
} else {
    echo "etiquette_formats : déjà là\n";
}

$n = (int) $db->query('SELECT COUNT(*) FROM etiquette_formats')->fetchColumn();
if ($n === 0) {
    $ins = $db->prepare("INSERT INTO etiquette_formats
        (nom, type, largeur_mm, hauteur_mm, est_systeme, ordre, date_creation, date_modification, sync_uuid)
        VALUES (:nom, :type, :l, :h, 1, :o, NOW(), NOW(), UUID())");
    foreach ([
        ['70 × 70 mm', 'piece', 70, 70, 0],
        ['50 × 30 mm', 'piece', 50, 30, 1],
        ['165 × 100 mm', 'piece', 165, 100, 2],
        ['130 × 100 mm', 'piece', 130, 100, 3],
        ['90 × 40 mm', 'barre', 90, 40, 0],
    ] as $f) {
        $ins->execute(['nom' => $f[0], 'type' => $f[1], 'l' => $f[2], 'h' => $f[3], 'o' => $f[4]]);
    }
    echo "5 formats semés (4 pièce + 1 barre)\n";
} else {
    echo "formats déjà présents : $n\n";
}
