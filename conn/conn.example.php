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

try {
  // Connexion à la base avec PDO
  $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);

  // Définition du mode d'erreur de PDO pour lever des exceptions  
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


} catch (PDOException $e) {
  // Gestion des erreurs
}

?>
