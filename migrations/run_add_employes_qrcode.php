<?php
/** php migrations/run_add_employes_qrcode.php */
require_once __DIR__ . '/../conn/conn.php';

try {
    $sql = trim((string) file_get_contents(__DIR__ . '/add_employes_qrcode.sql'));
    if ($sql !== '') {
        $db->exec($sql);
    }
    echo "+ employes.qr_chemin + qr_payload OK\n";
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'Duplicate column') !== false) {
        echo "— colonnes qr deja presentes\n";
    } else {
        echo 'Erreur: ' . $m . "\n";
        exit(1);
    }
}
