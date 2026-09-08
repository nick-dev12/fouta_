<?php

/**
 * Configuration de connexion à la base de données
 * Copiez ce fichier en conn.php et modifiez les valeurs selon votre environnement
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$db_host = "localhost";
$db_name = "tresor_afri";
$db_user = "root";
$db_pass = "";

$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $db = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        $pdo_options
    );

    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    /* L'HEURE DE LA BASE = L'HEURE DE DAKAR (08/09/2026). Le VPS tourne en
       Europe/Berlin : son MySQL écrivait NOW() en heure de Paris (+2 h) alors
       que PHP y est en UTC et que foutasvr (la référence) est en UTC. Les
       marques de synchronisation (sync_updated_at) se comparent d'un serveur
       à l'autre : une ligne re-marquée sur le VPS « gagnait » pendant deux
       heures sur tout ce que l'atelier écrivait à Dakar — les anciennes photos
       restaient sur la page du QR. Le Sénégal vit à UTC toute l'année. */
    $db->exec("SET time_zone = '+00:00'");

    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    ini_set('default_charset', 'UTF-8');

} catch (PDOException $e) {
    // Gestion des erreurs
}
