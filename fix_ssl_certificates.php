<?php
/**
 * Script pour corriger l'erreur cURL 60 (SSL certificate)
 * Exécuter depuis la ligne de commande : php fix_ssl_certificates.php
 * Ou ouvrir dans le navigateur : http://localhost:5000/fix_ssl_certificates.php
 */

$cacert_url = 'https://curl.se/ca/cacert.pem';
$cacert_path = __DIR__ . '/config/cacert.pem';

echo "<pre>\n";
echo "=== Correction SSL cURL (erreur 60) ===\n\n";

// Créer le dossier config si nécessaire
if (!is_dir(__DIR__ . '/config')) {
    mkdir(__DIR__ . '/config', 0755, true);
}

// Télécharger cacert.pem
echo "1. Téléchargement de cacert.pem...\n";

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$content = @file_get_contents($cacert_url, false, $context);

if ($content && strlen($content) > 1000) {
    file_put_contents($cacert_path, $content);
    echo "   OK - Fichier enregistré dans config/cacert.pem\n\n";
} else {
    echo "   ECHEC - Téléchargement automatique impossible.\n\n";
    echo "   Téléchargez manuellement :\n";
    echo "   1. Allez sur https://curl.se/ca/cacert.pem\n";
    echo "   2. Clic droit > Enregistrer sous\n";
    echo "   3. Sauvegardez dans : " . $cacert_path . "\n\n";
}

// Vérifier si le fichier existe
if (file_exists($cacert_path)) {
    $realpath = realpath($cacert_path);
    echo "2. Fichier trouvé : $realpath\n\n";
    echo "3. Configuration php.ini (RECOMMANDE) :\n";
    echo "   Ouvrez votre fichier php.ini (WAMP : Clic sur l'icône > PHP > php.ini)\n";
    echo "   Ajoutez ou modifiez ces lignes :\n\n";
    echo "   [curl]\n";
    echo "   curl.cainfo = \"" . str_replace('\\', '/', $realpath) . "\"\n\n";
    echo "   [openssl]\n";
    echo "   openssl.cafile = \"" . str_replace('\\', '/', $realpath) . "\"\n\n";
    echo "   Puis redémarrez Apache (WAMP).\n\n";
    echo "   Le projet est déjà configuré pour utiliser config/cacert.pem.\n";
} else {
    echo "2. Le fichier config/cacert.pem n'existe pas.\n";
    echo "   Téléchargez-le manuellement depuis https://curl.se/ca/cacert.pem\n";
}

echo "\n=== Fin ===\n";
echo "</pre>";
