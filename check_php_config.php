<?php
/**
 * Script temporaire pour vérifier la configuration PHP réelle
 * À supprimer après utilisation
 */

echo "<h2>Configuration PHP actuelle</h2>";
echo "<pre>";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "\n";
echo "Fichier php.ini chargé: " . php_ini_loaded_file() . "\n";
echo "Fichier php.ini supplémentaire: " . php_ini_scanned_files() . "\n";
echo "</pre>";

// Convertir les valeurs en bytes pour comparaison
function convertToBytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int) $val;
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

$post_max_bytes = convertToBytes(ini_get('post_max_size'));
$upload_max_bytes = convertToBytes(ini_get('upload_max_filesize'));

echo "<h3>Analyse</h3>";
echo "<pre>";
echo "post_max_size en bytes: " . number_format($post_max_bytes) . "\n";
echo "upload_max_filesize en bytes: " . number_format($upload_max_bytes) . "\n";
echo "\n";
if ($post_max_bytes < 250 * 1024 * 1024) {
    echo "⚠️ ATTENTION: post_max_size (" . ini_get('post_max_size') . ") est trop petit pour une vidéo de 250MB\n";
    echo "   Recommandation: Augmentez à au moins 300M\n";
}
if ($upload_max_bytes < 250 * 1024 * 1024) {
    echo "⚠️ ATTENTION: upload_max_filesize (" . ini_get('upload_max_filesize') . ") est trop petit pour une vidéo de 250MB\n";
    echo "   Recommandation: Augmentez à au moins 300M\n";
}
echo "</pre>";
?>