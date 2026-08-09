<?php
/**
 * Refresh COMPLET : production → WAMP → serveur entreprise.
 * Équivalent à : php scripts/sync_prod_to_wamp_and_server.php --full
 */
$_SERVER['argv'] = $argv = array_merge($argv ?? [basename(__FILE__)], ['--full']);
require __DIR__ . '/sync_prod_to_wamp_and_server.php';
