<?php
/**
 * Hooks optionnels de synchronisation (complément aux triggers MySQL).
 * Les triggers créés par migrations/run_add_sync_columns.php couvrent la majorité des cas.
 */

require_once __DIR__ . '/sync_functions.php';

if (!function_exists('sync_touch_record')) {
    // Déjà défini dans sync_functions.php — fichier conservé pour inclusion explicite si besoin.
}
