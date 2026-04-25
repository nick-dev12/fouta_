<?php
require_once __DIR__ . '/conn/conn.php';
$sql = file_get_contents(__DIR__ . '/migrate_fcm_tokens.sql');
try {
    $db->exec($sql);
    echo "Migration FCM OK\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
