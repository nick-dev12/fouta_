<?php
/**
 * Migration — champs dynamiques formulaire produit.
 * php migrations/run_create_produit_formulaire_champs.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';

/**
 * @param string $message
 * @return void
 */
function produit_formulaire_champs_migration_log_error($message) {
    if (defined('STDERR')) {
        fwrite(STDERR, $message . "\n");
        return;
    }
    fwrite(STDOUT, $message . "\n");
}

global $db;
if (!$db) {
    produit_formulaire_champs_migration_log_error('Connexion BDD indisponible.');
    exit(1);
}

if (!produit_formulaire_champs_run_migration()) {
    produit_formulaire_champs_migration_log_error('Échec de la migration champs formulaire produit.');
    exit(1);
}

echo "OK: tables produit_formulaire_champ, produit_formulaire_champ_droit, produit_champ_valeur.\n";
echo produit_formulaire_champs_seed_systeme()['message'] . "\n";

exit(0);
