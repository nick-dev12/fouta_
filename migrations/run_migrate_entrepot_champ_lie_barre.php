<?php
/**
 * Migration lie_barre + champ_element_id sur barres.
 * php migrations/run_migrate_entrepot_champ_lie_barre.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

require_once __DIR__ . '/../models/model_entrepot_structure_champs.php';

entrepot_structure_champ_ensure_lie_barre_schema();
entrepot_barre_ensure_champ_element_schema();

echo "+ lie_barre / champ_element_id OK\n";
echo "Terminé.\n";
