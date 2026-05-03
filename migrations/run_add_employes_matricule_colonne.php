<?php
/**
 * Colonne dénormalisée employes.matricule + synchro depuis employes_matricules.
 * Exécuter : php migrations/run_add_employes_matricule_colonne.php
 */
require_once __DIR__ . '/../conn/conn.php';

$steps = [
    [
        '-- colonne employes.matricule',
        'ALTER TABLE `employes`
          ADD COLUMN `matricule` VARCHAR(12) NULL DEFAULT NULL
            COMMENT \'Dénormalisé depuis employes_matricules (FPLxxxxxx)\'
          AFTER `photo_chemin`',
    ],
    [
        '-- remplissage depuis employes_matricules',
        'UPDATE `employes` `e`
        INNER JOIN `employes_matricules` `m` ON `m`.`employe_id` = `e`.`id`
        SET `e`.`matricule` = `m`.`matricule`
        WHERE (`e`.`matricule` IS NULL OR `e`.`matricule` <> `m`.`matricule`)',
    ],
    [
        '-- index unique (NULL multiples autorisés)',
        'ALTER TABLE `employes`
          ADD UNIQUE KEY `uq_employes_matricule` (`matricule`)',
    ],
];

foreach ($steps as $pair) {
    list($label, $sql) = $pair;
    try {
        $db->exec($sql);
        echo '+ ' . $label . "\n";
    } catch (PDOException $e) {
        $m = $e->getMessage();
        if (
            stripos($m, 'Duplicate column') !== false
            || stripos($m, 'duplicate column name') !== false
            || stripos($m, '[42S21]') !== false
            || stripos($m, '1060') !== false
            || stripos($m, 'déjà utilisé') !== false
        ) {
            echo '— ' . $label . " (déjà présent)\n";
            continue;
        }
        if (
            stripos($m, 'Duplicate key') !== false
            || stripos($m, 'duplicate key name') !== false
            || stripos($m, '1061') !== false
        ) {
            echo '— ' . $label . " (index déjà présent)\n";
            continue;
        }
        echo 'Erreur ' . $label . ': ' . $m . "\n";
        exit(1);
    }
}

echo "Terminé.\n";
