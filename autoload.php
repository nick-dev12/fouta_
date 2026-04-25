<?php

// fichier autoload.php

// Définir un namespace racine pour l'application
define('APP_NS', 'App');

// Définir le répertoire racine de l'application
define('APP_PATH', __DIR__ . '/');

// Fonction d'autoload enregistrée avec spl_autoload_register()
function autoload($className) {

  // Construire le chemin à partir du namespace
  $classPath = str_replace(APP_NS, '', $className);
  $file = APP_PATH . str_replace('\\', '/', $classPath) . '.php';

  // Charger le fichier
  if(file_exists($file)) {
    require $file;
  }

}

// Enregistrer la fonction d'autoload
spl_autoload_register('autoload');
?>