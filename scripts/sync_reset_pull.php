<?php
/**
 * Réinitialise le curseur pull pour forcer une resynchronisation complète.
 * CLI : php scripts/sync_reset_pull.php
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/sync_functions.php';

global $db;

if (!$db instanceof PDO) {
    fwrite(STDERR, "Connexion base indisponible.\n");
    exit(1);
}

sync_ensure_infrastructure($db);
sync_set_state($db, 'last_pull_since', '1970-01-01 00:00:00');
echo "Curseur pull réinitialisé.\n";
