<?php

/**
 * Configuration de connexion à la base de données
 * Copiez ce fichier en conn.php et modifiez les valeurs selon votre environnement
 */

// Charger l'autoload Composer (PHPMailer, Firebase, etc.)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Paramètres de connexion
$db_host = "localhost";
$db_name = "tresor_afri";
$db_user = "root";
$db_pass = "";

$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    $db = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        $pdo_options
    );

    $db->exec("SET CHARACTER SET utf8mb4");
    $db->exec("SET collation_connection = 'utf8mb4_unicode_ci'");

} catch (PDOException $e) {
    // Gestion des erreurs
}
